<?php

/*
 * V3.0.1 immutable Expense Revision Provenance foundation.
 *
 * This file is deliberately pure:
 * - no SQL
 * - no PDO
 * - no database writes
 * - no permission policy
 *
 * It defines the canonical representation that a later centralized expense
 * mutation service will persist.
 *
 * Two fingerprints are intentionally distinct:
 *
 * 1. causal_fingerprint
 *    Only facts capable of changing attribution/economic outcomes.
 *    Description wording, actor metadata, allocation row IDs and derived
 *    allocation percentages are excluded.
 *
 * 2. state_fingerprint
 *    Full auditable business state. Descriptive/actor metadata is retained.
 *
 * This separation preserves Production-Entry wording invariance while still
 * allowing the immutable expense ledger to prove what the complete record
 * looked like at each revision.
 */

if (!function_exists('expense_revision_array_is_list')) {
function expense_revision_array_is_list(array $value): bool
{
    if ($value === []) {
        return true;
    }

    return array_keys($value)
        === range(0, count($value) - 1);
}
}

if (!function_exists('expense_revision_canonicalize')) {
function expense_revision_canonicalize($value)
{
    if (!is_array($value)) {
        return $value;
    }

    if (expense_revision_array_is_list($value)) {
        $result = [];

        foreach ($value as $item) {
            $result[] =
                expense_revision_canonicalize(
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
            expense_revision_canonicalize(
                $item
            );
    }

    return $value;
}
}

if (!function_exists('expense_revision_json')) {
function expense_revision_json(array $manifest): string
{
    $json =
        json_encode(
            expense_revision_canonicalize(
                $manifest
            ),
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
        );

    if (!is_string($json)) {
        throw new RuntimeException(
            'Unable to encode canonical expense revision manifest.'
        );
    }

    return $json;
}
}

if (!function_exists('expense_revision_hash')) {
function expense_revision_hash(array $manifest): string
{
    return hash(
        'sha256',
        expense_revision_json(
            $manifest
        )
    );
}
}

if (!function_exists('expense_revision_nullable_string')) {
function expense_revision_nullable_string(
    $value,
    bool $lowercase = false
): ?string {
    if (
        $value === null
        || trim((string)$value) === ''
    ) {
        return null;
    }

    $value =
        trim(
            (string)$value
        );

    return $lowercase
        ? strtolower($value)
        : $value;
}
}

if (!function_exists('expense_revision_decimal')) {
function expense_revision_decimal($value): string
{
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
        !preg_match(
            '/^([+-]?)(\d+)(?:\.(\d+))?$/',
            $value,
            $matches
        )
    ) {
        throw new InvalidArgumentException(
            'Expense revision decimal value is invalid.'
        );
    }

    $negative =
        ($matches[1] ?? '') === '-';

    $whole =
        ltrim(
            $matches[2],
            '0'
        );

    if ($whole === '') {
        $whole = '0';
    }

    $fraction =
        rtrim(
            $matches[3] ?? '',
            '0'
        );

    $normalized =
        $whole
        . (
            $fraction === ''
                ? ''
                : '.' . $fraction
        );

    if ($normalized === '0') {
        return '0';
    }

    return $negative
        ? '-' . $normalized
        : $normalized;
}
}

if (!function_exists('expense_revision_causal_expense')) {
function expense_revision_causal_expense(
    array $expense
): array {
    return [
        'amount' =>
            expense_revision_decimal(
                $expense['amount'] ?? 0
            ),

        'attribution_scope' =>
            expense_revision_nullable_string(
                $expense['attribution_scope']
                ?? null,
                true
            ),

        'category' =>
            strtolower(
                trim(
                    (string)(
                        $expense['category']
                        ?? ''
                    )
                )
            ),

        'cycle_id' =>
            empty($expense['cycle_id'])
                ? null
                : (int)$expense['cycle_id'],

        'expense_date' =>
            trim(
                (string)(
                    $expense['expense_date']
                    ?? ''
                )
            ),

        'expense_id' =>
            (int)(
                $expense['id']
                ?? $expense['expense_id']
                ?? 0
            ),

        'farm_id' =>
            (int)(
                $expense['farm_id']
                ?? 0
            ),

        'farm_type' =>
            strtolower(
                trim(
                    (string)(
                        $expense['farm_type']
                        ?? ''
                    )
                )
            ),

        'poultry_category' =>
            expense_revision_nullable_string(
                $expense['poultry_category']
                ?? null,
                true
            ),

        'production_type' =>
            expense_revision_nullable_string(
                $expense['production_type']
                ?? null,
                true
            ),

        'unit' =>
            expense_revision_decimal(
                $expense['unit'] ?? 0
            ),
    ];
}
}

if (!function_exists('expense_revision_causal_financial_allocations')) {
function expense_revision_causal_financial_allocations(
    array $rows
): array {
    $normalized = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            throw new InvalidArgumentException(
                'Financial allocation revision state must be an array.'
            );
        }

        /*
         * allocation_percent is intentionally excluded.
         * allocated_amount is the causal economic authority.
         *
         * Row IDs are also excluded: recreating the same semantic allocation
         * must not create a false economic revision.
         */
        $normalized[] = [
            'allocated_amount' =>
                expense_revision_decimal(
                    $row['allocated_amount']
                    ?? 0
                ),

            'cycle_id' =>
                empty($row['cycle_id'])
                    ? null
                    : (int)$row['cycle_id'],
        ];
    }

    usort(
        $normalized,
        static function (
            array $left,
            array $right
        ): int {
            return strcmp(
                expense_revision_json(
                    $left
                ),
                expense_revision_json(
                    $right
                )
            );
        }
    );

    return $normalized;
}
}

if (!function_exists('expense_revision_causal_animal_allocations')) {
function expense_revision_causal_animal_allocations(
    array $rows
): array {
    $normalized = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            throw new InvalidArgumentException(
                'Animal allocation revision state must be an array.'
            );
        }

        /*
         * Allocation row IDs, allocation_method and allocation_percent are
         * deliberately excluded from the causal digest.
         *
         * The economic result is which animal receives what amount. The
         * current helper may delete/reinsert rows during an edit, so database
         * row identity cannot be allowed to manufacture a false economic
         * change.
         */
        $normalized[] = [
            'allocated_amount' =>
                expense_revision_decimal(
                    $row['allocated_amount']
                    ?? 0
                ),

            'animal_id' =>
                (int)(
                    $row['animal_id']
                    ?? 0
                ),
        ];
    }

    usort(
        $normalized,
        static function (
            array $left,
            array $right
        ): int {
            return strcmp(
                expense_revision_json(
                    $left
                ),
                expense_revision_json(
                    $right
                )
            );
        }
    );

    return $normalized;
}
}

if (!function_exists('expense_revision_causal_manifest')) {
function expense_revision_causal_manifest(
    array $expense,
    array $financialAllocations = [],
    array $animalAllocations = []
): array {
    return [
        'expense' =>
            expense_revision_causal_expense(
                $expense
            ),

        'financial_allocations' =>
            expense_revision_causal_financial_allocations(
                $financialAllocations
            ),

        'ruminant_animal_allocations' =>
            expense_revision_causal_animal_allocations(
                $animalAllocations
            ),

        'schema' =>
            'renee.farm-expense.causal.v1',
    ];
}
}

if (!function_exists('expense_revision_state_expense')) {
function expense_revision_state_expense(
    array $expense
): array {
    /*
     * Explicit field selection is intentional.
     *
     * Future projection metadata such as expense_revision_no and
     * expense_causal_fingerprint must never be hashed into the state that
     * generated those same metadata values.
     */
    return [
        'amount' =>
            $expense['amount']
            ?? null,

        'attribution_scope' =>
            $expense['attribution_scope']
            ?? null,

        'category' =>
            $expense['category']
            ?? null,

        'created_at' =>
            $expense['created_at']
            ?? null,

        'cycle_id' =>
            $expense['cycle_id']
            ?? null,

        'description' =>
            $expense['description']
            ?? '',

        'expense_date' =>
            $expense['expense_date']
            ?? null,

        'expense_id' =>
            $expense['id']
            ?? $expense['expense_id']
            ?? null,

        'farm_id' =>
            $expense['farm_id']
            ?? null,

        'farm_type' =>
            $expense['farm_type']
            ?? null,

        'poultry_category' =>
            $expense['poultry_category']
            ?? null,

        'production_type' =>
            $expense['production_type']
            ?? null,

        'unit' =>
            $expense['unit']
            ?? null,

        'user_id' =>
            $expense['user_id']
            ?? null,
    ];
}
}

if (!function_exists('expense_revision_state_financial_allocation')) {
function expense_revision_state_financial_allocation(
    array $row
): array {
    return [
        'allocated_amount' =>
            $row['allocated_amount']
            ?? null,

        'allocation_percent' =>
            $row['allocation_percent']
            ?? null,

        'created_at' =>
            $row['created_at']
            ?? null,

        'created_by' =>
            $row['created_by']
            ?? null,

        'cycle_id' =>
            $row['cycle_id']
            ?? null,

        'expense_id' =>
            $row['expense_id']
            ?? null,

        'farm_id' =>
            $row['farm_id']
            ?? null,

        'id' =>
            $row['id']
            ?? null,

        'notes' =>
            $row['notes']
            ?? null,
    ];
}
}

if (!function_exists('expense_revision_state_animal_allocation')) {
function expense_revision_state_animal_allocation(
    array $row
): array {
    return [
        'allocated_amount' =>
            $row['allocated_amount']
            ?? null,

        'allocation_method' =>
            $row['allocation_method']
            ?? null,

        'allocation_percent' =>
            $row['allocation_percent']
            ?? null,

        'animal_id' =>
            $row['animal_id']
            ?? null,

        'created_at' =>
            $row['created_at']
            ?? null,

        'created_by' =>
            $row['created_by']
            ?? null,

        'expense_id' =>
            $row['expense_id']
            ?? null,

        'farm_id' =>
            $row['farm_id']
            ?? null,

        'id' =>
            $row['id']
            ?? null,
    ];
}
}

if (!function_exists('expense_revision_state_manifest')) {
function expense_revision_state_manifest(
    array $expense,
    array $financialAllocations = [],
    array $animalAllocations = []
): array {
    $financial = [];

    foreach ($financialAllocations as $row) {
        $financial[] =
            expense_revision_state_financial_allocation(
                $row
            );
    }

    usort(
        $financial,
        static function (
            array $left,
            array $right
        ): int {
            return strcmp(
                expense_revision_json(
                    $left
                ),
                expense_revision_json(
                    $right
                )
            );
        }
    );

    $animals = [];

    foreach ($animalAllocations as $row) {
        $animals[] =
            expense_revision_state_animal_allocation(
                $row
            );
    }

    usort(
        $animals,
        static function (
            array $left,
            array $right
        ): int {
            return strcmp(
                expense_revision_json(
                    $left
                ),
                expense_revision_json(
                    $right
                )
            );
        }
    );

    return [
        'expense' =>
            expense_revision_state_expense(
                $expense
            ),

        'financial_allocations' =>
            $financial,

        'ruminant_animal_allocations' =>
            $animals,

        'schema' =>
            'renee.farm-expense.state.v1',
    ];
}
}

if (!function_exists('expense_revision_build')) {
function expense_revision_build(
    array $expense,
    array $financialAllocations = [],
    array $animalAllocations = []
): array {
    $causalManifest =
        expense_revision_causal_manifest(
            $expense,
            $financialAllocations,
            $animalAllocations
        );

    $stateManifest =
        expense_revision_state_manifest(
            $expense,
            $financialAllocations,
            $animalAllocations
        );

    $causalJson =
        expense_revision_json(
            $causalManifest
        );

    $stateJson =
        expense_revision_json(
            $stateManifest
        );

    return [
        'causal_manifest' =>
            $causalManifest,

        'causal_manifest_json' =>
            $causalJson,

        'causal_fingerprint' =>
            hash(
                'sha256',
                $causalJson
            ),

        'state_manifest' =>
            $stateManifest,

        'state_manifest_json' =>
            $stateJson,

        'state_fingerprint' =>
            hash(
                'sha256',
                $stateJson
            ),
    ];
}
}
