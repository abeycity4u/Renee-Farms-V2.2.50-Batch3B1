<?php
/** Structured Poultry Health / Treatment history used by management and diagnostics. */

function poultry_health_event_types(): array
{
    return [
        'vaccination' => 'Vaccination',
        'treatment' => 'Medication / Treatment',
        'deworming' => 'Deworming / Parasite Control',
        'supplement' => 'Vitamin / Supplement',
        'observation' => 'Observation / Symptom',
        'veterinary_review' => 'Veterinary Review',
        'other' => 'Other',
    ];
}

function poultry_health_event_type_label(string $type): string
{
    $types = poultry_health_event_types();
    return $types[$type] ?? ucfirst(str_replace('_', ' ', $type));
}

function poultry_health_validate_cycle(PDO $pdo, int $farmId, int $cycleId, string $productionType, string $eventDate): array
{
    if ($cycleId < 1) {
        throw new InvalidArgumentException('Please select a production cycle.');
    }
    $stmt = $pdo->prepare("SELECT id, cycle_code, production_type, start_date, expected_end_date, status
        FROM production_cycles
        WHERE id=? AND farm_id=? AND farm_type='poultry' LIMIT 1");
    $stmt->execute([$cycleId, $farmId]);
    $cycle = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cycle || strtolower((string)$cycle['production_type']) !== $productionType) {
        throw new InvalidArgumentException('The selected production cycle does not match the selected poultry type.');
    }
    if ($eventDate < (string)$cycle['start_date'] || (!empty($cycle['expected_end_date']) && $eventDate > (string)$cycle['expected_end_date'])) {
        throw new InvalidArgumentException('The event date is outside the selected production cycle dates.');
    }
    return $cycle;
}

function poultry_health_validate_stock_item(PDO $pdo, int $farmId, ?int $stockItemId): ?array
{
    if (($stockItemId ?? 0) < 1) return null;
    $stmt = $pdo->prepare("SELECT si.id, si.item_name, si.unit,
               COALESCE(ic.financial_type, si.financial_classification, 'other_stock') AS financial_type
        FROM stock_items si
        JOIN inventory_categories ic ON ic.id=si.category_id AND ic.farm_id=si.farm_id
        WHERE si.id=? AND si.farm_id=? AND si.is_active=1 AND si.farm_type IN ('poultry','both') LIMIT 1");
    $stmt->execute([(int)$stockItemId, $farmId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$item) throw new InvalidArgumentException('The linked inventory item is not available for this poultry farm.');
    if (!in_array((string)$item['financial_type'], ['medication_vaccine','supplement'], true)) {
        throw new InvalidArgumentException('Only Medication / Vaccine or Supplement inventory items can be linked to a poultry health event.');
    }
    return $item;
}

function poultry_health_list(PDO $pdo, int $farmId, ?string $productionType = null, ?int $cycleId = null, ?string $from = null, ?string $to = null): array
{
    $sql = "SELECT phe.*, pc.cycle_code, si.item_name AS linked_item_name, si.unit AS linked_item_unit,
                   u.full_name AS recorded_by_name
            FROM poultry_health_events phe
            LEFT JOIN production_cycles pc ON pc.id=phe.cycle_id AND pc.farm_id=phe.farm_id
            LEFT JOIN stock_items si ON si.id=phe.stock_item_id AND si.farm_id=phe.farm_id
            LEFT JOIN users u ON u.id=phe.recorded_by AND u.farm_id=phe.farm_id
            WHERE phe.farm_id=?";
    $params = [$farmId];
    if ($productionType !== null && in_array($productionType, ['layer','broiler'], true)) {
        $sql .= " AND phe.production_type=?";
        $params[] = $productionType;
    }
    if (($cycleId ?? 0) > 0) {
        $sql .= " AND phe.cycle_id=?";
        $params[] = $cycleId;
    }
    if ($from) { $sql .= " AND phe.event_date>=?"; $params[] = $from; }
    if ($to) { $sql .= " AND phe.event_date<=?"; $params[] = $to; }
    $sql .= " ORDER BY phe.event_date DESC, phe.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function poultry_health_recent_for_cycle(PDO $pdo, int $farmId, int $cycleId, string $fromDate, string $toDate): array
{
    $stmt = $pdo->prepare("SELECT phe.*, si.item_name AS linked_item_name
        FROM poultry_health_events phe
        LEFT JOIN stock_items si ON si.id=phe.stock_item_id AND si.farm_id=phe.farm_id
        WHERE phe.farm_id=? AND phe.cycle_id=? AND phe.event_date BETWEEN ? AND ?
        ORDER BY phe.event_date ASC, phe.id ASC");
    $stmt->execute([$farmId, $cycleId, $fromDate, $toDate]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Diagnostic health context for one poultry cycle.
 * Exact-cycle events are evidence for the investigation. Same-type events recorded
 * in the same date window but attached to another cycle are returned separately so
 * diagnostics never say "no health event was recorded" when the farm did record one.
 */
function poultry_health_diagnostic_context(PDO $pdo, int $farmId, int $cycleId, string $productionType, string $fromDate, string $toDate): array
{
    $exact = poultry_health_recent_for_cycle($pdo, $farmId, $cycleId, $fromDate, $toDate);

    $stmt = $pdo->prepare("SELECT phe.*, pc.cycle_code, si.item_name AS linked_item_name
        FROM poultry_health_events phe
        LEFT JOIN production_cycles pc ON pc.id=phe.cycle_id AND pc.farm_id=phe.farm_id
        LEFT JOIN stock_items si ON si.id=phe.stock_item_id AND si.farm_id=phe.farm_id
        WHERE phe.farm_id=? AND phe.production_type=? AND phe.event_date BETWEEN ? AND ?
          AND (phe.cycle_id IS NULL OR phe.cycle_id<>?)
        ORDER BY phe.event_date ASC, phe.id ASC");
    $stmt->execute([$farmId, $productionType, $fromDate, $toDate, $cycleId]);
    $otherCycle = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return ['exact_cycle' => $exact, 'other_cycle' => $otherCycle];
}
