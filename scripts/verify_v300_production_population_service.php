<?php
/**
 * V3.0 focused static/pure-contract verifier:
 * shared production population service.
 *
 * No database connection is opened and no migration is executed.
 */

$root = dirname(__DIR__);
$servicePath = $root . '/lib/production_population.php';

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

$source = is_file($servicePath)
    ? file_get_contents($servicePath)
    : false;

$check(
    $source !== false,
    'shared production population service exists and is readable'
);

if ($source === false) {
    echo "\nChecks: {$checks}\n";
    echo "Failures: {$failures}\n";
    echo "V3.0 PRODUCTION POPULATION SERVICE: FAILED\n";
    exit(1);
}

require_once $servicePath;

$check(
    class_exists('ProductionPopulationException')
    && function_exists('production_population_establish_baseline')
    && function_exists('production_population_record_movement')
    && function_exists('production_population_reverse_movement')
    && function_exists('production_population_state'),
    'canonical population service surface is loaded'
);

$expectedMovementTypes = [
    'acquisition' => 1,
    'birth' => 1,
    'transfer_in' => 1,
    'mortality' => -1,
    'sale' => -1,
    'cull' => -1,
    'slaughter' => -1,
    'transfer_out' => -1,
    'adjustment_in' => 1,
    'adjustment_out' => -1,
];

$check(
    production_population_movement_types() === $expectedMovementTypes,
    'one shared movement map owns every normal movement sign'
);

$check(
    !array_key_exists(
        'reversal',
        production_population_movement_types()
    ),
    'reversal is internal-only and cannot be requested as a normal movement type'
);

$expectedSources = [
    'daily_layer_record',
    'daily_broiler_record',
    'daily_ruminant_record',
    'sale',
    'ruminant_exit',
    'transfer',
    'poultry_acquisition',
    'adjustment',
];

$check(
    production_population_source_types() === $expectedSources,
    'shared source policy exposes only approved integration owners'
);

$check(
    production_population_baseline_sources() === [
        'cycle_opening',
        'legacy_cutover',
    ],
    'baseline source policy separates new-cycle opening from legacy cutover'
);

$check(
    strpos(
        $source,
        "\$baselineSource === 'cycle_opening'"
    ) !== false
    && strpos(
        $source,
        "if (\$baselineDate !== (string)\$cycle['start_date'])"
    ) !== false
    && strpos(
        $source,
        "if (\$baselineQuantity !== (int)\$cycle['opening_headcount'])"
    ) !== false
    && strpos(
        $source,
        'Cycle-opening population baseline must use the production cycle start date.'
    ) !== false
    && strpos(
        $source,
        'Cycle-opening population baseline must equal the production cycle opening headcount.'
    ) !== false,
    'cycle-opening baseline is locked to cycle start date and opening headcount'
);

$check(
    production_population_valid_date('2026-09-14')
    && !production_population_valid_date('2026-02-30')
    && !production_population_valid_date('14-09-2026'),
    'date validation is strict and deterministic'
);

$compact = preg_replace('/\s+/', ' ', $source);

$check(
    preg_match(
        '/\$baselineSource\s*===\s*\x27legacy_cutover\x27/',
        $source
    ) === 1
    && preg_match(
        '/\(string\)\s*\$cycle\s*\[\s*\x27status\x27\s*\]\s*!==\s*\x27active\x27/',
        $source
    ) === 1
    && strpos(
        $source,
        'Legacy population cutover can only be established for an active cycle.'
    ) !== false,
    'legacy cutover is limited to active existing cycles'
);

$check(
    preg_match(
        '/\$quantity\s*<=\s*0/',
        $source
    ) === 1
    && preg_match(
        '/\$quantityDelta\s*=\s*\$quantity\s*\*\s*\(int\)\$movementTypes\[\$movementType\]/',
        $compact
    ) === 1,
    'callers provide positive quantity while the shared service derives direction'
);

$check(
    strpos(
        $compact,
        "if (\$sourceType === 'adjustment')"
    ) !== false
    && strpos(
        $compact,
        'Population adjustment requires a submission token.'
    ) !== false
    && strpos(
        $compact,
        'This population movement source requires a durable source record.'
    ) !== false,
    'manual adjustments are token-owned while integration movements require durable sources'
);

$check(
    strpos(
        $compact,
        'AND source_type = ? AND source_id = ? AND source_version = ? LIMIT 1'
    ) !== false
    && strpos(
        $compact,
        'AND source_version = ? AND movement_type = ?'
    ) === false
    && strpos(
        $compact,
        "&& (string)\$existing['movement_type'] === \$movementType"
    ) !== false
    && strpos(
        $compact,
        'request_token = ?'
    ) !== false
    && strpos(
        $compact,
        'This source/version already owns a different population movement.'
    ) !== false
    && strpos(
        $compact,
        'This population submission token has already been used for another movement.'
    ) !== false,
    'one source/version owns one movement and conflicting source/request reuse fails closed'
);

$check(
    strpos(
        $compact,
        'WHERE farm_id = ? AND source_type = ? AND source_id = ? AND source_version = ? LIMIT 1'
    ) !== false
    && strpos(
        $compact,
        "(int)\$existing['cycle_id'] === \$cycleId"
    ) !== false,
    'durable source/version identity is global across cycles'
);

$check(
    strpos(
        $compact,
        'This cycle already has a different V3 population baseline.'
    ) !== false
    && preg_match(
        '/\bUPDATE\s+production_population_baselines\b/i',
        $source
    ) !== 1
    && preg_match(
        '/\bDELETE\s+FROM\s+production_population_baselines\b/i',
        $source
    ) !== 1,
    'established baselines are immutable'
);

$check(
    preg_match(
        '/\bUPDATE\s+production_population_movements\b/i',
        $source
    ) !== 1
    && preg_match(
        '/\bDELETE\s+FROM\s+production_population_movements\b/i',
        $source
    ) !== 1,
    'historical population movements are immutable'
);

$check(
    function_exists(
        'production_population_project_delta_locked'
    )
    && strpos(
        $compact,
        'movement_date > ?'
    ) !== false
    && strpos(
        $compact,
        'GROUP BY movement_date'
    ) !== false
    && strpos(
        $compact,
        'ORDER BY movement_date ASC'
    ) !== false
    && strpos(
        $compact,
        'This population change would make a later recorded population balance negative.'
    ) !== false,
    'backdated outbound changes prove every later dated balance remains non-negative'
);

$projectReferences = substr_count(
    $source,
    'production_population_project_delta_locked'
);

$check(
    $projectReferences >= 4,
    'shared future-balance guard is defined and used by movement and reversal paths'
);

$check(
    strpos(
        $compact,
        "\$startedTransaction = !\$pdo->inTransaction();"
    ) !== false
    && substr_count(
        $source,
        '$startedTransaction = !$pdo->inTransaction();'
    ) >= 3
    && substr_count(
        $source,
        'if ($startedTransaction && $pdo->inTransaction())'
    ) >= 3,
    'write services preserve caller-owned transactions and roll back only their own transaction'
);

$check(
    substr_count(
        $source,
        'production_population_lock_cycle('
    ) >= 4
    && substr_count(
        $source,
        'production_population_lock_baseline('
    ) >= 4
    && strpos(
        $compact,
        'FOR UPDATE'
    ) !== false,
    'canonical writers use cycle/baseline locking instead of unlocked population writes'
);

$check(
    strpos(
        $compact,
        'Population movement cannot be earlier than the V3 population baseline.'
    ) !== false
    && strpos(
        $compact,
        "\$reversalDate = (string)\$original['movement_date'];"
    ) !== false
    && strpos(
        $compact,
        'Population reversal cannot be earlier than the V3 baseline.'
    ) !== false
    && preg_match(
        '/function\s+production_population_reverse_movement\s*\([^)]*string\s+\$reversalDate/s',
        $source
    ) !== 1,
    'reversal effective date is owned by the original movement, not caller input'
);

$check(
    strpos(
        $compact,
        'AND farm_id = ? AND cycle_id = ? LIMIT 1 FOR UPDATE'
    ) !== false
    && strpos(
        $compact,
        "=== 'reversal'"
    ) !== false
    && strpos(
        $compact,
        'A compensating reversal cannot itself be reversed.'
    ) !== false,
    'reversal target is same-tenant/same-cycle locked and reversal-of-reversal is blocked'
);

$check(
    strpos(
        $compact,
        "VALUES (?, ?, ?, 'reversal', ?, 'reversal', ?, 1, NULL, ?, ?, ?)"
    ) !== false
    && strpos(
        $compact,
        'reversal_of_id = ?'
    ) !== false,
    'correction is represented by one compensating reversal row linked to the original movement'
);

$legacyTables = [
    'layer_daily_records',
    'broiler_daily_records',
    'ruminant_daily_records',
    'ruminant_animals',
    'ruminant_animal_cycle_memberships',
    'ruminant_animal_exit_events',
    'sales_records',
];

$legacyWrite = false;

foreach ($legacyTables as $table) {
    $quoted = preg_quote($table, '/');

    if (
        preg_match(
            '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+`?'
            . $quoted
            . '`?\b/i',
            $source
        ) === 1
    ) {
        $legacyWrite = true;
        break;
    }
}

$check(
    !$legacyWrite,
    'shared population service performs no direct legacy operational-table writes'
);

$check(
    preg_match(
        '/\b(?:curl_|file_get_contents\s*\(\s*[\'"]https?:\/\/)/i',
        $source
    ) !== 1,
    'population service performs no provider or network I/O'
);

$check(
    strpos(
        $compact,
        'return null;'
    ) !== false
    && strpos(
        $compact,
        "'enabled' => true"
    ) !== false
    && strpos(
        $compact,
        "'quantity' => \$quantity"
    ) !== false,
    'read model distinguishes pre-baseline cycles and returns canonical V3 quantity after cutover'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    echo "V3.0 PRODUCTION POPULATION SERVICE: FAILED\n";
    exit(1);
}

echo "V3.0 PRODUCTION POPULATION SERVICE: PASSED\n";
