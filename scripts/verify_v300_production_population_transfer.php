<?php
/**
 * Static verifier for V3 paired production population transfer foundation.
 *
 * No database connection.
 * No database write.
 */

$root = dirname(__DIR__);

$read = static function (string $path): string {
    $content = @file_get_contents($path);

    if ($content === false) {
        return '';
    }

    return $content;
};

$migration = $read(
    $root . '/migrations/060_production_population_transfers.sql'
);
$service = $read(
    $root . '/lib/production_population_transfer.php'
);
$population = $read(
    $root . '/lib/production_population.php'
);
$projection = $read(
    $root . '/lib/production_population_projection.php'
);
$globalIdentity = $read(
    $root . '/migrations/056_population_global_source_identity.sql'
);

$checks = 0;
$failures = 0;

$ck = static function (
    string $label,
    bool $condition
) use (&$checks, &$failures): void {
    $checks++;

    if ($condition) {
        echo "PASS: {$label}\n";
        return;
    }

    $failures++;
    echo "FAIL: {$label}\n";
};

$ck(
    'transfer migration is readable',
    $migration !== ''
);

$ck(
    'transfer service is readable',
    $service !== ''
);

$ck(
    'canonical population service is readable',
    $population !== ''
);

$ck(
    'population projection service is readable',
    $projection !== ''
);

$ck(
    'global source identity migration is readable',
    $globalIdentity !== ''
);

$ck(
    'migration creates durable transfer parent',
    str_contains(
        $migration,
        'CREATE TABLE IF NOT EXISTS production_population_transfers'
    )
);

$ck(
    'migration creates durable transfer legs',
    str_contains(
        $migration,
        'CREATE TABLE IF NOT EXISTS production_population_transfer_legs'
    )
);

$ck(
    'migration does not backfill transfer history',
    !preg_match(
        '/INSERT\s+INTO\s+production_population_transfers\s+SELECT/i',
        $migration
    )
);

$ck(
    'parent request token is tenant unique',
    str_contains(
        $migration,
        'UNIQUE KEY uniq_population_transfer_request'
    )
    && str_contains(
        $migration,
        'farm_id,'
    )
    && str_contains(
        $migration,
        'request_token'
    )
);

$ck(
    'source transfer cycle requires canonical baseline',
    str_contains(
        $migration,
        'fk_population_transfer_from_baseline'
    )
    && str_contains(
        $migration,
        'production_population_baselines(farm_id, cycle_id)'
    )
);

$ck(
    'destination transfer cycle requires canonical baseline',
    str_contains(
        $migration,
        'fk_population_transfer_to_baseline'
    )
);

$ck(
    'each transfer owns one leg per direction',
    str_contains(
        $migration,
        'uniq_population_transfer_leg_direction'
    )
);

$ck(
    'transfer leg links to canonical movement',
    str_contains(
        $migration,
        'fk_population_transfer_leg_movement'
    )
    && str_contains(
        $migration,
        'production_population_movements'
    )
);

$ck(
    'service requires canonical request-token normalization',
    str_contains(
        $service,
        'production_population_normalize_request_token('
    )
);

$ck(
    'service rejects zero or negative transfer quantity',
    str_contains(
        $service,
        'Transfer quantity must be greater than 0.'
    )
);

$ck(
    'service requires distinct source and destination cycles',
    str_contains(
        $service,
        'Source and destination production cycles must be different.'
    )
);

$ck(
    'cycle locking order is deterministic',
    str_contains(
        $service,
        'sort($cycleIds, SORT_NUMERIC);'
    )
);

$ck(
    'both cycles are locked through canonical population service',
    str_contains(
        $service,
        'production_population_lock_cycle('
    )
);

$ck(
    'both baselines are locked through canonical population service',
    str_contains(
        $service,
        'production_population_lock_baseline('
    )
);

$ck(
    'new transfers require both cycles active',
    str_contains(
        $service,
        'Production transfers can only be recorded between active production cycles.'
    )
);

$ck(
    'transfer date is validated against both cycles',
    substr_count(
        $service,
        'production_population_assert_date_in_cycle('
    ) >= 2
);

$ck(
    'transfer date is bounded by both baselines',
    str_contains(
        $service,
        "['from_baseline']"
    )
    || (
        str_contains(
            $service,
            "\$baselines[\$fromCycleId]['baseline_date']"
        )
        && str_contains(
            $service,
            "\$baselines[\$toCycleId]['baseline_date']"
        )
    )
);

$ck(
    'transfer requires matching farm type',
    str_contains(
        $service,
        '$sameFarmType'
    )
);

$ck(
    'transfer requires matching production type',
    str_contains(
        $service,
        '$sameProductionType'
    )
);

$ck(
    'transfer preserves custom livestock identity',
    str_contains(
        $service,
        '$fromLivestockTypeId'
    )
    && str_contains(
        $service,
        '$toLivestockTypeId'
    )
);

$ck(
    'record operation preserves caller transaction',
    str_contains(
        $service,
        '$startedTransaction = !$pdo->inTransaction();'
    )
    && str_contains(
        $service,
        'if ($startedTransaction)'
    )
);

$ck(
    'transfer creates explicit OUT durable leg',
    str_contains(
        $service,
        "'out'"
    )
    && str_contains(
        $service,
        '$outLegId'
    )
);

$ck(
    'transfer creates explicit IN durable leg',
    str_contains(
        $service,
        "'in'"
    )
    && str_contains(
        $service,
        '$inLegId'
    )
);

$ck(
    'transfer records exactly two canonical population movements',
    substr_count(
        $service,
        'production_population_record_movement('
    ) === 2
);

$ck(
    'OUT leg uses canonical transfer_out movement',
    str_contains(
        $service,
        "'transfer_out'"
    )
);

$ck(
    'IN leg uses canonical transfer_in movement',
    str_contains(
        $service,
        "'transfer_in'"
    )
);

$ck(
    'both movements use canonical transfer source type',
    substr_count(
        $service,
        "'transfer',"
    ) >= 2
);

$ck(
    'OUT movement uses OUT leg durable source ID',
    preg_match(
        "/'transfer',\s*\\\$outLegId,\s*1,/s",
        $service
    ) === 1
);

$ck(
    'IN movement uses IN leg durable source ID',
    preg_match(
        "/'transfer',\s*\\\$inLegId,\s*1,/s",
        $service
    ) === 1
);

$ck(
    'transfer service does not directly write canonical population tables',
    !preg_match(
        '/(?:INSERT\s+INTO|UPDATE\s+|DELETE\s+FROM)\s+production_population_(?:movements|baselines)\b/i',
        $service
    )
);

$ck(
    'canonical movement IDs are linked back to durable transfer legs',
    str_contains(
        $service,
        'SET population_movement_id = ?'
    )
    && str_contains(
        $service,
        '$outMovementId'
    )
    && str_contains(
        $service,
        '$inMovementId'
    )
);

$ck(
    'paired transfer reversal exists',
    str_contains(
        $service,
        'function production_population_transfer_reverse('
    )
);

$ck(
    'reversal delegates exactly twice to canonical reversal service',
    substr_count(
        $service,
        'production_population_reverse_movement('
    ) === 2
);

$inReversePos = strpos(
    $service,
    '$inReversalId ='
);
$outReversePos = strpos(
    $service,
    '$outReversalId ='
);

$ck(
    'destination IN reversal is attempted before source OUT restoration',
    $inReversePos !== false
    && $outReversePos !== false
    && $inReversePos < $outReversePos
);

$ck(
    'transfer reversal is auditably marked rather than deleting parent',
    str_contains(
        $service,
        'reversed_at = NOW()'
    )
    && str_contains(
        $service,
        'reversal_reason = ?'
    )
    && !preg_match(
        '/DELETE\s+FROM\s+production_population_transfers/i',
        $service
    )
);

$ck(
    'global source identity excludes cycle ID',
    preg_match(
        '/UNIQUE\s+KEY\s+uniq_population_movement_source\s*\(\s*farm_id\s*,\s*source_type\s*,\s*source_id\s*,\s*source_version\s*\)/is',
        $globalIdentity
    ) === 1
);

$ck(
    'projection service treats multiple active rows for one durable source as integrity failure',
    str_contains(
        $projection,
        'multiple active movements across cycles'
    )
);

$ck(
    'canonical movement service already recognizes transfer source',
    str_contains(
        $population,
        "'transfer',"
    )
);

$ck(
    'canonical movement service already recognizes transfer_out',
    str_contains(
        $population,
        "'transfer_out'"
    )
);

$ck(
    'canonical movement service already recognizes transfer_in',
    str_contains(
        $population,
        "'transfer_in'"
    )
);

$selfSource = (string)file_get_contents(__FILE__);
$pdoConstructorNeedle = 'new' . ' PDO(';
$configNeedle = 'config' . '.php';

$ck(
    'verifier itself does not connect to database',
    !str_contains(
        $selfSource,
        $pdoConstructorNeedle
    )
    && !str_contains(
        $selfSource,
        $configNeedle
    )
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures === 0) {
    echo "V3 paired production population transfer verifier PASSED.\n";
    echo "Database connection/write: NONE.\n";
    exit(0);
}

echo "V3 paired production population transfer verifier FAILED.\n";
exit(1);
