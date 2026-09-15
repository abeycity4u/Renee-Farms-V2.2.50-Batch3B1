<?php
/**
 * Renee Farms V3.0 — tagged-ruminant transfer UI read model.
 *
 * This helper owns read-only preparation for Animal Profile.
 * Actual movement/membership mutation remains exclusively in
 * ruminant_cycle_transfer_record().
 */

require_once __DIR__
    . '/ruminant_cycle_transfer.php';

if (!function_exists(
    'ruminant_cycle_transfer_workspace'
)) {
    function ruminant_cycle_transfer_workspace(
        PDO $pdo,
        int $farmId,
        int $animalId
    ): array {
        if (
            $farmId <= 0
            || $animalId <= 0
        ) {
            throw new InvalidArgumentException(
                'Select a valid animal.'
            );
        }

        $memberships =
            ruminant_cycle_memberships_for_animal(
                $pdo,
                $farmId,
                $animalId
            );

        $openMemberships =
            array_values(
                array_filter(
                    $memberships,
                    static function (
                        array $membership
                    ): bool {
                        return
                            $membership['end_date']
                            === null;
                    }
                )
            );

        $currentMembership = null;
        $sourceCycle = null;
        $destinationCycles = [];
        $ready = false;
        $readinessMessage = null;

        if (count($openMemberships) > 1) {
            $readinessMessage =
                'Multiple open production-cycle '
                . 'memberships exist. Correct the '
                . 'membership history before moving '
                . 'this animal.';
        } elseif (!$openMemberships) {
            $readinessMessage =
                'Assign the animal to its current '
                . 'production cycle before moving it.';
        } else {
            $currentMembership =
                $openMemberships[0];

            $sourceStmt =
                $pdo->prepare(
                    'SELECT
                         pc.id,
                         pc.cycle_code,
                         pc.farm_type,
                         pc.production_type,
                         pc.livestock_type_id,
                         pc.status,
                         pc.start_date,
                         pc.close_date,
                         b.baseline_date
                     FROM production_cycles pc
                     LEFT JOIN production_population_baselines b
                       ON b.farm_id = pc.farm_id
                      AND b.cycle_id = pc.id
                     WHERE pc.id = ?
                       AND pc.farm_id = ?
                     LIMIT 1'
                );

            $sourceStmt->execute([
                (int)$currentMembership[
                    'cycle_id'
                ],
                $farmId,
            ]);

            $sourceCycle =
                $sourceStmt->fetch(
                    PDO::FETCH_ASSOC
                ) ?: null;

            if (!$sourceCycle) {
                $readinessMessage =
                    'The current production cycle '
                    . 'could not be found.';
            } elseif (
                strtolower(
                    (string)$sourceCycle[
                        'status'
                    ]
                ) !== 'active'
            ) {
                $readinessMessage =
                    'The current production cycle '
                    . 'must be Active before this '
                    . 'animal can be moved.';
            } elseif (
                empty(
                    $sourceCycle[
                        'baseline_date'
                    ]
                )
            ) {
                $readinessMessage =
                    'Confirm the current population '
                    . 'for '
                    . (string)$sourceCycle[
                        'cycle_code'
                    ]
                    . ' before moving this animal.';
            } else {
                $destinationStmt =
                    $pdo->prepare(
                        'SELECT
                             pc.id,
                             pc.cycle_code,
                             pc.start_date,
                             pc.close_date,
                             b.baseline_date
                         FROM production_cycles pc
                         INNER JOIN production_population_baselines b
                           ON b.farm_id = pc.farm_id
                          AND b.cycle_id = pc.id
                         WHERE pc.farm_id = ?
                           AND pc.id <> ?
                           AND pc.status = "active"
                           AND LOWER(pc.farm_type)
                               = LOWER(?)
                           AND LOWER(pc.production_type)
                               = LOWER(?)
                           AND pc.livestock_type_id
                               <=> ?
                         ORDER BY
                             pc.start_date DESC,
                             pc.id DESC'
                    );

                $destinationStmt->execute([
                    $farmId,
                    (int)$sourceCycle['id'],
                    (string)$sourceCycle[
                        'farm_type'
                    ],
                    (string)$sourceCycle[
                        'production_type'
                    ],
                    $sourceCycle[
                        'livestock_type_id'
                    ] !== null
                        ? (int)$sourceCycle[
                            'livestock_type_id'
                        ]
                        : null,
                ]);

                $destinationCycles =
                    $destinationStmt->fetchAll(
                        PDO::FETCH_ASSOC
                    );

                if (!$destinationCycles) {
                    $readinessMessage =
                        'No other Active production '
                        . 'cycle with confirmed current '
                        . 'population and the same '
                        . 'production identity is '
                        . 'available yet.';
                } else {
                    $ready = true;
                }
            }
        }

        $historyStmt =
            $pdo->prepare(
                'SELECT
                     rat.id,
                     rat.population_transfer_id,
                     pt.from_cycle_id,
                     pt.to_cycle_id,
                     pt.transfer_date,
                     pt.notes,
                     rat.created_at,
                     rat.reversed_at,
                     rat.reversal_reason,
                     from_cycle.cycle_code
                         AS from_cycle_code,
                     to_cycle.cycle_code
                         AS to_cycle_code
                 FROM ruminant_animal_cycle_transfers rat
                 INNER JOIN production_population_transfers pt
                   ON pt.id =
                        rat.population_transfer_id
                  AND pt.farm_id =
                        rat.farm_id
                 INNER JOIN production_cycles from_cycle
                   ON from_cycle.id =
                        pt.from_cycle_id
                  AND from_cycle.farm_id =
                        rat.farm_id
                 INNER JOIN production_cycles to_cycle
                   ON to_cycle.id =
                        pt.to_cycle_id
                  AND to_cycle.farm_id =
                        rat.farm_id
                 WHERE rat.farm_id = ?
                   AND rat.animal_id = ?
                 ORDER BY
                     pt.transfer_date DESC,
                     rat.id DESC'
            );

        $historyStmt->execute([
            $farmId,
            $animalId,
        ]);

        return [
            'memberships' =>
                $memberships,

            'current_membership' =>
                $currentMembership,

            'source_cycle' =>
                $sourceCycle,

            'destination_cycles' =>
                $destinationCycles,

            'transfer_history' =>
                $historyStmt->fetchAll(
                    PDO::FETCH_ASSOC
                ),

            'ready' =>
                $ready,

            'readiness_message' =>
                $readinessMessage,
        ];
    }
}
