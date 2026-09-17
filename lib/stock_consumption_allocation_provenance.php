<?php

require_once __DIR__ . '/shared_cost_contract.php';

/*
 * V3.0.1 immutable consumed-stock allocation provenance.
 *
 * Pure contract:
 * - no SQL
 * - no PDO
 * - no database mutation
 *
 * causal fingerprint:
 *   economic parent facts + cycle_id + allocated_amount
 *
 * state fingerprint:
 *   causal facts + allocation_percent + notes + descriptive parent state
 *
 * allocation_percent remains derived metadata.
 * revision action/reason/actor are event metadata and are intentionally
 * outside both fingerprints.
 */

if (!function_exists('stock_consumption_allocation_provenance_canonicalize')) {
function stock_consumption_allocation_provenance_canonicalize($value)
{
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
                stock_consumption_allocation_provenance_canonicalize(
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
            stock_consumption_allocation_provenance_canonicalize(
                $item
            );
    }

    return $value;
}
}

if (!function_exists('stock_consumption_allocation_provenance_json')) {
function stock_consumption_allocation_provenance_json(
    array $value
): string {
    $json =
        json_encode(
            stock_consumption_allocation_provenance_canonicalize(
                $value
            ),
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
        );

    if (!is_string($json)) {
        throw new RuntimeException(
            'Unable to encode stock allocation provenance.'
        );
    }

    return $json;
}
}

if (!function_exists('stock_consumption_allocation_provenance_hash')) {
function stock_consumption_allocation_provenance_hash(
    array $value
): string {
    return hash(
        'sha256',
        stock_consumption_allocation_provenance_json(
            $value
        )
    );
}
}

if (!function_exists('stock_consumption_allocation_provenance_decimal')) {
function stock_consumption_allocation_provenance_decimal(
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
            'Stock allocation decimal value is invalid.'
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

if (!function_exists('stock_consumption_allocation_provenance_nullable_string')) {
function stock_consumption_allocation_provenance_nullable_string(
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

if (!function_exists('stock_consumption_allocation_provenance_parent')) {
function stock_consumption_allocation_provenance_parent(
    array $movement
): array {
    return [
        'stock_transaction_id' =>
            (int)(
                $movement['id']
                ?? $movement[
                    'stock_transaction_id'
                ]
                ?? 0
            ),

        'farm_id' =>
            (int)(
                $movement['farm_id']
                ?? 0
            ),

        'stock_item_id' =>
            (int)(
                $movement['stock_item_id']
                ?? 0
            ),

        'transaction_type' =>
            strtolower(
                trim(
                    (string)(
                        $movement[
                            'transaction_type'
                        ]
                        ?? ''
                    )
                )
            ),

        'transaction_date' =>
            trim(
                (string)(
                    $movement[
                        'transaction_date'
                    ]
                    ?? ''
                )
            ),

        'quantity' =>
            stock_consumption_allocation_provenance_decimal(
                $movement['quantity']
                ?? 0
            ),

        'unit_cost' =>
            stock_consumption_allocation_provenance_decimal(
                $movement['unit_cost']
                ?? 0
            ),

        'total_cost' =>
            stock_consumption_allocation_provenance_decimal(
                $movement['total_cost']
                ?? 0
            ),

        'farm_type' =>
            stock_consumption_allocation_provenance_nullable_string(
                $movement['farm_type']
                ?? null,
                true
            ),

        'production_type' =>
            stock_consumption_allocation_provenance_nullable_string(
                $movement[
                    'production_type'
                ]
                ?? null,
                true
            ),

        'attribution_scope' =>
            stock_consumption_allocation_provenance_nullable_string(
                $movement[
                    'attribution_scope'
                ]
                ?? null,
                true
            ),

        'financial_classification' =>
            stock_consumption_allocation_provenance_nullable_string(
                $movement[
                    'financial_classification'
                ]
                ?? null,
                true
            ),

        'source_type' =>
            stock_consumption_allocation_provenance_nullable_string(
                $movement['source_type']
                ?? null,
                true
            ),

        'source_id' =>
            empty(
                $movement['source_id']
            )
                ? null
                : (int)$movement[
                    'source_id'
                ],
    ];
}
}

if (!function_exists('stock_consumption_allocation_provenance_causal_rows')) {
function stock_consumption_allocation_provenance_causal_rows(
    array $rows
): array {
    $result = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            throw new InvalidArgumentException(
                'Stock allocation row must be an array.'
            );
        }

        $result[] = [
            'cycle_id' =>
                (int)(
                    $row['cycle_id']
                    ?? 0
                ),

            'allocated_amount' =>
                stock_consumption_allocation_provenance_decimal(
                    $row[
                        'allocated_amount'
                    ]
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
            return strcmp(
                stock_consumption_allocation_provenance_json(
                    $left
                ),
                stock_consumption_allocation_provenance_json(
                    $right
                )
            );
        }
    );

    return $result;
}
}

if (!function_exists('stock_consumption_allocation_provenance_state_rows')) {
function stock_consumption_allocation_provenance_state_rows(
    array $rows
): array {
    $result = [];

    foreach ($rows as $row) {
        $result[] = [
            'cycle_id' =>
                (int)(
                    $row['cycle_id']
                    ?? 0
                ),

            'allocated_amount' =>
                stock_consumption_allocation_provenance_decimal(
                    $row[
                        'allocated_amount'
                    ]
                    ?? 0
                ),

            'allocation_percent' =>
                stock_consumption_allocation_provenance_decimal(
                    $row[
                        'allocation_percent'
                    ]
                    ?? 0
                ),

            'notes' =>
                stock_consumption_allocation_provenance_nullable_string(
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
            return strcmp(
                stock_consumption_allocation_provenance_json(
                    $left
                ),
                stock_consumption_allocation_provenance_json(
                    $right
                )
            );
        }
    );

    return $result;
}
}

if (!function_exists('stock_consumption_allocation_provenance_build')) {
function stock_consumption_allocation_provenance_build(
    array $movement,
    array $rows
): array {
    $parent =
        stock_consumption_allocation_provenance_parent(
            $movement
        );

    $parentAmount =
        shared_cost_contract_money(
            shared_cost_contract_money_cents(
                $movement['total_cost']
                ?? null,
                'Consumed stock cost'
            )
        );

    $allocated = [];

    foreach ($rows as $row) {
        $allocated[] =
            $row['allocated_amount']
            ?? null;
    }

    $conservation =
        shared_cost_contract_conservation(
            $parentAmount,
            $allocated
        );

    $causalManifest = [
        'parent' =>
            $parent,

        'allocations' =>
            stock_consumption_allocation_provenance_causal_rows(
                $rows
            ),
    ];

    $stateManifest = [
        'parent' =>
            array_merge(
                $parent,
                [
                    'remarks' =>
                        stock_consumption_allocation_provenance_nullable_string(
                            $movement['remarks']
                            ?? null
                        ),
                ]
            ),

        'allocations' =>
            stock_consumption_allocation_provenance_state_rows(
                $rows
            ),
    ];

    return [
        'parent_amount' =>
            $conservation[
                'parent_amount'
            ],

        'allocated_amount' =>
            $conservation[
                'allocated_amount'
            ],

        'unallocated_amount' =>
            $conservation[
                'unallocated_amount'
            ],

        'causal_manifest' =>
            $causalManifest,

        'causal_manifest_json' =>
            stock_consumption_allocation_provenance_json(
                $causalManifest
            ),

        'causal_fingerprint' =>
            stock_consumption_allocation_provenance_hash(
                $causalManifest
            ),

        'state_manifest' =>
            $stateManifest,

        'state_manifest_json' =>
            stock_consumption_allocation_provenance_json(
                $stateManifest
            ),

        'state_fingerprint' =>
            stock_consumption_allocation_provenance_hash(
                $stateManifest
            ),
    ];
}
}
