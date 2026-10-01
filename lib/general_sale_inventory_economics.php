<?php

require_once __DIR__ . '/stock_reporting.php';

/**
 * Canonical COGS reader for ordinary General Inventory-backed Sales.
 *
 * General / Other Stock remains excluded from normal operating consumption.
 * Only effective USED movements owned by a General sale become COGS here.
 */
function general_sale_inventory_economics_summary(
    PDO $pdo,
    int $farmId,
    string $startDate,
    string $endDate,
    string $farmType = 'all',
    ?string $productionType = null,
    ?int $cycleId = null
): array {
    $farmType = strtolower(trim($farmType));
    $productionType = strtolower(trim((string)$productionType));

    if (
        !in_array($farmType, ['all', 'general'], true)
        ||
        ($productionType !== '' && $productionType !== 'general')
        ||
        (int)($cycleId ?? 0) > 0
    ) {
        return [
            'general_sale_inventory_cogs' => 0.0,
            'movement_count' => 0,
        ];
    }

    if ($farmId < 1) {
        throw new InvalidArgumentException(
            'General-sale Inventory economic farm identity is invalid.'
        );
    }

    $effective = stock_effective_sql_predicate('t');

    $stmt = $pdo->prepare(
        "SELECT
             t.id AS stock_transaction_id,
             t.stock_item_id,
             t.source_id AS sale_id,
             t.transaction_date,
             t.quantity,
             t.unit_cost,
             t.total_cost,
             s.stock_item_id AS sale_stock_item_id,
             s.sale_date,
             s.farm_type AS sale_farm_type,
             s.production_type AS sale_production_type,
             s.cycle_id AS sale_cycle_id,
             si.farm_type AS item_farm_type,
             COALESCE(
                 NULLIF(ic.inventory_role,''),
                 'operational'
             ) AS inventory_role
         FROM stock_transactions t
         INNER JOIN sales_records s
             ON s.id=t.source_id
            AND s.farm_id=t.farm_id
         INNER JOIN stock_items si
             ON si.id=t.stock_item_id
            AND si.farm_id=t.farm_id
         INNER JOIN inventory_categories ic
             ON ic.id=si.category_id
            AND ic.farm_id=si.farm_id
         WHERE t.farm_id=?
           AND t.transaction_type='used'
           AND t.source_type='general_sale'
           AND {$effective}
           AND t.transaction_date BETWEEN ? AND ?
         ORDER BY t.transaction_date,t.id"
    );

    $stmt->execute([
        $farmId,
        $startDate,
        $endDate,
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $seenSales = [];
    $cents = 0;

    foreach ($rows as $row) {
        $saleId = (int)($row['sale_id'] ?? 0);

        if ($saleId < 1 || isset($seenSales[$saleId])) {
            throw new RuntimeException(
                'General-sale Inventory COGS source identity is inconsistent.'
            );
        }

        $seenSales[$saleId] = true;

        if (
            (int)($row['sale_stock_item_id'] ?? 0)
                !== (int)($row['stock_item_id'] ?? 0)
            ||
            strtolower(trim((string)($row['sale_farm_type'] ?? '')))
                !== 'general'
            ||
            strtolower(trim((string)($row['sale_production_type'] ?? '')))
                !== 'general'
            ||
            (int)($row['sale_cycle_id'] ?? 0) > 0
            ||
            strtolower(trim((string)($row['item_farm_type'] ?? '')))
                !== 'general'
            ||
            strtolower(trim((string)($row['inventory_role'] ?? '')))
                !== 'operational'
            ||
            (string)($row['sale_date'] ?? '')
                !== (string)($row['transaction_date'] ?? '')
        ) {
            throw new RuntimeException(
                'General-sale Inventory COGS provenance is inconsistent.'
            );
        }

        $cost = $row['total_cost'] ?? null;

        if (
            $cost === null
            || $cost === ''
            || !is_numeric($cost)
            || (float)$cost < 0
        ) {
            throw new RuntimeException(
                'General-sale Inventory movement has no valid frozen cost.'
            );
        }

        $cents += (int)round(((float)$cost) * 100);
    }

    return [
        'general_sale_inventory_cogs' =>
            round($cents / 100, 2),
        'movement_count' =>
            count($rows),
    ];
}
