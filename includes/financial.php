<?php
require_once __DIR__ . '/../lib/stock_reporting.php';
require_once __DIR__ . '/../lib/stock_costing.php';
require_once __DIR__ . '/../lib/attribution.php';
require_once __DIR__ . '/../lib/inventory_financial.php';
require_once __DIR__ . '/../lib/stock_consumption_economics.php';
require_once __DIR__ . '/../lib/ruminant_slaughter_sale_economics.php';
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

    /*
     * Attribution-composition presentation state.
     *
     * These values do not create a second accounting engine. They preserve
     * the same source rows already used by this canonical profitability
     * reader and explain whether included value is native/direct or arrived
     * through an explicit allocation.
     */
    $directRevenue = 0.0;
    $allocatedRevenue = 0.0;
    $allocatedSharedRevenue = 0.0;
    $unallocatedPooledRevenue = 0.0;
    $directExpenseRows = [];
    $allocatedExpenseRows = [];

    // Revenue: exact source records at farm/production level. At cycle level,
    // include direct cycle sales plus any explicit allocation of pooled sales.
    if ($cycleId) {
        $salesSql = "SELECT COALESCE(SUM(total_amount),0) FROM sales_records WHERE farm_id=? AND sale_date BETWEEN ? AND ? AND cycle_id=?";
        $salesParams = [$farmId,$startDate,$endDate,$cycleId];
        if ($farmType !== 'all') { $salesSql .= " AND farm_type=?"; $salesParams[]=$farmType; }
        if ($productionType !== '') { $salesSql .= " AND production_type=?"; $salesParams[]=$productionType; }
        $stmt=$pdo->prepare($salesSql); $stmt->execute($salesParams); $directRevenue=(float)$stmt->fetchColumn(); $revenue=$directRevenue;

        $allocSql = "SELECT COALESCE(SUM(sa.allocated_amount),0)
                     FROM sales_allocations sa
                     JOIN sales_records s ON s.id=sa.sale_id AND s.farm_id=sa.farm_id
                     JOIN production_cycles pc ON pc.id=sa.cycle_id AND pc.farm_id=sa.farm_id
                     WHERE sa.farm_id=? AND sa.cycle_id=? AND s.sale_date BETWEEN ? AND ?";
        $allocParams=[$farmId,$cycleId,$startDate,$endDate];
        if ($farmType !== 'all') { $allocSql .= " AND pc.farm_type=?"; $allocParams[]=$farmType; }
        if ($productionType !== '') { $allocSql .= " AND pc.production_type=?"; $allocParams[]=$productionType; }
        $stmt=$pdo->prepare($allocSql); $stmt->execute($allocParams); $allocatedRevenue=(float)$stmt->fetchColumn(); $revenue += $allocatedRevenue;

        /*
         * Shared-revenue disclosure previously lived in the Profitability
         * page. Keep it central so the page does not own allocation SQL.
         */
        $sharedIncludedSql =
            "SELECT COALESCE(SUM(sa.allocated_amount),0)
             FROM sales_allocations sa
             JOIN sales_records s
               ON s.id=sa.sale_id
              AND s.farm_id=sa.farm_id
             WHERE sa.farm_id=?
               AND sa.cycle_id=?
               AND s.cycle_id IS NULL
               AND s.sale_date BETWEEN ? AND ?";

        $stmt =
            $pdo->prepare(
                $sharedIncludedSql
            );

        $stmt->execute(
            [
                $farmId,
                $cycleId,
                $startDate,
                $endDate,
            ]
        );

        $allocatedSharedRevenue =
            (float)$stmt->fetchColumn();

        $pooledSql =
            "SELECT COALESCE(
                SUM(
                    GREATEST(
                        s.total_amount
                        -
                        COALESCE(
                            a.allocated_amount,
                            0
                        ),
                        0
                    )
                ),
                0
             )
             FROM sales_records s
             JOIN production_cycles target_pc
               ON target_pc.id=?
              AND target_pc.farm_id=s.farm_id
             LEFT JOIN (
                 SELECT
                     farm_id,
                     sale_id,
                     SUM(allocated_amount) AS allocated_amount
                 FROM sales_allocations
                 GROUP BY farm_id,sale_id
             ) a
               ON a.farm_id=s.farm_id
              AND a.sale_id=s.id
             WHERE s.farm_id=?
               AND s.sale_date BETWEEN ? AND ?
               AND s.cycle_id IS NULL
               AND s.farm_type=target_pc.farm_type
               AND s.production_type=target_pc.production_type";

        $stmt =
            $pdo->prepare(
                $pooledSql
            );

        $stmt->execute(
            [
                $cycleId,
                $farmId,
                $startDate,
                $endDate,
            ]
        );

        $unallocatedPooledRevenue =
            (float)$stmt->fetchColumn();

    } else {
        $salesSql = "SELECT COALESCE(SUM(total_amount),0) FROM sales_records WHERE farm_id=? AND sale_date BETWEEN ? AND ?";
        $salesParams=[$farmId,$startDate,$endDate];
        if ($farmType !== 'all') { $salesSql .= " AND farm_type=?"; $salesParams[]=$farmType; }
        if ($productionType !== '') { $salesSql .= " AND production_type=?"; $salesParams[]=$productionType; }
        $stmt=$pdo->prepare($salesSql); $stmt->execute($salesParams); $revenue=(float)$stmt->fetchColumn();
        $directRevenue = $revenue;
    }

    if (
        (int)round(
            (
                $directRevenue
                +
                $allocatedRevenue
            ) * 100
        )
        !==
        (int)round(
            $revenue * 100
        )
    ) {
        throw new RuntimeException(
            'Profitability revenue attribution composition does not conserve its canonical total.'
        );
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
        foreach(
            $stmt->fetchAll(PDO::FETCH_KEY_PAIR)
            as $cat=>$amount
        ) {
            $directExpenseRows[$cat] =
                (float)$amount;

            $expenseRows[$cat] =
                (float)$amount;
        }

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
        foreach(
            $stmt->fetchAll(PDO::FETCH_KEY_PAIR)
            as $cat=>$amount
        ) {
            $allocatedExpenseRows[$cat] =
                (float)$amount;

            $expenseRows[$cat] =
                (
                    $expenseRows[$cat]
                    ?? 0
                )
                +
                (float)$amount;
        }
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
        $directExpenseRows = $expenseRows;
    }

    /*
     * Consumed-stock operating cost has one canonical economic reader.
     *
     * Farm-wide reports retain each parent movement once. Narrower module,
     * production-type and cycle reports receive only the explicitly attributed
     * share where the parent itself lives at a broader scope.
     */
    $stockConsumption =
        stock_consumption_economics_summary(
            $pdo,
            $farmId,
            $startDate,
            $endDate,
            $farmType,
            $productionType !== ''
                ? $productionType
                : null,
            $cycleId
        );

    $feedCost =
        (float)(
            $stockConsumption[
                'feed_consumption_cost'
            ]
            ?? 0
        );

    /*
     * Attribution composition is derived only from rows returned by the
     * canonical consumed-stock economics reader. No stock SQL or competing
     * scope policy is introduced here.
     */
    $feedDirectNativeCents = 0;
    $feedExplicitAllocationCents = 0;
    $operatingDirectNativeCents = 0;
    $operatingExplicitAllocationCents = 0;

    $stockSourceCounts = [
        'native_parent' => 0,
        'explicit_allocation' => 0,
    ];

    foreach(
        $stockConsumption['rows']
        ?? []
        as $stockEconomicRow
    ) {
        $mode =
            (string)(
                $stockEconomicRow[
                    'attribution_mode'
                ]
                ?? ''
            );

        if (
            !array_key_exists(
                $mode,
                $stockSourceCounts
            )
        ) {
            throw new RuntimeException(
                'Profitability received an unsupported consumed-stock attribution mode.'
            );
        }

        $amountCents =
            (int)(
                $stockEconomicRow[
                    'economic_amount_cents'
                ]
                ?? 0
            );

        if ($amountCents < 0) {
            throw new RuntimeException(
                'Profitability received a negative consumed-stock economic amount.'
            );
        }

        $stockSourceCounts[$mode]++;

        $costKind =
            (string)(
                $stockEconomicRow[
                    'cost_kind'
                ]
                ?? ''
            );

        if ($costKind === 'feed') {
            if ($mode === 'explicit_allocation') {
                $feedExplicitAllocationCents +=
                    $amountCents;
            } else {
                $feedDirectNativeCents +=
                    $amountCents;
            }

            continue;
        }

        if ($costKind === 'operating') {
            if ($mode === 'explicit_allocation') {
                $operatingExplicitAllocationCents +=
                    $amountCents;
            } else {
                $operatingDirectNativeCents +=
                    $amountCents;
            }
        }
    }

    if (
        (
            $feedDirectNativeCents
            +
            $feedExplicitAllocationCents
        )
        !==
        (int)round(
            $feedCost * 100
        )
    ) {
        throw new RuntimeException(
            'Profitability Feed attribution composition does not conserve its canonical total.'
        );
    }

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

    /*
     * Non-feed consumed inventory comes from the same canonical reader as Feed.
     * Do not independently re-query stock_transactions here: doing so would
     * bypass explicit consumed-stock allocation and reintroduce competing
     * attribution formulas.
     */
    $inventoryConsumptionBreakdown =
        $stockConsumption[
            'inventory_operating_consumption_breakdown'
        ]
        ?? [];

    $inventoryOperatingConsumption =
        (float)(
            $stockConsumption[
                'inventory_operating_consumption_cost'
            ]
            ?? 0
        );

    if (
        (
            $operatingDirectNativeCents
            +
            $operatingExplicitAllocationCents
        )
        !==
        (int)round(
            $inventoryOperatingConsumption
            * 100
        )
    ) {
        throw new RuntimeException(
            'Profitability operating-stock attribution composition does not conserve its canonical total.'
        );
    }

    $manualNonFeedExpenses=0.0;
    foreach($expenseRows as $category=>$amount) if($category!=='feeds') $manualNonFeedExpenses+=(float)$amount;

    $directManualNonFeedExpenses = 0.0;

    foreach(
        $directExpenseRows
        as $category=>$amount
    ) {
        if ($category === 'feeds') {
            continue;
        }

        $directManualNonFeedExpenses +=
            (float)$amount;
    }

    $allocatedManualNonFeedExpenses = 0.0;

    foreach(
        $allocatedExpenseRows
        as $category=>$amount
    ) {
        if ($category === 'feeds') {
            continue;
        }

        $allocatedManualNonFeedExpenses +=
            (float)$amount;
    }

    if (
        (int)round(
            (
                $directManualNonFeedExpenses
                +
                $allocatedManualNonFeedExpenses
            ) * 100
        )
        !==
        (int)round(
            $manualNonFeedExpenses
            * 100
        )
    ) {
        throw new RuntimeException(
            'Profitability manual-expense attribution composition does not conserve its canonical total.'
        );
    }

    /*
     * Sale-specific cost of goods sold.
     *
     * Slaughter output remains Financial Type other_stock so unrelated
     * general stock is never turned into period operating cost. The
     * dedicated reader recognises only explicit effective slaughter-sale
     * lot consumption with conserved frozen COGS provenance.
     */
    $slaughterSaleEconomics =
        ruminant_slaughter_sale_economics_summary(
            $pdo,
            $farmId,
            $startDate,
            $endDate,
            $farmType,
            $productionType !== ''
                ? $productionType
                : null,
            $cycleId
        );

    $slaughterOutputCogs =
        (float)(
            $slaughterSaleEconomics[
                'slaughter_output_cogs'
            ]
            ?? 0
        );

    $nonFeedExpenses=$manualNonFeedExpenses+$inventoryOperatingConsumption;

    /*
     * Keep operating cost semantically distinct from COGS.
     * Profit uses total recognised cost so downstream reporting can
     * explain both values without double-counting either one.
     */
    $totalOperatingCost=$nonFeedExpenses+$feedCost;
    $totalRecognizedCost=$totalOperatingCost+$slaughterOutputCogs;

    return [
        'revenue'=>$revenue,
        'feed_consumption_cost'=>$feedCost,
        'non_feed_expenses'=>$nonFeedExpenses,
        'manual_non_feed_expenses'=>$manualNonFeedExpenses,
        'inventory_operating_consumption_cost'=>$inventoryOperatingConsumption,
        'inventory_operating_consumption_breakdown'=>$inventoryConsumptionBreakdown,
        'cost_of_goods_sold'=>$slaughterOutputCogs,
        'slaughter_output_cogs'=>$slaughterOutputCogs,
        'cost_of_goods_sold_breakdown'=>[
            'ruminant_slaughter_output'=>$slaughterOutputCogs,
        ],
        'total_operating_cost'=>$totalOperatingCost,
        'total_recognized_cost'=>$totalRecognizedCost,
        'profit'=>$revenue-$totalRecognizedCost,
        'cash_feed_expenses'=>$cashFeed,
        'expense_breakdown'=>$expenseRows,
        'allocated_shared_revenue'=>$allocatedSharedRevenue,
        'unallocated_pooled_revenue'=>$unallocatedPooledRevenue,
        'attribution_composition'=>[
            'revenue'=>[
                'direct_sales'=>$directRevenue,
                'explicit_sale_allocation'=>$allocatedRevenue,
                'allocated_shared_revenue'=>$allocatedSharedRevenue,
                'unallocated_pooled_revenue'=>$unallocatedPooledRevenue,
                'total'=>$revenue,
            ],
            'feed_consumption'=>[
                'direct_native_stock'=>$feedDirectNativeCents / 100,
                'explicit_stock_allocation'=>$feedExplicitAllocationCents / 100,
                'total'=>$feedCost,
            ],
            'operating_inventory_consumption'=>[
                'direct_native_stock'=>$operatingDirectNativeCents / 100,
                'explicit_stock_allocation'=>$operatingExplicitAllocationCents / 100,
                'total'=>$inventoryOperatingConsumption,
            ],
            'manual_non_feed_expenses'=>[
                'direct_expense'=>$directManualNonFeedExpenses,
                'explicit_shared_expense_allocation'=>$allocatedManualNonFeedExpenses,
                'total'=>$manualNonFeedExpenses,
            ],
            'cost_of_goods_sold'=>[
                'ruminant_slaughter_output'=>$slaughterOutputCogs,
                'total'=>$slaughterOutputCogs,
            ],
            'other_operating_cost'=>[
                'manual_non_feed_expenses'=>$manualNonFeedExpenses,
                'inventory_operating_consumption'=>$inventoryOperatingConsumption,
                'total'=>$nonFeedExpenses,
            ],
            'stock_source_counts'=>[
                'native_parent_rows'=>$stockSourceCounts['native_parent'],
                'explicit_allocation_rows'=>$stockSourceCounts['explicit_allocation'],
            ],
            'total_operating_cost'=>$totalOperatingCost,
            'total_recognized_cost'=>$totalRecognizedCost,
            'profit'=>$revenue-$totalRecognizedCost,
        ],
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

