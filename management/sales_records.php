<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../includes/pdf/PdfReportService.php');
requireLogin();
requireBusinessReportAccess();
$pdfRequested = pdf_report_is_requested();
if ($pdfRequested) {
    pdf_report_begin();
}

require_once(__DIR__ . '/../lib/attribution.php');
require_once(__DIR__ . '/../lib/sales_receivables.php');
require_once(__DIR__ . '/../lib/sales_allocation.php');
require_once(__DIR__ . '/../lib/ruminant_sale_animal_allocation.php');
require_once(__DIR__ . '/../lib/ruminant_animal_exit.php');
require_once(__DIR__ . '/../lib/sale_population_effects.php');
require_once(__DIR__ . '/../lib/sales_units.php');
require_once(__DIR__ . '/../lib/transaction_actor_display.php');
require_once(__DIR__ . '/../lib/record_reference_persistence.php');
require_once(__DIR__ . '/../lib/ruminant_slaughter_sale_consumption.php');
$tenantFarmId = requireCurrentFarmId();

$userType = getUserType();
$userFarmType = getUserFarmType();

$canChooseFarmType = isPlatformOwner() || hasRole('farm_admin', 'sales_rep');

// Restrict managers (except sales managers) to their assigned farm type
if (!$canChooseFarmType) {
    $farmType = $userFarmType;
}

$reportMode = $_GET['report_mode'] ?? 'monthly';
$month = $_GET['month'] ?? date('Y-m');
$year = $_GET['year'] ?? date('Y');

if ($reportMode === 'yearly') {
    $year = date('Y', strtotime($year . '-01-01'));
    $startDate = $year . '-01-01';
    $endDate = $year . '-12-31';
    $periodLabel = $year;
} else {
    $month = date('Y-m', strtotime($month . '-01'));
    $monthFilterDate = date('Y-m-d', strtotime($month . '-' . min((int)date('d'), (int)date('t', strtotime($month . '-01')))));
    $startDate = $month . '-01';
    $endDate = date('Y-m-t', strtotime($startDate));
    $periodLabel = date('F Y', strtotime($month));
}

$salesOnlyScope = enabledFarmTypes() === []
    && farmHasModule('sales')
    && (isPlatformOwner() || hasRole('farm_admin', 'sales_rep', 'viewer'));
// A sales-only workspace has no livestock scope to normalize. Use the neutral
// classification explicitly so its ledger can see general sales without also
// exposing historical poultry or ruminant records.
$requestedFarmType = $farmType ?? ($_GET['farm_type'] ?? null);
if ($requestedFarmType === 'both' && count(accessibleFarmTypes()) === 2) {
    $requestedFarmType = 'all';
}
if ($salesOnlyScope) {
    $farmType = 'general';
} elseif ($requestedFarmType === 'general' && in_array('general', allowedSalesFarmTypes(), true)) {
    $farmType = 'general';
} else {
    $farmType = normalizeFarmType($requestedFarmType, true, false, $canChooseFarmType);
}
$productionTypeFilter = strtolower(trim((string)($_GET['production_type'] ?? 'all')));
$reportProductionOptions = $farmType === 'all' ? [] : attribution_production_types($farmType);
if ($productionTypeFilter !== 'all' && !isset($reportProductionOptions[$productionTypeFilter])) $productionTypeFilter='all';
$showActions = isPlatformOwner() || hasRole('farm_admin') || hasPermission(getUserType(), 'sales_edit') || hasPermission(getUserType(), 'sales_delete');
$privilegedSalesActions = isPlatformOwner() || hasRole('farm_admin');
$canAddSales = $privilegedSalesActions || hasPermission(getUserType(), 'sales_add');
$canRecordPayments = $privilegedSalesActions || hasPermission(getUserType(), 'sales_payment');
$canEditLedger = $privilegedSalesActions || hasPermission(getUserType(), 'sales_receivables_edit');
$canDeleteLedger = $privilegedSalesActions || hasPermission(getUserType(), 'sales_receivables_delete');
$canManageLedger = $canEditLedger || $canDeleteLedger;
$saleFarmTypes = allowedSalesFarmTypes();
$allCyclesStmt = $pdo->prepare("SELECT id, cycle_code, farm_type, production_type, status FROM production_cycles WHERE farm_id=? ORDER BY start_date DESC,id DESC");
$allCyclesStmt->execute([$tenantFarmId]);
$allSalesCycles = $allCyclesStmt->fetchAll(PDO::FETCH_ASSOC);
$saleFarmTypeLabel = static function (string $type): string {
    return ucfirst($type);
};


$prepareSlaughterSaleInput =
    static function (
        array &$input,
        ?int $saleId = null
    ) use (
        $pdo,
        $tenantFarmId
    ): array {
        $rows =
            ruminant_slaughter_sale_rows_from_post(
                $input
            );

        $selection =
            ruminant_slaughter_sale_selection(
                $pdo,
                $tenantFarmId,
                (string)(
                    $input['sale_date']
                    ?? ''
                ),
                $rows,
                $saleId
            );

        if (
            ($selection['mode'] ?? 'financial_only')
            !== 'slaughter_output'
        ) {
            return $selection;
        }

        /*
         * Physical slaughter-output provenance owns these fields.
         * Never trust browser-supplied equivalents for an Inventory-linked sale.
         */
        $input['farm_type'] =
            'ruminant';

        $input['production_type'] =
            (string)$selection['production_type'];

        $input['cycle_id'] =
            (string)(int)$selection['cycle_id'];

        $input['product_type'] =
            (string)$selection['product_type'];

        $input['quantity'] =
            number_format(
                (float)$selection['quantity'],
                2,
                '.',
                ''
            );

        $derivedUnit =
            (string)$selection['unit_of_measure'];

        if (
            array_key_exists(
                $derivedUnit,
                sales_unit_presets()
            )
        ) {
            $input['unit_preset'] =
                $derivedUnit;

            $input['unit_custom'] =
                '';
        } else {
            $input['unit_preset'] =
                '__custom__';

            $input['unit_custom'] =
                $derivedUnit;
        }

        /*
         * The source animal already left live population at slaughter.
         * Output sale must not create a second animal/population exit.
         */
        $input['population_effect_mode'] =
            'financial_only';

        unset(
            $input['population_cycle_ids'],
            $input['population_quantities']
        );

        $input['sale_animal_allocation_mode'] =
            'shared';

        unset(
            $input['sale_animal_ids'],
            $input['sale_animal_amounts'],
            $input['sale_animal_exit_outcomes']
        );

        return $selection;
    };

$renderSalePopulationEffectControls = static function (string $prefix): void {
    $idPrefix = $prefix === 'edit' ? 'edit' : 'add';
    ?>
    <div class="card mb-3 sale-population-effect-card" id="<?php echo $idPrefix; ?>PopulationEffectCard">
        <div class="card-body py-3">
            <div class="fw-semibold mb-2">Live Population Effect</div>

            <select
                name="population_effect_mode"
                id="<?php echo $idPrefix; ?>PopulationEffectMode"
                class="form-select"
            >
                <option value="financial_only" selected>
                    Financial only — no sale-owned population removal
                </option>
                <option value="remove_live_population">
                    Remove live population — explicit headcount by source cycle
                </option>
            </select>

            <div
                id="<?php echo $idPrefix; ?>PopulationEffectExplanation"
                class="small text-muted mt-1"
            >
                This option creates no sale-owned live-population deduction. For tagged
                ruminants, an explicit Sold live or Culled/slaughtered lifecycle outcome can
                still change population independently. Product type, quantity, and unit of
                measure remain financial/revenue data only.
            </div>

            <div
                id="<?php echo $idPrefix; ?>PopulationEffectRows"
                class="mt-3 d-none"
            ></div>

            <button
                type="button"
                id="<?php echo $idPrefix; ?>PopulationEffectAddRow"
                class="btn btn-sm btn-outline-secondary d-none"
            >
                Add source cycle
            </button>

            <div
                id="<?php echo $idPrefix; ?>PopulationEffectRuminantNote"
                class="alert alert-info py-2 px-3 mt-2 mb-0 d-none"
            >
                Aggregate/group headcount only. Do not include tagged animals whose
                Sold live or Culled/slaughtered outcome is already handled through Animal Registry.
            </div>
        </div>
    </div>
    <?php
};

$selectedCustomer = trim($_GET['customer'] ?? '');

$debtFeatureEnabled = true;
try {
    $pdo->query("SELECT 1 FROM customer_ledger_entries LIMIT 1");
} catch (Throwable $e) {
    $debtFeatureEnabled = false;
}

// Build query based on filters
$salesSqlBase = "SELECT s.*, u.full_name as seller, u.user_type AS seller_user_type, pc.cycle_code,
                        COALESCE((SELECT SUM(sa.allocated_amount) FROM sales_allocations sa WHERE sa.farm_id=s.farm_id AND sa.sale_id=s.id),0) AS allocated_amount
                 FROM sales_records s
                 LEFT JOIN production_cycles pc ON pc.id=s.cycle_id AND pc.farm_id=s.farm_id
                 LEFT JOIN users u ON s.user_id=u.id AND u.farm_id=s.farm_id
                 WHERE s.farm_id=? AND s.sale_date BETWEEN ? AND ?";
$salesParams=[$tenantFarmId,$startDate,$endDate];
if ($farmType === '') {
    $salesSqlBase .= " AND 1=0";
} elseif ($farmType !== 'all') {
    $salesSqlBase .= " AND s.farm_type=?";
    $salesParams[]=$farmType;
}
if ($productionTypeFilter !== 'all') {
    $salesSqlBase .= " AND s.production_type=?";
    $salesParams[]=$productionTypeFilter;
}
$salesQuery=$salesSqlBase . " ORDER BY s.sale_date DESC,s.id DESC";
$salesStmt=$pdo->prepare($salesQuery); $salesStmt->execute($salesParams);
$salesRecords=$salesStmt->fetchAll();

$ruminantAnimalsStmt = $pdo->prepare("SELECT id,tag_no,species,status FROM ruminant_animals WHERE farm_id=? ORDER BY species,tag_no");
$ruminantAnimalsStmt->execute([$tenantFarmId]);
$ruminantSaleAnimals = $ruminantAnimalsStmt->fetchAll(PDO::FETCH_ASSOC);
$ruminantSaleAnimalAllocations = ruminant_sale_allocations_for_sales($pdo, $tenantFarmId, array_column($salesRecords, 'id'));
$ruminantSaleExitEvents = ruminant_sale_exit_events_for_sales($pdo, $tenantFarmId, array_column($salesRecords, 'id'));
$salePopulationEffectMap = sale_population_effect_rows_for_sales(
    $pdo,
    $tenantFarmId,
    array_column($salesRecords, 'id')
);


$slaughterSaleLots =
    in_array(
        'ruminant',
        $saleFarmTypes,
        true
    )
        ? ruminant_slaughter_sale_available_lots(
            $pdo,
            $tenantFarmId
        )
        : [];

$slaughterSaleHistoryMap =
    ruminant_slaughter_sale_history_for_sales(
        $pdo,
        $tenantFarmId,
        array_column(
            $salesRecords,
            'id'
        )
    );

// Get sales summary with the same attribution scope as the detail ledger.
if ($farmType === '') {
    $summaries=[]; $summary=['total_sales'=>0,'transaction_count'=>0,'avg_price'=>0];
} elseif ($farmType === 'all') {
    $summaryQuery="SELECT SUM(total_amount) total_sales,COUNT(*) transaction_count,AVG(unit_price) avg_price,farm_type
                   FROM sales_records WHERE farm_id=? AND sale_date BETWEEN ? AND ?";
    $summaryParams=[$tenantFarmId,$startDate,$endDate];
    if ($productionTypeFilter !== 'all') { $summaryQuery.=" AND production_type=?"; $summaryParams[]=$productionTypeFilter; }
    $summaryQuery.=" GROUP BY farm_type";
    $summaryStmt=$pdo->prepare($summaryQuery); $summaryStmt->execute($summaryParams); $summaries=$summaryStmt->fetchAll();
} else {
    $summaryQuery="SELECT SUM(total_amount) total_sales,COUNT(*) transaction_count,AVG(unit_price) avg_price
                   FROM sales_records WHERE farm_id=? AND sale_date BETWEEN ? AND ? AND farm_type=?";
    $summaryParams=[$tenantFarmId,$startDate,$endDate,$farmType];
    if ($productionTypeFilter !== 'all') { $summaryQuery.=" AND production_type=?"; $summaryParams[]=$productionTypeFilter; }
    $summaryStmt=$pdo->prepare($summaryQuery); $summaryStmt->execute($summaryParams); $summary=$summaryStmt->fetch();
}

$customerBalances = [];
$customerLedger = [];
$selectedCustomerBalance = 0.0;
$selectedCustomerTotalCredit = 0.0;
$selectedCustomerTotalPayments = 0.0;
$selectedCustomerTotalSales = 0.0;
$selectedCustomerUpfrontPayments = 0.0;
$selectedCustomerGrandTotalPaid = 0.0;

if ($debtFeatureEnabled) {
    $customerBalancesStmt = $pdo->query("SELECT customer_name, SUM(amount) AS balance
        FROM customer_ledger_entries
        WHERE farm_id = $tenantFarmId
        GROUP BY customer_name
        ORDER BY customer_name ASC");
    $customerBalances = $customerBalancesStmt->fetchAll();

    if ($selectedCustomer !== '') {
        $ledgerStmt = $pdo->prepare("SELECT l.*,
            s.public_reference AS sale_public_reference,
            u.full_name AS recorded_by_name,
            u.user_type AS recorded_by_user_type
            FROM customer_ledger_entries l
            LEFT JOIN sales_records s ON s.id = l.sale_id AND s.farm_id = l.farm_id
            LEFT JOIN users u ON l.user_id = u.id AND u.farm_id = l.farm_id
            WHERE l.farm_id = ? AND l.customer_name = ?
            ORDER BY l.entry_date ASC, l.id ASC");
        $ledgerStmt->execute([$tenantFarmId, $selectedCustomer]);
        $customerLedger = $ledgerStmt->fetchAll();

        foreach ($customerLedger as $entry) {
            $amount = (float)$entry['amount'];
            if ($amount > 0) {
                $selectedCustomerTotalCredit += $amount;
            } else {
                $selectedCustomerTotalPayments += abs($amount);
            }
            $selectedCustomerBalance += $amount;
        }

        $customerSalesTotalStmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0)
            FROM sales_records
            WHERE farm_id = ? AND customer_name = ?");
        $customerSalesTotalStmt->execute([$tenantFarmId, $selectedCustomer]);
        $selectedCustomerTotalSales = (float)$customerSalesTotalStmt->fetchColumn();
        $upfrontStmt = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN payment_received IS NOT NULL THEN payment_received ELSE 0 END),0), COUNT(*) AS sale_count, SUM(CASE WHEN payment_received IS NULL THEN 1 ELSE 0 END) AS legacy_count FROM sales_records WHERE farm_id=? AND customer_name=?");
        $upfrontStmt->execute([$tenantFarmId,$selectedCustomer]);
        $upfrontRow=$upfrontStmt->fetch(PDO::FETCH_NUM) ?: [0,0,0];
        $selectedCustomerUpfrontPayments=(float)$upfrontRow[0];
        if((int)$upfrontRow[2]>0) { $selectedCustomerUpfrontPayments += max(0, $selectedCustomerTotalSales - $selectedCustomerTotalCredit - $selectedCustomerUpfrontPayments); }
        $selectedCustomerGrandTotalPaid = $selectedCustomerUpfrontPayments + $selectedCustomerTotalPayments;
    }
}

$saleBalanceMap = [];
$openCreditSales = [];
if ($debtFeatureEnabled && $selectedCustomer !== '') {
    $saleBalancesStmt = $pdo->prepare("SELECT sale_id, SUM(amount) AS sale_balance
        FROM customer_ledger_entries
        WHERE farm_id = ? AND customer_name = ? AND sale_id IS NOT NULL
        GROUP BY sale_id");
    $saleBalancesStmt->execute([$tenantFarmId, $selectedCustomer]);
    $saleBalances = $saleBalancesStmt->fetchAll();
    foreach ($saleBalances as $row) {
        $saleBalanceMap[(int)$row['sale_id']] = (float)$row['sale_balance'];
    }

    $openSalesStmt = $pdo->prepare("SELECT s.id, s.public_reference, s.sale_date, s.product_type, s.quantity, s.unit_of_measure
        FROM sales_records s
        INNER JOIN customer_ledger_entries l ON l.sale_id = s.id AND l.farm_id = s.farm_id
        WHERE s.farm_id = ? AND l.farm_id = ? AND l.customer_name = ?
        GROUP BY s.id, s.sale_date, s.product_type, s.quantity
        ORDER BY s.sale_date ASC, s.id ASC");
    $openSalesStmt->execute([$tenantFarmId, $tenantFarmId, $selectedCustomer]);
    $creditSales = $openSalesStmt->fetchAll();
    foreach ($creditSales as $sale) {
        $saleId = (int)$sale['id'];
        $balance = $saleBalanceMap[$saleId] ?? 0;
        if ($balance > 0) {
            $sale['open_balance'] = $balance;
            $openCreditSales[] = $sale;
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_sale'])) {
        if (!$canAddSales) { http_response_code(403); exit('You do not have permission to add sales.'); }

        try {
            $slaughterSaleSelection =
                $prepareSlaughterSaleInput(
                    $_POST
                );
        } catch (RuminantSlaughterSaleException $e) {
            $_SESSION['error'] =
                $e->getMessage();

            header(
                "Location: sales_records.php?report_mode={$reportMode}&month={$month}&year={$year}&farm_type={$farmType}"
            );
            exit();
        }
        $quantity = (float)($_POST['quantity'] ?? 0);
        $unitPrice = (float)($_POST['unit_price'] ?? 0);
        $totalAmount = $quantity * $unitPrice;
        try { $unitOfMeasure = sales_unit_from_post($_POST); } catch (RuntimeException $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: sales_records.php?report_mode={$reportMode}&month={$month}&year={$year}&farm_type={$farmType}");
            exit();
        }
        $paymentReceived = (float)($_POST['payment_received'] ?? 0);
        $customerName = trim((string)($_POST['customer_name'] ?? ''));
        $outstandingAmount = max(0, $totalAmount - $paymentReceived);

        if ($paymentReceived < 0 || $paymentReceived > $totalAmount) {
            $_SESSION['error'] = "Payment received cannot be less than 0 or greater than total sale amount.";
            header("Location: sales_records.php?report_mode={$reportMode}&month={$month}&year={$year}&farm_type={$farmType}");
            exit();
        }
        if ($outstandingAmount > 0 && $customerName === '') {
            $_SESSION['error'] = "Customer name is required for credit/partial sales.";
            header("Location: sales_records.php?report_mode={$reportMode}&month={$month}&year={$year}&farm_type={$farmType}");
            exit();
        }

        $saleFarmType = $_POST['farm_type'] ?? '';
        if (!in_array($saleFarmType, $saleFarmTypes, true)) {
            $_SESSION['error'] = "That farm type is not enabled for this farm.";
            header("Location: sales_records.php?report_mode={$reportMode}&month={$month}&year={$year}&farm_type={$farmType}");
            exit();
        }

        $productionType = attribution_normalize_production_type($saleFarmType, $_POST['production_type'] ?? null);
        $cycleId = (int)($_POST['cycle_id'] ?? 0);
        try {
            attribution_validate_cycle($pdo, $tenantFarmId, $cycleId > 0 ? $cycleId : null, $saleFarmType, $productionType);
        } catch (RuntimeException $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: sales_records.php?report_mode={$reportMode}&month={$month}&year={$year}&farm_type={$farmType}");
            exit();
        }
        $scope = attribution_scope($cycleId > 0 ? $cycleId : null, $saleFarmType, $productionType);
        try {
            $populationEffectRows = sale_population_effect_rows_from_post($_POST);
        } catch (InvalidArgumentException $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: sales_records.php?report_mode={$reportMode}&month={$month}&year={$year}&farm_type={$farmType}");
            exit();
        }
        try {
            if ($saleFarmType !== 'ruminant') {
                $animalRevenueAllocation = [
                    'mode' => 'shared',
                    'rows' => [],
                ];

            } elseif (
                ($slaughterSaleSelection['mode'] ?? 'financial_only')
                === 'slaughter_output'
            ) {
                $animalRevenueAllocation =
                    ruminant_sale_build_slaughter_output_allocations(
                        $slaughterSaleSelection,
                        $totalAmount
                    );

            } else {
                $animalRevenueAllocation =
                    ruminant_sale_build_animal_allocations(
                        $pdo,
                        $tenantFarmId,
                        $productionType,
                        $totalAmount,
                        $_POST
                    );
            }
        } catch (RuntimeException $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: sales_records.php?report_mode={$reportMode}&month={$month}&year={$year}&farm_type={$farmType}");
            exit();
        }

        $pdo->beginTransaction();
        try {
        $saleDate =
            (string)(
                $_POST[
                    'sale_date'
                ]
                ?? ''
            );

        $saleProductType =
            (string)(
                $_POST[
                    'product_type'
                ]
                ?? ''
            );

        $saleRemarks =
            (string)(
                $_POST[
                    'remarks'
                ]
                ?? ''
            );

        $saleUserId =
            (int)(
                $_SESSION[
                    'user_id'
                ]
                ?? 0
            );

        $saleId =
            record_reference_persistence_insert_new(
                $pdo,
                'sale',
                static function (
                    string $publicReference,
                    string $createdAt
                ) use (
                    $pdo,
                    $tenantFarmId,
                    $saleDate,
                    $saleFarmType,
                    $productionType,
                    $scope,
                    $cycleId,
                    $saleProductType,
                    $quantity,
                    $unitOfMeasure,
                    $unitPrice,
                    $paymentReceived,
                    $customerName,
                    $saleRemarks,
                    $saleUserId
                ): int {
                    $stmt =
                        $pdo->prepare(
                            "INSERT INTO sales_records
                                (
                                    public_reference,
                                    farm_id,
                                    sale_date,
                                    farm_type,
                                    production_type,
                                    attribution_scope,
                                    cycle_id,
                                    product_type,
                                    quantity,
                                    unit_of_measure,
                                    unit_price,
                                    payment_received,
                                    customer_name,
                                    remarks,
                                    user_id,
                                    created_at
                                )
                             VALUES
                                (
                                    ?, ?, ?, ?, ?, ?, ?, ?,
                                    ?, ?, ?, ?, ?, ?, ?, ?
                                )"
                        );

                    $stmt->execute([
                        $publicReference,
                        $tenantFarmId,
                        $saleDate,
                        $saleFarmType,
                        $productionType,
                        $scope,
                        $cycleId > 0
                            ? $cycleId
                            : null,
                        $saleProductType,
                        $quantity,
                        $unitOfMeasure,
                        $unitPrice,
                        $paymentReceived,
                        $customerName !== ''
                            ? $customerName
                            : null,
                        $saleRemarks,
                        $saleUserId,
                        $createdAt,
                    ]);

                    return
                        (int)$pdo->lastInsertId();
                }
            );

        ruminant_slaughter_sale_sync(
            $pdo,
            $tenantFarmId,
            $saleId,
            $slaughterSaleSelection,
            (int)$_SESSION['user_id']
        );

        ruminant_sale_save_animal_allocations($pdo, $tenantFarmId, $saleId, $animalRevenueAllocation, (int)$_SESSION['user_id']);
        if ($saleFarmType === 'ruminant') {
            ruminant_sale_apply_exit_outcomes($pdo, $tenantFarmId, $saleId, (string)$_POST['sale_date'], $animalRevenueAllocation, $_POST, (int)$_SESSION['user_id']);
        }
        sale_population_effect_sync(
            $pdo,
            $tenantFarmId,
            $saleId,
            $populationEffectRows,
            (int)$_SESSION['user_id']
        );
        $allocationResult = sales_refresh_automatic_allocation($pdo, $tenantFarmId, $saleId, (int)$_SESSION['user_id']);
        if ($saleFarmType === 'poultry' && $productionType === 'layer' && layer_egg_is_sale_product($_POST['product_type'] ?? null)) {
            sales_rebuild_layer_egg_allocations($pdo, $tenantFarmId, (string)$_POST['sale_date'], (int)$_SESSION['user_id']);
            $allocationResult = sales_allocation_status($pdo, $tenantFarmId, $saleId, $totalAmount);
        }
        if ($debtFeatureEnabled && $customerName !== '' && $outstandingAmount > 0) {
            $ledgerStmt = $pdo->prepare("INSERT INTO customer_ledger_entries
                (farm_id, customer_name, entry_date, entry_type, amount, sale_id, notes, user_id)
                VALUES (?, ?, ?, 'sale', ?, ?, ?, ?)");
            $ledgerNote = sprintf(
                'Sale | %s - %s %s | Total Payment: %s - Upfront: %s',
                (string)$_POST['product_type'],
                number_format($quantity, 2),
                $unitOfMeasure,
                number_format($totalAmount, 2),
                number_format($paymentReceived, 2)
            );
            $ledgerStmt->execute([
                $tenantFarmId,
                $customerName,
                $_POST['sale_date'],
                $outstandingAmount,
                $saleId,
                $ledgerNote,
                $_SESSION['user_id']
            ]);
        }
        $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['error'] = safeUserExceptionMessage($e, 'The sale could not be recorded.');
            header("Location: sales_records.php?report_mode={$reportMode}&month={$month}&year={$year}&farm_type={$farmType}");
            exit();
        }

        $_SESSION['success'] = "Sale recorded successfully!";
        if ($saleFarmType === 'poultry' && $productionType === 'layer' && $cycleId <= 0 && layer_egg_is_sale_product($_POST['product_type'] ?? null)) {
            $statusNow = sales_allocation_status($pdo, $tenantFarmId, $saleId, $totalAmount);
            if (($statusNow['status'] ?? '') === 'allocated') {
                $_SESSION['success'] = "Sale recorded successfully. Shared Layer revenue was allocated to contributing cycles from unsold egg production.";
            } else {
                $_SESSION['warning'] = "Sale was saved, but its shared Layer revenue could not yet be assigned to cycles. Please check that Daily Records contain enough unsold egg production for the quantity sold.";
            }
        }
        header("Location: sales_records.php?report_mode={$reportMode}&month={$month}&year={$year}&farm_type={$farmType}");
        exit();
    } elseif (isset($_POST['record_payment']) && $debtFeatureEnabled) {
        if (!$canRecordPayments) { http_response_code(403); exit('You do not have permission to record customer payments.'); }
        $customerName = trim((string)($_POST['payment_customer_name'] ?? ''));
        $paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
        $paymentAmount = (float)($_POST['payment_amount'] ?? 0);
        $paymentNote = trim((string)($_POST['payment_note'] ?? ''));
        $settleSaleId = (int)($_POST['settle_sale_id'] ?? 0);

        if ($customerName === '' || $paymentAmount <= 0) {
            $_SESSION['error'] = "Customer name and payment amount are required.";
            header("Location: sales_records.php?report_mode={$reportMode}&month={$month}&year={$year}&farm_type={$farmType}");
            exit();
        }

        $defaultNote = ($paymentNote !== '' ? $paymentNote : 'Debt payment');
        $insertPaymentEntry = function(float $amount, ?int $saleId, string $note) use ($pdo, $customerName, $paymentDate, $tenantFarmId) {
            $stmt = $pdo->prepare("INSERT INTO customer_ledger_entries
                (farm_id, customer_name, entry_date, entry_type, amount, sale_id, notes, user_id)
                VALUES (?, ?, ?, 'payment', ?, ?, ?, ?)");
            $stmt->execute([
                $tenantFarmId,
                $customerName,
                $paymentDate,
                -1 * $amount,
                $saleId,
                $note,
                $_SESSION['user_id']
            ]);
        };

        $allocationCount = 0;
        $pdo->beginTransaction();
        try {
            if ($settleSaleId > 0) {
                $saleBalanceStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) AS balance
                    FROM customer_ledger_entries
                    WHERE farm_id = ? AND customer_name = ? AND sale_id = ?");
                $saleBalanceStmt->execute([$tenantFarmId, $customerName, $settleSaleId]);
                $saleOutstanding = (float)$saleBalanceStmt->fetchColumn();

                if ($saleOutstanding <= 0) {
                    throw new RuntimeException("Selected sale already has no outstanding balance.");
                }
                if ($paymentAmount > $saleOutstanding) {
                    throw new RuntimeException("Payment is greater than selected sale outstanding (₦" . number_format($saleOutstanding, 2) . ").");
                }

                $saleReferenceStmt = $pdo->prepare("SELECT public_reference
                    FROM sales_records
                    WHERE id = ? AND farm_id = ?
                    LIMIT 1");
                $saleReferenceStmt->execute([$settleSaleId, $tenantFarmId]);
                $salePublicReference = (string)($saleReferenceStmt->fetchColumn() ?: '');

                if ($salePublicReference === '') {
                    throw new RuntimeException("Selected sale reference is unavailable.");
                }

                $saleContextText = " | Applied to Sale {$salePublicReference}";
                $insertPaymentEntry($paymentAmount, $settleSaleId, $defaultNote . $saleContextText);
                $allocationCount = 1;
            } else {
                $remainingPayment = $paymentAmount;
                $openSalesStmt = $pdo->prepare("SELECT s.id, s.public_reference, s.sale_date, SUM(l.amount) AS balance
                    FROM customer_ledger_entries l
                    INNER JOIN sales_records s ON s.id = l.sale_id AND s.farm_id = l.farm_id
                    WHERE s.farm_id = ? AND l.farm_id = ? AND l.customer_name = ? AND l.sale_id IS NOT NULL
                    GROUP BY s.id, s.public_reference, s.sale_date
                    HAVING SUM(l.amount) > 0
                    ORDER BY s.sale_date ASC, s.id ASC");
                $openSalesStmt->execute([$tenantFarmId, $tenantFarmId, $customerName]);
                $openSales = $openSalesStmt->fetchAll();

                $customerOutstanding = 0.0;
                foreach ($openSales as $openSale) {
                    $customerOutstanding += max(0.0, (float)$openSale['balance']);
                }

                if ($customerOutstanding <= 0.00001) {
                    throw new RuntimeException('This customer has no outstanding balance to settle.');
                }
                if ($paymentAmount > $customerOutstanding + 0.00001) {
                    throw new RuntimeException('Payment is greater than customer outstanding balance (₦' . number_format($customerOutstanding, 2) . ').');
                }

                foreach ($openSales as $openSale) {
                    if ($remainingPayment <= 0.00001) {
                        break;
                    }

                    $saleId = (int)$openSale['id'];
                    $salePublicReference = (string)($openSale['public_reference'] ?? '');

                    if ($salePublicReference === '') {
                        throw new RuntimeException("Sale reference is unavailable for FIFO allocation.");
                    }

                    $openBalance = (float)$openSale['balance'];
                    $allocation = min($openBalance, $remainingPayment);
                    if ($allocation <= 0) {
                        continue;
                    }

                    $insertPaymentEntry(
                        $allocation,
                        $saleId,
                        $defaultNote . " | FIFO Auto-allocation Sale {$salePublicReference}"
                    );
                    $remainingPayment -= $allocation;
                    $allocationCount++;
                }

                if ($remainingPayment > 0.00001) {
                    throw new RuntimeException('Payment could not be fully allocated to open receivables. No advance payment was recorded.');
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            $_SESSION['error'] = safeUserExceptionMessage($e, 'The sales transaction could not be completed.');
            header("Location: sales_records.php?report_mode={$reportMode}&month={$month}&year={$year}&farm_type={$farmType}&customer=" . urlencode($customerName));
            exit();
        }

        $_SESSION['success'] = "Debt payment recorded successfully! Allocated to {$allocationCount} sale record(s).";
        header("Location: sales_records.php?report_mode={$reportMode}&month={$month}&year={$year}&farm_type={$farmType}&customer=" . urlencode($customerName));
        exit();
    } elseif (isset($_POST['update_ledger_entry'])) {
        if (!$canEditLedger) {
            $_SESSION['error'] = "You do not have permission to edit debt ledger entries.";
            header("Location: sales_records.php?report_mode={$reportMode}&month={$month}&year={$year}&farm_type={$farmType}&customer=" . urlencode($selectedCustomer));
            exit();
        }

        $ledgerId = (int)($_POST['ledger_id'] ?? 0);
        $customerName = trim((string)($_POST['ledger_customer_name'] ?? ''));
        $entryDate = $_POST['ledger_entry_date'] ?? date('Y-m-d');
        $amount = (float)($_POST['ledger_amount'] ?? 0);
        $notes = trim((string)($_POST['ledger_notes'] ?? ''));

        if ($ledgerId <= 0 || $customerName === '' || $amount == 0.0) {
            $_SESSION['error'] = "Ledger update requires valid customer, amount, and entry id.";
            header("Location: sales_records.php?report_mode={$reportMode}&month={$month}&year={$year}&farm_type={$farmType}&customer=" . urlencode($selectedCustomer));
            exit();
        }

        $stmt = $pdo->prepare("UPDATE customer_ledger_entries
            SET customer_name = ?, entry_date = ?, amount = ?, notes = ?
            WHERE id = ? AND farm_id = ?");
        $stmt->execute([$customerName, $entryDate, $amount, $notes, $ledgerId, $tenantFarmId]);

        $_SESSION['success'] = "Ledger entry updated successfully.";
        header("Location: sales_records.php?report_mode={$reportMode}&month={$month}&year={$year}&farm_type={$farmType}&customer=" . urlencode($customerName));
        exit();
    } elseif (isset($_POST['delete_ledger_entry'])) {
        if (!$canDeleteLedger) {
            $_SESSION['error'] = "You do not have permission to delete debt ledger entries.";
            header("Location: sales_records.php?report_mode={$reportMode}&month={$month}&year={$year}&farm_type={$farmType}&customer=" . urlencode($selectedCustomer));
            exit();
        }

        $ledgerId = (int)($_POST['ledger_id'] ?? 0);
        if ($ledgerId <= 0) {
            $_SESSION['error'] = "Invalid ledger entry selected.";
            header("Location: sales_records.php?report_mode={$reportMode}&month={$month}&year={$year}&farm_type={$farmType}&customer=" . urlencode($selectedCustomer));
            exit();
        }

        $getCustomerStmt = $pdo->prepare("SELECT customer_name FROM customer_ledger_entries WHERE id = ? AND farm_id = ?");
        $getCustomerStmt->execute([$ledgerId, $tenantFarmId]);
        $customerName = (string)($getCustomerStmt->fetchColumn() ?: $selectedCustomer);

        $deleteStmt = $pdo->prepare("DELETE FROM customer_ledger_entries WHERE id = ? AND farm_id = ?");
        $deleteStmt->execute([$ledgerId, $tenantFarmId]);

        $_SESSION['success'] = "Ledger entry deleted successfully.";
        header("Location: sales_records.php?report_mode={$reportMode}&month={$month}&year={$year}&farm_type={$farmType}&customer=" . urlencode($customerName));
        exit();
    } elseif (isset($_POST['update_sale'])) {
        if (!isPlatformOwner() && !hasRole('farm_admin') && !hasPermission(getUserType(), 'sales_edit')) {
            $_SESSION['error'] = "You do not have permission to update sales.";
            header("Location: sales_records.php?report_mode={$reportMode}&month={$month}&year={$year}&farm_type={$farmType}");
            exit();
        }

        $saleIdForSlaughter =
            (int)(
                $_POST['sale_id']
                ?? 0
            );

        if ($saleIdForSlaughter <= 0) {
            $_SESSION['error'] =
                'Invalid sale selected for update.';

            header(
                "Location: sales_records.php?report_mode={$reportMode}&month={$month}&year={$year}&farm_type={$farmType}"
            );
            exit();
        }

        try {
            $slaughterSaleSelection =
                $prepareSlaughterSaleInput(
                    $_POST,
                    $saleIdForSlaughter
                );
        } catch (RuminantSlaughterSaleException $e) {
            $_SESSION['error'] =
                $e->getMessage();

            header(
                "Location: sales_records.php?report_mode={$reportMode}&month={$month}&year={$year}&farm_type={$farmType}"
            );
            exit();
        }

        $saleFarmType = $_POST['farm_type'] ?? '';
        if (!in_array($saleFarmType, $saleFarmTypes, true)) {
            $_SESSION['error'] = "That farm type is not enabled for this farm.";
            header("Location: sales_records.php?report_mode={$reportMode}&month={$month}&year={$year}&farm_type={$farmType}");
            exit();
        }

        $productionType = attribution_normalize_production_type($saleFarmType, $_POST['production_type'] ?? null);
        $cycleId = (int)($_POST['cycle_id'] ?? 0);
        try {
            attribution_validate_cycle($pdo, $tenantFarmId, $cycleId > 0 ? $cycleId : null, $saleFarmType, $productionType);
        } catch (RuntimeException $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: sales_records.php?report_mode={$reportMode}&month={$month}&year={$year}&farm_type={$farmType}");
            exit();
        }
        $scope = attribution_scope($cycleId > 0 ? $cycleId : null, $saleFarmType, $productionType);
        try { $unitOfMeasure = sales_unit_from_post($_POST); } catch (RuntimeException $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: sales_records.php?report_mode={$reportMode}&month={$month}&year={$year}&farm_type={$farmType}");
            exit();
        }
        $newTotal = (float)$_POST['quantity'] * (float)$_POST['unit_price'];
        try {
            $populationEffectRows = sale_population_effect_rows_from_post($_POST);
        } catch (InvalidArgumentException $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: sales_records.php?report_mode={$reportMode}&month={$month}&year={$year}&farm_type={$farmType}");
            exit();
        }
        try {
            if ($saleFarmType !== 'ruminant') {
                $animalRevenueAllocation = [
                    'mode' => 'shared',
                    'rows' => [],
                ];

            } elseif (
                ($slaughterSaleSelection['mode'] ?? 'financial_only')
                === 'slaughter_output'
            ) {
                $animalRevenueAllocation =
                    ruminant_sale_build_slaughter_output_allocations(
                        $slaughterSaleSelection,
                        $newTotal
                    );

            } else {
                $animalRevenueAllocation =
                    ruminant_sale_build_animal_allocations(
                        $pdo,
                        $tenantFarmId,
                        $productionType,
                        $newTotal,
                        $_POST
                    );
            }
        } catch (RuntimeException $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: sales_records.php?report_mode={$reportMode}&month={$month}&year={$year}&farm_type={$farmType}");
            exit();
        }

        $pdo->beginTransaction();
        try {
        $beforeSale =
            sales_assert_manual_revenue_edit_allowed(
                $pdo,
                $tenantFarmId,
                (int)$_POST['sale_id'],
                [
                    'sale_date' =>
                        (string)$_POST['sale_date'],

                    'farm_type' =>
                        $saleFarmType,

                    'production_type' =>
                        $productionType,

                    'attribution_scope' =>
                        $scope,

                    'cycle_id' =>
                        $cycleId > 0
                            ? $cycleId
                            : null,

                    'product_type' =>
                        (string)$_POST['product_type'],

                    'quantity' =>
                        (string)$_POST['quantity'],

                    'unit_of_measure' =>
                        $unitOfMeasure,

                    'unit_price' =>
                        (string)$_POST['unit_price'],

                    'total_amount' =>
                        number_format(
                            $newTotal,
                            2,
                            '.',
                            ''
                        ),
                ],
                count(
                    $animalRevenueAllocation['rows']
                    ?? []
                )
            );

        $postedUpfront = isset($_POST['edit_payment_received']) ? (float)$_POST['edit_payment_received'] : null;
        receivable_sync_sale_edit($pdo,$tenantFarmId,(int)$_POST['sale_id'],$newTotal,trim((string)$_POST['customer_name']),(string)$_POST['sale_date'],(string)$_POST['product_type'],(float)$_POST['quantity'],(int)$_SESSION['user_id'],$postedUpfront);

        $stmt = $pdo->prepare("UPDATE sales_records
            SET sale_date=?, farm_type=?, production_type=?, attribution_scope=?, cycle_id=?,
                product_type=?, quantity=?, unit_of_measure=?, unit_price=?, customer_name=?, remarks=?
            WHERE id=? AND farm_id=?");
        $stmt->execute([
            $_POST['sale_date'], $saleFarmType, $productionType, $scope,
            $cycleId > 0 ? $cycleId : null, $_POST['product_type'], $_POST['quantity'], $unitOfMeasure,
            $_POST['unit_price'], $_POST['customer_name'], $_POST['remarks'],
            $_POST['sale_id'], $tenantFarmId
        ]);
        ruminant_slaughter_sale_sync(
            $pdo,
            $tenantFarmId,
            (int)$_POST['sale_id'],
            $slaughterSaleSelection,
            (int)$_SESSION['user_id']
        );

        ruminant_sale_save_animal_allocations($pdo, $tenantFarmId, (int)$_POST['sale_id'], $animalRevenueAllocation, (int)$_SESSION['user_id']);
        if ($saleFarmType === 'ruminant') {
            ruminant_sale_apply_exit_outcomes($pdo, $tenantFarmId, (int)$_POST['sale_id'], (string)$_POST['sale_date'], $animalRevenueAllocation, $_POST, (int)$_SESSION['user_id']);
        } else {
            // Converting a formerly ruminant sale to another farm type must not leave a stale animal exit.
            ruminant_sale_apply_exit_outcomes($pdo, $tenantFarmId, (int)$_POST['sale_id'], (string)$_POST['sale_date'], ['mode'=>'shared','rows'=>[]], [], (int)$_SESSION['user_id']);
        }
        sale_population_effect_sync(
            $pdo,
            $tenantFarmId,
            (int)$_POST['sale_id'],
            $populationEffectRows,
            (int)$_SESSION['user_id']
        );
        $allocationResult = sales_refresh_automatic_allocation($pdo, $tenantFarmId, (int)$_POST['sale_id'], (int)$_SESSION['user_id']);
        $wasLayerEgg = (($beforeSale['farm_type'] ?? '') === 'poultry' && strtolower((string)($beforeSale['production_type'] ?? '')) === 'layer' && layer_egg_is_sale_product($beforeSale['product_type'] ?? null));
        $isLayerEgg = ($saleFarmType === 'poultry' && $productionType === 'layer' && layer_egg_is_sale_product($_POST['product_type'] ?? null));
        if ($wasLayerEgg || $isLayerEgg) {
            $oldDate = (string)($beforeSale['sale_date'] ?? $_POST['sale_date']);
            $rebuildFrom = min($oldDate, (string)$_POST['sale_date']);
            sales_rebuild_layer_egg_allocations($pdo, $tenantFarmId, $rebuildFrom, (int)$_SESSION['user_id']);
        }

        $pdo->commit();
        } catch (SaleRevenueAllocationLifecycleException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $_SESSION['error'] =
                $e->getMessage();

            header("Location: sales_records.php?report_mode={$reportMode}&month={$month}&year={$year}&farm_type={$farmType}");
            exit();

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $_SESSION['error'] =
                safeUserExceptionMessage(
                    $e,
                    'The sale could not be updated.'
                );

            header("Location: sales_records.php?report_mode={$reportMode}&month={$month}&year={$year}&farm_type={$farmType}");
            exit();
        }

        $_SESSION['success'] = "Sale updated successfully!";
        if ($saleFarmType === 'poultry' && $productionType === 'layer' && $cycleId <= 0 && layer_egg_is_sale_product($_POST['product_type'] ?? null)) {
            $updatedTotal = (float)$_POST['quantity'] * (float)$_POST['unit_price'];
            $statusNow = sales_allocation_status($pdo, $tenantFarmId, (int)$_POST['sale_id'], $updatedTotal);
            if (($statusNow['status'] ?? '') === 'allocated') {
                $_SESSION['success'] = "Sale updated successfully. Shared Layer revenue was reallocated from the updated egg-production pool.";
            } else {
                $_SESSION['warning'] = "Sale was updated, but its shared Layer revenue could not yet be assigned to cycles. Please review Daily Record egg production for the affected dates.";
            }
        }
        header("Location: sales_records.php?report_mode={$reportMode}&month={$month}&year={$year}&farm_type={$farmType}");
        exit();
    }
}
$pdfReportParams = $_GET; unset($pdfReportParams['pdf']); $pdfReportUrl = 'sales_report_pdf.php?' . http_build_query($pdfReportParams);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include(__DIR__ . '/../navbar_head.php'); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Records - Renee Farms</title>
</head>
<body>
    <?php include(__DIR__ . '/../navbar.php'); ?>

    <div class="container-fluid mt-4">

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4>
                            <i class="bi bi-graph-up"></i> 
                            Sales Records - <?php echo htmlspecialchars($periodLabel); ?>
                        </h4>
                        <div class="d-flex gap-2 report-controls">
                            <select class="form-select app-width-150" id="farmTypeFilter">
                                <?php if ($canChooseFarmType): ?>
                                <?php if ($salesOnlyScope): ?><option value="general" selected>All Sales</option><?php endif; ?>
                                <?php if (count(accessibleFarmTypes()) === 2): ?><option value="all" <?php echo $farmType == 'all' ? 'selected' : ''; ?>>All Farms</option><?php endif; ?>
                                <?php foreach (accessibleFarmTypes() as $type): ?><option value="<?php echo $type; ?>" <?php echo $farmType === $type ? 'selected' : ''; ?>><?php echo ucfirst($type); ?></option><?php endforeach; ?>
                                <?php if (in_array('general', $saleFarmTypes, true)): ?><option value="general" <?php echo $farmType==='general'?'selected':''; ?>>General</option><?php endif; ?>
                                <?php else: ?>
                                <option value="<?php echo $farmType; ?>" selected><?php echo ucfirst($farmType); ?></option>
                                <?php endif; ?>
                            </select>
                            <select class="form-select app-width-190" id="productionTypeFilter">
                                <option value="all">All Production Types</option>
                                <?php foreach($reportProductionOptions as $value=>$label): ?><option value="<?php echo htmlspecialchars($value); ?>" <?php echo $productionTypeFilter===$value?'selected':''; ?>><?php echo htmlspecialchars($label); ?></option><?php endforeach; ?>
                            </select>
                            <select class="form-select app-width-140" id="reportMode">
                                <option value="monthly" <?php echo $reportMode === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                <option value="yearly" <?php echo $reportMode === 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                            </select>
                            <input type="date" class="form-control js-calendar-input app-width-170<?php echo $reportMode === 'yearly' ? ' d-none' : ''; ?>" id="monthFilter"
                                   value="<?php echo $monthFilterDate ?? date('Y-m-d'); ?>">
                            <select class="form-select app-width-130<?php echo $reportMode === 'monthly' ? ' d-none' : ''; ?>" id="yearFilter">
                                <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php echo (string)$y === (string)$year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                            <a class="btn btn-primary<?php echo $reportMode === 'yearly' ? ' d-none' : ''; ?>" id="printMonthlyBtn" href="<?php echo htmlspecialchars($pdfReportUrl); ?>" target="_blank"><i class="bi bi-file-earmark-pdf"></i> PDF Monthly</a>
                            <a class="btn btn-primary<?php echo $reportMode === 'monthly' ? ' d-none' : ''; ?>" id="printYearlyBtn" href="<?php echo htmlspecialchars($pdfReportUrl); ?>" target="_blank"><i class="bi bi-file-earmark-pdf"></i> PDF Yearly</a>
                            <?php if ($canAddSales): ?>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addSaleModal">
                                <i class="bi bi-plus-circle"></i> Add Sale
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Sales Summary -->
                    <div class="card-body bg-light">
                        <?php if ($farmType === 'all' && !empty($summaries)): ?>
                        <div class="row mb-4">
                            <?php foreach ($summaries as $summary): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card <?php echo $summary['farm_type'] == 'poultry' ? 'border-primary' : 'border-warning'; ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="card-title text-uppercase">
                                                    <?php echo $summary['farm_type']; ?> Sales
                                                </h6>
                                                <h3 class="text-success">₦<?php echo number_format($summary['total_sales'], 2); ?></h3>
                                                <small class="text-muted">
                                                    <?php echo $summary['transaction_count']; ?> transactions
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <small class="d-block">Avg Price</small>
                                                <h5>₦<?php echo number_format($summary['avg_price'], 2); ?></h5>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <?php 
                            $totalAllSales = array_sum(array_column($summaries, 'total_sales'));
                            $totalAllTransactions = array_sum(array_column($summaries, 'transaction_count'));
                            ?>
                            <div class="col-md-12 mt-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body text-center">
                                        <h2>TOTAL SALES: ₦<?php echo number_format($totalAllSales, 2); ?></h2>
                                        <h5><?php echo $totalAllTransactions; ?> Total Transactions</h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php elseif (isset($summary)): ?>
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="card text-white bg-success">
                                    <div class="card-body text-center">
                                        <h6>Total Sales</h6>
                                        <h2>₦<?php echo number_format($summary['total_sales'], 2); ?></h2>
                                        <small>For <?php echo htmlspecialchars($periodLabel); ?></small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card text-white bg-info">
                                    <div class="card-body text-center">
                                        <h6>Transactions</h6>
                                        <h2><?php echo $summary['transaction_count']; ?></h2>
                                        <small>Sales recorded</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card text-white bg-warning">
                                    <div class="card-body text-center">
                                        <h6>Average Price</h6>
                                        <h2>₦<?php echo number_format($summary['avg_price'], 2); ?></h2>
                                        <small>Per unit</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($debtFeatureEnabled): ?>
                        <div class="card border-secondary mb-4">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-wallet2 me-2"></i>Customer Debt Management</h5>
                                <div class="d-flex gap-2 no-print">
                                    <form method="GET" class="d-flex gap-2 app-responsive-form">
                                        <input type="hidden" name="report_mode" value="<?php echo htmlspecialchars($reportMode); ?>">
                                        <input type="hidden" name="month" value="<?php echo htmlspecialchars($month); ?>">
                                        <input type="hidden" name="year" value="<?php echo htmlspecialchars($year); ?>">
                                        <input type="hidden" name="farm_type" value="<?php echo htmlspecialchars($farmType); ?>">
                                        <select name="customer" class="form-select form-select-sm app-min-width-220" data-auto-submit>
                                            <option value="">Select customer ledger...</option>
                                            <?php foreach ($customerBalances as $customerRow): ?>
                                            <option value="<?php echo htmlspecialchars($customerRow['customer_name']); ?>" <?php echo $selectedCustomer === $customerRow['customer_name'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($customerRow['customer_name']); ?> (₦<?php echo number_format($customerRow['balance'], 2); ?>)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                    <?php if ($selectedCustomer !== ''): ?>
                                    <a class="btn btn-outline-primary btn-sm" id="printDebtBtn"
                                       href="debt_history_pdf.php?customer=<?php echo rawurlencode($selectedCustomer); ?>" target="_blank">
                                        <i class="bi bi-file-earmark-pdf me-1"></i>PDF Debt History
                                    </a>
                                    <?php else: ?>
                                    <button class="btn btn-outline-primary btn-sm" type="button" disabled>
                                        <i class="bi bi-file-earmark-pdf me-1"></i>PDF Debt History
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($canRecordPayments): ?>
                                    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
                                        <i class="bi bi-cash-coin me-1"></i>Record Payment
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if ($selectedCustomer === ''): ?>
                                    <p class="text-muted mb-0">Select a customer above to view credit history, outstanding balance, and payment timeline.</p>
                                <?php else: ?>
                                    <div class="row mb-3">
                                        <div class="col-md-4 mb-2">
                                            <div class="p-3 rounded border bg-light h-100 d-flex flex-column justify-content-center app-min-height-120">
                                                <small class="text-muted d-block">Current Outstanding Balance</small>
                                                <h5 class="mb-0 <?php echo $selectedCustomerBalance > 0 ? 'text-danger' : 'text-success'; ?>">₦<?php echo number_format($selectedCustomerBalance, 2); ?></h5>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <div class="p-3 rounded border bg-light h-100 d-flex flex-column justify-content-center app-min-height-120">
                                                <small class="text-muted d-block">Total Credit Taken</small>
                                                <h5 class="mb-0 text-primary">₦<?php echo number_format($selectedCustomerTotalCredit, 2); ?></h5>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <div class="p-3 rounded border bg-light h-100 d-flex flex-column justify-content-center app-min-height-120">
                                                <small class="text-muted d-block">Total Paid (Upfront + Debt)</small>
                                                <h5 class="mb-0 text-success">₦<?php echo number_format($selectedCustomerGrandTotalPaid, 2); ?></h5>
                                                <small class="text-muted d-block mt-1">Debt Settlements: ₦<?php echo number_format($selectedCustomerTotalPayments, 2); ?></small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="table-responsive" id="debtLedgerPrintArea" data-print-keep="header-with-first-row">
                                        <table class="table table-sm table-bordered align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Type</th>
                                                    <th>Sale Reference</th>
                                                    <th>Description</th>
                                                    <th>Amount (₦)</th>
                                                    <th>Running Balance (₦)</th>
                                                    <th>Recorded By</th>
                                                    <?php if ($canManageLedger): ?>
                                                    <th class="no-print">Actions</th>
                                                    <?php endif; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($customerLedger)): ?>
                                                <tr><td colspan="<?php echo $canManageLedger ? '8' : '7'; ?>" class="text-center text-muted">No debt ledger entries for this customer.</td></tr>
                                                <?php else: ?>
                                                    <?php $runningBalance = 0; ?>
                                                    <?php foreach ($customerLedger as $entry): ?>
                                                        <?php $runningBalance += (float)$entry['amount']; ?>
                                                        <?php
                                                            $entrySaleId = isset($entry['sale_id']) ? (int)$entry['sale_id'] : 0;
                                                            $saleOpenBalance = $entrySaleId > 0 ? ($saleBalanceMap[$entrySaleId] ?? 0) : 0;
                                                            $saleStatusLabel = $entrySaleId > 0
                                                                ? ($saleOpenBalance <= 0 ? 'Closed' : 'Open')
                                                                : null;
                                                        ?>
                                                        <tr>
                                                            <td><?php echo date('d/m/Y', strtotime($entry['entry_date'])); ?></td>
                                                            <td><span class="badge bg-<?php echo $entry['entry_type'] === 'payment' ? 'success' : ($entry['entry_type'] === 'sale' ? 'danger' : 'secondary'); ?>"><?php echo ucfirst($entry['entry_type']); ?></span></td>
                                                            <td class="text-nowrap">
                                                                <?php if (!empty($entry['sale_public_reference'])): ?>
                                                                    <code><?php echo htmlspecialchars((string)$entry['sale_public_reference']); ?></code>
                                                                <?php else: ?>
                                                                    <span class="text-muted">—</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php echo htmlspecialchars($entry['notes'] ?? '--'); ?>
                                                                <?php if ($saleStatusLabel !== null): ?>
                                                                    <span class="badge bg-<?php echo $saleStatusLabel === 'Closed' ? 'success' : 'warning'; ?> ms-1"><?php echo $saleStatusLabel; ?></span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="<?php echo (float)$entry['amount'] < 0 ? 'text-success' : 'text-danger'; ?>">
                                                                <?php echo ((float)$entry['amount'] < 0 ? '-' : '+') . number_format(abs((float)$entry['amount']), 2); ?>
                                                            </td>
                                                            <td class="fw-bold <?php echo $runningBalance > 0 ? 'text-danger' : 'text-success'; ?>">
                                                                <?php echo number_format($runningBalance, 2); ?>
                                                            </td>
                                                            <td><?php echo htmlspecialchars(transaction_recorded_by_label(
        transaction_actor_farm_name(
            $pdo,
            $tenantFarmId
        ),
        $entry['recorded_by_name'] ?? null,
        $entry['recorded_by_user_type'] ?? null
    )); ?></td>
                                                            <?php if ($canManageLedger): ?>
                                                            <td class="no-print">
                                                                <?php if ($canEditLedger): ?>
                                                                <button type="button"
                                                                        class="btn btn-sm btn-outline-primary edit-ledger-btn"
                                                                        data-id="<?php echo (int)$entry['id']; ?>"
                                                                        data-customer="<?php echo htmlspecialchars($entry['customer_name'], ENT_QUOTES); ?>"
                                                                        data-date="<?php echo htmlspecialchars($entry['entry_date'], ENT_QUOTES); ?>"
                                                                        data-amount="<?php echo htmlspecialchars($entry['amount'], ENT_QUOTES); ?>"
                                                                        data-notes="<?php echo htmlspecialchars($entry['notes'] ?? '', ENT_QUOTES); ?>">
                                                                    <i class="bi bi-pencil"></i>
                                                                </button>
                                                                <?php endif; ?>
                                                                <?php if ($canDeleteLedger): ?>
                                                                <form method="POST" class="d-inline" data-confirm="Delete this ledger entry? This action cannot be undone." data-confirm-title="Delete ledger entry?" data-confirm-button="Delete">
                                                                    <input type="hidden" name="ledger_id" value="<?php echo (int)$entry['id']; ?>">
                                                                    <button type="submit" name="delete_ledger_entry" class="btn btn-sm btn-outline-danger">
                                                                        <i class="bi bi-trash"></i>
                                                                    </button>
                                                                </form>
                                                                <?php endif; ?>
                                                            </td>
                                                            <?php endif; ?>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-warning">
                            Debt management tables are not available yet. Run migrations to enable customer credit tracking.
                        </div>
                        <?php endif; ?>
                        
                        <!-- Sales Table -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Date</th>
                                        <th>Reference</th>
                                        <th>Farm Type</th>
                                        <th>Production Type</th>
                                        <th>Cycle</th>
                                        <th>Allocation</th>
                                        <th>Animal Revenue Attribution</th>
                                        <th>Population Effect</th>
                                        <th>Product</th>
                                        <th>Quantity</th>
                                        <th>Unit</th>
                                        <th>Unit Price</th>
                                        <th>Total Amount</th>
                                        <th>Customer</th>
                                        <th>Remarks</th>
                                        <th>Recorded By</th>
                                        <?php if ($showActions): ?>
                                        <th>Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($salesRecords)): ?>
                                    <tr>
                                        <td colspan="<?php echo $showActions ? '17' : '16'; ?>" class="text-center text-muted py-4">
                                            <i class="bi bi-cart display-4 d-block mb-2"></i>
                                            No sales recorded for this period
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($salesRecords as $sale): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo date('d/m/Y', strtotime($sale['sale_date'])); ?></strong>
                                            </td>
                                            <td class="text-nowrap">
                                                <code><?php echo htmlspecialchars((string)($sale['public_reference'] ?? '—')); ?></code>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $sale['farm_type'] === 'poultry' ? 'info' : ($sale['farm_type'] === 'ruminant' ? 'warning' : 'secondary'); ?>">
                                                    <?php echo ucfirst($sale['farm_type']); ?>
                                                </span>
                                            </td>
                                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars(attribution_label($sale['production_type'] ?? null)); ?></span></td>
                                            <td><?php
                                                    if (!empty($sale['cycle_code'])) echo htmlspecialchars($sale['cycle_code']);
                                                    elseif (($sale['farm_type'] ?? '') === 'general') echo '<span class="text-muted">Not applicable</span>';
                                                    elseif (($sale['production_type'] ?? '') === 'shared') echo '<span class="text-muted">Shared operation</span>';
                                                    else echo '<span class="text-muted">Shared between cycles</span>';
                                                ?></td>
                                            <td>
                                                <?php if (!empty($sale['cycle_id'])): ?>
                                                    <span class="badge bg-success">Direct cycle</span>
                                                <?php else:
                                                    $saleTotalForAllocation = (float)$sale['total_amount'];
                                                    $allocatedForSale = (float)($sale['allocated_amount'] ?? 0);
                                                    $allocationPct = $saleTotalForAllocation > 0 ? min(100, ($allocatedForSale / $saleTotalForAllocation) * 100) : 0;
                                                ?>
                                                    <?php if ($allocationPct >= 99.999): ?>
                                                        <span class="badge bg-success">Allocated <?php echo number_format($allocationPct,0); ?>%</span>
                                                    <?php elseif ($allocationPct > 0): ?>
                                                        <span class="badge bg-warning text-dark">Partial <?php echo number_format($allocationPct,1); ?>%</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Unallocated</span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $animalRevenueRows = $ruminantSaleAnimalAllocations[(int)$sale['id']] ?? [];
                                                if (($sale['farm_type'] ?? '') !== 'ruminant') {
                                                    echo '<span class="text-muted">Not applicable</span>';
                                                } elseif (!$animalRevenueRows) {
                                                    echo '<span class="text-muted">Shared revenue — no individual animal allocation</span>';
                                                } else {
                                                    foreach ($animalRevenueRows as $revenueRow) {
                                                        $exitEvent = $ruminantSaleExitEvents[(int)$sale['id']][(int)$revenueRow['animal_id']] ?? null;
                                                        $outcome = $exitEvent ? ruminant_exit_outcome_label((string)$exitEvent['exit_outcome']) : 'Revenue only';
                                                        $outcomeClass = $exitEvent ? (((string)$exitEvent['exit_outcome'] === 'sold_live') ? 'bg-success' : 'bg-warning text-dark') : 'bg-light text-dark border';
                                                        echo '<div class="small mb-1"><span class="badge bg-secondary">' . htmlspecialchars((string)$revenueRow['tag_no']) . '</span> ₦' . number_format((float)$revenueRow['allocated_amount'], 2) . ' <span class="badge ' . $outcomeClass . '">' . htmlspecialchars($outcome) . '</span></div>';
                                                    }
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                $populationRows = $salePopulationEffectMap[(int)$sale['id']] ?? [];
                                                ?>
                                                <?php if (!$populationRows): ?>
                                                    <span class="badge bg-secondary">Financial only</span>
                                                <?php else: ?>
                                                    <?php foreach ($populationRows as $populationRow): ?>
                                                        <div class="small mb-1">
                                                            <span class="badge bg-danger">
                                                                -<?php echo number_format((int)$populationRow['population_quantity']); ?> head
                                                            </span>
                                                            <span class="text-muted">
                                                                <?php echo htmlspecialchars((string)$populationRow['cycle_code']); ?>
                                                            </span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                    <?php if (($sale['farm_type'] ?? '') === 'ruminant'): ?>
                                                        <div class="small text-muted">Aggregate/group only</div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?php echo app_html($sale['product_type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo number_format((float)$sale['quantity'], 2); ?></td>
                                            <td><?php echo htmlspecialchars(sales_unit_label($sale['unit_of_measure'] ?? null)); ?></td>
                                            <td>₦<?php echo number_format($sale['unit_price'], 2); ?></td>
                                            <td class="text-success fw-bold">
                                                ₦<?php echo number_format($sale['total_amount'], 2); ?>
                                            </td>
                                            <td>
                                                <?php echo app_html($sale['customer_name'] ?: '--'); ?>
                                            </td>
                                            <td>
                                                <?php if ($sale['remarks']): ?>
                                                <small class="text-muted"><?php echo app_html(substr($sale['remarks'], 0, 20)); ?>...</small>
                                                <?php else: ?>
                                                <span class="text-muted">--</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small><?php echo app_html(transaction_recorded_by_label(transaction_actor_farm_name($pdo, $tenantFarmId), $sale['seller'] ?? null, $sale['seller_user_type'] ?? null)); ?></small>
                                            </td>
                                            <?php if ($showActions): ?>
                                            <td>
                                                <?php $receivableState = receivable_sale_state($pdo,$tenantFarmId,(int)$sale['id']); ?>
                                                <button class="btn btn-sm btn-outline-primary edit-sale-btn"
                                                        data-id="<?php echo $sale['id']; ?>"
                                                        data-date="<?php echo htmlspecialchars($sale['sale_date'], ENT_QUOTES); ?>"
                                                        data-farm="<?php echo htmlspecialchars($sale['farm_type'], ENT_QUOTES); ?>"
                                                        data-production="<?php echo htmlspecialchars($sale['production_type'] ?? '', ENT_QUOTES); ?>"
                                                        data-cycle="<?php echo (int)($sale['cycle_id'] ?? 0); ?>"
                                                        data-product="<?php echo htmlspecialchars($sale['product_type'], ENT_QUOTES); ?>"
                                                        data-quantity="<?php echo htmlspecialchars($sale['quantity'], ENT_QUOTES); ?>"
                                                        data-unit="<?php echo htmlspecialchars((string)($sale['unit_of_measure'] ?? ''), ENT_QUOTES); ?>"
                                                        data-price="<?php echo htmlspecialchars($sale['unit_price'], ENT_QUOTES); ?>"
                                                        data-upfront="<?php echo htmlspecialchars((string)$receivableState['upfront'], ENT_QUOTES); ?>"
                                                        data-settlements="<?php echo htmlspecialchars((string)$receivableState['payments'], ENT_QUOTES); ?>"
                                                        data-outstanding="<?php echo htmlspecialchars((string)$receivableState['outstanding'], ENT_QUOTES); ?>"
                                                        data-cash-snapshot="<?php echo $receivableState['hasCashSnapshot'] ? '1' : '0'; ?>"
                                                        data-customer="<?php echo htmlspecialchars($sale['customer_name'] ?? '', ENT_QUOTES); ?>"
                                                        data-remarks="<?php echo htmlspecialchars($sale['remarks'] ?? '', ENT_QUOTES); ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <?php
                                                    $slaughterSaleHistoryRows =
                                                        $slaughterSaleHistoryMap[
                                                            (int)$sale['id']
                                                        ] ?? [];
                                                ?>
                                                <?php if ($slaughterSaleHistoryRows): ?>
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-secondary"
                                                    disabled
                                                    title="This sale has slaughter-output Inventory history and cannot be hard-deleted."
                                                >
                                                    <i class="bi bi-lock"></i>
                                                </button>
                                                <?php else: ?>
                                                <button class="btn btn-sm btn-outline-danger"
                                                        data-sale-delete-id="<?php echo (int)$sale['id']; ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </td>
                                            <?php endif; ?>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Sale Modal -->
    <div class="modal fade" id="addSaleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Record New Sale</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Sales Stock Source</label>
                            <select
                                name="sale_stock_source"
                                id="addSaleStockSource"
                                class="form-select"
                            >
                                <option value="financial_only" selected>
                                    Financial only — no Inventory movement
                                </option>
                                <option
                                    value="slaughter_output"
                                    <?php echo empty($slaughterSaleLots) ? 'disabled' : ''; ?>
                                >
                                    Slaughter Output Inventory — explicit lot
                                </option>
                            </select>
                            <small class="text-muted">
                                Use Slaughter Output Inventory only when this sale physically consumes a recorded slaughter output.
                            </small>
                        </div>

                        <div
                            class="card mb-3 d-none"
                            id="addSlaughterLotPanel"
                        >
                            <div class="card-body py-3">
                                <div class="fw-semibold mb-1">
                                    Slaughter Output Lots
                                </div>
                                <div class="small text-muted mb-3">
                                    Select the exact output lot and quantity sold. Product, source cycle, unit, total quantity and source-animal revenue attribution are derived from Inventory provenance. Selling price does not change frozen COGS.
                                </div>

                                <div id="addSlaughterLotRows"></div>

                                <button
                                    type="button"
                                    id="addSlaughterLotAddRow"
                                    class="btn btn-sm btn-outline-secondary"
                                >
                                    Add matching lot
                                </button>

                                <div
                                    id="addSlaughterLotSummary"
                                    class="small mt-2"
                                ></div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Sale Date</label>
                                <input type="date" name="sale_date" id="addSaleDate" class="form-control"
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Farm Type</label>
                                <select name="farm_type" id="addFarmType" class="form-select" required>
                                    <?php if ($canChooseFarmType): ?>
                                    <?php foreach ($saleFarmTypes as $type): ?><option value="<?php echo $type; ?>"><?php echo $saleFarmTypeLabel($type); ?></option><?php endforeach; ?>
                                    <?php else: ?>
                                    <option value="<?php echo $farmType; ?>" selected><?php echo ucfirst($farmType); ?></option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Production Type</label>
                                <select name="production_type" id="addProductionType" class="form-select" required></select>
                                <small class="text-muted">Who produced this revenue: Layer, Broiler, species, or General.</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Production Cycle (optional)</label>
                                <select name="cycle_id" id="addCycleId" class="form-select"><option value="0">Shared between cycles</option></select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Product Type</label>
                                <input type="text" name="product_type" id="addProductType" class="form-control"
                                       placeholder="e.g., Eggs, Broilers, Milk, Meat" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Unit of Measure</label>
                                <select name="unit_preset" id="addUnitPreset" class="form-select" required>
                                    <option value="">Select unit...</option>
                                    <?php foreach (sales_unit_presets() as $value => $label): ?><option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option><?php endforeach; ?>
                                    <option value="__custom__">Other / Custom...</option>
                                </select>
                                <input type="text" name="unit_custom" id="addUnitCustom" class="form-control mt-2 d-none" maxlength="30" placeholder="Enter custom unit">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Quantity</label>
                                <input type="number" name="quantity" id="addQuantity" class="form-control" step="0.01" min="0.01" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Unit Price (₦) <span class="text-muted small">per selected unit</span></label>
                                <input type="number" name="unit_price" id="addUnitPrice" class="form-control" step="0.01" min="0.01" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Total Amount</label>
                                <input type="text" class="form-control" id="totalAmount" 
                                       value="₦0.00" readonly>
                            </div>
                        </div>

                        <?php $renderSalePopulationEffectControls('add'); ?>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Payment Received (₦)</label>
                                <input type="number" name="payment_received" id="addPaymentReceived" class="form-control"
                                       step="0.01" min="0" value="0">
                                <small class="text-muted">0 = full credit sale</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Outstanding Added (₦)</label>
                                <input type="text" class="form-control" id="addOutstandingAmount"
                                       value="₦0.00" readonly>
                            </div>
                        </div>
                        
                        <div class="mb-3 ruminant-sale-animal-panel d-none" id="addRuminantSaleAnimalPanel">
                            <label>Animal Revenue Attribution</label>
                            <select name="sale_animal_allocation_mode" id="addSaleAnimalAllocationMode" class="form-select">
                                <option value="shared">Shared revenue — no individual animal allocation</option>
                                <option value="equal">Selected animals — Equal split</option>
                                <option value="custom">Selected animals — Custom split</option>
                            </select>
                            <small class="text-muted">Use individual attribution only when this revenue genuinely belongs to specific animals. For each selected animal, explicitly choose whether this is revenue only, a live sale, or a cull/slaughter exit.</small>
                            <div id="addSaleAnimalChoices" class="border rounded p-2 mt-2 d-none"></div>
                        </div>

                        <div class="mb-3">
                            <label>Customer Name</label>
                            <input type="text" name="customer_name" class="form-control" 
                                   placeholder="Required for credit tracking">
                        </div>
                        
                        <div class="mb-3">
                            <label>Remarks</label>
                            <textarea name="remarks" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_sale" class="btn btn-primary">Record Sale</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Record Debt Payment Modal -->
    <div class="modal fade" id="addPaymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Record Customer Payment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Customer Name</label>
                            <input type="text" name="payment_customer_name" class="form-control" required
                                   value="<?php echo htmlspecialchars($selectedCustomer); ?>">
                        </div>
                        <div class="mb-3">
                            <label>Apply to Specific Credit Record (Optional)</label>
                            <select name="settle_sale_id" class="form-select">
                                <option value="">General customer payment</option>
                                <?php foreach ($openCreditSales as $creditSale): ?>
                                <option value="<?php echo (int)$creditSale['id']; ?>">
                                    <?php echo htmlspecialchars((string)($creditSale['public_reference'] ?? '—')); ?> | <?php echo htmlspecialchars($creditSale['product_type']); ?> - <?php echo number_format((float)$creditSale['quantity'], 2); ?> <?php echo htmlspecialchars(sales_unit_label($creditSale['unit_of_measure'] ?? null)); ?> | Open: ₦<?php echo number_format((float)$creditSale['open_balance'], 2); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">If you leave this as General, payment auto-allocates FIFO to oldest open credit records and closes them when fully paid.</small>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Payment Date</label>
                                <input type="date" name="payment_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Amount Paid (₦)</label>
                                <input type="number" name="payment_amount" class="form-control" step="0.01" min="0.01"
                                       <?php if ($selectedCustomer !== '' && $selectedCustomerBalance > 0): ?>max="<?php echo htmlspecialchars(number_format($selectedCustomerBalance, 2, '.', '')); ?>"<?php endif; ?>
                                       <?php echo ($selectedCustomer !== '' && $selectedCustomerBalance <= 0) ? 'disabled' : ''; ?> required>
                                <?php if ($selectedCustomer !== ''): ?>
                                    <small class="text-muted d-block mt-1">Outstanding available to settle: ₦<?php echo number_format(max(0, $selectedCustomerBalance), 2); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label>Note</label>
                            <textarea name="payment_note" class="form-control" rows="2" placeholder="Optional note"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="record_payment" class="btn btn-success" <?php echo ($selectedCustomer !== '' && $selectedCustomerBalance <= 0) ? 'disabled' : ''; ?>>Save Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Debt Ledger Entry Modal -->
    <div class="modal fade" id="editLedgerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="ledger_id" id="editLedgerId">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Debt Ledger Entry</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Customer Name</label>
                            <input type="text" name="ledger_customer_name" id="editLedgerCustomer" class="form-control" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Entry Date</label>
                                <input type="date" name="ledger_entry_date" id="editLedgerDate" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Signed Amount (₦)</label>
                                <input type="number" name="ledger_amount" id="editLedgerAmount" class="form-control" step="0.01" required>
                                <small class="text-muted">Use positive for Sale/Credit, negative for Payment.</small>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label>Description</label>
                            <textarea name="ledger_notes" id="editLedgerNotes" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_ledger_entry" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Sale Modal -->
    <div class="modal fade" id="editSaleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="sale_id" id="editSaleId">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Sale</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Sales Stock Source</label>
                            <select
                                name="sale_stock_source"
                                id="editSaleStockSource"
                                class="form-select"
                            >
                                <option value="financial_only">
                                    Financial only — no active slaughter-output consumption
                                </option>
                                <option value="slaughter_output">
                                    Slaughter Output Inventory — explicit lot
                                </option>
                            </select>
                            <small class="text-muted">
                                Corrections keep prior slaughter-output Inventory history auditable.
                            </small>
                        </div>

                        <div
                            class="card mb-3 d-none"
                            id="editSlaughterLotPanel"
                        >
                            <div class="card-body py-3">
                                <div class="fw-semibold mb-1">
                                    Slaughter Output Lots
                                </div>
                                <div class="small text-muted mb-3">
                                    Changing lot or quantity restores the previous active lot usage append-only before applying the corrected selection. Animal revenue attribution follows the selected source lot automatically.
                                </div>

                                <div id="editSlaughterLotRows"></div>

                                <button
                                    type="button"
                                    id="editSlaughterLotAddRow"
                                    class="btn btn-sm btn-outline-secondary"
                                >
                                    Add matching lot
                                </button>

                                <div
                                    id="editSlaughterLotSummary"
                                    class="small mt-2"
                                ></div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Sale Date</label>
                                <input type="date" name="sale_date" id="editSaleDate" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Farm Type</label>
                                <select name="farm_type" id="editSaleFarmType" class="form-select" required>
                                    <?php foreach ($saleFarmTypes as $type): ?><option value="<?php echo $type; ?>"><?php echo $saleFarmTypeLabel($type); ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Production Type</label>
                                <select name="production_type" id="editSaleProductionType" class="form-select" required></select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Production Cycle (optional)</label>
                                <select name="cycle_id" id="editSaleCycleId" class="form-select"><option value="0">Shared between cycles</option></select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Product Type</label>
                                <input type="text" name="product_type" id="editSaleProduct" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Unit of Measure</label>
                                <select name="unit_preset" id="editSaleUnitPreset" class="form-select" required>
                                    <option value="">Select unit...</option>
                                    <?php foreach (sales_unit_presets() as $value => $label): ?><option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option><?php endforeach; ?>
                                    <option value="__custom__">Other / Custom...</option>
                                </select>
                                <input type="text" name="unit_custom" id="editSaleUnitCustom" class="form-control mt-2 d-none" maxlength="30" placeholder="Enter custom unit">
                                <small class="text-muted" id="editSaleUnitLegacyHint"></small>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Quantity</label>
                                <input type="number" name="quantity" id="editSaleQuantity" class="form-control" step="0.01" min="0.01" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Unit Price (₦) <span class="text-muted small">per selected unit</span></label>
                                <input type="number" name="unit_price" id="editSalePrice" class="form-control" step="0.01" min="0.01" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Total Amount</label>
                                <input type="text" class="form-control" id="editTotalAmount" value="₦0.00" readonly>
                            </div>
                        </div>

                        <?php $renderSalePopulationEffectControls('edit'); ?>

                        <div class="card mb-3" id="editPaymentStatusCard">
                            <div class="card-body py-2">
                                <div class="fw-semibold mb-2">Payment / Credit Status</div>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label>Upfront Cash Received (₦)</label>
                                        <input type="number" name="edit_payment_received" id="editSaleUpfront" class="form-control" step="0.01" min="0" required>
                                        <small class="text-muted" id="editSaleLegacyHint"></small>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Later Debt Payments</label>
                                        <input type="text" id="editSaleSettlements" class="form-control" readonly>
                                    </div>
                                    <div class="col-12">
                                        <div class="small" id="editSalePosition"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3 ruminant-sale-animal-panel d-none" id="editRuminantSaleAnimalPanel">
                            <label>Animal Revenue Attribution</label>
                            <select name="sale_animal_allocation_mode" id="editSaleAnimalAllocationMode" class="form-select">
                                <option value="shared">Shared revenue — no individual animal allocation</option>
                                <option value="equal">Selected animals — Equal split</option>
                                <option value="custom">Selected animals — Custom split</option>
                            </select>
                            <small class="text-muted">Revenue allocation and lifecycle are separate. Only an explicit Sold live or Culled/slaughtered outcome changes Animal Registry status.</small>
                            <div id="editSaleAnimalChoices" class="border rounded p-2 mt-2 d-none"></div>
                        </div>

                        <div class="mb-3">
                            <label>Customer Name</label>
                            <input type="text" name="customer_name" id="editSaleCustomer" class="form-control"
                                   placeholder="Optional">
                        </div>

                        <div class="mb-3">
                            <label>Remarks</label>
                            <textarea name="remarks" id="editSaleRemarks" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_sale" class="btn btn-primary">Update Sale</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/jquery/jquery.min.js'); ?>"></script>
    <script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js'); ?>"></script>
    <script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/datatables/js/jquery.dataTables.min.js'); ?>"></script>
    <script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/datatables/js/dataTables.bootstrap5.min.js'); ?>"></script>
    <script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/datatables-responsive/js/dataTables.responsive.min.js'); ?>"></script>
    <script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/main.js'); ?>"></script>
 <script src="<?php echo BASE_URL; ?>/assets/js/edit-modal.js"></script>
    <div
        id="managementSalesRecordsConfig"
        hidden
        data-sale-unit-presets="<?php echo htmlspecialchars(
            app_json_script(array_keys(sales_unit_presets())),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ); ?>"
        data-sales-cycles="<?php echo htmlspecialchars(
            app_json_script($allSalesCycles),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ); ?>"
        data-ruminant-sale-animals="<?php echo htmlspecialchars(
            app_json_script($ruminantSaleAnimals),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ); ?>"
        data-ruminant-sale-allocation-map="<?php echo htmlspecialchars(
            app_json_script($ruminantSaleAnimalAllocations),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ); ?>"
        data-ruminant-sale-exit-map="<?php echo htmlspecialchars(
            app_json_script($ruminantSaleExitEvents),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ); ?>"
        data-sale-population-effect-map="<?php echo htmlspecialchars(
            app_json_script($salePopulationEffectMap),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ); ?>"
        data-slaughter-sale-lots="<?php echo htmlspecialchars(
            app_json_script($slaughterSaleLots),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ); ?>"
        data-slaughter-sale-history-map="<?php echo htmlspecialchars(
            app_json_script($slaughterSaleHistoryMap),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ); ?>"
        data-csrf-token="<?php echo htmlspecialchars(
            csrf_token(),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ); ?>"
        data-delete-sale-url="<?php echo htmlspecialchars(
            BASE_URL . '/api/delete_sale.php',
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ); ?>"
    ></div>
    <script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/management-sales-records.js'); ?>"></script>
</body>
</html>
<?php
if ($pdfRequested) {
    pdf_report_finish('sales-report-' . preg_replace('/[^0-9A-Za-z-]+/', '-', strtolower($periodLabel)) . '.pdf', 'landscape', 'Sales Records - ' . $periodLabel);
}
?>
