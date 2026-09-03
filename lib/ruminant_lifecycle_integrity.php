<?php
require_once __DIR__.'/ruminant_lifecycle_service.php';

function ruminant_animal_has_history(PDO $pdo,int $farmId,int $animalId): bool
{
    $checks=[
        ['ruminant_animal_cycle_memberships','animal_id'],['ruminant_animal_weights','animal_id'],['ruminant_health_events','animal_id'],
        ['ruminant_expense_animal_allocations','animal_id'],['ruminant_sale_animal_allocations','animal_id'],['ruminant_animal_exit_events','animal_id']
    ];
    foreach($checks as [$table,$column]){
        $s=$pdo->prepare("SELECT 1 FROM {$table} WHERE farm_id=? AND {$column}=? LIMIT 1"); $s->execute([$farmId,$animalId]);
        if($s->fetchColumn()) return true;
    }
    return false;
}

function ruminant_manual_exit(PDO $pdo,int $farmId,int $animalId,string $date,string $outcome,?string $notes,?int $userId): int
{
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) throw new RuntimeException('Enter a valid effective exit date.');
    $map=['dead'=>'dead','culled'=>'culled','transferred'=>'transferred'];
    if(!isset($map[$outcome])) throw new RuntimeException('Choose a valid lifecycle outcome.');
    $pdo->beginTransaction();
    try{
        $s=$pdo->prepare('SELECT tag_no,status,purchase_date,birth_date FROM ruminant_animals WHERE id=? AND farm_id=? FOR UPDATE');
        $s->execute([$animalId,$farmId]); $a=$s->fetch(PDO::FETCH_ASSOC);
        if(!$a) throw new RuntimeException('Animal not found.');
        if((string)$a['status']!=='active') throw new RuntimeException($a['tag_no'].' is already '.ucfirst((string)$a['status']).'. A new exit can only be recorded for an Active animal.');
        $entry=$a['purchase_date'] ?: $a['birth_date'];
        if($entry && $date<$entry) throw new RuntimeException('Exit date cannot be before the animal entered the farm/operation.');
        $s=$pdo->prepare('SELECT 1 FROM ruminant_animal_exit_events WHERE farm_id=? AND animal_id=? LIMIT 1'); $s->execute([$farmId,$animalId]);
        if($s->fetchColumn()) throw new RuntimeException('This animal already has an exit event. Review Animal Exit History.');
        $pdo->prepare('UPDATE ruminant_animals SET status=?,updated_at=NOW() WHERE id=? AND farm_id=?')->execute([$map[$outcome],$animalId,$farmId]);
        $pdo->prepare('INSERT INTO ruminant_animal_exit_events(farm_id,animal_id,sale_id,exit_date,exit_outcome,previous_status,resulting_status,notes,recorded_by) VALUES(?,?,NULL,?,?,?,?,?,?)')
            ->execute([$farmId,$animalId,$date,'manual_'.$outcome,'active',$map[$outcome],$notes ?: null,$userId ?: null]);
        $id=(int)$pdo->lastInsertId();
        ruminant_lifecycle_apply_exit_boundary($pdo,$farmId,$animalId,$id,$date);
        $pdo->commit(); return $id;
    }catch(Throwable $e){ if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
}

function ruminant_exit_outcome_display(string $outcome): string
{
    return match($outcome){
        'sold_live'=>'Sold live','culled_slaughtered'=>'Culled / slaughtered','manual_dead'=>'Dead',
        'manual_culled'=>'Culled','manual_transferred'=>'Transferred',default=>'Exit'
    };
}
