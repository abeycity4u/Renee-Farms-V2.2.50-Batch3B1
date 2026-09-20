<?php

declare(strict_types=1);

require_once __DIR__
    . '/../includes/permission_catalog.php';

require_once __DIR__
    . '/inventory_financial.php';


/*
 * V3.0.1 Poultry Expense Workspace
 *
 * Read-model / UI workspace authority for the consolidated Poultry Expenses
 * page. Creation authority remains poultry_expense_entry.php.
 *
 * This service:
 * - reads only Poultry manual expense rows;
 * - resolves legacy Layer/Broiler production identity;
 * - filters every row through canonical operational View authority;
 * - keeps Shared as one physical parent row;
 * - never creates allocation or expense mutations.
 */


if (!function_exists(
    'poultry_expense_workspace_row_production_type'
)) {
function poultry_expense_workspace_row_production_type(
    array $row
): string {
    $productionType =
        strtolower(
            trim(
                (string)(
                    $row[
                        'production_type'
                    ]
                    ?? ''
                )
            )
        );

    if (
        in_array(
            $productionType,
            [
                'layer',
                'broiler',
                'shared',
            ],
            true
        )
    ) {
        return $productionType;
    }

    $poultryCategory =
        strtolower(
            trim(
                (string)(
                    $row[
                        'poultry_category'
                    ]
                    ?? ''
                )
            )
        );

    return
        in_array(
            $poultryCategory,
            [
                'layer',
                'broiler',
            ],
            true
        )
            ? $poultryCategory
            : '';
}
}


if (!function_exists(
    'poultry_expense_workspace_production_row'
)) {
function poultry_expense_workspace_production_row(
    string $productionType
): array {
    $productionType =
        strtolower(
            trim(
                $productionType
            )
        );

    return [
        'farm_type' =>
            'poultry',

        'production_type' =>
            $productionType,

        'poultry_category' =>
            in_array(
                $productionType,
                [
                    'layer',
                    'broiler',
                ],
                true
            )
                ? $productionType
                : null,
    ];
}
}


if (!function_exists(
    'poultry_expense_workspace_can_view_production'
)) {
function poultry_expense_workspace_can_view_production(
    string $productionType
): bool {
    return
        permission_catalog_expense_operational_can(
            poultry_expense_workspace_production_row(
                $productionType
            ),
            'view'
        );
}
}


if (!function_exists(
    'poultry_expense_workspace_tabs'
)) {
function poultry_expense_workspace_tabs(): array
{
    $tabs = [];

    $canLayer =
        poultry_expense_workspace_can_view_production(
            'layer'
        );

    $canBroiler =
        poultry_expense_workspace_can_view_production(
            'broiler'
        );

    $canShared =
        poultry_expense_workspace_can_view_production(
            'shared'
        );

    if (
        $canLayer
        ||
        $canBroiler
        ||
        $canShared
    ) {
        $tabs['all'] =
            'All';
    }

    if ($canLayer) {
        $tabs['layer'] =
            'Layer';
    }

    if ($canBroiler) {
        $tabs['broiler'] =
            'Broiler';
    }

    if ($canShared) {
        $tabs['shared'] =
            'Shared';
    }

    return $tabs;
}
}


if (!function_exists(
    'poultry_expense_workspace_resolve_tab'
)) {
function poultry_expense_workspace_resolve_tab(
    ?string $requested,
    array $tabs
): string {
    $requested =
        strtolower(
            trim(
                (string)$requested
            )
        );

    if (
        $requested !== ''
        &&
        array_key_exists(
            $requested,
            $tabs
        )
    ) {
        return $requested;
    }

    if (
        array_key_exists(
            'all',
            $tabs
        )
    ) {
        return 'all';
    }

    $keys =
        array_keys(
            $tabs
        );

    return
        (string)(
            $keys[0]
            ?? ''
        );
}
}


if (!function_exists(
    'poultry_expense_workspace_rows'
)) {
function poultry_expense_workspace_rows(
    PDO $pdo,
    int $farmId,
    string $startDate,
    string $endDate
): array {
    if ($farmId < 1) {
        throw new InvalidArgumentException(
            'Farm identity is invalid.'
        );
    }

    $stmt =
        $pdo->prepare(
            "SELECT
                 e.*,
                 u.full_name AS recorded_by_name,
                 u.user_type AS recorded_by_user_type,
                 pc.cycle_code AS expense_cycle_code
             FROM farm_expenses e
             LEFT JOIN users u
               ON u.id = e.user_id
              AND u.farm_id = e.farm_id
             LEFT JOIN production_cycles pc
               ON pc.id = e.cycle_id
              AND pc.farm_id = e.farm_id
             WHERE e.farm_id = ?
               AND e.expense_date BETWEEN ? AND ?
               AND e.farm_type = 'poultry'
             ORDER BY
                 e.expense_date DESC,
                 e.id DESC"
        );

    $stmt->execute([
        $farmId,
        $startDate,
        $endDate,
    ]);

    $rows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    $visible = [];

    foreach ($rows as $row) {
        $productionType =
            poultry_expense_workspace_row_production_type(
                $row
            );

        if ($productionType === '') {
            continue;
        }

        $row['workspace_production_type'] =
            $productionType;

        /*
         * Never depend on the requested tab for security. Every candidate row
         * is independently filtered through canonical operational View
         * authority before it reaches the page.
         */
        if (
            !permission_catalog_expense_operational_can(
                $row,
                'view'
            )
        ) {
            continue;
        }

        $visible[] =
            $row;
    }

    return $visible;
}
}


if (!function_exists(
    'poultry_expense_workspace_filter_rows'
)) {
function poultry_expense_workspace_filter_rows(
    array $rows,
    string $tab
): array {
    if ($tab === 'all') {
        return
            array_values(
                $rows
            );
    }

    return
        array_values(
            array_filter(
                $rows,
                static function (
                    array $row
                ) use (
                    $tab
                ): bool {
                    return
                        (
                            $row[
                                'workspace_production_type'
                            ]
                            ?? ''
                        )
                        === $tab;
                }
            )
        );
}
}


if (!function_exists(
    'poultry_expense_workspace_totals'
)) {
function poultry_expense_workspace_totals(
    array $rows
): array {
    $totals = [
        'all' =>
            0.0,

        'layer' =>
            0.0,

        'broiler' =>
            0.0,

        'shared' =>
            0.0,
    ];

    foreach ($rows as $row) {
        $lineTotal =
            round(
                (
                    (float)(
                        $row[
                            'amount'
                        ]
                        ?? 0
                    )
                )
                *
                (
                    (float)(
                        $row[
                            'unit'
                        ]
                        ?? 1
                    )
                ),
                2
            );

        $productionType =
            (string)(
                $row[
                    'workspace_production_type'
                ]
                ?? ''
            );

        $totals['all'] +=
            $lineTotal;

        if (
            array_key_exists(
                $productionType,
                $totals
            )
        ) {
            $totals[
                $productionType
            ] +=
                $lineTotal;
        }
    }

    foreach ($totals as $key => $value) {
        $totals[$key] =
            round(
                $value,
                2
            );
    }

    return $totals;
}
}


if (!function_exists(
    'poultry_expense_workspace_manual_category_totals'
)) {
function poultry_expense_workspace_manual_category_totals(
    array $rows
): array {
    $totals = [];

    foreach ($rows as $row) {
        $category =
            trim(
                (string)(
                    $row[
                        'category'
                    ]
                    ?? 'misc'
                )
            );

        if ($category === '') {
            $category =
                'misc';
        }

        $lineTotal =
            round(
                (
                    (float)(
                        $row[
                            'amount'
                        ]
                        ?? 0
                    )
                )
                *
                (
                    (float)(
                        $row[
                            'unit'
                        ]
                        ?? 1
                    )
                ),
                2
            );

        $totals[
            $category
        ] =
            (
                $totals[
                    $category
                ]
                ?? 0.0
            )
            +
            $lineTotal;
    }

    foreach ($totals as $category => $value) {
        $totals[
            $category
        ] =
            round(
                (float)$value,
                2
            );
    }

    return $totals;
}
}


if (!function_exists(
    'poultry_expense_workspace_inventory_purchase_production_type'
)) {
function poultry_expense_workspace_inventory_purchase_production_type(
    array $row
): string {
    $productionType =
        strtolower(
            trim(
                (string)(
                    $row[
                        'production_type'
                    ]
                    ?? ''
                )
            )
        );

    return
        in_array(
            $productionType,
            [
                'layer',
                'broiler',
                'shared',
            ],
            true
        )
            ? $productionType
            : '';
}
}


if (!function_exists(
    'poultry_expense_workspace_filter_inventory_purchases'
)) {
function poultry_expense_workspace_filter_inventory_purchases(
    array $rows,
    string $tab,
    array $visibleProductionTypes
): array {
    $tab =
        strtolower(
            trim(
                $tab
            )
        );

    if (
        !in_array(
            $tab,
            [
                'all',
                'layer',
                'broiler',
                'shared',
            ],
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Unsupported Poultry spending tab.'
        );
    }

    $visibleProductionTypes =
        array_values(
            array_unique(
                array_values(
                    array_filter(
                        array_map(
                            static function (
                                $value
                            ): string {
                                $value =
                                    strtolower(
                                        trim(
                                            (string)$value
                                        )
                                    );

                                return
                                    in_array(
                                        $value,
                                        [
                                            'layer',
                                            'broiler',
                                            'shared',
                                        ],
                                        true
                                    )
                                        ? $value
                                        : '';
                            },
                            $visibleProductionTypes
                        ),
                        static fn (
                            string $value
                        ): bool =>
                            $value !== ''
                    )
                )
            )
        );

    return
        array_values(
            array_filter(
                $rows,
                static function (
                    array $row
                ) use (
                    $tab,
                    $visibleProductionTypes
                ): bool {
                    $productionType =
                        poultry_expense_workspace_inventory_purchase_production_type(
                            $row
                        );

                    if (
                        $productionType === ''
                        ||
                        !in_array(
                            $productionType,
                            $visibleProductionTypes,
                            true
                        )
                    ) {
                        return false;
                    }

                    return
                        $tab === 'all'
                        ||
                        $productionType === $tab;
                }
            )
        );
}
}


if (!function_exists(
    'poultry_expense_workspace_spending_view'
)) {
function poultry_expense_workspace_spending_view(
    PDO $pdo,
    int $farmId,
    string $startDate,
    string $endDate,
    string $tab,
    array $workspaceRows
): array {
    $tab =
        strtolower(
            trim(
                $tab
            )
        );

    if (
        !in_array(
            $tab,
            [
                'all',
                'layer',
                'broiler',
                'shared',
            ],
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Unsupported Poultry spending tab.'
        );
    }

    /*
     * Resolve production visibility once from the same canonical operational
     * View authority that owns the workspace tabs and manual expense rows.
     *
     * Shared therefore remains Layer View AND Broiler View.
     */
    $visibleProductionTypes = [];

    foreach (
        [
            'layer',
            'broiler',
            'shared',
        ]
        as $productionType
    ) {
        if (
            poultry_expense_workspace_can_view_production(
                $productionType
            )
        ) {
            $visibleProductionTypes[] =
                $productionType;
        }
    }

    if (
        $tab === 'all'
        &&
        $visibleProductionTypes === []
    ) {
        throw new RuntimeException(
            'You do not have permission to view this Poultry expense area.'
        );
    }

    if (
        $tab !== 'all'
        &&
        !in_array(
            $tab,
            $visibleProductionTypes,
            true
        )
    ) {
        throw new RuntimeException(
            'You do not have permission to view this Poultry expense area.'
        );
    }

    /*
     * Manual rows have already passed row-level View authority in
     * poultry_expense_workspace_rows(). The All filter therefore counts each
     * visible parent exactly once; exact tabs select only their recorded
     * production owner.
     */
    $manualExpenses =
        poultry_expense_workspace_filter_rows(
            $workspaceRows,
            $tab
        );

    $manualCategoryTotals =
        poultry_expense_workspace_manual_category_totals(
            $manualExpenses
        );

    $manualExpenseTotal =
        round(
            array_sum(
                $manualCategoryTotals
            ),
            2
        );

    /*
     * Inventory remains the sole purchase/cash source.
     *
     * All performs one Poultry receipt read and then filters every returned
     * row through recorded production ownership + current visibility.
     *
     * Shared passes the literal "shared" filter, so Layer/Broiler purchases
     * are never inferred or copied into the Shared tab.
     */
    $inventoryProductionType =
        $tab === 'all'
            ? null
            : $tab;

    $inventoryPurchases =
        inventory_financial_receipts(
            $pdo,
            $farmId,
            $startDate,
            $endDate,
            'poultry',
            $inventoryProductionType
        );

    $inventoryPurchases =
        poultry_expense_workspace_filter_inventory_purchases(
            $inventoryPurchases,
            $tab,
            $visibleProductionTypes
        );

    $inventoryPurchaseTotal =
        inventory_financial_receipt_total(
            $inventoryPurchases
        );

    $inventoryCategoryTotals =
        inventory_financial_receipt_category_totals(
            $inventoryPurchases
        );

    $spendingCategoryTotals =
        inventory_financial_combined_spending_totals(
            $manualCategoryTotals,
            $inventoryCategoryTotals
        );

    $totalSpending =
        round(
            $manualExpenseTotal
            +
            $inventoryPurchaseTotal,
            2
        );

    return [
        'tab' =>
            $tab,

        'visible_production_types' =>
            $visibleProductionTypes,

        'manual_expenses' =>
            $manualExpenses,

        'manual_category_totals' =>
            $manualCategoryTotals,

        'manual_expense_total' =>
            $manualExpenseTotal,

        'inventory_purchases' =>
            $inventoryPurchases,

        'inventory_purchase_total' =>
            $inventoryPurchaseTotal,

        'inventory_category_totals' =>
            $inventoryCategoryTotals,

        'spending_category_totals' =>
            $spendingCategoryTotals,

        'total_spending' =>
            $totalSpending,
    ];
}
}


if (!function_exists(
    'poultry_expense_workspace_cycles'
)) {
function poultry_expense_workspace_cycles(
    PDO $pdo,
    int $farmId
): array {
    if ($farmId < 1) {
        throw new InvalidArgumentException(
            'Farm identity is invalid.'
        );
    }

    $stmt =
        $pdo->prepare(
            "SELECT
                 id,
                 production_type,
                 cycle_code,
                 status,
                 start_date
             FROM production_cycles
             WHERE farm_id = ?
               AND farm_type = 'poultry'
               AND production_type IN ('layer','broiler')
             ORDER BY
                 start_date DESC,
                 id DESC"
        );

    $stmt->execute([
        $farmId,
    ]);

    $grouped = [
        'layer' =>
            [],

        'broiler' =>
            [],
    ];

    foreach (
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        )
        as $row
    ) {
        $productionType =
            strtolower(
                trim(
                    (string)(
                        $row[
                            'production_type'
                        ]
                        ?? ''
                    )
                )
            );

        if (
            !array_key_exists(
                $productionType,
                $grouped
            )
        ) {
            continue;
        }

        $grouped[
            $productionType
        ][] =
            $row;
    }

    return $grouped;
}
}
