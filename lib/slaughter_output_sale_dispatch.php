<?php

require_once __DIR__ . '/slaughter_output_sale_common.php';
require_once __DIR__ . '/ruminant_slaughter_sale_consumption.php';
require_once __DIR__ . '/poultry_slaughter_sale_consumption.php';

/**
 * Shared dual-domain slaughter-output Sales dispatcher.
 *
 * The Sales page/API should use this boundary instead of branching directly
 * between Ruminant and Poultry lot services.
 *
 * Browser input chooses only the source domain identity. Each domain service
 * then re-proves the selected lot from canonical source tables and derives the
 * financial Sale farm/type/cycle/product/quantity/unit from that provenance.
 */

if (!class_exists('SlaughterOutputSaleException')) {
    class SlaughterOutputSaleException extends RuntimeException
    {
    }
}


function slaughter_output_sale_exception_factory(): callable
{
    return
        static function (
            string $message
        ): Throwable {
            return
                new SlaughterOutputSaleException(
                    $message
                );
        };
}


function slaughter_output_sale_domains(): array
{
    return [
        'poultry',
        'ruminant',
    ];
}


function slaughter_output_sale_domain_from_post(
    array $input
): ?string {
    $mode =
        strtolower(
            trim(
                (string)(
                    $input[
                        'sale_stock_source'
                    ]
                    ??
                    'financial_only'
                )
            )
        );

    if (
        $mode === ''
        ||
        $mode === 'financial_only'
    ) {
        return null;
    }

    if (
        $mode
        !== 'slaughter_output'
    ) {
        throw new SlaughterOutputSaleException(
            'Choose a valid Sales stock source.'
        );
    }

    $domain =
        strtolower(
            trim(
                (string)(
                    $input[
                        'slaughter_output_domain'
                    ]
                    ?? ''
                )
            )
        );

    if (
        !in_array(
            $domain,
            slaughter_output_sale_domains(),
            true
        )
    ) {
        throw new SlaughterOutputSaleException(
            'Choose whether the slaughter-output stock came from Poultry or Ruminant processing.'
        );
    }

    return $domain;
}


function slaughter_output_sale_wrap_domain_exception(
    Throwable $e
): void {
    if (
        $e instanceof RuminantSlaughterSaleException
        ||
        $e instanceof PoultrySlaughterSaleException
    ) {
        throw new SlaughterOutputSaleException(
            $e->getMessage(),
            0,
            $e
        );
    }

    throw $e;
}


function slaughter_output_sale_selection_from_post(
    PDO $pdo,
    int $farmId,
    string $saleDate,
    array $input,
    ?int $saleId = null
): array {
    $rows =
        slaughter_output_sale_common_rows_from_post(
            $input,
            slaughter_output_sale_exception_factory()
        );

    if (!$rows) {
        return [
            'mode' =>
                'financial_only',

            'slaughter_domain' =>
                null,

            'rows' =>
                [],
        ];
    }

    $domain =
        slaughter_output_sale_domain_from_post(
            $input
        );

    try {
        if ($domain === 'poultry') {
            $selection =
                poultry_slaughter_sale_selection(
                    $pdo,
                    $farmId,
                    $saleDate,
                    $rows,
                    $saleId
                );

        } else {
            $selection =
                ruminant_slaughter_sale_selection(
                    $pdo,
                    $farmId,
                    $saleDate,
                    $rows,
                    $saleId
                );
        }

    } catch (Throwable $e) {
        slaughter_output_sale_wrap_domain_exception(
            $e
        );
    }

    $selection[
        'slaughter_domain'
    ] =
        $domain;

    return $selection;
}


function slaughter_output_sale_available_lots(
    PDO $pdo,
    int $farmId,
    array $allowedFarmTypes
): array {
    $allowed =
        array_values(
            array_intersect(
                slaughter_output_sale_domains(),
                array_map(
                    static fn($value): string =>
                        strtolower(
                            trim(
                                (string)$value
                            )
                        ),
                    $allowedFarmTypes
                )
            )
        );

    $rows = [];

    if (
        in_array(
            'poultry',
            $allowed,
            true
        )
    ) {
        foreach (
            poultry_slaughter_sale_available_lots(
                $pdo,
                $farmId
            )
            as $row
        ) {
            $row[
                'slaughter_domain'
            ] =
                'poultry';

            $rows[] = $row;
        }
    }

    if (
        in_array(
            'ruminant',
            $allowed,
            true
        )
    ) {
        foreach (
            ruminant_slaughter_sale_available_lots(
                $pdo,
                $farmId
            )
            as $row
        ) {
            $row[
                'slaughter_domain'
            ] =
                'ruminant';

            $rows[] = $row;
        }
    }

    usort(
        $rows,
        static function (
            array $a,
            array $b
        ): int {
            return
                [
                    strtolower(
                        (string)(
                            $a[
                                'item_name'
                            ]
                            ?? ''
                        )
                    ),
                    (string)(
                        $a[
                            'slaughter_date'
                        ]
                        ?? ''
                    ),
                    (string)(
                        $a[
                            'slaughter_domain'
                        ]
                        ?? ''
                    ),
                    (int)(
                        $a[
                            'batch_id'
                        ]
                        ?? 0
                    ),
                    (int)(
                        $a[
                            'output_id'
                        ]
                        ?? 0
                    ),
                ]
                <=>
                [
                    strtolower(
                        (string)(
                            $b[
                                'item_name'
                            ]
                            ?? ''
                        )
                    ),
                    (string)(
                        $b[
                            'slaughter_date'
                        ]
                        ?? ''
                    ),
                    (string)(
                        $b[
                            'slaughter_domain'
                        ]
                        ?? ''
                    ),
                    (int)(
                        $b[
                            'batch_id'
                        ]
                        ?? 0
                    ),
                    (int)(
                        $b[
                            'output_id'
                        ]
                        ?? 0
                    ),
                ];
        }
    );

    return $rows;
}


function slaughter_output_sale_history_for_sales(
    PDO $pdo,
    int $farmId,
    array $saleIds
): array {
    $map = [];

    $sources = [
        'poultry' =>
            poultry_slaughter_sale_history_for_sales(
                $pdo,
                $farmId,
                $saleIds
            ),

        'ruminant' =>
            ruminant_slaughter_sale_history_for_sales(
                $pdo,
                $farmId,
                $saleIds
            ),
    ];

    foreach (
        $sources
        as $domain => $domainMap
    ) {
        foreach (
            $domainMap
            as $saleId => $rows
        ) {
            foreach ($rows as $row) {
                $row[
                    'slaughter_domain'
                ] =
                    $domain;

                $map[
                    (int)$saleId
                ][] = $row;
            }
        }
    }

    foreach ($map as &$rows) {
        usort(
            $rows,
            static function (
                array $a,
                array $b
            ): int {
                return
                    [
                        -(
                            (int)(
                                $a[
                                    'is_active'
                                ]
                                ?? 0
                            )
                        ),
                        (string)(
                            $a[
                                'slaughter_domain'
                            ]
                            ?? ''
                        ),
                        (int)(
                            $a[
                                'id'
                            ]
                            ?? 0
                        ),
                    ]
                    <=>
                    [
                        -(
                            (int)(
                                $b[
                                    'is_active'
                                ]
                                ?? 0
                            )
                        ),
                        (string)(
                            $b[
                                'slaughter_domain'
                            ]
                            ?? ''
                        ),
                        (int)(
                            $b[
                                'id'
                            ]
                            ?? 0
                        ),
                    ];
            }
        );
    }

    unset($rows);

    return $map;
}


function slaughter_output_sale_active_state(
    PDO $pdo,
    int $farmId,
    int $saleId,
    bool $forUpdate = false
): array {
    $poultry =
        poultry_slaughter_sale_current_active_rows(
            $pdo,
            $farmId,
            $saleId,
            $forUpdate
        );

    $ruminant =
        ruminant_slaughter_sale_current_active_rows(
            $pdo,
            $farmId,
            $saleId,
            $forUpdate
        );

    if (
        $poultry
        &&
        $ruminant
    ) {
        throw new SlaughterOutputSaleException(
            'This sale has conflicting active Poultry and Ruminant slaughter-output histories. Correct the source history before editing the sale.'
        );
    }

    return [
        'domain' =>
            $poultry
                ? 'poultry'
                : (
                    $ruminant
                        ? 'ruminant'
                        : null
                ),

        'poultry' =>
            $poultry,

        'ruminant' =>
            $ruminant,
    ];
}


function slaughter_output_sale_sync(
    PDO $pdo,
    int $farmId,
    int $saleId,
    array $selection,
    ?int $userId
): array {
    slaughter_output_sale_common_require_transaction(
        $pdo,
        slaughter_output_sale_exception_factory()
    );

    $mode =
        (string)(
            $selection[
                'mode'
            ]
            ?? 'financial_only'
        );

    $domain =
        $selection[
            'slaughter_domain'
        ]
        ?? null;

    if (
        $mode === 'slaughter_output'
        &&
        !in_array(
            $domain,
            slaughter_output_sale_domains(),
            true
        )
    ) {
        throw new SlaughterOutputSaleException(
            'The slaughter-output Sale selection has no valid Poultry/Ruminant source domain.'
        );
    }

    if (
        $mode !== 'financial_only'
        &&
        $mode !== 'slaughter_output'
    ) {
        throw new SlaughterOutputSaleException(
            'The slaughter-output Sale synchronization mode is invalid.'
        );
    }

    $active =
        slaughter_output_sale_active_state(
            $pdo,
            $farmId,
            $saleId,
            true
        );

    $previousDomain =
        $active[
            'domain'
        ];

    $crossDomainReversed =
        false;

    $financialOnly = [
        'mode' =>
            'financial_only',

        'rows' =>
            [],
    ];

    try {
        /*
         * A financial-only edit or a cross-domain conversion first closes the
         * old physical lot allocation append-only.
         */
        if (
            $previousDomain === 'poultry'
            &&
            $domain !== 'poultry'
        ) {
            poultry_slaughter_sale_sync(
                $pdo,
                $farmId,
                $saleId,
                $financialOnly,
                $userId
            );

            $crossDomainReversed = true;
        }

        if (
            $previousDomain === 'ruminant'
            &&
            $domain !== 'ruminant'
        ) {
            ruminant_slaughter_sale_sync(
                $pdo,
                $farmId,
                $saleId,
                $financialOnly,
                $userId
            );

            $crossDomainReversed = true;
        }

        if (
            $mode === 'financial_only'
        ) {
            return [
                'status' =>
                    'financial_only',

                'slaughter_domain' =>
                    null,

                'changed' =>
                    $crossDomainReversed,

                'allocation_count' =>
                    0,

                'previous_domain' =>
                    $previousDomain,
            ];
        }

        if ($domain === 'poultry') {
            $result =
                poultry_slaughter_sale_sync(
                    $pdo,
                    $farmId,
                    $saleId,
                    $selection,
                    $userId
                );

        } else {
            $result =
                ruminant_slaughter_sale_sync(
                    $pdo,
                    $farmId,
                    $saleId,
                    $selection,
                    $userId
                );
        }

    } catch (Throwable $e) {
        slaughter_output_sale_wrap_domain_exception(
            $e
        );
    }

    $result[
        'slaughter_domain'
    ] =
        $domain;

    $result[
        'previous_domain'
    ] =
        $previousDomain;

    if ($crossDomainReversed) {
        $result[
            'changed'
        ] =
            true;
    }

    return $result;
}


function slaughter_output_sale_assert_deletable(
    PDO $pdo,
    int $farmId,
    int $saleId
): void {
    try {
        /*
         * Any durable history in either domain blocks hard deletion,
         * including inactive/reversed allocation history.
         */
        poultry_slaughter_sale_assert_deletable(
            $pdo,
            $farmId,
            $saleId
        );

        ruminant_slaughter_sale_assert_deletable(
            $pdo,
            $farmId,
            $saleId
        );

    } catch (Throwable $e) {
        slaughter_output_sale_wrap_domain_exception(
            $e
        );
    }
}
