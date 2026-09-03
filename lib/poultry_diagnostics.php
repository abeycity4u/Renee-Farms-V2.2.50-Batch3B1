<?php
require_once __DIR__ . '/poultry_health.php';

/** V2.2.49 Batch 4B deterministic poultry investigation engine. */
function poultry_diagnostic_table(string $productionType): string {
    return $productionType === 'broiler' ? 'broiler_daily_records' : 'layer_daily_records';
}
function poultry_diagnostic_page(string $productionType): string {
    return $productionType === 'broiler' ? 'poultry/broiler_daily_record.php' : 'poultry/layers_daily_record.php';
}
function poultry_diagnostic_cycle(PDO $pdo,int $farmId,int $cycleId,string $productionType): array {
    $stmt=$pdo->prepare("SELECT id,cycle_code,production_type,start_date,expected_end_date,status FROM production_cycles WHERE id=? AND farm_id=? AND farm_type='poultry' LIMIT 1");
    $stmt->execute([$cycleId,$farmId]); $c=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$c || strtolower((string)$c['production_type'])!==$productionType) throw new InvalidArgumentException('The selected poultry cycle is not available for this investigation.');
    return $c;
}
function poultry_diagnostic_records(PDO $pdo,int $farmId,int $cycleId,string $productionType,string $asOfDate,int $limit=21): array {
    $table=poultry_diagnostic_table($productionType);
    $stmt=$pdo->prepare("SELECT d.*,s.item_name AS feed_item_name FROM {$table} d LEFT JOIN stock_items s ON s.id=d.feed_item_id AND s.farm_id=d.farm_id WHERE d.farm_id=? AND d.cycle_id=? AND d.record_date<=? ORDER BY d.record_date DESC,d.id DESC LIMIT ".max(1,min(60,$limit)));
    $stmt->execute([$farmId,$cycleId,$asOfDate]); $rows=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[]; return array_reverse($rows);
}
function poultry_diagnostic_avg(array $rows,string $field): ?float { if(!$rows)return null;$v=array_map(fn($r)=>(float)($r[$field]??0),$rows);return array_sum($v)/count($v); }
function poultry_diagnostic_per_bird(array $rows,string $field,float $multiplier=1.0): ?float { $sum=0;$n=0;foreach($rows as $r){$open=(float)($r['opening_stock']??0);if($open<=0)continue;$sum+=((float)($r[$field]??0)*$multiplier)/$open;$n++;}return $n?$sum/$n:null; }
function poultry_diagnostic_pct_delta(?float $recent,?float $prior): ?float { if($recent===null||$prior===null||abs($prior)<0.000001)return null;return (($recent-$prior)/abs($prior))*100; }
function poultry_diagnostic_evidence(string $type,string $title,string $detail,string $timing='context'): array { return compact('type','title','detail','timing'); }

function poultry_diagnostic_investigate(PDO $pdo,int $farmId,int $cycleId,string $productionType,string $issue,string $asOfDate): array {
    $productionType=in_array($productionType,['layer','broiler'],true)?$productionType:'layer';
    $cycle=poultry_diagnostic_cycle($pdo,$farmId,$cycleId,$productionType);
    $rows=poultry_diagnostic_records($pdo,$farmId,$cycleId,$productionType,$asOfDate,21);
    $latest14=array_slice($rows,-14); $recent=array_slice($latest14,-7); $prior=array_slice($latest14,0,max(0,count($latest14)-7)); if(count($prior)>7)$prior=array_slice($prior,-7);
    $enough=count($recent)>=7 && count($prior)>=7;
    $evidence=[];$checks=[];$missing=[];$headline='Poultry performance investigation';$measure='';
    if(!$enough)$missing[]='At least 14 recorded daily records are needed for a full 7-vs-7 trend comparison.';

    if($productionType==='layer' && $issue==='laying_decline'){
        $headline='Layer production decline investigation'; $ra=poultry_diagnostic_avg($recent,'laying_rate');$pa=poultry_diagnostic_avg($prior,'laying_rate');$delta=($ra!==null&&$pa!==null)?$ra-$pa:null;
        $measure=$ra===null?'No recent laying-rate data':number_format($ra,1).'% recent average'.($delta!==null?' · '.($delta>=0?'+':'−').number_format(abs($delta),1).' pp':'');
        if($delta!==null)$evidence[]=poultry_diagnostic_evidence('observed','Laying-rate movement','Recent 7 recorded days '.number_format($ra,1).'% versus '.number_format($pa,1).'% in the preceding 7 recorded days.','problem');
        $checks[]='Verify feed availability, feeder access and feed quality/formulation.';$checks[]='Verify water supply and drinker access.';$checks[]='Review flock health, recent treatments/vaccinations and physical condition.';$checks[]='Review lighting and house/environment conditions; these are not yet recorded structurally in the platform.';
    } else {
        $headline=ucfirst($productionType).' mortality investigation';
        $rate=function($x){$o=0;$m=0;foreach($x as $r){$o+=(float)$r['opening_stock'];$m+=(float)$r['mortality'];}return $o>0?$m/$o*100:null;};$ra=$rate($recent);$pa=$rate($prior);$delta=($ra!==null&&$pa!==null)?$ra-$pa:null;
        $measure=$ra===null?'No recent mortality data':number_format($ra,2).'% recent mortality'.($delta!==null?' · '.($delta>=0?'+':'−').number_format(abs($delta),2).' pp':'');
        if($delta!==null)$evidence[]=poultry_diagnostic_evidence('observed','Mortality movement','Recent 7 recorded days '.number_format($ra,2).'% versus '.number_format($pa,2).'% in the preceding 7 recorded days.','problem');
        $checks[]='Inspect birds and review recorded symptoms or health events.';$checks[]='Verify water supply and drinker access.';$checks[]='Review feed availability, feed changes and recent intake.';$checks[]='Seek veterinary assessment if elevated mortality continues or clinical signs are present.';
    }

    // Flock survival is useful context for both poultry types. For Layers it prevents a stable
    // laying percentage from hiding lost absolute production; for Broilers it shows the physical
    // flock effect of the mortality signal without inventing an external mortality benchmark.
    $firstOpen=$latest14?(float)($latest14[0]['opening_stock']??0):0;$last=end($latest14);$lastClose=$last?max(0,(float)$last['opening_stock']-(float)$last['mortality']):0;
    if($productionType==='layer'){
        $recentEgg=poultry_diagnostic_avg($recent,'egg_production');$priorEgg=poultry_diagnostic_avg($prior,'egg_production');$eggDelta=poultry_diagnostic_pct_delta($recentEgg,$priorEgg);
        if($eggDelta!==null)$evidence[]=poultry_diagnostic_evidence(abs($eggDelta)>=8?'possible':'context','Absolute egg output',($eggDelta>=0?'+':'−').number_format(abs($eggDelta),1).'% average eggs/day versus the preceding 7 recorded days. Laying rate and absolute output are evaluated separately so flock loss does not hide lost production volume.',$eggDelta<0?'problem':'context');
    }
    if($firstOpen>0){$flockDelta=(($lastClose-$firstOpen)/$firstOpen)*100;if($flockDelta<0)$evidence[]=poultry_diagnostic_evidence('observed','Live flock declined','Available records move from '.number_format($firstOpen,0).' opening birds to '.number_format($lastClose,0).' closing birds (−'.number_format(abs($flockDelta),1).'%).','problem');}

    // Broiler-specific diagnostic depth: show whether recent mortality is broadly distributed or
    // concentrated on a small number of recorded days. This is descriptive evidence only.
    if($productionType==='broiler' && $recent){
        $recentMortTotal=array_sum(array_map(fn($r)=>(float)($r['mortality']??0),$recent));
        $peak=null; foreach($recent as $r){if($peak===null || (float)($r['mortality']??0)>(float)($peak['mortality']??0))$peak=$r;}
        $peakMort=$peak?(float)($peak['mortality']??0):0;
        if($recentMortTotal>0 && $peakMort>0){
            $share=$peakMort/$recentMortTotal*100; $peakOpen=(float)($peak['opening_stock']??0); $peakRate=$peakOpen>0?$peakMort/$peakOpen*100:null;
            $detail='Highest recent recorded mortality was '.number_format($peakMort,0).' bird'.($peakMort==1?'':'s').' on '.date('M j',strtotime((string)$peak['record_date'])).', representing '.number_format($share,1).'% of mortality across the recent 7 recorded days.';
            if($peakRate!==null)$detail.=' That day was '.number_format($peakRate,2).'% of its opening flock.';
            $evidence[]=poultry_diagnostic_evidence($share>=50?'possible':'context','Mortality concentration',$detail.' Concentration is a pattern to review, not a disease diagnosis.',$share>=50?'problem':'context');
        }
    }

    $feedRecent=poultry_diagnostic_per_bird($recent,'feed_consumption_bags',1);$feedPrior=poultry_diagnostic_per_bird($prior,'feed_consumption_bags',1);$fd=poultry_diagnostic_pct_delta($feedRecent,$feedPrior);
    if($fd!==null)$evidence[]=poultry_diagnostic_evidence(abs($fd)>=8?'possible':'context','Feed intake per bird',($fd>=0?'+':'−').number_format(abs($fd),1).'% versus preceding 7 recorded days.'.(abs($fd)>=8?' A meaningful intake movement occurred around the problem period.':' Intake remained relatively close to the preceding period.'),$fd<0?'possible contributor':'context');
    $waterRecent=poultry_diagnostic_per_bird($recent,'water_consumption_liters',1);$waterPrior=poultry_diagnostic_per_bird($prior,'water_consumption_liters',1);$wd=poultry_diagnostic_pct_delta($waterRecent,$waterPrior);
    if($wd!==null)$evidence[]=poultry_diagnostic_evidence(abs($wd)>=8?'possible':'context','Water intake per bird',($wd>=0?'+':'−').number_format(abs($wd),1).'% versus preceding 7 recorded days.'.(abs($wd)>=8?' A meaningful water movement occurred around the problem period.':' Water intake remained relatively close to the preceding period.'),$wd<0?'possible contributor':'context');

    $feedNames=[];$feedChanges=[];$previousFeed=null;foreach($latest14 as $r){$name=trim((string)($r['feed_item_name']??''));if($name!=='' && $name!==$previousFeed){$feedNames[]=$name;$feedChanges[]=date('M j',strtotime($r['record_date'])).': '.$name;$previousFeed=$name;}}
    if(count($feedNames)>1)$evidence[]=poultry_diagnostic_evidence('possible','Feed item changed','Recorded feed sequence: '.implode(' → ',$feedChanges).'. Timing is relevant context; the system does not claim the feed change caused the performance movement.','context');
    elseif(count($feedNames)===1)$evidence[]=poultry_diagnostic_evidence('context','Feed item','No feed-item change was detected in the available 14-record window ('.$feedNames[0].').','context');
    else $missing[]='No linked feed item was available in the comparison window.';

    $ageAnomalies=[];for($i=1;$i<count($latest14);$i++){if(isset($latest14[$i]['birds_age'],$latest14[$i-1]['birds_age']) && (int)$latest14[$i]['birds_age'] <= (int)$latest14[$i-1]['birds_age'])$ageAnomalies[]=date('M j',strtotime($latest14[$i]['record_date']));}
    if($ageAnomalies)$missing[]='Bird age did not advance on consecutive recorded dates: '.implode(', ',$ageAnomalies).'. Review the daily entries.';

    $from=$rows?max($cycle['start_date'],date('Y-m-d',strtotime($asOfDate.' -20 days'))):$asOfDate;
    $healthContext=poultry_health_diagnostic_context($pdo,$farmId,$cycleId,$productionType,$from,$asOfDate);
    $health=$healthContext['exact_cycle']; $otherCycleHealth=$healthContext['other_cycle'];
    foreach($health as $h){$label=poultry_health_event_type_label((string)$h['event_type']);$product=trim((string)($h['product_name']??''));$reason=trim((string)($h['reason_symptoms']??''));$detail=date('M j, Y',strtotime($h['event_date'])).' · '.$label.($product!==''?' · '.$product:'').($reason!==''?' · '.$reason:'');$evidence[]=poultry_diagnostic_evidence('context','Health event recorded',$detail.'. This is timeline context, not proof of cause.','context');}
    if(!$health){
        if($otherCycleHealth){
            $examples=[]; foreach(array_slice($otherCycleHealth,-3) as $h){$examples[]=date('M j',strtotime($h['event_date'])).' · '.poultry_health_event_type_label((string)$h['event_type']).' · '.((string)($h['cycle_code']??'')!==''?(string)$h['cycle_code']:'No cycle');}
            $missing[]='Structured Poultry Health & Treatment event(s) exist in the context window, but they are linked to a different/no production cycle and are excluded from this flock investigation: '.implode(' | ',$examples).'. Review cycle attribution.';
        } else {
            $missing[]='No structured Poultry Health & Treatment event was recorded for this production cycle in the context window.';
        }
    }

    $remarks=[];foreach(array_slice($rows,-14) as $r){foreach(['medications','remarks'] as $f){$txt=trim((string)($r[$f]??''));if($txt!=='')$remarks[]=date('M j',strtotime($r['record_date'])).': '.$txt;}}
    if($remarks)$evidence[]=poultry_diagnostic_evidence('context','Daily notes available',implode(' | ',array_slice($remarks,-4)).(count($remarks)>4?' …':''),'context');

    // Evidence Timeline: chronological, recorded events only. This intentionally shows sequence without assigning causation.
    $timeline=[];
    $timelineAdd=function(string $date,string $type,string $title,string $detail) use (&$timeline){
        $timeline[]=['date'=>$date,'type'=>$type,'title'=>$title,'detail'=>$detail];
    };
    if($latest14){
        $baseline=$latest14[0];
        $baseDetail='Opening flock '.number_format((float)($baseline['opening_stock']??0),0);
        if($productionType==='layer')$baseDetail.=' · eggs '.number_format((float)($baseline['egg_production']??0),0).' · laying '.number_format((float)($baseline['laying_rate']??0),1).'%';
        $timelineAdd((string)$baseline['record_date'],'baseline','Comparison window begins',$baseDetail.'.');
        $prev=null;$prevFeed=null;
        foreach($latest14 as $r){
            $date=(string)$r['record_date'];$mort=(float)($r['mortality']??0);$open=(float)($r['opening_stock']??0);
            $feedName=trim((string)($r['feed_item_name']??''));
            if($feedName!=='' && $prevFeed!==null && $feedName!==$prevFeed)$timelineAdd($date,'feed','Feed item changed',$prevFeed.' → '.$feedName.'. Recorded timing only; causation is not inferred.');
            if($feedName!=='')$prevFeed=$feedName;
            if($mort>0){$mr=$open>0?$mort/$open*100:0;$timelineAdd($date,'mortality','Mortality recorded',number_format($mort,0).' bird'.($mort==1?'':'s').' · '.number_format($mr,2).'% of opening flock.');}
            if($prev){
                $po=(float)($prev['opening_stock']??0);$pw=(float)($prev['water_consumption_liters']??0);$cw=(float)($r['water_consumption_liters']??0);
                $prevFeedPerBird=$po>0?(float)($prev['feed_consumption_bags']??0)/$po:null;$curFeedPerBird=$open>0?(float)($r['feed_consumption_bags']??0)/$open:null;$feedDayDelta=poultry_diagnostic_pct_delta($curFeedPerBird,$prevFeedPerBird);
                if($feedDayDelta!==null && abs($feedDayDelta)>=8)$timelineAdd($date,'feed_intake','Feed intake/bird moved',($feedDayDelta>=0?'+':'−').number_format(abs($feedDayDelta),1).'% versus the previous recorded day.');
                $prevWaterPerBird=$po>0?$pw/$po:null;$curWaterPerBird=$open>0?$cw/$open:null;$waterDayDelta=poultry_diagnostic_pct_delta($curWaterPerBird,$prevWaterPerBird);
                if($waterDayDelta!==null && abs($waterDayDelta)>=8)$timelineAdd($date,'water','Water intake/bird moved',($waterDayDelta>=0?'+':'−').number_format(abs($waterDayDelta),1).'% versus the previous recorded day.');
                if($productionType==='layer'){
                    $pe=(float)($prev['egg_production']??0);$ce=(float)($r['egg_production']??0);$eggDayDelta=poultry_diagnostic_pct_delta($ce,$pe);
                    if($eggDayDelta!==null && $eggDayDelta<=-2)$timelineAdd($date,'production','Egg output declined','−'.number_format(abs($eggDayDelta),1).'% versus the previous recorded day ('.number_format($pe,0).' → '.number_format($ce,0).' eggs).');
                }
            }
            foreach(['medications'=>'Daily medication note','remarks'=>'Daily remark'] as $f=>$label){$txt=trim((string)($r[$f]??''));if($txt!=='' && stripos($txt,'Auto-created')!==0)$timelineAdd($date,'note',$label,$txt);}
            $prev=$r;
        }
    }
    foreach($health as $h){
        $label=poultry_health_event_type_label((string)$h['event_type']);$product=trim((string)($h['product_name']??''));$reason=trim((string)($h['reason_symptoms']??''));
        $detail=$label.($product!==''?' · '.$product:'').($reason!==''?' · '.$reason:'').'. Health-event context; not proof of cause.';
        $timelineAdd((string)$h['event_date'],'health','Health & Treatment event',$detail);
    }
    usort($timeline,function($a,$b){$c=strcmp($a['date'],$b['date']);if($c!==0)return $c; $order=['baseline'=>0,'feed'=>1,'feed_intake'=>2,'water'=>3,'production'=>4,'mortality'=>5,'health'=>6,'note'=>7];return ($order[$a['type']]??9)<=>($order[$b['type']]??9);});

    $strength=$enough?(count($health)>0?'Strong':'Moderate'):'Limited';
    if(!$enough)$strength='Limited';
    return ['cycle'=>$cycle,'production_type'=>$productionType,'issue'=>$issue,'as_of'=>$asOfDate,'from_date'=>($latest14?(string)$latest14[0]['record_date']:$asOfDate),'to_date'=>($last?(string)$last['record_date']:$asOfDate),'headline'=>$headline,'measure'=>$measure,'evidence_strength'=>$strength,'evidence'=>$evidence,'missing'=>$missing,'checks'=>array_values(array_unique($checks)),'records_count'=>count($rows),'comparison_complete'=>$enough,'timeline'=>$timeline,'daily_url'=>poultry_diagnostic_page($productionType)];
}
