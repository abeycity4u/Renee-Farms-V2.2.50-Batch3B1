<?php
function investigation_followup_outcomes(): array { return [
 'measurement_data_error'=>'Measurement / data error',
 'confirmed_observation'=>'Confirmed observation',
 'management_issue_found'=>'Management issue found',
 'veterinary_review_required'=>'Veterinary review required',
 'monitoring_no_conclusion'=>'Monitoring / no conclusion yet'
]; }
function investigation_followup_episode_key(string $type,string $issue,string $fromDate,string $toDate): string {
 return substr(hash('sha256',$type.'|'.$issue.'|'.$fromDate.'|'.$toDate),0,48);
}
function investigation_followup_get(PDO $pdo,int $farmId,string $type,int $subjectId,string $issue,string $episodeKey): ?array {
 $s=$pdo->prepare('SELECT f.*,u.username recorded_by_name,ru.username resolved_by_name FROM management_investigation_followups f LEFT JOIN users u ON u.id=f.recorded_by LEFT JOIN users ru ON ru.id=f.resolved_by WHERE f.farm_id=? AND f.investigation_type=? AND f.subject_id=? AND f.issue_type=? AND f.episode_key=? LIMIT 1');
 $s->execute([$farmId,$type,$subjectId,$issue,$episodeKey]); return $s->fetch(PDO::FETCH_ASSOC)?:null;
}
function investigation_followup_latest_prior(PDO $pdo,int $farmId,string $type,int $subjectId,string $issue,string $asOf,string $excludeEpisode=''): ?array {
 $sql='SELECT f.*,u.username recorded_by_name,ru.username resolved_by_name FROM management_investigation_followups f LEFT JOIN users u ON u.id=f.recorded_by LEFT JOIN users ru ON ru.id=f.resolved_by WHERE f.farm_id=? AND f.investigation_type=? AND f.subject_id=? AND f.issue_type=? AND f.as_of_date<=?';
 $args=[$farmId,$type,$subjectId,$issue,$asOf]; if($excludeEpisode!==''){ $sql.=' AND f.episode_key<>?'; $args[]=$excludeEpisode; }
 $sql.=' ORDER BY f.as_of_date DESC,f.id DESC LIMIT 1'; $s=$pdo->prepare($sql);$s->execute($args);return $s->fetch(PDO::FETCH_ASSOC)?:null;
}
function investigation_followup_prior_history(PDO $pdo,int $farmId,string $type,int $subjectId,string $issue,string $asOf,string $excludeEpisode='',int $limit=20): array {
 $sql='SELECT f.*,u.username recorded_by_name,ru.username resolved_by_name FROM management_investigation_followups f LEFT JOIN users u ON u.id=f.recorded_by LEFT JOIN users ru ON ru.id=f.resolved_by WHERE f.farm_id=? AND f.investigation_type=? AND f.subject_id=? AND f.issue_type=? AND f.as_of_date<=?';
 $args=[$farmId,$type,$subjectId,$issue,$asOf]; if($excludeEpisode!==''){ $sql.=' AND f.episode_key<>?'; $args[]=$excludeEpisode; }
 $sql.=' ORDER BY f.as_of_date DESC,f.id DESC LIMIT '.max(1,min(100,$limit)); $st=$pdo->prepare($sql);$st->execute($args);return $st->fetchAll(PDO::FETCH_ASSOC);
}

function investigation_followup_save(PDO $pdo,int $farmId,string $type,int $subjectId,string $issue,string $asOf,string $episodeKey,string $outcome,string $finding,string $action,bool $resolve,int $userId): int {
 $allowed=investigation_followup_outcomes(); if(!isset($allowed[$outcome])) throw new InvalidArgumentException('Select a valid management outcome.');
 if(trim($finding)==='') throw new InvalidArgumentException('Record what management found before saving the investigation outcome.');
 $status=$resolve?'resolved':'open'; $resolvedBy=$resolve?$userId:null;
 $sql='INSERT INTO management_investigation_followups (farm_id,investigation_type,subject_id,issue_type,as_of_date,episode_key,status,outcome,finding_notes,action_taken,recorded_by,resolved_by,resolved_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'.($resolve?'NOW()':'NULL').') ON DUPLICATE KEY UPDATE status=VALUES(status),outcome=VALUES(outcome),finding_notes=VALUES(finding_notes),action_taken=VALUES(action_taken),recorded_by=VALUES(recorded_by),resolved_by=VALUES(resolved_by),resolved_at='.($resolve?'NOW()':'NULL');
 $s=$pdo->prepare($sql); $s->execute([$farmId,$type,$subjectId,$issue,$asOf,$episodeKey,$status,$outcome,trim($finding),trim($action),$userId,$resolvedBy]);
 $row=investigation_followup_get($pdo,$farmId,$type,$subjectId,$issue,$episodeKey); return (int)($row['id']??0);
}


/**
 * Attach management follow-through state to investigation-capable Farm Intelligence signals.
 *
 * This is a read-only awareness layer. It never changes signal severity or suppresses a
 * still-detected source condition. Exact as-of follow-up wins; otherwise the latest prior
 * follow-up is shown as historical context so a continuing condition does not look untouched.
 */
if (!function_exists('investigation_followup_annotate_signals')) {
function investigation_followup_annotate_signals(PDO $pdo, int $farmId, array $signals, string $asOfDate): array {
    $targets=[];
    foreach($signals as $i=>$signal){
        $url=(string)($signal['action_url']??'');
        if($url==='') continue;
        $parts=parse_url($url); $path=(string)($parts['path']??'');
        parse_str((string)($parts['query']??''),$q);
        if(str_ends_with($path,'management/investigation.php') || str_ends_with($path,'investigation.php')){
            if(!isset($q['cycle_id']) || !isset($q['issue'])) continue;
            $targets[$i]=['type'=>'poultry','subject_id'=>(int)$q['cycle_id'],'issue'=>(string)$q['issue']];
        } elseif(str_ends_with($path,'management/ruminant_investigation.php') || str_ends_with($path,'ruminant_investigation.php')){
            if(!isset($q['animal_id'])) continue;
            $targets[$i]=['type'=>'ruminant','subject_id'=>(int)$q['animal_id'],'issue'=>'weight_change'];
        }
    }
    if(!$targets) return $signals;

    $stmt=$pdo->prepare("SELECT f.*,u.username recorded_by_name,ru.username resolved_by_name
        FROM management_investigation_followups f
        LEFT JOIN users u ON u.id=f.recorded_by
        LEFT JOIN users ru ON ru.id=f.resolved_by
        WHERE f.farm_id=? AND f.as_of_date<=?
        ORDER BY f.as_of_date DESC,f.id DESC");
    $stmt->execute([$farmId,$asOfDate]);
    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $byKey=[];
    foreach($rows as $row){
        $key=(string)$row['investigation_type'].'|'.(int)$row['subject_id'].'|'.(string)$row['issue_type'];
        if(!isset($byKey[$key])) $byKey[$key]=$row; // latest at/before as-of
    }
    // Resolve the latest recorded source evidence date in batches. A later source
    // record means a resolved review is historical context, not proof that the new
    // evidence has already been handled.
    $poultryIds=[]; $ruminantIds=[];
    foreach($targets as $target){
        if($target['type']==='poultry') $poultryIds[$target['subject_id']]=true;
        elseif($target['type']==='ruminant') $ruminantIds[$target['subject_id']]=true;
    }
    $evidenceDates=[];
    if($poultryIds){
        $ids=array_keys($poultryIds); $ph=implode(',',array_fill(0,count($ids),'?'));
        foreach(['layer_daily_records','broiler_daily_records'] as $table){
            $sql="SELECT cycle_id,MAX(record_date) evidence_date FROM {$table} WHERE farm_id=? AND cycle_id IN ({$ph}) AND record_date<=? GROUP BY cycle_id";
            $st=$pdo->prepare($sql); $st->execute(array_merge([$farmId],$ids,[$asOfDate]));
            foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){
                $k='poultry|'.(int)$r['cycle_id']; $d=(string)$r['evidence_date'];
                if(!isset($evidenceDates[$k]) || $d>$evidenceDates[$k]) $evidenceDates[$k]=$d;
            }
        }
    }
    if($ruminantIds){
        $ids=array_keys($ruminantIds); $ph=implode(',',array_fill(0,count($ids),'?'));
        $sql="SELECT animal_id,MAX(weight_date) evidence_date FROM ruminant_animal_weights WHERE farm_id=? AND animal_id IN ({$ph}) AND weight_date<=? GROUP BY animal_id";
        $st=$pdo->prepare($sql); $st->execute(array_merge([$farmId],$ids,[$asOfDate]));
        foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r) $evidenceDates['ruminant|'.(int)$r['animal_id']]=(string)$r['evidence_date'];
    }

    foreach($targets as $i=>$target){
        $key=$target['type'].'|'.$target['subject_id'].'|'.$target['issue'];
        $row=$byKey[$key]??null; if(!$row) continue;
        $evidenceDate=$evidenceDates[$target['type'].'|'.$target['subject_id']]??null;
        $hasNewEvidence=$evidenceDate && $evidenceDate>(string)$row['as_of_date'];
        $signals[$i]['followup_status']=(string)$row['status'];
        $signals[$i]['followup_recurrence_state']=((string)$row['status']==='resolved' && $hasNewEvidence)?'new_activity':'reviewed';
        $signals[$i]['followup_new_evidence']=((string)$row['status']==='resolved' && $hasNewEvidence);
        $signals[$i]['followup_evidence_date']=(string)($evidenceDate??'');
        $signals[$i]['followup_outcome']=(string)($row['outcome']??'');
        $signals[$i]['followup_as_of']=(string)$row['as_of_date'];
        $signals[$i]['followup_exact_as_of']=((string)$row['as_of_date']===$asOfDate);
        $signals[$i]['followup_updated_at']=(string)($row['updated_at']??$row['created_at']??'');
        $signals[$i]['followup_resolved_at']=(string)($row['resolved_at']??'');
        $signals[$i]['followup_recorded_by_name']=(string)($row['recorded_by_name']??'');
        $signals[$i]['followup_resolved_by_name']=(string)($row['resolved_by_name']??'');
    }
    return $signals;
}}
