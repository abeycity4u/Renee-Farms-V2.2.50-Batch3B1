<?php

declare(strict_types=1);

/**
 * V3.0.1 — Ruminant slaughter processing foundation verifier.
 *
 * Source-only. No database connection or write.
 */

$root = dirname(__DIR__);

$paths = [
    'migration' =>
        $root . '/migrations/069_ruminant_slaughter_processing_foundation.sql',
    'service' =>
        $root . '/lib/ruminant_slaughter_processing.php',
    'page' =>
        $root . '/ruminant/slaughter_processing.php',
    'navbar' =>
        $root . '/navbar.php',
    'stock' =>
        $root . '/lib/stock_service.php',
];

$sources = [];
foreach ($paths as $key => $path) {
    $sources[$key] =
        is_file($path) && is_readable($path)
            ? (string)file_get_contents($path)
            : '';
}

$checks = 0;
$failures = 0;

$check = static function (
    string $label,
    bool $ok
) use (&$checks, &$failures): void {
    $checks++;

    echo ($ok ? 'PASS: ' : 'FAIL: ')
        . $label
        . PHP_EOL;

    if (!$ok) {
        $failures++;
    }
};

$check(
    'Required slaughter-processing sources are readable',
    !in_array('', $sources, true)
);

$check(
    'Migration creates slaughter batch source table',
    str_contains(
        $sources['migration'],
        'CREATE TABLE IF NOT EXISTS ruminant_slaughter_batches'
    )
);

$check(
    'Migration creates source-specific slaughter output table',
    str_contains(
        $sources['migration'],
        'CREATE TABLE IF NOT EXISTS ruminant_slaughter_outputs'
    )
);

$check(
    'Exactly one processing batch can own a slaughter exit',
    str_contains(
        $sources['migration'],
        'UNIQUE KEY uniq_ruminant_slaughter_exit'
    )
);

$check(
    'Output stores both initial and remaining lot quantity',
    str_contains(
        $sources['migration'],
        'initial_quantity DECIMAL(12,2)'
    )
    && str_contains(
        $sources['migration'],
        'remaining_quantity DECIMAL(12,2)'
    )
);

$check(
    'Output row retains canonical stock transaction identity',
    str_contains(
        $sources['migration'],
        'stock_transaction_id INT NULL'
    )
    && str_contains(
        $sources['migration'],
        'uniq_ruminant_slaughter_output_stock_tx'
    )
);

$check(
    'Migration performs no historical slaughter backfill',
    !preg_match(
        '/INSERT\s+INTO\s+ruminant_slaughter_batches\s+SELECT/i',
        $sources['migration']
    )
);

$check(
    'Batch creation requires an explicit manual slaughter lifecycle event',
    str_contains(
        $sources['service'],
        "exit_outcome'] !== 'manual_slaughtered'"
    )
    && str_contains(
        $sources['service'],
        "status'] !== 'slaughtered'"
    )
);

$check(
    'Batch cycle comes from membership closed by the slaughter exit',
    str_contains(
        $sources['service'],
        'closed_by_exit_event_id=?'
    )
    && str_contains(
        $sources['service'],
        'count($memberships) !== 1'
    )
);

$check(
    'Slaughter output requires non-feed Ruminant/Shared Inventory item',
    str_contains(
        $sources['service'],
        "['ruminant', 'both']"
    )
    && str_contains(
        $sources['service'],
        "feed_category'] !== 'general'"
    )
);

$check(
    'Slaughter output uses the shared canonical stock writer',
    str_contains(
        $sources['service'],
        'stock_apply_movement('
    )
    && str_contains(
        $sources['service'],
        "'ruminant_slaughter_output'"
    )
);

$check(
    'Slaughter service contains no direct stock ledger mutation',
    !preg_match(
        '/(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+stock_transactions/i',
        $sources['service']
    )
    && !preg_match(
        '/UPDATE\s+stock_items\s+SET\s+current_stock/i',
        $sources['service']
    )
);

$check(
    'Output receipt stays attributed to exact slaughter cycle',
    str_contains(
        $sources['service'],
        "(int)\$batch['cycle_id']"
    )
    && str_contains(
        $sources['service'],
        "(string)\$batch['production_type']"
    )
);

$check(
    'Processing page uses shared service for both write actions',
    str_contains(
        $sources['page'],
        'ruminant_slaughter_processing_create_batch('
    )
    && str_contains(
        $sources['page'],
        'ruminant_slaughter_processing_add_output('
    )
);

$check(
    'Processing page protects writes with CSRF',
    str_contains(
        $sources['page'],
        'require_valid_csrf_post();'
    )
);

$check(
    'Processing page does not write canonical stock directly',
    !preg_match(
        '/(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+stock_transactions/i',
        $sources['page']
    )
);

$check(
    'Ruminant navigation exposes Slaughter Processing under Animal access',
    str_contains(
        $sources['navbar'],
        '/ruminant/slaughter_processing.php'
    )
    && str_contains(
        $sources['navbar'],
        'Slaughter Processing'
    )
);

$check(
    'Existing canonical stock service remains append-only authority',
    str_contains(
        $sources['stock'],
        'Canonical inventory ledger service'
    )
    && str_contains(
        $sources['stock'],
        'function stock_apply_movement'
    )
);

$check(
    'Migration records checkpoint 069',
    str_contains(
        $sources['migration'],
        '069_ruminant_slaughter_processing_foundation.sql'
    )
);

echo PHP_EOL;
echo 'CHECK_COUNT=' . $checks . PHP_EOL;
echo 'FAILED_COUNT=' . $failures . PHP_EOL;
echo 'DATABASE_CONNECTION_USED=NO' . PHP_EOL;
echo 'DATABASE_WRITE_PERFORMED=NO' . PHP_EOL;
echo 'RESULT='
    . ($failures === 0 ? 'PASS' : 'FAIL')
    . PHP_EOL;

exit($failures === 0 ? 0 : 1);
