<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/permission_catalog.php';

/*
 * Central authorization policy for Ruminant slaughter processing.
 *
 * Keep route/page authorization out of ruminant/slaughter_processing.php.
 *
 * Ruminant cost basis freezes automatically on first output receipt, so there
 * is intentionally no separate finalize-cost-basis permission.
 */

if (!function_exists(
    'ruminant_slaughter_permission_map'
)) {
function ruminant_slaughter_permission_map(): array
{
    return [
        'view' =>
            'ruminant_slaughter',

        'create_batch' =>
            'ruminant_slaughter_batch_add',

        'add_processing_expense' =>
            'ruminant_slaughter_processing_expense_add',

        'add_output' =>
            'ruminant_slaughter_output_add',
    ];
}
}


if (!function_exists(
    'ruminant_slaughter_permission_code'
)) {
function ruminant_slaughter_permission_code(
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
        ruminant_slaughter_permission_map();

    if (!isset($map[$action])) {
        throw new InvalidArgumentException(
            'Unknown Ruminant slaughter authorization action.'
        );
    }

    return $map[$action];
}
}


if (!function_exists(
    'ruminant_slaughter_can'
)) {
function ruminant_slaughter_can(
    string $action
): bool {
    return
        hasPermission(
            getUserType(),
            ruminant_slaughter_permission_code(
                $action
            )
        );
}
}


if (!function_exists(
    'ruminant_slaughter_require'
)) {
function ruminant_slaughter_require(
    string $action
): void {
    ensureAllowed(
        ruminant_slaughter_permission_code(
            $action
        )
    );
}
}
