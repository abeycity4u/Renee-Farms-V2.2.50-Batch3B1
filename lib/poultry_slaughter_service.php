<?php

require_once __DIR__ . '/production_population_projection.php';
require_once __DIR__ . '/poultry_cycle_acquisition.php';
require_once __DIR__ . '/expense_revision_service.php';
require_once __DIR__ . '/slaughter_output_inventory.php';
require_once dirname(__DIR__) . '/includes/financial.php';
require_once dirname(__DIR__) . '/includes/audit_helpers.php';

/*
 * Renee Farms V3.0.1 — Poultry slaughter batch core.
 *
 * Authority boundaries:
 *
 * - production_population.php remains the only live-population authority.
 * - poultry_cycle_acquisitions remains the flock capital-entry authority.
 * - getProfitabilitySummary() remains the operating-cost authority.
 * - this service freezes, but does not re-post, those economics.
 * - Inventory output and Sales consumption are intentionally NOT owned here.
 *
 * A poultry slaughter batch is a durable physical source projected into the
 * canonical append-only population ledger with:
 *
 *     source_type   = poultry_slaughter
 *     movement_type = slaughter
 *
 * Repeated request tokens are idempotent and must never deduct population
 * twice.
 */

if (!class_exists('PoultrySlaughterException')) {
    class PoultrySlaughterException extends RuntimeException {}
}

if (!function_exists('poultry_slaughter_load_expense_authority')) {
function poultry_slaughter_load_expense_authority(): void
{
    require_once __DIR__ . '/poultry_expense_entry.php';
}
}


if (!function_exists('poultry_slaughter_money_cents')) {
function poultry_slaughter_money_cents(
    $value
): int {
    if (!is_numeric($value)) {
        throw new InvalidArgumentException(
            'Poultry slaughter costing received an invalid monetary amount.'
        );
    }

    $cents =
        (int)round(
            ((float)$value) * 100,
            0,
            PHP_ROUND_HALF_UP
        );

    if ($cents < 0) {
        throw new InvalidArgumentException(
            'Poultry slaughter costing cannot use a negative monetary amount.'
        );
    }

    return $cents;
}
}


if (!function_exists('poultry_slaughter_money_from_cents')) {
function poultry_slaughter_money_from_cents(
    int $cents
): float {
    if ($cents < 0) {
        throw new InvalidArgumentException(
            'Poultry slaughter costing cannot use negative cents.'
        );
    }

    return
        round(
            $cents / 100,
            2
        );
}
}


/*
 * Allocate one remaining economic pool to the current slaughter event.
 *
 * The final live flock receives every remaining cent, avoiding cumulative
 * rounding residue across repeated slaughter batches.
 */
if (!function_exists('poultry_slaughter_allocate_pool_cents')) {
function poultry_slaughter_allocate_pool_cents(
    int $remainingCents,
    int $birdCount,
    int $populationBefore
): int {
    if ($remainingCents < 0) {
        throw new InvalidArgumentException(
            'Remaining poultry slaughter cost pool cannot be negative.'
        );
    }

    if (
        $birdCount <= 0
        ||
        $populationBefore <= 0
    ) {
        throw new InvalidArgumentException(
            'Poultry slaughter allocation requires a positive flock and slaughter quantity.'
        );
    }

    if ($birdCount > $populationBefore) {
        throw new InvalidArgumentException(
            'Poultry slaughter quantity cannot exceed the available live population.'
        );
    }

    if ($birdCount === $populationBefore) {
        return $remainingCents;
    }

    return
        (int)round(
            $remainingCents
            *
            (
                $birdCount
                /
                $populationBefore
            ),
            0,
            PHP_ROUND_HALF_UP
        );
}
}


if (!function_exists('poultry_slaughter_request_token')) {
function poultry_slaughter_request_token(
    string $value
): string {
    $token =
        strtolower(
            trim($value)
        );

    if (
        preg_match(
            '/^[a-f0-9]{32,64}$/',
            $token
        ) !== 1
    ) {
        throw new InvalidArgumentException(
            'Invalid poultry slaughter submission token. Refresh the page and try again.'
        );
    }

    return $token;
}
}


if (!function_exists('poultry_slaughter_batch_code')) {
function poultry_slaughter_batch_code(
    string $slaughterDate,
    string $requestToken
): string {
    return
        'PS-'
        .
        str_replace(
            '-',
            '',
            $slaughterDate
        )
        .
        '-'
        .
        strtoupper(
            substr(
                $requestToken,
                0,
                10
            )
        );
}
}


if (!function_exists('poultry_slaughter_notes')) {
function poultry_slaughter_notes(
    ?string $value
): ?string {
    $notes =
        trim(
            (string)$value
        );

    if ($notes === '') {
        return null;
    }

    if (
        production_population_text_length(
            $notes
        ) > 255
    ) {
        throw new InvalidArgumentException(
            'Poultry slaughter notes must be 255 characters or fewer.'
        );
    }

    return $notes;
}
}


if (!function_exists('poultry_slaughter_live_weight_kg')) {
function poultry_slaughter_live_weight_kg(
    ?float $value
): ?float {
    if ($value === null) {
        return null;
    }

    if (
        !is_finite($value)
        ||
        $value <= 0
    ) {
        throw new InvalidArgumentException(
            'Total live weight must be greater than zero when provided.'
        );
    }

    return
        round(
            $value,
            4
        );
}
}


/*
 * Capital authority:
 *
 * Active poultry_cycle_acquisitions recorded on/before the slaughter date.
 * An uncosted active acquisition makes the slaughter valuation incomplete;
 * this service therefore fails closed instead of inventing capital value.
 */
if (!function_exists('poultry_slaughter_acquisition_basis_as_of')) {
function poultry_slaughter_acquisition_basis_as_of(
    PDO $pdo,
    int $farmId,
    int $cycleId,
    string $asOfDate
): array {
    $rows =
        poultry_acquisition_history(
            $pdo,
            $farmId,
            $cycleId
        );

    $eligible = [];
    $totalCents = 0;

    foreach ($rows as $row) {
        if (!empty($row['voided_at'])) {
            continue;
        }

        if (
            (string)$row['acquisition_date']
            >
            $asOfDate
        ) {
            continue;
        }

        if (
            $row['total_cost'] === null
            ||
            $row['total_cost'] === ''
        ) {
            throw new PoultrySlaughterException(
                'Poultry slaughter costing is incomplete because an active flock acquisition has no defensible cost basis.'
            );
        }

        $costCents =
            poultry_slaughter_money_cents(
                $row['total_cost']
            );

        $totalCents +=
            $costCents;

        $eligible[] = [
            'id' =>
                (int)$row['id'],

            'acquisition_type' =>
                (string)$row['acquisition_type'],

            'acquisition_date' =>
                (string)$row['acquisition_date'],

            'quantity' =>
                (int)$row['quantity'],

            'total_cost' =>
                poultry_slaughter_money_from_cents(
                    $costCents
                ),
        ];
    }

    if (!$eligible) {
        throw new PoultrySlaughterException(
            'Poultry slaughter costing requires at least one active, costed flock acquisition for this cycle.'
        );
    }

    return [
        'total_cents' =>
            $totalCents,

        'total' =>
            poultry_slaughter_money_from_cents(
                $totalCents
            ),

        'rows' =>
            $eligible,
    ];
}
}


/*
 * Lock every prior effective poultry slaughter batch for this cycle.
 *
 * Prior processing operating cost is included in operating-transferred value
 * because it is already:
 *
 * 1. recognized by canonical farm_expenses / Profitability; and
 * 2. embedded in that earlier processed-output valuation.
 *
 * It therefore must never re-enter a later batch's available operating pool.
 */
if (!function_exists('poultry_slaughter_prior_batches_locked')) {
function poultry_slaughter_prior_batches_locked(
    PDO $pdo,
    int $farmId,
    int $cycleId
): array {
    $stmt =
        $pdo->prepare(
            "SELECT
                 id,
                 batch_code,
                 slaughter_date,
                 bird_count,
                 capital_basis_transferred,
                 embedded_operating_basis_transferred,
                 processing_operating_cost,
                 full_cost_basis_amount,
                 cost_basis_finalized_at
             FROM poultry_slaughter_batches
             WHERE farm_id = ?
               AND cycle_id = ?
               AND status <> 'reversed'
             ORDER BY
                 slaughter_date ASC,
                 id ASC
             FOR UPDATE"
        );

    $stmt->execute([
        $farmId,
        $cycleId,
    ]);

    $rows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];

    $capitalCents = 0;
    $operatingCents = 0;
    $lastDate = null;
    $provenance = [];

    foreach ($rows as $row) {
        if (
            empty(
                $row['cost_basis_finalized_at']
            )
        ) {
            throw new PoultrySlaughterException(
                'Finalize the processing cost basis of the earlier poultry slaughter batch before recording another slaughter batch.'
            );
        }

        $capital =
            poultry_slaughter_money_cents(
                $row[
                    'capital_basis_transferred'
                ]
            );

        $embeddedOperating =
            poultry_slaughter_money_cents(
                $row[
                    'embedded_operating_basis_transferred'
                ]
            );

        $processingOperating =
            poultry_slaughter_money_cents(
                $row[
                    'processing_operating_cost'
                ]
            );

        $capitalCents +=
            $capital;

        $operatingCents +=
            $embeddedOperating
            +
            $processingOperating;

        $lastDate =
            (string)$row[
                'slaughter_date'
            ];

        $provenance[] = [
            'id' =>
                (int)$row['id'],

            'batch_code' =>
                (string)$row['batch_code'],

            'slaughter_date' =>
                (string)$row['slaughter_date'],

            'bird_count' =>
                (int)$row['bird_count'],

            'capital_basis_transferred' =>
                poultry_slaughter_money_from_cents(
                    $capital
                ),

            'embedded_operating_basis_transferred' =>
                poultry_slaughter_money_from_cents(
                    $embeddedOperating
                ),

            'processing_operating_cost' =>
                poultry_slaughter_money_from_cents(
                    $processingOperating
                ),

            'full_cost_basis_amount' =>
                round(
                    (float)$row[
                        'full_cost_basis_amount'
                    ],
                    2
                ),

            'cost_basis_finalized_at' =>
                (string)$row[
                    'cost_basis_finalized_at'
                ],
        ];
    }

    return [
        'rows' =>
            $provenance,

        'capital_transferred_cents' =>
            $capitalCents,

        'operating_transferred_cents' =>
            $operatingCents,

        'last_slaughter_date' =>
            $lastDate,
    ];
}
}


/*
 * Frozen economic basis for one new slaughter batch.
 *
 * Capital:
 *   canonical poultry acquisition basis
 *   less capital already transferred to earlier effective slaughter batches
 *
 * Operating:
 *   canonical getProfitabilitySummary()->total_operating_cost
 *   less operating value already embedded in earlier slaughter batches
 *
 * Revenue and slaughter-output COGS never enter this calculation.
 */
if (!function_exists('poultry_slaughter_cost_basis_locked')) {
function poultry_slaughter_cost_basis_locked(
    PDO $pdo,
    array $cycle,
    string $slaughterDate,
    int $birdCount,
    int $populationBefore
): array {
    $farmId =
        (int)$cycle['farm_id'];

    $cycleId =
        (int)$cycle['id'];

    $productionType =
        strtolower(
            trim(
                (string)$cycle[
                    'production_type'
                ]
            )
        );

    $acquisition =
        poultry_slaughter_acquisition_basis_as_of(
            $pdo,
            $farmId,
            $cycleId,
            $slaughterDate
        );

    $profitability =
        getProfitabilitySummary(
            $pdo,
            $farmId,
            (string)$cycle['start_date'],
            $slaughterDate,
            'poultry',
            $cycleId,
            $productionType
        );

    $cumulativeOperatingCents =
        poultry_slaughter_money_cents(
            $profitability[
                'total_operating_cost'
            ]
            ?? 0
        );

    $cumulativeCapitalCents =
        (int)$acquisition[
            'total_cents'
        ];

    $prior =
        poultry_slaughter_prior_batches_locked(
            $pdo,
            $farmId,
            $cycleId
        );

    /*
     * Backdated insertion would change the cost pool from which an already
     * frozen later batch was valued. Corrections must use the dedicated
     * correction/reversal workflow instead of silently rewriting history.
     */
    if (
        $prior[
            'last_slaughter_date'
        ] !== null
        &&
        $slaughterDate
        <
        (string)$prior[
            'last_slaughter_date'
        ]
    ) {
        throw new PoultrySlaughterException(
            'A new poultry slaughter batch cannot be backdated before an existing effective slaughter batch. Correct the later batch first.'
        );
    }

    $priorCapitalCents =
        (int)$prior[
            'capital_transferred_cents'
        ];

    $priorOperatingCents =
        (int)$prior[
            'operating_transferred_cents'
        ];

    if (
        $priorCapitalCents
        >
        $cumulativeCapitalCents
    ) {
        throw new PoultrySlaughterException(
            'Poultry slaughter capital-pool history exceeds the canonical acquisition basis.'
        );
    }

    if (
        $priorOperatingCents
        >
        $cumulativeOperatingCents
    ) {
        throw new PoultrySlaughterException(
            'Poultry slaughter operating-pool history exceeds canonical recognized operating cost.'
        );
    }

    $remainingCapitalCents =
        $cumulativeCapitalCents
        -
        $priorCapitalCents;

    $remainingOperatingCents =
        $cumulativeOperatingCents
        -
        $priorOperatingCents;

    $capitalTransferCents =
        poultry_slaughter_allocate_pool_cents(
            $remainingCapitalCents,
            $birdCount,
            $populationBefore
        );

    $operatingTransferCents =
        poultry_slaughter_allocate_pool_cents(
            $remainingOperatingCents,
            $birdCount,
            $populationBefore
        );

    /*
     * Processing expense remains zero in Stage 14E-2.
     * A later stage links canonical farm-expense revisions and expands both
     * this frozen total and its provenance without introducing a second P&L
     * expense authority.
     */
    $fullCostCents =
        $capitalTransferCents
        +
        $operatingTransferCents;

    $manifest = [
        'contract' =>
            'poultry_slaughter_cost_basis_v1',

        'farm_id' =>
            $farmId,

        'cycle_id' =>
            $cycleId,

        'production_type' =>
            $productionType,

        'cycle_start_date' =>
            (string)$cycle[
                'start_date'
            ],

        'slaughter_date' =>
            $slaughterDate,

        'bird_count' =>
            $birdCount,

        'population_before' =>
            $populationBefore,

        'cumulative_capital_basis' =>
            poultry_slaughter_money_from_cents(
                $cumulativeCapitalCents
            ),

        'cumulative_operating_cost' =>
            poultry_slaughter_money_from_cents(
                $cumulativeOperatingCents
            ),

        'prior_capital_transferred' =>
            poultry_slaughter_money_from_cents(
                $priorCapitalCents
            ),

        'prior_operating_transferred' =>
            poultry_slaughter_money_from_cents(
                $priorOperatingCents
            ),

        'remaining_capital_pool_before' =>
            poultry_slaughter_money_from_cents(
                $remainingCapitalCents
            ),

        'remaining_operating_pool_before' =>
            poultry_slaughter_money_from_cents(
                $remainingOperatingCents
            ),

        'capital_basis_transferred' =>
            poultry_slaughter_money_from_cents(
                $capitalTransferCents
            ),

        'embedded_operating_basis_transferred' =>
            poultry_slaughter_money_from_cents(
                $operatingTransferCents
            ),

        'processing_operating_cost' =>
            0.0,

        'full_cost_basis_amount' =>
            poultry_slaughter_money_from_cents(
                $fullCostCents
            ),

        'acquisition_sources' =>
            $acquisition[
                'rows'
            ],

        'prior_effective_slaughter_batches' =>
            $prior[
                'rows'
            ],

        'operating_cost_breakdown' => [
            'feed_consumption_cost' =>
                round(
                    (float)(
                        $profitability[
                            'feed_consumption_cost'
                        ]
                        ?? 0
                    ),
                    2
                ),

            'manual_non_feed_expenses' =>
                round(
                    (float)(
                        $profitability[
                            'manual_non_feed_expenses'
                        ]
                        ?? 0
                    ),
                    2
                ),

            'inventory_operating_consumption_cost' =>
                round(
                    (float)(
                        $profitability[
                            'inventory_operating_consumption_cost'
                        ]
                        ?? 0
                    ),
                    2
                ),

            'total_operating_cost' =>
                round(
                    (float)(
                        $profitability[
                            'total_operating_cost'
                        ]
                        ?? 0
                    ),
                    2
                ),
        ],
    ];

    $json =
        json_encode(
            $manifest,
            JSON_UNESCAPED_SLASHES
            |
            JSON_PRESERVE_ZERO_FRACTION
        );

    if ($json === false) {
        throw new PoultrySlaughterException(
            'Poultry slaughter cost provenance could not be serialized.'
        );
    }

    return [
        'capital_pool_available_before' =>
            poultry_slaughter_money_from_cents(
                $remainingCapitalCents
            ),

        'operating_pool_available_before' =>
            poultry_slaughter_money_from_cents(
                $remainingOperatingCents
            ),

        'capital_basis_transferred' =>
            poultry_slaughter_money_from_cents(
                $capitalTransferCents
            ),

        'embedded_operating_basis_transferred' =>
            poultry_slaughter_money_from_cents(
                $operatingTransferCents
            ),

        'processing_operating_cost' =>
            0.0,

        'full_cost_basis_amount' =>
            poultry_slaughter_money_from_cents(
                $fullCostCents
            ),

        'cost_basis_method' =>
            'canonical_cycle_cost_pool_proportional_v1',

        'cost_basis_provenance_json' =>
            $json,

        'cost_basis_provenance_fingerprint' =>
            hash(
                'sha256',
                $json
            ),

        'manifest' =>
            $manifest,
    ];
}
}


if (!function_exists('poultry_slaughter_existing_batch_locked')) {
function poultry_slaughter_existing_batch_locked(
    PDO $pdo,
    int $farmId,
    string $requestToken
): ?array {
    $stmt =
        $pdo->prepare(
            'SELECT *
             FROM poultry_slaughter_batches
             WHERE farm_id = ?
               AND request_token = ?
             LIMIT 1
             FOR UPDATE'
        );

    $stmt->execute([
        $farmId,
        $requestToken,
    ]);

    $row =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    return
        $row
            ?: null;
}
}


if (!function_exists('poultry_slaughter_assert_same_submission')) {
function poultry_slaughter_assert_same_submission(
    array $existing,
    int $cycleId,
    string $slaughterDate,
    int $birdCount,
    ?float $liveWeightTotalKg,
    ?string $notes
): void {
    if (
        (string)$existing['status']
        === 'reversed'
    ) {
        throw new PoultrySlaughterException(
            'This poultry slaughter submission token belongs to a reversed batch. Refresh and submit a new request.'
        );
    }

    $existingWeight =
        $existing[
            'live_weight_total_kg'
        ] === null
            ? null
            : round(
                (float)$existing[
                    'live_weight_total_kg'
                ],
                4
            );

    $sameWeight =
        (
            $existingWeight === null
            &&
            $liveWeightTotalKg === null
        )
        ||
        (
            $existingWeight !== null
            &&
            $liveWeightTotalKg !== null
            &&
            abs(
                $existingWeight
                -
                $liveWeightTotalKg
            ) < 0.00005
        );

    $same =
        (int)$existing['cycle_id']
            === $cycleId
        &&
        (string)$existing[
            'slaughter_date'
        ] === $slaughterDate
        &&
        (int)$existing[
            'bird_count'
        ] === $birdCount
        &&
        $sameWeight
        &&
        trim(
            (string)(
                $existing[
                    'notes'
                ]
                ?? ''
            )
        )
            ===
        trim(
            (string)$notes
        );

    if (!$same) {
        throw new PoultrySlaughterException(
            'This poultry slaughter submission token has already been used for different batch details. Refresh and submit again.'
        );
    }
}
}


/*
 * Create one durable poultry slaughter batch and project exactly one canonical
 * live-population deduction.
 *
 * Inventory output, processing expense attachment and financial Sales remain
 * deliberately outside this Stage 14E-2 boundary.
 */
if (!function_exists('poultry_slaughter_batch_create')) {
function poultry_slaughter_batch_create(
    PDO $pdo,
    int $farmId,
    int $cycleId,
    string $slaughterDate,
    int $birdCount,
    ?float $liveWeightTotalKg,
    ?string $notes,
    int $actorUserId,
    string $requestToken
): array {
    if (
        $farmId <= 0
        ||
        $cycleId <= 0
        ||
        $actorUserId <= 0
    ) {
        throw new InvalidArgumentException(
            'Poultry slaughter requires a valid farm, cycle and user.'
        );
    }

    if (
        !production_population_valid_date(
            $slaughterDate
        )
    ) {
        throw new InvalidArgumentException(
            'Enter a valid poultry slaughter date.'
        );
    }

    if ($birdCount <= 0) {
        throw new InvalidArgumentException(
            'Poultry slaughter bird count must be greater than zero.'
        );
    }

    $requestToken =
        poultry_slaughter_request_token(
            $requestToken
        );

    $liveWeightTotalKg =
        poultry_slaughter_live_weight_kg(
            $liveWeightTotalKg
        );

    $notes =
        poultry_slaughter_notes(
            $notes
        );

    $startedTransaction =
        !$pdo->inTransaction();

    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        /*
         * Canonical lock order begins with production_cycles.
         * Existing population/acquisition writers use the same authority.
         */
        $cycle =
            production_population_lock_cycle(
                $pdo,
                $farmId,
                $cycleId
            );

        if (
            strtolower(
                (string)$cycle[
                    'farm_type'
                ]
            ) !== 'poultry'
        ) {
            throw new PoultrySlaughterException(
                'Poultry slaughter is available only for a Poultry production cycle.'
            );
        }

        $productionType =
            strtolower(
                trim(
                    (string)$cycle[
                        'production_type'
                    ]
                )
            );

        if (
            !in_array(
                $productionType,
                [
                    'layer',
                    'broiler',
                ],
                true
            )
        ) {
            throw new PoultrySlaughterException(
                'Poultry slaughter is available only for Layer and Broiler cycles.'
            );
        }

        if (
            (string)$cycle['status']
            !== 'active'
        ) {
            throw new PoultrySlaughterException(
                'Poultry slaughter can only be recorded against an active production cycle.'
            );
        }

        production_population_assert_date_in_cycle(
            $cycle,
            $slaughterDate,
            'Poultry slaughter date'
        );

        /*
         * Durable request-token idempotency.
         */
        $existing =
            poultry_slaughter_existing_batch_locked(
                $pdo,
                $farmId,
                $requestToken
            );

        if ($existing !== null) {
            poultry_slaughter_assert_same_submission(
                $existing,
                $cycleId,
                $slaughterDate,
                $birdCount,
                $liveWeightTotalKg,
                $notes
            );

            /*
             * Re-syncing the same source is safe:
             * projection_sync() returns unchanged rather than inserting a
             * second movement.
             */
            $projection =
                production_population_projection_sync(
                    $pdo,
                    $farmId,
                    $cycleId,
                    'poultry_slaughter',
                    (int)$existing['id'],
                    [
                        'movement_type' =>
                            'slaughter',

                        'movement_date' =>
                            $slaughterDate,

                        'quantity' =>
                            $birdCount,

                        'notes' =>
                            'Poultry slaughter batch '
                            .
                            (string)$existing[
                                'batch_code'
                            ],
                    ],
                    $actorUserId
                );

            if ($startedTransaction) {
                $pdo->commit();
            }

            return [
                'batch_id' =>
                    (int)$existing['id'],

                'batch_code' =>
                    (string)$existing[
                        'batch_code'
                    ],

                'idempotent' =>
                    true,

                'population_projection' =>
                    $projection,
            ];
        }

        $baseline =
            production_population_lock_baseline(
                $pdo,
                $farmId,
                $cycleId
            );

        if ($baseline === null) {
            throw new PoultrySlaughterException(
                'This cycle must have a canonical V3 population baseline before slaughter can be recorded.'
            );
        }

        $populationBefore =
            production_population_balance_locked(
                $pdo,
                $baseline,
                $slaughterDate
            );

        if ($populationBefore <= 0) {
            throw new PoultrySlaughterException(
                'There is no live population available to slaughter on this date.'
            );
        }

        if ($birdCount > $populationBefore) {
            throw new PoultrySlaughterException(
                'Poultry slaughter bird count cannot exceed the canonical live population available on this date.'
            );
        }

        /*
         * Prove this deduction is safe not only on slaughter date but also
         * against every later population movement already recorded.
         */
        $populationAfter =
            production_population_project_delta_locked(
                $pdo,
                $baseline,
                $slaughterDate,
                -$birdCount
            );

        $basis =
            poultry_slaughter_cost_basis_locked(
                $pdo,
                $cycle,
                $slaughterDate,
                $birdCount,
                $populationBefore
            );

        $batchCode =
            poultry_slaughter_batch_code(
                $slaughterDate,
                $requestToken
            );

        $stmt =
            $pdo->prepare(
                "INSERT INTO poultry_slaughter_batches
                 (
                     farm_id,
                     cycle_id,
                     batch_code,
                     request_token,
                     slaughter_date,
                     bird_count,
                     live_weight_total_kg,
                     population_before,
                     capital_pool_available_before,
                     operating_pool_available_before,
                     capital_basis_transferred,
                     embedded_operating_basis_transferred,
                     processing_operating_cost,
                     full_cost_basis_amount,
                     cost_basis_method,
                     cost_basis_snapshot_at,
                     cost_basis_provenance_fingerprint,
                     cost_basis_provenance_json,
                     status,
                     notes,
                     created_by
                 )
                 VALUES
                 (
                     ?, ?, ?, ?, ?, ?, ?, ?,
                     ?, ?, ?, ?,
                     0,
                     ?, ?,
                     NOW(),
                     ?, ?,
                     'open',
                     ?, ?
                 )"
            );

        $stmt->execute([
            $farmId,
            $cycleId,
            $batchCode,
            $requestToken,
            $slaughterDate,
            $birdCount,
            $liveWeightTotalKg,
            $populationBefore,
            $basis[
                'capital_pool_available_before'
            ],
            $basis[
                'operating_pool_available_before'
            ],
            $basis[
                'capital_basis_transferred'
            ],
            $basis[
                'embedded_operating_basis_transferred'
            ],
            $basis[
                'full_cost_basis_amount'
            ],
            $basis[
                'cost_basis_method'
            ],
            $basis[
                'cost_basis_provenance_fingerprint'
            ],
            $basis[
                'cost_basis_provenance_json'
            ],
            $notes,
            $actorUserId,
        ]);

        $batchId =
            (int)$pdo->lastInsertId();

        $projection =
            production_population_projection_sync(
                $pdo,
                $farmId,
                $cycleId,
                'poultry_slaughter',
                $batchId,
                [
                    'movement_type' =>
                        'slaughter',

                    'movement_date' =>
                        $slaughterDate,

                    'quantity' =>
                        $birdCount,

                    'notes' =>
                        'Poultry slaughter batch '
                        .
                        $batchCode,
                ],
                $actorUserId
            );

        if (function_exists('audit_log_event')) {
            audit_log_event(
                'poultry_slaughter_batch_recorded',
                'poultry_slaughter_batch',
                $batchId,
                [
                    'cycle_id' =>
                        $cycleId,

                    'cycle_code' =>
                        (string)$cycle[
                            'cycle_code'
                        ],

                    'production_type' =>
                        $productionType,

                    'slaughter_date' =>
                        $slaughterDate,

                    'bird_count' =>
                        $birdCount,

                    'population_before' =>
                        $populationBefore,

                    'population_after' =>
                        $populationAfter,

                    'capital_basis_transferred' =>
                        $basis[
                            'capital_basis_transferred'
                        ],

                    'embedded_operating_basis_transferred' =>
                        $basis[
                            'embedded_operating_basis_transferred'
                        ],

                    'processing_operating_cost' =>
                        0.0,

                    'full_cost_basis_amount' =>
                        $basis[
                            'full_cost_basis_amount'
                        ],

                    'cost_basis_provenance_fingerprint' =>
                        $basis[
                            'cost_basis_provenance_fingerprint'
                        ],
                ]
            );
        }

        if ($startedTransaction) {
            $pdo->commit();
        }

        return [
            'batch_id' =>
                $batchId,

            'batch_code' =>
                $batchCode,

            'idempotent' =>
                false,

            'production_type' =>
                $productionType,

            'population_before' =>
                $populationBefore,

            'population_after' =>
                $populationAfter,

            'capital_basis_transferred' =>
                $basis[
                    'capital_basis_transferred'
                ],

            'embedded_operating_basis_transferred' =>
                $basis[
                    'embedded_operating_basis_transferred'
                ],

            'processing_operating_cost' =>
                0.0,

            'full_cost_basis_amount' =>
                $basis[
                    'full_cost_basis_amount'
                ],

            'cost_basis_provenance_fingerprint' =>
                $basis[
                    'cost_basis_provenance_fingerprint'
                ],

            'population_projection' =>
                $projection,
        ];

    } catch (Throwable $e) {
        if (
            $startedTransaction
            &&
            $pdo->inTransaction()
        ) {
            $pdo->rollBack();
        }

        throw $e;
    }
}
}


/*
 * Lock one Poultry slaughter batch together with its canonical cycle identity.
 */
if (!function_exists('poultry_slaughter_batch_locked')) {
function poultry_slaughter_batch_locked(
    PDO $pdo,
    int $farmId,
    int $batchId
): array {
    if (
        $farmId <= 0
        ||
        $batchId <= 0
    ) {
        throw new InvalidArgumentException(
            'Choose a valid poultry slaughter batch.'
        );
    }

    $stmt =
        $pdo->prepare(
            "SELECT
                 b.*,
                 pc.cycle_code,
                 pc.farm_type AS cycle_farm_type,
                 pc.production_type
             FROM poultry_slaughter_batches b
             INNER JOIN production_cycles pc
               ON pc.id=b.cycle_id
              AND pc.farm_id=b.farm_id
             WHERE b.id=?
               AND b.farm_id=?
             LIMIT 1
             FOR UPDATE"
        );

    $stmt->execute([
        $batchId,
        $farmId,
    ]);

    $batch =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$batch) {
        throw new PoultrySlaughterException(
            'The selected Poultry slaughter batch could not be found.'
        );
    }

    if (
        strtolower(
            (string)$batch[
                'cycle_farm_type'
            ]
        )
        !== 'poultry'
        ||
        !in_array(
            strtolower(
                (string)$batch[
                    'production_type'
                ]
            ),
            [
                'layer',
                'broiler',
            ],
            true
        )
    ) {
        throw new PoultrySlaughterException(
            'The selected slaughter batch is not attached to a valid Poultry cycle.'
        );
    }

    return $batch;
}
}


/*
 * Deterministic request payload identity for canonical processing-expense
 * creation. The request token proves retry identity; this fingerprint proves
 * the retry still carries the same economic facts.
 */
if (!function_exists('poultry_slaughter_processing_expense_fingerprint')) {
function poultry_slaughter_processing_expense_fingerprint(
    int $batchId,
    string $category,
    string $amount,
    string $unit,
    string $description
): string {
    $payload = [
        'contract' =>
            'poultry_slaughter_processing_expense_request_v1',

        'batch_id' =>
            $batchId,

        'category' =>
            $category,

        'amount' =>
            $amount,

        'unit' =>
            $unit,

        'description' =>
            trim(
                $description
            ),
    ];

    $json =
        json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES
            |
            JSON_PRESERVE_ZERO_FRACTION
        );

    if ($json === false) {
        throw new PoultrySlaughterException(
            'Processing-expense request identity could not be serialized.'
        );
    }

    return
        hash(
            'sha256',
            $json
        );
}
}


/*
 * Create one canonical Poultry expense and link its exact immutable revision
 * to the still-unfinalized slaughter batch.
 *
 * The processing expense remains ordinary farm_expenses P&L.
 * The batch link only freezes provenance and Inventory valuation.
 */
if (!function_exists('poultry_slaughter_processing_expense_add')) {
function poultry_slaughter_processing_expense_add(
    PDO $pdo,
    int $farmId,
    int $batchId,
    string $category,
    $amount,
    $unit,
    ?string $description,
    int $actorUserId,
    string $requestToken
): array {
    if (
        $farmId <= 0
        ||
        $batchId <= 0
        ||
        $actorUserId <= 0
    ) {
        throw new InvalidArgumentException(
            'Processing expense requires a valid farm, slaughter batch and user.'
        );
    }

    poultry_slaughter_load_expense_authority();

    $requestToken =
        poultry_slaughter_request_token(
            $requestToken
        );

    $category =
        poultry_expense_entry_category(
            $category
        );

    $amount =
        poultry_expense_entry_positive_decimal(
            $amount,
            'Expense amount'
        );

    $unit =
        poultry_expense_entry_positive_decimal(
            $unit,
            'Expense quantity'
        );

    $description =
        trim(
            (string)$description
        );

    $requestFingerprint =
        poultry_slaughter_processing_expense_fingerprint(
            $batchId,
            $category,
            $amount,
            $unit,
            $description
        );

    $startedTransaction =
        !$pdo->inTransaction();

    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $batch =
            poultry_slaughter_batch_locked(
                $pdo,
                $farmId,
                $batchId
            );

        if (
            (string)$batch['status']
            === 'reversed'
        ) {
            throw new PoultrySlaughterException(
                'A reversed Poultry slaughter batch cannot receive processing expenses.'
            );
        }

        /*
         * Global-in-farm request-token replay guard.
         * Check this before finalized-state rejection so a successful request
         * replay remains idempotent even if the batch was finalized afterward.
         */
        $existingStmt =
            $pdo->prepare(
                "SELECT *
                 FROM poultry_slaughter_batch_expenses
                 WHERE farm_id=?
                   AND request_token=?
                 LIMIT 1
                 FOR UPDATE"
            );

        $existingStmt->execute([
            $farmId,
            $requestToken,
        ]);

        $existing =
            $existingStmt->fetch(
                PDO::FETCH_ASSOC
            );

        if ($existing) {
            if (
                (int)$existing['batch_id']
                    !== $batchId
                ||
                !hash_equals(
                    (string)$existing[
                        'request_fingerprint'
                    ],
                    $requestFingerprint
                )
            ) {
                throw new PoultrySlaughterException(
                    'This processing-expense submission token has already been used for different details. Refresh and submit again.'
                );
            }

            if ($startedTransaction) {
                $pdo->commit();
            }

            return [
                'link_id' =>
                    (int)$existing['id'],

                'expense_id' =>
                    (int)$existing[
                        'expense_id'
                    ],

                'expense_revision_id' =>
                    (int)$existing[
                        'expense_revision_id'
                    ],

                'amount_snapshot' =>
                    round(
                        (float)$existing[
                            'amount_snapshot'
                        ],
                        2
                    ),

                'idempotent' =>
                    true,
            ];
        }

        if (
            !empty(
                $batch[
                    'cost_basis_finalized_at'
                ]
            )
        ) {
            throw new PoultrySlaughterException(
                'Processing expenses cannot be added after this slaughter batch cost basis has been finalized.'
            );
        }

        /*
         * No output lot may exist before processing-cost finalization.
         * This is defensive now and remains important once output entry exists.
         */
        $outputStmt =
            $pdo->prepare(
                "SELECT COUNT(*)
                 FROM poultry_slaughter_outputs
                 WHERE farm_id=?
                   AND batch_id=?"
            );

        $outputStmt->execute([
            $farmId,
            $batchId,
        ]);

        if (
            (int)$outputStmt->fetchColumn()
            > 0
        ) {
            throw new PoultrySlaughterException(
                'Processing expenses cannot change after slaughter output Inventory has been created.'
            );
        }

        $productionType =
            strtolower(
                (string)$batch[
                    'production_type'
                ]
            );

        $expenseDescription =
            'Slaughter processing '
            .
            (string)$batch[
                'batch_code'
            ];

        if ($description !== '') {
            $expenseDescription .=
                ' · '
                .
                $description;
        }

        $created =
            poultry_expense_entry_create(
                $pdo,
                $farmId,
                $actorUserId,
                [
                    'production_type' =>
                        $productionType,

                    /*
                     * Processing service date is the physical slaughter date.
                     * Payment timing remains outside this economic recognition
                     * boundary.
                     */
                    'expense_date' =>
                        (string)$batch[
                            'slaughter_date'
                        ],

                    'cycle_id' =>
                        (int)$batch[
                            'cycle_id'
                        ],

                    'category' =>
                        $category,

                    'amount' =>
                        $amount,

                    'unit' =>
                        $unit,

                    'description' =>
                        $expenseDescription,
                ]
            );

        $expenseId =
            (int)$created[
                'expense_id'
            ];

        $expense =
            expense_revision_service_expense(
                $pdo,
                $farmId,
                $expenseId,
                true
            );

        $revision =
            expense_revision_service_latest(
                $pdo,
                $farmId,
                $expenseId,
                true
            );

        if (
            $revision === null
            ||
            (int)$revision[
                'revision_no'
            ] < 1
            ||
            (int)$expense[
                'expense_revision_no'
            ]
                !==
                (int)$revision[
                    'revision_no'
                ]
            ||
            !hash_equals(
                (string)$expense[
                    'expense_causal_fingerprint'
                ],
                (string)$revision[
                    'causal_fingerprint'
                ]
            )
        ) {
            throw new PoultrySlaughterException(
                'Canonical processing-expense revision provenance could not be established.'
            );
        }

        $amountSnapshotCents =
            poultry_slaughter_money_cents(
                (float)$expense['amount']
                *
                (float)$expense['unit']
            );

        $amountSnapshot =
            poultry_slaughter_money_from_cents(
                $amountSnapshotCents
            );

        $insert =
            $pdo->prepare(
                "INSERT INTO poultry_slaughter_batch_expenses
                 (
                     farm_id,
                     batch_id,
                     request_token,
                     request_fingerprint,
                     expense_id,
                     expense_revision_id,
                     expense_revision_no,
                     expense_causal_fingerprint,
                     amount_snapshot
                 )
                 VALUES (?,?,?,?,?,?,?,?,?)"
            );

        $insert->execute([
            $farmId,
            $batchId,
            $requestToken,
            $requestFingerprint,
            $expenseId,
            (int)$revision['id'],
            (int)$revision[
                'revision_no'
            ],
            (string)$revision[
                'causal_fingerprint'
            ],
            $amountSnapshot,
        ]);

        $linkId =
            (int)$pdo->lastInsertId();

        if (function_exists('audit_log_event')) {
            audit_log_event(
                'poultry_slaughter_processing_expense_linked',
                'poultry_slaughter_batch',
                $batchId,
                [
                    'expense_id' =>
                        $expenseId,

                    'expense_revision_id' =>
                        (int)$revision['id'],

                    'category' =>
                        $category,

                    'amount_snapshot' =>
                        $amountSnapshot,

                    'request_fingerprint' =>
                        $requestFingerprint,
                ]
            );
        }

        if ($startedTransaction) {
            $pdo->commit();
        }

        return [
            'link_id' =>
                $linkId,

            'expense_id' =>
                $expenseId,

            'expense_revision_id' =>
                (int)$revision['id'],

            'amount_snapshot' =>
                $amountSnapshot,

            'idempotent' =>
                false,
        ];

    } catch (Throwable $e) {
        if (
            $startedTransaction
            &&
            $pdo->inTransaction()
        ) {
            $pdo->rollBack();
        }

        throw $e;
    }
}
}


/*
 * Freeze the batch valuation before any processed output may enter Inventory.
 *
 * Linked processing expenses are re-proven against their exact current
 * revision. A generic Expense edit/delete therefore cannot silently rewrite
 * an already-selected slaughter valuation.
 */
if (!function_exists('poultry_slaughter_cost_basis_finalize')) {
function poultry_slaughter_cost_basis_finalize(
    PDO $pdo,
    int $farmId,
    int $batchId,
    int $actorUserId
): array {
    if (
        $farmId <= 0
        ||
        $batchId <= 0
        ||
        $actorUserId <= 0
    ) {
        throw new InvalidArgumentException(
            'Cost-basis finalization requires a valid farm, slaughter batch and user.'
        );
    }

    $startedTransaction =
        !$pdo->inTransaction();

    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $batch =
            poultry_slaughter_batch_locked(
                $pdo,
                $farmId,
                $batchId
            );

        if (
            (string)$batch['status']
            === 'reversed'
        ) {
            throw new PoultrySlaughterException(
                'A reversed Poultry slaughter batch cannot be finalized.'
            );
        }

        if (
            !empty(
                $batch[
                    'cost_basis_finalized_at'
                ]
            )
        ) {
            if ($startedTransaction) {
                $pdo->commit();
            }

            return [
                'batch_id' =>
                    $batchId,

                'processing_operating_cost' =>
                    round(
                        (float)$batch[
                            'processing_operating_cost'
                        ],
                        2
                    ),

                'full_cost_basis_amount' =>
                    round(
                        (float)$batch[
                            'full_cost_basis_amount'
                        ],
                        2
                    ),

                'cost_basis_provenance_fingerprint' =>
                    (string)$batch[
                        'cost_basis_provenance_fingerprint'
                    ],

                'idempotent' =>
                    true,
            ];
        }

        $outputStmt =
            $pdo->prepare(
                "SELECT COUNT(*)
                 FROM poultry_slaughter_outputs
                 WHERE farm_id=?
                   AND batch_id=?"
            );

        $outputStmt->execute([
            $farmId,
            $batchId,
        ]);

        if (
            (int)$outputStmt->fetchColumn()
            > 0
        ) {
            throw new PoultrySlaughterException(
                'The slaughter cost basis must be finalized before processed output Inventory is created.'
            );
        }

        $linkStmt =
            $pdo->prepare(
                "SELECT *
                 FROM poultry_slaughter_batch_expenses
                 WHERE farm_id=?
                   AND batch_id=?
                 ORDER BY id
                 FOR UPDATE"
            );

        $linkStmt->execute([
            $farmId,
            $batchId,
        ]);

        $links =
            $linkStmt->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];

        $processingCents = 0;
        $expenseSources = [];

        foreach ($links as $link) {
            $expenseId =
                (int)$link[
                    'expense_id'
                ];

            $expense =
                expense_revision_service_expense(
                    $pdo,
                    $farmId,
                    $expenseId,
                    true
                );

            $latest =
                expense_revision_service_latest(
                    $pdo,
                    $farmId,
                    $expenseId,
                    true
                );

            if (
                $latest === null
                ||
                (int)$latest['id']
                    !==
                    (int)$link[
                        'expense_revision_id'
                    ]
                ||
                (int)$latest[
                    'revision_no'
                ]
                    !==
                    (int)$link[
                        'expense_revision_no'
                    ]
                ||
                (int)$expense[
                    'expense_revision_no'
                ]
                    !==
                    (int)$link[
                        'expense_revision_no'
                    ]
                ||
                !hash_equals(
                    (string)$link[
                        'expense_causal_fingerprint'
                    ],
                    (string)$latest[
                        'causal_fingerprint'
                    ]
                )
                ||
                !hash_equals(
                    (string)$link[
                        'expense_causal_fingerprint'
                    ],
                    (string)$expense[
                        'expense_causal_fingerprint'
                    ]
                )
            ) {
                throw new PoultrySlaughterException(
                    'A linked processing expense changed after it was selected. Correct the slaughter processing expense linkage before finalizing this batch.'
                );
            }

            if (
                strtolower(
                    (string)$expense[
                        'farm_type'
                    ]
                ) !== 'poultry'
                ||
                strtolower(
                    (string)$expense[
                        'production_type'
                    ]
                )
                    !==
                    strtolower(
                        (string)$batch[
                            'production_type'
                        ]
                    )
                ||
                (int)$expense[
                    'cycle_id'
                ]
                    !==
                    (int)$batch[
                        'cycle_id'
                    ]
                ||
                (string)$expense[
                    'expense_date'
                ]
                    !==
                    (string)$batch[
                        'slaughter_date'
                    ]
            ) {
                throw new PoultrySlaughterException(
                    'A linked processing expense no longer belongs to this Poultry slaughter batch.'
                );
            }

            $currentCents =
                poultry_slaughter_money_cents(
                    (float)$expense['amount']
                    *
                    (float)$expense['unit']
                );

            $snapshotCents =
                poultry_slaughter_money_cents(
                    $link[
                        'amount_snapshot'
                    ]
                );

            if (
                $currentCents
                !==
                $snapshotCents
            ) {
                throw new PoultrySlaughterException(
                    'A linked processing expense amount changed after selection. Correct the slaughter processing expense linkage before finalizing this batch.'
                );
            }

            $processingCents +=
                $snapshotCents;

            $expenseSources[] = [
                'link_id' =>
                    (int)$link['id'],

                'expense_id' =>
                    $expenseId,

                'public_reference' =>
                    (string)(
                        $expense[
                            'public_reference'
                        ]
                        ?? ''
                    ),

                'expense_revision_id' =>
                    (int)$link[
                        'expense_revision_id'
                    ],

                'expense_revision_no' =>
                    (int)$link[
                        'expense_revision_no'
                    ],

                'expense_causal_fingerprint' =>
                    (string)$link[
                        'expense_causal_fingerprint'
                    ],

                'category' =>
                    (string)$expense[
                        'category'
                    ],

                'amount' =>
                    round(
                        (float)$expense[
                            'amount'
                        ],
                        2
                    ),

                'unit' =>
                    round(
                        (float)$expense[
                            'unit'
                        ],
                        2
                    ),

                'amount_snapshot' =>
                    poultry_slaughter_money_from_cents(
                        $snapshotCents
                    ),
            ];
        }

        $capitalCents =
            poultry_slaughter_money_cents(
                $batch[
                    'capital_basis_transferred'
                ]
            );

        $embeddedOperatingCents =
            poultry_slaughter_money_cents(
                $batch[
                    'embedded_operating_basis_transferred'
                ]
            );

        $fullCostCents =
            $capitalCents
            +
            $embeddedOperatingCents
            +
            $processingCents;

        $processingCost =
            poultry_slaughter_money_from_cents(
                $processingCents
            );

        $fullCost =
            poultry_slaughter_money_from_cents(
                $fullCostCents
            );

        $manifest =
            json_decode(
                (string)$batch[
                    'cost_basis_provenance_json'
                ],
                true
            );

        if (!is_array($manifest)) {
            throw new PoultrySlaughterException(
                'The existing poultry slaughter cost provenance is invalid.'
            );
        }

        $manifest[
            'processing_expenses'
        ] =
            $expenseSources;

        $manifest[
            'processing_operating_cost'
        ] =
            $processingCost;

        $manifest[
            'full_cost_basis_amount'
        ] =
            $fullCost;

        $manifest[
            'cost_basis_finalization'
        ] = [
            'contract' =>
                'poultry_slaughter_cost_basis_finalization_v1',

            'expense_count' =>
                count(
                    $expenseSources
                ),

            'finalized_by' =>
                $actorUserId,
        ];

        $provenanceJson =
            json_encode(
                $manifest,
                JSON_UNESCAPED_SLASHES
                |
                JSON_PRESERVE_ZERO_FRACTION
            );

        if ($provenanceJson === false) {
            throw new PoultrySlaughterException(
                'Finalized Poultry slaughter cost provenance could not be serialized.'
            );
        }

        $provenanceFingerprint =
            hash(
                'sha256',
                $provenanceJson
            );

        $update =
            $pdo->prepare(
                "UPDATE poultry_slaughter_batches
                 SET
                     processing_operating_cost=?,
                     full_cost_basis_amount=?,
                     cost_basis_method=?,
                     cost_basis_snapshot_at=NOW(),
                     cost_basis_finalized_at=NOW(),
                     cost_basis_finalized_by=?,
                     cost_basis_provenance_fingerprint=?,
                     cost_basis_provenance_json=?
                 WHERE id=?
                   AND farm_id=?
                   AND cost_basis_finalized_at IS NULL"
            );

        $update->execute([
            $processingCost,
            $fullCost,
            'canonical_cycle_cost_pool_plus_processing_v1',
            $actorUserId,
            $provenanceFingerprint,
            $provenanceJson,
            $batchId,
            $farmId,
        ]);

        if (
            $update->rowCount()
            !== 1
        ) {
            throw new PoultrySlaughterException(
                'The Poultry slaughter cost basis changed concurrently. Refresh and try again.'
            );
        }

        if (function_exists('audit_log_event')) {
            audit_log_event(
                'poultry_slaughter_cost_basis_finalized',
                'poultry_slaughter_batch',
                $batchId,
                [
                    'processing_expense_count' =>
                        count(
                            $expenseSources
                        ),

                    'processing_operating_cost' =>
                        $processingCost,

                    'capital_basis_transferred' =>
                        poultry_slaughter_money_from_cents(
                            $capitalCents
                        ),

                    'embedded_operating_basis_transferred' =>
                        poultry_slaughter_money_from_cents(
                            $embeddedOperatingCents
                        ),

                    'full_cost_basis_amount' =>
                        $fullCost,

                    'cost_basis_provenance_fingerprint' =>
                        $provenanceFingerprint,
                ]
            );
        }

        if ($startedTransaction) {
            $pdo->commit();
        }

        return [
            'batch_id' =>
                $batchId,

            'processing_expense_count' =>
                count(
                    $expenseSources
                ),

            'processing_operating_cost' =>
                $processingCost,

            'full_cost_basis_amount' =>
                $fullCost,

            'cost_basis_provenance_fingerprint' =>
                $provenanceFingerprint,

            'idempotent' =>
                false,
        ];

    } catch (Throwable $e) {
        if (
            $startedTransaction
            &&
            $pdo->inTransaction()
        ) {
            $pdo->rollBack();
        }

        throw $e;
    }
}
}


/*
 * Receive one processed Poultry slaughter-output lot into canonical Inventory.
 *
 * Population was already reduced by the durable slaughter batch. This function
 * therefore owns NO live-population movement.
 *
 * Revenue also remains separate. A later explicit Sales-lot workflow consumes
 * these source-specific balances without touching live population again.
 */
if (!function_exists('poultry_slaughter_output_add')) {
function poultry_slaughter_output_add(
    PDO $pdo,
    int $farmId,
    int $batchId,
    int $stockItemId,
    float $quantity,
    float $costSharePercent,
    int $actorUserId
): array {
    $quantity =
        round(
            $quantity,
            2
        );

    $costSharePercent =
        round(
            $costSharePercent,
            4
        );

    if (
        $farmId <= 0
        ||
        $batchId <= 0
        ||
        $stockItemId <= 0
        ||
        $actorUserId <= 0
        ||
        !is_finite($quantity)
        ||
        $quantity <= 0
        ||
        !is_finite($costSharePercent)
        ||
        $costSharePercent <= 0
        ||
        $costSharePercent > 100
    ) {
        throw new InvalidArgumentException(
            'Choose a valid Poultry slaughter batch, Inventory item, quantity and cost share greater than zero.'
        );
    }

    $startedTransaction =
        !$pdo->inTransaction();

    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        /*
         * Batch row is the concurrency gate for every output allocation from
         * this slaughter event.
         */
        $batch =
            poultry_slaughter_batch_locked(
                $pdo,
                $farmId,
                $batchId
            );

        if (
            (string)$batch['status']
            !== 'open'
        ) {
            throw new PoultrySlaughterException(
                'Only an open Poultry slaughter batch can receive processed outputs.'
            );
        }

        if (
            empty(
                $batch[
                    'cost_basis_finalized_at'
                ]
            )
            ||
            empty(
                $batch[
                    'cost_basis_finalized_by'
                ]
            )
        ) {
            throw new PoultrySlaughterException(
                'Finalize this Poultry slaughter batch cost basis before receiving processed output Inventory.'
            );
        }

        /*
         * Re-prove frozen full-cost conservation before creating a physical
         * lot. This protects against corrupted source state even though the DB
         * schema also carries the same conservation CHECK.
         */
        $capitalCents =
            poultry_slaughter_money_cents(
                $batch[
                    'capital_basis_transferred'
                ]
            );

        $embeddedOperatingCents =
            poultry_slaughter_money_cents(
                $batch[
                    'embedded_operating_basis_transferred'
                ]
            );

        $processingCents =
            poultry_slaughter_money_cents(
                $batch[
                    'processing_operating_cost'
                ]
            );

        $fullCostCents =
            poultry_slaughter_money_cents(
                $batch[
                    'full_cost_basis_amount'
                ]
            );

        if (
            $fullCostCents
            !==
            (
                $capitalCents
                +
                $embeddedOperatingCents
                +
                $processingCents
            )
        ) {
            throw new PoultrySlaughterException(
                'Poultry slaughter batch cost conservation failed. Processed Inventory cannot be created.'
            );
        }

        $allocationStmt =
            $pdo->prepare(
                "SELECT
                     COALESCE(
                         SUM(cost_share_percent),
                         0
                     ) AS allocated_percent,
                     COALESCE(
                         SUM(allocated_cost),
                         0
                     ) AS allocated_cost
                 FROM poultry_slaughter_outputs
                 WHERE farm_id=?
                   AND batch_id=?"
            );

        $allocationStmt->execute([
            $farmId,
            $batchId,
        ]);

        $allocation =
            $allocationStmt->fetch(
                PDO::FETCH_ASSOC
            ) ?: [];

        $quote =
            slaughter_output_inventory_allocation(
                poultry_slaughter_money_from_cents(
                    $fullCostCents
                ),
                (float)(
                    $allocation[
                        'allocated_percent'
                    ]
                    ?? 0
                ),
                (float)(
                    $allocation[
                        'allocated_cost'
                    ]
                    ?? 0
                ),
                $quantity,
                $costSharePercent
            );

        $allocatedCost =
            (float)$quote[
                'allocated_cost'
            ];

        $unitCostSnapshot =
            (float)$quote[
                'unit_cost_snapshot'
            ];

        /*
         * Shared helper owns canonical role/domain eligibility and locks the
         * physical Inventory item before any source lot is inserted.
         */
        $item =
            slaughter_output_inventory_lock_item(
                $pdo,
                $farmId,
                $stockItemId,
                'poultry'
            );

        $duplicateStmt =
            $pdo->prepare(
                "SELECT id
                 FROM poultry_slaughter_outputs
                 WHERE farm_id=?
                   AND batch_id=?
                   AND stock_item_id=?
                 LIMIT 1
                 FOR UPDATE"
            );

        $duplicateStmt->execute([
            $farmId,
            $batchId,
            $stockItemId,
        ]);

        if (
            $duplicateStmt->fetchColumn()
            !== false
        ) {
            throw new PoultrySlaughterException(
                'This Inventory item is already recorded for the Poultry slaughter batch. Use a different output item.'
            );
        }

        /*
         * Source lot first: stock_service provenance references this durable
         * row ID, never an inferred product name or quantity.
         */
        $insert =
            $pdo->prepare(
                "INSERT INTO poultry_slaughter_outputs
                 (
                     farm_id,
                     batch_id,
                     stock_item_id,
                     stock_transaction_id,
                     initial_quantity,
                     remaining_quantity,
                     unit,
                     cost_share_percent,
                     allocated_cost,
                     unit_cost_snapshot,
                     created_by
                 )
                 VALUES
                 (
                     ?, ?, ?, NULL,
                     ?, ?, ?, ?, ?, ?, ?
                 )"
            );

        $insert->execute([
            $farmId,
            $batchId,
            $stockItemId,
            $quantity,
            $quantity,
            (string)$item['unit'],
            $costSharePercent,
            $allocatedCost,
            $unitCostSnapshot,
            $actorUserId,
        ]);

        $outputId =
            (int)$pdo->lastInsertId();

        $transactionId =
            slaughter_output_inventory_receive(
                $pdo,
                $farmId,
                $stockItemId,
                $quantity,
                (string)$batch[
                    'slaughter_date'
                ],
                'Poultry slaughter output '
                    .
                    (string)$batch[
                        'batch_code'
                    ],
                $actorUserId,
                'poultry',
                (int)$batch[
                    'cycle_id'
                ],
                $outputId,
                $unitCostSnapshot,
                (string)$batch[
                    'production_type'
                ],
                $allocatedCost
            );

        $update =
            $pdo->prepare(
                "UPDATE poultry_slaughter_outputs
                 SET stock_transaction_id=?
                 WHERE id=?
                   AND farm_id=?
                   AND stock_transaction_id IS NULL"
            );

        $update->execute([
            $transactionId,
            $outputId,
            $farmId,
        ]);

        if (
            $update->rowCount()
            !== 1
        ) {
            throw new PoultrySlaughterException(
                'Poultry slaughter output stock provenance changed concurrently. Refresh and try again.'
            );
        }

        if (function_exists('audit_log_event')) {
            audit_log_event(
                'poultry_slaughter_output_received',
                'poultry_slaughter_output',
                $outputId,
                [
                    'batch_id' =>
                        $batchId,

                    'batch_code' =>
                        (string)$batch[
                            'batch_code'
                        ],

                    'cycle_id' =>
                        (int)$batch[
                            'cycle_id'
                        ],

                    'production_type' =>
                        (string)$batch[
                            'production_type'
                        ],

                    'stock_item_id' =>
                        $stockItemId,

                    'stock_transaction_id' =>
                        $transactionId,

                    'quantity' =>
                        $quantity,

                    'unit' =>
                        (string)$item[
                            'unit'
                        ],

                    'cost_share_percent' =>
                        $costSharePercent,

                    'allocated_cost' =>
                        $allocatedCost,

                    'unit_cost_snapshot' =>
                        $unitCostSnapshot,

                    'cumulative_cost_share_percent' =>
                        (float)$quote[
                            'new_percent'
                        ],

                    'cost_basis_provenance_fingerprint' =>
                        (string)$batch[
                            'cost_basis_provenance_fingerprint'
                        ],
                ]
            );
        }

        if ($startedTransaction) {
            $pdo->commit();
        }

        return [
            'output_id' =>
                $outputId,

            'stock_transaction_id' =>
                $transactionId,

            'batch_id' =>
                $batchId,

            'stock_item_id' =>
                $stockItemId,

            'quantity' =>
                $quantity,

            'unit' =>
                (string)$item[
                    'unit'
                ],

            'cost_share_percent' =>
                $costSharePercent,

            'cumulative_cost_share_percent' =>
                (float)$quote[
                    'new_percent'
                ],

            'allocated_cost' =>
                $allocatedCost,

            'unit_cost_snapshot' =>
                $unitCostSnapshot,
        ];

    } catch (Throwable $e) {
        if (
            $startedTransaction
            &&
            $pdo->inTransaction()
        ) {
            $pdo->rollBack();
        }

        throw $e;
    }
}
}
