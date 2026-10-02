<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$navbar = file_get_contents(
    $root . '/navbar.php'
);

$failures = 0;

$check = static function (
    bool $condition,
    string $label
) use (&$failures): void {
    if ($condition) {
        echo "PASS: {$label}\n";
        return;
    }

    echo "FAIL: {$label}\n";
    $failures++;
};

$check(
    str_contains(
        $navbar,
        "\$canViewPoultryExpenses = \$poultryExpenseEntitled && \$navHas('poultry_expenses');"
    ),
    'Poultry Expenses navbar uses canonical View permission'
);

$check(
    !str_contains(
        $navbar,
        "\$navHas('poultry_layer_expenses')"
    ),
    'navbar no longer checks legacy Layer expense permission'
);

$check(
    !str_contains(
        $navbar,
        "\$navHas('poultry_broiler_expenses')"
    ),
    'navbar no longer checks legacy Broiler expense permission'
);

$check(
    str_contains(
        $navbar,
        "\$canViewRuminantSlaughter = \$ruminantEntitled && \$navHas('ruminant_slaughter');"
    ),
    'Ruminant Slaughter navbar uses dedicated View permission'
);

$check(
    str_contains(
        $navbar,
        '<?php if ($canViewRuminantSlaughter): ?>'
    ),
    'Ruminant Slaughter link is gated independently'
);

$check(
    substr_count(
        $navbar,
        '/ruminant/slaughter_processing.php'
    ) === 1,
    'Ruminant Slaughter navigation has one canonical link'
);

$check(
    str_contains(
        $navbar,
        '|| $canViewRuminantSlaughter'
    ),
    'Ruminant top-level menu includes Slaughter visibility'
);

$check(
    str_contains(
        $navbar,
        '|| $canViewPoultryExpenses'
    ),
    'Poultry top-level menu derives from canonical child visibility'
);

echo "FAILURES={$failures}\n";

if ($failures === 0) {
    echo "NAVIGATION_PERMISSION_PARITY=PASS\n";
    exit(0);
}

echo "NAVIGATION_PERMISSION_PARITY=FAIL\n";
exit(1);
