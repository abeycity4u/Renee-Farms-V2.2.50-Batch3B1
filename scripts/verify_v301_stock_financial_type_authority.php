<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$files = [
    'costing' =>
        $root . '/lib/stock_costing.php',

    'economics' =>
        $root . '/lib/stock_consumption_economics.php',

    'intelligence' =>
        $root . '/lib/farm_intelligence.php',

    'profitability' =>
        $root . '/management/profitability.php',

    'rearing' =>
        $root . '/lib/poultry_rearing_economics.php',
];

$failed = 0;
$checks = 0;

function check_authority(
    string $label,
    bool $condition
): void {
    global $failed, $checks;

    $checks++;

    echo
        $label
        . '='
        . (
            $condition
                ? 'PASS'
                : 'FAIL'
        )
        . PHP_EOL;

    if (!$condition) {
        $failed++;
    }
}

$source = [];

foreach ($files as $key => $path) {
    check_authority(
        'FILE_' . strtoupper($key) . '_PRESENT',
        is_file($path)
    );

    $source[$key] =
        is_file($path)
            ? (string)file_get_contents($path)
            : '';
}


/*
 * Central transaction authority exists.
 */
check_authority(
    'TRANSACTION_FEED_AUTHORITY_EXISTS',
    strpos(
        $source['costing'],
        'function stock_feed_transaction_sql_predicate('
    ) !== false
);

check_authority(
    'TRANSACTION_FEED_AUTHORITY_USES_MOVEMENT_SNAPSHOT',
    strpos(
        $source['costing'],
        "financial_classification,'')))='feed'"
    ) !== false
);


/*
 * Category literal name is no longer Feed accounting authority.
 */
check_authority(
    'CATEGORY_NAME_FEED_AUTHORITY_REMOVED',
    strpos(
        $source['costing'],
        "category_name,'')) IN ('feed','feeds')"
    ) === false
);


/*
 * Operational item helper remains specialization-only.
 */
check_authority(
    'ITEM_FEED_HELPER_USES_USAGE_SPECIALIZATION_ONLY',
    strpos(
        $source['costing'],
        "feed_category IN ('layer','broiler','ruminant')"
    ) !== false
    &&
    strpos(
        $source['costing'],
        "category_name,'')) IN ('feed','feeds')"
    ) === false
);


/*
 * All four financial/economic readers use transaction snapshot authority.
 */
foreach (
    [
        'economics',
        'intelligence',
        'profitability',
        'rearing',
    ]
    as $key
) {
    check_authority(
        strtoupper($key)
        . '_USES_TRANSACTION_FEED_AUTHORITY',
        strpos(
            $source[$key],
            'stock_feed_transaction_sql_predicate('
        ) !== false
    );

    check_authority(
        strtoupper($key)
        . '_DOES_NOT_USE_ITEM_FEED_AUTHORITY',
        strpos(
            $source[$key],
            'stock_feed_item_sql_predicate('
        ) === false
    );
}


/*
 * The legacy/current-item Feed helper may remain centrally defined for
 * operational specialization, but financial/economic readers must not call it.
 *
 * Count product source only. Do not recursively count this verifier's own
 * assertion text as a product call site.
 */
$legacyDefinitionCount =
    substr_count(
        $source['costing'],
        'stock_feed_item_sql_predicate('
    );

$legacyReaderCallCount =
    substr_count(
        $source['economics'],
        'stock_feed_item_sql_predicate('
    )
    +
    substr_count(
        $source['intelligence'],
        'stock_feed_item_sql_predicate('
    )
    +
    substr_count(
        $source['profitability'],
        'stock_feed_item_sql_predicate('
    )
    +
    substr_count(
        $source['rearing'],
        'stock_feed_item_sql_predicate('
    );

check_authority(
    'LEGACY_ITEM_FEED_HELPER_DEFINITION_ONLY',
    $legacyDefinitionCount === 1
    &&
    $legacyReaderCallCount === 0
);


/*
 * Production-Entry digest compatibility inputs intentionally remain.
 */
check_authority(
    'DIRECT_FEED_PROVENANCE_RETAINS_FEED_CATEGORY_INPUT',
    substr_count(
        $source['rearing'],
        "\$revisionFacts['feed_category']"
    ) >= 1
);

check_authority(
    'FEED_PROVENANCE_RETAINS_CATEGORY_NAME_DIGEST_INPUT',
    substr_count(
        $source['rearing'],
        'feed_category_name_normalized'
    ) >= 2
);

check_authority(
    'PROVENANCE_COMMENT_DOCUMENTS_COMPATIBILITY',
    strpos(
        $source['rearing'],
        'provenance digest recipe for compatibility'
    ) !== false
);


/*
 * No accidental category-name accounting SQL should survive in targeted
 * financial/economic readers.
 */
foreach (
    [
        'economics',
        'intelligence',
        'profitability',
        'rearing',
    ]
    as $key
) {
    check_authority(
        strtoupper($key)
        . '_NO_LITERAL_FEED_CATEGORY_NAME_SQL',
        strpos(
            $source[$key],
            "IN ('feed','feeds')"
        ) === false
    );
}


echo 'CHECK_COUNT=' . $checks . PHP_EOL;
echo 'FAILED_COUNT=' . $failed . PHP_EOL;
echo 'RESULT=' . ($failed === 0 ? 'PASS' : 'FAIL') . PHP_EOL;

exit(
    $failed === 0
        ? 0
        : 1
);
