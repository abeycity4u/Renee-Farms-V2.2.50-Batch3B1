<?php

/**
 * Species-neutral arithmetic for slaughter-output sale economics.
 *
 * Domain readers own persistence/provenance validation.
 * This helper owns only deterministic monetary decomposition.
 */

if (!function_exists('slaughter_output_sale_economics_money_cents')) {
function slaughter_output_sale_economics_money_cents(
    $value
): int {
    if (
        $value === null
        ||
        $value === ''
        ||
        !is_numeric($value)
    ) {
        throw new RuntimeException(
            'Slaughter-sale COGS contains an invalid monetary amount.'
        );
    }

    return
        (int)round(
            ((float)$value) * 100
        );
}
}


if (!function_exists('slaughter_output_sale_economics_proportional_cents')) {
function slaughter_output_sale_economics_proportional_cents(
    int $amountCents,
    int $componentCents,
    int $totalBasisCents
): int {
    if (
        $amountCents < 0
        ||
        $componentCents < 0
        ||
        $totalBasisCents < 0
        ||
        $componentCents > $totalBasisCents
    ) {
        throw new RuntimeException(
            'Slaughter-sale economic basis is invalid.'
        );
    }

    if ($totalBasisCents === 0) {
        if ($amountCents !== 0) {
            throw new RuntimeException(
                'A non-zero slaughter-sale valuation has no frozen cost basis.'
            );
        }

        return 0;
    }

    return
        (int)round(
            $amountCents
            *
            (
                $componentCents
                /
                $totalBasisCents
            ),
            0,
            PHP_ROUND_HALF_UP
        );
}
}
