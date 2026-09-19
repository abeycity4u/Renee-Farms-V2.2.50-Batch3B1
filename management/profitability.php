<?php
require_once(dirname(__DIR__) . '/init.php');
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../includes/financial.php');
require_once(__DIR__ . '/../lib/stock_reporting.php');
require_once(__DIR__ . '/../lib/stock_costing.php');
require_once(__DIR__ . '/../lib/attribution.php');
require_once(__DIR__ . '/../lib/profitability_unallocated_shared.php');
requireLogin();
requireBusinessReportAccess();

$farmId = requireCurrentFarmId();
$access = getUserFarmType();
$canChoose = isPlatformOwner() || hasRole('farm_admin','sales_rep');
$farmType = $canChoose ? ($_GET['farm_type'] ?? 'all') : ($access === 'all' ? 'all' : $access);
if (!in_array($farmType, ['all','poultry','ruminant','general'], true)) $farmType = 'all';

$productionType = strtolower(trim((string)($_GET['production_type'] ?? 'all')));
$productionOptions = $farmType === 'all' ? [] : attribution_production_types($farmType);
if ($productionType !== 'all' && !isset($productionOptions[$productionType])) $productionType = 'all';

$period = ($_GET['period'] ?? 'monthly') === 'daily' ? 'daily' : 'monthly';
$selectedDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '') ? $_GET['date'] : date('Y-m-d');
$month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
if ($period === 'daily') {
    $start = $selectedDate;
    $end = $selectedDate;
    $periodLabel = date('j M Y', strtotime($selectedDate));
} else {
    $start = $month . '-01';
    $end = date('Y-m-t', strtotime($start));
    $periodLabel = date('F Y', strtotime($start));
}

$cycleId = (int)($_GET['cycle_id'] ?? 0);
$cyclesStmt = $pdo->prepare("SELECT id,cycle_code,farm_type,production_type,status FROM production_cycles WHERE farm_id=? ORDER BY start_date DESC,id DESC");
$cyclesStmt->execute([$farmId]);
$cycles = $cyclesStmt->fetchAll(PDO::FETCH_ASSOC);
$visibleCycles = array_values(array_filter($cycles, static function(array $cycle) use ($farmType, $productionType): bool {
    if ($farmType !== 'all' && strtolower((string)$cycle['farm_type']) !== $farmType) return false;
    if ($productionType !== 'all' && strtolower((string)$cycle['production_type']) !== $productionType) return false;
    return true;
}));
$validCycleIds = array_map('intval', array_column($visibleCycles,'id'));
if ($cycleId && !in_array($cycleId, $validCycleIds, true)) $cycleId = 0;

$summary = getProfitabilitySummary($pdo, $farmId, $start, $end, $farmType, $cycleId ?: null, $productionType === 'all' ? null : $productionType);

$unallocatedShared =
    profitability_unallocated_shared_summary(
        $pdo,
        $farmId,
        $start,
        $end
    );

$canManageStockAllocation =
    stock_consumption_allocation_workspace_can_manage();

$profitabilityAttribution =
    $summary['attribution_composition']
    ?? [];

$revenueAttribution =
    $profitabilityAttribution['revenue']
    ?? [];

$feedAttribution =
    $profitabilityAttribution['feed_consumption']
    ?? [];

$operatingStockAttribution =
    $profitabilityAttribution['operating_inventory_consumption']
    ?? [];

$manualExpenseAttribution =
    $profitabilityAttribution['manual_non_feed_expenses']
    ?? [];

$stockSourceCounts =
    $profitabilityAttribution['stock_source_counts']
    ?? [];

$allocatedSharedRevenue =
    (float)(
        $summary['allocated_shared_revenue']
        ?? 0
    );

$unallocatedPooledRevenue =
    (float)(
        $summary['unallocated_pooled_revenue']
        ?? 0
    );

$poultryEconomics = ['available' => false];
if ($farmType === 'poultry' && in_array($productionType, ['layer', 'broiler'], true)) {
    $poultryEconomics = getPoultryUnitEconomics(
        $pdo,
        $farmId,
        $start,
        $end,
        $productionType,
        $summary,
        $cycleId ?: null
    );
}

// Mortality value is kept separate from period profitability. Layer/Broiler
// birds are productive biological assets; mortality records the number/value
// of productive birds lost without creating or implying another cash/operating
// expense in the selected period.
$summary['mortality_value'] = !empty($poultryEconomics['available'])
    ? (float)$poultryEconomics['mortality_cost']
    : 0.0;

// Shared-revenue inclusion and unallocated pooled revenue are provided
// by the central profitability read model above.

// Use the same effective-transaction predicate as feed movement summaries and feed-cost reporting.
// Compatibility contract: transaction_type='used' AND is_reversed = 0 AND reversal_of_id IS NULL
$effectiveStockSql = stock_effective_sql_predicate();
$feedItemSql = stock_feed_item_sql_predicate('s', 'c');
$uncostedSql = "SELECT COUNT(*)
                FROM stock_transactions t
                JOIN stock_items s ON s.id=t.stock_item_id AND s.farm_id=t.farm_id
                LEFT JOIN inventory_categories c ON c.id=s.category_id AND c.farm_id=s.farm_id
                WHERE t.farm_id=? AND t.transaction_type='used' AND {$effectiveStockSql}
                  AND {$feedItemSql} AND t.transaction_date BETWEEN ? AND ? AND t.total_cost IS NULL";
$uncostedParams = [$farmId, $start, $end];
if ($farmType && $farmType !== 'all') { $uncostedSql .= " AND t.farm_type=?"; $uncostedParams[] = $farmType; }
if ($productionType !== 'all') { $uncostedSql .= " AND t.production_type=?"; $uncostedParams[] = $productionType; }
if ($cycleId) { $uncostedSql .= " AND t.cycle_id=?"; $uncostedParams[] = $cycleId; }
$hasUncosted = $pdo->prepare($uncostedSql);
$hasUncosted->execute($uncostedParams);
$uncosted = (int)$hasUncosted->fetchColumn();

$toggleParams = ['farm_type' => $farmType, 'production_type' => $productionType, 'cycle_id' => $cycleId];
$dailyUrl = '?' . http_build_query(array_merge($toggleParams, ['period' => 'daily', 'date' => $period === 'daily' ? $selectedDate : date('Y-m-d')]));
$monthlyUrl = '?' . http_build_query(array_merge($toggleParams, ['period' => 'monthly', 'month' => $period === 'monthly' ? $month : substr($selectedDate, 0, 7)]));
?>
<!doctype html>
<html lang="en"><head><?php include(__DIR__.'/../navbar_head.php'); ?><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Profitability - Farm Platform</title></head><body>
<?php include(__DIR__.'/../navbar.php'); ?>
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 app-responsive-toolbar">
        <div><h2 class="mb-1">Profitability</h2><p class="text-muted mb-0">Traceable operating profitability using effective feed consumption and recorded business activity.</p></div>
        <a class="btn btn-outline-secondary" href="<?php echo BASE_URL; ?>/management/reports.php">Back to Analytics</a>
    </div>

    <div class="btn-group mb-4" role="group" aria-label="Profitability period">
        <a href="<?php echo htmlspecialchars($dailyUrl); ?>" class="btn <?php echo $period === 'daily' ? 'btn-primary' : 'btn-outline-primary'; ?>">Daily</a>
        <a href="<?php echo htmlspecialchars($monthlyUrl); ?>" class="btn <?php echo $period === 'monthly' ? 'btn-primary' : 'btn-outline-primary'; ?>">Monthly</a>
    </div>

    <form class="card card-body mb-4" method="get">
        <input type="hidden" name="period" value="<?php echo htmlspecialchars($period); ?>">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <?php if ($period === 'daily'): ?>
                    <label class="form-label">Date</label><input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($selectedDate); ?>">
                <?php else: ?>
                    <label class="form-label">Month</label><input type="month" name="month" class="form-control" value="<?php echo htmlspecialchars($month); ?>">
                <?php endif; ?>
            </div>
            <div class="col-md-2"><label class="form-label">Farm type</label><select name="farm_type" id="profitFarmType" class="form-select" <?php echo $canChoose?'':'disabled'; ?>><option value="all" <?php echo $farmType==='all'?'selected':''; ?>>All</option><option value="poultry" <?php echo $farmType==='poultry'?'selected':''; ?>>Poultry</option><option value="ruminant" <?php echo $farmType==='ruminant'?'selected':''; ?>>Ruminant</option><option value="general" <?php echo $farmType==='general'?'selected':''; ?>>General</option></select><?php if(!$canChoose): ?><input type="hidden" name="farm_type" value="<?php echo htmlspecialchars($farmType); ?>"><?php endif; ?></div>
            <div class="col-md-3"><label class="form-label">Production type</label><select name="production_type" id="profitProductionType" class="form-select"><option value="all">All production types</option><?php foreach($productionOptions as $value=>$label): ?><option value="<?php echo htmlspecialchars($value); ?>" <?php echo $productionType===$value?'selected':''; ?>><?php echo htmlspecialchars($label); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Production cycle (optional)</label><select name="cycle_id" id="profitCycleId" class="form-select"><option value="0"><?php echo $productionType !== 'all' ? 'All ' . htmlspecialchars(attribution_label($productionType)) . ' cycles' : 'All cycles'; ?></option><?php foreach($visibleCycles as $c): ?><option value="<?php echo (int)$c['id']; ?>" <?php echo $cycleId===(int)$c['id']?'selected':''; ?>><?php echo htmlspecialchars($c['cycle_code'].' — '.$c['production_type'].' ('.$c['status'].')'); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-1"><button class="btn btn-primary w-100">Apply</button></div>
        </div>
        <div class="col-12 profitability-filter-help">
            <i class="bi bi-info-circle me-1"></i>
            Production type narrows Layer, Broiler, a ruminant species, or the recorded Shared Operation.
            Shared Operation is a source attribution; it is not the same as unallocated remainder.
            Unallocated shared balances remain at their parent scope until an explicit compatible allocation is recorded.
            Choose a cycle only when you need cycle-level analysis.
        </div>
    </form>

    <?php if ($cycleId && $allocatedSharedRevenue > 0.009): ?>
        <div class="alert alert-info d-flex gap-2 align-items-start">
            <i class="bi bi-diagram-3"></i>
            <div><strong>Shared revenue included.</strong> ₦<?php echo number_format($allocatedSharedRevenue,2); ?> from combined sales has been assigned to this cycle from recorded production ownership.</div>
        </div>
    <?php endif; ?>
    <?php if ($cycleId && $unallocatedPooledRevenue > 0.009): ?>
        <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
            <div><strong>Some shared revenue could not be assigned yet.</strong> ₦<?php echo number_format($unallocatedPooledRevenue,2); ?> remains at the production-type level. For Layer egg sales, check that Daily Records support enough unsold egg stock for the quantity sold.</div>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-2"><h5 class="mb-0"><?php echo $period === 'daily' ? 'Daily Analysis' : 'Monthly Analysis'; ?></h5><span class="text-muted"><?php echo htmlspecialchars($periodLabel); ?></span></div>
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="text-muted">Revenue</div><h3 class="mt-2">₦<?php echo number_format($summary['revenue'],2); ?></h3></div></div></div>
        <div class="col-12 col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="text-muted">Feed consumed</div><h3 class="mt-2">₦<?php echo number_format($summary['feed_consumption_cost'],2); ?></h3></div></div></div>
        <div class="col-12 col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="text-muted">Other operating cost</div><h3 class="mt-2">₦<?php echo number_format($summary['non_feed_expenses'],2); ?></h3></div></div></div>
        <div class="col-12 col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="text-muted">Profit / Loss</div><h3 class="mt-2 <?php echo $summary['profit']>=0?'text-success':'text-danger'; ?>">₦<?php echo number_format($summary['profit'],2); ?></h3></div></div></div>
    </div>


    <div class="card mb-4" id="unallocated-shared-balances">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <strong>Unallocated Shared Balances</strong>
                <div class="small text-muted">
                    Farm-level allocation queue for the selected period.
                    These balances are disclosed separately and do not create another Profit / Loss formula.
                </div>
            </div>

            <?php if ((int)($unallocatedShared['stock_attribution_exception_count'] ?? 0) > 0): ?>
                <span class="badge bg-danger">
                    <?php echo number_format((int)$unallocatedShared['stock_attribution_exception_count']); ?>
                    attribution exception(s)
                </span>
            <?php endif; ?>
        </div>

        <div class="card-body">
            <div class="row g-3 mb-3">

                <div class="col-sm-6 col-xl-3">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">
                            Shared revenue awaiting allocation
                        </div>

                        <div class="fs-5 fw-semibold">
                            ₦<?php echo number_format((float)($unallocatedShared['revenue_unallocated'] ?? 0),2); ?>
                        </div>

                        <div class="small text-muted">
                            Pooled Poultry/Ruminant revenue only.
                            General income is not treated as a shared allocation parent.
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">
                            Operating shared cost awaiting allocation
                        </div>

                        <div class="fs-5 fw-semibold">
                            ₦<?php echo number_format((float)($unallocatedShared['operating_shared_unallocated'] ?? 0),2); ?>
                        </div>

                        <div class="small text-muted">
                            Manual operating expense + consumed Feed + consumed operating inventory.
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">
                            Feed-purchase cash balance
                        </div>

                        <div class="fs-5 fw-semibold">
                            ₦<?php echo number_format((float)($unallocatedShared['cash_feed_purchase_unallocated'] ?? 0),2); ?>
                        </div>

                        <div class="small text-muted">
                            Cash/spending disclosure only.
                            It is not added again to consumed-feed Profitability.
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">
                            Attribution exceptions
                        </div>

                        <div class="fs-5 fw-semibold">
                            <?php echo number_format((int)($unallocatedShared['stock_attribution_exception_count'] ?? 0)); ?>
                        </div>

                        <div class="small text-muted">
                            ₦<?php echo number_format((float)($unallocatedShared['stock_attribution_exception_amount'] ?? 0),2); ?>
                            requires source-attribution correction rather than allocation.
                        </div>
                    </div>
                </div>

            </div>

            <div class="small text-muted mb-3">
                <strong>Important:</strong>
                Shared Operation is a recorded source scope.
                Unallocated remainder is the portion of a broader parent that has not yet been explicitly assigned.
                A source-attribution exception is neither of those and must be corrected at its authoritative source.
            </div>

            <?php if (!empty($unallocatedShared['rows'])): ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Source</th>
                            <th>Scope</th>
                            <th>Description</th>
                            <th class="text-end">Parent</th>
                            <th class="text-end">Allocated</th>
                            <th class="text-end">Unallocated</th>
                            <th>Status</th>
                        </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($unallocatedShared['rows'] as $sharedRow): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars((string)$sharedRow['source_type']); ?>
                                    #<?php echo (int)$sharedRow['source_id']; ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars((string)$sharedRow['scope']); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars((string)$sharedRow['description']); ?>
                                </td>

                                <td class="text-end">
                                    ₦<?php echo number_format((float)$sharedRow['parent_amount'],2); ?>
                                </td>

                                <td class="text-end">
                                    ₦<?php echo number_format((float)$sharedRow['allocated_amount'],2); ?>
                                </td>

                                <td class="text-end">
                                    ₦<?php echo number_format((float)$sharedRow['unallocated_amount'],2); ?>
                                </td>

                                <td>
                                    <?php
                                    $allocationUrl =
                                        $canManageStockAllocation
                                            ? trim(
                                                (string)(
                                                    $sharedRow[
                                                        'allocation_url'
                                                    ]
                                                    ?? ''
                                                )
                                            )
                                            : '';
                                    ?>

                                    <?php if (($sharedRow['status'] ?? '') === 'attribution_exception'): ?>
                                        <span class="badge bg-danger">
                                            Correct source attribution
                                        </span>
                                    <?php elseif (($sharedRow['status'] ?? '') === 'cash_only_waiting_allocation'): ?>
                                        <span class="badge bg-secondary">
                                            Cash-only balance
                                        </span>
                                    <?php elseif ($allocationUrl !== ''): ?>
                                        <a
                                            class="badge bg-warning text-dark text-decoration-none"
                                            href="<?php echo htmlspecialchars(
                                                $allocationUrl,
                                                ENT_QUOTES | ENT_SUBSTITUTE,
                                                'UTF-8'
                                            ); ?>"
                                            title="Open consumed-stock allocation workspace"
                                        >
                                            Awaiting allocation
                                            <i class="bi bi-box-arrow-up-right ms-1"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">
                                            Awaiting allocation
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-success mb-0">
                    No shared allocation remainder or attribution exception exists in this period.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-4" id="profitability-attribution">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <strong>Attribution &amp; Source Breakdown</strong>
                <div class="small text-muted">Explains which recorded source supplied each amount already included in Profitability.</div>
            </div>
            <span class="badge bg-light text-dark">Read-only economic trace</span>
        </div>

        <div class="card-body">
            <div class="alert alert-light border py-2 small">
                <strong>Source / provenance:</strong>
                direct/native amounts come from records already owned by the selected reporting scope.
                Explicit allocations are narrower shares assigned from a broader parent source.
                This breakdown explains the canonical totals above; it does not recalculate Profit / Loss.
            </div>

            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Profitability component</th>
                            <th>Included source composition</th>
                            <th>Source / provenance</th>
                            <th class="text-end">Canonical total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="fw-semibold">Revenue</td>
                            <td>
                                <div>Direct sales: ₦<?php echo number_format((float)($revenueAttribution['direct_sales'] ?? 0),2); ?></div>
                                <div>Explicit pooled-sale allocation: ₦<?php echo number_format((float)($revenueAttribution['explicit_sale_allocation'] ?? 0),2); ?></div>
                            </td>
                            <td class="small text-muted">Sales records / sales allocations</td>
                            <td class="text-end fw-semibold">₦<?php echo number_format((float)($revenueAttribution['total'] ?? $summary['revenue']),2); ?></td>
                        </tr>

                        <tr>
                            <td class="fw-semibold">Feed consumed</td>
                            <td>
                                <div>Direct/native consumed stock: ₦<?php echo number_format((float)($feedAttribution['direct_native_stock'] ?? 0),2); ?></div>
                                <div>Explicit consumed-stock allocation: ₦<?php echo number_format((float)($feedAttribution['explicit_stock_allocation'] ?? 0),2); ?></div>
                            </td>
                            <td class="small text-muted">Consumed stock ledger / stock allocations</td>
                            <td class="text-end fw-semibold">₦<?php echo number_format((float)($feedAttribution['total'] ?? $summary['feed_consumption_cost']),2); ?></td>
                        </tr>

                        <tr>
                            <td class="fw-semibold">Other operating cost</td>
                            <td>
                                <div>Direct farm expenses: ₦<?php echo number_format((float)($manualExpenseAttribution['direct_expense'] ?? 0),2); ?></div>
                                <div>Explicit shared-expense allocation: ₦<?php echo number_format((float)($manualExpenseAttribution['explicit_shared_expense_allocation'] ?? 0),2); ?></div>
                                <div>Direct/native consumed stock: ₦<?php echo number_format((float)($operatingStockAttribution['direct_native_stock'] ?? 0),2); ?></div>
                                <div>Explicit consumed-stock allocation: ₦<?php echo number_format((float)($operatingStockAttribution['explicit_stock_allocation'] ?? 0),2); ?></div>
                            </td>
                            <td class="small text-muted">Farm expenses / financial allocations / consumed stock ledger / stock allocations</td>
                            <td class="text-end fw-semibold">₦<?php echo number_format((float)$summary['non_feed_expenses'],2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="small text-muted mt-3">
                Consumed-stock provenance in this selection:
                <?php echo number_format((int)($stockSourceCounts['native_parent_rows'] ?? 0)); ?>
                native source row(s) and
                <?php echo number_format((int)($stockSourceCounts['explicit_allocation_rows'] ?? 0)); ?>
                explicit allocation row(s).
            </div>

            <?php if($cycleId): ?>
                <div class="alert alert-secondary py-2 small mt-3 mb-0">
                    <strong>Cycle attribution boundary:</strong>
                    Unallocated broader shared cost remains outside the selected cycle until an explicit compatible allocation is recorded.
                    Profitability does not force the remainder into this cycle or guess a distribution.
                    Remaining parent balances stay visible in the allocation workspaces.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if($uncosted>0): ?><div class="alert alert-warning"><strong>Cost data notice:</strong> <?php echo $uncosted; ?> effective feed-use transaction(s) in this period have no cost snapshot. They are excluded from feed-consumption cost so the platform does not invent a cost.</div><?php endif; ?>

    <?php if (!empty($poultryEconomics['available'])): ?>
        <?php
        $moneyOrNA = static function($value): string {
            return $value === null ? 'N/A' : '₦' . number_format((float)$value, 2);
        };
        $marginText = $poultryEconomics['margin_percent'] === null
            ? 'N/A'
            : number_format((float)$poultryEconomics['margin_percent'], 2) . '%';
        ?>
        <div class="card mb-4">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <strong>Poultry Unit Economics — <?php echo ucfirst(htmlspecialchars($productionType)); ?></strong>
                    <div class="small text-muted">Derived from recorded flock activity and the same traceable profitability engine above.</div>
                </div>
                <span class="badge bg-light text-dark"><?php echo number_format((int)$poultryEconomics['recorded_days']); ?> recorded day(s)</span>
            </div>
            <div class="card-body">
                <?php if ((int)$poultryEconomics['recorded_days'] === 0): ?>
                    <div class="alert alert-info mb-0">No <?php echo htmlspecialchars(ucfirst($productionType)); ?> Daily Records exist for this selection, so per-bird and production-unit economics are unavailable.</div>
                <?php else: ?>
                    <div class="row g-3">
                        <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="text-muted small">Average Live Flock</div><div class="fs-4 fw-semibold"><?php echo number_format((float)$poultryEconomics['average_live_flock'], 1); ?></div><div class="small text-muted">Average daily midpoint flock</div></div></div>
                        <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="text-muted small">Feed Cost / Bird</div><div class="fs-4 fw-semibold"><?php echo $moneyOrNA($poultryEconomics['feed_cost_per_bird']); ?></div><div class="small text-muted">Consumed-feed cost ÷ average live flock</div></div></div>
                        <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="text-muted small">Operating Cost / Bird</div><div class="fs-4 fw-semibold"><?php echo $moneyOrNA($poultryEconomics['operating_cost_per_bird']); ?></div><div class="small text-muted">Total operating cost ÷ average live flock</div></div></div>
                        <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="text-muted small">Profit / Bird</div><div class="fs-4 fw-semibold <?php echo ($poultryEconomics['profit_per_bird'] ?? 0) >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo $moneyOrNA($poultryEconomics['profit_per_bird']); ?></div><div class="small text-muted">Profit / Loss ÷ average live flock</div></div></div>

                        <?php if ($productionType === 'layer'): ?>
                            <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="text-muted small">Eggs Produced</div><div class="fs-4 fw-semibold"><?php echo number_format((int)$poultryEconomics['eggs_produced']); ?></div></div></div>
                            <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="text-muted small">Crates Equivalent</div><div class="fs-4 fw-semibold"><?php echo number_format((float)$poultryEconomics['crates_equivalent'], 2); ?></div><div class="small text-muted">30 eggs = 1 crate</div></div></div>
                            <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="text-muted small">Feed Cost / Egg</div><div class="fs-4 fw-semibold"><?php echo $moneyOrNA($poultryEconomics['feed_cost_per_egg']); ?></div></div></div>
                            <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="text-muted small">Feed Cost / Crate</div><div class="fs-4 fw-semibold"><?php echo $moneyOrNA($poultryEconomics['feed_cost_per_crate']); ?></div></div></div>
                            <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="text-muted small">Total Cost / Egg</div><div class="fs-4 fw-semibold"><?php echo $moneyOrNA($poultryEconomics['operating_cost_per_egg']); ?></div></div></div>
                            <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="text-muted small">Total Cost / Crate</div><div class="fs-4 fw-semibold"><?php echo $moneyOrNA($poultryEconomics['operating_cost_per_crate']); ?></div></div></div>
                        <?php endif; ?>

                        <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="text-muted small">Mortality</div><div class="fs-4 fw-semibold"><?php echo number_format((int)$poultryEconomics['mortality']); ?></div><div class="small text-muted"><?php echo number_format((int)$poultryEconomics['costed_mortality']); ?> costed; <?php echo number_format((int)$poultryEconomics['uncosted_mortality']); ?> awaiting cost basis</div></div></div>
                        <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="text-muted small">Mortality Value</div><div class="fs-4 fw-semibold"><?php echo $moneyOrNA((float)$poultryEconomics['mortality_cost']); ?></div><div class="small text-muted">Mortality × explicit cycle bird cost basis; productive-asset value lost, shown for management intelligence and not deducted again from period Profit / Loss</div></div></div>
                        <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="text-muted small">Profit Margin</div><div class="fs-4 fw-semibold"><?php echo $marginText; ?></div><div class="small text-muted">Profit / Loss ÷ revenue</div></div></div>
                    </div>

                    <?php if ((int)$poultryEconomics['uncosted_mortality'] > 0): ?>
                        <div class="alert alert-warning mt-3 mb-0"><strong>Mortality valuation is incomplete.</strong> <?php echo number_format((int)$poultryEconomics['uncosted_mortality']); ?> dead bird(s) in this selection are linked to a cycle without a Bird Cost Basis, or to a legacy record without a cycle. The platform has left their mortality value uncosted instead of guessing. This does not change period Profit / Loss. Set a Bird Cost Basis under Production Cycles where appropriate.</div>
                    <?php endif; ?>
                    <?php if ($productionType === 'layer' && (int)$poultryEconomics['eggs_produced'] === 0): ?>
                        <div class="alert alert-info mt-3 mb-0">No eggs were recorded for this selection. Feed Cost / Egg, Feed Cost / Crate, Total Cost / Egg and Total Cost / Crate therefore remain N/A.</div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-7"><div class="card"><div class="card-header fw-semibold">How this result is calculated</div><div class="card-body"><dl class="row mb-0"><dt class="col-7">Revenue from sales</dt><dd class="col-5 text-end">₦<?php echo number_format($summary['revenue'],2); ?></dd><dt class="col-7">Feed consumed (cost snapshot)</dt><dd class="col-5 text-end">₦<?php echo number_format($summary['feed_consumption_cost'],2); ?></dd><dt class="col-7">Other operating expenses</dt><dd class="col-5 text-end">₦<?php echo number_format($summary['non_feed_expenses'],2); ?></dd><dd class="col-12 small text-muted text-end mb-2">Manual/non-stock: ₦<?php echo number_format($summary['manual_non_feed_expenses'] ?? 0,2); ?> · Consumed operating inventory: ₦<?php echo number_format($summary['inventory_operating_consumption_cost'] ?? 0,2); ?></dd><hr><dt class="col-7">Profit / Loss</dt><dd class="col-5 text-end fw-bold <?php echo $summary['profit']>=0?'text-success':'text-danger'; ?>">₦<?php echo number_format($summary['profit'],2); ?></dd></dl><p class="small text-muted mt-3 mb-0">Feed purchases are tracked as cash expenses (₦<?php echo number_format($summary['cash_feed_expenses'],2); ?>) but are not added again to consumed-feed cost. Feed profitability is consumption-based, preventing purchase-day distortion and double counting. Medication/Vaccine, Supplement and Consumables inventory are likewise recognised inside Other operating cost when USED; their purchase receipts remain spending/cash-flow records rather than a second profitability charge.<?php if (!empty($poultryEconomics['available'])): ?> Mortality value is tracked separately as productive-bird loss intelligence and is not deducted again from period Profit / Loss.<?php endif; ?></p></div></div></div>
        <div class="col-lg-5"><div class="card"><div class="card-header fw-semibold">Reporting integrity</div><div class="card-body"><p class="mb-2">Daily and monthly views use the same calculation engine. Monthly is the sum of activity inside the selected month; Daily limits that engine to one date.</p><p class="mb-0 text-muted small">Reversed originals and restoration/reversal rows remain available in the audit ledger but are excluded from operational feed quantity and profitability calculations. Feed usage is valued from its transaction cost snapshot, so a later inventory price change does not rewrite historical profit. Production type and cycle attribution keep Layer, Broiler and ruminant species costs separated; pooled activity remains explicit instead of being guessed.</p></div></div></div>
    </div>
</div>
<div
    id="managementProfitabilityConfig"
    hidden
    data-cycles="<?php echo htmlspecialchars(
        app_json_script($cycles),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ); ?>"
    data-production-type="<?php echo htmlspecialchars(
        $productionType,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ); ?>"
    data-cycle-id="<?php echo (int)$cycleId; ?>"
></div>
<script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/management-profitability.js'); ?>"></script>
</body></html>
