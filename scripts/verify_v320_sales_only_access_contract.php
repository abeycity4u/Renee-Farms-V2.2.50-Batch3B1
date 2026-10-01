<?php

$root = dirname(__DIR__);

$files = [
    'config' => file_get_contents($root . '/config.php'),
    'navbar' => file_get_contents($root . '/navbar.php'),
    'intelligence' => file_get_contents($root . '/management/intelligence.php'),
    'livestock_report' => file_get_contents($root . '/management/poultry_ruminant_report.php'),
    'production_cycles' => file_get_contents($root . '/management/production_cycles.php'),
];

$fail = 0;

$assert = static function (
    bool $condition,
    string $label
) use (&$fail): void {
    if ($condition) {
        echo "PASS: {$label}\n";
        return;
    }

    echo "FAIL: {$label}\n";
    $fail = 1;
};

$assert(
    str_contains(
        $files['config'],
        "current_farm_is_sales_only()"
    )
    &&
    str_contains(
        $files['config'],
        "return 'Sales Only';"
    ),
    'Farm Access label uses canonical Sales-only contract'
);

$assert(
    str_contains(
        $files['config'],
        "return 'Farm Administration';"
    ),
    'Farm Admin identity remains available outside Sales-only workspace'
);

$assert(
    str_contains(
        $files['navbar'],
        '$salesOnlyWorkspace ='
    )
    &&
    str_contains(
        $files['navbar'],
        'current_farm_is_sales_only()'
    ),
    'navbar consumes canonical Sales-only workspace contract'
);

$assert(
    str_contains(
        $files['navbar'],
        '$canViewLivestockReport ='
    )
    &&
    str_contains(
        $files['navbar'],
        '!$salesOnlyWorkspace'
    ),
    'livestock report navigation is excluded from Sales-only'
);

$assert(
    preg_match(
        '/if\s*\(\$canViewReports\).*?management\/reports\.php/s',
        $files['navbar']
    ) === 1,
    'Analytics remains controlled by normal reports permission'
);

$assert(
    preg_match(
        '/if\s*\(\$canViewLivestockReport\).*?poultry_ruminant_report\.php/s',
        $files['navbar']
    ) === 1,
    'Poultry and Ruminant Report has separate livestock navigation gate'
);

$assert(
    preg_match(
        '/\$canViewFarmIntelligence\s*=\s*!\$salesOnlyWorkspace/s',
        $files['navbar']
    ) === 1,
    'Farm Intelligence navigation is excluded from Sales-only'
);

$assert(
    preg_match(
        '/\$canViewProductionCycles\s*=\s*!\$salesOnlyWorkspace/s',
        $files['navbar']
    ) === 1,
    'Production Cycles navigation is excluded from Sales-only'
);

foreach (
    [
        'livestock_report' => 'Poultry and Ruminant Report',
        'production_cycles' => 'Production Cycles',
        'intelligence' => 'Farm Intelligence',
    ]
    as $key => $label
) {
    $assert(
        str_contains(
            $files[$key],
            'current_farm_is_sales_only()'
        )
        &&
        str_contains(
            $files[$key],
            "/no_access.php"
        ),
        "{$label} has direct-access Sales-only guard"
    );
}

$assert(
    !str_contains(
        $files['navbar'],
        '$canViewReports = !$salesOnlyWorkspace'
    ),
    'Sales-only does not suppress Analytics reports permission'
);

if ($fail) {
    exit(1);
}

echo "SALES_ONLY_ACCESS_CONTRACT=PASS\n";
