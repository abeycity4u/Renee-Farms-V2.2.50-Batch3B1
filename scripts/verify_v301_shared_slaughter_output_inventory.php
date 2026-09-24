<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$roleFile =
    $root
    .
    '/lib/inventory_category_role.php';

$sharedFile =
    $root
    .
    '/lib/slaughter_output_inventory.php';

$ruminantFile =
    $root
    .
    '/lib/ruminant_slaughter_processing.php';

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
        $roleFile,
        $sharedFile,
        $ruminantFile,
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

$roleSource =
    (string)file_get_contents(
        $roleFile
    );

$sharedSource =
    (string)file_get_contents(
        $sharedFile
    );

$ruminantSource =
    (string)file_get_contents(
        $ruminantFile
    );

require_once $roleFile;
require_once $sharedFile;


if (
    inventory_category_role_contract_errors(
        inventory_category_slaughter_output_role(),
        'ruminant',
        'other_stock'
    ) !== []
    ||
    inventory_category_role_contract_errors(
        inventory_category_slaughter_output_role(),
        'poultry',
        'other_stock'
    ) !== []
    ||
    inventory_category_role_contract_errors(
        inventory_category_slaughter_output_role(),
        'both',
        'other_stock'
    ) === []
) {
    $fail(
        'Shared Slaughter Output category contract is not species-safe.'
    );
}

$pass(
    'Shared Slaughter Output category contract accepts Poultry and Ruminant only'
);


if (
    inventory_category_role_item_contract_errors(
        inventory_category_slaughter_output_role(),
        'poultry',
        'general'
    ) !== []
    ||
    inventory_category_role_item_contract_errors(
        inventory_category_slaughter_output_role(),
        'ruminant',
        'general'
    ) !== []
    ||
    inventory_category_role_item_contract_errors(
        inventory_category_slaughter_output_role(),
        'both',
        'general'
    ) !== []
    ||
    inventory_category_role_item_contract_errors(
        inventory_category_slaughter_output_role(),
        'poultry',
        'feed'
    ) === []
) {
    $fail(
        'Shared Slaughter Output item contract is invalid.'
    );
}

$pass(
    'Shared Slaughter Output item contract preserves general-stock boundary'
);


$movementTests = [
    [
        'received',
        'ruminant_slaughter_output',
        true,
    ],
    [
        'received',
        'poultry_slaughter_output',
        true,
    ],
    [
        'received',
        'ruminant_slaughter_sale_reversal',
        true,
    ],
    [
        'received',
        'poultry_slaughter_sale_reversal',
        true,
    ],
    [
        'used',
        'ruminant_slaughter_sale',
        true,
    ],
    [
        'used',
        'poultry_slaughter_sale',
        true,
    ],
    [
        'received',
        'inventory_update',
        false,
    ],
    [
        'used',
        'inventory_update',
        false,
    ],
];

foreach (
    $movementTests
    as [
        $movement,
        $source,
        $allowed,
    ]
) {
    $errors =
        inventory_category_role_stock_movement_errors(
            inventory_category_slaughter_output_role(),
            $movement,
            $source,
            1
        );

    if (
        ($allowed && $errors !== [])
        ||
        (!$allowed && $errors === [])
    ) {
        $fail(
            "Unexpected slaughter-output movement policy for {$movement}/{$source}."
        );
    }
}

$pass(
    'Shared provenance gate preserves Ruminant and adds Poultry source ownership'
);


if (
    slaughter_output_inventory_receipt_source(
        'ruminant'
    ) !== 'ruminant_slaughter_output'
    ||
    slaughter_output_inventory_receipt_source(
        'poultry'
    ) !== 'poultry_slaughter_output'
) {
    $fail(
        'Shared receipt-source mapping is invalid.'
    );
}

$pass(
    'Shared receipt-source mapping is domain scoped'
);


$first =
    slaughter_output_inventory_allocation(
        100.00,
        0.0,
        0.0,
        10.0,
        25.0
    );

if (
    abs(
        (float)$first[
            'allocated_cost'
        ]
        -
        25.00
    ) > 0.0001
    ||
    abs(
        (float)$first[
            'unit_cost_snapshot'
        ]
        -
        2.5000
    ) > 0.0001
) {
    $fail(
        'Shared proportional allocation calculation failed.'
    );
}

$final =
    slaughter_output_inventory_allocation(
        100.00,
        66.6667,
        66.67,
        10.0,
        33.3333
    );

if (
    abs(
        (float)$final[
            'allocated_cost'
        ]
        -
        33.33
    ) > 0.0001
    ||
    abs(
        (float)$final[
            'new_percent'
        ]
        -
        100.0000
    ) > 0.0001
) {
    $fail(
        'Final Slaughter Output allocation did not absorb remaining rounding.'
    );
}

$pass(
    'Shared allocation conserves proportional and final rounding value'
);


if (
    !str_contains(
        $ruminantSource,
        'slaughter_output_inventory_allocation('
    )
    ||
    !str_contains(
        $ruminantSource,
        'slaughter_output_inventory_lock_item('
    )
    ||
    !str_contains(
        $ruminantSource,
        'slaughter_output_inventory_receive('
    )
) {
    $fail(
        'Ruminant output writer is not delegated to the shared Inventory boundary.'
    );
}

if (
    str_contains(
        $ruminantSource,
        'stock_apply_movement('
    )
) {
    $fail(
        'Ruminant output writer still contains a duplicate direct stock receipt path.'
    );
}

$pass(
    'Ruminant output writer delegates common Inventory behavior centrally'
);


if (
    !str_contains(
        $sharedSource,
        'stock_apply_movement('
    )
    ||
    preg_match(
        '/(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+stock_transactions/i',
        $sharedSource
    )
) {
    $fail(
        'Shared slaughter-output boundary does not delegate stock exclusively to stock_service.php.'
    );
}

$pass(
    'Canonical stock_service remains the only physical stock writer'
);


echo
    "PASS: Stage 14E-3A shared slaughter-output Inventory boundary\n";
