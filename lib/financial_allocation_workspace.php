<?php

require_once __DIR__
    . '/financial_allocation_service.php';

require_once __DIR__
    . '/production_cycle_service.php';

require_once dirname(__DIR__)
    . '/includes/permission_catalog.php';

/*
 * V3.0.1 Financial Allocation Workspace
 *
 * Reader / access adapter only.
 *
 * Authority remains:
 * - financial_allocation_service.php for allocation compatibility;
 * - financial_allocation_persistence.php for mutations;
 * - expense_revision_service.php for immutable expense provenance.
 *
 * Pages and APIs must not reproduce allocation policy or financial-allocation
 * SQL outside these shared services.
 */

if (!function_exists(
    'financial_allocation_workspace_scope'
)) {
function financial_allocation_workspace_scope(
    ?string $value
): string {
    $value =
        strtolower(
            trim(
                (string)$value
            )
        );

    return $value === 'expense_report'
        ? 'expense_report'
        : 'operational';
}
}

if (!function_exists(
    'financial_allocation_workspace_parent_is_eligible'
)) {
function financial_allocation_workspace_parent_is_eligible(
    array $expense
): bool {
    try {
        financial_allocation_service_parent_contract(
            $expense
        );

        return true;

    } catch (Throwable $ignored) {
        return false;
    }
}
}

if (!function_exists(
    'financial_allocation_workspace_view_permission'
)) {
function financial_allocation_workspace_view_permission(
    ?string $editPermission
): ?string {
    if (!$editPermission) {
        return null;
    }

    $viewPermission =
        preg_replace(
            '/_edit$/',
            '',
            $editPermission
        );

    if (
        !is_string($viewPermission)
        ||
        $viewPermission === ''
        ||
        $viewPermission === $editPermission
    ) {
        return null;
    }

    return $viewPermission;
}
}

if (!function_exists(
    'financial_allocation_workspace_can_access'
)) {
function financial_allocation_workspace_can_access(
    array $expense,
    string $permissionScope
): bool {
    $permissionScope =
        financial_allocation_workspace_scope(
            $permissionScope
        );

    if (
        isPlatformOwner()
        ||
        hasRole('farm_admin')
    ) {
        return true;
    }

    if ($permissionScope === 'expense_report') {
        return
            hasPermission(
                getUserType(),
                'expenses'
            )
            &&
            hasPermission(
                getUserType(),
                'expenses_edit'
            )
            &&
            permission_catalog_expense_report_row_accessible(
                $expense
            );
    }

    return
        permission_catalog_expense_operational_can(
            $expense,
            'edit'
        );
}
}

if (!function_exists(
    'financial_allocation_workspace_url'
)) {
function financial_allocation_workspace_url(
    int $expenseId,
    string $permissionScope = 'operational'
): string {
    $permissionScope =
        financial_allocation_workspace_scope(
            $permissionScope
        );

    return
        rtrim(
            BASE_URL,
            '/'
        )
        . '/management/expense_allocation.php?'
        . http_build_query([
            'expense_id' =>
                $expenseId,

            'permission_scope' =>
                $permissionScope,
        ]);
}
}

if (!function_exists(
    'financial_allocation_workspace_return_url'
)) {
function financial_allocation_workspace_return_url(
    array $expense,
    string $permissionScope
): string {
    $permissionScope =
        financial_allocation_workspace_scope(
            $permissionScope
        );

    $date =
        trim(
            (string)(
                $expense['expense_date']
                ?? ''
            )
        );

    $month =
        preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $date
        ) === 1
            ? substr($date, 0, 7)
            : date('Y-m');

    if ($permissionScope === 'expense_report') {
        return
            rtrim(BASE_URL, '/')
            . '/management/expenses.php?'
            . http_build_query([
                'month' => $month,
            ]);
    }

    $farmType =
        strtolower(
            trim(
                (string)(
                    $expense['farm_type']
                    ?? ''
                )
            )
        );

    $productionType =
        strtolower(
            trim(
                (string)(
                    $expense['production_type']
                    ?? ''
                )
            )
        );

    if (
        $farmType === 'poultry'
        &&
        in_array(
            $productionType,
            [
                'layer',
                'broiler',
            ],
            true
        )
    ) {
        return
            rtrim(BASE_URL, '/')
            . '/poultry/'
            . $productionType
            . '_expenses.php?'
            . http_build_query([
                'month' => $month,
            ]);
    }

    if ($farmType === 'ruminant') {
        return
            rtrim(BASE_URL, '/')
            . '/ruminant/ruminant_expenses.php?'
            . http_build_query([
                'month' => $month,
            ]);
    }

    return
        rtrim(BASE_URL, '/')
        . '/management/expenses.php?'
        . http_build_query([
            'month' => $month,
        ]);
}
}


if (!function_exists(
    'financial_allocation_workspace_latest_revision'
)) {
function financial_allocation_workspace_latest_revision(
    PDO $pdo,
    int $farmId,
    int $expenseId
): ?array {
    if (
        $farmId < 1
        ||
        $expenseId < 1
    ) {
        throw new InvalidArgumentException(
            'Financial allocation revision identity is invalid.'
        );
    }

    /*
     * Read-only workspace projection.
     *
     * Mutation/revision authority remains expense_revision_service.php.
     * This helper only exposes the latest immutable decision for display.
     */
    $stmt =
        $pdo->prepare(
            "SELECT
                 id,
                 revision_no,
                 revision_action,
                 revision_reason,
                 created_at
             FROM farm_expense_revisions
             WHERE farm_id=?
               AND expense_id=?
             ORDER BY revision_no DESC
             LIMIT 1"
        );

    $stmt->execute([
        $farmId,
        $expenseId,
    ]);

    $row =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    return
        $row
            ?: null;
}
}


if (!function_exists(
    'financial_allocation_workspace_snapshot'
)) {
function financial_allocation_workspace_snapshot(
    PDO $pdo,
    int $farmId,
    int $expenseId
): array {
    if (
        $farmId < 1
        ||
        $expenseId < 1
    ) {
        throw new InvalidArgumentException(
            'Financial allocation workspace identity is invalid.'
        );
    }

    $parent =
        financial_allocation_service_parent(
            $pdo,
            $farmId,
            $expenseId,
            false
        );

    $parentContract =
        financial_allocation_service_parent_contract(
            $parent
        );

    $currentRows =
        financial_allocation_service_current_rows(
            $pdo,
            $farmId,
            $expenseId,
            false
        );

    $animalAllocationCount =
        financial_allocation_service_animal_count(
            $pdo,
            $farmId,
            $expenseId,
            false
        );

    /*
     * Candidate discovery is shared across allocation workspaces.
     * Each allocation service still owns target compatibility policy.
     */
    $candidateCycles =
        production_cycle_list_for_farm(
            $pdo,
            $farmId
        );

    $eligibleCycles = [];
    $incompatibleCycles = [];

    foreach ($candidateCycles as $cycle) {
        try {
            financial_allocation_service_target_contract(
                $parentContract,
                $cycle
            );

            $eligibleCycles[] =
                $cycle;

        } catch (Throwable $e) {
            $reason =
                trim(
                    (string)$e->getMessage()
                );

            $incompatibleCycles[] = [
                'id' =>
                    (int)(
                        $cycle['id']
                        ?? 0
                    ),

                'cycle_code' =>
                    (string)(
                        $cycle['cycle_code']
                        ?? (
                            'Cycle '
                            . (int)(
                                $cycle['id']
                                ?? 0
                            )
                        )
                    ),

                'farm_type' =>
                    strtolower(
                        trim(
                            (string)(
                                $cycle['farm_type']
                                ?? ''
                            )
                        )
                    ),

                'production_type' =>
                    strtolower(
                        trim(
                            (string)(
                                $cycle['production_type']
                                ?? ''
                            )
                        )
                    ),

                'status' =>
                    strtolower(
                        trim(
                            (string)(
                                $cycle['status']
                                ?? ''
                            )
                        )
                    ),

                'start_date' =>
                    $cycle['start_date']
                    ?? null,

                'reason' =>
                    $reason !== ''
                        ? $reason
                        : 'This cycle is not compatible with the shared expense.',
            ];
        }
    }

    /*
     * Validate the current projection through the same service used by the
     * writer. This is also the reader authority for allocated/remainder.
     */
    $summary =
        financial_allocation_service_validate_desired_rows(
            $parent,
            $eligibleCycles,
            $currentRows,
            $animalAllocationCount
        );

    $latestRevision =
        financial_allocation_workspace_latest_revision(
            $pdo,
            $farmId,
            $expenseId
        );

    $latestRevisionAction =
        $latestRevision
            ? strtolower(
                trim(
                    (string)(
                        $latestRevision[
                            'revision_action'
                        ]
                        ?? ''
                    )
                )
            )
            : '';

    $allocatedAmount =
        (float)(
            $summary[
                'allocated_amount'
            ]
            ?? 0
        );

    $remainingAmount =
        (float)(
            $summary[
                'remaining_amount'
            ]
            ?? 0
        );

    $retainedShared =
        $latestRevisionAction === 'retain_shared'
        &&
        $currentRows === []
        &&
        $allocatedAmount <= 0.00001
        &&
        $remainingAmount > 0.00001;

    $resolutionStatus =
        $retainedShared
            ? 'retained_shared'
            : (
                $allocatedAmount > 0.00001
                &&
                $remainingAmount > 0.00001
                    ? 'partially_allocated'
                    : (
                        $remainingAmount <= 0.00001
                            ? 'allocated'
                            : 'awaiting_allocation'
                    )
            );

    $currentMap = [];

    foreach ($currentRows as $row) {
        $currentMap[
            (int)$row['cycle_id']
        ] =
            $row;
    }

    $cycleRows = [];

    foreach ($eligibleCycles as $cycle) {
        $cycleId =
            (int)$cycle['id'];

        $current =
            $currentMap[$cycleId]
            ?? null;

        $cycleRows[] = [
            'id' =>
                $cycleId,

            'cycle_code' =>
                (string)(
                    $cycle['cycle_code']
                    ?? ('Cycle ' . $cycleId)
                ),

            'farm_type' =>
                strtolower(
                    trim(
                        (string)(
                            $cycle['farm_type']
                            ?? ''
                        )
                    )
                ),

            'production_type' =>
                strtolower(
                    trim(
                        (string)(
                            $cycle['production_type']
                            ?? ''
                        )
                    )
                ),

            'status' =>
                strtolower(
                    trim(
                        (string)(
                            $cycle['status']
                            ?? ''
                        )
                    )
                ),

            'start_date' =>
                $cycle['start_date']
                ?? null,

            'allocated_amount' =>
                $current
                    ? financial_allocation_service_money_string(
                        financial_allocation_service_money_cents(
                            $current[
                                'allocated_amount'
                            ]
                        )
                    )
                    : '0.00',

            'allocation_percent' =>
                $current
                    ? number_format(
                        (float)$current[
                            'allocation_percent'
                        ],
                        4,
                        '.',
                        ''
                    )
                    : '0.0000',

            'notes' =>
                $current[
                    'notes'
                ]
                ?? null,
        ];
    }

    return [
        'parent' =>
            $parent,

        'parent_contract' =>
            $parentContract,

        'allocation_rows' =>
            $summary['rows'],

        'cycles' =>
            $cycleRows,

        'incompatible_cycles' =>
            $incompatibleCycles,

        'animal_allocation_count' =>
            $animalAllocationCount,

        'gross_amount' =>
            $parentContract[
                'gross_amount'
            ],

        'allocated_amount' =>
            $summary[
                'allocated_amount'
            ],

        'remaining_amount' =>
            $summary[
                'remaining_amount'
            ],

        'latest_revision' =>
            $latestRevision,

        'resolution_status' =>
            $resolutionStatus,

        'retained_shared' =>
            $retainedShared,

        'retained_shared_reason' =>
            $retainedShared
                ? (
                    $latestRevision[
                        'revision_reason'
                    ]
                    ?? null
                )
                : null,
    ];
}
}
