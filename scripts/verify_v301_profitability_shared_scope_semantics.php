<?php

/**
 * V3.0.1 Profitability Shared Operation scope semantics.
 *
 * Source-only verifier.
 * No database connection.
 * No database writes.
 */

$root = dirname(__DIR__);

$stock =
    file_get_contents(
        $root
        . '/lib/stock_consumption_economics.php'
    );

$page =
    file_get_contents(
        $root
        . '/management/profitability.php'
    );

$js =
    file_get_contents(
        $root
        . '/assets/js/management-profitability.js'
    );

if (
    $stock === false
    || $page === false
    || $js === false
) {
    echo "RESULT=FAIL\n";
    echo "FAILED=SOURCE_LOAD\n";
    exit(1);
}

$checks = [];

$check = static function (
    string $name,
    bool $passed
) use (&$checks): void {
    $checks[$name] = $passed;
};

$check(
    'SHARED_NO_LONGER_COLLAPSES_TO_MODULE_SCOPE',
    strpos(
        $stock,
        "\$productionType === 'shared'"
    ) === false
);

$check(
    'ONLY_ALL_NORMALIZES_TO_EMPTY_PRODUCTION_SCOPE',
    strpos(
        $stock,
        "if (\$productionType === 'all')"
    ) !== false
);

$check(
    'SHARED_DOCUMENTED_AS_SOURCE_ATTRIBUTION',
    strpos(
        $stock,
        '"shared" is a real production/source attribution'
    ) !== false
);

$check(
    'UNALLOCATED_DOCUMENTED_AS_STATE_NOT_TYPE',
    strpos(
        $stock,
        'Unallocated remainder is an allocation state, not a production type.'
    ) !== false
);

$check(
    'POULTRY_LABEL_NO_LONGER_CONFLATES_UNALLOCATED',
    strpos(
        $js,
        "shared:'Shared Poultry / Other Poultry'"
    ) !== false
    &&
    strpos(
        $js,
        "Shared / Unallocated Poultry"
    ) === false
);

$check(
    'RUMINANT_LABEL_NO_LONGER_CONFLATES_UNALLOCATED',
    strpos(
        $js,
        "shared:'Shared Ruminant / Other Ruminant'"
    ) !== false
    &&
    strpos(
        $js,
        "Shared / Unallocated Ruminant"
    ) === false
);

$check(
    'SHARED_CYCLE_SELECTOR_EXPLAINS_NO_SPECIFIC_CYCLE',
    strpos(
        $js,
        "production === 'shared'"
    ) !== false
    &&
    strpos(
        $js,
        "'No specific cycle'"
    ) !== false
);

$check(
    'PAGE_EXPLAINS_SHARED_IS_NOT_UNALLOCATED',
    strpos(
        $page,
        'Shared Operation is a source attribution; it is not the same as unallocated remainder.'
    ) !== false
);

$check(
    'PAGE_EXPLAINS_UNALLOCATED_PARENT_RETENTION',
    strpos(
        $page,
        'Unallocated shared balances remain at their parent scope until an explicit compatible allocation is recorded.'
    ) !== false
);

$check(
    'CENTRAL_STOCK_SCOPE_STILL_SUPPORTS_FARM',
    strpos(
        $stock,
        "\$level = 'farm';"
    ) !== false
);

$check(
    'CENTRAL_STOCK_SCOPE_STILL_SUPPORTS_FARM_TYPE',
    strpos(
        $stock,
        "\$level = 'farm_type';"
    ) !== false
);

$check(
    'CENTRAL_STOCK_SCOPE_STILL_SUPPORTS_PRODUCTION',
    strpos(
        $stock,
        "\$level = 'production';"
    ) !== false
);

$check(
    'CENTRAL_STOCK_SCOPE_STILL_SUPPORTS_CYCLE',
    strpos(
        $stock,
        "\$level = 'cycle';"
    ) !== false
);

$failed = [];

foreach ($checks as $name => $passed) {
    echo $name
        . '='
        . ($passed ? 'PASS' : 'FAIL')
        . PHP_EOL;

    if (!$passed) {
        $failed[] = $name;
    }
}

echo 'CHECK_COUNT='
    . count($checks)
    . PHP_EOL;

echo "DATABASE_CONNECTION=NONE\n";
echo "DATABASE_WRITE=NONE\n";

if ($failed) {
    echo 'FAILED='
        . implode(',', $failed)
        . PHP_EOL;

    echo "RESULT=FAIL\n";
    exit(1);
}

echo "RESULT=PASS\n";
exit(0);
