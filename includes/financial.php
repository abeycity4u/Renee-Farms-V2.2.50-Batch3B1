<?php
require_once __DIR__ . '/../lib/stock_reporting.php';
require_once __DIR__ . '/../lib/stock_costing.php';
require_once __DIR__ . '/../lib/attribution.php';
require_once __DIR__ . '/../lib/inventory_financial.php';
/**
 * Traceable profitability engine.
 *
 * Attribution hierarchy: farm -> farm type -> production type -> cycle.
 * A production-type transaction may intentionally have no cycle (pooled sale,
 * shared production-type activity). Cycle reports only use directly assigned
 * rows plus explicit allocation rows, preventing invented precision.
 */
if (!function_exists('getProfitabilitySummary')) {
function getProfitabilitySummary(
    PDO $pdo,
    int $farmId,
    string $startDate,
    string $endDate,
    ?string $farmType = null,
    ?int $cycleId = null,
    ?string $productionType = null
): array {
    $farmType = $farmType ?: 'all';
    $productionType = strtolower(trim((string)$productionType));
    if ($productionType === 'all') $productionType = '';

    // Revenue: exact source records at farm/production level. At cycle level,
    // include direct cycle sales plus any explicit allocation of pooled sales.
    if ($cycleId) {
        $salesSql = "SELECT COALESCE(SUM(total_amount),0) FROM sales_records WHERE farm_id=? AND sale_date BETWEEN ? AND ? AND cycle_id=?";
        $salesParams = [$farmId,$startDate,$endDate,$cycleId];
        if ($farmType !== 'all') { $salesSql .= " AND farm_type=?"; $salesParams[]=$farmType; }
        if ($productionType !== '') { $salesSql .= " AND production_type=?"; $salesParams[]=$productionType; }
        $stmt=$pdo->prepare($salesSql); $stmt->execute($salesParams); $revenue=(float)$stmt->fetchColumn();

        $allocSql = "SELECT COALESCE(SUM(sa.allocated_amount),0)
                     FROM sales_allocations sa
                     JOIN sales_records s ON s.id=sa.sale_id AND s.farm_id=sa.farm_id
                     JOIN production_cycles pc ON pc.id=sa.cycle_id AND pc.farm_id=sa.farm_id
                     WHERE sa.farm_id=? AND sa.cycle_id=? AND s.sale_date BETWEEN ? AND ?";
        $allocParams=[$farmId,$cycleId,$startDate,$endDate];
        if ($farmType !== 'all') { $allocSql .= " AND pc.farm_type=?"; $allocParams[]=$farmType; }
        if ($productionType !== '') { $allocSql .= " AND pc.production_type=?"; $allocParams[]=$productionType; }
        $stmt=$pdo->prepare($allocSql); $stmt->execute($allocParams); $revenue += (float)$stmt->fetchColumn();
    } else {
        $salesSql = "SELECT COALESCE(SUM(total_amount),0) FROM sales_records WHERE farm_id=? AND sale_date BETWEEN ? AND ?";
        $salesParams=[$farmId,$startDate,$endDate];
        if ($farmType !== 'all') { $salesSql .= " AND farm_type=?"; $salesParams[]=$farmType; }
        if ($productionType !== '') { $salesSql .= " AND production_type=?"; $salesParams[]=$productionType; }
        $stmt=$pdo->prepare($salesSql); $stmt->execute($salesParams); $revenue=(float)$stmt->fetchColumn();
    }

    $expenseRows=[];
    if ($cycleId) {
        $expenseSql="SELECT category,COALESCE(SUM(amount * unit),0) total FROM farm_expenses WHERE farm_id=? AND expense_date BETWEEN ? AND ? AND cycle_id=?";
        $expenseParams=[$farmId,$startDate,$endDate,$cycleId];
        if ($farmType !== 'all') {
            $expenseSql .= $farmType === 'general' ? " AND farm_type='general'" : " AND (farm_type=? OR farm_type='both')";
            if ($farmType !== 'general') $expenseParams[]=$farmType;
        }
        if ($productionType !== '') { $expenseSql.=" AND production_type=?"; $expenseParams[]=$productionType; }
        $expenseSql.=" GROUP BY category";
        $stmt=$pdo->prepare($expenseSql); $stmt->execute($expenseParams);
        foreach($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $cat=>$amount) $expenseRows[$cat]=(float)$amount;

        $allocSql="SELECT e.category,COALESCE(SUM(fa.allocated_amount),0) total
                   FROM financial_allocations fa
                   JOIN farm_expenses e ON e.id=fa.expense_id AND e.farm_id=fa.farm_id
                   JOIN production_cycles pc ON pc.id=fa.cycle_id AND pc.farm_id=fa.farm_id
                   WHERE fa.farm_id=? AND fa.cycle_id=? AND e.expense_date BETWEEN ? AND ?";
        $allocParams=[$farmId,$cycleId,$startDate,$endDate];
        if ($farmType !== 'all') { $allocSql.=" AND pc.farm_type=?"; $allocParams[]=$farmType; }
        if ($productionType !== '') { $allocSql.=" AND pc.production_type=?"; $allocParams[]=$productionType; }
        $allocSql.=" GROUP BY e.category";
        $stmt=$pdo->prepare($allocSql); $stmt->execute($allocParams);
        foreach($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $cat=>$amount) $expenseRows[$cat]=($expenseRows[$cat]??0)+(float)$amount;
    } else {
        $expenseSql="SELECT category,COALESCE(SUM(amount * unit),0) total FROM farm_expenses WHERE farm_id=? AND expense_date BETWEEN ? AND ?";
        $expenseParams=[$farmId,$startDate,$endDate];
        if ($farmType !== 'all') {
            $expenseSql .= $farmType === 'general' ? " AND farm_type='general'" : " AND (farm_type=? OR farm_type='both')";
            if ($farmType !== 'general') $expenseParams[]=$farmType;
        }
        if ($productionType !== '') { $expenseSql.=" AND production_type=?"; $expenseParams[]=$productionType; }
        $expenseSql.=" GROUP BY category";
        $stmt=$pdo->prepare($expenseSql); $stmt->execute($expenseParams); $expenseRows=$stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    // Compatibility contract: transaction_type='used' AND is_reversed = 0 AND reversal_of_id IS NULL
    $effectiveStockSql=stock_effective_sql_predicate();
    $feedItemSql=stock_feed_item_sql_predicate('s','c');
    $feedSql="SELECT COALESCE(SUM(t.total_cost),0)
              FROM stock_transactions t
              JOIN stock_items s ON s.id=t.stock_item_id AND s.farm_id=t.farm_id
              LEFT JOIN inventory_categories c ON c.id=s.category_id AND c.farm_id=s.farm_id
              WHERE t.farm_id=? AND t.transaction_type='used' AND {$effectiveStockSql}
                AND {$feedItemSql} AND t.transaction_date BETWEEN ? AND ? AND t.total_cost IS NOT NULL";
    $feedParams=[$farmId,$startDate,$endDate];
    if ($farmType !== 'all') { $feedSql.=" AND t.farm_type=?"; $feedParams[]=$farmType; }
    if ($productionType !== '') { $feedSql.=" AND t.production_type=?"; $feedParams[]=$productionType; }
    if ($cycleId) { $feedSql.=" AND t.cycle_id=?"; $feedParams[]=$cycleId; }
    $stmt=$pdo->prepare($feedSql); $stmt->execute($feedParams); $feedCost=(float)$stmt->fetchColumn();

    // Feed purchases are cash-flow records, not an additional operating cost
    // when consumed-feed snapshots are present.
    $cashFeedSql="SELECT COALESCE(SUM(amount * unit),0) FROM farm_expenses WHERE farm_id=? AND expense_date BETWEEN ? AND ? AND category='feeds'";
    $cashFeedParams=[$farmId,$startDate,$endDate];
    if ($farmType !== 'all') {
        $cashFeedSql .= $farmType === 'general' ? " AND farm_type='general'" : " AND (farm_type=? OR farm_type='both')";
        if ($farmType !== 'general') $cashFeedParams[]=$farmType;
    }
    if ($productionType !== '') { $cashFeedSql.=" AND production_type=?"; $cashFeedParams[]=$productionType; }
    if ($cycleId) { $cashFeedSql.=" AND cycle_id=?"; $cashFeedParams[]=$cycleId; }
    $stmt=$pdo->prepare($cashFeedSql); $stmt->execute($cashFeedParams); $cashFeed=(float)$stmt->fetchColumn();

    // Non-feed stocked operating items become period cost when USED, not when purchased.
    // The transaction's financial_classification and total_cost are immutable historical
    // snapshots, so later category/price changes do not rewrite prior profitability.
    $operatingClasses = array_keys(inventory_operating_consumption_classifications());
    $inventoryConsumptionBreakdown = [];
    $inventoryOperatingConsumption = 0.0;
    if ($operatingClasses) {
        $placeholders = implode(',', array_fill(0, count($operatingClasses), '?'));
        $inventorySql = "SELECT t.financial_classification, COALESCE(SUM(t.total_cost),0) total
                         FROM stock_transactions t
                         WHERE t.farm_id=? AND t.transaction_type='used' AND {$effectiveStockSql}
                           AND t.transaction_date BETWEEN ? AND ? AND t.total_cost IS NOT NULL
                           AND t.financial_classification IN ({$placeholders})";
        $inventoryParams = array_merge([$farmId,$startDate,$endDate], $operatingClasses);
        if ($farmType !== 'all') { $inventorySql .= " AND t.farm_type=?"; $inventoryParams[]=$farmType; }
        if ($productionType !== '') { $inventorySql .= " AND t.production_type=?"; $inventoryParams[]=$productionType; }
        if ($cycleId) { $inventorySql .= " AND t.cycle_id=?"; $inventoryParams[]=$cycleId; }
        $inventorySql .= " GROUP BY t.financial_classification";
        $stmt=$pdo->prepare($inventorySql); $stmt->execute($inventoryParams);
        foreach($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $classification=>$amount) {
            $inventoryConsumptionBreakdown[$classification]=(float)$amount;
            $inventoryOperatingConsumption+=(float)$amount;
        }
    }

    $manualNonFeedExpenses=0.0;
    foreach($expenseRows as $category=>$amount) if($category!=='feeds') $manualNonFeedExpenses+=(float)$amount;
    $nonFeedExpenses=$manualNonFeedExpenses+$inventoryOperatingConsumption;
    $totalCost=$nonFeedExpenses+$feedCost;
    return [
        'revenue'=>$revenue,
        'feed_consumption_cost'=>$feedCost,
        'non_feed_expenses'=>$nonFeedExpenses,
        'manual_non_feed_expenses'=>$manualNonFeedExpenses,
        'inventory_operating_consumption_cost'=>$inventoryOperatingConsumption,
        'inventory_operating_consumption_breakdown'=>$inventoryConsumptionBreakdown,
        'total_operating_cost'=>$totalCost,
        'profit'=>$revenue-$totalCost,
        'cash_feed_expenses'=>$cashFeed,
        'expense_breakdown'=>$expenseRows,
    ];
}}

/**
 * Poultry unit economics derived from daily flock records and the existing
 * profitability summary. This helper intentionally refuses to invent missing
 * bird-value data: mortality is costed only where the linked production cycle
 * has an explicit bird_unit_cost.
 *
 * Average live flock is the average of each recorded day's midpoint flock:
 * (opening flock + closing flock) / 2. When multiple cycles have records on
 * the same date, their flocks are summed before the daily midpoint is averaged.
 */
if (!function_exists('getPoultryUnitEconomics')) {
function getPoultryUnitEconomics(
    PDO $pdo,
    int $farmId,
    string $startDate,
    string $endDate,
    string $productionType,
    array $profitabilitySummary,
    ?int $cycleId = null
): array {
    $productionType = strtolower(trim($productionType));
    if (!in_array($productionType, ['layer', 'broiler'], true)) {
        return ['available' => false];
    }

    $table = $productionType === 'layer' ? 'layer_daily_records' : 'broiler_daily_records';
    $params = [$farmId, $startDate, $endDate];
    $cycleSql = '';
    if ($cycleId) {
        $cycleSql = ' AND d.cycle_id = ?';
        $params[] = $cycleId;
    }

    $dailySql = "SELECT d.record_date,
                        SUM(d.opening_stock) AS opening_total,
                        SUM(GREATEST(d.opening_stock - d.mortality, 0)) AS closing_total,
                        SUM(d.mortality) AS mortality_total"
              . ($productionType === 'layer' ? ", SUM(d.egg_production) AS eggs_total" : ", 0 AS eggs_total") . "
                 FROM {$table} d
                 WHERE d.farm_id = ? AND d.record_date BETWEEN ? AND ? {$cycleSql}
                 GROUP BY d.record_date
                 ORDER BY d.record_date";
    $stmt = $pdo->prepare($dailySql);
    $stmt->execute($params);
    $days = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $recordedDays = count($days);
    $averageLiveFlock = 0.0;
    $mortality = 0;
    $eggs = 0;
    if ($recordedDays > 0) {
        $midpointSum = 0.0;
        foreach ($days as $day) {
            $opening = (float)$day['opening_total'];
            $closing = (float)$day['closing_total'];
            $midpointSum += ($opening + $closing) / 2;
            $mortality += (int)$day['mortality_total'];
            $eggs += (int)$day['eggs_total'];
        }
        $averageLiveFlock = $midpointSum / $recordedDays;
    }

    $mortalityParams = [$farmId, $startDate, $endDate];
    $mortalityCycleSql = '';
    if ($cycleId) {
        $mortalityCycleSql = ' AND d.cycle_id = ?';
        $mortalityParams[] = $cycleId;
    }
    $mortalitySql = "SELECT
                        COALESCE(SUM(CASE WHEN pc.bird_unit_cost IS NOT NULL
                            THEN d.mortality * pc.bird_unit_cost ELSE 0 END), 0) AS mortality_cost,
                        COALESCE(SUM(CASE WHEN d.mortality > 0 AND pc.bird_unit_cost IS NOT NULL
                            THEN d.mortality ELSE 0 END), 0) AS costed_mortality,
                        COALESCE(SUM(CASE WHEN d.mortality > 0 AND pc.bird_unit_cost IS NULL
                            THEN d.mortality ELSE 0 END), 0) AS uncosted_mortality
                     FROM {$table} d
                     LEFT JOIN production_cycles pc
                       ON pc.id = d.cycle_id AND pc.farm_id = d.farm_id
                     WHERE d.farm_id = ? AND d.record_date BETWEEN ? AND ? {$mortalityCycleSql}";
    $stmt = $pdo->prepare($mortalitySql);
    $stmt->execute($mortalityParams);
    $mortalityRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $feedCost = (float)($profitabilitySummary['feed_consumption_cost'] ?? 0);
    $baseOperatingCost = (float)($profitabilitySummary['total_operating_cost'] ?? 0);
    $mortalityCost = (float)($mortalityRow['mortality_cost'] ?? 0);
    // Mortality value is management intelligence: it values productive birds lost
    // at the explicit cycle cost basis. It is NOT another period operating expense
    // and is therefore not deducted again from profitability.
    $totalProductionCost = $baseOperatingCost;
    $revenue = (float)($profitabilitySummary['revenue'] ?? 0);
    $profit = $revenue - $baseOperatingCost;
    $crates = $productionType === 'layer' ? $eggs / 30 : 0.0;

    $safeDivide = static function(float $numerator, float $denominator): ?float {
        return $denominator > 0 ? $numerator / $denominator : null;
    };

    return [
        'available' => true,
        'production_type' => $productionType,
        'recorded_days' => $recordedDays,
        'average_live_flock' => $averageLiveFlock,
        'mortality' => $mortality,
        'mortality_cost' => $mortalityCost,
        'costed_mortality' => (int)($mortalityRow['costed_mortality'] ?? 0),
        'uncosted_mortality' => (int)($mortalityRow['uncosted_mortality'] ?? 0),
        'eggs_produced' => $eggs,
        'crates_equivalent' => $crates,
        'feed_cost_per_bird' => $safeDivide($feedCost, $averageLiveFlock),
        'operating_cost_per_bird' => $safeDivide($totalProductionCost, $averageLiveFlock),
        'profit_per_bird' => $safeDivide($profit, $averageLiveFlock),
        'feed_cost_per_egg' => $productionType === 'layer' ? $safeDivide($feedCost, (float)$eggs) : null,
        'feed_cost_per_crate' => $productionType === 'layer' ? $safeDivide($feedCost, $crates) : null,
        'operating_cost_per_egg' => $productionType === 'layer' ? $safeDivide($totalProductionCost, (float)$eggs) : null,
        'operating_cost_per_crate' => $productionType === 'layer' ? $safeDivide($totalProductionCost, $crates) : null,
        'total_production_cost' => $totalProductionCost,
        'profit' => $profit,
        'margin_percent' => $revenue > 0 ? ($profit / $revenue) * 100 : null,
    ];
}}

