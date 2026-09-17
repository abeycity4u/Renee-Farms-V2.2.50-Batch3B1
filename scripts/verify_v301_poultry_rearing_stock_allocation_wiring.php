<?php

$root =
    dirname(__DIR__);

$path =
    $root
    . '/lib/poultry_rearing_economics.php';

$source =
    file_get_contents(
        $path
    );

if ($source === false) {
    echo "RESULT=FAIL\n";
    echo "CHECK_COUNT=0\n";
    echo "FAILED=SOURCE_MISSING\n";
    exit(1);
}

require_once $path;

$checks = [];

function rearing_check(
    array &$checks,
    string $name,
    bool $passed
): void {
    $checks[$name] =
        $passed;
}

rearing_check(
    $checks,
    'REQUIRES_CENTRAL_STOCK_ECONOMICS',
    strpos(
        $source,
        "require_once __DIR__ . '/stock_consumption_economics.php';"
    ) !== false
);

rearing_check(
    $checks,
    'CALLS_CENTRAL_STOCK_ECONOMICS',
    strpos(
        $source,
        'stock_consumption_economics_rows('
    ) !== false
);

rearing_check(
    $checks,
    'EXPLICIT_ALLOCATION_FILTER_PRESENT',
    strpos(
        $source,
        "!== 'explicit_allocation'"
    ) !== false
);

rearing_check(
    $checks,
    'NO_DIRECT_STOCK_ALLOCATION_SQL',
    preg_match(
        '/\bFROM\s+stock_consumption_allocations\b/i',
        $source
    ) !== 1
);

rearing_check(
    $checks,
    'DIRECT_STOCK_PROVENANCE_PRESERVED',
    strpos(
        $source,
        'poultry_production_entry_stock_use_provenance_source('
    ) !== false
);

rearing_check(
    $checks,
    'ALLOCATION_PROVENANCE_MAPPER_PRESENT',
    function_exists(
        'poultry_production_entry_stock_allocation_provenance_source'
    )
);

rearing_check(
    $checks,
    'ALLOCATION_SOURCE_TYPE_DISTINCT',
    strpos(
        $source,
        "'stock_consumption_allocation'"
    ) !== false
);

rearing_check(
    $checks,
    'ALLOCATION_REVISION_VERSION_PRESERVED',
    strpos(
        $source,
        "'allocation_revision_no'"
    ) !== false
    &&
    strpos(
        $source,
        "'source_version'"
    ) !== false
);

rearing_check(
    $checks,
    'NO_STOCK_MUTATION_SQL',
    preg_match(
        '/\b(?:INSERT\s+INTO|UPDATE\s+stock_|DELETE\s+FROM\s+stock_)/i',
        $source
    ) !== 1
);

rearing_check(
    $checks,
    'NO_TRANSACTION_OWNERSHIP',
    preg_match(
        '/->\s*(?:beginTransaction|commit|rollBack)\s*\(/',
        $source
    ) !== 1
);

/*
 * Pure provenance fixture.
 */
$operating = [
    'allocation_id' =>
        90,

    'allocation_revision_no' =>
        4,

    'stock_transaction_id' =>
        373,

    'stock_item_id' =>
        50,

    'transaction_date' =>
        '2026-09-10',

    'transaction_type' =>
        'used',

    'quantity' =>
        '1.00',

    'unit_cost' =>
        '600.0000',

    'total_cost' =>
        '600.00',

    'financial_classification' =>
        'consumables',

    'source_type' =>
        'inventory_api',

    'source_id' =>
        '373',

    'farm_type' =>
        'poultry',

    'production_type' =>
        'layer',

    'attribution_scope' =>
        'production_type',

    'target_cycle_id' =>
        55,

    'effective_cycle_id' =>
        55,

    'economic_amount' =>
        '600.00',

    'feed_category' =>
        'general',

    'category_name' =>
        'General',

    'allocation_notes' =>
        'Presentation note',
];

$operatingSource =
    poultry_production_entry_stock_allocation_provenance_source(
        $operating,
        'operating_inventory_use'
    );

rearing_check(
    $checks,
    'OPERATING_ALLOCATION_SOURCE_VALID',
    $operatingSource[
        'role'
    ] === 'operating_inventory_use'
    &&
    $operatingSource[
        'source_type'
    ] === 'stock_consumption_allocation'
    &&
    $operatingSource[
        'source_id'
    ] === '90'
    &&
    $operatingSource[
        'source_version'
    ] === '4'
    &&
    preg_match(
        '/^[a-f0-9]{64}$/',
        (string)$operatingSource[
            'source_revision'
        ]
    ) === 1
);

$notesChanged =
    $operating;

$notesChanged[
    'allocation_notes'
] =
    'Presentation note changed';

$notesSource =
    poultry_production_entry_stock_allocation_provenance_source(
        $notesChanged,
        'operating_inventory_use'
    );

rearing_check(
    $checks,
    'ALLOCATION_NOTES_PRESENTATION_INVARIANCE',
    $notesSource[
        'source_revision'
    ]
    ===
    $operatingSource[
        'source_revision'
    ]
);

$amountChanged =
    $operating;

$amountChanged[
    'economic_amount'
] =
    '599.99';

$amountSource =
    poultry_production_entry_stock_allocation_provenance_source(
        $amountChanged,
        'operating_inventory_use'
    );

rearing_check(
    $checks,
    'ALLOCATED_AMOUNT_SENSITIVITY',
    $amountSource[
        'source_revision'
    ]
    !==
    $operatingSource[
        'source_revision'
    ]
);

$parentChanged =
    $operating;

$parentChanged[
    'total_cost'
] =
    '601.00';

$parentSource =
    poultry_production_entry_stock_allocation_provenance_source(
        $parentChanged,
        'operating_inventory_use'
    );

rearing_check(
    $checks,
    'PARENT_ECONOMIC_FACT_SENSITIVITY',
    $parentSource[
        'source_revision'
    ]
    !==
    $operatingSource[
        'source_revision'
    ]
);

$revisionChanged =
    $operating;

$revisionChanged[
    'allocation_revision_no'
] =
    5;

$revisionSource =
    poultry_production_entry_stock_allocation_provenance_source(
        $revisionChanged,
        'operating_inventory_use'
    );

rearing_check(
    $checks,
    'ALLOCATION_VERSION_SENSITIVITY',
    $revisionSource[
        'source_revision'
    ]
    ===
    $operatingSource[
        'source_revision'
    ]
    &&
    poultry_production_entry_provenance_source_key(
        $revisionSource
    )
    !==
    poultry_production_entry_provenance_source_key(
        $operatingSource
    )
);

/*
 * Feed-specific policy metadata.
 */
$feed =
    $operating;

$feed['allocation_id'] =
    91;

$feed['stock_transaction_id'] =
    382;

$feed['stock_item_id'] =
    42;

$feed['total_cost'] =
    '24141.00';

$feed['unit_cost'] =
    '24141.0000';

$feed['economic_amount'] =
    '12070.50';

$feed['financial_classification'] =
    'feed';

$feed['source_type'] =
    'inventory_manual';

$feed['source_id'] =
    '382';

$feed['feed_category'] =
    'layer';

$feed['category_name'] =
    'Feeds';

$feedSource =
    poultry_production_entry_stock_allocation_provenance_source(
        $feed,
        'feed_use'
    );

rearing_check(
    $checks,
    'FEED_ALLOCATION_SOURCE_VALID',
    $feedSource[
        'role'
    ] === 'feed_use'
    &&
    $feedSource[
        'source_type'
    ] === 'stock_consumption_allocation'
    &&
    preg_match(
        '/^[a-f0-9]{64}$/',
        (string)$feedSource[
            'source_revision'
        ]
    ) === 1
);

$feedCategoryChanged =
    $feed;

$feedCategoryChanged[
    'feed_category'
] =
    'broiler';

$feedCategorySource =
    poultry_production_entry_stock_allocation_provenance_source(
        $feedCategoryChanged,
        'feed_use'
    );

rearing_check(
    $checks,
    'FEED_CATEGORY_SENSITIVITY',
    $feedCategorySource[
        'source_revision'
    ]
    !==
    $feedSource[
        'source_revision'
    ]
);

$feedNameChanged =
    $feed;

$feedNameChanged[
    'category_name'
] =
    'Medication';

$feedNameSource =
    poultry_production_entry_stock_allocation_provenance_source(
        $feedNameChanged,
        'feed_use'
    );

rearing_check(
    $checks,
    'FEED_CATEGORY_NAME_SEMANTIC_SENSITIVITY',
    $feedNameSource[
        'source_revision'
    ]
    !==
    $feedSource[
        'source_revision'
    ]
);

$feedCaseChanged =
    $feed;

$feedCaseChanged[
    'category_name'
] =
    'FEEDS';

$feedCaseSource =
    poultry_production_entry_stock_allocation_provenance_source(
        $feedCaseChanged,
        'feed_use'
    );

rearing_check(
    $checks,
    'FEED_CATEGORY_NAME_CASE_INVARIANCE',
    $feedCaseSource[
        'source_revision'
    ]
    ===
    $feedSource[
        'source_revision'
    ]
);

$roleChanged =
    poultry_production_entry_stock_allocation_provenance_source(
        $feed,
        'operating_inventory_use'
    );

rearing_check(
    $checks,
    'ALLOCATION_ROLE_SENSITIVITY',
    poultry_production_entry_provenance_source_key(
        $roleChanged
    )
    !==
    poultry_production_entry_provenance_source_key(
        $feedSource
    )
);

$failed = [];

foreach (
    $checks
    as $name => $passed
) {
    if (!$passed) {
        $failed[] =
            $name;
    }
}

echo 'RESULT='
    . (
        $failed
            ? 'FAIL'
            : 'PASS'
    )
    . PHP_EOL;

echo 'CHECK_COUNT='
    . count($checks)
    . PHP_EOL;

foreach (
    $checks
    as $name => $passed
) {
    echo $name
        . '='
        . (
            $passed
                ? 'PASS'
                : 'FAIL'
        )
        . PHP_EOL;
}

echo "DATABASE_CONNECTION=NONE\n";
echo "DATABASE_WRITE=NONE\n";

if ($failed) {
    echo 'FAILED='
        . implode(
            ',',
            $failed
        )
        . PHP_EOL;

    exit(1);
}

exit(0);
