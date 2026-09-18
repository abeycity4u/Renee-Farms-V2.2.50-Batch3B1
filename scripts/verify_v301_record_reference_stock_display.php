<?php
/**
 * Renee Farms V3.0.1
 * Stock human-facing record-reference display verifier.
 *
 * Source-only.
 */

$root =
    dirname(__DIR__);

$paths = [
    'api' =>
        $root
        . '/api/get_stock_history.php',

    'page' =>
        $root
        . '/api/stock_history.php',

    'js' =>
        $root
        . '/assets/js/stock-history.js',

    'layer' =>
        $root
        . '/poultry/layer_feeds.php',

    'broiler' =>
        $root
        . '/poultry/broiler_feeds.php',

    'ruminant' =>
        $root
        . '/ruminant/ruminant_feeds_record.php',

    'pdf' =>
        $root
        . '/includes/pdf/FeedTransactionReport.php',
];

$source = [];

foreach ($paths as $key => $path) {
    $source[$key] =
        is_file($path)
            ? (string)file_get_contents($path)
            : '';
}

$checks = 0;
$failures = 0;

$check =
    static function (
        bool $ok,
        string $label
    ) use (
        &$checks,
        &$failures
    ): void {
        $checks++;

        echo
            ($ok ? '[PASS] ' : '[FAIL] ')
            . $label
            . PHP_EOL;

        if (!$ok) {
            $failures++;
        }
    };


$check(
    strpos(
        $source['api'],
        'SELECT t.*'
    ) !== false,
    'Stock History API still carries canonical transaction row'
);

$check(
    strpos(
        $source['page'],
        '<th>Reference</th>'
    ) !== false,
    'Stock History page has Reference column'
);

$check(
    strpos(
        $source['page'],
        'colspan="13"'
    ) !== false,
    'Stock History loading row matches thirteen columns'
);

$check(
    strpos(
        $source['js'],
        "escapeHtml(tx.public_reference || '—')"
    ) !== false,
    'Stock History renders escaped public reference'
);

$check(
    substr_count(
        $source['js'],
        'colspan="13"'
    ) === 2,
    'Stock History empty/error rows match thirteen columns'
);


foreach (
    [
        'layer' =>
            'Layer feed ledger',

        'broiler' =>
            'Broiler feed ledger',

        'ruminant' =>
            'Ruminant feed ledger',
    ]
    as $key => $label
) {
    $check(
        strpos(
            $source[$key],
            'SELECT t.*'
        ) !== false,
        $label
            . ' still carries canonical transaction row'
    );

    $check(
        strpos(
            $source[$key],
            '<th>Reference</th>'
        ) !== false,
        $label
            . ' has Reference column'
    );

    $check(
        strpos(
            $source[$key],
            "\$trans['public_reference']"
        ) !== false,
        $label
            . ' renders public reference'
    );

    $check(
        strpos(
            $source[$key],
            'data-id="<?php echo (int)$trans[\'id\']; ?>"'
        ) !== false
        &&
        strpos(
            $source[$key],
            'name="transaction_id"'
        ) !== false,
        $label
            . ' preserves internal action-routing IDs'
    );
}


$check(
    strpos(
        $source['broiler'],
        "? '14' : '13'"
    ) !== false,
    'Broiler empty state accounts for Reference column and optional Actions'
);

$check(
    strpos(
        $source['pdf'],
        '<th>Reference</th>'
    ) !== false,
    'Feed PDF has Reference column'
);

$check(
    strpos(
        $source['pdf'],
        "\$trans['public_reference']"
    ) !== false,
    'Feed PDF renders public reference'
);

$check(
    strpos(
        $source['pdf'],
        'colspan="13"'
    ) !== false,
    'Feed PDF empty state matches thirteen columns'
);


$allDisplay =
    implode(
        "\n",
        $source
    );

$check(
    strpos(
        $allDisplay,
        'record_reference_generate('
    ) === false,
    'Display surfaces never generate references'
);

$check(
    strpos(
        $allDisplay,
        'record_reference_persistence_assign_existing('
    ) === false,
    'Display surfaces never assign references'
);

$check(
    preg_match(
        '/(?:data-id|transaction_id)[^\\n>]*public_reference/i',
        $allDisplay
    ) !== 1,
    'Public reference never replaces internal action IDs'
);


echo PHP_EOL
    . $checks
    . ' checks, '
    . $failures
    . ' failure(s).'
    . PHP_EOL;

exit(
    $failures === 0
        ? 0
        : 1
);
