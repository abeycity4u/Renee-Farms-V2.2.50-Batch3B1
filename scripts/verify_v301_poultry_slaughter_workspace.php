<?php

declare(strict_types=1);

$root =
    dirname(
        __DIR__
    );

$files = [
    'page' =>
        $root
        .
        '/poultry/slaughter_processing.php',

    'workspace' =>
        $root
        .
        '/lib/poultry_slaughter_workspace.php',

    'service' =>
        $root
        .
        '/lib/poultry_slaughter_service.php',

    'permissions' =>
        $root
        .
        '/includes/poultry_slaughter_permissions.php',

    'css' =>
        $root
        .
        '/assets/css/poultry-slaughter-processing.css',

    'js' =>
        $root
        .
        '/assets/js/poultry-slaughter-processing.js',

    'navbar' =>
        $root
        .
        '/navbar.php',
];

$source = [];

foreach (
    $files
    as $key => $path
) {
    if (
        !is_file(
            $path
        )
        ||
        !is_readable(
            $path
        )
    ) {
        fwrite(
            STDERR,
            "Missing workspace source: {$key}\n"
        );

        exit(1);
    }

    $source[
        $key
    ] =
        (string)file_get_contents(
            $path
        );
}

$checks = 0;
$failed = 0;

$check =
    static function (
        string $label,
        bool $ok
    ) use (
        &$checks,
        &$failed
    ): void {
        $checks++;

        echo
            ($ok ? 'PASS: ' : 'FAIL: ')
            .
            $label
            .
            PHP_EOL;

        if (!$ok) {
            $failed++;
        }
    };

$page =
    $source[
        'page'
    ];

$workspace =
    $source[
        'workspace'
    ];


$check(
    'Workspace route loads the central Slaughter permission boundary',
    str_contains(
        $page,
        'includes/poultry_slaughter_permissions.php'
    )
    &&
    str_contains(
        $page,
        "poultry_slaughter_require(\n    'view'"
    )
);


foreach (
    [
        'create_batch',
        'add_processing_expense',
        'finalize_cost_basis',
        'add_output',
    ]
    as $action
) {
    $check(
        "Workspace delegates {$action} authorization centrally",
        str_contains(
            $page,
            "poultry_slaughter_require(\n                        '{$action}'"
        )
    );
}


$check(
    'Every workspace POST passes CSRF validation before mutation dispatch',
    str_contains(
        $page,
        "REQUEST_METHOD"
    )
    &&
    str_contains(
        $page,
        'verify_csrf_token('
    )
    &&
    substr_count(
        $page,
        'csrf_field()'
    ) >= 4
);


foreach (
    [
        'poultry_slaughter_batch_create(',
        'poultry_slaughter_processing_expense_add(',
        'poultry_slaughter_cost_basis_finalize(',
        'poultry_slaughter_output_add(',
    ]
    as $call
) {
    $check(
        "Workspace delegates mutation through {$call}",
        substr_count(
            $page,
            $call
        ) === 1
    );
}


$check(
    'Workspace page contains no SQL persistence',
    !preg_match(
        '/\bINSERT\s+INTO\b|\bUPDATE\s+[A-Za-z0-9_`]+\s+SET\b|\bDELETE\s+FROM\b/i',
        $page
    )
);


$check(
    'Workspace page contains no direct population mutation',
    !str_contains(
        $page,
        'production_population_'
    )
    &&
    !str_contains(
        $page,
        'sale_population_effect'
    )
);


$check(
    'Workspace page contains no direct Inventory mutation',
    !str_contains(
        $page,
        'stock_apply_movement('
    )
    &&
    !str_contains(
        $page,
        'stock_reverse_transaction('
    )
    &&
    !str_contains(
        $page,
        'slaughter_output_inventory_receive('
    )
);


$check(
    'Workspace page does not create financial Sales',
    !preg_match(
        '/INSERT\s+INTO\s+sales_records/i',
        $page
    )
    &&
    !str_contains(
        $page,
        'sale_population_effect_sync('
    )
);


$check(
    'Workspace does not open a second transaction around domain service calls',
    !preg_match(
        '/beginTransaction|commit\s*\(|rollBack\s*\(|inTransaction\s*\(/i',
        $page
    )
);


$check(
    'Read model is SELECT-only',
    !preg_match(
        '/\bINSERT\s+INTO\b|\bUPDATE\s+[A-Za-z0-9_`]+\s+SET\b|\bDELETE\s+FROM\b/i',
        $workspace
    )
    &&
    str_contains(
        $workspace,
        'SELECT'
    )
);


$check(
    'Read model scopes all workspace collections to tenant farm',
    substr_count(
        $workspace,
        'farm_id = ?'
    ) >= 4
);


$check(
    'Output-item picker uses canonical slaughter_output category role',
    str_contains(
        $workspace,
        "inventory_role"
    )
    &&
    str_contains(
        $workspace,
        "'slaughter_output'"
    )
    &&
    str_contains(
        $workspace,
        "si.is_active = 1"
    )
    &&
    str_contains(
        $workspace,
        "si.farm_type IN ('poultry','both')"
    )
);


$check(
    'Processing expense read model uses immutable batch-expense snapshots',
    str_contains(
        $workspace,
        'poultry_slaughter_batch_expenses'
    )
    &&
    str_contains(
        $workspace,
        'amount_snapshot'
    )
);


$check(
    'Output read model exposes frozen Inventory and cost provenance',
    str_contains(
        $workspace,
        'stock_transaction_id'
    )
    &&
    str_contains(
        $workspace,
        'remaining_quantity'
    )
    &&
    str_contains(
        $workspace,
        'allocated_cost'
    )
    &&
    str_contains(
        $workspace,
        'unit_cost_snapshot'
    )
);


$check(
    'Batch workspace discloses processed sale activity without mutating Sales',
    str_contains(
        $workspace,
        'poultry_slaughter_sale_allocations'
    )
    &&
    str_contains(
        $workspace,
        'is_active = 1'
    )
);


$check(
    'Processed sales remain linked to canonical Sales Records',
    substr_count(
        $page,
        '/management/sales_records.php'
    ) >= 2
    &&
    str_contains(
        $page,
        'Slaughter is not a sale.'
    )
);


$check(
    'Workspace preserves failed form input on same-page validation errors',
    str_contains(
        $page,
        '$posted'
    )
    &&
    str_contains(
        $page,
        '$formError'
    )
    &&
    str_contains(
        $page,
        'PoultrySlaughterException'
    )
    &&
    str_contains(
        $page,
        'InvalidArgumentException'
    )
);


$check(
    'Unexpected failures do not expose raw database details to browser',
    str_contains(
        $page,
        'catch (Throwable $e)'
    )
    &&
    str_contains(
        $page,
        'No database details were exposed.'
    )
    &&
    str_contains(
        $page,
        'error_log('
    )
);


$check(
    'Batch and processing-expense idempotency tokens are explicit',
    str_contains(
        $page,
        'batch_request_token'
    )
    &&
    str_contains(
        $page,
        'expense_request_token'
    )
    &&
    substr_count(
        $page,
        'random_bytes('
    ) === 2
);


$check(
    'UI uses external CSS and JS assets',
    str_contains(
        $page,
        '/assets/css/poultry-slaughter-processing.css'
    )
    &&
    str_contains(
        $page,
        '/assets/js/poultry-slaughter-processing.js'
    )
);


$check(
    'Workspace page has no inline browser event handlers',
    !preg_match(
        '/\bon(?:click|change|submit|input|load)\s*=/i',
        $page
    )
);


$check(
    'Finalization uses the platform-wide styled form confirmation contract',
    str_contains(
        $page,
        'data-confirm="Finalize this slaughter cost basis? Processing costs will be frozen before processed output Inventory is received."'
    )
    &&
    str_contains(
        $page,
        'data-confirm-title="Finalize slaughter cost basis?"'
    )
    &&
    str_contains(
        $page,
        'data-confirm-button="Finalize Cost Basis"'
    )
    &&
    str_contains(
        $page,
        'data-confirm-tone="warning"'
    )
    &&
    !str_contains(
        $page,
        'data-confirm-finalize'
    )
);

$check(
    'Workspace JS owns batch-picker behavior without a page-local native confirmation',
    str_contains(
        $source[
            'js'
        ],
        '[data-slaughter-batch-picker]'
    )
    &&
    !str_contains(
        $source[
            'js'
        ],
        '[data-confirm-finalize]'
    )
    &&
    !str_contains(
        $source[
            'js'
        ],
        'window.confirm('
    )
);


$check(
    'Verified workspace is discoverable through the permission-aware Poultry menu',
    str_contains(
        $source[
            'navbar'
        ],
        "\$canViewPoultrySlaughter = \$poultryEntitled && \$navHas('poultry_slaughter');"
    )
    &&
    str_contains(
        $source[
            'navbar'
        ],
        '<?php if ($canViewPoultrySlaughter): ?>'
    )
    &&
    str_contains(
        $source[
            'navbar'
        ],
        '/poultry/slaughter_processing.php'
    )
);


echo PHP_EOL;
echo 'CHECK_COUNT=' . $checks . PHP_EOL;
echo 'FAILED_COUNT=' . $failed . PHP_EOL;
echo 'DATABASE_CONNECTION_USED=NO' . PHP_EOL;
echo 'DATABASE_WRITE_PERFORMED=NO' . PHP_EOL;
echo 'RESULT='
    .
    (
        $failed === 0
            ? 'PASS'
            : 'FAIL'
    )
    .
    PHP_EOL;

exit(
    $failed === 0
        ? 0
        : 1
);
