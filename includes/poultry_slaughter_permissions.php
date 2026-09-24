<?php

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/permission_catalog.php';

/**
 * Central authorization policy for Poultry slaughter processing.
 *
 * The page/routes must use this boundary rather than duplicating permission
 * codes or role checks. hasPermission() remains responsible for:
 *
 * - Poultry subscription entitlement
 * - Poultry Manager role eligibility
 * - Platform Owner / Farm Admin bypass
 * - tenant permission overrides
 * - global permission defaults
 */

if (!function_exists('poultry_slaughter_permission_map')) {
function poultry_slaughter_permission_map(): array
{
    return [
        'view' =>
            'poultry_slaughter',

        'create_batch' =>
            'poultry_slaughter_batch_add',

        'add_processing_expense' =>
            'poultry_slaughter_processing_expense_add',

        'finalize_cost_basis' =>
            'poultry_slaughter_finalize',

        'add_output' =>
            'poultry_slaughter_output_add',
    ];
}
}


if (!function_exists('poultry_slaughter_permission_code')) {
function poultry_slaughter_permission_code(
    string $action
): string {
    $action =
        strtolower(
            trim(
                str_replace(
                    [
                        '-',
                        ' ',
                    ],
                    '_',
                    $action
                )
            )
        );

    $map =
        poultry_slaughter_permission_map();

    if (
        !isset(
            $map[
                $action
            ]
        )
    ) {
        throw new InvalidArgumentException(
            'Unknown Poultry slaughter authorization action.'
        );
    }

    return
        $map[
            $action
        ];
}
}


if (!function_exists('poultry_slaughter_can')) {
function poultry_slaughter_can(
    string $action
): bool {
    return
        hasPermission(
            getUserType(),
            poultry_slaughter_permission_code(
                $action
            )
        );
}
}


if (!function_exists('poultry_slaughter_require')) {
function poultry_slaughter_require(
    string $action
): void {
    ensureAllowed(
        poultry_slaughter_permission_code(
            $action
        )
    );
}
}
