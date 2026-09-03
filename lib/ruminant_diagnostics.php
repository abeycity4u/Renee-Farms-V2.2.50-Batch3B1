<?php
require_once __DIR__ . '/ruminant_cycle_membership.php';
require_once __DIR__ . '/stock_reporting.php';

/**
 * V2.2.49 Batch 4E — evidence-led ruminant diagnostic intelligence.
 *
 * The engine investigates an individual animal using only recorded evidence.
 * It does not diagnose disease, invent breed targets, infer individual feed
 * consumption from herd stock use, or prescribe treatment.
 */

if (!function_exists('ruminant_diagnostic_evidence')) {
function ruminant_diagnostic_evidence(string $type, string $title, string $detail): array
{
    return ['type'=>$type,'title'=>$title,'detail'=>$detail];
}}

if (!function_exists('ruminant_diagnostic_investigate_weight')) {
function ruminant_diagnostic_investigate_weight(PDO $pdo, int $farmId, int $animalId, ?string $asOfDate = null): array
{
    $asOfDate = $asOfDate ?: date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOfDate)) {
        throw new InvalidArgumentException('Enter a valid investigation date.');
    }

    $stmt=$pdo->prepare("SELECT id,tag_no,species,breed,sex,status,purchase_date,purchase_cost
                         FROM ruminant_animals WHERE id=? AND farm_id=? LIMIT 1");
    $stmt->execute([$animalId,$farmId]);
    $animal=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$animal) throw new RuntimeException('Animal not found.');

    $w=$pdo->prepare("SELECT id,weight_date,weight_kg,notes
                      FROM ruminant_animal_weights
                      WHERE farm_id=? AND animal_id=? AND weight_date<=?
                      ORDER BY weight_date DESC,id DESC LIMIT 8");
    $w->execute([$farmId,$animalId,$asOfDate]);
    $weights=$w->fetchAll(PDO::FETCH_ASSOC);
    if(count($weights)<2) throw new RuntimeException('At least two recorded weights are required for a weight-change investigation.');

    $latest=$weights[0]; $previous=$weights[1];
    $latestKg=(float)$latest['weight_kg']; $previousKg=(float)$previous['weight_kg'];
    $delta=round($latestKg-$previousKg,2);
    $deltaPct=$previousKg>0 ? round(($delta/$previousKg)*100,2) : null;
    $from=(string)$previous['weight_date']; $to=(string)$latest['weight_date'];
    if($from>$to){$tmp=$from;$from=$to;$to=$tmp;}

    $evidence=[]; $missing=[]; $timeline=[];
    $addTimeline=static function(string $date,string $type,string $title,string $detail) use (&$timeline): void {
        $timeline[]=['date'=>$date,'type'=>$type,'title'=>$title,'detail'=>$detail];
    };

    $direction=$delta<0?'declined':($delta>0?'increased':'was unchanged');
    $measure=($delta<0?'−':($delta>0?'+':'')) . number_format(abs($delta),1).' kg';
    if($deltaPct!==null) $measure.=' · '.($deltaPct>=0?'+':'−').number_format(abs($deltaPct),1).'%';
    $evidence[]=ruminant_diagnostic_evidence(
        'observed','Recorded weight movement',
        'Weight '.$direction.' from '.number_format($previousKg,1).' kg on '.date('M j, Y',strtotime($previous['weight_date'])).' to '.number_format($latestKg,1).' kg on '.date('M j, Y',strtotime($latest['weight_date'])).' ('.$measure.'). This compares the animal only with its own recorded history.'
    );
    $weightGapDays=(int)((strtotime((string)$latest['weight_date'])-strtotime((string)$previous['weight_date']))/86400);
    $rapidMeasurement=($deltaPct!==null && abs((float)$deltaPct)>=15.0 && $weightGapDays>=0 && $weightGapDays<=7);
    if($rapidMeasurement){
        $evidence[]=ruminant_diagnostic_evidence(
            'possible','Weight measurement needs verification',
            'The recorded weight changed by '.number_format(abs((float)$deltaPct),1).'% across '.$weightGapDays.' calendar day'.($weightGapDays===1?'':'s').'. This may be a real animal change or a weighing/entry inconsistency. Verify the scale, weighing method and source entry before interpreting the movement biologically.'
        );
    }
    $addTimeline((string)$previous['weight_date'],'weight','Previous weight recorded',number_format($previousKg,1).' kg'.(!empty($previous['notes'])?' · '.trim((string)$previous['notes']):''));
    $addTimeline((string)$latest['weight_date'],'weight','Latest weight recorded',number_format($latestKg,1).' kg'.(!empty($latest['notes'])?' · '.trim((string)$latest['notes']):''));

    // Membership is dated evidence of which production cycle the animal belonged to.
    $m=$pdo->prepare("SELECT m.id,m.cycle_id,m.start_date,m.end_date,pc.cycle_code,LOWER(pc.production_type) production_type
                      FROM ruminant_animal_cycle_memberships m
                      JOIN production_cycles pc ON pc.id=m.cycle_id AND pc.farm_id=m.farm_id
                      WHERE m.farm_id=? AND m.animal_id=?
                        AND m.start_date<=? AND (m.end_date IS NULL OR m.end_date>=?)
                      ORDER BY m.start_date DESC,m.id DESC LIMIT 1");
    $m->execute([$farmId,$animalId,$to,$to]);
    $membership=$m->fetch(PDO::FETCH_ASSOC) ?: null;
    if($membership){
        $evidence[]=ruminant_diagnostic_evidence('context','Production cycle context','Latest weight falls inside recorded membership for '.$membership['cycle_code'].'. Cycle membership is used only to scope herd-level context; it does not prove an individual animal consumed a particular feed quantity.');
    } else {
        $missing[]='No production-cycle membership covers the latest recorded weight date. Herd/cycle feed and operating context cannot be scoped confidently for this animal.';
    }

    // Individual structured health history between the two weight observations.
    $h=$pdo->prepare("SELECT id,event_date,event_type,description,medicine,dosage,withdrawal_until
                      FROM ruminant_health_events
                      WHERE farm_id=? AND animal_id=? AND event_date BETWEEN ? AND ?
                      ORDER BY event_date,id");
    $h->execute([$farmId,$animalId,$from,$to]);
    $health=$h->fetchAll(PDO::FETCH_ASSOC);
    foreach($health as $row){
        $label=ucwords(str_replace('_',' ',(string)$row['event_type']));
        $parts=[]; if(trim((string)($row['medicine']??''))!=='')$parts[]=trim((string)$row['medicine']);
        if(trim((string)($row['dosage']??''))!=='')$parts[]='Dose '.trim((string)$row['dosage']);
        if(trim((string)($row['description']??''))!=='')$parts[]=trim((string)$row['description']);
        if(!empty($row['withdrawal_until']))$parts[]='Withdrawal until '.date('M j, Y',strtotime($row['withdrawal_until']));
        $detail=$label.($parts?' · '.implode(' · ',$parts):'').'. Recorded health context; not proof of the cause of weight movement.';
        $evidence[]=ruminant_diagnostic_evidence('context','Health event recorded',date('M j, Y',strtotime($row['event_date'])).' · '.$detail);
        $addTimeline((string)$row['event_date'],'health','Health & Treatment event',$detail);
    }
    if(!$health) $missing[]='No structured Health & Treatment event was recorded for this animal between the two weight observations. Absence of a record does not prove no treatment or health event occurred.';

    // Active withdrawal is factual treatment context, not a product-specific rule invented by the platform.
    $wh=$pdo->prepare("SELECT event_date,event_type,medicine,withdrawal_until
                       FROM ruminant_health_events
                       WHERE farm_id=? AND animal_id=? AND withdrawal_until IS NOT NULL AND withdrawal_until>=?
                       ORDER BY withdrawal_until ASC,id DESC LIMIT 1");
    $wh->execute([$farmId,$animalId,$to]);
    $withdrawal=$wh->fetch(PDO::FETCH_ASSOC) ?: null;
    if($withdrawal){
        $evidence[]=ruminant_diagnostic_evidence('context','Recorded withdrawal period active','A health record carries a withdrawal-until date of '.date('M j, Y',strtotime($withdrawal['withdrawal_until'])).'. This is recorded management context; Product-specific withdrawal rules are not inferred.');
    }

    // Feed is herd/cycle context only. Never divide herd feed by this animal or claim intake.
    $feedRows=[];
    if($membership){
        $pred=stock_effective_sql_predicate('t');
        $sql="SELECT t.transaction_date,t.cycle_id,t.production_type,t.quantity,s.item_name,s.unit
              FROM stock_transactions t
              JOIN stock_items s ON s.id=t.stock_item_id AND s.farm_id=t.farm_id
              WHERE t.farm_id=? AND t.farm_type='ruminant' AND t.transaction_type='used'
                AND t.financial_classification='feed' AND t.transaction_date BETWEEN ? AND ?
                AND {$pred}
                AND (t.cycle_id=? OR (t.cycle_id IS NULL AND LOWER(t.production_type)=?))
              ORDER BY t.transaction_date,t.id";
        $fs=$pdo->prepare($sql);
        $fs->execute([$farmId,$from,$to,(int)$membership['cycle_id'],strtolower((string)$animal['species'])]);
        $feedRows=$fs->fetchAll(PDO::FETCH_ASSOC);
    }
    if($membership && $feedRows){
        $byItem=[]; $sequence=[];
        foreach($feedRows as $row){
            $name=(string)$row['item_name']; $unit=trim((string)$row['unit']); $key=$name.'|'.$unit;
            if(!isset($byItem[$key]))$byItem[$key]=['name'=>$name,'unit'=>$unit,'qty'=>0.0];
            $byItem[$key]['qty']+=(float)$row['quantity'];
            $sequence[]=['date'=>(string)$row['transaction_date'],'name'=>$name,'unit'=>$unit,'qty'=>(float)$row['quantity']];
        }
        $parts=[]; foreach($byItem as $x)$parts[]=$x['name'].' '.number_format($x['qty'],2).($x['unit']!==''?' '.$x['unit']:'');
        $evidence[]=ruminant_diagnostic_evidence('context','Herd/cycle feed usage recorded',implode('; ',$parts).'. These are stock-ledger quantities for the animal’s recorded cycle/species context, not individual-animal feed intake.');
        $last=null;
        foreach($sequence as $x){
            if($last===null || $x['name']!==$last){
                $addTimeline($x['date'],'feed','Herd/cycle feed item recorded',$x['name'].' · '.number_format($x['qty'],2).($x['unit']!==''?' '.$x['unit']:'').'. Herd/cycle context only; individual consumption is not inferred.');
                $last=$x['name'];
            }
        }
        if(count($byItem)>1){
            $evidence[]=ruminant_diagnostic_evidence('possible','Feed item mix changed','More than one feed item appears in effective herd/cycle stock usage between the two weights. Timing is relevant context to review; the system does not claim a feed change caused the weight movement.');
        }
    } elseif($membership) {
        $missing[]='No effective herd/cycle feed stock usage was recorded between the two weight observations. The platform will not infer individual feed intake from missing stock records.';
    }

    // Directly allocated expenses can reveal recorded interventions without being treated as causes.
    $ex=$pdo->prepare("SELECT e.expense_date,e.category,e.description,a.allocated_amount
                       FROM ruminant_expense_animal_allocations a
                       JOIN farm_expenses e ON e.id=a.expense_id AND e.farm_id=a.farm_id
                       WHERE a.farm_id=? AND a.animal_id=? AND e.expense_date BETWEEN ? AND ?
                       ORDER BY e.expense_date,e.id");
    $ex->execute([$farmId,$animalId,$from,$to]);
    $directExpenses=$ex->fetchAll(PDO::FETCH_ASSOC);
    foreach($directExpenses as $row){
        $detail=ucwords(str_replace(['_','-'],' ',(string)$row['category'])).' · ₦'.number_format((float)$row['allocated_amount'],2);
        if(trim((string)($row['description']??''))!=='')$detail.=' · '.trim((string)$row['description']);
        $detail.='. Financial/management context only; an allocated expense is not evidence of a medical cause.';
        $addTimeline((string)$row['expense_date'],'expense','Direct allocated expense recorded',$detail);
    }

    usort($timeline,static function($a,$b){
        $c=strcmp((string)$a['date'],(string)$b['date']); if($c!==0)return $c;
        $order=['weight'=>0,'feed'=>1,'health'=>2,'expense'=>3];
        return ($order[$a['type']]??9)<=>($order[$b['type']]??9);
    });

    $strength='Limited';
    if($membership && count($weights)>=2) $strength=$health?'Strong':'Moderate';
    // Rapid measurement change affects verification priority, not evidence strength.

    $checks=[
        'Confirm both weight records were taken with a consistent weighing method and scale.',
        'Physically assess the animal and review recorded symptoms, Health & Treatment events and withdrawal context.',
        'Review herd/cycle feed availability, feed-item changes, grazing access and water availability without assuming herd feed equals individual intake.',
        'Review the animal’s production-cycle membership and recent management interventions for timing around the weight movement.',
        'Seek veterinary assessment if weight loss persists, is substantial, or accompanies clinical signs.'
    ];

    return [
        'animal'=>$animal,'as_of'=>$asOfDate,'from_date'=>$from,'to_date'=>$to,
        'weights'=>$weights,'previous_weight'=>$previous,'latest_weight'=>$latest,
        'delta_kg'=>$delta,'delta_percent'=>$deltaPct,
        'headline'=>$delta<0?'Recorded weight decline investigation':'Recorded weight movement review',
        'measure'=>$measure,'evidence_strength'=>$strength,'evidence'=>$evidence,
        'timeline'=>$timeline,'missing'=>$missing,'checks'=>$checks,
        'membership'=>$membership,'health_events'=>$health,'feed_rows'=>$feedRows,
        'animal_url'=>'ruminant/animal_view.php?id='.$animalId,
        'registry_url'=>'ruminant/animal_registry.php',
        'inventory_url'=>'inventory.php',
    ];
}
}
