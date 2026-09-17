<?php

/*
 * V3.0.1 Production-Entry provenance clarity read model.
 *
 * Presentation-only:
 * - no database access;
 * - no accounting formula;
 * - no source-selection policy;
 * - no provenance mutation;
 * - no snapshot mutation.
 *
 * Monetary authority remains poultry_rearing_economics() /
 * poultry_production_entry_candidate().
 *
 * This helper only explains the canonical provenance manifest already
 * produced by the Production-Entry provenance foundation.
 */

if (!function_exists(
    'poultry_production_entry_clarity_source_type_label'
)) {
function poultry_production_entry_clarity_source_type_label(
    string $sourceType
): string {
    $sourceType =
        strtolower(
            trim(
                $sourceType
            )
        );

    $labels = [
        'poultry_cycle_acquisition' =>
            'Flock acquisition',

        'stock_transaction' =>
            'Consumed stock ledger',

        'stock_consumption_allocation' =>
            'Consumed-stock allocation',

        'farm_expense' =>
            'Farm expense',

        'financial_allocation' =>
            'Shared-expense allocation',

        'production_cycle_phase' =>
            'Lifecycle history',

        'production_population_baseline' =>
            'Population baseline',

        'production_population_movement' =>
            'Population movement',

        'layer_daily_record' =>
            'Layer Daily Record',
    ];

    return
        $labels[$sourceType]
        ?? (
            $sourceType !== ''
                ? $sourceType
                : 'Unknown source'
        );
}
}

if (!function_exists(
    'poultry_production_entry_clarity_role_contract'
)) {
function poultry_production_entry_clarity_role_contract(
    string $role,
    string $sourceType
): array {
    $role =
        strtolower(
            trim(
                $role
            )
        );

    $sourceType =
        strtolower(
            trim(
                $sourceType
            )
        );

    $key =
        $role
        . '|'
        . $sourceType;

    $contracts = [
        'acquisition|poultry_cycle_acquisition' => [
            'category_key' =>
                'economic_input',

            'category_label' =>
                'Economic input',

            'label' =>
                'Bird acquisition basis',

            'basis_effect' =>
                'Included in attributed investment',

            'description' =>
                'Recorded flock-entry acquisition evidence used by the canonical Production-Entry economics.',
        ],

        'feed_use|stock_transaction' => [
            'category_key' =>
                'economic_input',

            'category_label' =>
                'Economic input',

            'label' =>
                'Direct/native Feed use',

            'basis_effect' =>
                'Included in attributed investment',

            'description' =>
                'Feed consumption recorded directly against this cycle inside the Rearing window.',
        ],

        'feed_use|stock_consumption_allocation' => [
            'category_key' =>
                'economic_input',

            'category_label' =>
                'Economic input',

            'label' =>
                'Explicit allocated Feed use',

            'basis_effect' =>
                'Included in attributed investment',

            'description' =>
                'A consumed-stock Feed cost explicitly allocated from a broader source to this cycle.',
        ],

        'operating_inventory_use|stock_transaction' => [
            'category_key' =>
                'economic_input',

            'category_label' =>
                'Economic input',

            'label' =>
                'Direct/native operating-stock use',

            'basis_effect' =>
                'Included in attributed investment',

            'description' =>
                'Medication, vaccine, supplement or consumable use recorded directly against this cycle.',
        ],

        'operating_inventory_use|stock_consumption_allocation' => [
            'category_key' =>
                'economic_input',

            'category_label' =>
                'Economic input',

            'label' =>
                'Explicit allocated operating-stock use',

            'basis_effect' =>
                'Included in attributed investment',

            'description' =>
                'Operating consumed-stock cost explicitly allocated from a broader source to this cycle.',
        ],

        'direct_expense|farm_expense' => [
            'category_key' =>
                'economic_input',

            'category_label' =>
                'Economic input',

            'label' =>
                'Direct non-feed expense',

            'basis_effect' =>
                'Included in attributed investment',

            'description' =>
                'Farm expense recorded directly against this cycle inside the Rearing window.',
        ],

        'explicit_shared_allocation|financial_allocation' => [
            'category_key' =>
                'economic_input',

            'category_label' =>
                'Economic input',

            'label' =>
                'Explicit shared-expense allocation',

            'basis_effect' =>
                'Included in attributed investment',

            'description' =>
                'A shared farm expense explicitly allocated to this cycle.',
        ],

        'lifecycle_phase|production_cycle_phase' => [
            'category_key' =>
                'boundary_authority',

            'category_label' =>
                'Inclusion boundary',

            'label' =>
                'Lifecycle phase',

            'basis_effect' =>
                'Defines the Rearing / Production boundary; not an amount',

            'description' =>
                'The explicit biological lifecycle establishes which effective dates belong to the Rearing window.',
        ],

        'population_baseline|production_population_baseline' => [
            'category_key' =>
                'population_authority',

            'category_label' =>
                'Population authority',

            'label' =>
                'Population baseline',

            'basis_effect' =>
                'Defines Production-Entry flock; not an amount',

            'description' =>
                'Canonical population baseline used when establishing the surviving Production-Entry flock.',
        ],

        'population_movement|production_population_movement' => [
            'category_key' =>
                'population_authority',

            'category_label' =>
                'Population authority',

            'label' =>
                'Population movement',

            'basis_effect' =>
                'Defines Production-Entry flock; not an amount',

            'description' =>
                'Canonical population movements affecting the Production-Entry flock boundary.',
        ],

        'production_start_daily_reconciliation|layer_daily_record' => [
            'category_key' =>
                'reconciliation',

            'category_label' =>
                'Boundary reconciliation',

            'label' =>
                'Production-start Daily Record',

            'basis_effect' =>
                'Cross-check evidence; not an amount',

            'description' =>
                'Daily Record evidence used to reconcile the production-opening flock to the canonical population boundary.',
        ],

        'rearing_end_daily_reconciliation|layer_daily_record' => [
            'category_key' =>
                'reconciliation',

            'category_label' =>
                'Boundary reconciliation',

            'label' =>
                'Rearing-end Daily Record',

            'basis_effect' =>
                'Cross-check evidence; not an amount',

            'description' =>
                'Daily Record evidence used to reconcile the Rearing closing flock to the canonical population boundary.',
        ],

        'shared_pool_expense|farm_expense' => [
            'category_key' =>
                'outside_cycle_disclosure',

            'category_label' =>
                'Outside-cycle disclosure',

            'label' =>
                'Shared expense pool source',

            'basis_effect' =>
                'Disclosure only; unallocated remainder is not silently included',

            'description' =>
                'Shared expense evidence used to disclose cost that remains outside this cycle.',
        ],

        'shared_pool_allocation|financial_allocation' => [
            'category_key' =>
                'outside_cycle_disclosure',

            'category_label' =>
                'Outside-cycle disclosure',

            'label' =>
                'Shared expense pool allocation dependency',

            'basis_effect' =>
                'Disclosure only; not added to this cycle unless explicitly targeted here',

            'description' =>
                'Existing allocations against a shared parent are dependency evidence for the visible unallocated remainder.',
        ],
    ];

    $contract =
        $contracts[$key]
        ?? [
            'category_key' =>
                'other',

            'category_label' =>
                'Other provenance',

            'label' =>
                $role !== ''
                    ? ucwords(
                        str_replace(
                            '_',
                            ' ',
                            $role
                        )
                    )
                    : 'Source evidence',

            'basis_effect' =>
                'Identity / dependency evidence',

            'description' =>
                'Canonical provenance evidence retained by the Production-Entry source manifest.',
        ];

    $contract['role'] =
        $role;

    $contract['source_type'] =
        $sourceType;

    $contract['source_type_label'] =
        poultry_production_entry_clarity_source_type_label(
            $sourceType
        );

    return
        $contract;
}
}

if (!function_exists(
    'poultry_production_entry_clarity_manifest_summary'
)) {
function poultry_production_entry_clarity_manifest_summary(
    array $manifest
): array {
    if (
        (
            $manifest['schema']
            ?? ''
        )
        !==
        'poultry_production_entry_provenance'
        ||
        (int)(
            $manifest['schema_version']
            ?? 0
        )
        !== 1
    ) {
        throw new RuntimeException(
            'Production-Entry provenance manifest is not supported for clarity presentation.'
        );
    }

    $sources =
        $manifest['sources']
        ?? null;

    if (!is_array($sources)) {
        throw new RuntimeException(
            'Production-Entry provenance source list is invalid.'
        );
    }

    $groups = [];

    foreach ($sources as $source) {
        if (!is_array($source)) {
            throw new RuntimeException(
                'Production-Entry provenance contains an invalid source record.'
            );
        }

        $role =
            strtolower(
                trim(
                    (string)(
                        $source['role']
                        ?? ''
                    )
                )
            );

        $sourceType =
            strtolower(
                trim(
                    (string)(
                        $source['source_type']
                        ?? ''
                    )
                )
            );

        if (
            $role === ''
            ||
            $sourceType === ''
        ) {
            throw new RuntimeException(
                'Production-Entry provenance source identity is incomplete.'
            );
        }

        $contract =
            poultry_production_entry_clarity_role_contract(
                $role,
                $sourceType
            );

        $key =
            $role
            . "\x1F"
            . $sourceType;

        if (!isset($groups[$key])) {
            $groups[$key] = [
                'role' =>
                    $role,

                'source_type' =>
                    $sourceType,

                'source_type_label' =>
                    $contract[
                        'source_type_label'
                    ],

                'category_key' =>
                    $contract[
                        'category_key'
                    ],

                'category_label' =>
                    $contract[
                        'category_label'
                    ],

                'label' =>
                    $contract['label'],

                'basis_effect' =>
                    $contract[
                        'basis_effect'
                    ],

                'description' =>
                    $contract[
                        'description'
                    ],

                'count' =>
                    0,

                'effective_date_start' =>
                    null,

                'effective_date_end' =>
                    null,
            ];
        }

        $groups[$key]['count']++;

        $date =
            trim(
                (string)(
                    $source[
                        'effective_date'
                    ]
                    ?? ''
                )
            );

        if ($date !== '') {
            if (
                $groups[$key][
                    'effective_date_start'
                ] === null
                ||
                $date
                <
                $groups[$key][
                    'effective_date_start'
                ]
            ) {
                $groups[$key][
                    'effective_date_start'
                ] =
                    $date;
            }

            if (
                $groups[$key][
                    'effective_date_end'
                ] === null
                ||
                $date
                >
                $groups[$key][
                    'effective_date_end'
                ]
            ) {
                $groups[$key][
                    'effective_date_end'
                ] =
                    $date;
            }
        }
    }

    $order = [
        'economic_input' =>
            10,

        'boundary_authority' =>
            20,

        'population_authority' =>
            30,

        'reconciliation' =>
            40,

        'outside_cycle_disclosure' =>
            50,

        'other' =>
            90,
    ];

    $groupRows =
        array_values(
            $groups
        );

    usort(
        $groupRows,
        static function (
            array $left,
            array $right
        ) use ($order): int {
            $leftOrder =
                $order[
                    $left['category_key']
                ]
                ?? 999;

            $rightOrder =
                $order[
                    $right['category_key']
                ]
                ?? 999;

            if ($leftOrder !== $rightOrder) {
                return
                    $leftOrder
                    <=>
                    $rightOrder;
            }

            $labelCompare =
                strcmp(
                    (string)$left['label'],
                    (string)$right['label']
                );

            if ($labelCompare !== 0) {
                return
                    $labelCompare;
            }

            return
                strcmp(
                    (string)$left[
                        'source_type'
                    ],
                    (string)$right[
                        'source_type'
                    ]
                );
        }
    );

    $context =
        isset($manifest['context'])
        &&
        is_array($manifest['context'])
            ? $manifest['context']
            : [];

    return [
        'recorded' =>
            true,

        'state' =>
            'recorded',

        'schema' =>
            (string)$manifest['schema'],

        'schema_version' =>
            (int)$manifest[
                'schema_version'
            ],

        'source_count' =>
            count($sources),

        'context' => [
            'mode' =>
                $context['mode']
                ?? null,

            'rearing_start_date' =>
                $context[
                    'rearing_start_date'
                ]
                ?? null,

            'rearing_end_date' =>
                $context[
                    'rearing_end_date'
                ]
                ?? null,

            'production_entry_date' =>
                $context[
                    'production_entry_date'
                ]
                ?? null,
        ],

        'groups' =>
            $groupRows,
    ];
}
}

if (!function_exists(
    'poultry_production_entry_clarity_candidate_summary'
)) {
function poultry_production_entry_clarity_candidate_summary(
    array $candidate
): array {
    $manifest =
        $candidate[
            'provenance_manifest'
        ]
        ?? null;

    if (!is_array($manifest)) {
        return [
            'recorded' =>
                false,

            'state' =>
                'current_not_available',

            'source_count' =>
                0,

            'context' =>
                [],

            'groups' =>
                [],
        ];
    }

    $summary =
        poultry_production_entry_clarity_manifest_summary(
            $manifest
        );

    $expected =
        $candidate[
            'provenance_source_count'
        ]
        ?? null;

    if (
        $expected !== null
        &&
        (int)$expected
        !==
        (int)$summary[
            'source_count'
        ]
    ) {
        throw new RuntimeException(
            'Current Production-Entry provenance source count does not match its manifest.'
        );
    }

    $summary['state'] =
        'current';

    return
        $summary;
}
}

if (!function_exists(
    'poultry_production_entry_clarity_snapshot_summary'
)) {
function poultry_production_entry_clarity_snapshot_summary(
    ?array $snapshot
): array {
    if ($snapshot === null) {
        return [
            'recorded' =>
                false,

            'state' =>
                'no_snapshot',

            'version_no' =>
                null,

            'snapshot_status' =>
                null,

            'source_count' =>
                0,

            'context' =>
                [],

            'groups' =>
                [],
        ];
    }

    $base = [
        'version_no' =>
            (int)(
                $snapshot[
                    'version_no'
                ]
                ?? 0
            ),

        'snapshot_status' =>
            strtolower(
                trim(
                    (string)(
                        $snapshot[
                            'snapshot_status'
                        ]
                        ?? ''
                    )
                )
            ),
    ];

    $json =
        trim(
            (string)(
                $snapshot[
                    'provenance_manifest_json'
                ]
                ?? ''
            )
        );

    if ($json === '') {
        return
            $base
            + [
                'recorded' =>
                    false,

                'state' =>
                    'legacy_not_recorded',

                'source_count' =>
                    0,

                'context' =>
                    [],

                'groups' =>
                    [],
            ];
    }

    try {
        $manifest =
            json_decode(
                $json,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
    } catch (JsonException $e) {
        throw new RuntimeException(
            'Approved Production-Entry provenance manifest is invalid.',
            0,
            $e
        );
    }

    if (!is_array($manifest)) {
        throw new RuntimeException(
            'Approved Production-Entry provenance manifest is invalid.'
        );
    }

    $summary =
        poultry_production_entry_clarity_manifest_summary(
            $manifest
        );

    $expected =
        $snapshot[
            'provenance_source_count'
        ]
        ?? null;

    if (
        $expected !== null
        &&
        trim(
            (string)$expected
        ) !== ''
        &&
        (int)$expected
        !==
        (int)$summary[
            'source_count'
        ]
    ) {
        throw new RuntimeException(
            'Approved Production-Entry provenance source count does not match its manifest.'
        );
    }

    $summary['state'] =
        'approved';

    return
        $base
        + $summary;
}
}
