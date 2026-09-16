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
$populationCutoverCycles = [];
$populationTrackedActiveCount = 0;

$cutoverForm = [
    'cycle_id' => '',
    'baseline_date' => '',
    'baseline_quantity' => '',
    'notes' => '',
    'confirmed' => false,
];

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
$poultryLifecycleByCycle = [];
$poultryPhaseHistoryByCycle = [];
$poultryAcquisitionHistoryByCycle = [];
$poultryAcquisitionSummaryByCycle = [];

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

$productionCyclesPrgAction = '';

$productionCyclesPrgAnchors = [
    'create_cycle' =>
        '#create-cycle',
    'update_bird_cost_basis' =>
        '#cycle-maintenance-tools',
    'post_batch' =>
        '#cycle-tools',
    'confirm_population_cutover' =>
        '#population-cutover',
];

$productionCyclesPrgRedirect = static function (
    string $action,
    ?array $flashState,
    array $createFormState,
    array $cutoverFormState
) use (
    $productionCyclesPrgKey,
    $productionCyclesPrgAnchors
): void {
    $_SESSION[$productionCyclesPrgKey] = [
        'action' =>
            $action,
        'flash' =>
            $flashState,
        'create_form' =>
            $createFormState,
        'cutover_form' =>
            $cutoverFormState,
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

    $productionCyclesPrgAction =
        trim(
            (string)(
                $productionCyclesPrg['action']
                ?? ''
            )
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

    if (
        isset($productionCyclesPrg['cutover_form'])
        && is_array(
            $productionCyclesPrg['cutover_form']
        )
    ) {
        foreach (
            array_keys($cutoverForm)
            as $cutoverField
        ) {
            if (
                array_key_exists(
                    $cutoverField,
                    $productionCyclesPrg['cutover_form']
                )
            ) {
                $cutoverForm[$cutoverField] =
                    $productionCyclesPrg['cutover_form']
                        [$cutoverField];
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

        if ($action === 'update_bird_cost_basis') {
            $cycleId = (int)($_POST['cycle_id'] ?? 0);
            $birdUnitCostRaw = trim((string)($_POST['bird_unit_cost'] ?? ''));
            $birdUnitCost = $birdUnitCostRaw === '' ? null : filter_var($birdUnitCostRaw, FILTER_VALIDATE_FLOAT);

            if ($cycleId <= 0) {
                $flash = ['type' => 'danger', 'message' => 'Select a poultry cycle to update.', 'title' => 'Cycle is required.'];
            } elseif ($birdUnitCost === false || ($birdUnitCost !== null && $birdUnitCost < 0)) {
                $flash = ['type' => 'danger', 'message' => 'Bird cost basis must be blank or 0 and above.', 'title' => 'Invalid bird cost basis.'];
            } else {
                $cycleOwnerStmt = $pdo->prepare("SELECT id FROM production_cycles WHERE id = ? AND farm_id = ? AND farm_type = 'poultry' LIMIT 1");
                $cycleOwnerStmt->execute([$cycleId, $tenantFarmId]);
                if (!$cycleOwnerStmt->fetchColumn()) {
                    $flash = ['type' => 'danger', 'message' => 'The selected poultry cycle was not found in this farm.'];
                } else {
                    $stmt = $pdo->prepare('UPDATE production_cycles SET bird_unit_cost = ? WHERE id = ? AND farm_id = ?');
                    $stmt->execute([$birdUnitCost, $cycleId, $tenantFarmId]);
                    $flash = [
                        'type' => 'success',
                        'message' => $birdUnitCost === null
                            ? 'Bird cost basis cleared. Mortality for this cycle will remain uncosted until a basis is supplied.'
                            : 'Bird cost basis updated successfully.'
                    ];
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


        if ($action === 'confirm_population_cutover') {
            $cycleId = (int)($_POST['cycle_id'] ?? 0);
            $baselineDate = trim(
                (string)($_POST['baseline_date'] ?? '')
            );
            $baselineQuantityRaw = trim(
                (string)($_POST['baseline_quantity'] ?? '')
            );
            $notes = trim(
                (string)($_POST['notes'] ?? '')
            );
            $confirmed =
                (string)($_POST['confirm_cutover'] ?? '') === '1';

            $cutoverForm = [
                'cycle_id' => $cycleId > 0 ? (string)$cycleId : '',
                'baseline_date' => $baselineDate,
                'baseline_quantity' => $baselineQuantityRaw,
                'notes' => $notes,
                // A corrected immutable baseline must be explicitly confirmed
                // again after any failed submission.
                'confirmed' => false,
            ];

            if (!$populationBaselineTableExists) {
                $flash = [
                    'type' => 'danger',
                    'title' => 'Population foundation is not available.',
                    'message' => 'Run the V3 database migrations before confirming a population cutover.',
                ];
            } elseif (!$confirmed) {
                $flash = [
                    'type' => 'danger',
                    'title' => 'Population confirmation is required.',
                    'message' => 'Confirm that the entered headcount is the physically verified live population for the selected cutover date.',
                ];
            } else {
                try {
                    production_cycle_cutover_population_v3(
                        $pdo,
                        $tenantFarmId,
                        $cycleId,
                        $baselineDate,
                        $baselineQuantityRaw,
                        $notes !== '' ? $notes : null,
                        isset($_SESSION['user_id'])
                            ? (int)$_SESSION['user_id']
                            : null
                    );

                    $flash = [
                        'type' => 'success',
                        'title' => 'V3 population cutover confirmed.',
                        'message' => 'The selected cycle now uses the user-confirmed population baseline. Earlier legacy records were not reconstructed or backfilled.',
                        'tip' => 'Future population changes are tracked from this baseline. Correct later population differences through the canonical adjustment workflow instead of rewriting this baseline.',
                    ];

                    $cutoverForm = [
                        'cycle_id' => '',
                        'baseline_date' => '',
                        'baseline_quantity' => '',
                        'notes' => '',
                        'confirmed' => false,
                    ];
                } catch (Throwable $e) {
                    $safe =
                        $e instanceof InvalidArgumentException
                        || $e instanceof ProductionCycleException
                        || $e instanceof ProductionPopulationException;

                    if (!$safe) {
                        error_log(
                            'Production population cutover failed: '
                            . $e->getMessage()
                        );
                    }

                    $flash = [
                        'type' => 'danger',
                        'title' => 'Population cutover was not saved.',
                        'message' => $safe
                            ? $e->getMessage()
                            : 'The population cutover could not be completed. No baseline was changed.',
                    ];
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
                $createCycleForm,
                $cutoverForm
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

            if ($populationBaselineTableExists) {
                if (
                    $cycle['population_snapshot']['tracking_status']
                    === 'canonical'
                ) {
                    $populationTrackedActiveCount++;
                } else {
                    $populationCutoverCycles[] = $cycle;
                }
            }
        }
        unset($cycle);

        $closedCycleStmt = $pdo->prepare(
            "SELECT cycle_code, farm_type, production_type, opening_headcount, close_date, closing_headcount
             FROM production_cycles
             WHERE farm_id = ? AND status = 'closed'
             ORDER BY close_date DESC, created_at DESC
             LIMIT 20"
        );
        $closedCycleStmt->execute([$tenantFarmId]);
        $closedCycleDetails = $closedCycleStmt->fetchAll(PDO::FETCH_ASSOC);

        $recentStmt = $pdo->prepare(
            "SELECT id, cycle_code, farm_type, production_type, status, start_date, opening_headcount, bird_unit_cost, expected_end_date, close_date
             FROM production_cycles
             WHERE farm_id = ?
             ORDER BY created_at DESC
             LIMIT 12"
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
                if ($phaseRow['end_date'] === null) {
                    $poultryLifecycleByCycle[$phaseCycleId] = $phaseRow;
                }
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
            foreach ($poultryCycles as $cycle) {
                $cycleIdForSummary = (int)$cycle['id'];
                $poultryAcquisitionSummaryByCycle[$cycleIdForSummary] = poultry_acquisition_summary($poultryAcquisitionHistoryByCycle[$cycleIdForSummary] ?? []);
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
            $createCycleForm,
            $cutoverForm
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
                        Start here to see the production cycles in this farm.
                        Choose a cycle below to work on it. Setup and maintenance
                        tools stay out of the way until you need them.
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

        <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
            <a class="btn btn-outline-primary" href="#recent-cycles">
                <i class="bi bi-list-ul"></i> Choose a Cycle
            </a>

            <?php if (isPlatformOwner() || hasRole('farm_admin')): ?>
                <a
                    class="btn btn-success"
                    href="#create-cycle"
                    data-open-cycle-tools
                >
                    <i class="bi bi-plus-circle"></i> New Cycle
                </a>
            <?php endif; ?>
        </div>

        <?php if (isPlatformOwner() || hasRole('farm_admin')): ?>
            <details
                class="card mb-3"
                id="cycle-tools"
                <?php echo $flash !== null ? 'open' : ''; ?>
            >
                <summary class="card-header">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <strong>Create New Cycle</strong>
                            <div class="small text-muted">
                                Start a new production cycle here. Existing-cycle setup,
                                cost maintenance and aggregate history stay under Advanced Maintenance.
                                Selected-cycle poultry operations stay inside Manage Cycle.
                            </div>
                        </div>
                        <span class="badge bg-secondary">Open</span>
                    </div>
                </summary>

                <div class="card-body">
                    <div class="row g-3 mb-3" id="create-cycle">
                        <div class="col-lg-6">
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
                        <?php echo (
                            $flash !== null
                            && in_array(
                                $productionCyclesPrgAction,
                                [
                                    'confirm_population_cutover',
                                    'update_bird_cost_basis',
                                ],
                                true
                            )
                        ) ? 'open' : ''; ?>
                    >
                        <summary class="card-header">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <div>
                                    <strong>Advanced Maintenance &amp; History</strong>
                                    <div class="small text-muted">
                                        Population setup, cost maintenance, acquisition audit,
                                        and poultry lifecycle history.
                                    </div>
                                </div>
                                <span class="badge bg-secondary">Open maintenance</span>
                            </div>
                        </summary>

                        <div class="card-body">

        <?php if (
            (isPlatformOwner() || hasRole('farm_admin'))
            && (
                !$populationBaselineTableExists
                || !empty($populationCutoverCycles)
            )
        ): ?>
            <div class="card mb-3" id="population-cutover">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>V3 Population Cutover</strong>
                    <?php if ($populationBaselineTableExists): ?>
                        <span class="badge bg-secondary">
                            <?php echo number_format($populationTrackedActiveCount); ?> active cycle(s) already tracked
                        </span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <p class="mb-2">
                        Use this only for an existing active cycle that has not yet entered V3 population tracking.
                        Enter the <strong>physically verified live headcount</strong>; the platform will not derive it
                        from Daily Records, Animal Registry, Sales, opening stock, or other historical records.
                    </p>

                    <div class="alert alert-warning">
                        <strong>This establishes the cycle's V3 population starting point.</strong>
                        The baseline is not silently rewritten later. If the selected date already has
                        population-changing activity recorded, choose a clean cutover date and confirm the
                        live headcount before recording that date's V3 population-changing activity.
                    </div>

                    <?php if (!$populationBaselineTableExists): ?>
                        <div class="alert alert-danger mb-0">
                            <strong>V3 population foundation is not available.</strong>
                            Run the database migrations before confirming a population cutover.
                        </div>
                    <?php else: ?>
                        <form method="post" class="row g-3">
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>"
                            >
                            <input
                                type="hidden"
                                name="action"
                                value="confirm_population_cutover"
                            >

                            <div class="col-md-6">
                                <label class="form-label">Active Cycle</label>
                                <select class="form-select" name="cycle_id" required>
                                    <option value="">Select cycle requiring cutover</option>
                                    <?php foreach ($populationCutoverCycles as $cycle): ?>
                                        <option
                                            value="<?php echo (int)$cycle['id']; ?>"
                                            <?php echo (string)$cutoverForm['cycle_id'] === (string)$cycle['id'] ? 'selected' : ''; ?>
                                        >
                                            <?php
                                            echo htmlspecialchars(
                                                $cycle['cycle_code']
                                                . ' — '
                                                . ucfirst((string)$cycle['production_type'])
                                                . ' — started '
                                                . (string)$cycle['start_date']
                                            );
                                            ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Cutover Date</label>
                                <input
                                    class="form-control"
                                    type="date"
                                    name="baseline_date"
                                    value="<?php echo htmlspecialchars($cutoverForm['baseline_date'], ENT_QUOTES); ?>"
                                    required
                                >
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Confirmed Live Population</label>
                                <input
                                    class="form-control"
                                    type="number"
                                    min="0"
                                    step="1"
                                    name="baseline_quantity"
                                    value="<?php echo htmlspecialchars($cutoverForm['baseline_quantity'], ENT_QUOTES); ?>"
                                    required
                                >
                            </div>

                            <div class="col-12">
                                <label class="form-label">Cutover Notes</label>
                                <textarea
                                    class="form-control"
                                    name="notes"
                                    rows="2"
                                    placeholder="Optional: how the live headcount was physically confirmed"
                                ><?php echo htmlspecialchars($cutoverForm['notes']); ?></textarea>
                            </div>

                            <div class="col-12">
                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        value="1"
                                        name="confirm_cutover"
                                        id="confirmPopulationCutover"
                                        <?php echo $cutoverForm['confirmed'] ? 'checked' : ''; ?>
                                        required
                                    >
                                    <label
                                        class="form-check-label"
                                        for="confirmPopulationCutover"
                                    >
                                        I confirm this is the physically verified live population for this
                                        cycle at the start of the selected cutover date, before that date's
                                        V3 population-changing activity is recorded.
                                    </label>
                                </div>
                            </div>

                            <div class="col-12">
                                <button class="btn btn-warning" type="submit">
                                    Confirm V3 Population Cutover
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="card mb-3">
            <div class="card-header"><strong>Poultry Bird Cost Basis</strong></div>
            <div class="card-body">
                <p class="text-muted small">Set or correct the traceable per-bird value used for mortality cost. This does not alter stock, expenses, sales, or feed records.</p>
                <form method="post" class="row g-3 align-items-end">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
                    <input type="hidden" name="action" value="update_bird_cost_basis">
                    <div class="col-md-6">
                        <label class="form-label">Poultry Cycle</label>
                        <select class="form-select" name="cycle_id" required>
                            <option value="">Select poultry cycle</option>
                            <?php foreach ($poultryCycles as $cycle): ?>
                                <option value="<?php echo (int)$cycle['id']; ?>"><?php echo htmlspecialchars($cycle['cycle_code'] . ' — ' . ucfirst($cycle['production_type']) . ' (' . $cycle['status'] . ')'); ?><?php echo $cycle['bird_unit_cost'] !== null ? ' — ₦' . number_format((float)$cycle['bird_unit_cost'], 2) . '/bird' : ' — no cost basis'; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Bird Cost Basis (₦ / bird)</label>
                        <input class="form-control" type="number" min="0" step="0.01" name="bird_unit_cost" placeholder="Blank clears the cost basis">
                    </div>
                    <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Update</button></div>
                </form>
            </div>
        </div>

        <div class="card mb-3" id="poultry-entry-acquisition">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Poultry Acquisition History</strong>
                
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">The starting flock for new poultry cycles is recorded once during Create Cycle. This section keeps aggregate acquisition history visible for audit. Use Edit for cycle details and corrections to Opening Headcount or Total Acquisition Cost.</p>
                <?php if (!$poultryAcquisitionTableExists): ?>
                    <div class="alert alert-warning mb-0">
                        <strong>Poultry acquisition migration is not available.</strong> Run <code>php scripts/run_migrations.php</code> before recording flock entry facts.
                        <?php if ($migration038Recorded): ?>
                            <div class="mt-2"><strong>Detected mismatch:</strong> migration 038 is recorded but <code>poultry_cycle_acquisitions</code> is missing. Re-run migrations using the same database credentials as the web app.</div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Cycle</th><th>Type</th><th>Entry status</th><th class="text-end">Recorded qty</th><th class="text-end">Acquisition cost</th><th class="text-end">Effective cost / bird</th></tr></thead>
                            <tbody>
                            <?php foreach ($poultryCycles as $cycle): ?>
                                <?php $acqSummary = $poultryAcquisitionSummaryByCycle[(int)$cycle['id']] ?? ['entry_count'=>0,'quantity'=>0,'total_cost'=>null,'effective_cost_per_bird'=>null,'has_uncosted_entry'=>false]; ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($cycle['cycle_code']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($cycle['production_type'])); ?></td>
                                    <td>
                                        <?php if ((int)$acqSummary['entry_count'] === 0): ?>
                                            <span class="badge bg-warning text-dark">Not recorded</span>
                                        <?php elseif (!empty($acqSummary['has_uncosted_entry'])): ?>
                                            <span class="badge bg-secondary">Recorded · basis incomplete</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Recorded</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end"><?php echo (int)$acqSummary['entry_count'] > 0 ? number_format((int)$acqSummary['quantity']) : '-'; ?></td>
                                    <td class="text-end"><?php echo $acqSummary['total_cost'] !== null ? '₦' . number_format((float)$acqSummary['total_cost'], 2) : '-'; ?></td>
                                    <td class="text-end"><?php echo $acqSummary['effective_cost_per_bird'] !== null ? '₦' . number_format((float)$acqSummary['effective_cost_per_bird'], 2) : '-'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <div class="border rounded p-3 h-100">
                                <h6>Recorded Acquisition History</h6>
                                <p class="text-muted small">History is auditable. Erroneous entries are voided rather than deleted; voided rows remain visible and are excluded from active acquisition totals. It is not inferred from opening stock, Bird Cost Basis, Daily Records, Inventory, Expenses, or lifecycle phases.</p>
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <thead><tr><th>Cycle</th><th>Entry</th><th>Date</th><th>Age</th><th class="text-end">Qty</th><th class="text-end">Cost</th><th>Status</th><th>Source / Ref</th></tr></thead>
                                        <tbody>
                                        <?php $acquisitionRows = 0; ?>
                                        <?php foreach ($poultryCycles as $cycle): ?>
                                            <?php foreach (($poultryAcquisitionHistoryByCycle[(int)$cycle['id']] ?? []) as $acquisitionRow): $acquisitionRows++; ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($cycle['cycle_code']); ?></td>
                                                    <td><?php echo htmlspecialchars(poultry_acquisition_type_label($cycle['production_type'], $acquisitionRow['acquisition_type'])); ?></td>
                                                    <td><?php echo htmlspecialchars($acquisitionRow['acquisition_date']); ?></td>
                                                    <td><?php echo number_format((int)$acquisitionRow['age_days']); ?> days</td>
                                                    <td class="text-end"><?php echo number_format((int)$acquisitionRow['quantity']); ?></td>
                                                    <td class="text-end"><?php echo $acquisitionRow['total_cost'] !== null ? '₦' . number_format((float)$acquisitionRow['total_cost'], 2) : 'Basis pending'; ?></td>
                                                    <td><?php if (!empty($acquisitionRow['voided_at'])): ?><span class="badge bg-secondary">Voided</span><div class="small text-muted"><?php echo htmlspecialchars((string)($acquisitionRow['void_reason'] ?? '')); ?></div><?php else: ?><span class="badge bg-success">Active</span><?php endif; ?></td>
                                                    <td><?php echo htmlspecialchars(trim((string)($acquisitionRow['source_name'] ?? '') . ((string)($acquisitionRow['reference_no'] ?? '') !== '' ? ' · ' . $acquisitionRow['reference_no'] : '')) ?: '-'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                        <?php if ($acquisitionRows === 0): ?>
                                            <tr><td colspan="8" class="text-center text-muted py-3">No poultry acquisition history is recorded for this legacy cycle. New V3 poultry cycles record the starting flock during Create Cycle.</td></tr>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="alert alert-info mt-3 mb-0">
                                    Use <strong>Edit</strong> in the Production Cycles table for cycle details and initial-entry corrections.
                                    Corrected acquisition facts remain auditable: the erroneous row is voided rather than deleted.
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-3" id="poultry-lifecycle-history">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Poultry Lifecycle History</strong>
                
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Biological lifecycle is recorded separately from the production cycle's operational status. The platform does not infer a phase from bird age, eggs, feed, mortality, or cycle closure.
                </p>

                <?php if (!$poultryPhaseTableExists): ?>
                    <div class="alert alert-warning mb-0">
                        <strong>Poultry lifecycle migration is not available.</strong>
                        Run <code>php scripts/run_migrations.php</code> to apply <code>037_poultry_cycle_phase_history.sql</code>.
                        <?php if ($migration037Recorded): ?>
                            <div class="mt-2"><strong>Detected mismatch:</strong> migration 037 is recorded but <code>production_cycle_phases</code> is missing. Re-run migrations using the same database credentials as the web app.</div>
                        <?php endif; ?>
                    </div>
                <?php elseif (empty($poultryCycles)): ?>
                    <div class="text-muted">No poultry production cycle exists yet.</div>
                <?php else: ?>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Cycle</th>
                                <th>Type</th>
                                <th>Operational Status</th>
                                <th>Lifecycle</th>
                                <th>Phase Start</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($poultryCycles as $cycle): ?>
                                <?php $currentPhase = $poultryLifecycleByCycle[(int)$cycle['id']] ?? null; ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($cycle['cycle_code']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($cycle['production_type'])); ?></td>
                                    <td><span class="badge bg-secondary text-uppercase"><?php echo htmlspecialchars($cycle['status']); ?></span></td>
                                    <td>
                                        <?php if ($currentPhase): ?>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars(poultry_lifecycle_phase_label($cycle['production_type'], $currentPhase['phase'])); ?></span>
                                        <?php elseif (!empty($poultryPhaseHistoryByCycle[(int)$cycle['id']])): ?>
                                            <span class="badge bg-secondary">No open phase</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Not yet defined</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $currentPhase ? htmlspecialchars($currentPhase['start_date']) : '-'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="alert alert-info mb-3">
                        Lifecycle changes are managed inside the selected cycle.
                        Use <strong>Manage Cycle</strong> in the Production Cycles table below
                        to record the next biological transition or to end production.
                    </div>

                    <hr>
                    <h6>Recorded Phase History</h6>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Cycle</th><th>Phase</th><th>Start</th><th>End</th><th>Notes</th></tr></thead>
                            <tbody>
                            <?php $phaseHistoryRows = 0; ?>
                            <?php foreach ($poultryCycles as $cycle): ?>
                                <?php foreach (($poultryPhaseHistoryByCycle[(int)$cycle['id']] ?? []) as $phaseRow): $phaseHistoryRows++; ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($cycle['cycle_code']); ?></td>
                                        <td><?php echo htmlspecialchars(poultry_lifecycle_phase_label($cycle['production_type'], $phaseRow['phase'])); ?></td>
                                        <td><?php echo htmlspecialchars($phaseRow['start_date']); ?></td>
                                        <td><?php echo htmlspecialchars($phaseRow['end_date'] ?? 'Open'); ?></td>
                                        <td><?php echo htmlspecialchars($phaseRow['notes'] ?? '-'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                            <?php if ($phaseHistoryRows === 0): ?>
                                <tr><td colspan="5" class="text-center text-muted py-3">No poultry lifecycle history is recorded for this legacy cycle. New V3 poultry cycles record the starting biological stage during Create Cycle.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

                        </div>
                    </details>

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
                            <th class="text-end">Bird Cost Basis</th>
                            <th>Expected End</th>
                            <th>Closed Date</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($recentCycles)): ?>
                            <tr><td colspan="10" class="text-center text-muted py-4">No cycles yet. Create your first cycle above.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentCycles as $cycle): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($cycle['cycle_code']); ?></td>
                                    <td class="text-capitalize"><?php echo htmlspecialchars($cycle['farm_type']); ?></td>
                                    <td><?php echo htmlspecialchars($cycle['production_type']); ?></td>
                                    <td><span class="badge bg-secondary text-uppercase"><?php echo htmlspecialchars($cycle['status']); ?></span></td>
                                    <td><?php echo htmlspecialchars($cycle['start_date']); ?></td>
                                    <td class="text-end"><?php echo number_format(max(0, (int)($cycle['opening_headcount'] ?? 0))); ?></td>
                                    <td class="text-end"><?php echo $cycle['farm_type'] === 'poultry' && $cycle['bird_unit_cost'] !== null ? '₦' . number_format((float)$cycle['bird_unit_cost'], 2) : '-'; ?></td>
                                    <td><?php echo htmlspecialchars($cycle['expected_end_date'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($cycle['close_date'] ?? '-'); ?></td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php $hasCycleAction = false; ?>

                                            <?php if (strtolower((string)$cycle['farm_type']) === 'poultry' && in_array(strtolower((string)$cycle['production_type']), ['layer','broiler'], true)): ?>
                                                <?php $hasCycleAction = true; ?>
                                                <a
                                                    class="btn btn-sm btn-outline-primary"
                                                    href="<?php echo BASE_URL; ?>/management/poultry_cycle.php?id=<?php echo (int)$cycle['id']; ?>"
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
                            <th class="text-end">Opening Headcount</th>
                            <th>Close Date</th>
                            <th class="text-end">Closing Headcount</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($closedCycleDetails)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No closed cycles yet. Close a cycle to see details here.</td></tr>
                        <?php else: ?>
                            <?php foreach ($closedCycleDetails as $cycle): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($cycle['cycle_code']); ?></td>
                                    <td class="text-capitalize"><?php echo htmlspecialchars($cycle['farm_type']); ?></td>
                                    <td class="text-capitalize"><?php echo htmlspecialchars($cycle['production_type']); ?></td>
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
