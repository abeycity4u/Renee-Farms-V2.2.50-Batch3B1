<?php

require_once __DIR__
    . '/financial_allocation_service.php';

/*
 * V3.0.1 Financial Allocation Integrity Boundary
 *
 * Protects farm-expense mutations that occur outside the dedicated financial
 * allocation writer.
 *
 * Canonical lock order:
 *   1. farm_expenses parent
 *   2. current financial_allocations
 *   3. ruminant animal allocations
 *   4. target production_cycles in deterministic ID order
 *
 * Expense revision state/history must be locked only AFTER this boundary.
 *
 * This service never commits or rolls back.
 */

if (!function_exists(
    'financial_allocation_integrity_require_transaction'
)) {
function financial_allocation_integrity_require_transaction(
    PDO $pdo
): void {
    if (!$pdo->inTransaction()) {
        throw new RuntimeException(
            'Financial allocation integrity validation requires an active transaction.'
        );
    }
}
}

if (!function_exists(
    'financial_allocation_integrity_requested_animal_rows'
)) {
function financial_allocation_integrity_requested_animal_rows(
    ?array $allocation
): bool {
    if (!$allocation) {
        return false;
    }

    $rows =
        $allocation['rows']
        ?? [];

    return
        is_array($rows)
        &&
        count($rows) > 0;
}
}

if (!function_exists(
    'financial_allocation_integrity_lock_state'
)) {
function financial_allocation_integrity_lock_state(
    PDO $pdo,
    int $farmId,
    int $expenseId
): array {
    financial_allocation_integrity_require_transaction(
        $pdo
    );

    if (
        $farmId < 1
        ||
        $expenseId < 1
    ) {
        throw new InvalidArgumentException(
            'Expense identity is invalid for allocation integrity validation.'
        );
    }

    /*
     * Keep this order aligned with financial_allocation_persistence.php.
     */
    $parent =
        financial_allocation_service_parent(
            $pdo,
            $farmId,
            $expenseId,
            true
        );

    $financialRows =
        financial_allocation_service_current_rows(
            $pdo,
            $farmId,
            $expenseId,
            true
        );

    $animalCount =
        financial_allocation_service_animal_count(
            $pdo,
            $farmId,
            $expenseId,
            true
        );

    $cycleIds = [];

    foreach ($financialRows as $row) {
        $cycleId =
            (int)(
                $row['cycle_id']
                ?? 0
            );

        if ($cycleId > 0) {
            $cycleIds[$cycleId] =
                $cycleId;
        }
    }

    ksort(
        $cycleIds,
        SORT_NUMERIC
    );

    $cycles =
        financial_allocation_service_target_cycles(
            $pdo,
            $farmId,
            array_values($cycleIds),
            true
        );

    return [
        'parent' =>
            $parent,

        'financial_rows' =>
            $financialRows,

        'animal_count' =>
            $animalCount,

        'cycles' =>
            $cycles,
    ];
}
}

if (!function_exists(
    'financial_allocation_integrity_assert_parent_update'
)) {
function financial_allocation_integrity_assert_parent_update(
    PDO $pdo,
    int $farmId,
    int $expenseId,
    array $proposedParent,
    ?array $proposedAnimalAllocation = null
): array {
    $state =
        financial_allocation_integrity_lock_state(
            $pdo,
            $farmId,
            $expenseId
        );

    $financialRows =
        $state['financial_rows'];

    /*
     * No current financial allocation means this integrity boundary has
     * nothing additional to protect. Existing expense/animal validation
     * remains authoritative.
     */
    if (!$financialRows) {
        return [
            'financial_allocation_count' =>
                0,

            'animal_allocation_count' =>
                (int)$state[
                    'animal_count'
                ],

            'allocated_amount' =>
                '0.00',

            'remaining_amount' =>
                null,
        ];
    }

    /*
     * First prove that the CURRENT projection is internally valid.
     * This makes an expense edit fail closed if prior drift already exists.
     */
    $currentValidated =
        financial_allocation_service_validate_desired_rows(
            $state['parent'],
            $state['cycles'],
            $financialRows,
            (int)$state['animal_count']
        );

    /*
     * Reciprocal exclusivity:
     * financial cycle allocation and individual-animal allocation cannot
     * describe the same expense at the same time.
     */
    if (
        financial_allocation_integrity_requested_animal_rows(
            $proposedAnimalAllocation
        )
    ) {
        throw new RuntimeException(
            'Clear the current production-cycle financial allocation before allocating this expense to individual animals.'
        );
    }

    $nextParent =
        array_replace(
            $state['parent'],
            $proposedParent
        );

    /*
     * Tenant/identity cannot be moved through a parent edit.
     */
    $nextParent['id'] =
        $expenseId;

    $nextParent['farm_id'] =
        $farmId;

    /*
     * Revalidate the existing allocation against the PROPOSED parent.
     * This rejects moves to a specific cycle, incompatible production types,
     * malformed scopes, non-allocatable categories and over-allocation.
     */
    $nextValidated =
        financial_allocation_service_validate_desired_rows(
            $nextParent,
            $state['cycles'],
            $financialRows,
            (int)$state['animal_count']
        );

    /*
     * allocated_amount is causal authority while allocation_percent is
     * derived metadata. If gross changes, the stored percentages would become
     * stale. Require the farmer to clear/rebuild the allocation explicitly
     * rather than silently rewriting financial allocation history here.
     */
    $currentGross =
        (int)$currentValidated[
            'parent'
        ][
            'gross_cents'
        ];

    $nextGross =
        (int)$nextValidated[
            'parent'
        ][
            'gross_cents'
        ];

    if ($currentGross !== $nextGross) {
        throw new RuntimeException(
            'Clear the current production-cycle financial allocation before changing the expense amount or quantity.'
        );
    }

    return [
        'financial_allocation_count' =>
            count($financialRows),

        'animal_allocation_count' =>
            (int)$state[
                'animal_count'
            ],

        'allocated_amount' =>
            $nextValidated[
                'allocated_amount'
            ],

        'remaining_amount' =>
            $nextValidated[
                'remaining_amount'
            ],
    ];
}
}

if (!function_exists(
    'financial_allocation_integrity_assert_animal_mutation'
)) {
function financial_allocation_integrity_assert_animal_mutation(
    PDO $pdo,
    int $farmId,
    int $expenseId,
    array $allocation
): array {
    $state =
        financial_allocation_integrity_lock_state(
            $pdo,
            $farmId,
            $expenseId
        );

    $requested =
        financial_allocation_integrity_requested_animal_rows(
            $allocation
        );

    if (
        $requested
        &&
        !empty(
            $state[
                'financial_rows'
            ]
        )
    ) {
        throw new RuntimeException(
            'Clear the current production-cycle financial allocation before allocating this expense to individual animals.'
        );
    }

    return [
        'financial_allocation_count' =>
            count(
                $state[
                    'financial_rows'
                ]
            ),

        'animal_allocation_count' =>
            (int)$state[
                'animal_count'
            ],

        'requested_animal_rows' =>
            $requested,
    ];
}
}
