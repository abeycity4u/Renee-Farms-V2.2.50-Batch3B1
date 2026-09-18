<?php

/**
 * V3.0.1 Daily Feed authoritative-cycle guard.
 *
 * Source-only.
 * No database connection.
 * No database writes.
 */

$root = dirname(__DIR__);

$sync =
    file_get_contents(
        $root
        . '/lib/daily_feed_sync.php'
    );

$layer =
    file_get_contents(
        $root
        . '/poultry/layers_daily_record.php'
    );

$broiler =
    file_get_contents(
        $root
        . '/poultry/broiler_daily_record.php'
    );

$ruminant =
    file_get_contents(
        $root
        . '/ruminant/ruminant_daily_record.php'
    );

if (
    $sync === false
    || $layer === false
    || $broiler === false
    || $ruminant === false
) {
    echo "RESULT=FAIL\n";
    echo "FAILED=SOURCE_LOAD\n";
    exit(1);
}

$checks = [];

$check = static function (
    string $name,
    bool $ok
) use (&$checks): void {
    $checks[$name] = $ok;
};

$check(
    'CENTRAL_GUARD_EXISTS_ONCE',
    substr_count(
        $sync,
        'function daily_feed_sync_authoritative_cycle_guard('
    ) === 1
);

$check(
    'CENTRAL_GUARD_REQUIRES_TRANSACTION',
    strpos(
        $sync,
        'if (!$pdo->inTransaction())'
    ) !== false
);

$check(
    'CENTRAL_GUARD_REQUIRES_LINKED_DAILY_SOURCE',
    strpos(
        $sync,
        "!== 'linked_daily_record'"
    ) !== false
);

$check(
    'CENTRAL_GUARD_RESOLVES_SOURCE_FOR_UPDATE',
    strpos(
        $sync,
        'stock_consumption_source_resolver_resolve('
    ) !== false
    &&
    strpos(
        $sync,
        "            true\n        );"
    ) !== false
);

$check(
    'CENTRAL_GUARD_READS_AUTHORITATIVE_CYCLE',
    strpos(
        $sync,
        "'authoritative_source'"
    ) !== false
    &&
    strpos(
        $sync,
        "['cycle_id']"
    ) !== false
);

$check(
    'CENTRAL_GUARD_FAILS_ON_CYCLE_MISMATCH',
    strpos(
        $sync,
        '$requestedCycleId'
    ) !== false
    &&
    strpos(
        $sync,
        '$authoritativeCycleId'
    ) !== false
    &&
    strpos(
        $sync,
        'No stock movement was changed.'
    ) !== false
);

$guardCall =
    strpos(
        $sync,
        'daily_feed_sync_authoritative_cycle_guard(',
        strpos(
            $sync,
            'function sync_daily_feed_usage('
        )
    );

$stockRead =
    strpos(
        $sync,
        'SELECT * FROM stock_transactions',
        strpos(
            $sync,
            'function sync_daily_feed_usage('
        )
    );

$check(
    'GUARD_RUNS_BEFORE_STOCK_LOCK',
    $guardCall !== false
    &&
    $stockRead !== false
    &&
    $guardCall < $stockRead
);

$check(
    'SYNC_USES_GUARDED_CYCLE',
    strpos(
        $sync,
        "\$cycleId =\n        daily_feed_sync_authoritative_cycle_guard("
    ) !== false
);

$check(
    'LAYER_USES_CENTRAL_SYNC',
    strpos(
        $layer,
        'sync_daily_feed_usage('
    ) !== false
    &&
    strpos(
        $layer,
        "'daily_layer_record'"
    ) !== false
);

$check(
    'BROILER_USES_CENTRAL_SYNC',
    strpos(
        $broiler,
        'sync_daily_feed_usage('
    ) !== false
    &&
    strpos(
        $broiler,
        "'daily_broiler_record'"
    ) !== false
);

$check(
    'RUMINANT_USES_CENTRAL_SYNC',
    strpos(
        $ruminant,
        'sync_daily_feed_usage('
    ) !== false
    &&
    strpos(
        $ruminant,
        "'daily_ruminant_record'"
    ) !== false
);

$check(
    'RUMINANT_SAVE_IS_TRANSACTIONAL',
    strpos(
        $ruminant,
        '$pdo->beginTransaction();'
    ) !== false
    &&
    strpos(
        $ruminant,
        '$pdo->commit();'
    ) !== false
    &&
    strpos(
        $ruminant,
        '$pdo->rollBack();'
    ) !== false
);

$check(
    'LAYER_SAVE_IS_TRANSACTIONAL',
    strpos(
        $layer,
        '$pdo->beginTransaction();'
    ) !== false
    &&
    strpos(
        $layer,
        '$pdo->commit();'
    ) !== false
);

$check(
    'BROILER_SAVE_IS_TRANSACTIONAL',
    strpos(
        $broiler,
        '$pdo->beginTransaction();'
    ) !== false
    &&
    strpos(
        $broiler,
        '$pdo->commit();'
    ) !== false
);

$check(
    'SYNC_STILL_USES_APPEND_ONLY_REVERSAL',
    strpos(
        $sync,
        'stock_reverse_transaction('
    ) !== false
);

$check(
    'SYNC_STILL_USES_CANONICAL_STOCK_APPLY',
    strpos(
        $sync,
        'stock_apply_movement('
    ) !== false
);

$check(
    'SYNC_HAS_NO_DIRECT_STOCK_MUTATION_SQL',
    preg_match(
        '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+stock_transactions\b/i',
        $sync
    ) !== 1
);

$failed = [];

foreach (
    $checks
    as $name => $ok
) {
    echo $name
        . '='
        . ($ok ? 'PASS' : 'FAIL')
        . PHP_EOL;

    if (!$ok) {
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
        . implode(
            ',',
            $failed
        )
        . PHP_EOL;

    echo "RESULT=FAIL\n";
    exit(1);
}

echo "RESULT=PASS\n";
exit(0);
