<?php
require_once __DIR__ . '/stock_reporting.php';
require_once __DIR__ . '/stock_costing.php';
require_once __DIR__ . '/inventory_financial.php';
require_once __DIR__ . '/poultry_cycle_lifecycle.php';
require_once __DIR__ . '/poultry_cycle_acquisition.php';

/**
 * V2.2.50 Batch 3A — read-only Layer rearing / production-entry economics.
 *
 * This helper does not post financial transactions and does not modify the
 * canonical period-profitability engine. It interprets already-attributed,
 * historical source records inside the explicit Layer Rearing phase.
 */
if (!function_exists('poultry_rearing_economics')) {
function poultry_rearing_economics(PDO $pdo, int $farmId, int $cycleId): array
{
    $base = [
        'available' => false,
        'mode' => 'not_available',
        'message' => 'Rearing economics are not available for this cycle.',
        'cycle' => null,
        'rearing_phase' => null,
        'production_phase' => null,
        'acquisition_cost' => 0.0,
        'acquisition_quantity' => 0,
        'feed_consumed_cost' => 0.0,
        'inventory_operating_cost' => 0.0,
        'inventory_operating_breakdown' => [],
        'direct_expenses' => 0.0,
        'allocated_shared_expenses' => 0.0,
        'expense_breakdown' => [],
        'known_attributable_rearing_cost' => 0.0,
        'rearing_investment' => null,
        'production_entry_headcount' => null,
        'production_entry_headcount_source' => null,
        'investment_per_surviving_bird' => null,
        'uncosted_feed_uses' => 0,
        'uncosted_operating_uses' => 0,
        'uncosted_acquisition_entries' => 0,
        'unallocated_shared_expense_pool' => 0.0,
        'warnings' => [],
    ];

    $stmt = $pdo->prepare(
        "SELECT id, farm_id, cycle_code, farm_type, production_type, status, start_date, close_date,
                opening_headcount, closing_headcount, bird_unit_cost
         FROM production_cycles
         WHERE id=? AND farm_id=? AND farm_type='poultry'
         LIMIT 1"
    );
    $stmt->execute([$cycleId, $farmId]);
    $cycle = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cycle || strtolower((string)$cycle['production_type']) !== 'layer') {
        $base['message'] = 'Layer rearing economics are available only for a Layer production cycle in this farm.';
        return $base;
    }
    $base['cycle'] = $cycle;

    $phases = poultry_lifecycle_history($pdo, $farmId, $cycleId);
    $rearing = null;
    $production = null;
    foreach ($phases as $phase) {
        if ((string)$phase['phase'] === 'rearing' && $rearing === null) $rearing = $phase;
        if ((string)$phase['phase'] === 'production' && $production === null) $production = $phase;
    }
    $base['rearing_phase'] = $rearing;
    $base['production_phase'] = $production;

    $acqRows = poultry_acquisition_history($pdo, $farmId, $cycleId);
    $activeAcq = array_values(array_filter($acqRows, static fn(array $r): bool => empty($r['voided_at'])));
    $polRows = array_values(array_filter($activeAcq, static fn(array $r): bool => (string)$r['acquisition_type'] === 'purchased_point_of_lay'));

    // POL is a distinct entry model. Do not fabricate an on-farm rearing phase.
    if ($rearing === null && !empty($polRows)) {
        $qty = 0; $cost = 0.0; $allCosted = true;
        foreach ($polRows as $row) {
            $qty += (int)$row['quantity'];
            if ($row['total_cost'] === null || $row['total_cost'] === '') $allCosted = false;
            else $cost += (float)$row['total_cost'];
        }
        $base['available'] = true;
        $base['mode'] = 'pol';
        $base['message'] = 'Purchased Point-of-Lay entry. On-farm rearing investment is not applicable.';
        $base['acquisition_quantity'] = $qty;
        $base['acquisition_cost'] = $allCosted ? round($cost, 2) : 0.0;
        $base['rearing_investment'] = $allCosted ? round($cost, 2) : null;
        $base['production_entry_headcount'] = $qty > 0 ? $qty : null;
        $base['production_entry_headcount_source'] = $qty > 0 ? 'Recorded Point-of-Lay acquisition quantity' : null;
        $base['investment_per_surviving_bird'] = ($allCosted && $qty > 0) ? round($cost / $qty, 2) : null;
        if (!$allCosted) $base['warnings'][] = 'One or more active Point-of-Lay acquisition entries do not have a defensible cost basis.';
        if ($production === null) $base['warnings'][] = 'Production lifecycle phase has not yet been recorded; acquisition basis is shown without inventing a production-entry date.';
        return $base;
    }

    if ($rearing === null) {
        $base['message'] = 'No explicit Layer Rearing phase is recorded. Rearing is not inferred from bird age, egg output, feed, or cycle status.';
        return $base;
    }
    if (empty($rearing['end_date']) || $production === null) {
        $base['message'] = 'Rearing is still open or no Production transition is recorded. Accumulated rearing cost can be reviewed only after a known production-entry boundary exists.';
        return $base;
    }

    $start = (string)$rearing['start_date'];
    $end = (string)$rearing['end_date'];
    $productionStart = (string)$production['start_date'];

    // Acquisition basis: active non-POL entries received no later than the end of rearing.
    $acqCost = 0.0; $acqQty = 0; $uncostedAcq = 0; $eligibleAcqRows = 0;
    foreach ($activeAcq as $row) {
        if ((string)$row['acquisition_type'] === 'purchased_point_of_lay') continue;
        if ((string)$row['acquisition_date'] > $end) continue;
        $eligibleAcqRows++;
        $acqQty += (int)$row['quantity'];
        if ($row['total_cost'] === null || $row['total_cost'] === '') $uncostedAcq++;
        else $acqCost += (float)$row['total_cost'];
    }
    $base['acquisition_cost'] = round($acqCost, 2);
    $base['acquisition_quantity'] = $acqQty;
    $base['uncosted_acquisition_entries'] = $uncostedAcq;
    if ($eligibleAcqRows === 0) {
        $uncostedAcq++;
        $base['uncosted_acquisition_entries'] = $uncostedAcq;
    }

    $effective = stock_effective_sql_predicate('t');
    $feedPredicate = stock_feed_item_sql_predicate('s', 'c');

    $feedSql = "SELECT COALESCE(SUM(t.total_cost),0), SUM(CASE WHEN t.total_cost IS NULL THEN 1 ELSE 0 END)
                FROM stock_transactions t
                JOIN stock_items s ON s.id=t.stock_item_id AND s.farm_id=t.farm_id
                LEFT JOIN inventory_categories c ON c.id=s.category_id AND c.farm_id=s.farm_id
                WHERE t.farm_id=? AND t.cycle_id=? AND t.transaction_type='used' AND {$effective}
                  AND {$feedPredicate} AND t.transaction_date BETWEEN ? AND ?";
    $stmt = $pdo->prepare($feedSql); $stmt->execute([$farmId,$cycleId,$start,$end]);
    $feed = $stmt->fetch(PDO::FETCH_NUM) ?: [0,0];
    $base['feed_consumed_cost'] = round((float)$feed[0],2);
    $base['uncosted_feed_uses'] = (int)$feed[1];

    $classes = array_keys(inventory_operating_consumption_classifications());
    if ($classes) {
        $ph = implode(',', array_fill(0,count($classes),'?'));
        $sql = "SELECT t.financial_classification, COALESCE(SUM(t.total_cost),0) total,
                       SUM(CASE WHEN t.total_cost IS NULL THEN 1 ELSE 0 END) uncosted
                FROM stock_transactions t
                WHERE t.farm_id=? AND t.cycle_id=? AND t.transaction_type='used' AND {$effective}
                  AND t.transaction_date BETWEEN ? AND ? AND t.financial_classification IN ({$ph})
                GROUP BY t.financial_classification";
        $params = array_merge([$farmId,$cycleId,$start,$end],$classes);
        $stmt=$pdo->prepare($sql); $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key=(string)$row['financial_classification'];
            $base['inventory_operating_breakdown'][$key]=round((float)$row['total'],2);
            $base['inventory_operating_cost'] += (float)$row['total'];
            $base['uncosted_operating_uses'] += (int)$row['uncosted'];
        }
        $base['inventory_operating_cost']=round($base['inventory_operating_cost'],2);
    }

    // Direct non-feed expenses recorded specifically against this cycle.
    $sql = "SELECT category, COALESCE(SUM(amount*unit),0) total
            FROM farm_expenses
            WHERE farm_id=? AND cycle_id=? AND expense_date BETWEEN ? AND ? AND category<>'feeds'
            GROUP BY category";
    $stmt=$pdo->prepare($sql); $stmt->execute([$farmId,$cycleId,$start,$end]);
    foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $cat=>$amount) {
        $value=(float)$amount;
        $base['expense_breakdown'][$cat]=($base['expense_breakdown'][$cat]??0)+$value;
        $base['direct_expenses'] += $value;
    }
    $base['direct_expenses']=round($base['direct_expenses'],2);

    // Explicit shared-expense allocations are defensible cycle attribution.
    $sql = "SELECT e.category, COALESCE(SUM(fa.allocated_amount),0) total
            FROM financial_allocations fa
            JOIN farm_expenses e ON e.id=fa.expense_id AND e.farm_id=fa.farm_id
            WHERE fa.farm_id=? AND fa.cycle_id=? AND e.expense_date BETWEEN ? AND ? AND e.category<>'feeds'
            GROUP BY e.category";
    $stmt=$pdo->prepare($sql); $stmt->execute([$farmId,$cycleId,$start,$end]);
    foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $cat=>$amount) {
        $value=(float)$amount;
        $base['expense_breakdown'][$cat]=($base['expense_breakdown'][$cat]??0)+$value;
        $base['allocated_shared_expenses'] += $value;
    }
    $base['allocated_shared_expenses']=round($base['allocated_shared_expenses'],2);

    // Disclosure only: Layer shared expense pool not yet explicitly allocated.
    // It is NOT included in rearing investment because that would invent precision.
    $sql = "SELECT COALESCE(SUM(GREATEST((e.amount*e.unit)-COALESCE(a.allocated,0),0)),0)
            FROM farm_expenses e
            LEFT JOIN (
                SELECT farm_id, expense_id, SUM(allocated_amount) allocated
                FROM financial_allocations GROUP BY farm_id, expense_id
            ) a ON a.farm_id=e.farm_id AND a.expense_id=e.id
            WHERE e.farm_id=? AND e.cycle_id IS NULL AND e.expense_date BETWEEN ? AND ?
              AND e.farm_type IN ('poultry','both') AND LOWER(COALESCE(e.production_type,''))='layer' AND e.category<>'feeds'";
    $stmt=$pdo->prepare($sql); $stmt->execute([$farmId,$start,$end]);
    $base['unallocated_shared_expense_pool']=round((float)$stmt->fetchColumn(),2);

    $complete = $uncostedAcq===0 && $base['uncosted_feed_uses']===0 && $base['uncosted_operating_uses']===0;
    $investment = $acqCost + $base['feed_consumed_cost'] + $base['inventory_operating_cost'] + $base['direct_expenses'] + $base['allocated_shared_expenses'];
    $base['known_attributable_rearing_cost'] = round($investment, 2);
    $base['rearing_investment'] = $complete ? round($investment,2) : null;

    // Production-entry headcount: use only the exact boundary records. If both
    // sides exist but disagree, refuse to invent a definitive surviving count.
    $entryStmt=$pdo->prepare('SELECT opening_stock FROM layer_daily_records WHERE farm_id=? AND cycle_id=? AND record_date=? LIMIT 1');
    $entryStmt->execute([$farmId,$cycleId,$productionStart]);
    $productionOpening=$entryStmt->fetchColumn();
    $endStmt=$pdo->prepare('SELECT opening_stock,mortality FROM layer_daily_records WHERE farm_id=? AND cycle_id=? AND record_date=? LIMIT 1');
    $endStmt->execute([$farmId,$cycleId,$end]);
    $rearingEndRow=$endStmt->fetch(PDO::FETCH_ASSOC);
    $rearingClosing=$rearingEndRow ? max(0,(int)$rearingEndRow['opening_stock']-(int)$rearingEndRow['mortality']) : null;

    if ($productionOpening !== false && $rearingClosing !== null) {
        if ((int)$productionOpening === $rearingClosing) {
            $base['production_entry_headcount']=(int)$productionOpening;
            $base['production_entry_headcount_source']='Production-start opening flock, reconciled to prior rearing-day closing flock';
        } else {
            $base['warnings'][]='Production-entry flock boundary does not reconcile: production opening flock differs from the preceding rearing-day closing flock.';
        }
    } elseif ($productionOpening !== false) {
        $base['production_entry_headcount']=(int)$productionOpening;
        $base['production_entry_headcount_source']='Production-start opening flock';
    } elseif ($rearingClosing !== null) {
        $base['production_entry_headcount']=$rearingClosing;
        $base['production_entry_headcount_source']='Rearing-end closing flock';
    } else {
        $base['warnings'][]='No exact Daily Record was found on the production-entry boundary, so surviving production-entry flock is not inferred.';
    }

    if ($base['rearing_investment'] !== null && !empty($base['production_entry_headcount'])) {
        $base['investment_per_surviving_bird']=round($base['rearing_investment']/(int)$base['production_entry_headcount'],2);
    }
    if ($base['uncosted_feed_uses']>0) $base['warnings'][]='One or more feed-use transactions in the Rearing phase have no historical cost snapshot.';
    if ($base['uncosted_operating_uses']>0) $base['warnings'][]='One or more eligible non-feed inventory-use transactions in the Rearing phase have no historical cost snapshot.';
    if ($eligibleAcqRows===0) $base['warnings'][]='No active flock-entry/acquisition record is available for the Rearing phase, so rearing investment cannot be presented as complete.';
    elseif ($uncostedAcq>0) $base['warnings'][]='One or more active flock-entry records in the Rearing phase do not have a defensible acquisition cost.';
    if ($base['unallocated_shared_expense_pool']>0) $base['warnings'][]='Unallocated shared Layer expenses exist in the Rearing window. They are disclosed separately and are not silently assigned to this cycle.';

    $base['available']=true;
    $base['mode']='reared';
    $base['message']='Actual recorded costs attributed to the explicit Layer Rearing phase. Mortality value is not deducted again.';
    return $base;
}
}
