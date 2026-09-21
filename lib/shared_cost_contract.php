<?php

/*
 * V3.0.1 Shared Cost Contract.
 *
 * Source-neutral policy for manual expenses and consumed inventory.
 *
 * A shared cost is never silently assigned.
 *
 * Parent cost =
 *     explicitly allocated cost
 *     + visible unallocated cost.
 *
 * This file owns scope/target/conservation policy only.
 * It performs no database mutations.
 */

if (!function_exists('shared_cost_contract_normalize')) {
function shared_cost_contract_normalize($value): string
{
    return strtolower(
        trim(
            (string)$value
        )
    );
}
}

if (!function_exists('shared_cost_contract_money_cents')) {
function shared_cost_contract_money_cents(
    $value,
    string $label
): int {
    if (
        $value === null
        ||
        $value === ''
        ||
        !is_numeric($value)
    ) {
        throw new InvalidArgumentException(
            $label . ' must be a valid amount.'
        );
    }

    $number = (float)$value;

    if (
        !is_finite($number)
        ||
        $number < 0
    ) {
        throw new InvalidArgumentException(
            $label . ' cannot be negative.'
        );
    }

    return (int)round(
        $number * 100,
        0,
        PHP_ROUND_HALF_UP
    );
}
}

if (!function_exists('shared_cost_contract_money')) {
function shared_cost_contract_money(
    int $cents
): string {
    return number_format(
        $cents / 100,
        2,
        '.',
        ''
    );
}
}

if (!function_exists('shared_cost_contract_production_types')) {
function shared_cost_contract_production_types(
    string $farmType
): array {
    $farmType =
        shared_cost_contract_normalize(
            $farmType
        );

    if ($farmType === 'poultry') {
        return [
            'layer',
            'broiler',
        ];
    }

    if ($farmType === 'ruminant') {
        return [
            'cattle',
            'goat',
            'sheep',
            'other',
        ];
    }

    return [];
}
}

if (!function_exists('shared_cost_contract_parent')) {
function shared_cost_contract_parent(
    array $row
): array {
    $farmId =
        (int)(
            $row['farm_id']
            ?? 0
        );

    if ($farmId < 1) {
        throw new RuntimeException(
            'Shared cost parent farm is invalid.'
        );
    }

    $cycleIdRaw =
        $row['cycle_id']
        ?? null;

    $cycleId =
        (
            $cycleIdRaw === null
            ||
            trim((string)$cycleIdRaw) === ''
        )
            ? null
            : (int)$cycleIdRaw;

    if (($cycleId ?? 0) > 0) {
        throw new RuntimeException(
            'A cost already attributed to a specific cycle is not a shared-cost parent.'
        );
    }

    $farmType =
        shared_cost_contract_normalize(
            $row['farm_type']
            ?? ''
        );

    $productionType =
        shared_cost_contract_normalize(
            $row['production_type']
            ?? ''
        );

    $scope =
        shared_cost_contract_normalize(
            $row['attribution_scope']
            ?? ''
        );

    $scopeType = null;

    if (
        in_array(
            $farmType,
            [
                'poultry',
                'ruminant',
            ],
            true
        )
    ) {
        $concrete =
            shared_cost_contract_production_types(
                $farmType
            );

        if (
            in_array(
                $productionType,
                $concrete,
                true
            )
        ) {
            if ($scope !== 'production_type') {
                throw new RuntimeException(
                    'Concrete production shared cost must use production-type attribution.'
                );
            }

            $scopeType =
                'production_pool';

        } elseif ($productionType === 'shared') {
            if ($scope !== 'farm') {
                throw new RuntimeException(
                    'Farm-type shared cost must use farm attribution.'
                );
            }

            $scopeType =
                'farm_type_pool';

        } else {
            throw new RuntimeException(
                'Shared cost production type is unsupported.'
            );
        }

    } elseif ($farmType === 'both') {
        if (
            $productionType !== 'shared'
            ||
            $scope !== 'farm'
        ) {
            throw new RuntimeException(
                'Cross-module shared cost must use both/shared farm attribution.'
            );
        }

        $scopeType =
            'cross_module_pool';

    } else {
        throw new RuntimeException(
            'Shared cost farm type is unsupported.'
        );
    }

    return [
        'farm_id' =>
            $farmId,

        'farm_type' =>
            $farmType,

        'production_type' =>
            $productionType,

        'attribution_scope' =>
            $scope,

        'scope_type' =>
            $scopeType,
    ];
}
}

if (!function_exists('shared_cost_contract_target')) {
function shared_cost_contract_target(
    array $parent,
    array $cycle
): array {
    $cycleId =
        (int)(
            $cycle['id']
            ?? 0
        );

    if ($cycleId < 1) {
        throw new RuntimeException(
            'Select a valid target production cycle.'
        );
    }

    $cycleFarmId =
        (int)(
            $cycle['farm_id']
            ?? 0
        );

    if (
        $cycleFarmId < 1
        ||
        $cycleFarmId
            !== (int)$parent['farm_id']
    ) {
        throw new RuntimeException(
            'The target cycle does not belong to the shared-cost farm.'
        );
    }

    $cycleFarmType =
        shared_cost_contract_normalize(
            $cycle['farm_type']
            ?? ''
        );

    $cycleProductionType =
        shared_cost_contract_normalize(
            $cycle['production_type']
            ?? ''
        );

    if (
        !in_array(
            $cycleProductionType,
            shared_cost_contract_production_types(
                $cycleFarmType
            ),
            true
        )
    ) {
        throw new RuntimeException(
            'The target cycle production type is not supported.'
        );
    }

    $scopeType =
        (string)$parent['scope_type'];

    if ($scopeType === 'production_pool') {
        if (
            $cycleFarmType
                !== (string)$parent['farm_type']
            ||
            $cycleProductionType
                !== (string)$parent[
                    'production_type'
                ]
        ) {
            throw new RuntimeException(
                'Production-level shared cost may only be allocated to matching production cycles.'
            );
        }

    } elseif ($scopeType === 'farm_type_pool') {
        if (
            $cycleFarmType
                !== (string)$parent['farm_type']
        ) {
            throw new RuntimeException(
                'Farm-type shared cost may only be allocated within its farm type.'
            );
        }

    } elseif ($scopeType === 'cross_module_pool') {
        if (
            !in_array(
                $cycleFarmType,
                [
                    'poultry',
                    'ruminant',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'Cross-module shared cost target must be a Poultry or Ruminant cycle.'
            );
        }

    } else {
        throw new RuntimeException(
            'Shared cost scope is unsupported.'
        );
    }

    /*
     * Cycle status is intentionally not restricted.
     * Historical allocation/correction may legitimately target a closed cycle.
     */
    return [
        'id' =>
            $cycleId,

        'farm_id' =>
            $cycleFarmId,

        'farm_type' =>
            $cycleFarmType,

        'production_type' =>
            $cycleProductionType,

        'status' =>
            shared_cost_contract_normalize(
                $cycle['status']
                ?? ''
            ),
    ];
}
}


if (!function_exists('shared_cost_contract_business_date')) {
function shared_cost_contract_business_date(
    $value,
    string $label
): string {
    $value =
        trim(
            (string)$value
        );

    if (
        preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $value
        ) !== 1
    ) {
        throw new InvalidArgumentException(
            $label . ' is invalid.'
        );
    }

    [$year, $month, $day] =
        array_map(
            'intval',
            explode(
                '-',
                $value
            )
        );

    if (
        !checkdate(
            $month,
            $day,
            $year
        )
    ) {
        throw new InvalidArgumentException(
            $label . ' is invalid.'
        );
    }

    return $value;
}
}

if (!function_exists('shared_cost_contract_is_pre_cycle')) {
function shared_cost_contract_is_pre_cycle(
    $parentDate,
    array $cycle
): bool {
    $parentDate =
        shared_cost_contract_business_date(
            $parentDate,
            'Cost date'
        );

    $cycleStartDate =
        shared_cost_contract_business_date(
            $cycle['start_date']
            ?? null,
            'Production cycle start date'
        );

    return
        $cycleStartDate
        >
        $parentDate;
}
}

if (!function_exists('shared_cost_contract_assert_pre_cycle_reason')) {
function shared_cost_contract_assert_pre_cycle_reason(
    $parentDate,
    array $cycles,
    array $rows,
    ?string $reason
): void {
    if ($rows === []) {
        return;
    }

    $cycleMap = [];

    foreach ($cycles as $cycle) {
        if (!is_array($cycle)) {
            throw new RuntimeException(
                'Pre-cycle allocation target data is invalid.'
            );
        }

        $cycleId =
            (int)(
                $cycle['id']
                ?? $cycle['cycle_id']
                ?? 0
            );

        if ($cycleId > 0) {
            $cycleMap[$cycleId] =
                $cycle;
        }
    }

    foreach ($rows as $row) {
        if (!is_array($row)) {
            throw new RuntimeException(
                'Pre-cycle allocation row is invalid.'
            );
        }

        $cycleId =
            (int)(
                $row['cycle_id']
                ?? $row['target_cycle_id']
                ?? 0
            );

        if (
            $cycleId < 1
            ||
            !isset(
                $cycleMap[$cycleId]
            )
        ) {
            throw new RuntimeException(
                'Pre-cycle allocation target is unavailable.'
            );
        }

        if (
            shared_cost_contract_is_pre_cycle(
                $parentDate,
                $cycleMap[$cycleId]
            )
            &&
            trim(
                (string)(
                    $reason
                    ?? ''
                )
            ) === ''
        ) {
            throw new InvalidArgumentException(
                'Enter a reason explaining how this cost prepared the selected future-start cycle.'
            );
        }
    }
}
}

if (!function_exists('shared_cost_contract_conservation')) {
function shared_cost_contract_conservation(
    $parentAmount,
    array $allocatedAmounts
): array {
    $parentCents =
        shared_cost_contract_money_cents(
            $parentAmount,
            'Shared cost parent amount'
        );

    $allocatedCents = 0;

    foreach ($allocatedAmounts as $amount) {
        $cents =
            shared_cost_contract_money_cents(
                $amount,
                'Allocated amount'
            );

        if ($cents < 1) {
            throw new InvalidArgumentException(
                'Allocated amount must be greater than zero.'
            );
        }

        $allocatedCents += $cents;

        if ($allocatedCents > $parentCents) {
            throw new RuntimeException(
                'Shared cost allocations cannot exceed the parent cost.'
            );
        }
    }

    $unallocatedCents =
        $parentCents
        - $allocatedCents;

    return [
        'parent_cents' =>
            $parentCents,

        'allocated_cents' =>
            $allocatedCents,

        'unallocated_cents' =>
            $unallocatedCents,

        'parent_amount' =>
            shared_cost_contract_money(
                $parentCents
            ),

        'allocated_amount' =>
            shared_cost_contract_money(
                $allocatedCents
            ),

        'unallocated_amount' =>
            shared_cost_contract_money(
                $unallocatedCents
            ),

        'fully_allocated' =>
            $unallocatedCents === 0,
    ];
}
}

if (!function_exists('shared_cost_contract_stock_is_operating')) {
function shared_cost_contract_stock_is_operating(
    string $classification
): bool {
    return in_array(
        shared_cost_contract_normalize(
            $classification
        ),
        [
            'feed',
            'medication_vaccine',
            'supplement',
            'consumables',
        ],
        true
    );
}
}

if (!function_exists('shared_cost_contract_source_attribution_status')) {
function shared_cost_contract_source_attribution_status(
    array $movement,
    ?array $authoritativeSource
): string {
    /*
     * When a linked authoritative source already identifies a cycle,
     * a cycle-less movement is attribution drift, not a shared pool.
     */
    if ($authoritativeSource === null) {
        return 'NO_AUTHORITATIVE_SOURCE';
    }

    $sourceCycleId =
        (int)(
            $authoritativeSource['cycle_id']
            ?? 0
        );

    $movementCycleId =
        (int)(
            $movement['cycle_id']
            ?? 0
        );

    if (
        $sourceCycleId > 0
        &&
        $movementCycleId !== $sourceCycleId
    ) {
        return 'SOURCE_ATTRIBUTION_DRIFT';
    }

    if (
        $sourceCycleId > 0
        &&
        $movementCycleId === $sourceCycleId
    ) {
        return 'SOURCE_ATTRIBUTION_MATCH';
    }

    return 'SOURCE_HAS_NO_CYCLE';
}
}
