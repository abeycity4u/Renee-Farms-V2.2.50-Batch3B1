<?php

require_once __DIR__
    . '/sale_revenue_allocation_service.php';

/*
 * V3.0.1 immutable manual shared-revenue allocation provenance.
 *
 * Pure contract:
 * - no SQL;
 * - no PDO;
 * - no database mutation.
 *
 * causal fingerprint:
 *   allocation-relevant sale facts + cycle_id + allocated_amount
 *
 * state fingerprint:
 *   causal state + derived percent + manual basis + notes.
 *
 * Actor, revision action and revision reason are event metadata and remain
 * outside the state fingerprints.
 */

if (!function_exists(
    'sale_revenue_allocation_provenance_canonicalize'
)) {
function sale_revenue_allocation_provenance_canonicalize(
    $value
) {
    if (!is_array($value)) {
        return $value;
    }

    if ($value === []) {
        return [];
    }

    $isList =
        array_keys($value)
        === range(
            0,
            count($value) - 1
        );

    if ($isList) {
        $result = [];

        foreach ($value as $item) {
            $result[] =
                sale_revenue_allocation_provenance_canonicalize(
                    $item
                );
        }

        return $result;
    }

    ksort(
        $value,
        SORT_STRING
    );

    foreach ($value as $key => $item) {
        $value[$key] =
            sale_revenue_allocation_provenance_canonicalize(
                $item
            );
    }

    return $value;
}
}


if (!function_exists(
    'sale_revenue_allocation_provenance_json'
)) {
function sale_revenue_allocation_provenance_json(
    array $value
): string {
    $json =
        json_encode(
            sale_revenue_allocation_provenance_canonicalize(
                $value
            ),
            JSON_UNESCAPED_SLASHES
            |
            JSON_UNESCAPED_UNICODE
        );

    if (!is_string($json)) {
        throw new RuntimeException(
            'Unable to encode shared revenue allocation provenance.'
        );
    }

    return $json;
}
}


if (!function_exists(
    'sale_revenue_allocation_provenance_hash'
)) {
function sale_revenue_allocation_provenance_hash(
    array $value
): string {
    return hash(
        'sha256',
        sale_revenue_allocation_provenance_json(
            $value
        )
    );
}
}


if (!function_exists(
    'sale_revenue_allocation_provenance_decimal'
)) {
function sale_revenue_allocation_provenance_decimal(
    $value
): string {
    if (is_int($value)) {
        return (string)$value;
    }

    if (is_float($value)) {
        $value =
            sprintf(
                '%.12F',
                $value
            );
    }

    $value =
        trim(
            (string)$value
        );

    if (
        preg_match(
            '/^([+-]?)(\d+)(?:\.(\d+))?$/',
            $value,
            $m
        ) !== 1
    ) {
        throw new InvalidArgumentException(
            'Revenue allocation decimal value is invalid.'
        );
    }

    $negative =
        ($m[1] ?? '') === '-';

    $whole =
        ltrim(
            $m[2],
            '0'
        );

    if ($whole === '') {
        $whole = '0';
    }

    $fraction =
        rtrim(
            $m[3] ?? '',
            '0'
        );

    $result =
        $whole
        . (
            $fraction === ''
                ? ''
                : '.'
                    . $fraction
        );

    if ($result === '0') {
        return '0';
    }

    return
        $negative
            ? '-'
                . $result
            : $result;
}
}


if (!function_exists(
    'sale_revenue_allocation_provenance_nullable_string'
)) {
function sale_revenue_allocation_provenance_nullable_string(
    $value,
    bool $lower = false
): ?string {
    if (
        $value === null
        ||
        trim(
            (string)$value
        ) === ''
    ) {
        return null;
    }

    $value =
        trim(
            (string)$value
        );

    return
        $lower
            ? strtolower($value)
            : $value;
}
}


if (!function_exists(
    'sale_revenue_allocation_provenance_parent'
)) {
function sale_revenue_allocation_provenance_parent(
    array $sale
): array {
    return [
        'sale_id' =>
            (int)(
                $sale['id']
                ?? $sale['sale_id']
                ?? 0
            ),

        'farm_id' =>
            (int)(
                $sale['farm_id']
                ?? 0
            ),

        'public_reference' =>
            sale_revenue_allocation_provenance_nullable_string(
                $sale['public_reference']
                ?? null
            ),

        'sale_date' =>
            trim(
                (string)(
                    $sale['sale_date']
                    ?? ''
                )
            ),

        'farm_type' =>
            sale_revenue_allocation_provenance_nullable_string(
                $sale['farm_type']
                ?? null,
                true
            ),

        'production_type' =>
            sale_revenue_allocation_provenance_nullable_string(
                $sale['production_type']
                ?? null,
                true
            ),

        'attribution_scope' =>
            sale_revenue_allocation_provenance_nullable_string(
                $sale['attribution_scope']
                ?? null,
                true
            ),

        'cycle_id' =>
            empty(
                $sale['cycle_id']
            )
                ? null
                : (int)$sale['cycle_id'],

        'product_type' =>
            sale_revenue_allocation_provenance_nullable_string(
                $sale['product_type']
                ?? null
            ),

        'quantity' =>
            sale_revenue_allocation_provenance_decimal(
                $sale['quantity']
                ?? 0
            ),

        'unit_of_measure' =>
            sale_revenue_allocation_provenance_nullable_string(
                $sale['unit_of_measure']
                ?? null,
                true
            ),

        'unit_price' =>
            sale_revenue_allocation_provenance_decimal(
                $sale['unit_price']
                ?? 0
            ),

        'total_amount' =>
            sale_revenue_allocation_provenance_decimal(
                $sale['total_amount']
                ?? 0
            ),
    ];
}
}


if (!function_exists(
    'sale_revenue_allocation_provenance_causal_rows'
)) {
function sale_revenue_allocation_provenance_causal_rows(
    array $rows
): array {
    $result = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            throw new InvalidArgumentException(
                'Revenue allocation row must be an array.'
            );
        }

        $result[] = [
            'cycle_id' =>
                (int)(
                    $row['cycle_id']
                    ?? 0
                ),

            'allocated_amount' =>
                sale_revenue_allocation_provenance_decimal(
                    $row['allocated_amount']
                    ?? 0
                ),
        ];
    }

    usort(
        $result,
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

    return $result;
}
}


if (!function_exists(
    'sale_revenue_allocation_provenance_state_rows'
)) {
function sale_revenue_allocation_provenance_state_rows(
    array $rows
): array {
    $result = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            throw new InvalidArgumentException(
                'Revenue allocation row must be an array.'
            );
        }

        $result[] = [
            'cycle_id' =>
                (int)(
                    $row['cycle_id']
                    ?? 0
                ),

            'allocated_amount' =>
                sale_revenue_allocation_provenance_decimal(
                    $row['allocated_amount']
                    ?? 0
                ),

            'allocation_percent' =>
                sale_revenue_allocation_provenance_decimal(
                    $row['allocation_percent']
                    ?? 0
                ),

            'allocation_basis' =>
                sale_revenue_allocation_provenance_nullable_string(
                    $row['allocation_basis']
                    ?? null,
                    true
                ),

            'allocated_quantity' =>
                $row['allocated_quantity']
                ?? null,

            'allocation_unit' =>
                sale_revenue_allocation_provenance_nullable_string(
                    $row['allocation_unit']
                    ?? null,
                    true
                ),

            'notes' =>
                sale_revenue_allocation_provenance_nullable_string(
                    $row['notes']
                    ?? null
                ),
        ];
    }

    usort(
        $result,
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

    return $result;
}
}


if (!function_exists(
    'sale_revenue_allocation_provenance_build'
)) {
function sale_revenue_allocation_provenance_build(
    array $sale,
    array $rows
): array {
    $parent =
        sale_revenue_allocation_service_parent_contract(
            $sale
        );

    $parentManifest =
        sale_revenue_allocation_provenance_parent(
            $sale
        );

    $allocatedCents = 0;

    foreach ($rows as $row) {
        $allocatedCents +=
            sale_revenue_allocation_service_money_cents(
                $row['allocated_amount']
                ?? null,
                'Allocated revenue'
            );
    }

    if (
        $allocatedCents
        >
        (int)$parent['parent_cents']
    ) {
        throw new RuntimeException(
            'Revenue allocation provenance exceeds the parent sale total.'
        );
    }

    $remainingCents =
        (int)$parent['parent_cents']
        - $allocatedCents;

    $causalManifest = [
        'parent' =>
            $parentManifest,

        'allocations' =>
            sale_revenue_allocation_provenance_causal_rows(
                $rows
            ),
    ];

    $stateManifest = [
        'parent' =>
            $parentManifest,

        'allocations' =>
            sale_revenue_allocation_provenance_state_rows(
                $rows
            ),
    ];

    return [
        'parent_amount' =>
            sale_revenue_allocation_service_money_string(
                (int)$parent['parent_cents']
            ),

        'allocated_amount' =>
            sale_revenue_allocation_service_money_string(
                $allocatedCents
            ),

        'unallocated_amount' =>
            sale_revenue_allocation_service_money_string(
                $remainingCents
            ),

        'causal_manifest' =>
            $causalManifest,

        'causal_manifest_json' =>
            sale_revenue_allocation_provenance_json(
                $causalManifest
            ),

        'causal_fingerprint' =>
            sale_revenue_allocation_provenance_hash(
                $causalManifest
            ),

        'state_manifest' =>
            $stateManifest,

        'state_manifest_json' =>
            sale_revenue_allocation_provenance_json(
                $stateManifest
            ),

        'state_fingerprint' =>
            sale_revenue_allocation_provenance_hash(
                $stateManifest
            ),
    ];
}
}
