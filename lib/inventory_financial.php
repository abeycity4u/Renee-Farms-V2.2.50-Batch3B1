<?php
require_once __DIR__ . '/stock_reporting.php';

/**
 * Financial classification for stocked items.
 *
 * Inventory category remains farmer-defined and flexible. This classification
 * is system-controlled and is used only to group purchase/spending intelligence.
 */
function inventory_financial_classifications(): array
{
    return [
        'feed' => 'Feed',
        'medication_vaccine' => 'Medication / Vaccine',
        'supplement' => 'Supplement',
        'consumables' => 'Consumables',
        'equipment_tools' => 'Equipment / Tools',
        'spare_parts' => 'Spare Parts / Maintenance Materials',
        'other_stock' => 'General / Other Stock',
    ];
}

function inventory_financial_classification_is_valid(string $value): bool
{
    return array_key_exists($value, inventory_financial_classifications());
}

function inventory_financial_classification_label(string $value): string
{
    $labels = inventory_financial_classifications();
    return $labels[$value] ?? 'Other Stock';
}

/** Stock financial types whose USED movements are period operating costs. */
function inventory_operating_consumption_classifications(): array
{
    return [
        'medication_vaccine' => 'Medication / Vaccine',
        'supplement' => 'Supplement',
        'consumables' => 'Consumables',
    ];
}

function inventory_financial_classification_is_operating_consumption(string $value): bool
{
    return array_key_exists($value, inventory_operating_consumption_classifications());
}

/** Default operational owner for General / Non-feed inventory. */
function inventory_default_production_types(string $farmType): array
{
    $farmType = strtolower(trim($farmType));
    if ($farmType === 'poultry') {
        return ['layer' => 'Layer', 'broiler' => 'Broiler', 'shared' => 'Shared Poultry'];
    }
    if ($farmType === 'ruminant') {
        return ['cattle' => 'Cattle', 'goat' => 'Goat', 'sheep' => 'Sheep', 'other' => 'Other', 'shared' => 'Shared Ruminant'];
    }
    return ['shared' => 'Shared / Farm-wide'];
}

function inventory_normalize_default_production_type(string $farmType, string $feedCategory, ?string $value): string
{
    $feedCategory = strtolower(trim($feedCategory));
    if ($feedCategory === 'layer') return 'layer';
    if ($feedCategory === 'broiler') return 'broiler';
    if ($feedCategory === 'ruminant') return 'shared';

    $allowed = inventory_default_production_types($farmType);
    $value = strtolower(trim((string)$value));
    return isset($allowed[$value]) ? $value : 'shared';
}

/**
 * Inventory purchase activity for financial/expense screens.
 *
 * A received stock transaction already contains the financial facts of the
 * purchase (business date, quantity, receipt unit cost, and total cost). We
 * surface that same ledger row rather than creating a duplicate farm_expenses
 * row. Purchase totals can be shown as spending, while profitability continues
 * to recognise stocked-item cost from the appropriate consumption logic.
 */
function inventory_financial_receipts(
    PDO $pdo,
    int $farmId,
    string $startDate,
    string $endDate,
    string $farmType,
    ?string $productionType = null
): array {
    $effective = stock_effective_sql_predicate('t');
    $sql = "SELECT t.id, t.transaction_date, t.quantity, t.unit_cost, t.total_cost,
                   t.farm_type, t.production_type, t.attribution_scope, t.cycle_id,
                   t.remarks, t.created_at,
                   s.id AS stock_item_id, s.item_name, s.unit, s.feed_category,
                   COALESCE(NULLIF(t.financial_classification,''), NULLIF(c.financial_type,''), NULLIF(s.financial_classification,''),
                       CASE WHEN s.feed_category IN ('layer','broiler','ruminant') THEN 'feed' ELSE 'other_stock' END
                   ) AS financial_classification,
                   c.category_name, u.full_name AS recorded_by_name,
                   pc.cycle_code
            FROM stock_transactions t
            INNER JOIN stock_items s ON s.id=t.stock_item_id AND s.farm_id=t.farm_id
            LEFT JOIN inventory_categories c ON c.id=s.category_id AND c.farm_id=s.farm_id
            LEFT JOIN users u ON u.id=t.user_id AND u.farm_id=t.farm_id
            LEFT JOIN production_cycles pc ON pc.id=t.cycle_id AND pc.farm_id=t.farm_id
            WHERE t.farm_id=?
              AND t.transaction_type='received'
              AND {$effective}
              AND t.transaction_date BETWEEN ? AND ?
              AND t.farm_type=?";
    $params = [$farmId, $startDate, $endDate, $farmType];

    if ($productionType !== null && $productionType !== '') {
        $sql .= " AND t.production_type=?";
        $params[] = strtolower($productionType);
    }

    $sql .= " ORDER BY t.transaction_date DESC, t.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function inventory_financial_receipt_total(array $rows): float
{
    $total = 0.0;
    foreach ($rows as $row) {
        $total += (float)($row['total_cost'] ?? 0);
    }
    return round($total, 2);
}

/** Aggregate inventory receipts into financial-spending cards. */
function inventory_financial_receipt_category_totals(array $rows): array
{
    $totals = [];
    foreach ($rows as $row) {
        $key = (string)($row['financial_classification'] ?? 'other_stock');
        if (!inventory_financial_classification_is_valid($key)) {
            $key = 'other_stock';
        }
        $totals[$key] = ($totals[$key] ?? 0.0) + (float)($row['total_cost'] ?? 0);
    }
    foreach ($totals as $key => $value) {
        $totals[$key] = round($value, 2);
    }
    return $totals;
}

/**
 * Merge manually-recorded non-stock expenses and inventory purchase spending
 * for display. The resulting totals are spending intelligence only; they do not
 * change the profitability engine.
 */
function inventory_financial_combined_spending_totals(array $manualTotals, array $inventoryTotals): array
{
    $combined = $manualTotals;

    // Manual category -> display category. Historical 'feeds' remains visible
    // for audit, while new feed purchases originate from Inventory.
    $map = [
        'feeds' => 'feed',
        'medication' => 'medication_vaccine',
        'salary' => 'salary',
        'logistic' => 'logistic',
        'fuel' => 'fuel',
        'misc' => 'misc',
    ];
    $normalized = [];
    foreach ($combined as $key => $value) {
        $target = $map[$key] ?? $key;
        $normalized[$target] = ($normalized[$target] ?? 0.0) + (float)$value;
    }
    foreach ($inventoryTotals as $key => $value) {
        $normalized[$key] = ($normalized[$key] ?? 0.0) + (float)$value;
    }
    return $normalized;
}

function inventory_financial_spending_label(string $key): string
{
    $labels = array_merge(inventory_financial_classifications(), [
        'salary' => 'Salary / Wages',
        'logistic' => 'Logistics / Transport',
        'misc' => 'Miscellaneous',
    ]);
    return $labels[$key] ?? ucfirst(str_replace('_', ' ', $key));
}
