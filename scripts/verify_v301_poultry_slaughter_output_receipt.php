<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$serviceFile =
    $root
    .
    '/lib/poultry_slaughter_service.php';

$sharedFile =
    $root
    .
    '/lib/slaughter_output_inventory.php';

$migrationFile =
    $root
    .
    '/migrations/079_poultry_slaughter_processing_foundation.sql';

$fail =
    static function (
        string $message
    ): void {
        fwrite(
            STDERR,
            "FAIL: {$message}\n"
        );

        exit(1);
    };

$pass =
    static function (
        string $message
    ): void {
        echo
            "PASS: {$message}\n";
    };

foreach (
    [
        $serviceFile,
        $sharedFile,
        $migrationFile,
    ]
    as $file
) {
    if (
        !is_file($file)
        ||
        !is_readable($file)
    ) {
        $fail(
            'Required source is missing: '
            .
            basename($file)
        );
    }
}

$service =
    (string)file_get_contents(
        $serviceFile
    );

$shared =
    (string)file_get_contents(
        $sharedFile
    );

$migration =
    (string)file_get_contents(
        $migrationFile
    );

$start =
    strpos(
        $service,
        'function poultry_slaughter_output_add('
    );

if ($start === false) {
    $fail(
        'Poultry slaughter output receipt function is missing.'
    );
}

/*
 * The output function is intentionally appended as the final service contract.
 */
$outputSlice =
    substr(
        $service,
        $start
    );

$required = [
    "status']\n            !== 'open'",
    'cost_basis_finalized_at',
    'cost_basis_finalized_by',
    'slaughter_output_inventory_allocation(',
    'slaughter_output_inventory_lock_item(',
    "INSERT INTO poultry_slaughter_outputs",
    'slaughter_output_inventory_receive(',
    "UPDATE poultry_slaughter_outputs",
    'stock_transaction_id IS NULL',
    'cost_basis_provenance_fingerprint',
];

foreach (
    $required
    as $needle
) {
    if (
        strpos(
            $outputSlice,
            $needle
        ) === false
    ) {
        $fail(
            'Poultry output receipt missing contract: '
            .
            $needle
        );
    }
}

$pass(
    'Poultry output receipt requires open/finalized batch and canonical shared Inventory boundary'
);


if (
    strpos(
        $outputSlice,
        'production_population_projection_sync('
    ) !== false
    ||
    strpos(
        $outputSlice,
        'production_population_record_movement('
    ) !== false
) {
    $fail(
        'Processed output receipt must not change live population.'
    );
}

$pass(
    'Processed output receipt has no second live-population mutation'
);


if (
    strpos(
        $outputSlice,
        'poultry_expense_entry_create('
    ) !== false
) {
    $fail(
        'Processed output receipt must not create or change processing expenses.'
    );
}

if (
    preg_match(
        '/INSERT\s+INTO\s+sales_records/i',
        $outputSlice
    )
) {
    $fail(
        'Processed output receipt must not create Sales revenue.'
    );
}

$pass(
    'Processed output receipt remains separate from Expenses and Sales'
);


if (
    strpos(
        $outputSlice,
        'stock_apply_movement('
    ) !== false
) {
    $fail(
        'Poultry output receipt must delegate physical stock to the shared slaughter-output boundary.'
    );
}

if (
    strpos(
        $shared,
        'stock_apply_movement('
    ) === false
) {
    $fail(
        'Shared slaughter-output boundary no longer delegates to canonical stock_service.'
    );
}

$pass(
    'Canonical stock_service remains the single physical stock writer'
);


if (
    strpos(
        $migration,
        'UNIQUE KEY uniq_poultry_slaughter_output_item'
    ) === false
    ||
    strpos(
        $migration,
        'UNIQUE KEY uniq_poultry_slaughter_output_stock_tx'
    ) === false
    ||
    strpos(
        $migration,
        'remaining_quantity >= 0'
    ) === false
    ||
    strpos(
        $migration,
        'remaining_quantity <= initial_quantity'
    ) === false
) {
    $fail(
        'Migration 079 is missing Poultry output-lot conservation constraints.'
    );
}

$pass(
    'Migration 079 preserves source-lot quantity and stock-transaction identity'
);


require_once $serviceFile;

if (
    !function_exists(
        'poultry_slaughter_output_add'
    )
) {
    $fail(
        'Poultry output receipt function did not load.'
    );
}

$quote =
    slaughter_output_inventory_allocation(
        123.45,
        75.0000,
        92.59,
        10.00,
        25.0000
    );

if (
    abs(
        (float)$quote[
            'new_percent'
        ]
        -
        100.0000
    ) > 0.0001
    ||
    abs(
        (float)$quote[
            'allocated_cost'
        ]
        -
        30.86
    ) > 0.0001
) {
    $fail(
        'Final Poultry output allocation did not absorb the exact remaining batch value.'
    );
}

$pass(
    'Final output allocation conserves exact remaining frozen batch value'
);


$movementErrors =
    inventory_category_role_stock_movement_errors(
        inventory_category_slaughter_output_role(),
        'received',
        'poultry_slaughter_output',
        1
    );

if ($movementErrors !== []) {
    $fail(
        'Central Inventory role policy rejected sourced Poultry slaughter output.'
    );
}

$pass(
    'Central Inventory role policy accepts Poultry slaughter-output provenance'
);


echo
    "PASS: Stage 14E-3D Poultry slaughter output receipt contract\n";
