<?php

require_once __DIR__
    . '/daily_population_boundary.php';

require_once __DIR__
    . '/ruminant_cycle_membership.php';

/**
 * Canonical read-only Ruminant economic-participation contract.
 *
 * Physical population owns the per-head denominator.
 * Registered/tagged membership only proves whether a named animal is
 * individually entitled to one of those physical-head shares.
 *
 * A tagged animal is never allowed to stand in for unregistered livestock.
 *
 * Date-level exposure rule:
 * - opening livestock participated during the business day;
 * - additions on that date also participated during the business day;
 * - removals on that date do not erase earlier same-day participation.
 *
 * Therefore:
 *
 *   exposure headcount = opening headcount + same-day additions
 *
 * This service never writes population, membership, financial or Inventory
 * history.
 */

if (!class_exists(
    'RuminantEconomicParticipationException'
)) {
    class RuminantEconomicParticipationException
        extends RuntimeException
    {
    }
}

if (!function_exists(
    'ruminant_economic_participation_valid_date'
)) {
function ruminant_economic_participation_valid_date(
    string $date
): bool {
    $parsed =
        DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $date
        );

    return
        $parsed instanceof DateTimeImmutable
        &&
        $parsed->format('Y-m-d') === $date;
}
}

if (!function_exists(
    'ruminant_economic_participation_cycle'
)) {
function ruminant_economic_participation_cycle(
    PDO $pdo,
    int $farmId,
    int $cycleId,
    string $species
): array {
    $species =
        strtolower(
            trim($species)
        );

    if (
        $farmId < 1
        ||
        $cycleId < 1
        ||
        $species === ''
    ) {
        throw new InvalidArgumentException(
            'Ruminant economic-participation scope is invalid.'
        );
    }

    $stmt =
        $pdo->prepare(
            "SELECT
                 id,
                 cycle_code,
                 farm_type,
                 production_type,
                 status,
                 start_date,
                 close_date,
                 opening_headcount
             FROM production_cycles
             WHERE farm_id=?
               AND id=?
               AND farm_type='ruminant'
             LIMIT 1"
        );

    $stmt->execute([
        $farmId,
        $cycleId,
    ]);

    $cycle =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$cycle) {
        throw new RuminantEconomicParticipationException(
            'Ruminant production cycle could not be resolved.'
        );
    }

    if (
        strtolower(
            trim(
                (string)$cycle[
                    'production_type'
                ]
            )
        )
        !==
        $species
    ) {
        throw new RuminantEconomicParticipationException(
            'Ruminant economic-participation species does not match the production cycle.'
        );
    }

    return $cycle;
}
}

if (!function_exists(
    'ruminant_economic_participation_snapshot'
)) {
function ruminant_economic_participation_snapshot(
    string $date,
    int $opening,
    int $closing,
    int $additions,
    int $removals,
    string $source
): array {
    if (
        $opening < 0
        ||
        $closing < 0
        ||
        $additions < 0
        ||
        $removals < 0
    ) {
        throw new RuminantEconomicParticipationException(
            'Ruminant physical-population evidence cannot be negative.'
        );
    }

    /*
     * Animals removed later on the business date still participated
     * during that date. Animals added on the date also participate.
     */
    $exposure =
        $opening
        +
        $additions;

    return [
        'date' =>
            $date,

        'opening_headcount' =>
            $opening,

        'closing_headcount' =>
            $closing,

        'additions' =>
            $additions,

        'removals' =>
            $removals,

        'physical_headcount' =>
            $exposure,

        'source' =>
            $source,
    ];
}
}

if (!function_exists(
    'ruminant_economic_participation_exact_cycle_population'
)) {
function ruminant_economic_participation_exact_cycle_population(
    PDO $pdo,
    int $farmId,
    int $cycleId,
    string $species,
    string $date
): ?array {
    if (
        !ruminant_economic_participation_valid_date(
            $date
        )
    ) {
        throw new InvalidArgumentException(
            'Enter a valid Ruminant economic-participation date.'
        );
    }

    $cycle =
        ruminant_economic_participation_cycle(
            $pdo,
            $farmId,
            $cycleId,
            $species
        );

    $cycleStart =
        (string)$cycle[
            'start_date'
        ];

    $cycleClose =
        trim(
            (string)(
                $cycle[
                    'close_date'
                ]
                ?? ''
            )
        );

    if (
        $date < $cycleStart
        ||
        (
            $cycleClose !== ''
            &&
            $date > $cycleClose
        )
    ) {
        return null;
    }

    /*
     * Canonical V3 population is authoritative where the requested
     * date is covered by its baseline/movement ledger.
     */
    $canonical =
        daily_population_boundary_snapshot(
            $pdo,
            $farmId,
            $cycleId,
            $date
        );

    if ($canonical !== null) {
        return
            ruminant_economic_participation_snapshot(
                $date,
                (int)$canonical[
                    'opening_quantity'
                ],
                (int)$canonical[
                    'closing_quantity'
                ],
                (int)(
                    $canonical[
                        'additions'
                    ]
                    ?? 0
                ),
                (int)(
                    $canonical[
                        'removals'
                    ]
                    ?? 0
                ),
                'canonical_population'
            );
    }

    /*
     * Pre-V3 history may have an exact Daily Record even though the
     * canonical population baseline begins later.
     *
     * Use exact-date evidence only. Never interpolate a missing date.
     */
    $stmt =
        $pdo->prepare(
            "SELECT
                 opening_stock,
                 mortality
             FROM ruminant_daily_records
             WHERE farm_id=?
               AND cycle_id=?
               AND LOWER(animal_type)=?
               AND record_date=?
             ORDER BY id DESC
             LIMIT 1"
        );

    $stmt->execute([
        $farmId,
        $cycleId,
        strtolower(
            trim($species)
        ),
        $date,
    ]);

    $daily =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if ($daily) {
        $opening =
            max(
                0,
                (int)$daily[
                    'opening_stock'
                ]
            );

        $mortality =
            max(
                0,
                (int)$daily[
                    'mortality'
                ]
            );

        return
            ruminant_economic_participation_snapshot(
                $date,
                $opening,
                max(
                    0,
                    $opening
                    -
                    $mortality
                ),
                0,
                $mortality,
                'daily_record_exact'
            );
    }

    /*
     * The cycle opening quantity is exact physical evidence only for
     * the actual cycle-start date. It must never be carried forward
     * across an unobserved historical gap.
     */
    if (
        $date === $cycleStart
        &&
        (int)(
            $cycle[
                'opening_headcount'
            ]
            ?? 0
        ) > 0
    ) {
        $opening =
            (int)$cycle[
                'opening_headcount'
            ];

        return
            ruminant_economic_participation_snapshot(
                $date,
                $opening,
                $opening,
                0,
                0,
                'cycle_opening_headcount'
            );
    }

    return null;
}
}

if (!function_exists(
    'ruminant_economic_participation_first_physical_cohort'
)) {
function ruminant_economic_participation_first_physical_cohort(
    PDO $pdo,
    int $farmId,
    int $cycleId,
    string $species
): ?array {
    $cycle =
        ruminant_economic_participation_cycle(
            $pdo,
            $farmId,
            $cycleId,
            $species
        );

    $candidates = [];

    $opening =
        (int)(
            $cycle[
                'opening_headcount'
            ]
            ?? 0
        );

    if ($opening > 0) {
        $candidates[] = [
            'date' =>
                (string)$cycle[
                    'start_date'
                ],

            'quantity' =>
                $opening,

            'source' =>
                'cycle_opening_headcount',
        ];
    }

    $stmt =
        $pdo->prepare(
            "SELECT
                 record_date,
                 opening_stock
             FROM ruminant_daily_records
             WHERE farm_id=?
               AND cycle_id=?
               AND LOWER(animal_type)=?
               AND opening_stock>0
             ORDER BY
                 record_date ASC,
                 id ASC
             LIMIT 1"
        );

    $stmt->execute([
        $farmId,
        $cycleId,
        strtolower(
            trim($species)
        ),
    ]);

    $daily =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if ($daily) {
        $candidates[] = [
            'date' =>
                (string)$daily[
                    'record_date'
                ],

            'quantity' =>
                (int)$daily[
                    'opening_stock'
                ],

            'source' =>
                'daily_record',
        ];
    }

    $stmt =
        $pdo->prepare(
            "SELECT
                 baseline_date,
                 baseline_quantity
             FROM production_population_baselines
             WHERE farm_id=?
               AND cycle_id=?
             LIMIT 1"
        );

    $stmt->execute([
        $farmId,
        $cycleId,
    ]);

    $baseline =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (
        $baseline
        &&
        (int)$baseline[
            'baseline_quantity'
        ] > 0
    ) {
        $candidates[] = [
            'date' =>
                (string)$baseline[
                    'baseline_date'
                ],

            'quantity' =>
                (int)$baseline[
                    'baseline_quantity'
                ],

            'source' =>
                'population_baseline',
        ];
    }

    /*
     * Handle cycles that legitimately begin at zero and receive their
     * first livestock through a canonical population addition later.
     */
    if ($baseline) {
        $baselineDate =
            (string)$baseline[
                'baseline_date'
            ];

        $running =
            (int)$baseline[
                'baseline_quantity'
            ];

        $stmt =
            $pdo->prepare(
                "SELECT
                     m.movement_date,
                     m.movement_type,
                     m.quantity_delta
                 FROM production_population_movements m
                 WHERE m.farm_id=?
                   AND m.cycle_id=?
                   AND m.movement_date>=?
                   AND m.reversal_of_id IS NULL
                   AND NOT EXISTS (
                       SELECT 1
                       FROM production_population_movements r
                       WHERE r.farm_id=m.farm_id
                         AND r.cycle_id=m.cycle_id
                         AND r.reversal_of_id=m.id
                   )
                 ORDER BY
                     m.movement_date ASC,
                     m.id ASC"
            );

        $stmt->execute([
            $farmId,
            $cycleId,
            $baselineDate,
        ]);

        $byDate = [];

        foreach (
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            )
            as $movement
        ) {
            $date =
                (string)$movement[
                    'movement_date'
                ];

            if (!isset($byDate[$date])) {
                $byDate[$date] = [];
            }

            $byDate[$date][] =
                (int)$movement[
                    'quantity_delta'
                ];
        }

        foreach ($byDate as $date => $deltas) {
            $additions = 0;
            $net = 0;

            foreach ($deltas as $delta) {
                $net += $delta;

                if ($delta > 0) {
                    $additions += $delta;
                }
            }

            $exposure =
                $running
                +
                $additions;

            if ($exposure > 0) {
                $candidates[] = [
                    'date' =>
                        $date,

                    'quantity' =>
                        $exposure,

                    'source' =>
                        'population_movement',
                ];

                break;
            }

            $running += $net;
        }
    }

    if (!$candidates) {
        return null;
    }

    usort(
        $candidates,
        static function (
            array $a,
            array $b
        ): int {
            $dateCompare =
                strcmp(
                    (string)$a['date'],
                    (string)$b['date']
                );

            if ($dateCompare !== 0) {
                return $dateCompare;
            }

            return
                strcmp(
                    (string)$a['source'],
                    (string)$b['source']
                );
        }
    );

    $first =
        $candidates[0];

    $exact =
        ruminant_economic_participation_exact_cycle_population(
            $pdo,
            $farmId,
            $cycleId,
            $species,
            (string)$first[
                'date'
            ]
        );

    if (
        $exact !== null
        &&
        (int)$exact[
            'physical_headcount'
        ] > 0
    ) {
        $exact[
            'cohort_source'
        ] =
            $first[
                'source'
            ];

        return $exact;
    }

    $quantity =
        (int)$first[
            'quantity'
        ];

    return
        ruminant_economic_participation_snapshot(
            (string)$first[
                'date'
            ],
            $quantity,
            $quantity,
            0,
            0,
            (string)$first[
                'source'
            ]
        );
}
}

if (!function_exists(
    'ruminant_economic_participation_species_population'
)) {
function ruminant_economic_participation_species_population(
    PDO $pdo,
    int $farmId,
    string $species,
    string $date
): array {
    if (
        !ruminant_economic_participation_valid_date(
            $date
        )
    ) {
        throw new InvalidArgumentException(
            'Enter a valid Ruminant economic-participation date.'
        );
    }

    $species =
        strtolower(
            trim($species)
        );

    $stmt =
        $pdo->prepare(
            "SELECT id,cycle_code
             FROM production_cycles
             WHERE farm_id=?
               AND farm_type='ruminant'
               AND LOWER(production_type)=?
               AND start_date<=?
               AND (
                   close_date IS NULL
                   OR close_date>=?
               )
             ORDER BY id"
        );

    $stmt->execute([
        $farmId,
        $species,
        $date,
        $date,
    ]);

    $cycles =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    $total = 0;
    $parts = [];
    $missing = [];

    foreach ($cycles as $cycle) {
        $cycleId =
            (int)$cycle['id'];

        $snapshot =
            ruminant_economic_participation_exact_cycle_population(
                $pdo,
                $farmId,
                $cycleId,
                $species,
                $date
            );

        if ($snapshot === null) {
            $missing[] =
                $cycleId;

            continue;
        }

        $headcount =
            (int)$snapshot[
                'physical_headcount'
            ];

        $total +=
            $headcount;

        $parts[] = [
            'cycle_id' =>
                $cycleId,

            'cycle_code' =>
                (string)$cycle[
                    'cycle_code'
                ],

            'physical_headcount' =>
                $headcount,

            'source' =>
                (string)$snapshot[
                    'source'
                ],
        ];
    }

    return [
        'date' =>
            $date,

        'physical_headcount' =>
            $total,

        'cycle_parts' =>
            $parts,

        'missing_cycle_ids' =>
            $missing,

        'resolved' =>
            !$missing,

        'source' =>
            'species_cycle_population',
    ];
}
}

if (!function_exists(
    'ruminant_economic_participation_context'
)) {
function ruminant_economic_participation_context(
    PDO $pdo,
    int $farmId,
    string $species,
    string $sourceDate,
    ?int $cycleId = null,
    bool $preCyclePreparation = false
): array {
    if (
        !ruminant_economic_participation_valid_date(
            $sourceDate
        )
    ) {
        throw new InvalidArgumentException(
            'Shared-cost source date is invalid.'
        );
    }

    $species =
        strtolower(
            trim($species)
        );

    $cycleId =
        (int)($cycleId ?? 0);

    $effectiveDate =
        $sourceDate;

    $population = null;

    if ($preCyclePreparation) {
        if ($cycleId < 1) {
            throw new RuminantEconomicParticipationException(
                'Pre-cycle preparation requires an exact production-cycle target.'
            );
        }

        $population =
            ruminant_economic_participation_first_physical_cohort(
                $pdo,
                $farmId,
                $cycleId,
                $species
            );

        if ($population !== null) {
            $effectiveDate =
                (string)$population[
                    'date'
                ];
        }
    } elseif ($cycleId > 0) {
        $population =
            ruminant_economic_participation_exact_cycle_population(
                $pdo,
                $farmId,
                $cycleId,
                $species,
                $sourceDate
            );
    } else {
        $speciesPopulation =
            ruminant_economic_participation_species_population(
                $pdo,
                $farmId,
                $species,
                $sourceDate
            );

        if (
            !empty(
                $speciesPopulation[
                    'resolved'
                ]
            )
        ) {
            $population =
                $speciesPopulation;
        }
    }

    if ($population === null) {
        return [
            'status' =>
                'review',

            'review_reason' =>
                'physical_population_evidence_missing',

            'effective_date' =>
                $effectiveDate,

            'physical_headcount' =>
                null,

            'registered_animal_ids' =>
                [],

            'registered_participant_count' =>
                0,

            'aggregate_unregistered_headcount' =>
                null,

            'population_source' =>
                null,

            'pre_cycle_preparation' =>
                $preCyclePreparation,
        ];
    }

    $physicalHeadcount =
        (int)(
            $population[
                'physical_headcount'
            ]
            ?? 0
        );

    if ($physicalHeadcount <= 0) {
        return [
            'status' =>
                'review',

            'review_reason' =>
                'no_physical_population',

            'effective_date' =>
                $effectiveDate,

            'physical_headcount' =>
                $physicalHeadcount,

            'registered_animal_ids' =>
                [],

            'registered_participant_count' =>
                0,

            'aggregate_unregistered_headcount' =>
                0,

            'population_source' =>
                $population[
                    'source'
                ]
                ?? null,

            'pre_cycle_preparation' =>
                $preCyclePreparation,
        ];
    }

    $registered =
        ruminant_cycle_eligible_animal_ids(
            $pdo,
            $farmId,
            $species,
            $effectiveDate,
            $cycleId > 0
                ? $cycleId
                : null
        );

    $registered =
        array_values(
            array_unique(
                array_map(
                    'intval',
                    $registered
                )
            )
        );

    sort(
        $registered,
        SORT_NUMERIC
    );

    $registeredCount =
        count($registered);

    if (
        $registeredCount
        >
        $physicalHeadcount
    ) {
        return [
            'status' =>
                'review',

            'review_reason' =>
                'registered_population_exceeds_physical',

            'effective_date' =>
                $effectiveDate,

            'physical_headcount' =>
                $physicalHeadcount,

            'registered_animal_ids' =>
                $registered,

            'registered_participant_count' =>
                $registeredCount,

            'aggregate_unregistered_headcount' =>
                0,

            'population_source' =>
                $population[
                    'source'
                ]
                ?? null,

            'pre_cycle_preparation' =>
                $preCyclePreparation,
        ];
    }

    return [
        'status' =>
            'resolved',

        'review_reason' =>
            null,

        'effective_date' =>
            $effectiveDate,

        'physical_headcount' =>
            $physicalHeadcount,

        'registered_animal_ids' =>
            $registered,

        'registered_participant_count' =>
            $registeredCount,

        'aggregate_unregistered_headcount' =>
            $physicalHeadcount
            -
            $registeredCount,

        'population_source' =>
            $population[
                'source'
            ]
            ?? null,

        'opening_headcount' =>
            $population[
                'opening_headcount'
            ]
            ?? null,

        'closing_headcount' =>
            $population[
                'closing_headcount'
            ]
            ?? null,

        'additions' =>
            $population[
                'additions'
            ]
            ?? null,

        'removals' =>
            $population[
                'removals'
            ]
            ?? null,

        'pre_cycle_preparation' =>
            $preCyclePreparation,
    ];
}
}

if (!function_exists(
    'ruminant_economic_participation_share_for_animal'
)) {
function ruminant_economic_participation_share_for_animal(
    float $poolAmount,
    int $physicalHeadcount,
    array $registeredAnimalIds,
    int $targetAnimalId
): float {
    $registeredAnimalIds =
        array_values(
            array_unique(
                array_map(
                    'intval',
                    $registeredAnimalIds
                )
            )
        );

    sort(
        $registeredAnimalIds,
        SORT_NUMERIC
    );

    if (
        $physicalHeadcount <= 0
        ||
        $targetAnimalId <= 0
        ||
        count($registeredAnimalIds)
            >
        $physicalHeadcount
    ) {
        return 0.0;
    }

    $index =
        array_search(
            $targetAnimalId,
            $registeredAnimalIds,
            true
        );

    if ($index === false) {
        return 0.0;
    }

    $totalCents =
        (int)round(
            round(
                $poolAmount,
                2
            )
            *
            100
        );

    if ($totalCents <= 0) {
        return 0.0;
    }

    $base =
        intdiv(
            $totalCents,
            $physicalHeadcount
        );

    /*
     * When the entire physical herd is individually identified,
     * distribute remainder cents deterministically so named shares
     * conserve the complete pool.
     *
     * While some livestock remain aggregate/unregistered, remainder
     * cents stay with that aggregate herd rather than being assigned
     * arbitrarily to one of the known animals.
     */
    if (
        count($registeredAnimalIds)
        ===
        $physicalHeadcount
    ) {
        $remainder =
            $totalCents
            -
            (
                $base
                *
                $physicalHeadcount
            );

        if ($index < $remainder) {
            $base++;
        }
    }

    return
        $base
        /
        100;
}
}

if (!function_exists(
    'ruminant_economic_participation_named_total'
)) {
function ruminant_economic_participation_named_total(
    float $poolAmount,
    int $physicalHeadcount,
    array $registeredAnimalIds
): float {
    if ($physicalHeadcount <= 0) {
        return 0.0;
    }

    $registeredAnimalIds =
        array_values(
            array_unique(
                array_map(
                    'intval',
                    $registeredAnimalIds
                )
            )
        );

    sort(
        $registeredAnimalIds,
        SORT_NUMERIC
    );

    $registeredCount =
        count(
            $registeredAnimalIds
        );

    if (
        $registeredCount
        >
        $physicalHeadcount
    ) {
        return 0.0;
    }

    $totalCents =
        (int)round(
            round(
                $poolAmount,
                2
            )
            *
            100
        );

    if (
        $registeredCount
        ===
        $physicalHeadcount
    ) {
        return
            $totalCents
            /
            100;
    }

    $base =
        intdiv(
            $totalCents,
            $physicalHeadcount
        );

    return
        (
            $base
            *
            $registeredCount
        )
        /
        100;
}
}
