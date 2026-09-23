<?php

$root = dirname(__DIR__);

$paths = [
    'navigation' =>
        $root . '/lib/shared_allocation_navigation.php',

    'poultry_economics' =>
        $root . '/lib/poultry_rearing_economics.php',

    'poultry_cycle' =>
        $root . '/management/poultry_cycle.php',

    'ruminant_shared' =>
        $root . '/lib/ruminant_shared_cost_economics.php',

    'ruminant_animal_economics' =>
        $root . '/lib/ruminant_animal_economics.php',

    'animal_view' =>
        $root . '/ruminant/animal_view.php',

    'profitability' =>
        $root . '/management/profitability.php',
];

$source = [];

foreach ($paths as $key => $path) {
    if (!is_file($path)) {
        fwrite(
            STDERR,
            "Missing source: {$path}\n"
        );
        exit(1);
    }

    $source[$key] =
        file_get_contents($path);
}

$checks = [];

$check =
    static function (
        bool $ok,
        string $label
    ) use (&$checks): void {
        $checks[] = [
            'ok' => $ok,
            'label' => $label,
        ];
    };

$has =
    static fn(
        string $text,
        string $needle
    ): bool =>
        strpos($text, $needle) !== false;

$navigation =
    $source['navigation'];

$check(
    $has(
        $navigation,
        'financial_allocation_workspace.php'
    )
    &&
    $has(
        $navigation,
        'stock_consumption_allocation_workspace.php'
    ),
    'navigation delegates to canonical workspace stacks'
);

$check(
    $has(
        $navigation,
        'financial_allocation_workspace_url'
    ),
    'expense sources use canonical allocation URL'
);

$check(
    $has(
        $navigation,
        'stock_consumption_allocation_workspace_action_state'
    ),
    'stock sources reuse canonical action-state policy'
);

$check(
    $has(
        $navigation,
        'financial_allocation_workspace_can_access'
    )
    &&
    $has(
        $navigation,
        'stock_consumption_allocation_workspace_can_manage'
    ),
    'navigation remains permission aware'
);

$upper =
    strtoupper($navigation);

$check(
    strpos($upper, 'INSERT INTO') === false
    &&
    strpos($upper, 'UPDATE ') === false
    &&
    strpos($upper, 'DELETE FROM') === false,
    'navigation helper is read-only'
);

$check(
    $has(
        $source['poultry_economics'],
        'unallocated_shared_expense_rows'
    )
    &&
    $has(
        $source['poultry_economics'],
        "'unallocated_amount'"
    ),
    'Poultry economics exposes unresolved source parents'
);

$check(
    $has(
        $source['poultry_cycle'],
        'shared_allocation_navigation_actions'
    )
    &&
    $has(
        $source['poultry_cycle'],
        "\$action['url']"
    )
    &&
    $has(
        $source['poultry_cycle'],
        "\$action['action_label']"
    ),
    'Poultry warning renders exact resolved workspace actions'
);

$check(
    $has(
        $source['ruminant_shared'],
        'uncovered_shared_cost_rows'
    )
    &&
    $has(
        $source['ruminant_shared'],
        "'uncovered_amount'"
    ),
    'Ruminant economics preserves uncovered sources'
);

$check(
    $has(
        $source['ruminant_animal_economics'],
        "'uncovered_shared_cost_rows'"
    ),
    'animal economics passes uncovered source rows'
);

$check(
    $has(
        $source['animal_view'],
        'shared_allocation_navigation_actions'
    )
    &&
    $has(
        $source['animal_view'],
        "\$action['url']"
    ),
    'Ruminant warning renders exact source workspace actions'
);

$check(
    $has(
        $source['animal_view'],
        'Review cycle membership'
    )
    &&
    $has(
        $source['animal_view'],
        '#cycle-membership'
    ),
    'Ruminant warning links to cycle-membership correction'
);

$profitability =
    $source['profitability'];

$check(
    $has(
        $profitability,
        'Review exact allocation sources'
    )
    &&
    $has(
        $profitability,
        '#unallocated-shared-balances'
    ),
    'Profitability aggregate warning leads to source queue'
);

$check(
    $has(
        $profitability,
        'unallocated-shared-source-table'
    )
    &&
    $has(
        $profitability,
        'Review exact sources'
    ),
    'Profitability summary cards lead to exact-source table'
);

/*
 * Do not require one exact PHP array formatting shape.
 * Validate the actual rendering contract instead.
 */
$check(
    $has(
        $profitability,
        '$allocationUrl'
    )
    &&
    $has(
        $profitability,
        'href="<?php echo htmlspecialchars('
    ),
    'Profitability renders allocation URLs as links'
);

$check(
    $has(
        $profitability,
        'Open shared-expense allocation workspace'
    )
    &&
    $has(
        $profitability,
        'Open consumed-stock allocation workspace'
    )
    &&
    $has(
        $profitability,
        'Open shared-revenue allocation workspace'
    ),
    'Profitability preserves all exact workspace action types'
);

$check(
    $has(
        $profitability,
        'Click an actionable status badge'
    ),
    'Profitability explains actionable source navigation'
);

$passed = 0;
$failed = 0;

foreach ($checks as $result) {
    if ($result['ok']) {
        $passed++;
    } else {
        $failed++;

        fwrite(
            STDERR,
            'FAIL: '
            . $result['label']
            . PHP_EOL
        );
    }
}

echo
    'SHARED_ALLOCATION_NAVIGATION='
    . (
        $failed === 0
            ? 'PASS'
            : 'FAIL'
    )
    . ' CHECKS='
    . count($checks)
    . ' FAILED='
    . $failed
    . PHP_EOL;

exit(
    $failed === 0
        ? 0
        : 1
);
