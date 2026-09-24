<?php

declare(strict_types=1);

$root =
    dirname(
        __DIR__
    );

$paths = [
    'catalog' =>
        $root
        .
        '/includes/permission_catalog.php',

    'helper' =>
        $root
        .
        '/includes/poultry_slaughter_permissions.php',

    'functions' =>
        $root
        .
        '/includes/functions.php',

    'admin_page' =>
        $root
        .
        '/admin/permissions.php',

    'admin_save' =>
        $root
        .
        '/admin/permissions_save.php',

    'navbar' =>
        $root
        .
        '/navbar.php',

    'migration' =>
        $root
        .
        '/migrations/080_poultry_slaughter_permission_defaults.sql',
];

$source = [];

foreach (
    $paths
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
            "FAIL: Missing Poultry slaughter permission source: {$key}\n"
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


$codes = [
    'poultry_slaughter',
    'poultry_slaughter_batch_add',
    'poultry_slaughter_processing_expense_add',
    'poultry_slaughter_finalize',
    'poultry_slaughter_output_add',
];


foreach (
    $codes
    as $code
) {
    $check(
        "Catalog contains {$code}",
        str_contains(
            $source[
                'catalog'
            ],
            "'{$code}'"
        )
    );
}


$check(
    'All five slaughter permissions belong only to Poultry Manager',
    preg_match_all(
        "/'poultry_slaughter(?:_[a-z_]+)?'\\s*=>\\s*\\[[^\\n]+\\]/",
        $source[
            'catalog'
        ],
        $catalogRows
    ) === 5
    &&
    count(
        array_filter(
            $catalogRows[
                0
            ],
            static fn(
                string $row
            ): bool =>
                str_contains(
                    $row,
                    "'roles' => ['poultry_manager']"
                )
                &&
                !str_contains(
                    $row,
                    'sales_rep'
                )
                &&
                !str_contains(
                    $row,
                    'ruminant_manager'
                )
        )
    ) === 5
);


$check(
    'Central action map owns all page-level permission codes',
    str_contains(
        $source[
            'helper'
        ],
        'function poultry_slaughter_permission_map('
    )
    &&
    str_contains(
        $source[
            'helper'
        ],
        'function poultry_slaughter_permission_code('
    )
    &&
    str_contains(
        $source[
            'helper'
        ],
        'function poultry_slaughter_can('
    )
    &&
    str_contains(
        $source[
            'helper'
        ],
        'function poultry_slaughter_require('
    )
);


foreach (
    [
        "'view' =>",
        "'create_batch' =>",
        "'add_processing_expense' =>",
        "'finalize_cost_basis' =>",
        "'add_output' =>",
    ]
    as $needle
) {
    $check(
        "Central action map contains {$needle}",
        str_contains(
            $source[
                'helper'
            ],
            $needle
        )
    );
}


$check(
    'Authorization helper delegates to canonical permission runtime',
    str_contains(
        $source[
            'helper'
        ],
        'hasPermission('
    )
    &&
    str_contains(
        $source[
            'helper'
        ],
        'ensureAllowed('
    )
    &&
    !str_contains(
        $source[
            'helper'
        ],
        "hasRole('poultry_manager'"
    )
);


$check(
    'Canonical hasPermission retains Poultry entitlement and role boundary',
    str_contains(
        $source[
            'functions'
        ],
        "str_starts_with(\$module, 'poultry') && !farmHasModule('poultry')"
    )
    &&
    str_contains(
        $source[
            'functions'
        ],
        "str_starts_with(\$module, 'poultry') && !hasRole('poultry_manager')"
    )
);


$check(
    'Permission runtime retains tenant-over-global precedence',
    str_contains(
        $source[
            'functions'
        ],
        'farm_id IN (0, ?)'
    )
    &&
    str_contains(
        $source[
            'functions'
        ],
        'ORDER BY farm_id DESC'
    )
);


$check(
    'Permission admin UI loads global defaults before tenant overrides',
    str_contains(
        $source[
            'admin_page'
        ],
        'farm_id IN (0, ?)'
    )
    &&
    str_contains(
        $source[
            'admin_page'
        ],
        'ORDER BY farm_id ASC'
    )
);


$check(
    'Permission admin save consumes the canonical catalog',
    str_contains(
        $source[
            'admin_save'
        ],
        'permission_catalog_codes()'
    )
    &&
    str_contains(
        $source[
            'admin_save'
        ],
        'permission_catalog_applicable('
    )
    &&
    str_contains(
        $source[
            'admin_save'
        ],
        'ON DUPLICATE KEY UPDATE allowed = VALUES(allowed)'
    )
);


foreach (
    $codes
    as $code
) {
    $needle =
        "(0, 'poultry_manager', '{$code}', 1)";

    $check(
        "Migration seeds global Poultry Manager default {$code}",
        str_contains(
            $source[
                'migration'
            ],
            $needle
        )
    );
}


$check(
    'Migration seeds exactly five Poultry slaughter default rows',
    preg_match_all(
        "/\\(0,\\s*'poultry_manager',\\s*'poultry_slaughter[^']*',\\s*1\\)/",
        $source[
            'migration'
        ]
    ) === 5
);


$check(
    'Migration does not grant slaughter defaults to unrelated specialist roles',
    !preg_match(
        "/\\(0,\\s*'(?:sales_rep|ruminant_manager|viewer)',\\s*'poultry_slaughter/",
        $source[
            'migration'
        ]
    )
);


$check(
    'Migration records its schema marker',
    str_contains(
        $source[
            'migration'
        ],
        "'080_poultry_slaughter_permission_defaults.sql'"
    )
);


$check(
    'Verified Poultry Slaughter route is discoverable only through its View capability',
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


$check(
    'Permission helper owns no SQL persistence',
    !preg_match(
        '/\\b(?:INSERT|UPDATE|DELETE|SELECT)\\b/i',
        $source[
            'helper'
        ]
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
