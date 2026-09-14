<?php
/**
 * V3.0 focused static verifier:
 * canonical production population foundation.
 *
 * This verifier performs no database or network work.
 */

$root = dirname(__DIR__);

$migrationPath =
    $root . '/migrations/054_production_population_foundation.sql';

$checks = 0;
$failures = 0;

$check = static function (
    bool $ok,
    string $message
) use (&$checks, &$failures): void {
    $checks++;

    if ($ok) {
        echo "PASS: {$message}\n";
        return;
    }

    $failures++;
    echo "FAIL: {$message}\n";
};

$migration = is_file($migrationPath)
    ? file_get_contents($migrationPath)
    : false;

$check(
    $migration !== false,
    'migration 054 exists and is readable'
);

if ($migration === false) {
    echo "\nChecks: {$checks}\n";
    echo "Failures: {$failures}\n";
    echo "V3.0 PRODUCTION POPULATION FOUNDATION: FAILED\n";
    exit(1);
}

$compact = preg_replace('/\s+/', ' ', $migration);

$check(
    strpos(
        $migration,
        'CREATE TABLE IF NOT EXISTS production_population_baselines'
    ) !== false
    && strpos(
        $migration,
        'CREATE TABLE IF NOT EXISTS production_population_movements'
    ) !== false,
    'migration creates baseline and immutable movement authorities'
);

$check(
    strpos(
        $compact,
        'UNIQUE KEY uniq_population_baseline_cycle ( farm_id, cycle_id )'
    ) !== false,
    'each farm/cycle can establish only one population baseline'
);

$check(
    strpos(
        $compact,
        'baseline_quantity INT UNSIGNED NOT NULL'
    ) !== false
    && strpos(
        $compact,
        'baseline_date DATE NOT NULL'
    ) !== false
    && strpos(
        $compact,
        'baseline_source VARCHAR(40) NOT NULL'
    ) !== false,
    'baseline stores explicit non-negative quantity, date, and source'
);

$check(
    strpos(
        $compact,
        'FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE RESTRICT'
    ) !== false
    && strpos(
        $compact,
        'FOREIGN KEY (cycle_id) REFERENCES production_cycles(id) ON DELETE RESTRICT'
    ) !== false,
    'baseline is tenant/cycle anchored without cascading deletion'
);

$check(
    strpos(
        $compact,
        'quantity_delta INT NOT NULL'
    ) !== false
    && strpos(
        $compact,
        'movement_date DATE NOT NULL'
    ) !== false
    && strpos(
        $compact,
        'movement_type VARCHAR(40) NOT NULL'
    ) !== false,
    'movement ledger stores signed dated headcount effects'
);

$check(
    strpos(
        $compact,
        'source_type VARCHAR(40) NOT NULL'
    ) !== false
    && strpos(
        $compact,
        'source_id BIGINT UNSIGNED NULL'
    ) !== false
    && strpos(
        $compact,
        'source_version INT UNSIGNED NOT NULL DEFAULT 1'
    ) !== false,
    'movement source identity supports immutable corrected versions'
);

$check(
    strpos(
        $compact,
        'UNIQUE KEY uniq_population_movement_source ( farm_id, cycle_id, source_type, source_id, source_version )'
    ) !== false,
    'one durable source/version owns one canonical cycle movement'
);

$check(
    strpos(
        $compact,
        'UNIQUE KEY uniq_population_movement_request ( farm_id, request_token )'
    ) !== false,
    'request-owned movements have tenant-scoped idempotency'
);

$check(
    strpos(
        $compact,
        'UNIQUE KEY uniq_population_movement_identity ( farm_id, cycle_id, id )'
    ) !== false
    && strpos(
        $compact,
        'UNIQUE KEY uniq_population_movement_reversal ( farm_id, cycle_id, reversal_of_id )'
    ) !== false,
    'movement identity and one-reversal contract are tenant/cycle scoped'
);

$check(
    strpos(
        $compact,
        'FOREIGN KEY (farm_id, cycle_id) REFERENCES production_population_baselines(farm_id, cycle_id) ON DELETE RESTRICT'
    ) !== false,
    'every movement requires an established baseline for the same tenant/cycle'
);

$check(
    strpos(
        $compact,
        'FOREIGN KEY (farm_id, cycle_id, reversal_of_id) REFERENCES production_population_movements(farm_id, cycle_id, id) ON DELETE RESTRICT'
    ) !== false,
    'reversal cannot cross farm or cycle boundaries'
);

$check(
    strpos(
        $migration,
        'backfill historical V2.x population events'
    ) !== false
    && strpos(
        $migration,
        'Historical movement rows are never silently rewritten'
    ) !== false,
    'migration explicitly preserves legacy and immutable-history boundaries'
);

$legacyTables = [
    'layer_daily_records',
    'broiler_daily_records',
    'ruminant_daily_records',
    'ruminant_animals',
    'ruminant_animal_cycle_memberships',
    'ruminant_animal_exit_events',
    'sales_records',
    'production_cycles',
];

$legacyMutation = false;

foreach ($legacyTables as $table) {
    $quoted = preg_quote($table, '/');

    if (
        preg_match(
            '/\bALTER\s+TABLE\s+`?' . $quoted . '`?\b/i',
            $migration
        ) === 1
        || preg_match(
            '/\bUPDATE\s+`?' . $quoted . '`?\b/i',
            $migration
        ) === 1
        || preg_match(
            '/\bDELETE\s+FROM\s+`?' . $quoted . '`?\b/i',
            $migration
        ) === 1
        || preg_match(
            '/\bINSERT\s+INTO\s+`?' . $quoted . '`?\b/i',
            $migration
        ) === 1
    ) {
        $legacyMutation = true;
        break;
    }
}

$check(
    !$legacyMutation,
    'migration performs no legacy operational-table mutation'
);

$check(
    preg_match(
        '/\bCREATE\s+TRIGGER\b/i',
        $migration
    ) !== 1,
    'population behavior is not hidden in database triggers'
);

$check(
    strpos(
        $migration,
        "054_production_population_foundation.sql"
    ) !== false
    && strpos(
        $migration,
        'schema_migrations'
    ) !== false,
    'migration records the correct schema checkpoint'
);

$check(
    strpos($migration, 'curl_') === false
    && strpos($migration, 'http://') === false
    && strpos($migration, 'https://') === false,
    'migration performs no network/provider work'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    echo "V3.0 PRODUCTION POPULATION FOUNDATION: FAILED\n";
    exit(1);
}

echo "V3.0 PRODUCTION POPULATION FOUNDATION: PASSED\n";
