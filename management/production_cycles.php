<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../includes/functions.php');
require_once(__DIR__ . '/../includes/audit_helpers.php');
require_once(__DIR__ . '/../lib/poultry_cycle_lifecycle.php');
require_once(__DIR__ . '/../lib/poultry_cycle_acquisition.php');
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
    'bird_unit_cost' => '',
    'start_age_days' => '1',
    'notes' => '',
];

/**
 * Estimate cycle current stock using latest daily record(s).
 */
function getCycleCurrentStock(PDO $pdo, array $cycle): int
{
    $cycleId = (int)($cycle['id'] ?? 0);
    $farmType = strtolower((string)($cycle['farm_type'] ?? ''));
    $productionType = strtolower((string)($cycle['production_type'] ?? ''));

    if ($cycleId <= 0) {
        return 0;
    }

    if ($farmType === 'poultry' && $productionType === 'layer') {
        $stmt = $pdo->prepare(
            "SELECT opening_stock, mortality
             FROM layer_daily_records
             WHERE cycle_id = ?
             ORDER BY record_date DESC, id DESC
             LIMIT 1"
        );
        $stmt->execute([$cycleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? max(0, (int)$row['opening_stock'] - (int)$row['mortality']) : 0;
    }

    if ($farmType === 'poultry' && $productionType === 'broiler') {
        $stmt = $pdo->prepare(
            "SELECT opening_stock, mortality
             FROM broiler_daily_records
             WHERE cycle_id = ?
             ORDER BY record_date DESC, id DESC
             LIMIT 1"
        );
        $stmt->execute([$cycleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? max(0, (int)$row['opening_stock'] - (int)$row['mortality']) : 0;
    }

    if ($farmType === 'ruminant') {
        $latestDateStmt = $pdo->prepare(
            "SELECT MAX(record_date) FROM ruminant_daily_records WHERE cycle_id = ?"
        );
        $latestDateStmt->execute([$cycleId]);
        $latestDate = $latestDateStmt->fetchColumn();
        if (!$latestDate) {
            return 0;
        }

        $sumStmt = $pdo->prepare(
            "SELECT COALESCE(SUM(opening_stock - mortality), 0)
             FROM ruminant_daily_records
             WHERE cycle_id = ? AND record_date = ?"
        );
        $sumStmt->execute([$cycleId, $latestDate]);

        return max(0, (int)$sumStmt->fetchColumn());
    }

    return 0;
}

try {
    $cycleTableExists = ($pdo->query("SHOW TABLES LIKE 'production_cycles'")->rowCount() > 0);
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
            $birdUnitCostRaw = trim((string)($_POST['bird_unit_cost'] ?? ''));
            $startAgeDaysRaw = trim((string)($_POST['start_age_days'] ?? '1'));
            $notes = trim((string)($_POST['notes'] ?? ''));

            $createCycleForm = [
                'cycle_code' => $cycleCode,
                'farm_type' => $farmType !== '' ? $farmType : 'poultry',
                'production_type' => $productionType !== '' ? $productionType : 'layer',
                'start_date' => $startDate,
                'expected_end_date' => $expectedEndDate,
                'opening_headcount' => $openingHeadcountRaw,
                'bird_unit_cost' => $birdUnitCostRaw,
                'start_age_days' => $startAgeDaysRaw,
                'notes' => $notes,
            ];

            $openingHeadcount = filter_var($openingHeadcountRaw, FILTER_VALIDATE_INT);
            $birdUnitCost = $birdUnitCostRaw === '' ? null : filter_var($birdUnitCostRaw, FILTER_VALIDATE_FLOAT);
            $startAgeDays = filter_var($startAgeDaysRaw, FILTER_VALIDATE_INT);
            $allowedProductionTypes = [
                'poultry' => ['layer', 'broiler'],
                'ruminant' => ['cattle', 'goat', 'sheep', 'other'],
            ];

            if ($cycleCode === '' || $productionType === '' || $startDate === '') {
                $flash = ['type' => 'danger', 'message' => 'Cycle code, production type, and start date are required.', 'title' => 'Required cycle information is missing.'];
            } elseif (mb_strlen($cycleCode) > 100) {
                $flash = ['type' => 'danger', 'message' => 'Cycle code must be 100 characters or fewer.', 'title' => 'Cycle code is too long.'];
            } elseif (!in_array($farmType, allowedFarmTypes(false), true)) {
                $flash = ['type' => 'danger', 'message' => 'Farm type must be poultry or ruminant.', 'title' => 'Invalid farm type.'];
            } elseif (!isset($allowedProductionTypes[$farmType]) || !in_array($productionType, $allowedProductionTypes[$farmType], true)) {
                $flash = ['type' => 'danger', 'message' => 'Select a valid production type for the selected farm type.', 'title' => 'Invalid production type.'];
            } elseif ($openingHeadcount === false || $openingHeadcount < 0) {
                $flash = ['type' => 'danger', 'message' => 'Opening headcount must be 0 or greater.', 'title' => 'Invalid opening headcount.'];
            } elseif ($farmType === 'poultry' && ($birdUnitCost === false || ($birdUnitCost !== null && $birdUnitCost < 0))) {
                $flash = ['type' => 'danger', 'message' => 'Bird cost basis must be blank or 0 and above.', 'title' => 'Invalid bird cost basis.'];
            } elseif ($startAgeDays === false || $startAgeDays < 1) {
                $flash = ['type' => 'danger', 'message' => 'Start age must be at least 1 day.', 'title' => 'Invalid start age.'];
            } elseif ($expectedEndDate !== '' && $expectedEndDate < $startDate) {
                $flash = ['type' => 'danger', 'message' => 'Expected end date cannot be earlier than the cycle start date.', 'title' => 'Invalid cycle dates.'];
            } else {
                // Friendly pre-check: the database constraint remains the final safeguard.
                $duplicateStmt = $pdo->prepare(
                    'SELECT id FROM production_cycles WHERE farm_id = ? AND cycle_code = ? LIMIT 1'
                );
                $duplicateStmt->execute([$tenantFarmId, $cycleCode]);
                if ($duplicateStmt->fetchColumn()) {
                    $flash = [
                        'type' => 'danger',
                        'title' => 'Cycle code already exists.',
                        'message' => 'The cycle code "' . $cycleCode . '" is already being used in this farm. Please choose a different code.',
                        'tip' => 'Your other entries have been preserved. Change only the cycle code and submit again.',
                    ];
                } else {
                    try {
                        $pdo->beginTransaction();
                        $stmt = $pdo->prepare(
                            'INSERT INTO production_cycles
                            (farm_id, cycle_code, farm_type, production_type, status, start_date, expected_end_date, opening_headcount, bird_unit_cost, notes, created_by)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                        );
                        $stmt->execute([
                            $tenantFarmId,
                            $cycleCode,
                            $farmType,
                            $productionType,
                            'active',
                            $startDate,
                            $expectedEndDate !== '' ? $expectedEndDate : null,
                            $openingHeadcount,
                            $farmType === 'poultry' ? $birdUnitCost : null,
                            ($notes !== '' ? $notes : null),
                            $_SESSION['user_id'] ?? null,
                        ]);
                        $newCycleId = (int)$pdo->lastInsertId();

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
                        $pdo->commit();
                        $createCycleForm = [
                            'cycle_code' => '', 'farm_type' => $farmType, 'production_type' => $productionType,
                            'start_date' => '', 'expected_end_date' => '', 'opening_headcount' => '0', 'bird_unit_cost' => '', 'start_age_days' => '1', 'notes' => ''
                        ];
                        $flash = ['type' => 'success', 'message' => 'Production cycle created successfully.'];
                    } catch (PDOException $e) {
                        if ($pdo->inTransaction()) { $pdo->rollBack(); }
                        if ((int)$e->errorInfo[1] === 1062) {
                            $flash = [
                                'type' => 'danger',
                                'title' => 'Cycle code already exists.',
                                'message' => 'The cycle code "' . $cycleCode . '" is already being used in this farm. Please choose a different code.',
                                'tip' => 'Your other entries have been preserved. Change only the cycle code and submit again.',
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

        if ($action === 'record_poultry_acquisition') {
            if (!$poultryAcquisitionTableExists || !$poultryAcquisitionCorrectionReady) {
                $flash = ['type' => 'danger', 'title' => 'Acquisition migration required.', 'message' => 'Run the database migrations before recording poultry flock entry/acquisition.'];
            } else {
                $cycleId = (int)($_POST['cycle_id'] ?? 0);
                $acquisitionType = strtolower(trim((string)($_POST['acquisition_type'] ?? '')));
                $acquisitionDate = trim((string)($_POST['acquisition_date'] ?? ''));
                $quantityRaw = trim((string)($_POST['acquisition_quantity'] ?? ''));
                $ageDaysRaw = trim((string)($_POST['age_days'] ?? ''));
                $unitPriceRaw = trim((string)($_POST['unit_price'] ?? ''));
                $totalCostRaw = trim((string)($_POST['total_cost'] ?? ''));
                $requestToken = strtolower(trim((string)($_POST['request_token'] ?? '')));
                $sourceName = trim((string)($_POST['source_name'] ?? ''));
                $referenceNo = trim((string)($_POST['reference_no'] ?? ''));
                $acquisitionNotes = trim((string)($_POST['acquisition_notes'] ?? ''));

                $quantity = filter_var($quantityRaw, FILTER_VALIDATE_INT);
                $ageDays = filter_var($ageDaysRaw, FILTER_VALIDATE_INT);
                $unitPrice = $unitPriceRaw === '' ? null : filter_var($unitPriceRaw, FILTER_VALIDATE_FLOAT);
                $totalCost = $totalCostRaw === '' ? null : filter_var($totalCostRaw, FILTER_VALIDATE_FLOAT);
                if ($totalCost === null && $unitPrice !== null && $unitPrice !== false && $quantity !== false) {
                    $totalCost = round(((float)$unitPrice) * ((int)$quantity), 2);
                }
                try {
                    if ($quantity === false) { throw new InvalidArgumentException('Acquisition quantity must be a whole number greater than 0.'); }
                    if ($ageDays === false) { throw new InvalidArgumentException('Age at acquisition must be a whole number of days.'); }
                    if ($unitPriceRaw !== '' && ($unitPrice === false || (float)$unitPrice < 0)) { throw new InvalidArgumentException('Enter a valid unit purchase price.'); }
                    if ($totalCostRaw !== '' && $totalCost === false) { throw new InvalidArgumentException('Enter a valid total acquisition amount.'); }
                    poultry_acquisition_record(
                        $pdo,
                        $tenantFarmId,
                        $cycleId,
                        $acquisitionType,
                        $acquisitionDate,
                        (int)$quantity,
                        (int)$ageDays,
                        $totalCost === null ? null : (float)$totalCost,
                        $sourceName,
                        $referenceNo,
                        $acquisitionNotes,
                        isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
                        $requestToken
                    );
                    $_SESSION['success'] = 'Poultry acquisition recorded. The flock entry fact was saved once without changing Inventory, Expenses, period profitability, bird cost basis, or lifecycle history.';
                    header('Location: ' . BASE_URL . '/management/production_cycles.php#poultry-entry-acquisition');
                    exit();
                } catch (Throwable $e) {
                    $safeMessage = ($e instanceof InvalidArgumentException || $e instanceof PoultryAcquisitionException) ? $e->getMessage() : 'The poultry acquisition could not be saved. No acquisition history was changed.';
                    if (!($e instanceof InvalidArgumentException) && !($e instanceof PoultryAcquisitionException)) { error_log('Poultry acquisition record failed: ' . $e->getMessage()); }
                    $flash = ['type' => 'danger', 'title' => 'Poultry acquisition was not saved.', 'message' => $safeMessage];
                }
            }
        }

        if ($action === 'void_poultry_acquisition') {
            if (!$poultryAcquisitionTableExists || !$poultryAcquisitionCorrectionReady) {
                $flash = ['type' => 'danger', 'title' => 'Acquisition migration required.', 'message' => 'Run the database migrations before correcting poultry acquisition history.'];
            } else {
                $acquisitionId = (int)($_POST['acquisition_id'] ?? 0);
                $voidReason = trim((string)($_POST['void_reason'] ?? ''));
                try {
                    poultry_acquisition_void($pdo, $tenantFarmId, $acquisitionId, $voidReason, isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
                    $_SESSION['success'] = 'Acquisition entry voided. The original row remains in audit history and is excluded from active acquisition totals.';
                    header('Location: ' . BASE_URL . '/management/production_cycles.php#poultry-entry-acquisition');
                    exit();
                } catch (Throwable $e) {
                    $safeMessage = ($e instanceof InvalidArgumentException || $e instanceof PoultryAcquisitionException) ? $e->getMessage() : 'The acquisition entry could not be voided. No acquisition history was changed.';
                    if (!($e instanceof InvalidArgumentException) && !($e instanceof PoultryAcquisitionException)) { error_log('Poultry acquisition void failed: ' . $e->getMessage()); }
                    $flash = ['type' => 'danger', 'title' => 'Acquisition entry was not voided.', 'message' => $safeMessage];
                }
            }
        }

        if ($action === 'set_initial_poultry_phase') {
            if (!$poultryPhaseTableExists) {
                $flash = ['type' => 'danger', 'title' => 'Lifecycle migration required.', 'message' => 'Run the database migrations before recording poultry lifecycle history.'];
            } else {
                $cycleId = (int)($_POST['cycle_id'] ?? 0);
                $phase = strtolower(trim((string)($_POST['phase'] ?? '')));
                $startDate = trim((string)($_POST['phase_start_date'] ?? ''));
                $phaseNotes = trim((string)($_POST['phase_notes'] ?? ''));
                try {
                    poultry_lifecycle_record_initial_phase($pdo, $tenantFarmId, $cycleId, $phase, $startDate, $phaseNotes, isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
                    $flash = ['type' => 'success', 'title' => 'Lifecycle history started.', 'message' => 'The initial poultry biological phase was recorded explicitly. No historical phase was inferred.'];
                } catch (Throwable $e) {
                    $safeMessage = ($e instanceof InvalidArgumentException || $e instanceof PoultryLifecycleException) ? $e->getMessage() : 'The lifecycle phase could not be saved. No lifecycle history was changed.';
                    if (!($e instanceof InvalidArgumentException) && !($e instanceof PoultryLifecycleException)) { error_log('Initial poultry lifecycle phase failed: ' . $e->getMessage()); }
                    $flash = ['type' => 'danger', 'title' => 'Lifecycle phase was not saved.', 'message' => $safeMessage];
                }
            }
        }

        if ($action === 'transition_poultry_phase') {
            if (!$poultryPhaseTableExists) {
                $flash = ['type' => 'danger', 'title' => 'Lifecycle migration required.', 'message' => 'Run the database migrations before recording poultry lifecycle history.'];
            } else {
                $cycleId = (int)($_POST['cycle_id'] ?? 0);
                $nextPhase = strtolower(trim((string)($_POST['phase'] ?? '')));
                $transitionDate = trim((string)($_POST['phase_start_date'] ?? ''));
                $phaseNotes = trim((string)($_POST['phase_notes'] ?? ''));
                try {
                    poultry_lifecycle_transition_phase($pdo, $tenantFarmId, $cycleId, $nextPhase, $transitionDate, $phaseNotes, isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
                    $flash = ['type' => 'success', 'title' => 'Lifecycle transition recorded.', 'message' => 'The previous phase was closed on the day before this transition and the new phase was appended to history.'];
                } catch (Throwable $e) {
                    $safeMessage = ($e instanceof InvalidArgumentException || $e instanceof PoultryLifecycleException) ? $e->getMessage() : 'The lifecycle transition could not be saved. No lifecycle history was changed.';
                    if (!($e instanceof InvalidArgumentException) && !($e instanceof PoultryLifecycleException)) { error_log('Poultry lifecycle transition failed: ' . $e->getMessage()); }
                    $flash = ['type' => 'danger', 'title' => 'Lifecycle transition was not saved.', 'message' => $safeMessage];
                }
            }
        }

        if ($action === 'end_poultry_phase') {
            if (!$poultryPhaseTableExists) {
                $flash = ['type' => 'danger', 'title' => 'Lifecycle migration required.', 'message' => 'Run the database migrations before recording poultry lifecycle history.'];
            } else {
                $cycleId = (int)($_POST['cycle_id'] ?? 0);
                $endDate = trim((string)($_POST['phase_end_date'] ?? ''));
                try {
                    poultry_lifecycle_end_current_phase($pdo, $tenantFarmId, $cycleId, $endDate, isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
                    $flash = ['type' => 'success', 'title' => 'Lifecycle phase ended.', 'message' => 'The current biological phase was closed without changing the production cycle operational status.'];
                } catch (Throwable $e) {
                    $safeMessage = ($e instanceof InvalidArgumentException || $e instanceof PoultryLifecycleException) ? $e->getMessage() : 'The lifecycle phase could not be ended. No lifecycle history was changed.';
                    if (!($e instanceof InvalidArgumentException) && !($e instanceof PoultryLifecycleException)) { error_log('Poultry lifecycle phase end failed: ' . $e->getMessage()); }
                    $flash = ['type' => 'danger', 'title' => 'Lifecycle phase was not ended.', 'message' => $safeMessage];
                }
            }
        }

        if ($action === 'close_cycle') {
            $cycleId = (int)($_POST['cycle_id'] ?? 0);
            $closeDate = $_POST['close_date'] ?? '';
            $postedClosingHeadcount = trim((string)($_POST['closing_headcount'] ?? ''));
            $closingHeadcount = ($postedClosingHeadcount === '') ? 0 : (int)$postedClosingHeadcount;

            if ($cycleId <= 0 || $closeDate === '') {
                $flash = ['type' => 'danger', 'message' => 'Cycle and close date are required to close a production cycle.'];
            } else {
                $cycleStmt = $pdo->prepare(
                    'SELECT id, cycle_code, farm_type, production_type
                     FROM production_cycles
                     WHERE id = ? AND farm_id = ? AND status = ?
                     LIMIT 1'
                );
                $cycleStmt->execute([$cycleId, $tenantFarmId, 'active']);
                $cycleToClose = $cycleStmt->fetch(PDO::FETCH_ASSOC);

                if (!$cycleToClose) {
                    $flash = ['type' => 'danger', 'message' => 'Selected active cycle was not found.'];
                } else {
                    if ($closingHeadcount <= 0) {
                        $closingHeadcount = getCycleCurrentStock($pdo, $cycleToClose);
                    }

                    $stmt = $pdo->prepare(
                        'UPDATE production_cycles
                         SET status = ?, close_date = ?, closing_headcount = ?
                         WHERE id = ? AND farm_id = ?'
                    );
                    $stmt->execute(['closed', $closeDate, $closingHeadcount, $cycleId, $tenantFarmId]);
                    $flash = ['type' => 'success', 'message' => 'Cycle closed successfully.'];
                }
            }
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

        $activeStmt = $pdo->prepare("SELECT id, cycle_code, farm_type, production_type, bird_unit_cost FROM production_cycles WHERE farm_id = ? AND status = 'active' ORDER BY start_date DESC");
        $activeStmt->execute([$tenantFarmId]);
        $activeCycles = $activeStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($activeCycles as &$cycle) {
            $cycle['current_stock'] = getCycleCurrentStock($pdo, $cycle);
            $summary['total_current_stock'] += (int)$cycle['current_stock'];
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
    error_log('Production cycle page error: ' . $exception->getMessage());
    $errorMessage = 'We could not load the production cycle data right now. Please try again.';
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
<body>
<?php include(__DIR__ . '/../navbar.php'); ?>

<div class="container-fluid mt-4">
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-2"><i class="bi bi-arrow-repeat"></i> Production Cycles</h4>
                    <p class="text-muted mb-1">This page is where the new cycle model appears in the platform.</p>
                    <p class="mb-0">Use this for Create Cycle, Close Cycle, and cycle-level monitoring. Poultry cycles now open into a dedicated Manage Cycle workspace so this overview does not keep growing into a wall of forms.</p>
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

        <div class="row g-3 mb-3">
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
                            <div class="mb-2" id="birdCostBasisWrap">
                                <label class="form-label">Bird Cost Basis (₦ / bird)</label>
                                <input class="form-control" type="number" min="0" step="0.01" name="bird_unit_cost" value="<?php echo htmlspecialchars($createCycleForm['bird_unit_cost'], ENT_QUOTES); ?>" placeholder="Optional">
                                <div class="form-text">Poultry only. Used to value mortality; leave blank if no defensible bird cost is available.</div>
                            </div>
                            <div class="mb-2"><label class="form-label">Start Age (days)</label><input class="form-control" type="number" min="1" name="start_age_days" value="<?php echo htmlspecialchars($createCycleForm['start_age_days'], ENT_QUOTES); ?>"></div>
                            <div class="mb-2"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"><?php echo htmlspecialchars($createCycleForm['notes'], ENT_QUOTES); ?></textarea></div>
                            <button class="btn btn-success" type="submit">Create Cycle</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><strong>Close Cycle</strong></div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
                            <input type="hidden" name="action" value="close_cycle">
                            <div class="mb-2"><label class="form-label">Active Cycle</label>
                                <select class="form-select" name="cycle_id" required>
                                    <option value="">Select active cycle</option>
                                    <?php foreach ($activeCycles as $cycle): ?>
                                        <option value="<?php echo (int)$cycle['id']; ?>"><?php echo htmlspecialchars($cycle['cycle_code'] . ' - ' . $cycle['production_type']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-2"><label class="form-label">Close Date</label><input class="form-control" type="date" name="close_date" required></div>
                            <div class="mb-2"><label class="form-label">Closing Headcount</label><input class="form-control" type="number" min="0" name="closing_headcount" placeholder="Auto-fill from latest closing if left blank or 0"><div class="form-text">Leave blank to derive it from the selected cycle code's latest closing stock.</div></div>
                            <button class="btn btn-warning" type="submit">Close Cycle</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

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
                <strong>Poultry Entry / Acquisition</strong>
                
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">Records how this farm actually received the flock. Broilers may enter at day-old, 2 weeks, 4 weeks, or another known age. Layer Point-of-Lay purchase is recorded explicitly. Acquisition does not post Inventory or Expense transactions and does not alter period profitability or Bird Cost Basis.</p>
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
                        <div class="col-lg-5">
                            <div class="border rounded p-3 h-100">
                                <h6>Record Flock Entry</h6>
                                <p class="text-muted small">Use the bird age actually received by this farm. For Broilers, 1 = day-old, 14 = 2 weeks, 28 = 4 weeks. Do not invent an earlier rearing history.</p>
                                <form method="post" data-confirm="Record this poultry flock acquisition/entry fact?" data-confirm-title="Confirm flock entry" data-confirm-button="Record entry">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
                                    <input type="hidden" name="action" value="record_poultry_acquisition">
                                    <input type="hidden" name="request_token" value="<?php echo htmlspecialchars(bin2hex(random_bytes(24)), ENT_QUOTES); ?>">
                                    <div class="mb-2">
                                        <label class="form-label">Poultry Cycle</label>
                                        <select class="form-select" name="cycle_id" id="acquisitionCycle" required>
                                            <option value="">Select poultry cycle</option>
                                            <?php foreach ($poultryCycles as $cycle): ?>
                                                <option value="<?php echo (int)$cycle['id']; ?>" data-production-type="<?php echo htmlspecialchars($cycle['production_type'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($cycle['cycle_code'] . ' — ' . ucfirst($cycle['production_type'])); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">Entry / Acquisition Type</label>
                                        <select class="form-select" name="acquisition_type" id="acquisitionType" required>
                                            <option value="">Select type</option>
                                            <option value="purchased">Purchased birds</option>
                                            <option value="purchased_point_of_lay" data-layer-only="1">Purchased Point-of-Lay (Layer only)</option>
                                            <option value="internal_transfer">Farm-raised / transferred in</option>
                                        </select>
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-md-6 mb-2"><label class="form-label">Acquisition Date</label><input class="form-control" type="date" name="acquisition_date" required></div>
                                        <div class="col-md-6 mb-2"><label class="form-label">Quantity Received</label><input class="form-control" type="number" min="1" step="1" name="acquisition_quantity" required></div>
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-md-6 mb-2"><label class="form-label">Age at Acquisition (days)</label><input class="form-control" type="number" min="1" step="1" name="age_days" required><div class="form-text">Use actual age; no DOC assumption.</div></div>
                                        <div class="col-md-6 mb-2"><label class="form-label">Unit Purchase Price (₦ / bird)</label><input class="form-control" id="acquisitionUnitPrice" type="number" min="0" step="0.01" name="unit_price"><div class="form-text">Optional helper. Enter the supplier's price per bird if quoted that way.</div></div>
                                    </div>
                                    <div class="mb-2"><label class="form-label">Total Bird Acquisition Amount (₦)</label><input class="form-control" id="acquisitionTotalCost" type="number" min="0" step="0.01" name="total_cost"><div class="form-text"><strong>This is the total amount for all birds, not the unit price.</strong> For purchases it is required; when Quantity + Unit Price are entered, this field is calculated automatically and may be adjusted to the actual invoice total.</div></div>
                                    <div class="mb-2"><label class="form-label">Source / Supplier</label><input class="form-control" maxlength="190" name="source_name" placeholder="Optional supplier or source"></div>
                                    <div class="mb-2"><label class="form-label">Reference</label><input class="form-control" maxlength="120" name="reference_no" placeholder="Optional invoice, receipt or delivery reference"></div>
                                    <div class="mb-2"><label class="form-label">Notes</label><textarea class="form-control" name="acquisition_notes" rows="2" placeholder="Optional management context"></textarea></div>
                                    <button class="btn btn-primary w-100" type="submit">Record Flock Entry</button>
                                </form>
                            </div>
                        </div>
                        <div class="col-lg-7">
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
                                            <tr><td colspan="8" class="text-center text-muted py-3">No poultry acquisition history has been recorded. Existing cycles remain explicitly unknown until management records known entry facts.</td></tr>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php
                                $activeAcquisitionOptions = [];
                                foreach ($poultryCycles as $cycle) {
                                    foreach (($poultryAcquisitionHistoryByCycle[(int)$cycle['id']] ?? []) as $row) {
                                        if (empty($row['voided_at'])) {
                                            $activeAcquisitionOptions[] = ['cycle' => $cycle, 'row' => $row];
                                        }
                                    }
                                }
                                ?>
                                <?php if (!empty($activeAcquisitionOptions)): ?>
                                    <hr>
                                    <h6>Correct an Erroneous Entry</h6>
                                    <p class="text-muted small">Use this only for a mistaken or duplicated acquisition. The row is voided, not deleted, so audit history remains intact.</p>
                                    <form method="post" data-confirm="Void this acquisition entry? It will remain visible in history but stop contributing to acquisition totals." data-confirm-title="Confirm acquisition correction" data-confirm-button="Void entry">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
                                        <input type="hidden" name="action" value="void_poultry_acquisition">
                                        <div class="mb-2"><label class="form-label">Acquisition Entry</label><select class="form-select" name="acquisition_id" required><option value="">Select entry to void</option><?php foreach ($activeAcquisitionOptions as $opt): $r=$opt['row']; $c=$opt['cycle']; ?><option value="<?php echo (int)$r['id']; ?>">#<?php echo (int)$r['id']; ?> · <?php echo htmlspecialchars($c['cycle_code']); ?> · <?php echo htmlspecialchars($r['acquisition_date']); ?> · <?php echo number_format((int)$r['quantity']); ?> birds · <?php echo $r['total_cost'] !== null ? '₦'.number_format((float)$r['total_cost'],2) : 'basis pending'; ?></option><?php endforeach; ?></select></div>
                                        <div class="mb-2"><label class="form-label">Correction Reason</label><input class="form-control" name="void_reason" maxlength="255" required placeholder="e.g. Duplicate browser resubmission or incorrect amount entered"></div>
                                        <button class="btn btn-outline-warning" type="submit">Void Erroneous Entry</button>
                                    </form>
                                <?php endif; ?>
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
                <?php elseif (!$poultryAcquisitionCorrectionReady): ?>
                    <div class="alert alert-warning mb-0">
                        <strong>Batch 2A acquisition correction migration is required.</strong> Run <code>php scripts/run_migrations.php</code> to apply <code>039_poultry_acquisition_submission_corrections.sql</code> before recording or correcting flock entry facts.
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

                    <div class="row g-3">
                        <div class="col-lg-4">
                            <div class="border rounded p-3 h-100">
                                <h6>Set Initial Phase</h6>
                                <p class="text-muted small">Use only when this cycle has no lifecycle history. Existing cycles are deliberately not backfilled automatically.</p>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
                                    <input type="hidden" name="action" value="set_initial_poultry_phase">
                                    <div class="mb-2">
                                        <label class="form-label">Cycle</label>
                                        <select class="form-select" name="cycle_id" required>
                                            <option value="">Select cycle</option>
                                            <?php foreach ($poultryCycles as $cycle): ?>
                                                <?php if (!empty($poultryPhaseHistoryByCycle[(int)$cycle['id']])) continue; ?>
                                                <option value="<?php echo (int)$cycle['id']; ?>"><?php echo htmlspecialchars($cycle['cycle_code'] . ' — ' . ucfirst($cycle['production_type'])); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">Biological Phase</label>
                                        <select class="form-select" name="phase" required>
                                            <option value="">Select phase</option>
                                            <optgroup label="Layer">
                                                <option value="rearing">Rearing</option>
                                                <option value="production">Production</option>
                                            </optgroup>
                                            <optgroup label="Broiler">
                                                <option value="growing">Growing / Rearing</option>
                                                <option value="harvest">Harvest / Sale</option>
                                            </optgroup>
                                        </select>
                                    </div>
                                    <div class="mb-2"><label class="form-label">Phase Start Date</label><input class="form-control" type="date" name="phase_start_date" required></div>
                                    <div class="mb-2"><label class="form-label">Notes</label><textarea class="form-control" name="phase_notes" rows="2" placeholder="Optional management context"></textarea></div>
                                    <button class="btn btn-primary w-100" type="submit">Record Initial Phase</button>
                                </form>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="border rounded p-3 h-100">
                                <h6>Record Phase Transition</h6>
                                <p class="text-muted small">A transition closes the current phase on the previous day and appends the new phase. History is not rewritten.</p>
                                <form method="post" data-confirm="Record this lifecycle transition?" data-confirm-title="Confirm lifecycle transition" data-confirm-button="Record transition">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
                                    <input type="hidden" name="action" value="transition_poultry_phase">
                                    <div class="mb-2">
                                        <label class="form-label">Cycle</label>
                                        <select class="form-select" name="cycle_id" required>
                                            <option value="">Select cycle</option>
                                            <?php foreach ($poultryCycles as $cycle): ?>
                                                <?php
                                                $currentPhase = $poultryLifecycleByCycle[(int)$cycle['id']] ?? null;
                                                $nextPhases = $currentPhase ? poultry_lifecycle_next_phases($cycle['production_type'], $currentPhase['phase']) : [];
                                                if (!$currentPhase || empty($nextPhases)) continue;
                                                ?>
                                                <option value="<?php echo (int)$cycle['id']; ?>"><?php echo htmlspecialchars($cycle['cycle_code'] . ' — ' . poultry_lifecycle_phase_label($cycle['production_type'], $currentPhase['phase'])); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">Next Phase</label>
                                        <select class="form-select" name="phase" required>
                                            <option value="">Select next phase</option>
                                            <option value="production">Layer — Production</option>
                                            <option value="harvest">Broiler — Harvest / Sale</option>
                                        </select>
                                    </div>
                                    <div class="mb-2"><label class="form-label">Transition Date</label><input class="form-control" type="date" name="phase_start_date" required></div>
                                    <div class="mb-2"><label class="form-label">Notes</label><textarea class="form-control" name="phase_notes" rows="2" placeholder="Optional management context"></textarea></div>
                                    <button class="btn btn-success w-100" type="submit">Record Transition</button>
                                </form>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="border rounded p-3 h-100">
                                <h6>End Current Phase</h6>
                                <p class="text-muted small">Use for a terminal biological phase such as Layer Production or Broiler Harvest / Sale. This does not close the production cycle.</p>
                                <form method="post" data-confirm="End the selected current biological phase?" data-confirm-title="Confirm phase end" data-confirm-button="End phase">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
                                    <input type="hidden" name="action" value="end_poultry_phase">
                                    <div class="mb-2">
                                        <label class="form-label">Cycle</label>
                                        <select class="form-select" name="cycle_id" required>
                                            <option value="">Select cycle with an open phase</option>
                                            <?php foreach ($poultryCycles as $cycle): ?>
                                                <?php
                                                $currentPhase = $poultryLifecycleByCycle[(int)$cycle['id']] ?? null;
                                                if (!$currentPhase || !empty(poultry_lifecycle_next_phases($cycle['production_type'], $currentPhase['phase']))) continue;
                                                ?>
                                                <option value="<?php echo (int)$cycle['id']; ?>"><?php echo htmlspecialchars($cycle['cycle_code'] . ' — ' . poultry_lifecycle_phase_label($cycle['production_type'], $currentPhase['phase'])); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-2"><label class="form-label">Phase End Date</label><input class="form-control" type="date" name="phase_end_date" required></div>
                                    <button class="btn btn-outline-warning w-100" type="submit">End Current Phase</button>
                                </form>
                            </div>
                        </div>
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
                                <tr><td colspan="5" class="text-center text-muted py-3">No poultry lifecycle history has been recorded. Existing cycles remain explicitly undefined until management records known history.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Cycles</h5>
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
                                        <?php if (strtolower((string)$cycle['farm_type']) === 'poultry' && in_array(strtolower((string)$cycle['production_type']), ['layer','broiler'], true)): ?>
                                            <a class="btn btn-sm btn-outline-primary" href="<?php echo BASE_URL; ?>/management/poultry_cycle.php?id=<?php echo (int)$cycle['id']; ?>">Manage Cycle</a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
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
<script>
document.addEventListener('DOMContentLoaded', function () {
    const farmType = document.querySelector('select[name="farm_type"]');
    const productionType = document.getElementById('productionType');
    if (!farmType || !productionType) return;

    const options = {
        poultry: ['layer', 'broiler'],
        ruminant: ['cattle', 'goat', 'sheep', 'other']
    };

    const birdCostBasisWrap = document.getElementById('birdCostBasisWrap');

    const render = () => {
        const selectedFarm = farmType.value || 'poultry';
        if (birdCostBasisWrap) birdCostBasisWrap.style.display = selectedFarm === 'poultry' ? '' : 'none';
        const requestedProduction = productionType.dataset.selected || productionType.value;
        productionType.innerHTML = '';
        (options[selectedFarm] || []).forEach((value) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = value.charAt(0).toUpperCase() + value.slice(1);
            if (value === requestedProduction) option.selected = true;
            productionType.appendChild(option);
        });
    };

    farmType.addEventListener('change', function () {
        productionType.dataset.selected = '';
        render();
    });
    render();
});
</script>

<script>
(function () {
    const cycle = document.getElementById('acquisitionCycle');
    const type = document.getElementById('acquisitionType');
    if (!cycle || !type) return;
    const sync = function () {
        const selected = cycle.options[cycle.selectedIndex];
        const productionType = selected ? selected.getAttribute('data-production-type') : '';
        Array.from(type.options).forEach(function (option) {
            if (option.getAttribute('data-layer-only') === '1') {
                option.hidden = productionType === 'broiler';
                option.disabled = productionType === 'broiler';
                if (productionType === 'broiler' && option.selected) type.value = '';
            }
        });
    };
    cycle.addEventListener('change', sync);
    sync();
})();
</script>


<script>
(function () {
    const qty = document.querySelector('input[name="acquisition_quantity"]');
    const unit = document.getElementById('acquisitionUnitPrice');
    const total = document.getElementById('acquisitionTotalCost');
    if (!qty || !unit || !total) return;
    let totalManuallyEdited = false;
    total.addEventListener('input', function () { totalManuallyEdited = total.value !== ''; });
    const recalc = function () {
        if (totalManuallyEdited) return;
        const q = Number(qty.value);
        const u = Number(unit.value);
        if (q > 0 && u >= 0 && unit.value !== '') total.value = (q * u).toFixed(2);
        else if (!unit.value) total.value = '';
    };
    qty.addEventListener('input', recalc);
    unit.addEventListener('input', function () { totalManuallyEdited = false; recalc(); });
})();
</script>

</body>
</html>
