<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

require_once
    $root
    .
    '/includes/expense_category_catalog.php';

$failed = false;

function contract_check(
    bool $condition,
    string $label
): void {
    global $failed;

    if ($condition) {
        echo "PASS: {$label}\n";
        return;
    }

    echo "FAIL: {$label}\n";
    $failed = true;
}


$manual =
    expense_category_keys(
        'manual'
    );

$slaughter =
    expense_category_keys(
        'slaughter_processing'
    );

$report =
    expense_category_keys(
        'report'
    );


contract_check(
    $manual === [
        'salary',
        'labour',
        'logistic',
        'fuel',
        'processing_materials',
        'misc',
    ],
    'manual expense surface uses complete canonical non-stock list'
);


contract_check(
    $slaughter === [
        'labour',
        'logistic',
        'fuel',
        'processing_materials',
        'misc',
    ],
    'Slaughter Processing exposes only processing-relevant subset'
);


contract_check(
    !in_array(
        'salary',
        $slaughter,
        true
    ),
    'Salary is excluded from Slaughter Processing'
);


contract_check(
    in_array(
        'salary',
        $manual,
        true
    ),
    'Salary remains available on normal Expenses'
);


contract_check(
    in_array(
        'feeds',
        $report,
        true
    )
    &&
    in_array(
        'medication',
        $report,
        true
    )
    &&
    in_array(
        'labour',
        $report,
        true
    )
    &&
    in_array(
        'processing_materials',
        $report,
        true
    ),
    'report authority preserves historical and new categories'
);


contract_check(
    expense_category_label(
        'labour'
    )
    ===
    'Labour Cost',
    'Labour Cost label is canonical'
);


contract_check(
    expense_category_label(
        'processing_materials'
    )
    ===
    'Processing Materials',
    'Processing Materials label is canonical'
);


contract_check(
    expense_category_is_historical(
        'feeds'
    )
    &&
    expense_category_is_historical(
        'medication'
    )
    &&
    !expense_category_is_historical(
        'labour'
    ),
    'historical Inventory-owned categories remain distinguishable'
);


$salaryRejected = false;

try {
    expense_category_normalize(
        'salary',
        'slaughter_processing'
    );

} catch (InvalidArgumentException $e) {
    $salaryRejected = true;
}

contract_check(
    $salaryRejected,
    'Slaughter Processing server contract rejects Salary'
);


contract_check(
    expense_category_normalize(
        'labour',
        'slaughter_processing'
    )
    ===
    'labour',
    'Slaughter Processing server contract accepts Labour Cost'
);


$migrationPath =
    $root
    .
    '/migrations/081_expense_category_authority.sql';

$migration =
    is_file($migrationPath)
        ? file_get_contents($migrationPath)
        : false;

contract_check(
    is_string($migration),
    'migration 081 exists and is readable'
);


contract_check(
    is_string($migration)
    &&
    str_contains(
        $migration,
        "'labour'"
    )
    &&
    str_contains(
        $migration,
        "'processing_materials'"
    ),
    'migration 081 expands farm_expenses category ENUM'
);


contract_check(
    is_string($migration)
    &&
    str_contains(
        $migration,
        "'081_expense_category_authority.sql'"
    ),
    'migration 081 records schema migration marker'
);


contract_check(
    is_string($migration)
    &&
    !preg_match(
        '/UPDATE\s+farm_expenses/i',
        $migration
    ),
    'migration 081 does not rewrite historical expense rows'
);


exit(
    $failed
        ? 1
        : 0
);
