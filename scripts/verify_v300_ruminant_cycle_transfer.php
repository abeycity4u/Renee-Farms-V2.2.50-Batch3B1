<?php
/**
 * V3 tagged-ruminant cycle-transfer focused verifier.
 *
 * Static only:
 * - no config bootstrap;
 * - no PDO connection;
 * - no database write;
 * - no migration execution.
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
    $root
    . '/migrations/061_ruminant_cycle_transfer_integrity.sql'
);

$service = $read(
    $root
    . '/lib/ruminant_cycle_transfer.php'
);

$membership = $read(
    $root
    . '/lib/ruminant_cycle_membership.php'
);

$populationTransfer = $read(
    $root
    . '/lib/production_population_transfer.php'
);

$checks = 0;
$failures = 0;

$check = static function (
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

/*
 * ---------------------------------------------------------
 * Files / foundation
 * ---------------------------------------------------------
 */

$check(
    'migration file is readable',
    $migration !== ''
);

$check(
    'tagged transfer service is readable',
    $service !== ''
);

$check(
    'membership service is readable',
    $membership !== ''
);

$check(
    'canonical paired transfer service is readable',
    $populationTransfer !== ''
);

/*
 * ---------------------------------------------------------
 * Migration 061 contract
 * ---------------------------------------------------------
 */

$check(
    'migration adds opened transfer provenance',
    str_contains(
        $migration,
        'opened_by_transfer_id BIGINT UNSIGNED NULL'
    )
);

$check(
    'migration adds closed transfer provenance',
    str_contains(
        $migration,
        'closed_by_transfer_id BIGINT UNSIGNED NULL'
    )
);

$check(
    'migration preserves prior source end date',
    str_contains(
        $migration,
        'pre_transfer_end_date DATE NULL'
    )
);

$check(
    'opened transfer FK index has correct leading prefix',
    preg_match(
        '/idx_racm_opened_transfer\s*'
        . '\(\s*farm_id\s*,\s*'
        . 'opened_by_transfer_id\s*,\s*animal_id\s*\)/is',
        $migration
    ) === 1
);

$check(
    'closed transfer FK index has correct leading prefix',
    preg_match(
        '/idx_racm_closed_transfer\s*'
        . '\(\s*farm_id\s*,\s*'
        . 'closed_by_transfer_id\s*,\s*animal_id\s*\)/is',
        $migration
    ) === 1
);

$check(
    'opened transfer FK references canonical transfer parent',
    preg_match(
        '/FOREIGN KEY\s*'
        . '\(\s*farm_id\s*,\s*opened_by_transfer_id\s*\)\s*'
        . 'REFERENCES\s+production_population_transfers\s*'
        . '\(\s*farm_id\s*,\s*id\s*\)/is',
        $migration
    ) === 1
);

$check(
    'closed transfer FK references canonical transfer parent',
    preg_match(
        '/FOREIGN KEY\s*'
        . '\(\s*farm_id\s*,\s*closed_by_transfer_id\s*\)\s*'
        . 'REFERENCES\s+production_population_transfers\s*'
        . '\(\s*farm_id\s*,\s*id\s*\)/is',
        $migration
    ) === 1
);

$check(
    'migration creates durable tagged transfer audit table',
    str_contains(
        $migration,
        'CREATE TABLE IF NOT EXISTS ruminant_animal_cycle_transfers'
    )
);

$check(
    'tagged transfer audit has unique canonical parent',
    preg_match(
        '/UNIQUE KEY\s+uniq_ruminant_animal_transfer_parent\s*'
        . '\(\s*farm_id\s*,\s*population_transfer_id\s*\)/is',
        $migration
    ) === 1
);

$check(
    'tagged audit references canonical population transfer',
    preg_match(
        '/CONSTRAINT\s+fk_ract_population_transfer\s*'
        . 'FOREIGN KEY\s*'
        . '\(\s*farm_id\s*,\s*population_transfer_id\s*\)\s*'
        . 'REFERENCES\s+production_population_transfers\s*'
        . '\(\s*farm_id\s*,\s*id\s*\)/is',
        $migration
    ) === 1
);

$fkCount = preg_match_all(
    '/^[ \t]*(?:ADD[ \t]+)?CONSTRAINT[ \t]+fk_/mi',
    $migration
);

$check(
    'migration has exactly seven intended foreign keys',
    $fkCount === 7
);

$check(
    'migration contains no historical transfer backfill',
    preg_match(
        '/INSERT\s+INTO\s+ruminant_animal_cycle_transfers\s+SELECT/is',
        $migration
    ) !== 1
);

$check(
    'migration registers only migration 061 filename',
    substr_count(
        $migration,
        "VALUES ('061_ruminant_cycle_transfer_integrity.sql')"
    ) === 1
);

/*
 * ---------------------------------------------------------
 * Tagged transfer service ownership
 * ---------------------------------------------------------
 */

$check(
    'service requires canonical paired transfer foundation',
    str_contains(
        $service,
        "require_once __DIR__"
    )
    && str_contains(
        $service,
        "'/production_population_transfer.php'"
    )
);

$check(
    'service requires central membership service',
    str_contains(
        $service,
        "'/ruminant_cycle_membership.php'"
    )
);

$check(
    'record function exists exactly once',
    substr_count(
        $service,
        'function ruminant_cycle_transfer_record('
    ) === 1
);

$check(
    'reverse function exists exactly once',
    substr_count(
        $service,
        'function ruminant_cycle_transfer_reverse('
    ) === 1
);

$check(
    'record delegates exactly once to canonical paired transfer',
    substr_count(
        $service,
        'production_population_transfer_record('
    ) === 1
);

$check(
    'reverse delegates exactly once to canonical paired reversal',
    substr_count(
        $service,
        'production_population_transfer_reverse('
    ) === 1
);

$check(
    'tagged transfer service has no direct canonical population SQL',
    preg_match(
        '/(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+'
        . 'production_population_'
        . '(?:movements|baselines)\b/is',
        $service
    ) !== 1
);

$check(
    'service never terminally updates animal status',
    preg_match(
        '/UPDATE\s+ruminant_animals\s+SET\s+status/is',
        $service
    ) !== 1
);

$check(
    'service never creates lifecycle exit event',
    preg_match(
        '/INSERT\s+INTO\s+ruminant_animal_exit_events/is',
        $service
    ) !== 1
);

$check(
    'animal must be Active to transfer',
    str_contains(
        $service,
        'is not Active and cannot be moved'
    )
);

$check(
    'one tagged animal always delegates quantity one',
    preg_match(
        '/production_population_transfer_record\(\s*'
        . '\$pdo\s*,\s*'
        . '\$farmId\s*,\s*'
        . '\$fromCycleId\s*,\s*'
        . '\$toCycleId\s*,\s*'
        . '\$transferDate\s*,\s*'
        . '1\s*,/is',
        $service
    ) === 1
);

$check(
    'source and destination cycle must differ',
    str_contains(
        $service,
        '$fromCycleId === $toCycleId'
    )
    && preg_match(
        '/Choose a different destination.{0,160}production cycle\./s',
        $service
    ) === 1
);

$check(
    'same-day first-membership transfer is rejected',
    preg_match(
        '/cannot be transferred on the.{0,160}first day of its current membership\./s',
        $service
    ) === 1
);

/*
 * ---------------------------------------------------------
 * Membership effective-date contract
 * ---------------------------------------------------------
 */

$check(
    'source end date is previous calendar day',
    str_contains(
        $service,
        "->modify('-1 day')"
    )
);

$check(
    'source membership preserves previous end date',
    str_contains(
        $service,
        'pre_transfer_end_date = end_date'
    )
);

$check(
    'source membership is closed by canonical transfer parent',
    str_contains(
        $service,
        'closed_by_transfer_id = ?'
    )
);

$check(
    'destination membership uses shared add helper',
    substr_count(
        $service,
        'ruminant_cycle_membership_add('
    ) === 1
);

$check(
    'destination membership begins on transfer date',
    preg_match(
        '/ruminant_cycle_membership_add\(\s*'
        . '\$pdo\s*,\s*'
        . '\$farmId\s*,\s*'
        . '\$animalId\s*,\s*'
        . '\$toCycleId\s*,\s*'
        . '\$transferDate\s*,/is',
        $service
    ) === 1
);

$check(
    'destination membership is marked by canonical transfer parent',
    str_contains(
        $service,
        'opened_by_transfer_id = ?'
    )
);

$check(
    'durable tagged transfer audit row is inserted',
    substr_count(
        $service,
        'INSERT INTO ruminant_animal_cycle_transfers'
    ) === 1
);

$check(
    'record stores source previous end date in domain audit',
    str_contains(
        $service,
        'source_previous_end_date'
    )
);

/*
 * ---------------------------------------------------------
 * Idempotency / transaction contract
 * ---------------------------------------------------------
 */

$check(
    'record uses canonical request-token normalization',
    str_contains(
        $service,
        'production_population_transfer_request_token('
    )
);

$check(
    'tagged idempotency check runs before source re-derivation',
    ($tokenPos = strpos(
        $service,
        'ruminant_cycle_transfer_load_by_token('
    )) !== false
    && ($sourcePos = strpos(
        $service,
        'ruminant_cycle_transfer_source_membership(',
        $tokenPos
    )) !== false
    && $tokenPos < $sourcePos
);

$check(
    'service refuses adoption of unrelated generic transfer token',
    preg_match(
        '/token already belongs.{0,160}to another production transfer\./s',
        $service
    ) === 1
);

$check(
    'service preserves caller-owned transaction',
    substr_count(
        $service,
        '$startedTransaction ='
    ) >= 2
    && substr_count(
        $service,
        '!$pdo->inTransaction();'
    ) >= 2
);

$check(
    'record rollback path exists',
    substr_count(
        $service,
        '$pdo->rollBack();'
    ) >= 2
);

/*
 * ---------------------------------------------------------
 * Reversal contract
 * ---------------------------------------------------------
 */

$populationReversePos = strpos(
    $service,
    'production_population_transfer_reverse('
);

$deleteDestinationPos = strpos(
    $service,
    '$deleteDestination ='
);

$restoreSourcePos = strpos(
    $service,
    '$restoreSource ='
);

$domainReverseMarkPos = strpos(
    $service,
    '$updateTransfer ='
);

$check(
    'canonical population reversal precedes membership restoration',
    $populationReversePos !== false
    && $deleteDestinationPos !== false
    && $restoreSourcePos !== false
    && $populationReversePos < $deleteDestinationPos
    && $populationReversePos < $restoreSourcePos
);

$check(
    'destination membership later history blocks reversal',
    preg_match(
        '/\$destination\s*\[\s*'
        . "'closed_by_exit_event_id'"
        . '\s*\]/s',
        $service
    ) === 1
    && preg_match(
        '/\$destination\s*\[\s*'
        . "'closed_by_transfer_id'"
        . '\s*\]/s',
        $service
    ) === 1
);

$check(
    'destination financial activity blocks reversal',
    substr_count(
        $service,
        'ruminant_cycle_membership_has_financial_activity('
    ) === 1
);

$check(
    'reversal removes only transfer-created destination membership',
    str_contains(
        $service,
        'DELETE FROM'
    )
    && str_contains(
        $service,
        'opened_by_transfer_id = ?'
    )
    && str_contains(
        $service,
        'closed_by_exit_event_id IS NULL'
    )
    && str_contains(
        $service,
        'closed_by_transfer_id IS NULL'
    )
);

$check(
    'reversal restores exact prior source end date',
    str_contains(
        $service,
        'end_date = pre_transfer_end_date'
    )
    && str_contains(
        $service,
        'pre_transfer_end_date = NULL'
    )
);

$check(
    'tagged transfer reversal is marked only after membership restoration',
    $restoreSourcePos !== false
    && $domainReverseMarkPos !== false
    && $restoreSourcePos < $domainReverseMarkPos
);

$check(
    'tagged reversal is durable and reasoned',
    str_contains(
        $service,
        'reversed_at = NOW()'
    )
    && str_contains(
        $service,
        'reversal_reason = ?'
    )
);

/*
 * ---------------------------------------------------------
 * Ordinary membership protection
 * ---------------------------------------------------------
 */

$check(
    'manual membership close reads transfer provenance',
    str_contains(
        $membership,
        'opened_by_transfer_id,closed_by_transfer_id'
    )
);

$check(
    'manual close blocks both opened and closed transfer memberships',
    str_contains(
        $membership,
        "if(!empty(\$m['opened_by_transfer_id']) "
        . "|| !empty(\$m['closed_by_transfer_id']))"
    )
);

$check(
    'manual membership delete reads transfer provenance',
    str_contains(
        $membership,
        "boundaryRow['opened_by_transfer_id']"
    )
    && str_contains(
        $membership,
        "boundaryRow['closed_by_transfer_id']"
    )
);

$check(
    'manual membership delete blocks transfer-owned history',
    str_contains(
        $membership,
        'linked to a recorded cycle transfer'
    )
);

/*
 * ---------------------------------------------------------
 * Canonical paired-transfer dependency remains intact
 * ---------------------------------------------------------
 */

$check(
    'canonical paired transfer still emits transfer_out',
    str_contains(
        $populationTransfer,
        "'transfer_out'"
    )
);

$check(
    'canonical paired transfer still emits transfer_in',
    str_contains(
        $populationTransfer,
        "'transfer_in'"
    )
);

$canonicalInReversePos = strpos(
    $populationTransfer,
    '$inReversalId ='
);

$canonicalOutReversePos = strpos(
    $populationTransfer,
    '$outReversalId ='
);

$check(
    'canonical paired reversal still reverses IN before OUT',
    $canonicalInReversePos !== false
    && $canonicalOutReversePos !== false
    && $canonicalInReversePos < $canonicalOutReversePos
);

/*
 * ---------------------------------------------------------
 * Verifier self-safety
 * ---------------------------------------------------------
 */

$self = (string)file_get_contents(__FILE__);

$pdoConstructorNeedle =
    'new'
    . ' PDO(';

$configNeedle =
    'config'
    . '.php';

$check(
    'verifier itself does not bootstrap database',
    !str_contains(
        $self,
        $pdoConstructorNeedle
    )
    && !str_contains(
        $self,
        $configNeedle
    )
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures === 0) {
    echo "V3 tagged ruminant cycle transfer verifier PASSED.\n";
    echo "Database connection/write: NONE.\n";
    exit(0);
}

echo "V3 tagged ruminant cycle transfer verifier FAILED.\n";
exit(1);
