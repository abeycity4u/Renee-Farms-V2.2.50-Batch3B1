<?php
require_once __DIR__ . '/../includes/financial.php';
require_once __DIR__ . '/sales_units.php';
require_once __DIR__ . '/poultry_diagnostics.php';
require_once __DIR__ . '/ruminant_diagnostics.php';
require_once __DIR__ . '/record_quality_intelligence.php';
require_once __DIR__ . '/investigation_followup.php';

/**
 * V2.2.49 canonical farm-intelligence read model.
 *
 * Financial intelligence MUST delegate to getProfitabilitySummary(). This file
 * is intentionally a reporting/read layer; it does not create a second profit
 * formula and it never writes derived totals back to the database.
 */

if (!function_exists('farm_intelligence_summary')) {
function farm_intelligence_summary(PDO $pdo, int $farmId, string $startDate, string $endDate, string $farmType = 'all'): array {
    if ($farmType === '') {
        return [
            'revenue'=>0.0,'feed_consumption_cost'=>0.0,'non_feed_expenses'=>0.0,
            'manual_non_feed_expenses'=>0.0,'inventory_operating_consumption_cost'=>0.0,
            'inventory_operating_consumption_breakdown'=>[],'total_operating_cost'=>0.0,
            'profit'=>0.0,'cash_feed_expenses'=>0.0,'expense_breakdown'=>[],
            'start_date'=>$startDate,'end_date'=>$endDate,'farm_type'=>'','margin_percent'=>null,
        ];
    }
    $farmType = in_array($farmType, ['all','poultry','ruminant','general'], true) ? $farmType : 'all';
    $summary = getProfitabilitySummary($pdo, $farmId, $startDate, $endDate, $farmType);
    $summary['start_date'] = $startDate;
    $summary['end_date'] = $endDate;
    $summary['farm_type'] = $farmType;
    $summary['margin_percent'] = abs((float)$summary['revenue']) > 0.00001
        ? ((float)$summary['profit'] / (float)$summary['revenue']) * 100
        : null;
    return $summary;
}}

if (!function_exists('farm_intelligence_monthly_series')) {
function farm_intelligence_monthly_series(PDO $pdo, int $farmId, int $year, string $farmType = 'all'): array {
    $rows = [];
    for ($month = 1; $month <= 12; $month++) {
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = date('Y-m-t', strtotime($start));
        $summary = farm_intelligence_summary($pdo, $farmId, $start, $end, $farmType);
        $rows[] = [
            'month' => substr($start, 0, 7),
            'farm_type' => $farmType,
            'total_sales' => (float)$summary['revenue'],
            'feed_consumed' => (float)$summary['feed_consumption_cost'],
            'other_operating_cost' => (float)$summary['non_feed_expenses'],
            'total_expenses' => (float)$summary['total_operating_cost'],
            'net_profit' => (float)$summary['profit'],
            'margin_percent' => $summary['margin_percent'],
        ];
    }
    return $rows;
}}

if (!function_exists('farm_intelligence_expense_breakdown')) {
function farm_intelligence_expense_breakdown(PDO $pdo, int $farmId, string $startDate, string $endDate, string $farmType = 'all'): array {
    $summary = farm_intelligence_summary($pdo, $farmId, $startDate, $endDate, $farmType);
    $breakdown = [];
    if ((float)$summary['feed_consumption_cost'] != 0.0) {
        $breakdown['Feed Consumed'] = (float)$summary['feed_consumption_cost'];
    }
    foreach (($summary['expense_breakdown'] ?? []) as $category => $amount) {
        if (strtolower((string)$category) === 'feeds') continue; // purchase/cash event; not period cost
        $label = ucwords(str_replace(['_','-'], ' ', (string)$category));
        $breakdown[$label] = ($breakdown[$label] ?? 0.0) + (float)$amount;
    }
    foreach (($summary['inventory_operating_consumption_breakdown'] ?? []) as $classification => $amount) {
        $label = 'Inventory Used · ' . (string)$classification;
        $breakdown[$label] = ($breakdown[$label] ?? 0.0) + (float)$amount;
    }
    arsort($breakdown, SORT_NUMERIC);
    $rows = [];
    foreach ($breakdown as $category => $amount) $rows[] = ['category'=>$category, 'total_amount'=>(float)$amount];
    return $rows;
}}

if (!function_exists('farm_intelligence_top_products')) {
function farm_intelligence_top_products(PDO $pdo, int $farmId, string $startDate, string $endDate, string $farmType = 'all', int $limit = 10): array {
    $sql = "SELECT product_type, unit_of_measure, SUM(quantity) total_quantity, SUM(total_amount) total_revenue
            FROM sales_records WHERE farm_id=? AND sale_date BETWEEN ? AND ?";
    $params = [$farmId,$startDate,$endDate];
    if ($farmType !== 'all') { $sql .= " AND farm_type=?"; $params[] = $farmType; }
    $sql .= " GROUP BY product_type, unit_of_measure ORDER BY total_revenue DESC LIMIT " . max(1, min(100, $limit));
    $stmt=$pdo->prepare($sql); $stmt->execute($params);
    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $unit = trim((string)($row['unit_of_measure'] ?? ''));
        $row['unit_label'] = $unit !== '' ? $unit : sales_unit_label(null);
        $row['display_product'] = (string)$row['product_type'] . ' · ' . $row['unit_label'];
        $row['total_quantity'] = (float)$row['total_quantity'];
        $row['total_revenue'] = (float)$row['total_revenue'];
    }
    unset($row);
    return $rows;
}}

if (!function_exists('farm_intelligence_rolling_months')) {
function farm_intelligence_rolling_months(PDO $pdo, int $farmId, int $months = 12, string $farmType = 'all'): array {
    $months=max(1,min(36,$months)); $rows=[];
    $anchor = new DateTimeImmutable(date('Y-m-01'));
    for ($i=$months-1; $i>=0; $i--) {
        $startObj=$anchor->modify("-{$i} months");
        $start=$startObj->format('Y-m-01'); $end=$startObj->format('Y-m-t');
        $s=farm_intelligence_summary($pdo,$farmId,$start,$end,$farmType);
        $rows[]=['period'=>$startObj->format('Y-m'),'label'=>$startObj->format('M Y'),'revenue'=>(float)$s['revenue'],'cost'=>(float)$s['total_operating_cost'],'profit'=>(float)$s['profit']];
    }
    return $rows;
}}

/**
 * V2.2.49 Batch 2 — explainable farm-intelligence signals.
 *
 * This layer turns recorded facts into management signals without creating a
 * second accounting model. Financial values continue to come exclusively from
 * farm_intelligence_summary()/getProfitabilitySummary(). Each signal carries
 * its measured value, reason, source period and action target so the dashboard
 * can explain why attention is requested.
 */
if (!function_exists('farm_intelligence_signal')) {
function farm_intelligence_signal(
    string $id,
    string $category,
    string $severity,
    string $title,
    string $measuredValue,
    string $reason,
    string $periodLabel,
    string $actionLabel,
    string $actionUrl,
    string $icon = 'bi-info-circle'
): array {
    $allowed = ['danger','warning','info','success'];
    if (!in_array($severity, $allowed, true)) $severity = 'info';
    return [
        'id'=>$id,
        'category'=>$category,
        'severity'=>$severity,
        'title'=>$title,
        'measured_value'=>$measuredValue,
        'reason'=>$reason,
        'period_label'=>$periodLabel,
        'action_label'=>$actionLabel,
        'action_url'=>$actionUrl,
        'icon'=>$icon,
    ];
}}

if (!function_exists('farm_intelligence_explainable_signals')) {
function farm_intelligence_explainable_signals(
    PDO $pdo,
    int $farmId,
    string $farmType = 'all',
    ?string $asOfDate = null
): array {
    $farmType = in_array($farmType, ['all','poultry','ruminant'], true) ? $farmType : 'all';
    $asOfDate = $asOfDate ?: date('Y-m-d');
    $asOf = new DateTimeImmutable($asOfDate);
    $periodStart = $asOf->format('Y-m-01');
    $periodEnd = $asOf->format('Y-m-d');
    $periodLabel = $asOf->format('M j, Y') . ' month-to-date';

    // Compare against the same number of elapsed calendar days in the prior
    // month. This avoids comparing a partial current month to a complete prior month.
    $priorAnchor = $asOf->modify('first day of previous month');
    $priorStart = $priorAnchor->format('Y-m-01');
    $elapsedDay = (int)$asOf->format('j');
    $priorLastDay = (int)$priorAnchor->format('t');
    $priorEndDay = min($elapsedDay, $priorLastDay);
    $priorEnd = $priorAnchor->setDate((int)$priorAnchor->format('Y'), (int)$priorAnchor->format('m'), $priorEndDay)->format('Y-m-d');
    $comparisonLabel = $priorAnchor->format('M j') . '–' . date('j, Y', strtotime($priorEnd));

    $current = farm_intelligence_summary($pdo, $farmId, $periodStart, $periodEnd, $farmType);
    $prior = farm_intelligence_summary($pdo, $farmId, $priorStart, $priorEnd, $farmType);
    $signals = [];

    // Financial position — factual, canonical, and period-matched.
    $currentProfit = (float)$current['profit'];
    $currentRevenue = (float)$current['revenue'];
    $currentCost = (float)$current['total_operating_cost'];
    if ($currentRevenue != 0.0 || $currentCost != 0.0) {
        if ($currentProfit < 0) {
            $signals[] = farm_intelligence_signal(
                'financial-operating-position','Financial','danger','Operating loss recorded',
                '₦'.number_format($currentProfit, 2),
                'Canonical month-to-date revenue is below consumed feed and other operating cost.',
                $periodLabel,'Review profitability','management/profitability.php','bi-graph-down-arrow'
            );
        } else {
            $margin = $current['margin_percent'];
            $measure = '₦'.number_format($currentProfit, 2) . ($margin !== null ? ' · '.number_format((float)$margin,1).'% margin' : '');
            $signals[] = farm_intelligence_signal(
                'financial-operating-position','Financial','success','Positive operating position',
                $measure,
                'Canonical month-to-date revenue exceeds consumed feed and other operating cost.',
                $periodLabel,'Review profitability','management/profitability.php','bi-graph-up-arrow'
            );
        }
    } else {
        $signals[] = farm_intelligence_signal(
            'financial-no-activity','Financial','info','No financial activity yet',
            '₦0.00 revenue · ₦0.00 operating cost',
            'No sales or operating cost has been recognised in the canonical profitability engine for this period.',
            $periodLabel,'Open profitability','management/profitability.php','bi-receipt'
        );
    }

    $priorHasActivity = ((float)$prior['revenue'] != 0.0 || (float)$prior['total_operating_cost'] != 0.0);
    $currentHasActivity = ($currentRevenue != 0.0 || $currentCost != 0.0);
    if ($priorHasActivity && $currentHasActivity) {
        $delta = $currentProfit - (float)$prior['profit'];
        $severity = $delta > 0.00001 ? 'success' : ($delta < -0.00001 ? 'warning' : 'info');
        $title = $delta > 0.00001 ? 'Operating result improved' : ($delta < -0.00001 ? 'Operating result declined' : 'Operating result unchanged');
        $signals[] = farm_intelligence_signal(
            'financial-period-comparison','Financial',$severity,$title,
            ($delta >= 0 ? '+' : '−').'₦'.number_format(abs($delta), 2),
            'Current month-to-date operating profit/loss is compared with the same elapsed calendar days in the previous month, not the whole previous month.',
            $periodLabel.' vs '.$comparisonLabel,'Open analytics','management/reports.php','bi-arrow-left-right'
        );
    }

    // Inventory threshold risk.
    $stockSql = "SELECT item_name,current_stock,min_stock_level,unit FROM stock_items
                 WHERE farm_id=? AND is_active=1 AND current_stock<=min_stock_level";
    $stockParams = [$farmId];
    if ($farmType !== 'all') {
        $stockSql .= " AND farm_type IN (?, 'both')";
        $stockParams[] = $farmType;
    }
    $stockSql .= ' ORDER BY (min_stock_level-current_stock) DESC, item_name ASC';
    $stmt = $pdo->prepare($stockSql); $stmt->execute($stockParams);
    $lowStock = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($lowStock) {
        $sample = array_slice(array_map(static fn($r)=>(string)$r['item_name'], $lowStock), 0, 3);
        $signals[] = farm_intelligence_signal(
            'inventory-low-stock','Inventory','danger','Stock below minimum level',
            count($lowStock).' item'.(count($lowStock)===1?'':'s'),
            'The following active stock items are at or below their configured minimum: '.implode(', ', $sample).(count($lowStock)>3?'…':''),
            'As of '.$asOf->format('M j, Y'),'Open inventory','inventory.php','bi-box-seam'
        );
    } else {
        $signals[] = farm_intelligence_signal(
            'inventory-low-stock','Inventory','success','Minimum stock coverage is clear',
            '0 items below minimum',
            'All visible active stock items are currently above their configured minimum stock level.',
            'As of '.$asOf->format('M j, Y'),'Review inventory','inventory.php','bi-check2-circle'
        );
    }

    // Profitability completeness: USED operating stock without a historical cost
    // snapshot must never be silently treated as zero cost.
    $effectiveStockSql = stock_effective_sql_predicate();
    $feedItemSql = stock_feed_item_sql_predicate('s','c');
    $operatingClasses = array_keys(inventory_operating_consumption_classifications());
    $opPlaceholders = $operatingClasses ? implode(',', array_fill(0, count($operatingClasses), '?')) : "''";
    $uncostedSql = "SELECT COUNT(*) tx_count, COALESCE(SUM(t.quantity),0) quantity_total
                    FROM stock_transactions t
                    JOIN stock_items s ON s.id=t.stock_item_id AND s.farm_id=t.farm_id
                    LEFT JOIN inventory_categories c ON c.id=s.category_id AND c.farm_id=s.farm_id
                    WHERE t.farm_id=? AND t.transaction_type='used' AND {$effectiveStockSql}
                      AND t.transaction_date BETWEEN ? AND ? AND t.total_cost IS NULL
                      AND (({$feedItemSql}) OR t.financial_classification IN ({$opPlaceholders}))";
    $uncostedParams = array_merge([$farmId,$periodStart,$periodEnd], $operatingClasses);
    if ($farmType !== 'all') { $uncostedSql .= ' AND t.farm_type=?'; $uncostedParams[] = $farmType; }
    $stmt=$pdo->prepare($uncostedSql); $stmt->execute($uncostedParams); $uncosted=$stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $uncostedCount=(int)($uncosted['tx_count'] ?? 0);
    if ($uncostedCount > 0) {
        $signals[] = farm_intelligence_signal(
            'data-uncosted-stock-use','Data quality','danger','Operating stock usage is uncosted',
            $uncostedCount.' transaction'.($uncostedCount===1?'':'s'),
            'These USED feed/operating-stock transactions have no historical total-cost snapshot, so period profitability is incomplete until their cost history is corrected.',
            $periodLabel,'Review stock history','inventory.php','bi-exclamation-octagon'
        );
    }

    // Historical/legacy UOM remains explicit instead of being guessed.
    $uomSql="SELECT COUNT(*) FROM sales_records WHERE farm_id=? AND sale_date BETWEEN ? AND ? AND (unit_of_measure IS NULL OR TRIM(unit_of_measure)='')";
    $uomParams=[$farmId,$periodStart,$periodEnd];
    if ($farmType !== 'all') { $uomSql .= ' AND farm_type=?'; $uomParams[]=$farmType; }
    $stmt=$pdo->prepare($uomSql); $stmt->execute($uomParams); $legacyUomCount=(int)$stmt->fetchColumn();
    if ($legacyUomCount > 0) {
        $signals[] = farm_intelligence_signal(
            'data-legacy-sales-uom','Data quality','warning','Sales unit is not specified',
            $legacyUomCount.' sale'.($legacyUomCount===1?'':'s'),
            'These sales retain the explicit legacy “Not specified” unit. Quantity intelligence will not guess a physical unit.',
            $periodLabel,'Review sales','management/sales_records.php','bi-rulers'
        );
    }

    // Poultry intelligence deliberately uses the locked unit-economics helper.
    if ($farmType === 'all' || $farmType === 'poultry') {
        foreach (['layer'=>'Layer','broiler'=>'Broiler'] as $productionType=>$label) {
            $pSummary = getProfitabilitySummary($pdo,$farmId,$periodStart,$periodEnd,'poultry',null,$productionType);
            $econ = getPoultryUnitEconomics($pdo,$farmId,$periodStart,$periodEnd,$productionType,$pSummary,null);
            if (($econ['available'] ?? false) && (int)($econ['uncosted_mortality'] ?? 0) > 0) {
                $count=(int)$econ['uncosted_mortality'];
                $signals[] = farm_intelligence_signal(
                    'poultry-'.$productionType.'-mortality-basis','Poultry','warning',$label.' mortality value is incomplete',
                    $count.' mortalit'.($count===1?'y':'ies').' without bird cost basis',
                    'Mortality count remains valid, but management valuation cannot be calculated for birds whose cycle has no Bird Cost Basis.',
                    $periodLabel,'Review production cycles','management/production_cycles.php','bi-egg'
                );
            }
        }

        foreach ([
            ['layer','layer_daily_records','Layer','poultry/layers_daily_record.php'],
            ['broiler','broiler_daily_records','Broiler','poultry/broiler_daily_record.php'],
        ] as [$productionType,$table,$label,$url]) {
            $sql="SELECT pc.cycle_code, MAX(d.record_date) latest_date
                  FROM production_cycles pc
                  LEFT JOIN {$table} d ON d.cycle_id=pc.id AND d.farm_id=pc.farm_id
                  WHERE pc.farm_id=? AND pc.farm_type='poultry' AND LOWER(pc.production_type)=? AND pc.status='active'
                  GROUP BY pc.id,pc.cycle_code";
            $stmt=$pdo->prepare($sql); $stmt->execute([$farmId,$productionType]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $latest=$row['latest_date'] ?? null;
                if (!$latest || $latest < $asOfDate) {
                    $signals[] = farm_intelligence_signal(
                        'poultry-'.$productionType.'-daily-'.md5((string)$row['cycle_code']),'Data quality','warning',$label.' daily record is not current',
                        $latest ? 'Last record: '.date('M j, Y',strtotime($latest)) : 'No daily record',
                        'Active cycle '.$row['cycle_code'].' does not have a daily record for the as-of date. Intelligence will use only recorded days and will not invent missing production.',
                        'As of '.$asOf->format('M j, Y'),'Open '.$label.' daily record',$url,'bi-calendar-x'
                    );
                }
            }
        }
    }

    // Batch 4F — proactive record-quality checks. These use internal
    // consistency only; they do not invent biological targets or silently
    // rewrite the operator's source records.
    if ($farmType === 'all' || $farmType === 'poultry') {
        foreach ([
            ['layer','Layer','poultry/layers_daily_record.php'],
            ['broiler','Broiler','poultry/broiler_daily_record.php'],
        ] as [$productionType,$label,$url]) {
            $ageIssues = record_quality_poultry_age_issues($pdo,$farmId,$productionType,$asOfDate);
            if ($ageIssues) {
                $first=$ageIssues[0];
                $signals[] = farm_intelligence_signal(
                    'data-'.$productionType.'-bird-age-continuity','Data quality','warning',$label.' bird-age sequence needs review',
                    count($ageIssues).' inconsistent transition'.(count($ageIssues)===1?'':'s'),
                    'Recorded bird age does not advance by the elapsed calendar days between consecutive records. Example: '.$first['current_date'].' records '.$first['current_age'].' days; based on the previous record, '.$first['expected_age'].' days would preserve the recorded sequence. The recorded entry is not changed automatically.',
                    'Latest recorded history through '.$asOf->format('M j, Y'),'Review '.$label.' daily records',$url,'bi-calendar2-check'
                );
            }

            $quickNotes = record_quality_poultry_unstructured_medication_notes($pdo,$farmId,$productionType,$periodStart,$periodEnd);
            if ($quickNotes) {
                $first=$quickNotes[0];
                $signals[] = farm_intelligence_signal(
                    'data-'.$productionType.'-unstructured-health-notes','Data quality','info',$label.' medication notes are not in structured health history',
                    count($quickNotes).' daily note'.(count($quickNotes)===1?'':'s'),
                    'Daily Record medication text remains valid as a quick note, but no same-cycle structured Health & Treatment event exists on the recorded date. Structured history improves later investigation context. Latest example: '.$first['record_date'].'.',
                    $periodLabel,'Review Health & Treatment','poultry/health.php?type='.$productionType,'bi-journal-medical'
                );
            }
        }
    }

    // Batch 3 — operational performance intelligence. Comparisons use the
    // farm's own recorded history; no breed/industry benchmark is invented.
    if ($farmType === 'all' || $farmType === 'poultry') {
        // Layer performance: keep laying-rate efficiency, absolute egg output and
        // flock survival separate. A stable laying rate can otherwise hide
        // a serious production-volume loss when mortality reduces the denominator.
        $stmt=$pdo->prepare("SELECT pc.id cycle_id,pc.cycle_code,d.record_date,d.opening_stock,d.mortality,d.egg_production,d.laying_rate
                            FROM production_cycles pc
                            JOIN layer_daily_records d ON d.cycle_id=pc.id AND d.farm_id=pc.farm_id
                            WHERE pc.farm_id=? AND pc.farm_type='poultry' AND LOWER(pc.production_type)='layer'
                              AND pc.status='active' AND d.record_date<=?
                            ORDER BY pc.id,d.record_date DESC,d.id DESC");
        $stmt->execute([$farmId,$asOfDate]);
        $byCycle=[];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cid=(int)$row['cycle_id'];
            if (!isset($byCycle[$cid])) $byCycle[$cid]=['code'=>(string)$row['cycle_code'],'rows'=>[]];
            if (count($byCycle[$cid]['rows']) < 14) $byCycle[$cid]['rows'][]=$row;
        }
        foreach ($byCycle as $cid=>$data) {
            if (count($data['rows']) < 14) continue;
            $recent=array_slice($data['rows'],0,7); $priorRows=array_slice($data['rows'],7,7);
            $avg=function(array $rows,string $field): float { return array_sum(array_map(fn($r)=>(float)$r[$field],$rows))/max(1,count($rows)); };
            $recentRate=$avg($recent,'laying_rate'); $priorRate=$avg($priorRows,'laying_rate'); $rateDelta=$recentRate-$priorRate;
            $recentEgg=$avg($recent,'egg_production'); $priorEgg=$avg($priorRows,'egg_production');
            $eggDelta=$priorEgg>0?(($recentEgg-$priorEgg)/$priorEgg)*100:0.0;
            $mortRate=function(array $rows): float { $open=0.0;$mort=0.0;foreach($rows as $r){$open+=(float)$r['opening_stock'];$mort+=(float)$r['mortality'];}return $open>0?($mort/$open)*100:0.0; };
            $recentMort=$mortRate($recent); $priorMort=$mortRate($priorRows); $mortDelta=$recentMort-$priorMort;

            if (abs($rateDelta) >= 5.0) {
                $improved=$rateDelta>0;
                $signals[] = farm_intelligence_signal(
                    'poultry-layer-rate-trend-'.$cid,'Poultry',$improved?'success':'warning',
                    $improved?'Layer laying rate improved':'Layer laying rate declined',
                    number_format($recentRate,1).'% recent avg · '.($rateDelta>=0?'+':'−').number_format(abs($rateDelta),1).' pp',
                    'The latest 7 recorded days are compared with the preceding 7 recorded days for active cycle '.$data['code'].'. Laying rate measures efficiency per available bird and is kept separate from flock survival and absolute egg volume. This is a farm-history comparison, not an external production benchmark.',
                    'Latest 14 recorded days','Investigate','management/investigation.php?type=layer&issue=laying_decline&cycle_id='.$cid.'&as_of='.urlencode($asOfDate),'bi-search'
                );
            }
            if ($mortDelta >= 0.5 || ($eggDelta <= -8.0 && $recentMort > $priorMort)) {
                $signals[] = farm_intelligence_signal(
                    'poultry-layer-flock-deterioration-'.$cid,'Poultry','warning','Layer flock performance needs investigation',
                    number_format($recentMort,2).'% mortality · '.($eggDelta>=0?'+':'−').number_format(abs($eggDelta),1).'% egg output',
                    'Mortality/flock survival and absolute egg output changed materially in the latest 7 recorded days for active cycle '.$data['code'].'. This signal remains meaningful even when laying rate stays relatively stable because a shrinking flock can mask lost production volume.',
                    'Latest 14 recorded days','Investigate','management/investigation.php?type=layer&issue=mortality&cycle_id='.$cid.'&as_of='.urlencode($asOfDate),'bi-search'
                );
            }
        }

        // Broiler mortality trend uses the same recorded-day comparison and
        // weights mortality by opening flock rather than averaging daily rates.
        $stmt=$pdo->prepare("SELECT pc.id cycle_id,pc.cycle_code,d.record_date,d.opening_stock,d.mortality
                            FROM production_cycles pc
                            JOIN broiler_daily_records d ON d.cycle_id=pc.id AND d.farm_id=pc.farm_id
                            WHERE pc.farm_id=? AND pc.farm_type='poultry' AND LOWER(pc.production_type)='broiler'
                              AND pc.status='active' AND d.record_date<=?
                            ORDER BY pc.id,d.record_date DESC,d.id DESC");
        $stmt->execute([$farmId,$asOfDate]); $byCycle=[];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cid=(int)$row['cycle_id'];
            if (!isset($byCycle[$cid])) $byCycle[$cid]=['code'=>(string)$row['cycle_code'],'rows'=>[]];
            if (count($byCycle[$cid]['rows']) < 14) $byCycle[$cid]['rows'][]=$row;
        }
        foreach ($byCycle as $cid=>$data) {
            if (count($data['rows']) < 14) continue;
            $rate=function(array $rows): float { $open=0.0;$mort=0.0; foreach($rows as $r){$open+=(float)$r['opening_stock'];$mort+=(float)$r['mortality'];} return $open>0?($mort/$open)*100:0.0; };
            $recent=$rate(array_slice($data['rows'],0,7)); $priorRate=$rate(array_slice($data['rows'],7,7)); $delta=$recent-$priorRate;
            if ($delta >= 0.5) {
                $signals[] = farm_intelligence_signal(
                    'poultry-broiler-mortality-trend-'.$cid,'Poultry','warning','Broiler mortality rate increased',
                    number_format($recent,2).'% recent · +'.number_format($delta,2).' pp',
                    'Mortality across the latest 7 recorded days is higher than the preceding 7 recorded days for active cycle '.$data['code'].'. Rates are weighted by recorded opening flock and are not compared with an external benchmark.',
                    'Latest 14 recorded days','Investigate','management/investigation.php?type=broiler&issue=mortality&cycle_id='.$cid.'&as_of='.urlencode($asOfDate),'bi-search'
                );
            }
        }
    }

    // Inventory usage velocity is an estimate from recorded effective USED
    // transactions. It is deliberately labelled as an estimate and never
    // replaces the configured minimum-stock control.
    $velocityStart=$asOf->modify('-13 days')->format('Y-m-d');
    $velocitySql="SELECT s.id,s.item_name,s.current_stock,s.min_stock_level,s.unit,COALESCE(SUM(t.quantity),0) used_qty
                  FROM stock_items s
                  JOIN stock_transactions t ON t.stock_item_id=s.id AND t.farm_id=s.farm_id
                  WHERE s.farm_id=? AND s.is_active=1 AND t.transaction_type='used' AND ".stock_effective_sql_predicate()."
                    AND t.transaction_date BETWEEN ? AND ?";
    $velocityParams=[$farmId,$velocityStart,$asOfDate];
    if ($farmType !== 'all') { $velocitySql.=" AND s.farm_type IN (?, 'both')"; $velocityParams[]=$farmType; }
    $velocitySql.=" GROUP BY s.id,s.item_name,s.current_stock,s.min_stock_level,s.unit HAVING used_qty>0";
    $stmt=$pdo->prepare($velocitySql); $stmt->execute($velocityParams); $shortCover=[];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $daily=(float)$row['used_qty']/14.0; $stock=(float)$row['current_stock']; $min=(float)$row['min_stock_level'];
        if ($daily<=0 || $stock<=$min) continue; // minimum-stock danger already explains these items
        $days=$stock/$daily;
        if ($days<=7.0) $shortCover[]=['name'=>(string)$row['item_name'],'days'=>$days,'unit'=>(string)$row['unit']];
    }
    if ($shortCover) {
        usort($shortCover,static fn($a,$b)=>$a['days']<=>$b['days']); $first=$shortCover[0];
        $signals[] = farm_intelligence_signal(
            'inventory-short-cover','Inventory','warning','Recorded usage suggests short stock cover',
            count($shortCover).' item'.(count($shortCover)===1?'':'s').' · nearest '.number_format($first['days'],1).' days',
            'Estimate uses effective USED quantity recorded during the last 14 calendar days and current stock. It is a planning signal, not a guaranteed run-out date. Nearest item: '.$first['name'].'.',
            $velocityStart.' to '.$asOfDate,'Review inventory','inventory.php','bi-hourglass-split'
        );
    }

    // Receivables are cash-collection intelligence, not revenue. Only show this
    // at all-farm scope because legacy/customer ledger entries are not reliably
    // production-type scoped.
    if ($farmType === 'all') {
        try {
            $stmt=$pdo->prepare("SELECT customer_name,SUM(amount) balance FROM customer_ledger_entries WHERE farm_id=? GROUP BY customer_name HAVING balance>0.005 ORDER BY balance DESC");
            $stmt->execute([$farmId]); $balances=$stmt->fetchAll(PDO::FETCH_ASSOC); $outstanding=0.0;
            foreach($balances as $r) $outstanding+=(float)$r['balance'];
            if ($outstanding>0.005) {
                $largest=$balances[0] ?? null;
                $reason='Recorded customer-ledger balances remain collectible and are kept separate from recognised revenue.';
                if ($largest) $reason.=' Largest current balance: '.$largest['customer_name'].' at ₦'.number_format((float)$largest['balance'],2).'.';
                $signals[] = farm_intelligence_signal(
                    'receivables-outstanding','Receivables','info','Customer balances are outstanding',
                    '₦'.number_format($outstanding,2).' · '.count($balances).' customer'.(count($balances)===1?'':'s'),
                    $reason,
                    'Current ledger balance','Review sales & receivables','management/sales_records.php','bi-cash-coin'
                );
            }
        } catch (Throwable $e) { /* legacy installs without debt ledger remain usable */ }
    }

    // Ruminant lifecycle/data-quality intelligence uses dated membership and
    // health history. Current status alone is never used to invent an exit date.
    if ($farmType === 'all' || $farmType === 'ruminant') {
        $stmt=$pdo->prepare("SELECT COUNT(*) FROM ruminant_animals a
                            WHERE a.farm_id=? AND a.status='active'
                              AND NOT EXISTS (
                                  SELECT 1 FROM ruminant_animal_cycle_memberships m
                                  WHERE m.farm_id=a.farm_id AND m.animal_id=a.id
                                    AND m.start_date<=? AND (m.end_date IS NULL OR m.end_date>=?)
                              )");
        $stmt->execute([$farmId,$asOfDate,$asOfDate]); $missingMembership=(int)$stmt->fetchColumn();
        if ($missingMembership > 0) {
            $signals[] = farm_intelligence_signal(
                'ruminant-membership-coverage','Ruminant','warning','Active animals lack current cycle membership',
                $missingMembership.' animal'.($missingMembership===1?'':'s'),
                'Shared-cost economics cannot defensibly allocate species/cycle operating cost to an animal without dated cycle membership coverage.',
                'As of '.$asOf->format('M j, Y'),'Review animal registry','ruminant/animal_registry.php','bi-diagram-3'
            );
        }

        $stmt=$pdo->prepare("SELECT COUNT(*) FROM ruminant_animal_cycle_memberships m
                            JOIN ruminant_animals a ON a.id=m.animal_id AND a.farm_id=m.farm_id
                            WHERE m.farm_id=? AND a.status<>'active' AND m.end_date IS NULL");
        $stmt->execute([$farmId]); $openExitedMemberships=(int)$stmt->fetchColumn();
        if ($openExitedMemberships > 0) {
            $signals[] = farm_intelligence_signal(
                'ruminant-open-exit-membership','Data quality','warning','Exited animals still have open membership history',
                $openExitedMemberships.' membership'.($openExitedMemberships===1?'':'s'),
                'Lifecycle-aware economics can still use dated exit events, but closing stale membership history improves audit clarity and prevents future ambiguity.',
                'Current registry state','Review memberships','management/ruminant_membership_integrity.php','bi-shield-exclamation'
            );
        }

        $stmt=$pdo->prepare("SELECT COUNT(DISTINCT h.animal_id) animal_count, MIN(h.withdrawal_until) nearest_until
                            FROM ruminant_health_events h
                            JOIN ruminant_animals a ON a.id=h.animal_id AND a.farm_id=h.farm_id
                            WHERE h.farm_id=? AND a.status='active' AND h.withdrawal_until IS NOT NULL AND h.withdrawal_until>=?");
        $stmt->execute([$farmId,$asOfDate]); $withdrawal=$stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $withdrawalCount=(int)($withdrawal['animal_count'] ?? 0);
        if ($withdrawalCount > 0) {
            $nearest=$withdrawal['nearest_until'] ? date('M j, Y',strtotime($withdrawal['nearest_until'])) : 'recorded date';
            $signals[] = farm_intelligence_signal(
                'ruminant-withdrawal','Ruminant','warning','Animals are inside a recorded withdrawal period',
                $withdrawalCount.' active animal'.($withdrawalCount===1?'':'s').' · nearest end '.$nearest,
                'A health record has an active withdrawal-until date. This is a management reminder from recorded treatment data; the platform does not invent product-specific withdrawal rules.',
                'As of '.$asOf->format('M j, Y'),'Review animal registry','ruminant/animal_registry.php','bi-capsule'
            );
        }
    }

    // Ruminant weight intelligence compares each active animal only with its
    // own previous recorded weight. It does not invent breed growth targets.
    if ($farmType === 'all' || $farmType === 'ruminant') {
        $stmt=$pdo->prepare("SELECT a.id,a.tag_no,w.weight_date,w.weight_kg
                            FROM ruminant_animals a
                            LEFT JOIN ruminant_animal_weights w ON w.animal_id=a.id AND w.farm_id=a.farm_id AND w.weight_date<=?
                            WHERE a.farm_id=? AND a.status='active'
                            ORDER BY a.id,w.weight_date DESC,w.id DESC");
        $stmt->execute([$asOfDate,$farmId]); $weightMap=[];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
            $id=(int)$row['id']; if(!isset($weightMap[$id]))$weightMap[$id]=['id'=>$id,'tag'=>(string)$row['tag_no'],'weights'=>[]];
            if($row['weight_date']!==null && count($weightMap[$id]['weights'])<2)$weightMap[$id]['weights'][]=['date'=>$row['weight_date'],'kg'=>(float)$row['weight_kg']];
        }
        $declines=[];$unweighed=0;
        foreach($weightMap as $data){
            if(count($data['weights'])===0){$unweighed++;continue;}
            if(count($data['weights'])<2)continue;
            $latest=$data['weights'][0];$previous=$data['weights'][1];$delta=$latest['kg']-$previous['kg'];
            if($delta<0)$declines[]=['animal_id'=>$data['id']??0,'tag'=>$data['tag'],'delta'=>$delta,'latest'=>$latest,'previous'=>$previous];
        }
        if($declines){
            usort($declines,static fn($a,$b)=>$a['delta']<=>$b['delta']);$d=$declines[0];
            $rapidByAnimal=[];
            foreach(record_quality_ruminant_weight_jumps($pdo,$farmId,$asOfDate,15.0,7) as $jump){$rapidByAnimal[(int)$jump['animal_id']]=$jump;}
            $rapid=$rapidByAnimal[(int)($d['animal_id']??0)] ?? null;
            $reason='Latest recorded weight is below the immediately previous recorded weight for one or more active animals. Largest decline: '.$d['tag'].'. This compares the animal with its own history and does not diagnose a cause.';
            $title='Recorded weight decline needs review';
            if($rapid){
                $title='Recorded weight decline needs verification';
                $reason.=' The largest decline is also a rapid recorded movement of −'.number_format(abs((float)$rapid['pct']),1).'% across '.(int)$rapid['days'].' day'.((int)$rapid['days']===1?'':'s').'; verify the weighing method/entry before treating it as biological change.';
            }
            $signals[]=farm_intelligence_signal(
                'ruminant-weight-decline','Ruminant','warning',$title,
                count($declines).' animal'.(count($declines)===1?'':'s').' · largest −'.number_format(abs($d['delta']),1).' kg',
                $reason,
                'Weights recorded through '.$asOf->format('M j, Y'),'Investigate','management/ruminant_investigation.php?animal_id='.(int)$d['animal_id'].'&as_of='.urlencode($asOfDate),'bi-activity'
            );
        }
        if($unweighed>0){
            $signals[]=farm_intelligence_signal(
                'ruminant-weight-coverage','Data quality','info','Active animals have no weight history',
                $unweighed.' animal'.($unweighed===1?'':'s'),
                'Weight-based management trends are unavailable for these active registry animals until at least one weight is recorded.',
                'As of '.$asOf->format('M j, Y'),'Review animal registry','ruminant/animal_registry.php','bi-speedometer2'
            );
        }
    }

    // Batch 4H: show management follow-through state without suppressing a still-current source condition.
    $signals=investigation_followup_annotate_signals($pdo,$farmId,$signals,$asOfDate);

    $priority=['danger'=>1,'warning'=>2,'info'=>3,'success'=>4];
    usort($signals, static function(array $a,array $b) use ($priority): int {
        $pa=$priority[$a['severity']] ?? 99; $pb=$priority[$b['severity']] ?? 99;
        if ($pa!==$pb) return $pa<=>$pb;
        return strcmp($a['category'],$b['category']);
    });

    $counts=['danger'=>0,'warning'=>0,'info'=>0,'success'=>0];
    $categoryCounts=[];
    foreach ($signals as $signal) {
        $counts[$signal['severity']]++;
        $categoryCounts[$signal['category']] = ($categoryCounts[$signal['category']] ?? 0) + 1;
    }
    $actionCount=$counts['danger']+$counts['warning'];
    if ($counts['danger']>0) { $status='Critical attention'; $statusClass='danger'; }
    elseif ($counts['warning']>0) { $status='Needs attention'; $statusClass='warning'; }
    elseif ($counts['info']>0) { $status='Monitor'; $statusClass='info'; }
    else { $status='Stable'; $statusClass='success'; }

    return [
        'status'=>$status,
        'status_class'=>$statusClass,
        'action_count'=>$actionCount,
        'counts'=>$counts,
        'category_counts'=>$categoryCounts,
        'signals'=>$signals,
        'current_period'=>['start'=>$periodStart,'end'=>$periodEnd,'label'=>$periodLabel,'financial'=>$current],
        'comparison_period'=>['start'=>$priorStart,'end'=>$priorEnd,'label'=>$comparisonLabel,'financial'=>$prior],
        'method'=>'Farm Intelligence highlights recorded conditions that may need attention and links each insight to the records used for review.',
    ];
}}
