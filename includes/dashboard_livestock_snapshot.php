<?php
/**
 * Dashboard livestock population adapter.
 *
 * Physical population policy belongs to the shared population-intelligence
 * contract. This adapter only shapes those shared active-cycle snapshots for
 * the Dashboard ticker and exposes whether legacy estimates are contributing.
 */

require_once __DIR__ . '/../lib/production_population_intelligence.php';

if (!function_exists('dashboard_livestock_snapshot')) {
function dashboard_livestock_snapshot(
    PDO $pdo,
    int $farmId,
    string $farmAccess
): array {
    $snapshot = [
        'poultry' => [
            'Layer' => null,
            'Broiler' => null,
        ],
        'ruminant' => [
            'Cattle' => null,
            'Goat' => null,
            'Sheep' => null,
            'Other' => null,
        ],
        'tracking' => [
            'canonical_cycles' => 0,
            'legacy_estimate_cycles' => 0,
            'untracked_without_snapshot_cycles' => 0,
            'read_error' => false,
        ],
    ];

    if (
        $farmId <= 0
        || !in_array(
            $farmAccess,
            [
                'poultry',
                'ruminant',
                'both',
            ],
            true
        )
    ) {
        return $snapshot;
    }

    try {
        $cycleSnapshots =
            production_population_intelligence_active_cycle_snapshots(
                $pdo,
                $farmId,
                $farmAccess
            );
    } catch (Throwable $e) {
        $snapshot['tracking']['read_error'] =
            true;

        return $snapshot;
    }

    $poultryTotals = [
        'Layer' => 0,
        'Broiler' => 0,
    ];

    $poultryFound = [
        'Layer' => false,
        'Broiler' => false,
    ];

    $ruminantTotals = [
        'Cattle' => 0,
        'Goat' => 0,
        'Sheep' => 0,
        'Other' => 0,
    ];

    $ruminantFound = [
        'Cattle' => false,
        'Goat' => false,
        'Sheep' => false,
        'Other' => false,
    ];

    foreach ($cycleSnapshots as $cycleSnapshot) {
        $trackingStatus =
            (string)(
                $cycleSnapshot[
                    'tracking_status'
                ]
                ?? ''
            );

        $hasSnapshot =
            !empty(
                $cycleSnapshot[
                    'has_snapshot'
                ]
            );

        if ($trackingStatus === 'canonical') {
            $snapshot[
                'tracking'
            ][
                'canonical_cycles'
            ]++;
        } elseif (
            $trackingStatus
            === 'legacy_untracked'
        ) {
            if ($hasSnapshot) {
                $snapshot[
                    'tracking'
                ][
                    'legacy_estimate_cycles'
                ]++;
            } else {
                $snapshot[
                    'tracking'
                ][
                    'untracked_without_snapshot_cycles'
                ]++;
            }
        }

        if (!$hasSnapshot) {
            continue;
        }

        $quantity =
            max(
                0,
                (int)(
                    $cycleSnapshot[
                        'quantity'
                    ]
                    ?? 0
                )
            );

        $farmType =
            strtolower(
                trim(
                    (string)(
                        $cycleSnapshot[
                            'farm_type'
                        ]
                        ?? ''
                    )
                )
            );

        $productionType =
            strtolower(
                trim(
                    (string)(
                        $cycleSnapshot[
                            'production_type'
                        ]
                        ?? ''
                    )
                )
            );

        if ($farmType === 'poultry') {
            $poultryLabels = [
                'layer' => 'Layer',
                'broiler' => 'Broiler',
            ];

            if (
                !isset(
                    $poultryLabels[
                        $productionType
                    ]
                )
            ) {
                continue;
            }

            $label =
                $poultryLabels[
                    $productionType
                ];

            $poultryTotals[$label] +=
                $quantity;

            $poultryFound[$label] =
                true;

            continue;
        }

        if ($farmType === 'ruminant') {
            $ruminantLabels = [
                'cattle' => 'Cattle',
                'goat' => 'Goat',
                'sheep' => 'Sheep',
                'other' => 'Other',
            ];

            $label =
                $ruminantLabels[
                    $productionType
                ]
                ?? 'Other';

            $ruminantTotals[$label] +=
                $quantity;

            $ruminantFound[$label] =
                true;
        }
    }

    foreach (
        $poultryTotals as
        $label => $quantity
    ) {
        $snapshot[
            'poultry'
        ][$label] =
            $poultryFound[$label]
                ? $quantity
                : null;
    }

    foreach (
        $ruminantTotals as
        $label => $quantity
    ) {
        $snapshot[
            'ruminant'
        ][$label] =
            $ruminantFound[$label]
                ? $quantity
                : null;
    }

    return $snapshot;
}
}
