<?php

require_once __DIR__
    . '/layer_egg_inventory.php';

/*
 * V3.0.1 canonical manual shared-revenue allocation policy.
 *
 * This service owns validation only.
 * It performs no INSERT / UPDATE / DELETE.
 *
 * sales_records remains the one parent revenue source.
 * sales_allocations is only an attribution projection.
 *
 * Automatic Layer egg revenue remains owned by sales_allocation.php and is
 * deliberately excluded from this manual contract.
 */

if (!function_exists(
    'sale_revenue_allocation_service_money_cents'
)) {
function sale_revenue_allocation_service_money_cents(
    $value,
    string $label = 'Amount'
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

    $cents =
        (int)round(
            (float)$value * 100
        );

    if ($cents < 0) {
        throw new InvalidArgumentException(
            $label . ' cannot be negative.'
        );
    }

    return $cents;
}
}


if (!function_exists(
    'sale_revenue_allocation_service_money_string'
)) {
function sale_revenue_allocation_service_money_string(
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


if (!function_exists(
    'sale_revenue_allocation_service_notes'
)) {
function sale_revenue_allocation_service_notes(
    $value
): ?string {
    $value =
        trim(
            (string)(
                $value
                ?? ''
            )
        );

    if ($value === '') {
        return null;
    }

    $length =
        function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);

    if ($length > 255) {
        throw new InvalidArgumentException(
            'Revenue allocation notes cannot exceed 255 characters.'
        );
    }

    return $value;
}
}


if (!function_exists(
    'sale_revenue_allocation_service_is_automatic_layer_egg'
)) {
function sale_revenue_allocation_service_is_automatic_layer_egg(
    array $sale
): bool {
    return
        strtolower(
            trim(
                (string)(
                    $sale['farm_type']
                    ?? ''
                )
            )
        ) === 'poultry'
        &&
        strtolower(
            trim(
                (string)(
                    $sale['production_type']
                    ?? ''
                )
            )
        ) === 'layer'
        &&
        layer_egg_is_sale_product(
            $sale['product_type']
            ?? null
        );
}
}


if (!function_exists(
    'sale_revenue_allocation_service_parent_contract'
)) {
function sale_revenue_allocation_service_parent_contract(
    array $sale
): array {
    $saleId =
        (int)(
            $sale['id']
            ?? 0
        );

    $farmId =
        (int)(
            $sale['farm_id']
            ?? 0
        );

    if (
        $saleId < 1
        ||
        $farmId < 1
    ) {
        throw new InvalidArgumentException(
            'Shared revenue source identity is invalid.'
        );
    }

    if (
        isset($sale['cycle_id'])
        &&
        (int)$sale['cycle_id'] > 0
    ) {
        throw new RuntimeException(
            'A cycle-attributed sale cannot also use shared revenue allocation.'
        );
    }

    $farmType =
        strtolower(
            trim(
                (string)(
                    $sale['farm_type']
                    ?? ''
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
        throw new RuntimeException(
            'Only shared poultry or ruminant revenue can be manually allocated.'
        );
    }

    $productionType =
        strtolower(
            trim(
                (string)(
                    $sale['production_type']
                    ?? ''
                )
            )
        );

    if ($productionType === '') {
        throw new RuntimeException(
            'Shared revenue production attribution is missing.'
        );
    }

    $scope =
        strtolower(
            trim(
                (string)(
                    $sale['attribution_scope']
                    ?? ''
                )
            )
        );

    if (
        !in_array(
            $scope,
            [
                'farm',
                'production_type',
            ],
            true
        )
    ) {
        throw new RuntimeException(
            'Shared revenue attribution scope is invalid.'
        );
    }

    $saleDate =
        trim(
            (string)(
                $sale['sale_date']
                ?? ''
            )
        );

    if (
        preg_match(
            '/^\\d{4}-\\d{2}-\\d{2}$/',
            $saleDate
        ) !== 1
    ) {
        throw new RuntimeException(
            'Shared revenue sale date is invalid.'
        );
    }

    if (
        sale_revenue_allocation_service_is_automatic_layer_egg(
            $sale
        )
    ) {
        throw new RuntimeException(
            'Layer egg revenue is managed by the automatic unsold-egg allocation contract.'
        );
    }

    $parentCents =
        sale_revenue_allocation_service_money_cents(
            $sale['total_amount']
            ?? null,
            'Sale total'
        );

    if ($parentCents <= 0) {
        throw new RuntimeException(
            'Shared revenue total must be greater than zero.'
        );
    }

    return [
        'sale_id' =>
            $saleId,

        'farm_id' =>
            $farmId,

        'farm_type' =>
            $farmType,

        'production_type' =>
            $productionType,

        'attribution_scope' =>
            $scope,

        'sale_date' =>
            $saleDate,

        'parent_cents' =>
            $parentCents,

        'parent_amount' =>
            sale_revenue_allocation_service_money_string(
                $parentCents
            ),

        'allocation_basis' =>
            'manual_shared_revenue',
    ];
}
}


if (!function_exists(
    'sale_revenue_allocation_service_target_contract'
)) {
function sale_revenue_allocation_service_target_contract(
    array $parent,
    array $cycle
): array {
    $cycleId =
        (int)(
            $cycle['id']
            ?? 0
        );

    $cycleFarmId =
        (int)(
            $cycle['farm_id']
            ?? 0
        );

    if (
        $cycleId < 1
        ||
        $cycleFarmId < 1
    ) {
        throw new InvalidArgumentException(
            'Revenue allocation target cycle is invalid.'
        );
    }

    if (
        $cycleFarmId
        !==
        (int)$parent['farm_id']
    ) {
        throw new RuntimeException(
            'Revenue allocation target cycle does not belong to this farm.'
        );
    }

    $cycleFarmType =
        strtolower(
            trim(
                (string)(
                    $cycle['farm_type']
                    ?? ''
                )
            )
        );

    if (
        $cycleFarmType
        !==
        (string)$parent['farm_type']
    ) {
        throw new RuntimeException(
            'Revenue allocation target cycle does not match the sale farm type.'
        );
    }

    $parentProduction =
        strtolower(
            trim(
                (string)(
                    $parent[
                        'production_type'
                    ]
                    ?? ''
                )
            )
        );

    $cycleProduction =
        strtolower(
            trim(
                (string)(
                    $cycle[
                        'production_type'
                    ]
                    ?? ''
                )
            )
        );

    if (
        $parentProduction !== 'shared'
        &&
        $cycleProduction !== $parentProduction
    ) {
        throw new RuntimeException(
            'Revenue allocation target cycle does not match the sale production type.'
        );
    }

    $cycleStartDate =
        trim(
            (string)(
                $cycle['start_date']
                ?? ''
            )
        );

    if ($cycleStartDate !== '') {
        if (
            preg_match(
                '/^\\d{4}-\\d{2}-\\d{2}$/',
                $cycleStartDate
            ) !== 1
        ) {
            throw new RuntimeException(
                'Revenue allocation target cycle start date is invalid.'
            );
        }

        if (
            $cycleStartDate
            >
            (string)$parent['sale_date']
        ) {
            throw new RuntimeException(
                'Revenue cannot be allocated to a production cycle that starts after the sale date.'
            );
        }
    }

    return [
        'id' =>
            $cycleId,

        'farm_id' =>
            $cycleFarmId,

        'farm_type' =>
            $cycleFarmType,

        'production_type' =>
            $cycleProduction,

        'status' =>
            strtolower(
                trim(
                    (string)(
                        $cycle['status']
                        ?? ''
                    )
                )
            ),
    ];
}
}


if (!function_exists(
    'sale_revenue_allocation_service_validate_desired_rows'
)) {
function sale_revenue_allocation_service_validate_desired_rows(
    array $sale,
    array $cycles,
    array $desiredRows,
    int $animalAllocationCount = 0
): array {
    $parent =
        sale_revenue_allocation_service_parent_contract(
            $sale
        );

    if ($animalAllocationCount > 0) {
        throw new RuntimeException(
            'Revenue allocated to individual animals cannot also be allocated to production cycles.'
        );
    }

    $cycleMap = [];

    foreach ($cycles as $cycle) {
        $target =
            sale_revenue_allocation_service_target_contract(
                $parent,
                $cycle
            );

        $cycleMap[
            (int)$target['id']
        ] =
            $target;
    }

    $seen = [];
    $rows = [];
    $allocatedCents = 0;

    foreach ($desiredRows as $row) {
        if (!is_array($row)) {
            throw new InvalidArgumentException(
                'Revenue allocation row is invalid.'
            );
        }

        $cycleId =
            (int)(
                $row['cycle_id']
                ?? 0
            );

        if ($cycleId < 1) {
            throw new InvalidArgumentException(
                'Select a valid revenue allocation target cycle.'
            );
        }

        if (isset($seen[$cycleId])) {
            throw new InvalidArgumentException(
                'The same production cycle cannot appear more than once.'
            );
        }

        $seen[$cycleId] = true;

        if (!isset($cycleMap[$cycleId])) {
            throw new RuntimeException(
                'Revenue allocation target cycle is unavailable or incompatible.'
            );
        }

        $amountCents =
            sale_revenue_allocation_service_money_cents(
                $row['allocated_amount']
                ?? null,
                'Allocated revenue'
            );

        if ($amountCents <= 0) {
            throw new InvalidArgumentException(
                'Revenue allocation amounts must be greater than zero.'
            );
        }

        $allocatedCents +=
            $amountCents;

        if (
            $allocatedCents
            >
            (int)$parent['parent_cents']
        ) {
            throw new InvalidArgumentException(
                'Total revenue allocations cannot exceed the sale total.'
            );
        }

        $rows[] = [
            'cycle_id' =>
                $cycleId,

            'allocated_amount' =>
                sale_revenue_allocation_service_money_string(
                    $amountCents
                ),

            'allocation_percent' =>
                number_format(
                    (
                        $amountCents
                        /
                        (int)$parent['parent_cents']
                    )
                    * 100,
                    4,
                    '.',
                    ''
                ),

            'allocation_basis' =>
                'manual_shared_revenue',

            'allocated_quantity' =>
                null,

            'allocation_unit' =>
                null,

            'notes' =>
                sale_revenue_allocation_service_notes(
                    $row['notes']
                    ?? null
                ),
        ];
    }

    usort(
        $rows,
        static function (
            array $left,
            array $right
        ): int {
            return
                (int)$left['cycle_id']
                <=>
                (int)$right['cycle_id'];
        }
    );

    $remainingCents =
        (int)$parent['parent_cents']
        - $allocatedCents;

    return [
        'parent' =>
            $parent,

        'rows' =>
            $rows,

        'allocated_cents' =>
            $allocatedCents,

        'allocated_amount' =>
            sale_revenue_allocation_service_money_string(
                $allocatedCents
            ),

        'remaining_cents' =>
            $remainingCents,

        'remaining_amount' =>
            sale_revenue_allocation_service_money_string(
                $remainingCents
            ),
    ];
}
}
