<?php
/**
 * V2.2.48 — Ruminant production-cycle membership history.
 *
 * Membership is explicit and date-ranged. We never infer that a registry animal
 * belonged to a cycle merely because its species matches the cycle.
 */

function ruminant_cycle_memberships_for_animal(PDO $pdo, int $farmId, int $animalId): array
{
    $stmt = $pdo->prepare("SELECT m.*, pc.cycle_code, pc.production_type, pc.status AS cycle_status,
                                 pc.start_date AS cycle_start_date, pc.close_date AS cycle_close_date
                          FROM ruminant_animal_cycle_memberships m
                          JOIN production_cycles pc ON pc.id=m.cycle_id AND pc.farm_id=m.farm_id
                          WHERE m.farm_id=? AND m.animal_id=?
                          ORDER BY m.start_date DESC,m.id DESC");
    $stmt->execute([$farmId,$animalId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function ruminant_cycle_membership_add(PDO $pdo, int $farmId, int $animalId, int $cycleId, string $startDate, ?string $endDate, ?string $notes, ?int $createdBy): int
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$startDate)) throw new RuntimeException('Enter a valid membership start date.');
    if ($endDate !== null && (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$endDate) || $endDate < $startDate)) {
        throw new RuntimeException('Membership end date must be on or after the start date.');
    }

    $animalStmt=$pdo->prepare('SELECT species,tag_no,status,purchase_date,birth_date FROM ruminant_animals WHERE id=? AND farm_id=? LIMIT 1');
    $animalStmt->execute([$animalId,$farmId]);
    $animal=$animalStmt->fetch(PDO::FETCH_ASSOC);
    if(!$animal) throw new RuntimeException('Animal not found.');

    $cycleStmt=$pdo->prepare("SELECT id,cycle_code,production_type,start_date,close_date FROM production_cycles WHERE id=? AND farm_id=? AND farm_type='ruminant' LIMIT 1");
    $cycleStmt->execute([$cycleId,$farmId]);
    $cycle=$cycleStmt->fetch(PDO::FETCH_ASSOC);
    if(!$cycle) throw new RuntimeException('Choose a valid ruminant production cycle.');
    if(strtolower((string)$cycle['production_type']) !== strtolower((string)$animal['species'])) {
        throw new RuntimeException('The selected production cycle must match the animal species.');
    }
    $economicEntryDate = !empty($animal['purchase_date']) ? (string)$animal['purchase_date'] : (!empty($animal['birth_date']) ? (string)$animal['birth_date'] : null);
    if ($economicEntryDate !== null && $startDate < $economicEntryDate) {
        throw new RuntimeException('Membership cannot start before the animal entered the farm/operation.');
    }

    // An exited animal may receive a historical correction membership only
    // when the complete range ends on/before its dated lifecycle exit. Never
    // allow an open membership to be recreated beyond an existing exit.
    if ((string)$animal['status'] !== 'active') {
        $exitStmt=$pdo->prepare("SELECT exit_date FROM ruminant_animal_exit_events WHERE farm_id=? AND animal_id=? ORDER BY exit_date DESC,id DESC LIMIT 1");
        $exitStmt->execute([$farmId,$animalId]);
        $exitDate=$exitStmt->fetchColumn();
        if(!$exitDate) throw new RuntimeException('This exited animal has no dated lifecycle exit event. Review lifecycle history before adding membership history.');
        if($startDate>(string)$exitDate) throw new RuntimeException("Membership cannot start after the animal's recorded exit date (".date('d/m/Y',strtotime((string)$exitDate)).').');
        if($endDate===null || $endDate>(string)$exitDate) throw new RuntimeException("Membership for an exited animal must end on or before its recorded exit date (".date('d/m/Y',strtotime((string)$exitDate)).').');
    }
    if($startDate < (string)$cycle['start_date']) {
        throw new RuntimeException('Membership cannot start before the production cycle start date.');
    }
    if(!empty($cycle['close_date']) && ($endDate === null || $endDate > (string)$cycle['close_date'])) {
        throw new RuntimeException('A closed production cycle requires a membership end date no later than the cycle close date.');
    }

    // One animal cannot belong to overlapping production cycles. Adjacent ranges are allowed.
    $overlap=$pdo->prepare("SELECT m.id,pc.cycle_code
                           FROM ruminant_animal_cycle_memberships m
                           JOIN production_cycles pc ON pc.id=m.cycle_id AND pc.farm_id=m.farm_id
                           WHERE m.farm_id=? AND m.animal_id=?
                             AND m.start_date <= COALESCE(?, '9999-12-31')
                             AND COALESCE(m.end_date,'9999-12-31') >= ?
                           LIMIT 1");
    $overlap->execute([$farmId,$animalId,$endDate,$startDate]);
    $conflict=$overlap->fetch(PDO::FETCH_ASSOC);
    if($conflict) throw new RuntimeException('This date range overlaps the existing '.$conflict['cycle_code'].' membership. Close or adjust that membership first.');

    $stmt=$pdo->prepare('INSERT INTO ruminant_animal_cycle_memberships (farm_id,animal_id,cycle_id,start_date,end_date,notes,created_by) VALUES (?,?,?,?,?,?,?)');
    $stmt->execute([$farmId,$animalId,$cycleId,$startDate,$endDate,$notes ?: null,$createdBy ?: null]);
    return (int)$pdo->lastInsertId();
}


function ruminant_cycle_close_open_membership_at_exit(PDO $pdo, int $farmId, int $animalId, string $exitDate): void
{
    $stmt=$pdo->prepare("UPDATE ruminant_animal_cycle_memberships SET end_date=? WHERE farm_id=? AND animal_id=? AND start_date<=? AND (end_date IS NULL OR end_date>?)");
    $stmt->execute([$exitDate,$farmId,$animalId,$exitDate,$exitDate]);
}

function ruminant_cycle_membership_close(PDO $pdo,int $farmId,int $animalId,int $membershipId,string $endDate): void
{
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$endDate)) throw new RuntimeException('Enter a valid membership end date.');
    $s=$pdo->prepare('SELECT start_date,end_date,closed_by_exit_event_id FROM ruminant_animal_cycle_memberships WHERE id=? AND farm_id=? AND animal_id=? LIMIT 1');
    $s->execute([$membershipId,$farmId,$animalId]); $m=$s->fetch(PDO::FETCH_ASSOC);
    if(!$m) throw new RuntimeException('Cycle membership could not be found.');
    if($endDate<$m['start_date']) throw new RuntimeException('Membership end date cannot be before its start date.');
    if(!empty($m['closed_by_exit_event_id'])) throw new RuntimeException('This membership end date is controlled by a recorded lifecycle exit. Edit/reverse the lifecycle exit instead of rewriting its membership boundary.');
    if($m['end_date']!==null && $endDate>$m['end_date']) throw new RuntimeException('A closed historical membership cannot be extended from this correction action.');
    $e=$pdo->prepare("SELECT exit_date FROM ruminant_animal_exit_events WHERE farm_id=? AND animal_id=? ORDER BY exit_date DESC,id DESC LIMIT 1");
    $e->execute([$farmId,$animalId]); $exitDate=$e->fetchColumn();
    if($exitDate && $endDate>(string)$exitDate) throw new RuntimeException("Membership cannot extend beyond the animal's recorded exit date (".date('d/m/Y',strtotime((string)$exitDate)).').');
    $pdo->prepare('UPDATE ruminant_animal_cycle_memberships SET end_date=? WHERE id=? AND farm_id=? AND animal_id=?')->execute([$endDate,$membershipId,$farmId,$animalId]);
}

function ruminant_cycle_membership_has_financial_activity(PDO $pdo,int $farmId,int $animalId,int $membershipId): bool
{
    $s=$pdo->prepare("SELECT m.*,LOWER(pc.production_type) species FROM ruminant_animal_cycle_memberships m JOIN production_cycles pc ON pc.id=m.cycle_id AND pc.farm_id=m.farm_id WHERE m.id=? AND m.farm_id=? AND m.animal_id=? LIMIT 1");
    $s->execute([$membershipId,$farmId,$animalId]); $m=$s->fetch(PDO::FETCH_ASSOC);
    if(!$m) throw new RuntimeException('Cycle membership could not be found.');
    $end=$m['end_date'] ?: '9999-12-31';
    $q=$pdo->prepare("SELECT 1 FROM farm_expenses e WHERE e.farm_id=? AND e.farm_type='ruminant' AND e.expense_date BETWEEN ? AND ? AND (e.cycle_id=? OR (e.cycle_id IS NULL AND LOWER(e.production_type)=?)) LIMIT 1");
    $q->execute([$farmId,$m['start_date'],$end,$m['cycle_id'],$m['species']]); if($q->fetchColumn()) return true;
    $q=$pdo->prepare("SELECT 1 FROM stock_transactions t WHERE t.farm_id=? AND t.farm_type='ruminant' AND t.transaction_type='used' AND t.transaction_date BETWEEN ? AND ? AND (t.cycle_id=? OR (t.cycle_id IS NULL AND LOWER(t.production_type)=?)) LIMIT 1");
    $q->execute([$farmId,$m['start_date'],$end,$m['cycle_id'],$m['species']]); return (bool)$q->fetchColumn();
}

function ruminant_cycle_membership_delete(PDO $pdo, int $farmId, int $animalId, int $membershipId): void
{
    $boundary=$pdo->prepare('SELECT closed_by_exit_event_id FROM ruminant_animal_cycle_memberships WHERE id=? AND farm_id=? AND animal_id=? LIMIT 1');
    $boundary->execute([$membershipId,$farmId,$animalId]);
    $closedByExit=$boundary->fetchColumn();
    if($closedByExit!==false && $closedByExit!==null) throw new RuntimeException('This membership is linked to a lifecycle exit boundary and cannot be deleted independently. Review the animal exit history instead.');
    if(ruminant_cycle_membership_has_financial_activity($pdo,$farmId,$animalId,$membershipId)) throw new RuntimeException('This membership has financial activity in its date range and cannot be deleted because that would rewrite historical shared-cost economics. Correct the dates instead.');
    $stmt=$pdo->prepare('DELETE FROM ruminant_animal_cycle_memberships WHERE id=? AND farm_id=? AND animal_id=?');
    $stmt->execute([$membershipId,$farmId,$animalId]);
    if($stmt->rowCount()!==1) throw new RuntimeException('Cycle membership could not be found.');
}

function ruminant_cycle_eligible_animal_ids(PDO $pdo, int $farmId, string $species, string $date, ?int $cycleId): array
{
    $params=[$farmId,strtolower($species),$date,$date];
    $cycleSql='';
    if($cycleId){ $cycleSql=' AND m.cycle_id=?'; $params[]=$cycleId; }
    $sql="SELECT DISTINCT m.animal_id
          FROM ruminant_animal_cycle_memberships m
          JOIN production_cycles pc ON pc.id=m.cycle_id AND pc.farm_id=m.farm_id
          JOIN ruminant_animals a ON a.id=m.animal_id AND a.farm_id=m.farm_id
          WHERE m.farm_id=? AND LOWER(pc.production_type)=?
            AND m.start_date<=? AND (m.end_date IS NULL OR m.end_date>=?)
            {$cycleSql}
            AND NOT EXISTS (
                SELECT 1 FROM ruminant_animal_exit_events xe
                WHERE xe.farm_id=m.farm_id AND xe.animal_id=m.animal_id AND xe.exit_date < ?
            )
          ORDER BY m.animal_id";
    $params[]=$date;
    $stmt=$pdo->prepare($sql); $stmt->execute($params);
    return array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
}
