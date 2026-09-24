<?php

require_once __DIR__ . '/slaughter_output_sale_economics_common.php';
require_once __DIR__ . '/ruminant_slaughter_sale_economics.php';
require_once __DIR__ . '/poultry_slaughter_sale_economics.php';

/**
 * Canonical dual-domain slaughter-output sale economics.
 *
 * Domain readers prove their own immutable provenance.
 * This boundary only aggregates already-proven economics.
 *
 * Recognised COGS:
 *   Poultry capital released
 *   + Ruminant purchase/capital released
 *
 * Embedded operating cost is disclosure only because its authoritative
 * operating sources already reached Profitability elsewhere.
 */

if (!function_exists('slaughter_output_sale_economics_summary')) {
function slaughter_output_sale_economics_summary(
    PDO $pdo,
    int $farmId,
    string $startDate,
    string $endDate,
    string $farmType = 'all',
    ?string $productionType = null,
    ?int $cycleId = null
): array {
    $poultry =
        poultry_slaughter_sale_economics_summary(
            $pdo,
            $farmId,
            $startDate,
            $endDate,
            $farmType,
            $productionType,
            $cycleId
        );

    $ruminant =
        ruminant_slaughter_sale_economics_summary(
            $pdo,
            $farmId,
            $startDate,
            $endDate,
            $farmType,
            $productionType,
            $cycleId
        );

    $domainBreakdown = [
        'poultry' => [
            'slaughter_output_cogs' =>
                (float)(
                    $poultry[
                        'slaughter_output_cogs'
                    ]
                    ?? 0
                ),

            'slaughter_output_full_cost_valuation' =>
                (float)(
                    $poultry[
                        'slaughter_output_full_cost_valuation'
                    ]
                    ?? 0
                ),

            'slaughter_output_embedded_operating_cost' =>
                (float)(
                    $poultry[
                        'slaughter_output_embedded_operating_cost'
                    ]
                    ?? 0
                ),
        ],

        'ruminant' => [
            'slaughter_output_cogs' =>
                (float)(
                    $ruminant[
                        'slaughter_output_cogs'
                    ]
                    ?? 0
                ),

            'slaughter_output_full_cost_valuation' =>
                (float)(
                    $ruminant[
                        'slaughter_output_full_cost_valuation'
                    ]
                    ?? 0
                ),

            'slaughter_output_embedded_operating_cost' =>
                (float)(
                    $ruminant[
                        'slaughter_output_embedded_operating_cost'
                    ]
                    ?? 0
                ),
        ],
    ];

    $recognizedCogsCents = 0;
    $fullCostCents = 0;
    $embeddedOperatingCents = 0;

    foreach (
        $domainBreakdown
        as $domain => $values
    ) {
        $domainCogsCents =
            slaughter_output_sale_economics_money_cents(
                $values[
                    'slaughter_output_cogs'
                ]
            );

        $domainFullCostCents =
            slaughter_output_sale_economics_money_cents(
                $values[
                    'slaughter_output_full_cost_valuation'
                ]
            );

        $domainEmbeddedOperatingCents =
            slaughter_output_sale_economics_money_cents(
                $values[
                    'slaughter_output_embedded_operating_cost'
                ]
            );

        if (
            $domainCogsCents
            +
            $domainEmbeddedOperatingCents
            !==
            $domainFullCostCents
        ) {
            throw new RuntimeException(
                ucfirst(
                    $domain
                )
                .
                ' slaughter-sale valuation decomposition does not conserve full cost.'
            );
        }

        $recognizedCogsCents +=
            $domainCogsCents;

        $fullCostCents +=
            $domainFullCostCents;

        $embeddedOperatingCents +=
            $domainEmbeddedOperatingCents;
    }

    if (
        $recognizedCogsCents
        +
        $embeddedOperatingCents
        !==
        $fullCostCents
    ) {
        throw new RuntimeException(
            'Combined slaughter-sale valuation decomposition does not conserve full cost.'
        );
    }

    return [
        'slaughter_output_cogs' =>
            round(
                $recognizedCogsCents / 100,
                2
            ),

        'slaughter_output_full_cost_valuation' =>
            round(
                $fullCostCents / 100,
                2
            ),

        'slaughter_output_embedded_operating_cost' =>
            round(
                $embeddedOperatingCents / 100,
                2
            ),

        'domain_breakdown' =>
            $domainBreakdown,
    ];
}
}
