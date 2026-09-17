<?php

require_once __DIR__ . '/shared_cost_contract.php';

/*
 * V3.0.1 Consumed Stock Allocation foundation.
 *
 * Economic source:
 *     one effective USED stock movement.
 *
 * This service does not mutate stock history and does not write allocations.
 *
 * Parent consumed cost =
 *     explicit future cycle allocations
 *     + visible unallocated balance.
 *
 * A movement whose authoritative operational source already identifies
 * a cycle is attribution drift, not a shared-cost pool.
 */

if (!function_exists('stock_consumption_allocation_service_normalize')) {
function stock_consumption_allocation_service_normalize(
    $value
): string {
    return shared_cost_contract_normalize(
        $value
    );
}
}

if (!function_exists('stock_consumption_allocation_service_money_cents')) {
function stock_consumption_allocation_service_money_cents(
    $value,
    string $label = 'Allocated amount'
): int {
    return shared_cost_contract_money_cents(
        $value,
        $label
    );
}
}

if (!function_exists('stock_consumption_allocation_service_money_string')) {
function stock_consumption_allocation_service_money_string(
    int $cents
): string {
    return shared_cost_contract_money(
        $cents
    );
}
}

if (!function_exists('stock_consumption_allocation_service_is_effective_used')) {
function stock_consumption_allocation_service_is_effective_used(
    array $movement
): bool {
    if (
        stock_consumption_allocation_service_normalize(
            $movement['transaction_type']
            ?? ''
        ) !== 'used'
    ) {
        return false;
    }

    if (
        (int)(
            $movement['is_reversed']
            ?? 0
        ) === 1
    ) {
        return false;
    }

    if (
        isset($movement['reversal_of_id'])
        &&
        $movement['reversal_of_id'] !== null
        &&
        trim(
            (string)$movement[
                'reversal_of_id'
            ]
        ) !== ''
        &&
        (int)$movement[
            'reversal_of_id'
        ] > 0
    ) {
        return false;
    }

    return true;
}
}

if (!function_exists('stock_consumption_allocation_service_requires_source_resolution')) {
function stock_consumption_allocation_service_requires_source_resolution(
    array $movement
): bool {
    $sourceType =
        stock_consumption_allocation_service_normalize(
            $movement['source_type']
            ?? ''
        );

    return preg_match(
        '/^daily_.*_record$/',
        $sourceType
    ) === 1;
}
}

if (!function_exists('stock_consumption_allocation_service_parent_contract')) {
function stock_consumption_allocation_service_parent_contract(
    array $movement,
    ?array $authoritativeSource = null
): array {
    $movementId =
        (int)(
            $movement['id']
            ?? $movement[
                'stock_transaction_id'
            ]
            ?? 0
        );

    if ($movementId < 1) {
        throw new RuntimeException(
            'Consumed stock movement is invalid.'
        );
    }

    if (
        !stock_consumption_allocation_service_is_effective_used(
            $movement
        )
    ) {
        throw new RuntimeException(
            'Only effective USED stock movements are eligible for consumed-stock allocation.'
        );
    }

    if (
        (int)(
            $movement['cycle_id']
            ?? 0
        ) > 0
    ) {
        throw new RuntimeException(
            'Stock consumption already attributed to a production cycle is not a shared-cost parent.'
        );
    }

    $classification =
        stock_consumption_allocation_service_normalize(
            $movement[
                'financial_classification'
            ]
            ?? ''
        );

    if (
        !shared_cost_contract_stock_is_operating(
            $classification
        )
    ) {
        throw new RuntimeException(
            'This stock classification is not eligible for operating-consumption allocation.'
        );
    }

    $parentCents =
        stock_consumption_allocation_service_money_cents(
            $movement['total_cost']
            ?? null,
            'Consumed stock cost'
        );

    if ($parentCents < 1) {
        throw new RuntimeException(
            'Consumed stock cost must be greater than zero before it can be allocated.'
        );
    }

    if (
        stock_consumption_allocation_service_requires_source_resolution(
            $movement
        )
        &&
        $authoritativeSource === null
    ) {
        throw new RuntimeException(
            'Daily-record stock usage requires authoritative source attribution before allocation.'
        );
    }

    $sourceStatus =
        shared_cost_contract_source_attribution_status(
            $movement,
            $authoritativeSource
        );

    if (
        $sourceStatus
        === 'SOURCE_ATTRIBUTION_DRIFT'
    ) {
        throw new RuntimeException(
            'Authoritative source attribution identifies a different cycle; correct the stock ledger attribution instead of allocating this movement.'
        );
    }

    /*
     * Canonical source-neutral scope classification.
     *
     * Examples:
     * poultry/layer/production_type => Layer production pool
     * poultry/shared/farm           => Shared Poultry pool
     * ruminant/shared/farm          => Shared Ruminant pool
     * both/shared/farm              => Cross-module farm pool
     */
    $shared =
        shared_cost_contract_parent(
            $movement
        );

    return array_merge(
        $movement,
        $shared,
        [
            'id' =>
                $movementId,

            'stock_transaction_id' =>
                $movementId,

            'financial_classification' =>
                $classification,

            'parent_cents' =>
                $parentCents,

            'parent_amount' =>
                stock_consumption_allocation_service_money_string(
                    $parentCents
                ),

            'source_attribution_status' =>
                $sourceStatus,
        ]
    );
}
}

if (!function_exists('stock_consumption_allocation_service_target_contract')) {
function stock_consumption_allocation_service_target_contract(
    array $parentContract,
    array $cycle
): array {
    return shared_cost_contract_target(
        $parentContract,
        $cycle
    );
}
}

if (!function_exists('stock_consumption_allocation_service_validate_desired_rows')) {
function stock_consumption_allocation_service_validate_desired_rows(
    array $movement,
    array $cycles,
    array $desiredRows,
    ?array $authoritativeSource = null
): array {
    $parent =
        stock_consumption_allocation_service_parent_contract(
            $movement,
            $authoritativeSource
        );

    $cycleMap = [];

    foreach ($cycles as $key => $cycle) {
        if (!is_array($cycle)) {
            throw new RuntimeException(
                'Consumed-stock target cycle data is invalid.'
            );
        }

        $cycleId =
            (int)(
                $cycle['id']
                ?? $cycle['cycle_id']
                ?? (
                    is_numeric($key)
                        ? $key
                        : 0
                )
            );

        if ($cycleId < 1) {
            throw new RuntimeException(
                'Consumed-stock target cycle is invalid.'
            );
        }

        $cycle['id'] =
            $cycleId;

        $cycleMap[
            $cycleId
        ] =
            $cycle;
    }

    $seen = [];
    $rows = [];
    $allocatedAmounts = [];

    foreach ($desiredRows as $row) {
        if (!is_array($row)) {
            throw new RuntimeException(
                'Consumed-stock allocation row is invalid.'
            );
        }

        $cycleId =
            (int)(
                $row['cycle_id']
                ?? 0
            );

        if ($cycleId < 1) {
            throw new RuntimeException(
                'Select a valid production cycle for each consumed-stock allocation.'
            );
        }

        if (isset($seen[$cycleId])) {
            throw new RuntimeException(
                'The same production cycle cannot be allocated more than once.'
            );
        }

        if (!isset($cycleMap[$cycleId])) {
            throw new RuntimeException(
                'The selected production cycle is not available for this consumed-stock allocation.'
            );
        }

        $target =
            stock_consumption_allocation_service_target_contract(
                $parent,
                $cycleMap[$cycleId]
            );

        $amountCents =
            stock_consumption_allocation_service_money_cents(
                $row['allocated_amount']
                ?? null,
                'Allocated amount'
            );

        if ($amountCents < 1) {
            throw new InvalidArgumentException(
                'Allocated amount must be greater than zero.'
            );
        }

        $allocatedAmount =
            stock_consumption_allocation_service_money_string(
                $amountCents
            );

        $allocatedAmounts[] =
            $allocatedAmount;

        /*
         * Running conservation check rejects over-allocation immediately.
         */
        shared_cost_contract_conservation(
            $parent['parent_amount'],
            $allocatedAmounts
        );

        $percent =
            number_format(
                round(
                    (
                        $amountCents
                        / $parent[
                            'parent_cents'
                        ]
                    ) * 100,
                    4
                ),
                4,
                '.',
                ''
            );

        $rows[] =
            array_merge(
                $row,
                [
                    'cycle_id' =>
                        $cycleId,

                    'allocated_amount' =>
                        $allocatedAmount,

                    /*
                     * Display/derived metadata only.
                     * Naira allocated_amount remains authority.
                     */
                    'allocation_percent' =>
                        $percent,

                    'target_farm_type' =>
                        $target['farm_type'],

                    'target_production_type' =>
                        $target[
                            'production_type'
                        ],
                ]
            );

        $seen[$cycleId] =
            true;
    }

    $conservation =
        shared_cost_contract_conservation(
            $parent['parent_amount'],
            $allocatedAmounts
        );

    return [
        'stock_transaction_id' =>
            (int)$parent[
                'stock_transaction_id'
            ],

        'parent_amount' =>
            $conservation[
                'parent_amount'
            ],

        'allocated_amount' =>
            $conservation[
                'allocated_amount'
            ],

        'remaining_amount' =>
            $conservation[
                'unallocated_amount'
            ],

        'fully_allocated' =>
            $conservation[
                'fully_allocated'
            ],

        'source_attribution_status' =>
            $parent[
                'source_attribution_status'
            ],

        'rows' =>
            $rows,
    ];
}
}
