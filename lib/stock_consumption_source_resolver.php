<?php

require_once __DIR__ . '/shared_cost_contract.php';

/*
 * V3.0.1 consumed-stock source resolver.
 *
 * The stock movement remains the canonical economic transaction.
 *
 * Source ownership:
 *
 *   inventory_manual
 *   inventory_api
 *   manual_feed
 *       -> movement-authoritative
 *
 *   daily_layer_record
 *   daily_broiler_record
 *   daily_ruminant_record
 *       -> linked Daily Record is authoritative for cycle attribution.
 *
 * Reversal source types and unknown source types are never accepted
 * as new shared-cost allocation parents.
 *
 * No database mutation occurs in this service.
 */

if (!function_exists('stock_consumption_source_resolver_normalize')) {
function stock_consumption_source_resolver_normalize(
    $value
): string {
    return shared_cost_contract_normalize(
        $value
    );
}
}

if (!function_exists('stock_consumption_source_resolver_definition')) {
function stock_consumption_source_resolver_definition(
    $sourceType
): array {
    $sourceType =
        stock_consumption_source_resolver_normalize(
            $sourceType
        );

    $definitions = [
        'inventory_manual' => [
            'mode' =>
                'movement_authority',

            'table' =>
                null,
        ],

        'inventory_api' => [
            'mode' =>
                'movement_authority',

            'table' =>
                null,
        ],

        'manual_feed' => [
            'mode' =>
                'movement_authority',

            'table' =>
                null,
        ],

        'daily_layer_record' => [
            'mode' =>
                'linked_daily_record',

            'table' =>
                'layer_daily_records',

            'expected_farm_type' =>
                'poultry',

            'expected_production_type' =>
                'layer',
        ],

        'daily_broiler_record' => [
            'mode' =>
                'linked_daily_record',

            'table' =>
                'broiler_daily_records',

            'expected_farm_type' =>
                'poultry',

            'expected_production_type' =>
                'broiler',
        ],

        'daily_ruminant_record' => [
            'mode' =>
                'linked_daily_record',

            'table' =>
                'ruminant_daily_records',

            'expected_farm_type' =>
                'ruminant',

            /*
             * Species remains cycle/source-data dependent.
             * Do not invent a concrete species here.
             */
            'expected_production_type' =>
                null,
        ],
    ];

    if (isset($definitions[$sourceType])) {
        return array_merge(
            [
                'source_type' =>
                    $sourceType,

                'supported' =>
                    true,

                'allocatable' =>
                    true,
            ],
            $definitions[$sourceType]
        );
    }

    if (
        $sourceType !== ''
        &&
        substr(
            $sourceType,
            -9
        ) === '_reversal'
    ) {
        return [
            'source_type' =>
                $sourceType,

            'supported' =>
                true,

            'allocatable' =>
                false,

            'mode' =>
                'reversal',

            'table' =>
                null,
        ];
    }

    return [
        'source_type' =>
            $sourceType,

        'supported' =>
            false,

        'allocatable' =>
            false,

        'mode' =>
            'unsupported',

        'table' =>
            null,
    ];
}
}

if (!function_exists('stock_consumption_source_resolver_assert_allocatable')) {
function stock_consumption_source_resolver_assert_allocatable(
    array $movement
): array {
    $definition =
        stock_consumption_source_resolver_definition(
            $movement['source_type']
            ?? null
        );

    if (!$definition['supported']) {
        throw new RuntimeException(
            'The stock movement source is not supported for shared-cost allocation.'
        );
    }

    if (!$definition['allocatable']) {
        throw new RuntimeException(
            'A stock reversal source cannot be used as a shared-cost allocation parent.'
        );
    }

    if (
        $definition['mode']
            === 'linked_daily_record'
        &&
        (int)(
            $movement['source_id']
            ?? 0
        ) < 1
    ) {
        throw new RuntimeException(
            'Daily-record stock usage is missing its authoritative source record.'
        );
    }

    return $definition;
}
}

if (!function_exists('stock_consumption_source_resolver_require_lock_transaction')) {
function stock_consumption_source_resolver_require_lock_transaction(
    PDO $pdo,
    bool $forUpdate
): void {
    if (
        $forUpdate
        &&
        !$pdo->inTransaction()
    ) {
        throw new RuntimeException(
            'Authoritative stock-source locking requires an active transaction.'
        );
    }
}
}

if (!function_exists('stock_consumption_source_resolver_resolve')) {
function stock_consumption_source_resolver_resolve(
    PDO $pdo,
    array $movement,
    bool $forUpdate = false
): array {
    $definition =
        stock_consumption_source_resolver_assert_allocatable(
            $movement
        );

    stock_consumption_source_resolver_require_lock_transaction(
        $pdo,
        $forUpdate
    );

    if (
        $definition['mode']
        === 'movement_authority'
    ) {
        return [
            'definition' =>
                $definition,

            'mode' =>
                'movement_authority',

            /*
             * The movement itself owns its attribution.
             * The allocation adapter therefore receives NULL as
             * its external authoritative source.
             */
            'authoritative_source' =>
                null,

            'source_row' =>
                null,
        ];
    }

    $farmId =
        (int)(
            $movement['farm_id']
            ?? 0
        );

    $sourceId =
        (int)(
            $movement['source_id']
            ?? 0
        );

    if (
        $farmId < 1
        ||
        $sourceId < 1
    ) {
        throw new RuntimeException(
            'Daily-record stock source identity is invalid.'
        );
    }

    /*
     * Table name comes only from the closed definition map above.
     * It is never caller supplied.
     */
    $table =
        (string)$definition['table'];

    $sql =
        "SELECT
             id,
             farm_id,
             cycle_id
         FROM {$table}
         WHERE id=?
           AND farm_id=?
         LIMIT 1";

    if ($forUpdate) {
        $sql .=
            " FOR UPDATE";
    }

    $stmt =
        $pdo->prepare(
            $sql
        );

    $stmt->execute([
        $sourceId,
        $farmId,
    ]);

    $row =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$row) {
        throw new RuntimeException(
            'The authoritative Daily Record linked to this stock movement was not found.'
        );
    }

    $cycleId =
        (int)(
            $row['cycle_id']
            ?? 0
        );

    return [
        'definition' =>
            $definition,

        'mode' =>
            'linked_daily_record',

        'authoritative_source' => [
            'cycle_id' =>
                $cycleId > 0
                    ? $cycleId
                    : null,
        ],

        'source_row' => [
            'id' =>
                (int)$row['id'],

            'farm_id' =>
                (int)$row['farm_id'],

            'cycle_id' =>
                $cycleId > 0
                    ? $cycleId
                    : null,
        ],
    ];
}
}

if (!function_exists('stock_consumption_source_resolver_assert_cycle_consistency')) {
function stock_consumption_source_resolver_assert_cycle_consistency(
    array $movement,
    array $resolution
): void {
    if (
        ($resolution['mode'] ?? '')
        !== 'linked_daily_record'
    ) {
        return;
    }

    $movementCycleId =
        (int)(
            $movement['cycle_id']
            ?? 0
        );

    $sourceCycleId =
        (int)(
            $resolution[
                'authoritative_source'
            ]['cycle_id']
            ?? 0
        );

    if ($movementCycleId !== $sourceCycleId) {
        throw new RuntimeException(
            'Authoritative Daily Record cycle attribution differs from the stock movement. Correct the stock ledger attribution instead of allocating this movement.'
        );
    }
}
}
