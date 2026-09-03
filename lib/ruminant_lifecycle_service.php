<?php
/**
 * Central ruminant lifecycle/membership boundary service.
 *
 * Any dated lifecycle exit must pass through this service so an animal cannot
 * remain economically/operationally open in a production cycle beyond its
 * exit date. The boundary marker also allows a later reversal of that same
 * exit event to restore the exact prior membership end date.
 */
require_once __DIR__.'/ruminant_cycle_membership.php';

function ruminant_lifecycle_apply_exit_boundary(PDO $pdo, int $farmId, int $animalId, int $exitEventId, string $exitDate): void
{
    if ($exitEventId <= 0) throw new RuntimeException('A valid lifecycle exit event is required.');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$exitDate)) throw new RuntimeException('Enter a valid effective exit date.');

    // If this exit is being edited, every membership previously bounded by it
    // must still be capable of ending on the revised date.
    $check=$pdo->prepare("SELECT id,start_date FROM ruminant_animal_cycle_memberships
                          WHERE farm_id=? AND animal_id=? AND closed_by_exit_event_id=? FOR UPDATE");
    $check->execute([$farmId,$animalId,$exitEventId]);
    foreach($check->fetchAll(PDO::FETCH_ASSOC) as $row){
        if((string)$row['start_date']>$exitDate){
            throw new RuntimeException('Exit date cannot be moved before a production-cycle membership that was previously closed by this exit. Review membership history first.');
        }
    }

    // Update memberships already owned by this exit event while preserving the
    // pre-exit end date captured on first application.
    $pdo->prepare("UPDATE ruminant_animal_cycle_memberships
                   SET end_date=CASE
                       WHEN pre_exit_end_date IS NOT NULL AND pre_exit_end_date < ? THEN pre_exit_end_date
                       ELSE ? END
                   WHERE farm_id=? AND animal_id=? AND closed_by_exit_event_id=?")
        ->execute([$exitDate,$exitDate,$farmId,$animalId,$exitEventId]);

    // Capture and bound any other membership that crosses the exit date. This
    // includes legacy open memberships and a manually dated membership whose
    // end date would otherwise extend beyond the animal exit.
    $pdo->prepare("UPDATE ruminant_animal_cycle_memberships
                   SET pre_exit_end_date=end_date,
                       end_date=?,
                       closed_by_exit_event_id=?
                   WHERE farm_id=? AND animal_id=?
                     AND closed_by_exit_event_id IS NULL
                     AND start_date<=?
                     AND (end_date IS NULL OR end_date>?)")
        ->execute([$exitDate,$exitEventId,$farmId,$animalId,$exitDate,$exitDate]);
}

function ruminant_lifecycle_reverse_exit_boundary(PDO $pdo, int $farmId, int $animalId, int $exitEventId): void
{
    if($exitEventId<=0) return;
    $stmt=$pdo->prepare("SELECT id,pre_exit_end_date
                         FROM ruminant_animal_cycle_memberships
                         WHERE farm_id=? AND animal_id=? AND closed_by_exit_event_id=?
                         FOR UPDATE");
    $stmt->execute([$farmId,$animalId,$exitEventId]);
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
        $pdo->prepare("UPDATE ruminant_animal_cycle_memberships
                       SET end_date=?, closed_by_exit_event_id=NULL, pre_exit_end_date=NULL
                       WHERE id=? AND farm_id=? AND animal_id=?")
            ->execute([$row['pre_exit_end_date'] ?: null,(int)$row['id'],$farmId,$animalId]);
    }
}

function ruminant_lifecycle_repair_open_membership(PDO $pdo, int $farmId, int $animalId, int $membershipId): array
{
    $pdo->beginTransaction();
    try{
        $animalStmt=$pdo->prepare("SELECT id,tag_no,status FROM ruminant_animals WHERE id=? AND farm_id=? FOR UPDATE");
        $animalStmt->execute([$animalId,$farmId]);
        $animal=$animalStmt->fetch(PDO::FETCH_ASSOC);
        if(!$animal) throw new RuntimeException('Animal not found.');
        if((string)$animal['status']==='active') throw new RuntimeException('Active animals do not require an exit-membership repair.');

        $mStmt=$pdo->prepare("SELECT m.*,pc.cycle_code
                              FROM ruminant_animal_cycle_memberships m
                              JOIN production_cycles pc ON pc.id=m.cycle_id AND pc.farm_id=m.farm_id
                              WHERE m.id=? AND m.farm_id=? AND m.animal_id=? FOR UPDATE");
        $mStmt->execute([$membershipId,$farmId,$animalId]);
        $membership=$mStmt->fetch(PDO::FETCH_ASSOC);
        if(!$membership) throw new RuntimeException('Membership could not be found.');
        if($membership['end_date']!==null) throw new RuntimeException('This membership is already closed.');

        $eStmt=$pdo->prepare("SELECT * FROM ruminant_animal_exit_events
                              WHERE farm_id=? AND animal_id=?
                              ORDER BY exit_date DESC,id DESC LIMIT 1 FOR UPDATE");
        $eStmt->execute([$farmId,$animalId]);
        $exit=$eStmt->fetch(PDO::FETCH_ASSOC);
        if(!$exit) throw new RuntimeException('No dated lifecycle exit event was found for this exited animal. Review the animal history manually.');
        if((string)$membership['start_date']>(string)$exit['exit_date']){
            throw new RuntimeException('This membership starts after the recorded exit date and cannot be repaired automatically. Review the dates manually.');
        }

        ruminant_lifecycle_apply_exit_boundary($pdo,$farmId,$animalId,(int)$exit['id'],(string)$exit['exit_date']);
        $pdo->commit();
        return [
            'animal_id'=>$animalId,
            'tag_no'=>(string)$animal['tag_no'],
            'membership_id'=>$membershipId,
            'cycle_code'=>(string)$membership['cycle_code'],
            'exit_event_id'=>(int)$exit['id'],
            'exit_date'=>(string)$exit['exit_date'],
        ];
    }catch(Throwable $e){ if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
}
