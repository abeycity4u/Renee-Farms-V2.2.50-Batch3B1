<?php

require_once __DIR__
    . '/stock_reporting.php';

require_once __DIR__
    . '/stock_costing.php';

require_once __DIR__
    . '/inventory_financial.php';

/*
 * V3.0.1 Canonical Consumed-Stock Economics Reader
 *
 * Business hierarchy:
 *
 *   farm -> module -> production type -> cycle
 *
 * A stock movement's total_cost belongs to its recorded parent scope.
 * Explicit stock_consumption_allocations decompose that parent into narrower
 * cycle attribution without creating new cost.
 *
 * Reader contract:
 *
 * - Farm-wide reporting counts each effective parent movement exactly once.
 * - A report at the movement's native scope counts the full parent once.
 * - A report narrower than the movement's native scope counts only explicit
 *   allocation rows whose target lies inside that requested scope.
 * - Cycle reporting counts directly-attributed movements plus explicit
 *   allocation rows targeting that cycle.
 * - Optional decomposition mode exposes explicit child allocations plus the
 *   visible unallocated remainder while preserving the parent total.
 *
 * This file is read-only. It owns no transaction and performs no mutation.
 */

if (!function_exists(
    'stock_consumption_economics_money_cents'
)) {
function stock_consumption_economics_money_cents(
    $value
): int {
    if (
        $value === null
        ||
        $value === ''
        ||
        !is_numeric($value)
    ) {
        throw new InvalidArgumentException(
            'Consumed-stock economic amount is invalid.'
        );
    }

    return
        (int)round(
            ((float)$value) * 100
        );
}
}

if (!function_exists(
    'stock_consumption_economics_money'
)) {
function stock_consumption_economics_money(
    int $cents
): string {
    return
        number_format(
            $cents / 100,
            2,
            '.',
            ''
        );
}
}

if (!function_exists(
    'stock_consumption_economics_scope'
)) {
function stock_consumption_economics_scope(
    string $farmType = 'all',
    ?string $productionType = null,
    ?int $cycleId = null
): array {
    $farmType =
        strtolower(
            trim($farmType)
        );

    if ($farmType === '') {
        $farmType = 'all';
    }

    if (
        !in_array(
            $farmType,
            [
                'all',
                'poultry',
                'ruminant',
                'general',
            ],
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Consumed-stock economic farm scope is invalid.'
        );
    }

    $productionType =
        strtolower(
            trim(
                (string)$productionType
            )
        );

    if ($productionType === 'all') {
        $productionType = '';
    }

    /*
     * IMPORTANT:
     *
     * "shared" is a real production/source attribution and must remain
     * distinguishable from the whole Poultry/Ruminant module.
     *
     * Unallocated remainder is an allocation state, not a production type.
     * Do not collapse production_type=shared into module scope here.
     */

    $cycleId =
        (int)(
            $cycleId
            ?? 0
        );

    if ($cycleId > 0) {
        $level = 'cycle';

    } elseif ($productionType !== '') {
        $level = 'production';

    } elseif ($farmType !== 'all') {
        $level = 'farm_type';

    } else {
        $level = 'farm';
    }

    return [
        'level' =>
            $level,

        'farm_type' =>
            $farmType,

        'production_type' =>
            $productionType,

        'cycle_id' =>
            $cycleId > 0
                ? $cycleId
                : null,
    ];
}
}

if (!function_exists(
    'stock_consumption_economics_parent_matches_scope'
)) {
function stock_consumption_economics_parent_matches_scope(
    array $parent,
    array $scope
): bool {
    $level =
        (string)$scope['level'];

    if ($level === 'farm') {
        return true;
    }

    if ($level === 'cycle') {
        return
            (int)(
                $parent['cycle_id']
                ?? 0
            )
            ===
            (int)$scope['cycle_id'];
    }

    $parentFarmType =
        strtolower(
            trim(
                (string)(
                    $parent['farm_type']
                    ?? ''
                )
            )
        );

    if (
        $scope['farm_type'] !== 'all'
        &&
        $parentFarmType
            !== $scope['farm_type']
    ) {
        return false;
    }

    if ($level === 'farm_type') {
        return true;
    }

    return
        strtolower(
            trim(
                (string)(
                    $parent['production_type']
                    ?? ''
                )
            )
        )
        ===
        $scope['production_type'];
}
}

if (!function_exists(
    'stock_consumption_economics_target_matches_scope'
)) {
function stock_consumption_economics_target_matches_scope(
    array $allocation,
    array $scope
): bool {
    $level =
        (string)$scope['level'];

    if ($level === 'farm') {
        return true;
    }

    if ($level === 'cycle') {
        return
            (int)(
                $allocation['cycle_id']
                ?? 0
            )
            ===
            (int)$scope['cycle_id'];
    }

    $targetFarmType =
        strtolower(
            trim(
                (string)(
                    $allocation[
                        'target_farm_type'
                    ]
                    ?? ''
                )
            )
        );

    if (
        $scope['farm_type'] !== 'all'
        &&
        $targetFarmType
            !== $scope['farm_type']
    ) {
        return false;
    }

    if ($level === 'farm_type') {
        return true;
    }

    return
        strtolower(
            trim(
                (string)(
                    $allocation[
                        'target_production_type'
                    ]
                    ?? ''
                )
            )
        )
        ===
        $scope['production_type'];
}
}

if (!function_exists(
    'stock_consumption_economics_output_row'
)) {
function stock_consumption_economics_output_row(
    array $parent,
    int $amountCents,
    string $mode,
    ?array $allocation = null
): array {
    $row =
        $parent;

    $row['economic_amount'] =
        stock_consumption_economics_money(
            $amountCents
        );

    $row['economic_amount_cents'] =
        $amountCents;

    $row['attribution_mode'] =
        $mode;

    $row['allocation_id'] =
        $allocation === null
            ? null
            : (
                isset(
                    $allocation[
                        'allocation_id'
                    ]
                )
                    ? (int)$allocation[
                        'allocation_id'
                    ]
                    : null
            );

    $row['allocation_revision_no'] =
        $allocation === null
            ? null
            : (
                isset(
                    $allocation[
                        'allocation_revision_no'
                    ]
                )
                    ? (int)$allocation[
                        'allocation_revision_no'
                    ]
                    : null
            );

    $row['target_cycle_id'] =
        $allocation === null
            ? null
            : (
                isset(
                    $allocation[
                        'cycle_id'
                    ]
                )
                    ? (int)$allocation[
                        'cycle_id'
                    ]
                    : null
            );

    $row['target_farm_type'] =
        $allocation[
            'target_farm_type'
        ]
        ?? null;

    $row['target_production_type'] =
        $allocation[
            'target_production_type'
        ]
        ?? null;

    $row['target_cycle_code'] =
        $allocation[
            'target_cycle_code'
        ]
        ?? null;

    $row['target_cycle_start_date'] =
        $allocation[
            'target_cycle_start_date'
        ]
        ?? null;

    $row['effective_cycle_id'] =
        $allocation !== null
            ? (
                isset(
                    $allocation[
                        'cycle_id'
                    ]
                )
                    ? (int)$allocation[
                        'cycle_id'
                    ]
                    : null
            )
            : (
                isset(
                    $parent['cycle_id']
                )
                &&
                $parent['cycle_id'] !== null
                &&
                $parent['cycle_id'] !== ''
                    ? (int)$parent[
                        'cycle_id'
                    ]
                    : null
            );

    return $row;
}
}

if (!function_exists(
    'stock_consumption_economics_select_rows'
)) {
function stock_consumption_economics_select_rows(
    array $parents,
    array $allocations,
    array $scope,
    bool $decomposeNativeAllocations = false
): array {
    $parentMap = [];

    foreach ($parents as $parent) {
        if (!is_array($parent)) {
            throw new RuntimeException(
                'Consumed-stock economic parent row is invalid.'
            );
        }

        $parentId =
            (int)(
                $parent[
                    'stock_transaction_id'
                ]
                ?? $parent['id']
                ?? 0
            );

        if ($parentId < 1) {
            throw new RuntimeException(
                'Consumed-stock economic parent identity is invalid.'
            );
        }

        if (isset($parentMap[$parentId])) {
            throw new RuntimeException(
                'Consumed-stock economic parent identity is duplicated.'
            );
        }

        $parent[
            'stock_transaction_id'
        ] = $parentId;

        $parentMap[$parentId] =
            $parent;
    }

    $allocationMap = [];

    foreach ($allocations as $allocation) {
        if (!is_array($allocation)) {
            throw new RuntimeException(
                'Consumed-stock economic allocation row is invalid.'
            );
        }

        $parentId =
            (int)(
                $allocation[
                    'stock_transaction_id'
                ]
                ?? 0
            );

        if (
            $parentId < 1
            ||
            !isset(
                $parentMap[$parentId]
            )
        ) {
            throw new RuntimeException(
                'Consumed-stock economic allocation parent is invalid.'
            );
        }

        $cycleId =
            (int)(
                $allocation[
                    'cycle_id'
                ]
                ?? 0
            );

        if ($cycleId < 1) {
            throw new RuntimeException(
                'Consumed-stock economic allocation target is invalid.'
            );
        }

        if (
            isset(
                $allocationMap[
                    $parentId
                ][
                    $cycleId
                ]
            )
        ) {
            throw new RuntimeException(
                'Consumed-stock economic allocation target is duplicated.'
            );
        }

        $allocationMap[
            $parentId
        ][
            $cycleId
        ] =
            $allocation;
    }

    $selected = [];

    foreach ($parentMap as $parentId => $parent) {
        $parentCents =
            stock_consumption_economics_money_cents(
                $parent[
                    'total_cost'
                ]
                ?? null
            );

        if ($parentCents < 0) {
            throw new RuntimeException(
                'Consumed-stock parent amount cannot be negative.'
            );
        }

        $parentAllocations =
            array_values(
                $allocationMap[
                    $parentId
                ]
                ?? []
            );

        $allocatedCents = 0;

        foreach (
            $parentAllocations
            as $allocation
        ) {
            $allocatedCents +=
                stock_consumption_economics_money_cents(
                    $allocation[
                        'allocated_amount'
                    ]
                    ?? null
                );
        }

        if (
            $allocatedCents
            > $parentCents
        ) {
            throw new RuntimeException(
                'Consumed-stock allocation exceeds its parent amount.'
            );
        }

        $native =
            stock_consumption_economics_parent_matches_scope(
                $parent,
                $scope
            );

        if ($native) {
            if (
                $decomposeNativeAllocations
                &&
                $parentAllocations !== []
                &&
                in_array(
                    $scope['level'],
                    [
                        'farm_type',
                        'production',
                    ],
                    true
                )
            ) {
                foreach (
                    $parentAllocations
                    as $allocation
                ) {
                    /*
                     * Canonical allocations may only narrow a parent. If a
                     * native parent points outside its own requested scope,
                     * fail closed rather than silently losing cost.
                     */
                    if (
                        !stock_consumption_economics_target_matches_scope(
                            $allocation,
                            $scope
                        )
                    ) {
                        throw new RuntimeException(
                            'Consumed-stock allocation leaves its native economic scope.'
                        );
                    }

                    $selected[] =
                        stock_consumption_economics_output_row(
                            $parent,
                            stock_consumption_economics_money_cents(
                                $allocation[
                                    'allocated_amount'
                                ]
                            ),
                            'explicit_allocation',
                            $allocation
                        );
                }

                $remainderCents =
                    $parentCents
                    - $allocatedCents;

                if ($remainderCents > 0) {
                    $selected[] =
                        stock_consumption_economics_output_row(
                            $parent,
                            $remainderCents,
                            'unallocated_remainder'
                        );
                }

                continue;
            }

            /*
             * Equal-or-broader scope: allocation rows are internal detail.
             * Count the parent exactly once.
             */
            $selected[] =
                stock_consumption_economics_output_row(
                    $parent,
                    $parentCents,
                    'native_parent'
                );

            continue;
        }

        /*
         * Narrower scope: only explicit allocations may cross the boundary.
         */
        foreach (
            $parentAllocations
            as $allocation
        ) {
            if (
                !stock_consumption_economics_target_matches_scope(
                    $allocation,
                    $scope
                )
            ) {
                continue;
            }

            $selected[] =
                stock_consumption_economics_output_row(
                    $parent,
                    stock_consumption_economics_money_cents(
                        $allocation[
                            'allocated_amount'
                        ]
                    ),
                    'explicit_allocation',
                    $allocation
                );
        }
    }

    return $selected;
}
}

if (!function_exists(
    'stock_consumption_economics_source_rows'
)) {
function stock_consumption_economics_source_rows(
    PDO $pdo,
    int $farmId,
    string $startDate,
    string $endDate
): array {
    if ($farmId < 1) {
        throw new InvalidArgumentException(
            'Consumed-stock economic farm identity is invalid.'
        );
    }

    $effective =
        stock_effective_sql_predicate(
            't'
        );

    $feedPredicate =
        stock_feed_transaction_sql_predicate(
            't'
        );

    $operatingClasses =
        array_keys(
            inventory_operating_consumption_classifications()
        );

    $placeholders =
        $operatingClasses
            ? implode(
                ',',
                array_fill(
                    0,
                    count(
                        $operatingClasses
                    ),
                    '?'
                )
            )
            : "''";

    $sql =
        "SELECT
             t.id AS stock_transaction_id,
             t.stock_item_id,
             t.transaction_date,
             t.transaction_type,
             t.quantity,
             t.unit_cost,
             t.total_cost,
             t.financial_classification,
             t.farm_type,
             t.production_type,
             t.attribution_scope,
             t.cycle_id,
             t.source_type,
             t.source_id,
             s.item_name,
             s.unit,
             s.feed_category,
             c.category_name,
             CASE
                 WHEN ({$feedPredicate})
                 THEN 1
                 ELSE 0
             END AS is_feed
         FROM stock_transactions t
         JOIN stock_items s
           ON s.id=t.stock_item_id
          AND s.farm_id=t.farm_id
         LEFT JOIN inventory_categories c
           ON c.id=s.category_id
          AND c.farm_id=s.farm_id
         WHERE t.farm_id=?
           AND t.transaction_type='used'
           AND {$effective}
           AND t.transaction_date BETWEEN ? AND ?
           AND t.total_cost IS NOT NULL
           AND (
               ({$feedPredicate})
               OR
               t.financial_classification IN ({$placeholders})
           )
         ORDER BY t.transaction_date,t.id";

    $params =
        array_merge(
            [
                $farmId,
                $startDate,
                $endDate,
            ],
            $operatingClasses
        );

    $stmt =
        $pdo->prepare(
            $sql
        );

    $stmt->execute(
        $params
    );

    $rows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];

    foreach ($rows as &$row) {
        $isFeed =
            (int)(
                $row['is_feed']
                ?? 0
            ) === 1;

        $row['cost_kind'] =
            $isFeed
                ? 'feed'
                : 'operating';

        $row['cost_classification'] =
            $isFeed
                ? 'feed'
                : strtolower(
                    trim(
                        (string)(
                            $row[
                                'financial_classification'
                            ]
                            ?? ''
                        )
                    )
                );
    }

    unset($row);

    return $rows;
}
}

if (!function_exists(
    'stock_consumption_economics_allocation_rows'
)) {
function stock_consumption_economics_allocation_rows(
    PDO $pdo,
    int $farmId,
    array $parents
): array {
    if (!$parents) {
        return [];
    }

    $ids = [];

    foreach ($parents as $parent) {
        $id =
            (int)(
                $parent[
                    'stock_transaction_id'
                ]
                ?? 0
            );

        if ($id > 0) {
            $ids[$id] = $id;
        }
    }

    if (!$ids) {
        return [];
    }

    ksort(
        $ids,
        SORT_NUMERIC
    );

    $ids =
        array_values(
            $ids
        );

    $placeholders =
        implode(
            ',',
            array_fill(
                0,
                count($ids),
                '?'
            )
        );

    $sql =
        "SELECT
             a.id AS allocation_id,
             a.stock_transaction_id,
             a.cycle_id,
             a.allocated_amount,
             a.allocation_percent,
             a.notes,
             a.allocation_revision_no,
             pc.farm_type AS target_farm_type,
             pc.production_type AS target_production_type,
             pc.cycle_code AS target_cycle_code,
             pc.start_date AS target_cycle_start_date,
             pc.status AS target_cycle_status
         FROM stock_consumption_allocations a
         JOIN production_cycles pc
           ON pc.id=a.cycle_id
          AND pc.farm_id=a.farm_id
         WHERE a.farm_id=?
           AND a.stock_transaction_id IN ({$placeholders})
         ORDER BY a.stock_transaction_id,a.cycle_id,a.id";

    $stmt =
        $pdo->prepare(
            $sql
        );

    $stmt->execute(
        array_merge(
            [
                $farmId,
            ],
            $ids
        )
    );

    return
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];
}
}

if (!function_exists(
    'stock_consumption_economics_rows'
)) {
function stock_consumption_economics_rows(
    PDO $pdo,
    int $farmId,
    string $startDate,
    string $endDate,
    string $farmType = 'all',
    ?string $productionType = null,
    ?int $cycleId = null,
    bool $decomposeNativeAllocations = false
): array {
    $scope =
        stock_consumption_economics_scope(
            $farmType,
            $productionType,
            $cycleId
        );

    $parents =
        stock_consumption_economics_source_rows(
            $pdo,
            $farmId,
            $startDate,
            $endDate
        );

    $allocations =
        stock_consumption_economics_allocation_rows(
            $pdo,
            $farmId,
            $parents
        );

    return
        stock_consumption_economics_select_rows(
            $parents,
            $allocations,
            $scope,
            $decomposeNativeAllocations
        );
}
}

if (!function_exists(
    'stock_consumption_economics_summary'
)) {
function stock_consumption_economics_summary(
    PDO $pdo,
    int $farmId,
    string $startDate,
    string $endDate,
    string $farmType = 'all',
    ?string $productionType = null,
    ?int $cycleId = null
): array {
    $rows =
        stock_consumption_economics_rows(
            $pdo,
            $farmId,
            $startDate,
            $endDate,
            $farmType,
            $productionType,
            $cycleId,
            false
        );

    $feedCents = 0;
    $operatingCents = 0;
    $breakdownCents = [];

    foreach ($rows as $row) {
        $amountCents =
            (int)(
                $row[
                    'economic_amount_cents'
                ]
                ?? 0
            );

        if (
            ($row['cost_kind'] ?? '')
            === 'feed'
        ) {
            $feedCents +=
                $amountCents;

            continue;
        }

        $classification =
            strtolower(
                trim(
                    (string)(
                        $row[
                            'cost_classification'
                        ]
                        ?? ''
                    )
                )
            );

        if (
            !inventory_financial_classification_is_operating_consumption(
                $classification
            )
        ) {
            continue;
        }

        $operatingCents +=
            $amountCents;

        $breakdownCents[
            $classification
        ] =
            (
                $breakdownCents[
                    $classification
                ]
                ?? 0
            )
            + $amountCents;
    }

    $breakdown = [];

    foreach (
        $breakdownCents
        as $classification => $cents
    ) {
        $breakdown[
            $classification
        ] =
            $cents / 100;
    }

    ksort($breakdown);

    return [
        'rows' =>
            $rows,

        'feed_consumption_cost' =>
            $feedCents / 100,

        'inventory_operating_consumption_cost' =>
            $operatingCents / 100,

        'inventory_operating_consumption_breakdown' =>
            $breakdown,

        'total_consumed_stock_operating_cost' =>
            (
                $feedCents
                + $operatingCents
            ) / 100,
    ];
}
}
