<?php

require_once __DIR__ . '/attribution.php';

/*
 * V3.0.1 Canonical Stock Movement Attribution Resolver
 *
 * Purpose:
 * - one attribution decision for every stock movement writer;
 * - preserve deliberate production-level pooling;
 * - preserve deliberate farm/module-level sharing;
 * - validate direct cycle attribution centrally;
 * - never silently turn an invalid requested cycle into pooled usage.
 *
 * This service is read-only. It performs no database mutation.
 */

if (!function_exists(
    'stock_movement_attribution_normalize'
)) {
function stock_movement_attribution_normalize(
    $value
): string {
    return strtolower(
        trim(
            (string)$value
        )
    );
}
}

if (!function_exists(
    'stock_movement_attribution_allowed_production_types'
)) {
function stock_movement_attribution_allowed_production_types(
    string $farmType
): array {
    $farmType =
        stock_movement_attribution_normalize(
            $farmType
        );

    if ($farmType === 'both') {
        return [
            'shared' =>
                'Shared / Farm-wide',
        ];
    }

    return
        attribution_production_types(
            $farmType
        );
}
}

if (!function_exists(
    'stock_movement_attribution_resolve'
)) {
function stock_movement_attribution_resolve(
    PDO $pdo,
    int $farmId,
    array $item,
    string $transactionType,
    ?string $requestedFarmType = null,
    ?string $requestedFeedCategory = null,
    ?int $requestedCycleId = null,
    ?string $productionTypeOverride = null
): array {
    if ($farmId < 1) {
        throw new InvalidArgumentException(
            'Stock movement farm identity is invalid.'
        );
    }

    $transactionType =
        stock_movement_attribution_normalize(
            $transactionType
        );

    if (
        !in_array(
            $transactionType,
            [
                'received',
                'used',
            ],
            true
        )
    ) {
        throw new RuntimeException(
            'Stock movement transaction type is invalid.'
        );
    }

    $itemFarmType =
        stock_movement_attribution_normalize(
            $item['farm_type']
            ?? ''
        );

    if (
        !in_array(
            $itemFarmType,
            [
                'poultry',
                'ruminant',
                'both',
                'general',
            ],
            true
        )
    ) {
        throw new RuntimeException(
            'Inventory item farm attribution is invalid.'
        );
    }

    $movementFarmType =
        stock_movement_attribution_normalize(
            $requestedFarmType
        );

    if ($movementFarmType === '') {
        $movementFarmType =
            $itemFarmType;
    }

    if (
        $itemFarmType !== 'both'
        &&
        $movementFarmType !== $itemFarmType
    ) {
        throw new RuntimeException(
            'Stock movement farm attribution does not match the inventory item.'
        );
    }

    if (
        $itemFarmType === 'both'
        &&
        !in_array(
            $movementFarmType,
            [
                'poultry',
                'ruminant',
                'both',
            ],
            true
        )
    ) {
        throw new RuntimeException(
            'Shared inventory item farm attribution is invalid.'
        );
    }

    $feedCategory =
        stock_movement_attribution_normalize(
            $requestedFeedCategory
        );

    if ($feedCategory === '') {
        $feedCategory =
            stock_movement_attribution_normalize(
                $item['feed_category']
                ?? 'general'
            );
    }

    $requestedProduction =
        stock_movement_attribution_normalize(
            $productionTypeOverride
        );

    /*
     * Fixed Poultry feed categories own their operational production type.
     *
     * A hidden/stale client value must never turn Layer feed into Shared
     * Poultry or Broiler feed into another production operation.
     */
    if (
        in_array(
            $feedCategory,
            [
                'layer',
                'broiler',
            ],
            true
        )
    ) {
        if (
            $requestedProduction !== ''
            &&
            $requestedProduction
                !== $feedCategory
        ) {
            throw new RuntimeException(
                'Feed production attribution must match its inventory feed category.'
            );
        }

        $requestedProduction =
            $feedCategory;

    } elseif (
        $movementFarmType === 'general'
    ) {
        /*
         * General-farm movements normalize to the canonical General
         * production owner. Preserve compatibility with an old "shared"
         * default while refusing unrelated livestock ownership.
         */
        if (
            $requestedProduction !== ''
            &&
            !in_array(
                $requestedProduction,
                [
                    'general',
                    'shared',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'General stock cannot be attributed to a livestock production type.'
            );
        }

        $requestedProduction =
            'general';

    } elseif (
        $requestedProduction === ''
    ) {
        if (
            $feedCategory === 'ruminant'
        ) {
            /*
             * Ruminant feed without a cycle is legitimately shared inside
             * the Ruminant module. A direct cycle may later narrow this
             * to cattle/goat/sheep/other.
             */
            $requestedProduction =
                'shared';

        } elseif (
            $feedCategory === 'general'
        ) {
            $requestedProduction =
                stock_movement_attribution_normalize(
                    $item[
                        'default_production_type'
                    ]
                    ?? 'shared'
                );

            if (
                $requestedProduction === ''
            ) {
                $requestedProduction =
                    'shared';
            }

        } else {
            $requestedProduction =
                'shared';
        }
    }

    $allowedProduction =
        stock_movement_attribution_allowed_production_types(
            $movementFarmType
        );

    if (
        !array_key_exists(
            $requestedProduction,
            $allowedProduction
        )
    ) {
        throw new RuntimeException(
            'Selected production attribution is not valid for this stock movement.'
        );
    }

    $productionType =
        attribution_normalize_production_type(
            $movementFarmType,
            $requestedProduction
        );

    $cycleId =
        (int)(
            $requestedCycleId
            ?? 0
        );

    if ($cycleId > 0) {
        $cycleStmt =
            $pdo->prepare(
                "SELECT
                     id,
                     farm_id,
                     farm_type,
                     production_type,
                     status
                 FROM production_cycles
                 WHERE id=?
                   AND farm_id=?
                 LIMIT 1"
            );

        $cycleStmt->execute([
            $cycleId,
            $farmId,
        ]);

        $cycle =
            $cycleStmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$cycle) {
            throw new RuntimeException(
                'The selected production cycle does not belong to this farm.'
            );
        }

        $cycleFarmType =
            stock_movement_attribution_normalize(
                $cycle['farm_type']
                ?? ''
            );

        $cycleProductionType =
            stock_movement_attribution_normalize(
                $cycle['production_type']
                ?? ''
            );

        if (
            $movementFarmType !== 'both'
            &&
            $cycleFarmType !== $movementFarmType
        ) {
            throw new RuntimeException(
                'The selected production cycle does not match the stock movement farm type.'
            );
        }

        $dynamicRuminantFeed =
            $feedCategory === 'ruminant'
            &&
            $movementFarmType === 'ruminant'
            &&
            stock_movement_attribution_normalize(
                $productionTypeOverride
            ) === '';

        if (!$dynamicRuminantFeed) {
            if ($productionType === 'shared') {
                throw new RuntimeException(
                    'Choose a concrete production attribution before selecting a production cycle.'
                );
            }

            if (
                $cycleProductionType
                !== $productionType
            ) {
                throw new RuntimeException(
                    'The selected production cycle does not match the selected production attribution.'
                );
            }
        }

        $movementFarmType =
            $cycleFarmType;

        $productionType =
            $cycleProductionType;
    }

    $resolvedCycleId =
        $cycleId > 0
            ? $cycleId
            : null;

    return [
        'farm_type' =>
            $movementFarmType,

        'production_type' =>
            $productionType,

        'cycle_id' =>
            $resolvedCycleId,

        'attribution_scope' =>
            attribution_scope(
                $resolvedCycleId,
                $movementFarmType,
                $productionType
            ),
    ];
}
}
