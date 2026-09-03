<?php
require_once __DIR__.'/ruminant_cycle_membership.php';
require_once __DIR__.'/inventory_financial.php';
require_once __DIR__.'/stock_reporting.php';

/**
 * Analytical allocation of shared ruminant operating costs.
 *
 * Standard driver: equal active headcount on each source transaction date.
 * Across a reporting period this naturally becomes animal-days: an animal only
 * receives a share on dates it is explicitly a member of the relevant cycle.
 * No weight interpolation or hidden lifecycle inference is performed.
 */
function ruminant_shared_cost_share_for_target(float $poolAmount, array $eligibleAnimalIds, int $targetAnimalId): float
{
    $eligibleAnimalIds=array_values(array_unique(array_map('intval',$eligibleAnimalIds)));
    sort($eligibleAnimalIds,SORT_NUMERIC);
    $idx=array_search($targetAnimalId,$eligibleAnimalIds,true);
    if($idx===false || !$eligibleAnimalIds) return 0.0;
    $totalCents=(int)round(round($poolAmount,2)*100);
    if($totalCents<=0) return 0.0;
    $count=count($eligibleAnimalIds);
    $base=intdiv($totalCents,$count);
    $remainder=$totalCents-($base*$count);
    return ($base + ($idx < $remainder ? 1 : 0))/100;
}

function ruminant_shared_cost_economics(PDO $pdo, int $farmId, int $animalId, string $species): array
{
    $species=strtolower(trim($species));
    $rows=[];

    // Shared manual/non-stock expenses at species or species-cycle level only.
    // Expense rows already explicitly allocated to animals are excluded entirely.
    $expenseSql="SELECT e.id source_id,e.expense_date source_date,'expense' source_type,
                       COALESCE(NULLIF(e.description,''),e.category) source_label,
                       e.category classification,(e.amount*e.unit) pool_amount,e.cycle_id,pc.cycle_code,
                       LOWER(COALESCE(pc.production_type,e.production_type,'')) production_type
                FROM farm_expenses e
                LEFT JOIN production_cycles pc ON pc.id=e.cycle_id AND pc.farm_id=e.farm_id
                WHERE e.farm_id=? AND e.farm_type='ruminant' AND e.category<>'feeds'
                  AND LOWER(COALESCE(pc.production_type,e.production_type,''))=?
                  AND NOT EXISTS (SELECT 1 FROM ruminant_expense_animal_allocations ra WHERE ra.farm_id=e.farm_id AND ra.expense_id=e.id)";
    $stmt=$pdo->prepare($expenseSql); $stmt->execute([$farmId,$species]);
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $rows[]=$r;

    // Explicit financial allocation of a pooled expense to a species cycle.
    $allocSql="SELECT e.id source_id,e.expense_date source_date,'allocated_expense' source_type,
                     COALESCE(NULLIF(e.description,''),e.category) source_label,
                     e.category classification,fa.allocated_amount pool_amount,fa.cycle_id,pc.cycle_code,
                     LOWER(pc.production_type) production_type
              FROM financial_allocations fa
              JOIN farm_expenses e ON e.id=fa.expense_id AND e.farm_id=fa.farm_id
              JOIN production_cycles pc ON pc.id=fa.cycle_id AND pc.farm_id=fa.farm_id
              WHERE fa.farm_id=? AND e.cycle_id IS NULL AND e.category<>'feeds'
                AND pc.farm_type='ruminant' AND LOWER(pc.production_type)=?
                AND NOT EXISTS (SELECT 1 FROM ruminant_expense_animal_allocations ra WHERE ra.farm_id=e.farm_id AND ra.expense_id=e.id)";
    $stmt=$pdo->prepare($allocSql); $stmt->execute([$farmId,$species]);
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $rows[]=$r;

    // Shared operating inventory actually USED: feed + eligible non-feed operating inventory.
    $effective=stock_effective_sql_predicate('t');
    $allowed=array_merge(['feed'],array_keys(inventory_operating_consumption_classifications()));
    $ph=implode(',',array_fill(0,count($allowed),'?'));
    $stockSql="SELECT t.id source_id,t.transaction_date source_date,'inventory_use' source_type,
                     s.item_name source_label,t.financial_classification classification,t.total_cost pool_amount,
                     t.cycle_id,pc.cycle_code,LOWER(COALESCE(pc.production_type,t.production_type,'')) production_type
              FROM stock_transactions t
              JOIN stock_items s ON s.id=t.stock_item_id AND s.farm_id=t.farm_id
              LEFT JOIN production_cycles pc ON pc.id=t.cycle_id AND pc.farm_id=t.farm_id
              WHERE t.farm_id=? AND t.farm_type='ruminant' AND t.transaction_type='used'
                AND {$effective} AND t.total_cost IS NOT NULL
                AND t.financial_classification IN ({$ph})
                AND LOWER(COALESCE(pc.production_type,t.production_type,''))=?";
    $params=array_merge([$farmId],$allowed,[$species]);
    $stmt=$pdo->prepare($stockSql); $stmt->execute($params);
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $rows[]=$r;

    usort($rows,static function($a,$b){
        $d=strcmp((string)$a['source_date'],(string)$b['source_date']);
        if($d!==0)return $d;
        $t=strcmp((string)$a['source_type'],(string)$b['source_type']);
        return $t!==0?$t:((int)$a['source_id']<=> (int)$b['source_id']);
    });

    $allocated=[]; $total=0.0; $eligiblePool=0.0;
    foreach($rows as $r){
        $amount=round((float)$r['pool_amount'],2);
        if($amount<=0) continue;
        $eligible=ruminant_cycle_eligible_animal_ids($pdo,$farmId,$species,(string)$r['source_date'],!empty($r['cycle_id'])?(int)$r['cycle_id']:null);
        if(!$eligible) continue; // visible as uncovered below
        $eligiblePool+=$amount;
        $share=ruminant_shared_cost_share_for_target($amount,$eligible,$animalId);
        if($share<=0) continue;
        $r['eligible_animal_count']=count($eligible);
        $r['allocated_amount']=$share;
        $r['allocation_method']='Active headcount on transaction date';
        $allocated[]=$r;
        $total+=$share;
    }

    // Amounts in the animal's species cost centre that cannot be allocated to
    // any animal because no explicit membership covers the transaction date.
    $uncovered=0.0;
    foreach($rows as $r){
        $amount=round((float)$r['pool_amount'],2);
        if($amount<=0) continue;
        $eligible=ruminant_cycle_eligible_animal_ids($pdo,$farmId,$species,(string)$r['source_date'],!empty($r['cycle_id'])?(int)$r['cycle_id']:null);
        if(!$eligible) $uncovered+=$amount;
    }

    return [
        'allocated_shared_cost'=>round($total,2),
        'shared_cost_rows'=>$allocated,
        'uncovered_species_shared_cost'=>round($uncovered,2),
        'eligible_species_pool'=>round($eligiblePool,2),
        'method'=>'Active headcount on each transaction date',
    ];
}
