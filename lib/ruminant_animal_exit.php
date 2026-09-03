<?php
require_once __DIR__.'/ruminant_lifecycle_service.php';
/**
 * V2.2.48 — explicit ruminant lifecycle exits tied to sale attribution.
 *
 * Revenue allocation and lifecycle are deliberately separate. A selected
 * animal stays active unless the user explicitly records Sold live or
 * Culled/slaughtered. Exit changes are applied in the same DB transaction as
 * the sale. Reversal is conservative: we never overwrite a later status change.
 */

function ruminant_exit_outcome_label(string $outcome): string
{
    return match ($outcome) {
        'sold_live' => 'Sold live',
        'culled_slaughtered' => 'Culled / slaughtered',
        default => 'Revenue only — remains in current status',
    };
}

function ruminant_sale_exit_events_for_sales(PDO $pdo, int $farmId, array $saleIds): array
{
    $saleIds = array_values(array_unique(array_filter(array_map('intval', $saleIds), static fn($id) => $id > 0)));
    if (!$saleIds) return [];
    $ph = implode(',', array_fill(0, count($saleIds), '?'));
    $stmt = $pdo->prepare("SELECT e.*, r.tag_no
        FROM ruminant_animal_exit_events e
        JOIN ruminant_animals r ON r.id=e.animal_id AND r.farm_id=e.farm_id
        WHERE e.farm_id=? AND e.sale_id IN ($ph)
        ORDER BY e.sale_id,e.animal_id");
    $stmt->execute(array_merge([$farmId], $saleIds));
    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[(int)$row['sale_id']][(int)$row['animal_id']] = $row;
    }
    return $map;
}

function ruminant_sale_apply_exit_outcomes(
    PDO $pdo,
    int $farmId,
    int $saleId,
    string $saleDate,
    array $animalAllocation,
    array $input,
    ?int $recordedBy
): void {
    $selectedIds = [];
    foreach (($animalAllocation['rows'] ?? []) as $row) $selectedIds[(int)$row['animal_id']] = true;

    $existingStmt = $pdo->prepare('SELECT * FROM ruminant_animal_exit_events WHERE farm_id=? AND sale_id=? FOR UPDATE');
    $existingStmt->execute([$farmId, $saleId]);
    $existing = [];
    foreach ($existingStmt->fetchAll(PDO::FETCH_ASSOC) as $row) $existing[(int)$row['animal_id']] = $row;

    // If an edit removes an animal or converts the sale to shared revenue,
    // reverse only the lifecycle projection created by this same sale.
    foreach ($existing as $animalId => $event) {
        if (isset($selectedIds[$animalId])) continue;
        $animalStmt = $pdo->prepare('SELECT status FROM ruminant_animals WHERE id=? AND farm_id=? FOR UPDATE');
        $animalStmt->execute([$animalId, $farmId]);
        $current = $animalStmt->fetchColumn();
        if ($current === false) throw new RuntimeException('An animal linked to this sale no longer exists.');
        if ((string)$current !== (string)$event['resulting_status']) {
            throw new RuntimeException('This sale cannot remove an animal exit because that animal status was changed afterwards. Review the Animal Registry first.');
        }
        $pdo->prepare('UPDATE ruminant_animals SET status=?, updated_at=NOW() WHERE id=? AND farm_id=?')
            ->execute([(string)$event['previous_status'], $animalId, $farmId]);
        ruminant_lifecycle_reverse_exit_boundary($pdo,$farmId,$animalId,(int)$event['id']);
        $pdo->prepare('DELETE FROM ruminant_animal_exit_events WHERE id=? AND farm_id=?')->execute([(int)$event['id'], $farmId]);
    }

    $outcomes = $input['sale_animal_exit_outcomes'] ?? [];
    if (!is_array($outcomes)) $outcomes = [];

    foreach (array_keys($selectedIds) as $animalId) {
        $outcome = strtolower(trim((string)($outcomes[$animalId] ?? 'remain_active')));
        if (!in_array($outcome, ['remain_active','sold_live','culled_slaughtered'], true)) {
            throw new RuntimeException('Choose a valid animal sale/lifecycle outcome.');
        }
        $oldEvent = $existing[$animalId] ?? null;

        $animalStmt = $pdo->prepare('SELECT tag_no,status FROM ruminant_animals WHERE id=? AND farm_id=? FOR UPDATE');
        $animalStmt->execute([$animalId, $farmId]);
        $animal = $animalStmt->fetch(PDO::FETCH_ASSOC);
        if (!$animal) throw new RuntimeException('Selected animal could not be found.');

        if ($outcome === 'remain_active') {
            if ($oldEvent) {
                if ((string)$animal['status'] !== (string)$oldEvent['resulting_status']) {
                    throw new RuntimeException('Cannot reverse the sale exit for '.$animal['tag_no'].' because its status changed afterwards. Review the Animal Registry first.');
                }
                $pdo->prepare('UPDATE ruminant_animals SET status=?, updated_at=NOW() WHERE id=? AND farm_id=?')
                    ->execute([(string)$oldEvent['previous_status'], $animalId, $farmId]);
                ruminant_lifecycle_reverse_exit_boundary($pdo,$farmId,$animalId,(int)$oldEvent['id']);
                $pdo->prepare('DELETE FROM ruminant_animal_exit_events WHERE id=? AND farm_id=?')->execute([(int)$oldEvent['id'], $farmId]);
            }
            continue;
        }

        $newStatus = $outcome === 'sold_live' ? 'sold' : 'culled';
        if ($oldEvent) {
            if ((string)$animal['status'] !== (string)$oldEvent['resulting_status']) {
                throw new RuntimeException('Cannot change the sale exit for '.$animal['tag_no'].' because its status changed afterwards. Review the Animal Registry first.');
            }
            $previousStatus = (string)$oldEvent['previous_status'];
        } else {
            if ((string)$animal['status'] !== 'active') {
                throw new RuntimeException($animal['tag_no'].' is currently '.ucfirst((string)$animal['status']).'. Only an active animal can be exited by a new sale. Use Revenue only if this sale is proceeds/by-product revenue for an animal that already exited.');
            }
            $previousStatus = (string)$animal['status'];
        }

        $pdo->prepare('UPDATE ruminant_animals SET status=?, updated_at=NOW() WHERE id=? AND farm_id=?')
            ->execute([$newStatus, $animalId, $farmId]);

        if ($oldEvent) {
            $pdo->prepare('UPDATE ruminant_animal_exit_events SET exit_date=?,exit_outcome=?,previous_status=?,resulting_status=?,recorded_by=?,updated_at=NOW() WHERE id=? AND farm_id=?')
                ->execute([$saleDate,$outcome,$previousStatus,$newStatus,$recordedBy ?: null,(int)$oldEvent['id'],$farmId]);
            $exitEventId=(int)$oldEvent['id'];
        } else {
            $pdo->prepare('INSERT INTO ruminant_animal_exit_events (farm_id,animal_id,sale_id,exit_date,exit_outcome,previous_status,resulting_status,recorded_by) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$farmId,$animalId,$saleId,$saleDate,$outcome,$previousStatus,$newStatus,$recordedBy ?: null]);
            $exitEventId=(int)$pdo->lastInsertId();
        }
        ruminant_lifecycle_apply_exit_boundary($pdo,$farmId,$animalId,$exitEventId,$saleDate);
    }
}

function ruminant_sale_reverse_exit_events(PDO $pdo, int $farmId, int $saleId): void
{
    $stmt = $pdo->prepare('SELECT * FROM ruminant_animal_exit_events WHERE farm_id=? AND sale_id=? ORDER BY id DESC FOR UPDATE');
    $stmt->execute([$farmId,$saleId]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($events as $event) {
        $animalStmt = $pdo->prepare('SELECT tag_no,status FROM ruminant_animals WHERE id=? AND farm_id=? FOR UPDATE');
        $animalStmt->execute([(int)$event['animal_id'],$farmId]);
        $animal = $animalStmt->fetch(PDO::FETCH_ASSOC);
        if (!$animal) continue;
        if ((string)$animal['status'] !== (string)$event['resulting_status']) {
            throw new RuntimeException('Sale cannot be deleted because '.$animal['tag_no'].' had a later status change after this sale exit. Review the Animal Registry first.');
        }
        $pdo->prepare('UPDATE ruminant_animals SET status=?, updated_at=NOW() WHERE id=? AND farm_id=?')
            ->execute([(string)$event['previous_status'],(int)$event['animal_id'],$farmId]);
        ruminant_lifecycle_reverse_exit_boundary($pdo,$farmId,(int)$event['animal_id'],(int)$event['id']);
    }
    $pdo->prepare('DELETE FROM ruminant_animal_exit_events WHERE farm_id=? AND sale_id=?')->execute([$farmId,$saleId]);
}
