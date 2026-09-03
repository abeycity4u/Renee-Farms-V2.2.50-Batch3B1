<?php
/**
 * V2.2.49 Batch 4F — proactive record-quality intelligence.
 *
 * These helpers inspect the farm's own recorded history for internal
 * inconsistencies or measurements that deserve verification. They do not
 * invent biological targets and they do not rewrite source records.
 */

if (!function_exists('record_quality_poultry_age_issues')) {
function record_quality_poultry_age_issues(PDO $pdo, int $farmId, string $productionType, string $asOfDate, int $limitPerCycle = 21): array
{
    $productionType = strtolower($productionType);
    if (!in_array($productionType, ['layer','broiler'], true)) return [];
    $table = $productionType === 'layer' ? 'layer_daily_records' : 'broiler_daily_records';

    $sql = "SELECT pc.id cycle_id,pc.cycle_code,d.id,d.record_date,d.birds_age
            FROM production_cycles pc
            JOIN {$table} d ON d.cycle_id=pc.id AND d.farm_id=pc.farm_id
            WHERE pc.farm_id=? AND pc.farm_type='poultry' AND LOWER(pc.production_type)=?
              AND pc.status='active' AND d.record_date<=?
            ORDER BY pc.id,d.record_date DESC,d.id DESC";
    $stmt=$pdo->prepare($sql); $stmt->execute([$farmId,$productionType,$asOfDate]);
    $byCycle=[];
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
        $cid=(int)$row['cycle_id'];
        if(!isset($byCycle[$cid])) $byCycle[$cid]=['code'=>(string)$row['cycle_code'],'rows'=>[]];
        if(count($byCycle[$cid]['rows'])<$limitPerCycle) $byCycle[$cid]['rows'][]=$row;
    }

    $issues=[];
    foreach($byCycle as $cid=>$data){
        $rows=array_reverse($data['rows']);
        for($i=1;$i<count($rows);$i++){
            $prev=$rows[$i-1]; $curr=$rows[$i];
            $prevAge=(int)$prev['birds_age']; $currAge=(int)$curr['birds_age'];
            $days=(int)((strtotime((string)$curr['record_date'])-strtotime((string)$prev['record_date']))/86400);
            if($days<=0) continue;
            $expected=$prevAge+$days;
            if($currAge!==$expected){
                $issues[]=[
                    'cycle_id'=>$cid,'cycle_code'=>$data['code'],'production_type'=>$productionType,
                    'previous_date'=>(string)$prev['record_date'],'current_date'=>(string)$curr['record_date'],
                    'previous_age'=>$prevAge,'current_age'=>$currAge,'expected_age'=>$expected,
                ];
            }
        }
    }
    return $issues;
}}

if (!function_exists('record_quality_poultry_unstructured_medication_notes')) {
function record_quality_poultry_unstructured_medication_notes(PDO $pdo, int $farmId, string $productionType, string $periodStart, string $periodEnd): array
{
    $productionType=strtolower($productionType);
    if(!in_array($productionType,['layer','broiler'],true)) return [];
    $table=$productionType==='layer'?'layer_daily_records':'broiler_daily_records';

    $sql="SELECT d.id,d.record_date,d.cycle_id,pc.cycle_code,d.medications
          FROM {$table} d
          JOIN production_cycles pc ON pc.id=d.cycle_id AND pc.farm_id=d.farm_id
          WHERE d.farm_id=? AND d.record_date BETWEEN ? AND ?
            AND d.medications IS NOT NULL AND TRIM(d.medications)<>''
            AND TRIM(d.medications)<>'--'
            AND NOT EXISTS (
                SELECT 1 FROM poultry_health_events h
                WHERE h.farm_id=d.farm_id AND h.cycle_id=d.cycle_id
                  AND h.event_date=d.record_date AND LOWER(h.production_type)=?
            )
          ORDER BY d.record_date DESC,d.id DESC";
    $stmt=$pdo->prepare($sql); $stmt->execute([$farmId,$periodStart,$periodEnd,$productionType]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}}

if (!function_exists('record_quality_ruminant_weight_jumps')) {
function record_quality_ruminant_weight_jumps(PDO $pdo, int $farmId, string $asOfDate, float $pctThreshold = 15.0, int $maxDays = 7): array
{
    $stmt=$pdo->prepare("SELECT a.id,a.tag_no,w.id weight_id,w.weight_date,w.weight_kg
                         FROM ruminant_animals a
                         JOIN ruminant_animal_weights w ON w.animal_id=a.id AND w.farm_id=a.farm_id
                         WHERE a.farm_id=? AND a.status='active' AND w.weight_date<=?
                         ORDER BY a.id,w.weight_date DESC,w.id DESC");
    $stmt->execute([$farmId,$asOfDate]);
    $byAnimal=[];
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
        $id=(int)$row['id'];
        if(!isset($byAnimal[$id]))$byAnimal[$id]=['tag'=>(string)$row['tag_no'],'rows'=>[]];
        if(count($byAnimal[$id]['rows'])<2)$byAnimal[$id]['rows'][]=$row;
    }

    $issues=[];
    foreach($byAnimal as $animalId=>$data){
        if(count($data['rows'])<2)continue;
        $latest=$data['rows'][0]; $previous=$data['rows'][1];
        $prev=(float)$previous['weight_kg']; $latestKg=(float)$latest['weight_kg'];
        if($prev<=0)continue;
        $days=(int)((strtotime((string)$latest['weight_date'])-strtotime((string)$previous['weight_date']))/86400);
        if($days<0 || $days>$maxDays)continue;
        $pct=(($latestKg-$prev)/$prev)*100.0;
        if(abs($pct)+0.00001<$pctThreshold)continue;
        $issues[]=[
            'animal_id'=>$animalId,'tag'=>$data['tag'],'days'=>$days,'pct'=>$pct,
            'previous_date'=>(string)$previous['weight_date'],'previous_kg'=>$prev,
            'latest_date'=>(string)$latest['weight_date'],'latest_kg'=>$latestKg,
        ];
    }
    usort($issues,static fn($a,$b)=>abs($b['pct'])<=>abs($a['pct']));
    return $issues;
}}
