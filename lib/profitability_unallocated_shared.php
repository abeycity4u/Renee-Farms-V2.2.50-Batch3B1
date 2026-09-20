<?php

/**
 * V3.0.1 Profitability — Unallocated Shared Balance read model.
 *
 * This reader does NOT change Profit / Loss.
 *
 * Its job is disclosure:
 *
 * parent shared amount
 * = explicitly allocated amount
 * + visible unallocated remainder.
 *
 * "Shared Operation" is a source attribution.
 * "Unallocated" is a state of a broader parent.
 * They must never be treated as synonyms.
 */

require_once __DIR__ . '/shared_cost_contract.php';
require_once __DIR__ . '/financial_allocation_workspace.php';
require_once __DIR__ . '/sale_revenue_allocation_workspace.php';
require_once __DIR__ . '/stock_consumption_economics.php';
require_once __DIR__ . '/stock_consumption_allocation_workspace.php';

if (!function_exists(
    'profitability_unallocated_shared_cents'
)) {
function profitability_unallocated_shared_cents(
    $value
): int {
    if (
        $value === null
        || $value === ''
        || !is_numeric($value)
    ) {
        return 0;
    }

    return
        (int)round(
            (float)$value * 100
        );
}
}


if (!function_exists(
    'profitability_unallocated_shared_latest_cost_revision_map'
)) {
function profitability_unallocated_shared_latest_cost_revision_map(
    PDO $pdo,
    int $farmId,
    array $parentIds,
    string $source
): array {
    if ($farmId < 1) {
        throw new InvalidArgumentException(
            'Shared-cost revision farm identity is invalid.'
        );
    }

    $source =
        strtolower(
            trim(
                $source
            )
        );

    if ($source === 'expense') {
        $table =
            'farm_expense_revisions';

        $parentColumn =
            'expense_id';

    } elseif ($source === 'stock') {
        $table =
            'stock_consumption_allocation_revisions';

        $parentColumn =
            'stock_transaction_id';

    } else {
        throw new InvalidArgumentException(
            'Shared-cost revision source is invalid.'
        );
    }

    $ids = [];

    foreach ($parentIds as $parentId) {
        $parentId =
            (int)$parentId;

        if ($parentId > 0) {
            $ids[$parentId] =
                $parentId;
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

    /*
     * One batched lookup per cost authority.
     * Do not add a per-row latest-revision query to Profitability.
     */
    $sql =
        "SELECT
             r.{$parentColumn} AS parent_id,
             r.revision_no,
             r.revision_action,
             r.revision_reason
         FROM {$table} r
         INNER JOIN (
             SELECT
                 {$parentColumn} AS parent_id,
                 MAX(revision_no) AS revision_no
             FROM {$table}
             WHERE farm_id=?
               AND {$parentColumn} IN ({$placeholders})
             GROUP BY {$parentColumn}
         ) latest
           ON latest.parent_id=r.{$parentColumn}
          AND latest.revision_no=r.revision_no
         WHERE r.farm_id=?";

    $params =
        array_merge(
            [
                $farmId,
            ],
            $ids,
            [
                $farmId,
            ]
        );

    $stmt =
        $pdo->prepare(
            $sql
        );

    $stmt->execute(
        $params
    );

    $map = [];

    foreach (
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        )
        as $row
    ) {
        $parentId =
            (int)(
                $row[
                    'parent_id'
                ]
                ?? 0
            );

        if ($parentId > 0) {
            $map[$parentId] =
                $row;
        }
    }

    return $map;
}
}


if (!function_exists(
    'profitability_unallocated_shared_scope_label'
)) {
function profitability_unallocated_shared_scope_label(
    string $farmType,
    string $productionType,
    string $scope
): string {
    $farmType =
        strtolower(
            trim($farmType)
        );

    $productionType =
        strtolower(
            trim($productionType)
        );

    $scope =
        strtolower(
            trim($scope)
        );

    if (
        $farmType === 'both'
        && $productionType === 'shared'
    ) {
        return 'Farm-wide cross-module';
    }

    if (
        in_array(
            $farmType,
            ['poultry', 'ruminant'],
            true
        )
        && $productionType === 'shared'
    ) {
        return ucfirst($farmType)
            . ' shared operation';
    }

    if ($productionType !== '') {
        return ucfirst(
            str_replace(
                '_',
                ' ',
                $productionType
            )
        )
        . (
            $scope === 'production_type'
                ? ' production pool'
                : ' shared parent'
        );
    }

    return 'Shared parent';
}
}

if (!function_exists(
    'profitability_unallocated_shared_summary'
)) {
function profitability_unallocated_shared_summary(
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

    if (
        !preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $startDate
        )
        ||
        !preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $endDate
        )
        ||
        $endDate < $startDate
    ) {
        throw new InvalidArgumentException(
            'Profitability shared-balance date range is invalid.'
        );
    }

    $totals = [
        'revenue_parent_cents' => 0,
        'revenue_allocated_cents' => 0,
        'revenue_unallocated_cents' => 0,

        'manual_operating_parent_cents' => 0,
        'manual_operating_allocated_cents' => 0,
        'manual_operating_unallocated_cents' => 0,

        /*
         * Feed purchase receipts remain cash-flow information.
         * They are disclosed separately and never added to
         * consumed-feed profitability.
         */
        'cash_feed_purchase_parent_cents' => 0,
        'cash_feed_purchase_allocated_cents' => 0,
        'cash_feed_purchase_unallocated_cents' => 0,

        'stock_feed_parent_cents' => 0,
        'stock_feed_allocated_cents' => 0,
        'stock_feed_unallocated_cents' => 0,

        'stock_operating_parent_cents' => 0,
        'stock_operating_allocated_cents' => 0,
        'stock_operating_unallocated_cents' => 0,

        'stock_attribution_exception_count' => 0,
        'stock_attribution_exception_cents' => 0,
    ];

    $rows = [];


    /* ======================================================
     * SHARED / POOLED REVENUE
     * ====================================================== */

    $salesStmt =
        $pdo->prepare(
            "SELECT
                 id,
                 farm_id,
                 sale_date,
                 farm_type,
                 production_type,
                 attribution_scope,
                 cycle_id,
                 product_type,
                 total_amount
             FROM sales_records
             WHERE farm_id=?
               AND sale_date BETWEEN ? AND ?
               AND cycle_id IS NULL
               AND farm_type IN (
                   'poultry',
                   'ruminant'
               )
             ORDER BY sale_date,id"
        );

    $salesStmt->execute([
        $farmId,
        $startDate,
        $endDate,
    ]);

    $salesAllocStmt =
        $pdo->prepare(
            "SELECT
                 COALESCE(
                     SUM(allocated_amount),
                     0
                 )
             FROM sales_allocations
             WHERE farm_id=?
               AND sale_id=?"
        );

    foreach (
        $salesStmt->fetchAll(
            PDO::FETCH_ASSOC
        )
        as $sale
    ) {
        $saleId =
            (int)$sale['id'];

        $parentCents =
            profitability_unallocated_shared_cents(
                $sale['total_amount']
            );

        $salesAllocStmt->execute([
            $farmId,
            $saleId,
        ]);

        $allocatedCents =
            profitability_unallocated_shared_cents(
                $salesAllocStmt->fetchColumn()
            );

        if ($allocatedCents > $parentCents) {
            throw new RuntimeException(
                'Shared revenue allocation exceeds its parent amount.'
            );
        }

        $unallocatedCents =
            $parentCents
            - $allocatedCents;

        /*
         * Profitability exposes only the destination.
         * Eligibility, authorization and all mutation authority remain
         * inside the canonical shared-revenue workspace/service stack.
         */
        $revenueAllocationUrl =
            null;

        if (
            sale_revenue_allocation_workspace_parent_is_eligible(
                $sale
            )
            &&
            sale_revenue_allocation_workspace_can_access(
                $sale
            )
        ) {
            $revenueAllocationUrl =
                sale_revenue_allocation_workspace_url(
                    $saleId
                );
        }

        $totals[
            'revenue_parent_cents'
        ] += $parentCents;

        $totals[
            'revenue_allocated_cents'
        ] += $allocatedCents;

        $totals[
            'revenue_unallocated_cents'
        ] += $unallocatedCents;

        if ($unallocatedCents > 0) {
            $latestRevenueRevision =
                sale_revenue_allocation_persistence_latest_revision(
                    $pdo,
                    $farmId,
                    $saleId,
                    false
                );

            $latestRevenueAction =
                $latestRevenueRevision
                    ? strtolower(
                        trim(
                            (string)(
                                $latestRevenueRevision[
                                    'revision_action'
                                ]
                                ?? ''
                            )
                        )
                    )
                    : '';

            $revenueStatus =
                $allocatedCents > 0
                    ? 'partially_allocated'
                    : (
                        $latestRevenueAction === 'retain_shared'
                            ? 'retained_shared'
                            : 'awaiting_allocation'
                    );

            $rows[] = [
                'source_type' =>
                    'Shared revenue',

                'source_id' =>
                    $saleId,

                'scope' =>
                    profitability_unallocated_shared_scope_label(
                        (string)$sale[
                            'farm_type'
                        ],
                        (string)$sale[
                            'production_type'
                        ],
                        (string)$sale[
                            'attribution_scope'
                        ]
                    ),

                'description' =>
                    (string)(
                        $sale[
                            'product_type'
                        ]
                        ?? 'Revenue'
                    ),

                'parent_amount' =>
                    $parentCents / 100,

                'allocated_amount' =>
                    $allocatedCents / 100,

                'unallocated_amount' =>
                    $unallocatedCents / 100,

                'status' =>
                    $revenueStatus,

                'resolution_reason' =>
                    $revenueStatus === 'retained_shared'
                        ? (
                            $latestRevenueRevision[
                                'revision_reason'
                            ]
                            ?? null
                        )
                        : null,

                /*
                 * Keep ineligible / unauthorized revenue visibly
                 * unallocated without exposing a mutation action.
                 */
                'allocation_kind' =>
                    $revenueAllocationUrl !== null
                        ? 'revenue'
                        : null,

                'allocation_url' =>
                    $revenueAllocationUrl,
            ];
        }
    }


    /* ======================================================
     * MANUAL SHARED EXPENSES
     * ====================================================== */

    $expenseStmt =
        $pdo->prepare(
            "SELECT
                 id,
                 farm_id,
                 farm_type,
                 production_type,
                 attribution_scope,
                 cycle_id,
                 category,
                 amount,
                 unit,
                 description
             FROM farm_expenses
             WHERE farm_id=?
               AND expense_date BETWEEN ? AND ?
               AND cycle_id IS NULL
             ORDER BY expense_date,id"
        );

    $expenseStmt->execute([
        $farmId,
        $startDate,
        $endDate,
    ]);

    $expenseAllocStmt =
        $pdo->prepare(
            "SELECT
                 allocated_amount
             FROM financial_allocations
             WHERE farm_id=?
               AND expense_id=?
             ORDER BY cycle_id,id"
        );

    $expenses =
        $expenseStmt->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];

    $expenseRevisionMap =
        profitability_unallocated_shared_latest_cost_revision_map(
            $pdo,
            $farmId,
            array_column(
                $expenses,
                'id'
            ),
            'expense'
        );

    foreach ($expenses as $expense) {
        try {
            $parent =
                shared_cost_contract_parent(
                    $expense
                );

        } catch (Throwable $error) {
            /*
             * General/direct/non-shared rows are not part of
             * this allocation queue.
             */
            continue;
        }

        $expenseId =
            (int)$expense['id'];

        $grossCents =
            profitability_unallocated_shared_cents(
                (float)$expense['amount']
                *
                (float)$expense['unit']
            );

        $expenseAllocStmt->execute([
            $farmId,
            $expenseId,
        ]);

        $allocationRows =
            $expenseAllocStmt->fetchAll(
                PDO::FETCH_COLUMN
            );

        $conservation =
            shared_cost_contract_conservation(
                $grossCents / 100,
                $allocationRows
            );

        $allocatedCents =
            (int)$conservation[
                'allocated_cents'
            ];

        $unallocatedCents =
            (int)$conservation[
                'unallocated_cents'
            ];

        $isFeedPurchase =
            strtolower(
                trim(
                    (string)$expense[
                        'category'
                    ]
                )
            ) === 'feeds';

        $expenseAllocationUrl =
            null;

        if (
            !$isFeedPurchase
            &&
            financial_allocation_workspace_parent_is_eligible(
                $expense
            )
            &&
            financial_allocation_workspace_can_access(
                $expense,
                'operational'
            )
        ) {
            $expenseAllocationUrl =
                financial_allocation_workspace_url(
                    $expenseId,
                    'operational'
                );
        }

        $latestExpenseRevision =
            $expenseRevisionMap[
                $expenseId
            ]
            ?? null;

        $latestExpenseAction =
            $latestExpenseRevision
                ? strtolower(
                    trim(
                        (string)(
                            $latestExpenseRevision[
                                'revision_action'
                            ]
                            ?? ''
                        )
                    )
                )
                : '';

        $expenseStatus =
            $isFeedPurchase
                ? 'cash_only_waiting_allocation'
                : (
                    $allocatedCents > 0
                        ? 'partially_allocated'
                        : (
                            $latestExpenseAction === 'retain_shared'
                                ? 'retained_shared'
                                : 'awaiting_allocation'
                        )
                );

        $prefix =
            $isFeedPurchase
                ? 'cash_feed_purchase'
                : 'manual_operating';

        $totals[
            $prefix
            . '_parent_cents'
        ] += $grossCents;

        $totals[
            $prefix
            . '_allocated_cents'
        ] += $allocatedCents;

        $totals[
            $prefix
            . '_unallocated_cents'
        ] += $unallocatedCents;

        if ($unallocatedCents > 0) {
            $rows[] = [
                'source_type' =>
                    $isFeedPurchase
                        ? 'Feed purchase — cash only'
                        : 'Manual operating expense',

                'source_id' =>
                    $expenseId,

                'scope' =>
                    profitability_unallocated_shared_scope_label(
                        (string)$expense[
                            'farm_type'
                        ],
                        (string)$expense[
                            'production_type'
                        ],
                        (string)$expense[
                            'attribution_scope'
                        ]
                    ),

                'description' =>
                    trim(
                        (string)(
                            $expense[
                                'description'
                            ]
                            ?? ''
                        )
                    ) !== ''
                        ? (string)$expense[
                            'description'
                        ]
                        : ucfirst(
                            (string)$expense[
                                'category'
                            ]
                        ),

                'parent_amount' =>
                    $grossCents / 100,

                'allocated_amount' =>
                    $allocatedCents / 100,

                'unallocated_amount' =>
                    $unallocatedCents / 100,

                'status' =>
                    $expenseStatus,

                'resolution_reason' =>
                    $expenseStatus === 'retained_shared'
                        ? (
                            $latestExpenseRevision[
                                'revision_reason'
                            ]
                            ?? null
                        )
                        : null,

                'allocation_kind' =>
                    $isFeedPurchase
                        ? null
                        : 'expense',

                'allocation_url' =>
                    $expenseAllocationUrl,
            ];
        }
    }


    /* ======================================================
     * CONSUMED STOCK
     * ====================================================== */

    $stockParents =
        stock_consumption_economics_source_rows(
            $pdo,
            $farmId,
            $startDate,
            $endDate
        );

    $stockAllocations =
        stock_consumption_economics_allocation_rows(
            $pdo,
            $farmId,
            $stockParents
        );

    $stockRevisionMap =
        profitability_unallocated_shared_latest_cost_revision_map(
            $pdo,
            $farmId,
            array_column(
                $stockParents,
                'stock_transaction_id'
            ),
            'stock'
        );

    $stockAllocationMap = [];

    foreach (
        $stockAllocations
        as $allocation
    ) {
        $stockAllocationMap[
            (int)$allocation[
                'stock_transaction_id'
            ]
        ][] = $allocation;
    }

    foreach ($stockParents as $movement) {
        if (
            (int)(
                $movement[
                    'cycle_id'
                ]
                ?? 0
            ) > 0
        ) {
            continue;
        }

        $stockId =
            (int)$movement[
                'stock_transaction_id'
            ];

        $movement['id'] =
            $stockId;

        $movement['farm_id'] =
            $farmId;

        $parentCents =
            profitability_unallocated_shared_cents(
                $movement[
                    'total_cost'
                ]
                ?? 0
            );

        $allocations =
            $stockAllocationMap[
                $stockId
            ]
            ?? [];

        $eligibility =
            stock_consumption_allocation_workspace_eligibility(
                $pdo,
                $farmId,
                $movement
            );

        if (!$eligibility['eligible']) {
            $reason =
                strtolower(
                    trim(
                        (string)(
                            $eligibility[
                                'reason'
                            ]
                            ?? ''
                        )
                    )
                );

            /*
             * A source-attribution mismatch is NOT legitimate
             * unallocated shared cost. Keep it in a separate
             * correction queue.
             */
            if (
                strpos(
                    $reason,
                    'differs'
                ) !== false
                ||
                strpos(
                    $reason,
                    'drift'
                ) !== false
            ) {
                $totals[
                    'stock_attribution_exception_count'
                ]++;

                $totals[
                    'stock_attribution_exception_cents'
                ] += $parentCents;

                $rows[] = [
                    'source_type' =>
                        'Stock attribution exception',

                    'source_id' =>
                        $stockId,

                    'scope' =>
                        profitability_unallocated_shared_scope_label(
                            (string)$movement[
                                'farm_type'
                            ],
                            (string)$movement[
                                'production_type'
                            ],
                            (string)$movement[
                                'attribution_scope'
                            ]
                        ),

                    'description' =>
                        (string)(
                            $movement[
                                'item_name'
                            ]
                            ?? 'Consumed stock'
                        ),

                    'parent_amount' =>
                        $parentCents / 100,

                    'allocated_amount' =>
                        0.0,

                    'unallocated_amount' =>
                        0.0,

                    'status' =>
                        'attribution_exception',
                ];
            }

            continue;
        }

        $allocatedAmounts = [];

        foreach (
            $allocations
            as $allocation
        ) {
            $allocatedAmounts[] =
                $allocation[
                    'allocated_amount'
                ];
        }

        $conservation =
            shared_cost_contract_conservation(
                $parentCents / 100,
                $allocatedAmounts
            );

        $allocatedCents =
            (int)$conservation[
                'allocated_cents'
            ];

        $unallocatedCents =
            (int)$conservation[
                'unallocated_cents'
            ];

        $latestStockRevision =
            $stockRevisionMap[
                $stockId
            ]
            ?? null;

        $latestStockAction =
            $latestStockRevision
                ? strtolower(
                    trim(
                        (string)(
                            $latestStockRevision[
                                'revision_action'
                            ]
                            ?? ''
                        )
                    )
                )
                : '';

        $stockStatus =
            $allocatedCents > 0
                ? 'partially_allocated'
                : (
                    $latestStockAction === 'retain_shared'
                        ? 'retained_shared'
                        : 'awaiting_allocation'
                );

        $kind =
            (
                (
                    $movement[
                        'cost_kind'
                    ]
                    ?? ''
                )
                === 'feed'
            )
                ? 'stock_feed'
                : 'stock_operating';

        $totals[
            $kind
            . '_parent_cents'
        ] += $parentCents;

        $totals[
            $kind
            . '_allocated_cents'
        ] += $allocatedCents;

        $totals[
            $kind
            . '_unallocated_cents'
        ] += $unallocatedCents;

        if ($unallocatedCents > 0) {
            $rows[] = [
                'source_type' =>
                    $kind === 'stock_feed'
                        ? 'Consumed Feed'
                        : 'Consumed operating stock',

                'source_id' =>
                    $stockId,

                'scope' =>
                    profitability_unallocated_shared_scope_label(
                        (string)$movement[
                            'farm_type'
                        ],
                        (string)$movement[
                            'production_type'
                        ],
                        (string)$movement[
                            'attribution_scope'
                        ]
                    ),

                'description' =>
                    (string)(
                        $movement[
                            'item_name'
                        ]
                        ?? 'Consumed stock'
                    ),

                'parent_amount' =>
                    $parentCents / 100,

                'allocated_amount' =>
                    $allocatedCents / 100,

                'unallocated_amount' =>
                    $unallocatedCents / 100,

                'status' =>
                    $stockStatus,

                'resolution_reason' =>
                    $stockStatus === 'retained_shared'
                        ? (
                            $latestStockRevision[
                                'revision_reason'
                            ]
                            ?? null
                        )
                        : null,

                /*
                 * This movement already passed the canonical
                 * consumed-stock allocation eligibility contract.
                 * Expose its workspace destination only; all mutation
                 * authority remains in the canonical workspace/API.
                 */
                'allocation_kind' =>
                    'stock',

                'allocation_url' =>
                    stock_consumption_allocation_workspace_url(
                        $stockId
                    ),
            ];
        }
    }


    /* ======================================================
     * PRESENTATION TOTALS
     * ====================================================== */

    $operatingUnallocatedCents =
        $totals[
            'manual_operating_unallocated_cents'
        ]
        +
        $totals[
            'stock_feed_unallocated_cents'
        ]
        +
        $totals[
            'stock_operating_unallocated_cents'
        ];

    return [
        'revenue_parent' =>
            $totals[
                'revenue_parent_cents'
            ] / 100,

        'revenue_allocated' =>
            $totals[
                'revenue_allocated_cents'
            ] / 100,

        'revenue_unallocated' =>
            $totals[
                'revenue_unallocated_cents'
            ] / 100,

        'manual_operating_parent' =>
            $totals[
                'manual_operating_parent_cents'
            ] / 100,

        'manual_operating_allocated' =>
            $totals[
                'manual_operating_allocated_cents'
            ] / 100,

        'manual_operating_unallocated' =>
            $totals[
                'manual_operating_unallocated_cents'
            ] / 100,

        'cash_feed_purchase_parent' =>
            $totals[
                'cash_feed_purchase_parent_cents'
            ] / 100,

        'cash_feed_purchase_allocated' =>
            $totals[
                'cash_feed_purchase_allocated_cents'
            ] / 100,

        'cash_feed_purchase_unallocated' =>
            $totals[
                'cash_feed_purchase_unallocated_cents'
            ] / 100,

        'stock_feed_parent' =>
            $totals[
                'stock_feed_parent_cents'
            ] / 100,

        'stock_feed_allocated' =>
            $totals[
                'stock_feed_allocated_cents'
            ] / 100,

        'stock_feed_unallocated' =>
            $totals[
                'stock_feed_unallocated_cents'
            ] / 100,

        'stock_operating_parent' =>
            $totals[
                'stock_operating_parent_cents'
            ] / 100,

        'stock_operating_allocated' =>
            $totals[
                'stock_operating_allocated_cents'
            ] / 100,

        'stock_operating_unallocated' =>
            $totals[
                'stock_operating_unallocated_cents'
            ] / 100,

        'operating_shared_unallocated' =>
            $operatingUnallocatedCents / 100,

        'stock_attribution_exception_count' =>
            (int)$totals[
                'stock_attribution_exception_count'
            ],

        'stock_attribution_exception_amount' =>
            $totals[
                'stock_attribution_exception_cents'
            ] / 100,

        'rows' =>
            $rows,
    ];
}
}
