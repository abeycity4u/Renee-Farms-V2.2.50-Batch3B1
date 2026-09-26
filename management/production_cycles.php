<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../includes/functions.php');
require_once(__DIR__ . '/../includes/audit_helpers.php');
require_once(__DIR__ . '/../lib/poultry_cycle_lifecycle.php');
require_once(__DIR__ . '/../lib/poultry_cycle_acquisition.php');
require_once(__DIR__ . '/../lib/poultry_cycle_onboarding.php');
require_once(__DIR__ . '/../lib/production_cycle_service.php');
require_once(__DIR__ . '/../lib/production_population_intelligence.php');
requireLogin();
requireBusinessReportAccess();
$tenantFarmId = requireCurrentFarmId();
if (!isPlatformOwner() && !hasRole('farm_admin') && !hasPermission(getUserType(), 'production_cycles')) { header('Location: ' . BASE_URL . '/no_access.php'); exit(); }

$cycleTableExists = false;
$stockBatchTableExists = false;
$poultryPhaseTableExists = false;
$poultryAcquisitionTableExists = false;
$poultryAcquisitionCorrectionReady = false;
$migration002Recorded = false;
$migration037Recorded = false;
$migration038Recorded = false;
$migration039Recorded = false;
$errorMessage = null;
$flash = null;

$populationBaselineTableExists = false;

$summary = [
    'active_cycles' => 0,
    'planned_cycles' => 0,
    'closed_cycles' => 0,
    'stock_batches' => 0,
    'total_current_stock' => 0,
];
$recentCycles = [];
$poultryCycles = [];
$activeCycles = [];
$closedCycleDetails = [];
$recentStockBatches = [];
$poultryPhaseHistoryByCycle = [];
$poultryAcquisitionHistoryByCycle = [];

// Preserve the Create Cycle form after validation/duplicate errors so the user
// can correct only the problematic field instead of re-entering everything.
$createCycleForm = [
    'cycle_code' => '',
    'farm_type' => 'poultry',
    'production_type' => 'layer',
    'start_date' => '',
    'expected_end_date' => '',
    'opening_headcount' => '0',
    'start_age_days' => '1',
    'poultry_acquisition_type' => 'purchased',
    'poultry_total_cost' => '',
    'poultry_source_name' => '',
    'poultry_reference_no' => '',
    'poultry_initial_phase' => 'rearing',
    'notes' => '',
];

$productionCyclesPrgKey =
    'production_cycles_prg';

$productionCyclesPrgAnchors = [
    'create_cycle' =>
        '#create-cycle',
    'post_batch' =>
        '#cycle-tools',
];

$productionCyclesPrgRedirect = static function (
    string $action,
    ?array $flashState,
    array $createFormState
) use (
    $productionCyclesPrgKey,
    $productionCyclesPrgAnchors
): void {
    $_SESSION[$productionCyclesPrgKey] = [
        'flash' =>
            $flashState,
        'create_form' =>
            $createFormState,
    ];

    $anchor =
        $productionCyclesPrgAnchors[$action]
        ?? '';

    header(
        'Location: '
        . BASE_URL
        . '/management/production_cycles.php'
        . $anchor,
        true,
        303
    );

    exit();
};

if (
    $_SERVER['REQUEST_METHOD'] === 'GET'
    && isset($_SESSION[$productionCyclesPrgKey])
    && is_array($_SESSION[$productionCyclesPrgKey])
) {
    $productionCyclesPrg =
        $_SESSION[$productionCyclesPrgKey];

    unset(
        $_SESSION[$productionCyclesPrgKey]
    );

    if (
        isset($productionCyclesPrg['flash'])
        && is_array($productionCyclesPrg['flash'])
    ) {
        $flash =
            $productionCyclesPrg['flash'];
    }

    if (
        isset($productionCyclesPrg['create_form'])
        && is_array(
            $productionCyclesPrg['create_form']
        )
    ) {
        foreach (
            array_keys($createCycleForm)
            as $createCycleField
        ) {
            if (
                array_key_exists(
                    $createCycleField,
                    $productionCyclesPrg['create_form']
                )
            ) {
                $createCycleForm[$createCycleField] =
                    (string)
                    $productionCyclesPrg['create_form']
                        [$createCycleField];
            }
        }
    }
}

try {
    $cycleTableExists = ($pdo->query("SHOW TABLES LIKE 'production_cycles'")->rowCount() > 0);
    $populationBaselineTableExists = ($pdo->query("SHOW TABLES LIKE 'production_population_baselines'")->rowCount() > 0);
    $stockBatchTableExists = ($pdo->query("SHOW TABLES LIKE 'stock_batches'")->rowCount() > 0);
    $poultryPhaseTableExists = ($pdo->query("SHOW TABLES LIKE 'production_cycle_phases'")->rowCount() > 0);
    $poultryAcquisitionTableExists = ($pdo->query("SHOW TABLES LIKE 'poultry_cycle_acquisitions'")->rowCount() > 0);
    if ($poultryAcquisitionTableExists) {
        $poultryAcquisitionCorrectionReady = ($pdo->query("SHOW COLUMNS FROM poultry_cycle_acquisitions LIKE 'request_token'")->rowCount() > 0)
            && ($pdo->query("SHOW COLUMNS FROM poultry_cycle_acquisitions LIKE 'voided_at'")->rowCount() > 0);
    }
    $migrationCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM schema_migrations WHERE filename = ?");
    $migrationCheckStmt->execute(['002_production_cycles.sql']);
    $migration002Recorded = ((int)$migrationCheckStmt->fetchColumn() > 0);
    $migrationCheckStmt->execute(['037_poultry_cycle_phase_history.sql']);
    $migration037Recorded = ((int)$migrationCheckStmt->fetchColumn() > 0);
    $migrationCheckStmt->execute(['038_poultry_cycle_acquisition.sql']);
    $migration038Recorded = ((int)$migrationCheckStmt->fetchColumn() > 0);
    $migrationCheckStmt->execute(['039_poultry_acquisition_submission_corrections.sql']);
    $migration039Recorded = ((int)$migrationCheckStmt->fetchColumn() > 0);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $cycleTableExists) {
        if (!(isPlatformOwner() || hasRole('farm_admin'))) { http_response_code(403); exit('Production-cycle management access required.'); }
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { http_response_code(419); exit('Invalid request token.'); }
        $action = $_POST['action'] ?? '';

        if ($action === 'create_cycle') {
            $cycleCode = trim((string)($_POST['cycle_code'] ?? ''));
            $farmType = strtolower(trim((string)($_POST['farm_type'] ?? '')));
            $productionType = strtolower(trim((string)($_POST['production_type'] ?? '')));
            $startDate = trim((string)($_POST['start_date'] ?? ''));
            $expectedEndDate = trim((string)($_POST['expected_end_date'] ?? ''));
            $openingHeadcountRaw = trim((string)($_POST['opening_headcount'] ?? '0'));
            $startAgeDaysRaw = trim((string)($_POST['start_age_days'] ?? '1'));
            $poultryAcquisitionType = strtolower(trim((string)($_POST['poultry_acquisition_type'] ?? '')));
            $poultryTotalCostRaw = trim((string)($_POST['poultry_total_cost'] ?? ''));
            $poultrySourceName = trim((string)($_POST['poultry_source_name'] ?? ''));
            $poultryReferenceNo = trim((string)($_POST['poultry_reference_no'] ?? ''));
            $poultryInitialPhase = strtolower(trim((string)($_POST['poultry_initial_phase'] ?? '')));
            $poultryRequestToken = strtolower(trim((string)($_POST['poultry_request_token'] ?? '')));
            $notes = trim((string)($_POST['notes'] ?? ''));

            $createCycleForm = [
                'cycle_code' => $cycleCode,
                'farm_type' => $farmType !== '' ? $farmType : 'poultry',
                'production_type' => $productionType !== '' ? $productionType : 'layer',
                'start_date' => $startDate,
                'expected_end_date' => $expectedEndDate,
                'opening_headcount' => $openingHeadcountRaw,
                'start_age_days' => $startAgeDaysRaw,
                'poultry_acquisition_type' => $poultryAcquisitionType,
                'poultry_total_cost' => $poultryTotalCostRaw,
                'poultry_source_name' => $poultrySourceName,
                'poultry_reference_no' => $poultryReferenceNo,
                'poultry_initial_phase' => $poultryInitialPhase,
                'notes' => $notes,
            ];

            $openingHeadcountForPricing =
                filter_var(
                    $openingHeadcountRaw,
                    FILTER_VALIDATE_INT
                );

            $startAgeDays =
                filter_var(
                    $startAgeDaysRaw,
                    FILTER_VALIDATE_INT
                );
            $poultryTotalCost = $poultryTotalCostRaw === ''
                ? null
                : filter_var($poultryTotalCostRaw, FILTER_VALIDATE_FLOAT);

            /*
             * New poultry-cycle economics have one authoritative input:
             * total acquisition cost.
             *
             * Bird Cost Basis remains an internal mortality-valuation fact
             * and is derived from total acquisition cost / opening flock.
             */
            $derivedBirdUnitCost = null;
            if (
                $farmType === 'poultry'
                && $poultryTotalCost !== null
                && $poultryTotalCost !== false
                && $openingHeadcountForPricing !== false
            ) {
                $derivedBirdUnitCost = poultry_acquisition_cost_per_bird(
                    (float)$poultryTotalCost,
                    (int)$openingHeadcountForPricing
                );
            }

            /*
             * Cycle identity, dates, opening population, bird cost basis,
             * duplicate-code policy and canonical opening baseline belong
             * to production_cycle_create_v3().
             *
             * This adapter keeps only poultry-onboarding-specific input
             * checks before entering the atomic setup transaction.
             */
            if (
                $farmType === 'poultry'
                && (
                    $startAgeDays === false
                    || $startAgeDays < 1
                )
            ) {
                $flash = [
                    'type' => 'danger',
                    'message' => 'Start age must be at least 1 day.',
                    'title' => 'Invalid start age.',
                ];
            } elseif (
                $farmType === 'poultry'
                && $poultryTotalCostRaw !== ''
                && (
                    $poultryTotalCost === false
                    || (float)$poultryTotalCost < 0
                )
            ) {
                $flash = [
                    'type' => 'danger',
                    'message' => 'Enter a valid total bird acquisition amount.',
                    'title' => 'Invalid poultry acquisition amount.',
                ];
            } else {
                try {
                    $pdo->beginTransaction();

                    $newCycleId =
                        production_cycle_create_v3(
                            $pdo,
                            $tenantFarmId,
                            [
                                'cycle_code' =>
                                    $cycleCode,

                                'farm_type' =>
                                    $farmType,

                                'production_type' =>
                                    $productionType,

                                'start_date' =>
                                    $startDate,

                                'expected_end_date' =>
                                    $expectedEndDate,

                                'opening_headcount' =>
                                    $openingHeadcountRaw,

                                'bird_unit_cost' =>
                                    $derivedBirdUnitCost,

                                'notes' =>
                                    $notes,
                            ],
                            isset($_SESSION['user_id'])
                                ? (int)$_SESSION['user_id']
                                : null
                        );

                    /*
                     * Safe to normalize for the route-owned Daily seed and
                     * poultry onboarding only after the canonical service
                     * accepted the opening population.
                     */
                    $openingHeadcount =
                        (int)$openingHeadcountRaw;

                        // Seed opening record on cycle start date so daily pages can continue immediately.
                        if ($farmType === 'poultry' && $productionType === 'layer') {
                            $seedStmt = $pdo->prepare(
                                'INSERT INTO layer_daily_records
                                (farm_id, cycle_id, record_date, opening_stock, mortality, feed_consumption_bags, water_consumption_liters, medications, egg_production, crates_count, laying_rate, birds_age, remarks, user_id)
                                VALUES (?, ?, ?, ?, 0, 0, 0, NULL, 0, 0, 0, ?, ?, ?)'
                            );
                            $seedStmt->execute([$tenantFarmId, $newCycleId, $startDate, $openingHeadcount, max(1, $startAgeDays), 'Auto-created from Production Cycle opening stock.', $_SESSION['user_id'] ?? null]);
                        } elseif ($farmType === 'poultry' && $productionType === 'broiler') {
                            $seedStmt = $pdo->prepare(
                                'INSERT INTO broiler_daily_records
                                (farm_id, cycle_id, record_date, opening_stock, mortality, feed_consumption_bags, water_consumption_liters, medications, birds_age, remarks, user_id)
                                VALUES (?, ?, ?, ?, 0, 0, 0, NULL, ?, ?, ?)'
                            );
                            $seedStmt->execute([$tenantFarmId, $newCycleId, $startDate, $openingHeadcount, max(1, $startAgeDays), 'Auto-created from Production Cycle opening stock.', $_SESSION['user_id'] ?? null]);
                        } elseif ($farmType === 'ruminant') {
                            $seedStmt = $pdo->prepare(
                                'INSERT INTO ruminant_daily_records
                                (farm_id, cycle_id, record_date, animal_type, opening_stock, mortality, feed_consumption_kg, water_consumption_liters, other_details, tag_no, medications, reproduction_details, remarks, user_id)
                                VALUES (?, ?, ?, ?, ?, 0, 0, 0, NULL, NULL, NULL, NULL, ?, ?)'
                            );
                            $seedStmt->execute([$tenantFarmId, $newCycleId, $startDate, $productionType, $openingHeadcount, 'Auto-created from Production Cycle opening stock.', $_SESSION['user_id'] ?? null]);
                        }

                        if ($farmType === 'poultry') {
                            poultry_cycle_onboarding_record_initial(
                                $pdo,
                                $tenantFarmId,
                                $newCycleId,
                                [
                                    'production_type' => $productionType,
                                    'start_date' => $startDate,
                                    'quantity' => (int)$openingHeadcount,
                                    'age_days' => max(1, (int)$startAgeDays),
                                    'acquisition_type' => $poultryAcquisitionType,
                                    'total_cost' => $poultryTotalCost === null
                                        ? null
                                        : (float)$poultryTotalCost,
                                    'source_name' => $poultrySourceName,
                                    'reference_no' => $poultryReferenceNo,
                                    'initial_phase' => $poultryInitialPhase,
                                    'request_token' => $poultryRequestToken,
                                ],
                                isset($_SESSION['user_id'])
                                    ? (int)$_SESSION['user_id']
                                    : null
                            );
                        }

                        $pdo->commit();

                        if ($farmType === 'poultry') {
                            $_SESSION['success'] =
                                'Poultry cycle created. Flock entry and starting biological stage were recorded.';
                            header(
                                'Location: '
                                . BASE_URL
                                . '/management/poultry_cycle.php?id='
                                . $newCycleId
                            );
                            exit();
                        }

                        $createCycleForm = [
                            'cycle_code' => '', 'farm_type' => $farmType, 'production_type' => $productionType,
                            'start_date' => '',
                            'expected_end_date' => '',
                            'opening_headcount' => '0',
                                                    'start_age_days' => '1',
                            'poultry_acquisition_type' => 'purchased',
                                                    'poultry_total_cost' => '',
                            'poultry_source_name' => '',
                            'poultry_reference_no' => '',
                            'poultry_initial_phase' => 'rearing',
                            'notes' => '',
                        ];
                        $flash = ['type' => 'success', 'message' => 'Production cycle created successfully.'];
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) { $pdo->rollBack(); }
                        if (
                            $e instanceof InvalidArgumentException
                            || $e instanceof ProductionCycleException
                            || $e instanceof PoultryAcquisitionException
                            || $e instanceof PoultryLifecycleException
                        ) {
                            $flash = [
                                'type' => 'danger',
                                'title' =>
                                    'Production cycle setup could not be completed.',
                                'message' =>
                                    $e->getMessage(),
                                'tip' =>
                                    'No cycle or related setup record was saved. '
                                    . 'Correct the entry and try again.',
                            ];
                        } else {
                            error_log('Production cycle creation failed: ' . $e->getMessage());
                            $flash = [
                                'type' => 'danger',
                                'title' => 'Cycle could not be created.',
                                'message' => 'We could not create this production cycle right now. No changes were saved.',
                                'tip' => 'Please review the entries and try again. If the problem continues, contact your platform administrator.',
                            ];
                        }
                    }
            }
        }

        if ($action === 'post_batch' && $stockBatchTableExists) {
            $cycleId = (int)($_POST['cycle_id'] ?? 0);
            $itemDescription = trim((string)($_POST['item_description'] ?? ''));
            $quantity = (int)($_POST['quantity'] ?? 0);
            $unitCost = (float)($_POST['unit_cost'] ?? 0);
            $receivedDate = $_POST['received_date'] ?? '';
            $supplierName = trim((string)($_POST['supplier_name'] ?? ''));
            $batchCode = trim((string)($_POST['batch_code'] ?? ''));
            $notes = trim((string)($_POST['batch_notes'] ?? ''));

            if ($cycleId <= 0 || $itemDescription === '' || $quantity <= 0 || $receivedDate === '') {
                $flash = ['type' => 'danger', 'message' => 'Cycle, item description, quantity, and received date are required for stock batch.'];
            } else {
                $cycleOwnerStmt = $pdo->prepare("SELECT id FROM production_cycles WHERE id = ? AND farm_id = ? AND status = 'active'");
                $cycleOwnerStmt->execute([$cycleId, $tenantFarmId]);
                if (!$cycleOwnerStmt->fetchColumn()) {
                    $flash = ['type' => 'danger', 'message' => 'The selected active cycle does not belong to this farm.'];
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO stock_batches
                        (farm_id, cycle_id, batch_code, item_description, quantity, unit_cost, supplier_name, received_date, notes, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([
                        $tenantFarmId,
                        $cycleId,
                        ($batchCode !== '' ? $batchCode : null),
                        $itemDescription,
                        $quantity,
                        $unitCost,
                        ($supplierName !== '' ? $supplierName : null),
                        $receivedDate,
                        ($notes !== '' ? $notes : null),
                        $_SESSION['user_id'] ?? null,
                    ]);
                    $flash = ['type' => 'success', 'message' => 'Stock batch posted and linked to the selected active cycle.'];
                }
            }
        }


        if (
            isset(
                $productionCyclesPrgAnchors[
                    (string)$action
                ]
            )
        ) {
            $productionCyclesPrgRedirect(
                (string)$action,
                $flash,
                $createCycleForm
            );
        }

    }

    if ($cycleTableExists) {
        $statusStmt = $pdo->prepare("SELECT status, COUNT(*) AS total FROM production_cycles WHERE farm_id = ? GROUP BY status");
        $statusStmt->execute([$tenantFarmId]);
        foreach ($statusStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $status = $row['status'] . '_cycles';
            if (array_key_exists($status, $summary)) {
                $summary[$status] = (int)$row['total'];
            }
        }

        // Planned cycles are tied to having an expected end date while not yet closed.
        $plannedStmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM production_cycles
             WHERE farm_id = ?
               AND expected_end_date IS NOT NULL
               AND status <> 'closed'"
        );
        $plannedStmt->execute([$tenantFarmId]);
        $summary['planned_cycles'] = (int)$plannedStmt->fetchColumn();

        if ($stockBatchTableExists) {
            $stockBatchCountStmt = $pdo->prepare("SELECT COUNT(*) FROM stock_batches WHERE farm_id = ?");
            $stockBatchCountStmt->execute([$tenantFarmId]);
            $summary['stock_batches'] = (int)$stockBatchCountStmt->fetchColumn();
        }

        $activeStmt = $pdo->prepare("SELECT id, cycle_code, farm_type, production_type, start_date, bird_unit_cost FROM production_cycles WHERE farm_id = ? AND status = 'active' ORDER BY start_date DESC");
        $activeStmt->execute([$tenantFarmId]);
        $activeCycles = $activeStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($activeCycles as &$cycle) {
            $cycle['population_snapshot'] =
                production_population_intelligence_cycle_snapshot(
                    $pdo,
                    $tenantFarmId,
                    $cycle,
                    null,
                    $populationBaselineTableExists
                );

            $cycle['population_state'] =
                $cycle['population_snapshot']['canonical_state'];

            $cycle['current_stock'] =
                (int)$cycle['population_snapshot']['quantity'];

            $summary['total_current_stock'] +=
                (int)$cycle['current_stock'];

        }
        unset($cycle);

        $closedCycleStmt = $pdo->prepare(
            "SELECT cycle_code, farm_type, production_type, start_date, opening_headcount, close_date, closing_headcount
             FROM production_cycles
             WHERE farm_id = ? AND status = 'closed'
             ORDER BY close_date DESC, created_at DESC
             LIMIT 20"
        );
        $closedCycleStmt->execute([$tenantFarmId]);
        $closedCycleDetails = $closedCycleStmt->fetchAll(PDO::FETCH_ASSOC);

        $recentStmt = $pdo->prepare(
            "SELECT id, cycle_code, farm_type, production_type, status, start_date, opening_headcount, bird_unit_cost, expected_end_date
             FROM production_cycles
             WHERE farm_id = ?
               AND status <> 'closed'
             ORDER BY created_at DESC"
        );
        $recentStmt->execute([$tenantFarmId]);
        $recentCycles = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

        $poultryCycleStmt = $pdo->prepare(
            "SELECT id, cycle_code, production_type, status, start_date, close_date, bird_unit_cost
             FROM production_cycles
             WHERE farm_id = ? AND farm_type = 'poultry'
             ORDER BY start_date DESC, id DESC"
        );
        $poultryCycleStmt->execute([$tenantFarmId]);
        $poultryCycles = $poultryCycleStmt->fetchAll(PDO::FETCH_ASSOC);

        if ($poultryPhaseTableExists && !empty($poultryCycles)) {
            $phaseStmt = $pdo->prepare(
                "SELECT p.id, p.cycle_id, p.phase, p.start_date, p.end_date, p.notes, p.created_by, p.created_at
                 FROM production_cycle_phases p
                 INNER JOIN production_cycles pc ON pc.id = p.cycle_id AND pc.farm_id = p.farm_id
                 WHERE p.farm_id = ? AND pc.farm_type = 'poultry'
                 ORDER BY p.cycle_id ASC, p.start_date ASC, p.id ASC"
            );
            $phaseStmt->execute([$tenantFarmId]);
            foreach ($phaseStmt->fetchAll(PDO::FETCH_ASSOC) as $phaseRow) {
                $phaseCycleId = (int)$phaseRow['cycle_id'];
                $poultryPhaseHistoryByCycle[$phaseCycleId][] = $phaseRow;
            }
        }

        if ($poultryAcquisitionTableExists && $poultryAcquisitionCorrectionReady && !empty($poultryCycles)) {
            $acquisitionStmt = $pdo->prepare(
                "SELECT a.id, a.cycle_id, a.acquisition_type, a.acquisition_date, a.quantity, a.age_days,
                        a.total_cost, a.source_name, a.reference_no, a.notes, a.request_token, a.created_by, a.created_at,
                        a.voided_at, a.voided_by, a.void_reason
                 FROM poultry_cycle_acquisitions a
                 INNER JOIN production_cycles pc ON pc.id = a.cycle_id AND pc.farm_id = a.farm_id
                 WHERE a.farm_id = ? AND pc.farm_type = 'poultry'
                 ORDER BY a.cycle_id ASC, a.acquisition_date ASC, a.id ASC"
            );
            $acquisitionStmt->execute([$tenantFarmId]);
            foreach ($acquisitionStmt->fetchAll(PDO::FETCH_ASSOC) as $acquisitionRow) {
                $acquisitionCycleId = (int)$acquisitionRow['cycle_id'];
                $poultryAcquisitionHistoryByCycle[$acquisitionCycleId][] = $acquisitionRow;
            }
        }

        if ($stockBatchTableExists) {
            $batchStmt = $pdo->prepare(
                "SELECT sb.batch_code, sb.item_description, sb.quantity, sb.unit_cost, sb.received_date, sb.supplier_name,
                        pc.cycle_code, pc.production_type
                 FROM stock_batches sb
                 INNER JOIN production_cycles pc ON pc.id = sb.cycle_id AND pc.farm_id = sb.farm_id
                 WHERE sb.farm_id = ?
                 ORDER BY sb.received_date DESC, sb.id DESC
                 LIMIT 20"
            );
            $batchStmt->execute([$tenantFarmId]);
            $recentStockBatches = $batchStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Production cycle page error: '
        . $exception->getMessage()
    );

    $errorMessage =
        'We could not load the production cycle data right now. Please try again.';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $failedAction =
            trim(
                (string)(
                    $_POST['action']
                    ?? ''
                )
            );

        $productionCyclesPrgRedirect(
            $failedAction,
            [
                'type' =>
                    'danger',
                'title' =>
                    'Production cycle action could not be completed.',
                'message' =>
                    $errorMessage,
            ],
            $createCycleForm
        );
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include(__DIR__ . '/../navbar_head.php'); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Production Cycles - Farm Management System</title>
</head>
<body data-arrow-scroll-safe-scope>
<?php include(__DIR__ . '/../navbar.php'); ?>

<div class="container-fluid mt-4">
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-2"><i class="bi bi-arrow-repeat"></i> Production Cycles</h4>
                    <p class="mb-0 text-muted">
                        Create a new production cycle here or manage an existing cycle below.
                        Advanced audit and legacy-maintenance tools remain available separately
                        when they are needed.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <?php if ($flash !== null): ?>
        <?php renderNotification(
            $flash['type'] === 'danger' ? 'error' : $flash['type'],
            $flash['message'],
            $flash['title'] ?? null,
            $flash['tip'] ?? null
        ); ?>
    <?php endif; ?>

    <?php if (!$cycleTableExists || !$stockBatchTableExists): ?>
        <div class="alert alert-warning" role="alert">
            <strong>Cycle tables are not available yet.</strong>
            Run <code>php scripts/run_migrations.php</code> so the <code>production_cycles</code> and <code>stock_batches</code> tables are created.
            <?php if ($migration002Recorded): ?>
                <hr class="my-2">
                <div><strong>Detected mismatch:</strong> migration <code>002_production_cycles.sql</code> is recorded, but required tables are missing. Re-run migrations again using the same database credentials as the web app.</div>
            <?php endif; ?>
        </div>
    <?php elseif ($errorMessage !== null): ?>
        <?php renderNotification('error', $errorMessage, 'Could not load production cycle data.', 'Check the migration/database status and try again.'); ?>
    <?php else: ?>
        <div class="row g-3 mb-3">
            <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted">Active Cycles</div><h4><?php echo $summary['active_cycles']; ?></h4></div></div></div>
            <div class="col-md-3"><div class="card border-success"><div class="card-body"><div class="text-muted">Current Stock (Active Cycles)</div><h4 class="text-success"><?php echo number_format((int)$summary['total_current_stock']); ?></h4></div></div></div>
            <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted">Planned Cycles</div><h4><?php echo $summary['planned_cycles']; ?></h4></div></div></div>
            <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted">Closed Cycles</div><h4><?php echo $summary['closed_cycles']; ?></h4></div></div></div>
        </div>

        <?php if (isPlatformOwner() || hasRole('farm_admin')): ?>
                    <div class="row g-3 mb-3" id="create-cycle">
                        <div class="col-12">
                            <div class="card h-100">
                                <div class="card-header"><strong>Create Cycle</strong></div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
                            <input type="hidden" name="action" value="create_cycle">
                            <div class="mb-2"><label class="form-label">Cycle Code</label><input class="form-control" name="cycle_code" maxlength="100" value="<?php echo htmlspecialchars($createCycleForm['cycle_code'], ENT_QUOTES); ?>" required></div>
                            <div class="mb-2"><label class="form-label">Farm Type</label><select class="form-select" name="farm_type" required><?php foreach (allowedFarmTypes(false) as $type): ?><option value="<?php echo $type; ?>" <?php echo $createCycleForm['farm_type'] === $type ? 'selected' : ''; ?>><?php echo ucfirst($type); ?></option><?php endforeach; ?></select></div>
                            <div class="mb-2">
                                <label class="form-label">Production Type</label>
                                <select class="form-select" name="production_type" id="productionType" data-selected="<?php echo htmlspecialchars($createCycleForm['production_type'], ENT_QUOTES); ?>" required></select>
                            </div>
                            <div class="mb-2"><label class="form-label">Start Date</label><input class="form-control" type="date" name="start_date" value="<?php echo htmlspecialchars($createCycleForm['start_date'], ENT_QUOTES); ?>" required></div>
                            <div class="mb-2"><label class="form-label">Expected End Date</label><input class="form-control" type="date" name="expected_end_date" value="<?php echo htmlspecialchars($createCycleForm['expected_end_date'], ENT_QUOTES); ?>"></div>
                            <div class="mb-2"><label class="form-label">Opening Headcount</label><input class="form-control" type="number" min="0" name="opening_headcount" value="<?php echo htmlspecialchars($createCycleForm['opening_headcount'], ENT_QUOTES); ?>"></div>
                            <div class="mb-2"><label class="form-label">Start Age (days)</label><input class="form-control" type="number" min="1" name="start_age_days" value="<?php echo htmlspecialchars($createCycleForm['start_age_days'], ENT_QUOTES); ?>"></div>

                            <div id="poultryCycleOnboardingWrap" class="border rounded p-3 mb-3">
                                <input
                                    type="hidden"
                                    name="poultry_request_token"
                                    value="<?php echo htmlspecialchars(bin2hex(random_bytes(24)), ENT_QUOTES); ?>"
                                >

                                <h6 class="mb-2">Poultry Flock Entry &amp; Starting Stage</h6>
                                <p class="small text-muted mb-3">
                                    For a new poultry cycle, the starting flock is recorded here once.
                                    Opening Headcount becomes the flock-entry quantity and Start Age becomes
                                    the age at entry. You do not need to record the same flock again afterward.
                                </p>

                                <div class="mb-2">
                                    <label class="form-label">How did these birds enter this cycle?</label>
                                    <select
                                        class="form-select"
                                        name="poultry_acquisition_type"
                                        id="createPoultryAcquisitionType"
                                    >
                                        <option
                                            value="purchased"
                                            id="createPurchasedBirdsOption"
                                            <?php echo $createCycleForm['poultry_acquisition_type'] === 'purchased' ? 'selected' : ''; ?>
                                        >Purchased birds</option>
                                        <option
                                            value="purchased_point_of_lay"
                                            id="createPointOfLayOption"
                                            <?php echo $createCycleForm['poultry_acquisition_type'] === 'purchased_point_of_lay' ? 'selected' : ''; ?>
                                        >Purchased Point-of-Lay</option>
                                        <option
                                            value="internal_transfer"
                                            <?php echo $createCycleForm['poultry_acquisition_type'] === 'internal_transfer' ? 'selected' : ''; ?>
                                        >Farm-raised / internal transfer</option>
                                    </select>
                                    <div class="form-text">
                                        Farm-raised / internal transfer means birds already owned by this farm
                                        and carried into this cycle. Do not use it for an external purchase
                                        simply because the old purchase cost is unknown.
                                    </div>
                                </div>

                                <div class="row g-2">
                                    <div class="col-12 mb-2">
                                        <label class="form-label">Total Acquisition Cost (₦)</label>
                                        <input
                                            class="form-control"
                                            id="createPoultryTotalCost"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            name="poultry_total_cost"
                                            value="<?php echo htmlspecialchars($createCycleForm['poultry_total_cost'], ENT_QUOTES); ?>"
                                        >
                                    </div>
                                </div>
                                <div class="form-text mb-2" id="createPoultryCostHelp">
                                    Purchased entries require the actual total acquisition cost.
                                    Acquisition Cost / Bird (₦) is calculated automatically from this
                                    amount and Opening Headcount.
                                </div>

                                <div class="row g-2">
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label">Source / Supplier</label>
                                        <input
                                            class="form-control"
                                            maxlength="190"
                                            name="poultry_source_name"
                                            value="<?php echo htmlspecialchars($createCycleForm['poultry_source_name'], ENT_QUOTES); ?>"
                                            placeholder="Optional supplier or internal source"
                                        >
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label">Reference</label>
                                        <input
                                            class="form-control"
                                            maxlength="120"
                                            name="poultry_reference_no"
                                            value="<?php echo htmlspecialchars($createCycleForm['poultry_reference_no'], ENT_QUOTES); ?>"
                                            placeholder="Optional invoice, receipt or transfer reference"
                                        >
                                    </div>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label">Starting Biological Stage</label>
                                    <select
                                        class="form-select"
                                        name="poultry_initial_phase"
                                        id="createPoultryInitialPhase"
                                    >
                                        <option
                                            value="rearing"
                                            data-production-type="layer"
                                            <?php echo $createCycleForm['poultry_initial_phase'] === 'rearing' ? 'selected' : ''; ?>
                                        >Layer — Rearing</option>
                                        <option
                                            value="production"
                                            data-production-type="layer"
                                            <?php echo $createCycleForm['poultry_initial_phase'] === 'production' ? 'selected' : ''; ?>
                                        >Layer — Production</option>
                                        <option
                                            value="growing"
                                            data-production-type="broiler"
                                            <?php echo $createCycleForm['poultry_initial_phase'] === 'growing' ? 'selected' : ''; ?>
                                        >Broiler — Growing / Rearing</option>
                                        <option
                                            value="harvest"
                                            data-production-type="broiler"
                                            <?php echo $createCycleForm['poultry_initial_phase'] === 'harvest' ? 'selected' : ''; ?>
                                        >Broiler — Harvest / Sale</option>
                                    </select>
                                    <div class="form-text">
                                        Confirm the flock's real starting biological stage.
                                        The platform will not infer it from age.
                                        Harvest / Sale is a biological stage; it does not record a sales transaction.
                                    </div>
                                </div>
                            </div>

                            <div class="mb-2"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"><?php echo htmlspecialchars($createCycleForm['notes'], ENT_QUOTES); ?></textarea></div>
                            <button class="btn btn-success" type="submit">Create Cycle</button>
                        </form>
                    </div>
                </div>
            </div>

        </div>

                    <details
                        class="card mb-3"
                        id="cycle-maintenance-tools"
                    >
                        <summary class="card-header">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <div>
                                    <strong>Advanced Maintenance &amp; History</strong>
                                    <div class="small text-muted">
                                        Acquisition and lifecycle audit history,
                                        plus temporary legacy-cycle setup.
                                    </div>
                                </div>
                                <span class="badge bg-secondary">Open maintenance</span>
                            </div>
                        </summary>

                        <div class="card-body">

        <div class="alert alert-light border d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <div>
                <strong>Legacy cycle setup has moved.</strong>
                <div class="small text-muted mt-1">
                    Population starting-point setup for older active cycles is kept separate
                    from normal Production Cycles work.
                </div>
            </div>
            <a
                class="btn btn-sm btn-outline-warning"
                href="<?php echo BASE_URL; ?>/management/legacy_cycle_setup.php"
            >
                <i class="bi bi-tools"></i> Legacy Cycle Setup
            </a>
        </div>

        <div class="card mb-3" id="poultry-entry-acquisition">
            <div class="card-header">
                <strong>Poultry Acquisition History</strong>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Each cycle's flock-entry facts and audit history are shown together.
                    Corrections remain visible as voided rows rather than being deleted.
                    Use Edit for cycle details and corrections to Opening Headcount or Total Acquisition Cost.
                </p>

                <?php if (!$poultryAcquisitionTableExists): ?>
                    <div class="alert alert-warning mb-0">
                        <strong>Poultry acquisition migration is not available.</strong>
                        Run <code>php scripts/run_migrations.php</code> before using acquisition history.
                        <?php if ($migration038Recorded): ?>
                            <div class="mt-2">
                                <strong>Detected mismatch:</strong>
                                migration 038 is recorded but
                                <code>poultry_cycle_acquisitions</code> is missing.
                                Re-run migrations using the same database credentials as the web app.
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Cycle</th>
                                <th>Type</th>
                                <th>Entry</th>
                                <th>Date</th>
                                <th>Age</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Cost</th>
                                <th class="text-end">Cost / Bird</th>
                                <th>Status</th>
                                <th>Source / Ref</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($poultryCycles)): ?>
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-3">
                                        No poultry production cycle exists yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($poultryCycles as $cycle): ?>
                                    <?php
                                    $cycleAcquisitionHistory =
                                        $poultryAcquisitionHistoryByCycle[
                                            (int)$cycle['id']
                                        ]
                                        ?? [];
                                    ?>

                                    <?php if (empty($cycleAcquisitionHistory)): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($cycle['cycle_code']); ?></td>
                                            <td><?php echo htmlspecialchars(ucfirst($cycle['production_type'])); ?></td>
                                            <td colspan="8">
                                                <span class="badge bg-warning text-dark">
                                                    Not recorded
                                                </span>
                                                <span class="small text-muted ms-2">
                                                    Legacy cycle with no recorded acquisition history.
                                                </span>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($cycleAcquisitionHistory as $acquisitionRow): ?>
                                            <?php
                                            $rowTotalCost =
                                                $acquisitionRow['total_cost'] !== null
                                                && $acquisitionRow['total_cost'] !== ''
                                                    ? (float)$acquisitionRow['total_cost']
                                                    : null;

                                            $rowCostPerBird =
                                                $rowTotalCost !== null
                                                    ? poultry_acquisition_cost_per_bird(
                                                        $rowTotalCost,
                                                        (int)$acquisitionRow['quantity']
                                                    )
                                                    : null;
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($cycle['cycle_code']); ?></td>
                                                <td><?php echo htmlspecialchars(ucfirst($cycle['production_type'])); ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars(
                                                        poultry_acquisition_type_label(
                                                            $cycle['production_type'],
                                                            $acquisitionRow['acquisition_type']
                                                        )
                                                    ); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($acquisitionRow['acquisition_date']); ?></td>
                                                <td><?php echo number_format((int)$acquisitionRow['age_days']); ?> days</td>
                                                <td class="text-end"><?php echo number_format((int)$acquisitionRow['quantity']); ?></td>
                                                <td class="text-end">
                                                    <?php echo $rowTotalCost !== null
                                                        ? '₦' . number_format($rowTotalCost, 2)
                                                        : 'Basis pending'; ?>
                                                </td>
                                                <td class="text-end">
                                                    <?php echo $rowCostPerBird !== null
                                                        ? '₦' . number_format($rowCostPerBird, 2)
                                                        : '-'; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($acquisitionRow['voided_at'])): ?>
                                                        <span class="badge bg-secondary">Voided</span>
                                                        <?php if (trim((string)($acquisitionRow['void_reason'] ?? '')) !== ''): ?>
                                                            <div class="small text-muted">
                                                                <?php echo htmlspecialchars((string)$acquisitionRow['void_reason']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $sourceReference =
                                                        trim(
                                                            (string)($acquisitionRow['source_name'] ?? '')
                                                            . (
                                                                (string)($acquisitionRow['reference_no'] ?? '') !== ''
                                                                    ? ' · ' . $acquisitionRow['reference_no']
                                                                    : ''
                                                            )
                                                        );

                                                    echo htmlspecialchars(
                                                        $sourceReference !== ''
                                                            ? $sourceReference
                                                            : '-'
                                                    );
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="alert alert-info mt-3 mb-0">
                        Acquisition corrections remain auditable:
                        the erroneous row is voided rather than deleted,
                        and the corrected fact remains visible here.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-3" id="poultry-lifecycle-history">
            <div class="card-header">
                <strong>Poultry Lifecycle History</strong>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Current biological stage and historical phase changes are shown in one audit table.
                    Lifecycle remains separate from the production cycle's operational status and is never inferred
                    from age, eggs, feed, mortality, or cycle closure.
                </p>

                <?php if (!$poultryPhaseTableExists): ?>
                    <div class="alert alert-warning mb-0">
                        <strong>Poultry lifecycle migration is not available.</strong>
                        Run <code>php scripts/run_migrations.php</code> to apply
                        <code>037_poultry_cycle_phase_history.sql</code>.
                        <?php if ($migration037Recorded): ?>
                            <div class="mt-2">
                                <strong>Detected mismatch:</strong>
                                migration 037 is recorded but
                                <code>production_cycle_phases</code> is missing.
                                Re-run migrations using the same database credentials as the web app.
                            </div>
                        <?php endif; ?>
                    </div>

                <?php elseif (empty($poultryCycles)): ?>
                    <div class="text-muted">
                        No poultry production cycle exists yet.
                    </div>

                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Cycle</th>
                                <th>Type</th>
                                <th>Operational Status</th>
                                <th>Phase</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Phase Status</th>
                                <th>Notes</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($poultryCycles as $cycle): ?>
                                <?php
                                $cyclePhaseHistory =
                                    $poultryPhaseHistoryByCycle[
                                        (int)$cycle['id']
                                    ]
                                    ?? [];
                                ?>

                                <?php if (empty($cyclePhaseHistory)): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($cycle['cycle_code']); ?></td>
                                        <td><?php echo htmlspecialchars(ucfirst($cycle['production_type'])); ?></td>
                                        <td>
                                            <span class="badge bg-secondary text-uppercase">
                                                <?php echo htmlspecialchars($cycle['status']); ?>
                                            </span>
                                        </td>
                                        <td colspan="5">
                                            <span class="badge bg-warning text-dark">
                                                Not yet defined
                                            </span>
                                            <span class="small text-muted ms-2">
                                                No lifecycle history is recorded for this legacy cycle.
                                            </span>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($cyclePhaseHistory as $phaseRow): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($cycle['cycle_code']); ?></td>
                                            <td><?php echo htmlspecialchars(ucfirst($cycle['production_type'])); ?></td>
                                            <td>
                                                <span class="badge bg-secondary text-uppercase">
                                                    <?php echo htmlspecialchars($cycle['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars(
                                                    poultry_lifecycle_phase_label(
                                                        $cycle['production_type'],
                                                        $phaseRow['phase']
                                                    )
                                                ); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($phaseRow['start_date']); ?></td>
                                            <td>
                                                <?php echo $phaseRow['end_date'] !== null
                                                    ? htmlspecialchars($phaseRow['end_date'])
                                                    : 'Open'; ?>
                                            </td>
                                            <td>
                                                <?php if ($phaseRow['end_date'] === null): ?>
                                                    <span class="badge bg-primary">Current</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Completed</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($phaseRow['notes'] ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="alert alert-info mt-3 mb-0">
                        Lifecycle changes are managed inside the selected cycle.
                        Use <strong>Manage Cycle</strong> below to record the next biological
                        transition or to end poultry production.
                    </div>
                <?php endif; ?>
            </div>
        </div>

                        </div>
                    </details>
        <?php endif; ?>

        <div class="card" id="recent-cycles">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Production Cycles</h5>
                <span class="badge bg-success">New</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead>
                        <tr>
                            <th>Cycle Code</th>
                            <th>Farm Type</th>
                            <th>Production Type</th>
                            <th>Status</th>
                            <th>Start Date</th>
                            <th class="text-end">Opening Headcount</th>
                            <th>Expected End</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($recentCycles)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">No open cycles. Create a new cycle or review closed cycles below.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentCycles as $cycle): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($cycle['cycle_code']); ?></td>
                                    <td class="text-capitalize"><?php echo htmlspecialchars($cycle['farm_type']); ?></td>
                                    <td><?php echo htmlspecialchars($cycle['production_type']); ?></td>
                                    <td><span class="badge bg-secondary text-uppercase"><?php echo htmlspecialchars($cycle['status']); ?></span></td>
                                    <td><?php echo htmlspecialchars($cycle['start_date']); ?></td>
                                    <td class="text-end"><?php echo number_format(max(0, (int)($cycle['opening_headcount'] ?? 0))); ?></td>
                                    <td><?php echo htmlspecialchars($cycle['expected_end_date'] ?? '-'); ?></td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php $hasCycleAction = false; ?>

                                            <?php if (strtolower((string)$cycle['farm_type']) === 'poultry' && in_array(strtolower((string)$cycle['production_type']), ['layer','broiler'], true)): ?>
                                                <?php $hasCycleAction = true; ?>
                                                <a
                                                    class="btn btn-sm btn-outline-primary"
                                                    href="<?php echo BASE_URL; ?>/management/poultry_cycle.php?id=<?php echo (int)$cycle['id']; ?>"
                                                >Manage Cycle</a>
                                            <?php elseif (strtolower((string)$cycle['farm_type']) === 'ruminant'): ?>
                                                <?php $hasCycleAction = true; ?>
                                                <a
                                                    class="btn btn-sm btn-outline-primary"
                                                    href="<?php echo BASE_URL; ?>/management/ruminant_cycle.php?id=<?php echo (int)$cycle['id']; ?>"
                                                >Manage Cycle</a>
                                            <?php endif; ?>

                                            <?php if (isPlatformOwner() || hasRole('farm_admin')): ?>
                                                <?php $hasCycleAction = true; ?>
                                                <a
                                                    class="btn btn-sm btn-outline-secondary"
                                                    href="<?php echo BASE_URL; ?>/management/production_cycle_edit.php?id=<?php echo (int)$cycle['id']; ?>"
                                                >Edit</a>
                                            <?php endif; ?>

                                            <?php if (!$hasCycleAction): ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>


        <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Close Cycle Details</h5>
                <span class="badge bg-success">Live</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead>
                        <tr>
                            <th>Cycle</th>
                            <th>Farm Type</th>
                            <th>Production Type</th>
                            <th>Cycle Start Date</th>
                            <th class="text-end">Opening Headcount</th>
                            <th>Close Date</th>
                            <th class="text-end">Closing Headcount</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($closedCycleDetails)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No closed cycles yet. Close a cycle to see details here.</td></tr>
                        <?php else: ?>
                            <?php foreach ($closedCycleDetails as $cycle): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($cycle['cycle_code']); ?></td>
                                    <td class="text-capitalize"><?php echo htmlspecialchars($cycle['farm_type']); ?></td>
                                    <td class="text-capitalize"><?php echo htmlspecialchars($cycle['production_type']); ?></td>
                                    <td><?php echo htmlspecialchars($cycle['start_date'] ?? '-'); ?></td>
                                    <td class="text-end"><?php echo number_format(max(0, (int)($cycle['opening_headcount'] ?? 0))); ?></td>
                                    <td><?php echo htmlspecialchars($cycle['close_date'] ?? '-'); ?></td>
                                    <td class="text-end fw-bold"><?php echo number_format(max(0, (int)($cycle['closing_headcount'] ?? 0))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>
<script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/production-cycles.js'); ?>"></script>

</body>
</html>
