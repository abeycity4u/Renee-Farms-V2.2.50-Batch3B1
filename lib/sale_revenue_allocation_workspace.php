<?php

require_once __DIR__
    . '/sale_revenue_allocation_persistence.php';

require_once __DIR__
    . '/production_cycle_service.php';

require_once dirname(__DIR__)
    . '/includes/permission_catalog.php';

/*
 * V3.0.1 Shared Revenue Allocation Workspace
 *
 * Reader / access / URL adapter only.
 *
 * Authority remains:
 * - sale_revenue_allocation_service.php for allocation policy;
 * - sale_revenue_allocation_persistence.php for projection + revisions;
 * - sale_revenue_allocation_provenance.php for immutable provenance;
 * - sales lifecycle helpers for protected sale edit/delete behavior.
 *
 * Pages and APIs must not reproduce those rules or directly mutate
 * sales_allocations / revision history.
 */

if (!function_exists(
    'sale_revenue_allocation_workspace_parent_is_eligible'
)) {
function sale_revenue_allocation_workspace_parent_is_eligible(
    array $sale
): bool {
    try {
        sale_revenue_allocation_service_parent_contract(
            $sale
        );

        return true;

    } catch (Throwable $ignored) {
        return false;
    }
}
}


if (!function_exists(
    'sale_revenue_allocation_workspace_can_access'
)) {
function sale_revenue_allocation_workspace_can_access(
    array $sale
): bool {
    if (
        isPlatformOwner()
        ||
        hasRole('farm_admin')
    ) {
        return true;
    }

    if (
        !hasPermission(
            getUserType(),
            'sales'
        )
        ||
        !hasPermission(
            getUserType(),
            'sales_edit'
        )
    ) {
        return false;
    }

    /*
     * Sales representatives already use the cross-module Sales workspace.
     * Poultry/Ruminant managers remain confined to their assigned farm type.
     */
    if (hasRole('sales_rep')) {
        return true;
    }

    $saleFarmType =
        strtolower(
            trim(
                (string)(
                    $sale['farm_type']
                    ?? ''
                )
            )
        );

    $userFarmType =
        strtolower(
            trim(
                (string)getUserFarmType()
            )
        );

    if (
        in_array(
            $userFarmType,
            [
                'all',
                'both',
            ],
            true
        )
    ) {
        return in_array(
            $saleFarmType,
            [
                'poultry',
                'ruminant',
            ],
            true
        );
    }

    return
        in_array(
            $saleFarmType,
            [
                'poultry',
                'ruminant',
            ],
            true
        )
        &&
        $saleFarmType === $userFarmType;
}
}


if (!function_exists(
    'sale_revenue_allocation_workspace_url'
)) {
function sale_revenue_allocation_workspace_url(
    int $saleId
): string {
    if ($saleId < 1) {
        throw new InvalidArgumentException(
            'Shared revenue workspace sale identity is invalid.'
        );
    }

    return
        rtrim(
            BASE_URL,
            '/'
        )
        . '/management/sale_revenue_allocation.php?'
        . http_build_query([
            'sale_id' =>
                $saleId,
        ]);
}
}


if (!function_exists(
    'sale_revenue_allocation_workspace_return_url'
)) {
function sale_revenue_allocation_workspace_return_url(
    array $sale
): string {
    $date =
        trim(
            (string)(
                $sale['sale_date']
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

    $farmType =
        strtolower(
            trim(
                (string)(
                    $sale['farm_type']
                    ?? 'all'
                )
            )
        );

    if (
        !in_array(
            $farmType,
            [
                'poultry',
                'ruminant',
            ],
            true
        )
    ) {
        $farmType =
            'all';
    }

    return
        rtrim(BASE_URL, '/')
        . '/management/profitability.php?'
        . http_build_query([
            'period' =>
                'monthly',

            'month' =>
                $month,

            'farm_type' =>
                $farmType,

            'production_type' =>
                'all',

            'cycle_id' =>
                0,
        ]);
}
}


if (!function_exists(
    'sale_revenue_allocation_workspace_snapshot'
)) {
function sale_revenue_allocation_workspace_snapshot(
    PDO $pdo,
    int $farmId,
    int $saleId
): array {
    if (
        $farmId < 1
        ||
        $saleId < 1
    ) {
        throw new InvalidArgumentException(
            'Shared revenue allocation workspace identity is invalid.'
        );
    }

    $parent =
        sale_revenue_allocation_persistence_parent(
            $pdo,
            $farmId,
            $saleId,
            false
        );

    $parentContract =
        sale_revenue_allocation_service_parent_contract(
            $parent
        );

    $currentRows =
        sale_revenue_allocation_persistence_current_rows(
            $pdo,
            $farmId,
            $saleId,
            false
        );

    /*
     * Fail closed if another allocation authority owns any projection row.
     */
    sale_revenue_allocation_persistence_assert_manual_projection(
        $currentRows
    );

    $latestRevision =
        sale_revenue_allocation_persistence_latest_revision(
            $pdo,
            $farmId,
            $saleId,
            false
        );

    /*
     * Reader state must agree with immutable provenance before it is shown
     * as editable state.
     */
    sale_revenue_allocation_persistence_assert_revision_consistency(
        $pdo,
        $parent,
        $currentRows,
        $latestRevision,
        false
    );

    $animalAllocationCount =
        sale_revenue_allocation_persistence_animal_count(
            $pdo,
            $farmId,
            $saleId,
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

    foreach (
        $candidateCycles
        as $cycle
    ) {
        try {
            sale_revenue_allocation_service_target_contract(
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
                        : 'This cycle is not compatible with the shared revenue.',
            ];
        }
    }

    /*
     * Validate the current projection through the canonical policy.
     *
     * Animal overlap is deliberately not passed as a validation blocker here
     * because this is a read-only snapshot. The actual overlap count is
     * returned separately to disable mutation in the UI, while the canonical
     * writer re-checks it under lock before every save.
     */
    $summary =
        sale_revenue_allocation_service_validate_desired_rows(
            $parent,
            $eligibleCycles,
            $currentRows,
            0
        );

    $currentMap = [];

    foreach (
        $currentRows
        as $row
    ) {
        $currentMap[
            (int)$row['cycle_id']
        ] =
            $row;
    }

    $cycleRows = [];

    foreach (
        $eligibleCycles
        as $cycle
    ) {
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
                    ? sale_revenue_allocation_service_money_string(
                        sale_revenue_allocation_service_money_cents(
                            $current['allocated_amount']
                        )
                    )
                    : '0.00',

            'allocation_percent' =>
                $current
                    ? number_format(
                        (float)$current['allocation_percent'],
                        4,
                        '.',
                        ''
                    )
                    : '0.0000',

            'notes' =>
                $current['notes']
                ?? null,
        ];
    }

    $latestRevisionAction =
        $latestRevision
            ? strtolower(
                trim(
                    (string)(
                        $latestRevision['revision_action']
                        ?? ''
                    )
                )
            )
            : '';

    $retainedShared =
        $latestRevisionAction === 'retain_shared'
        &&
        ($summary['rows'] ?? []) === [];

    $resolutionStatus =
        $retainedShared
            ? 'retained_shared'
            : (
                (float)$summary['allocated_amount'] > 0
                &&
                (float)$summary['remaining_amount'] > 0
                    ? 'partially_allocated'
                    : (
                        (float)$summary['remaining_amount'] <= 0
                            ? 'allocated'
                            : 'awaiting_allocation'
                    )
            );

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

        'mutation_blocked' =>
            $animalAllocationCount > 0,

        'gross_amount' =>
            $parentContract['parent_amount'],

        'allocated_amount' =>
            $summary['allocated_amount'],

        'remaining_amount' =>
            $summary['remaining_amount'],

        'latest_revision' =>
            $latestRevision,

        'revision_no' =>
            $latestRevision
                ? (int)$latestRevision['revision_no']
                : 0,

        'resolution_status' =>
            $resolutionStatus,

        'retained_shared' =>
            $retainedShared,

        'retained_shared_reason' =>
            $retainedShared
                ? (
                    $latestRevision['revision_reason']
                    ?? null
                )
                : null,

        /*
         * Canonical persistence permits the initial create without a reason
         * and requires one after revision history exists.
         */
        'reason_required' =>
            $latestRevision !== null,
    ];
}
}
