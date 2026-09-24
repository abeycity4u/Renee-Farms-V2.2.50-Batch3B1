<?php

declare(strict_types=1);

$root =
    dirname(
        __DIR__
    );

$paths = [
    'navbar' =>
        $root
        .
        '/navbar.php',

    'prepaint' =>
        $root
        .
        '/includes/permission_prepaint.php',

    'prepaint_css' =>
        $root
        .
        '/assets/css/permission-prepaint.css',

    'route' =>
        $root
        .
        '/poultry/slaughter_processing.php',

    'permissions' =>
        $root
        .
        '/includes/poultry_slaughter_permissions.php',

    'catalog' =>
        $root
        .
        '/includes/permission_catalog.php',
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
            "Missing discoverability source: {$key}\n"
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


$navbar =
    $source[
        'navbar'
    ];

$prepaint =
    $source[
        'prepaint'
    ];

$prepaintCss =
    $source[
        'prepaint_css'
    ];

$route =
    $source[
        'route'
    ];


$check(
    'Poultry Slaughter navigation requires Poultry entitlement and View permission',
    str_contains(
        $navbar,
        "\$canViewPoultrySlaughter = \$poultryEntitled && \$navHas('poultry_slaughter');"
    )
);


$check(
    'Poultry parent menu includes Slaughter child visibility',
    str_contains(
        $navbar,
        '$canViewPoultrySlaughter || $canViewPoultryExpenses'
    )
);


$check(
    'Poultry Slaughter link is conditionally rendered',
    str_contains(
        $navbar,
        '<?php if ($canViewPoultrySlaughter): ?>'
    )
    &&
    str_contains(
        $navbar,
        '/poultry/slaughter_processing.php'
    )
    &&
    str_contains(
        $navbar,
        'Slaughter Processing'
    )
);


$check(
    'Poultry Slaughter navbar link occurs exactly once',
    substr_count(
        $navbar,
        '/poultry/slaughter_processing.php'
    ) === 1
);


$check(
    'Poultry Slaughter does not use delegated Poultry expense entitlement',
    !preg_match(
        '/canViewPoultrySlaughter\s*=\s*\$poultryExpenseEntitled/',
        $navbar
    )
);


$check(
    'Pre-paint navigation map uses the same View permission',
    str_contains(
        $prepaint,
        "'/poultry/slaughter_processing.php' => 'poultry_slaughter'"
    )
);


$check(
    'Pre-paint stylesheet hides unauthorized Slaughter link before paint',
    str_contains(
        $prepaintCss,
        'hide-nav-poultry-slaughter'
    )
    &&
    str_contains(
        $prepaintCss,
        'a[href$="/poultry/slaughter_processing.php"]'
    )
);


$check(
    'Route itself remains protected by central View authorization',
    str_contains(
        $route,
        'includes/poultry_slaughter_permissions.php'
    )
    &&
    str_contains(
        $route,
        "poultry_slaughter_require(\n    'view'"
    )
);


$check(
    'Permission helper keeps route authorization central',
    str_contains(
        $source[
            'permissions'
        ],
        "'view' =>"
    )
    &&
    str_contains(
        $source[
            'permissions'
        ],
        "'poultry_slaughter'"
    )
    &&
    str_contains(
        $source[
            'permissions'
        ],
        'ensureAllowed('
    )
);


$check(
    'View permission remains Poultry Manager specific in catalog',
    preg_match(
        "/'poultry_slaughter'\\s*=>\\s*\\[[^\\n]*'roles'\\s*=>\\s*\\['poultry_manager'\\]/",
        $source[
            'catalog'
        ]
    ) === 1
);


$check(
    'No inline event-handler regression introduced into navbar',
    !preg_match(
        '/\bon(?:click|change|submit|input|load)\s*=/i',
        $navbar
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
