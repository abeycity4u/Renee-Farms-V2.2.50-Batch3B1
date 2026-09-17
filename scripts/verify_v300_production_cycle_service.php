<?php

$servicePath = __DIR__ . '/../lib/production_cycle_service.php';
$livestockPath = __DIR__ . '/../lib/livestock_types.php';
$populationPath = __DIR__ . '/../lib/production_population.php';
$routePath = __DIR__ . '/../management/production_cycles.php';

$checks = 0;
$failures = 0;

function verify_cycle_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;

    if ($condition) {
        echo "PASS: {$message}\n";
        return;
    }

    $failures++;
    echo "FAIL: {$message}\n";
}

function cycle_service_matches(
    string $source,
    string $pattern
): bool {
    return preg_match($pattern, $source) === 1;
}

verify_cycle_check(
    is_file($servicePath) && is_readable($servicePath),
    'shared production cycle service exists and is readable'
);

verify_cycle_check(
    is_file($livestockPath) && is_readable($livestockPath),
    'shared livestock type dependency exists and is readable'
);

verify_cycle_check(
    is_file($populationPath) && is_readable($populationPath),
    'shared population dependency exists and is readable'
);

verify_cycle_check(
    is_file($routePath) && is_readable($routePath),
    'live Production Cycles route exists and is readable'
);

$source = is_readable($servicePath)
    ? (string)file_get_contents($servicePath)
    : '';

$normalized = preg_replace('/\s+/', ' ', strtolower($source));
$normalized = is_string($normalized) ? trim($normalized) : '';

$routeSource = is_readable($routePath)
    ? (string)file_get_contents($routePath)
    : '';

require_once $servicePath;

verify_cycle_check(
    function_exists('livestock_type_validate_link')
    && function_exists('production_population_establish_baseline'),
    'service self-loads shared livestock and population dependencies'
);

verify_cycle_check(
    class_exists('ProductionCycleException'),
    'service exposes a dedicated production cycle domain exception'
);

verify_cycle_check(
    production_cycle_parse_type_choice('poultry:layer') === [
        'farm_type' => 'poultry',
        'production_type' => 'layer',
        'livestock_type_id' => null,
    ]
    && production_cycle_parse_type_choice('poultry:broiler') === [
        'farm_type' => 'poultry',
        'production_type' => 'broiler',
        'livestock_type_id' => null,
    ]
    && production_cycle_parse_type_choice('ruminant:custom:27') === [
        'farm_type' => 'ruminant',
        'production_type' => 'other',
        'livestock_type_id' => 27,
    ],
    'future one-field choices map to canonical cycle identity'
);

$invalidChoiceRejected = false;
try {
    production_cycle_parse_type_choice('poultry:rabbit');
} catch (InvalidArgumentException $e) {
    $invalidChoiceRejected = true;
}
verify_cycle_check(
    $invalidChoiceRejected,
    'invalid future cycle choices are rejected before database use'
);

verify_cycle_check(
    production_cycle_valid_date('2026-09-14')
    && !production_cycle_valid_date('2026-02-31'),
    'cycle date validation is strict and centralized'
);

$negativeHeadcountRejected = false;
try {
    production_cycle_nonnegative_int('-1', 'Opening headcount');
} catch (InvalidArgumentException $e) {
    $negativeHeadcountRejected = true;
}
verify_cycle_check(
    production_cycle_nonnegative_int('0', 'Opening headcount') === 0
    && production_cycle_nonnegative_int('500', 'Opening headcount') === 500
    && $negativeHeadcountRejected,
    'opening population whole-number validation is centralized'
);

verify_cycle_check(
    production_cycle_optional_money('', 'bird cost basis') === null
    && production_cycle_optional_money('12.50', 'bird cost basis') === 12.5,
    'optional poultry bird cost normalization is centralized'
);

verify_cycle_check(
    cycle_service_matches(
        $source,
        '/function\s+production_cycle_type_choices\s*\(.*?livestock_type_choices\s*\(/is'
    )
    && strpos($source, "'value' => 'poultry:layer'") !== false
    && strpos($source, "'value' => 'poultry:broiler'") !== false
    && strpos($source, "'value' => 'ruminant:' . (string)\$choice['value']") !== false,
    'cycle choice source composes poultry identities with shared livestock type choices'
);

verify_cycle_check(
    cycle_service_matches(
        $source,
        '/function\s+production_cycle_resolve_identity\s*\(.*?livestock_type_parse_choice\s*\(.*?livestock_type_validate_link\s*\(/is'
    ),
    'ruminant cycle identity delegates parsing and active same-farm validation to livestock authority'
);

verify_cycle_check(
    cycle_service_matches(
        $source,
        '/function\s+production_cycle_get\s*\(.*?FROM\s+production_cycles.*?WHERE\s+id\s*=\s*\?.*?AND\s+farm_id\s*=\s*\?/is'
    )
    && strpos($source, 'livestock_type_id') !== false,
    'canonical cycle lookup is tenant scoped and carries custom livestock identity'
);

verify_cycle_check(
    cycle_service_matches(
        $source,
        '/INSERT\s+INTO\s+production_cycles\s*\(\s*farm_id\s*,\s*cycle_code\s*,\s*farm_type\s*,\s*production_type\s*,\s*livestock_type_id\s*,\s*status\s*,\s*start_date\s*,\s*expected_end_date\s*,\s*opening_headcount\s*,\s*bird_unit_cost\s*,\s*notes\s*,\s*created_by\s*\)/is'
    ),
    'V3 creation writes the full cycle identity through one shared service'
);

verify_cycle_check(
    cycle_service_matches(
        $source,
        '/function\s+production_cycle_create_v3\s*\(.*?production_population_establish_baseline\s*\(\s*\$pdo\s*,\s*\$farmId\s*,\s*\$cycleId\s*,\s*\$startDate\s*,\s*\$openingHeadcount\s*,\s*\'cycle_opening\'/is'
    ),
    'V3 creation establishes the cycle-opening population baseline from the same start date and quantity'
);

verify_cycle_check(
    strpos($source, 'layer_daily_records') === false
    && strpos($source, 'broiler_daily_records') === false
    && strpos($source, 'ruminant_daily_records') === false,
    'production cycle service does not seed or own Daily Record SQL'
);

verify_cycle_check(
    strpos($source, 'sales_records') === false
    && strpos($source, 'farm_expenses') === false
    && strpos($source, 'stock_batches') === false
    && strpos($source, 'production_cycle_phases') === false
    && strpos($source, 'poultry_cycle_acquisitions') === false,
    'cycle service does not absorb sales expense batch lifecycle or acquisition ownership'
);

verify_cycle_check(
    substr_count($source, '$startedTransaction = !$pdo->inTransaction();') >= 3
    && substr_count($source, '$pdo->beginTransaction();') >= 3
    && substr_count($source, '$pdo->commit();') >= 3
    && substr_count($source, '$pdo->rollBack();') >= 3,
    'cycle writes compose safely with caller-owned or service-owned transactions'
);

verify_cycle_check(
    cycle_service_matches(
        $source,
        "/sqlState\s*===\s*'23000'.*?driverCode\s*===\s*1062/is"
    )
    && substr_count(
        $source,
        'This cycle code is already being used in this farm.'
    ) >= 2,
    'duplicate cycle codes are translated into one shared friendly domain error'
);

verify_cycle_check(
    cycle_service_matches(
        $source,
        '/function\s+production_cycle_update_bird_cost_basis\s*\(.*?UPDATE\s+production_cycles\s+SET\s+bird_unit_cost\s*=\s*\?.*?WHERE\s+id\s*=\s*\?.*?AND\s+farm_id\s*=\s*\?.*?AND\s+farm_type/is'
    )
    && strpos(
        $source,
        'Bird cost basis can be changed only for a poultry cycle.'
    ) !== false,
    'bird cost basis mutation is centralized and tenant-scoped to poultry'
);

verify_cycle_check(
    cycle_service_matches(
        $source,
        '/function\s+production_cycle_close_v3\s*\(.*?production_population_state\s*\(\s*\$pdo\s*,\s*\$farmId\s*,\s*\$cycleId\s*,\s*\$closeDate\s*\)/is'
    )
    && strpos(
        $source,
        'This cycle has not entered the V3 population contract.'
    ) !== false,
    'canonical closure requires an established V3 population contract'
);

verify_cycle_check(
    cycle_service_matches(
        $source,
        '/FROM\s+production_population_movements\s+WHERE\s+farm_id\s*=\s*\?\s+AND\s+cycle_id\s*=\s*\?\s+AND\s+movement_date\s*>\s*\?/is'
    ),
    'canonical closure rejects a date earlier than already-recorded population movements'
);

verify_cycle_check(
    strpos($source, "\$closingHeadcount = (int)\$population['quantity'];") !== false
    && cycle_service_matches(
        $source,
        '/UPDATE\s+production_cycles\s+SET\s+status\s*=\s*\?\s*,\s*close_date\s*=\s*\?\s*,\s*closing_headcount\s*=\s*\?.*?WHERE\s+id\s*=\s*\?.*?AND\s+farm_id\s*=\s*\?.*?AND\s+status\s*=\s*\?/is'
    ),
    'V3 closure persists the ledger-derived quantity with farm and active-state guards'
);

verify_cycle_check(
    strpos($source, 'opening_stock') === false
    && strpos($source, 'mortality') === false
    && strpos($source, 'getCycleCurrentStock') === false,
    'shared cycle authority contains no mortality-only legacy stock fallback'
);

verify_cycle_check(
    !cycle_service_matches(
        $source,
        '/DELETE\s+FROM\s+production_cycles/i'
    ),
    'production cycles are not destructively deleted by the shared authority'
);

verify_cycle_check(
    strpos($source, "'production_cycle_created_v3'") !== false
    && strpos(
        $source,
        "'production_cycle_bird_cost_basis_updated'"
    ) !== false
    && strpos($source, "'production_cycle_closed_v3'") !== false,
    'canonical production cycle mutations retain shared audit hooks'
);

$routeCreateStart = strpos(
    $routeSource,
    "if (\$action === 'create_cycle')"
);

$routeCreateEnd = strpos(
    $routeSource,
    "if (\$action === 'post_batch' && \$stockBatchTableExists)",
    $routeCreateStart !== false
        ? $routeCreateStart + 1
        : 0
);

$routeCreate = (
    $routeCreateStart !== false
    && $routeCreateEnd !== false
    && $routeCreateEnd > $routeCreateStart
)
    ? substr(
        $routeSource,
        $routeCreateStart,
        $routeCreateEnd - $routeCreateStart
    )
    : '';

verify_cycle_check(
    $routeCreate !== '',
    'live Create Cycle POST adapter is statically discoverable'
);

$routeCreateCallCount =
    preg_match_all(
        '/\\$newCycleId\\s*=\\s*'
        . 'production_cycle_create_v3\\s*\\(/s',
        $routeCreate,
        $routeCreateCallMatches
    );

verify_cycle_check(
    $routeCreateCallCount === 1
    && stripos(
        $routeCreate,
        'INSERT INTO production_cycles'
    ) === false,
    'live Create Cycle delegates cycle persistence exactly once to the canonical service'
);

verify_cycle_check(
    strpos(
        $routeCreate,
        'production_population_establish_baseline('
    ) === false
    && strpos(
        $routeCreate,
        'production_population_record_movement('
    ) === false
    && stripos(
        $routeCreate,
        'production_population_baselines'
    ) === false
    && stripos(
        $routeCreate,
        'production_population_movements'
    ) === false,
    'live Create Cycle route owns no direct population-ledger persistence'
);

verify_cycle_check(
    substr_count(
        $routeCreate,
        'INSERT INTO layer_daily_records'
    ) === 1
    && substr_count(
        $routeCreate,
        'INSERT INTO broiler_daily_records'
    ) === 1
    && substr_count(
        $routeCreate,
        'INSERT INTO ruminant_daily_records'
    ) === 1,
    'existing Layer Broiler and Ruminant opening Daily seeds remain caller-owned'
);

verify_cycle_check(
    substr_count(
        $routeCreate,
        'poultry_cycle_onboarding_record_initial('
    ) === 1,
    'existing poultry onboarding remains delegated exactly once after canonical cycle creation'
);

$routeBeginPos = strpos(
    $routeCreate,
    '$pdo->beginTransaction();'
);

$routeCreateCallPos = false;

if (
    preg_match(
        '/\\$newCycleId\\s*=\\s*'
        . 'production_cycle_create_v3\\s*\\(/s',
        $routeCreate,
        $routeCreateCallMatch,
        PREG_OFFSET_CAPTURE
    ) === 1
) {
    $routeCreateCallPos =
        (int)$routeCreateCallMatch[0][1];
}

$routeLayerSeedPos = stripos(
    $routeCreate,
    'INSERT INTO layer_daily_records'
);

$routeOnboardingPos = strpos(
    $routeCreate,
    'poultry_cycle_onboarding_record_initial('
);

$routeCommitPos = strpos(
    $routeCreate,
    '$pdo->commit();'
);

verify_cycle_check(
    $routeBeginPos !== false
    && $routeCreateCallPos !== false
    && $routeLayerSeedPos !== false
    && $routeOnboardingPos !== false
    && $routeCommitPos !== false
    && $routeBeginPos < $routeCreateCallPos
    && $routeCreateCallPos < $routeLayerSeedPos
    && $routeLayerSeedPos < $routeOnboardingPos
    && $routeOnboardingPos < $routeCommitPos,
    'cycle baseline Daily seed and poultry onboarding remain one caller-owned transaction'
);

verify_cycle_check(
    strpos(
        $routeCreate,
        "'cycle_code' =>"
    ) !== false
    && strpos(
        $routeCreate,
        "'farm_type' =>"
    ) !== false
    && strpos(
        $routeCreate,
        "'production_type' =>"
    ) !== false
    && strpos(
        $routeCreate,
        "'start_date' =>"
    ) !== false
    && strpos(
        $routeCreate,
        "'expected_end_date' =>"
    ) !== false
    && strpos(
        $routeCreate,
        "'opening_headcount' =>"
    ) !== false
    && strpos(
        $routeCreate,
        "'bird_unit_cost' =>"
    ) !== false
    && strpos(
        $routeCreate,
        "'notes' =>"
    ) !== false,
    'live adapter passes canonical cycle inputs rather than rebuilding persistence policy'
);

verify_cycle_check(
    strpos(
        $routeCreate,
        '$e instanceof ProductionCycleException'
    ) !== false
    && strpos(
        $routeCreate,
        'No cycle or related setup record was saved.'
    ) !== false,
    'canonical cycle-domain errors return safely with preserved setup semantics'
);

verify_cycle_check(
    strpos(
        $source,
        'Do not route live cycle creation here until'
    ) === false
    && strpos(
        $source,
        'live Create'
    ) !== false
    && strpos(
        $source,
        'caller-owned transaction'
    ) !== false,
    'service documentation reflects live canonical Create Cycle delegation'
);

$withoutComments = preg_replace('/^\s*\/\/.*$/m', '', $source);
$withoutComments = is_string($withoutComments)
    ? $withoutComments
    : $source;

verify_cycle_check(
    !cycle_service_matches($withoutComments, '/\bALTER\s+TABLE\b/i')
    && !cycle_service_matches($withoutComments, '/\bCREATE\s+TABLE\b/i')
    && !cycle_service_matches($withoutComments, '/\bDROP\s+TABLE\b/i')
    && strpos($normalized, 'http://') === false
    && strpos($normalized, 'https://') === false
    && strpos($normalized, 'curl') === false,
    'service owns application policy only and performs no schema network or provider work'
);

verify_cycle_check(
    strpos($source, 'config.php') === false
    && strpos($source, 'new PDO') === false
    && strpos($source, 'PDO(') === false,
    'service never opens its own database connection'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    echo "V3.0 PRODUCTION CYCLE SERVICE: FAILED\n";
    exit(1);
}

echo "V3.0 PRODUCTION CYCLE SERVICE: PASSED\n";
exit(0);
