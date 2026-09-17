<?php
/**
 * V3.0.1 G1 — Legacy population cutover ownership verifier.
 *
 * Source only.
 * No database connection.
 * No database writes.
 */

$root = dirname(__DIR__);

$cycles =
    (string)file_get_contents(
        $root
        . '/management/production_cycles.php'
    );

$legacy =
    (string)file_get_contents(
        $root
        . '/management/legacy_cycle_setup.php'
    );

$manage =
    (string)file_get_contents(
        $root
        . '/management/poultry_cycle.php'
    );

$compactLegacy =
    (string)preg_replace(
        '/\s+/',
        ' ',
        $legacy
    );

$checks = 0;
$failures = 0;

$check = static function (
    bool $ok,
    string $message
) use (
    &$checks,
    &$failures
): void {
    $checks++;

    if ($ok) {
        echo "PASS: {$message}\n";
        return;
    }

    $failures++;

    echo "FAIL: {$message}\n";
};

$check(
    $cycles !== '',
    'Production Cycles page exists and is readable'
);

$check(
    $legacy !== '',
    'Legacy Cycle Setup page exists and is readable'
);

$check(
    $manage !== '',
    'Poultry Manage Cycle page exists and is readable'
);

$check(
    strpos(
        $cycles,
        "if (\$action === 'confirm_population_cutover')"
    ) === false
    && strpos(
        $cycles,
        'value="confirm_population_cutover"'
    ) === false
    && strpos(
        $cycles,
        'id="population-cutover"'
    ) === false,
    'normal Production Cycles page no longer owns population cutover'
);

$check(
    strpos(
        $cycles,
        '/management/legacy_cycle_setup.php'
    ) !== false
    && strpos(
        $cycles,
        'Legacy Cycle Setup'
    ) !== false,
    'Production Cycles links to the dedicated compatibility page'
);

$check(
    strpos(
        $manage,
        '/management/legacy_cycle_setup.php#population-cutover'
    ) !== false
    && strpos(
        $manage,
        '/management/production_cycles.php#population-cutover'
    ) === false,
    'Poultry Manage Cycle points legacy cycles to dedicated setup'
);

$check(
    strpos(
        $legacy,
        "require_once(__DIR__ . '/../lib/production_cycle_service.php');"
    ) !== false
    && strpos(
        $legacy,
        "require_once(__DIR__ . '/../lib/production_population_intelligence.php');"
    ) !== false,
    'Legacy Cycle Setup loads canonical cycle and population authorities'
);

$check(
    strpos(
        $legacy,
        'requireLogin();'
    ) !== false
    && strpos(
        $legacy,
        'requireBusinessReportAccess();'
    ) !== false,
    'Legacy Cycle Setup retains authenticated business access'
);

$check(
    strpos(
        $legacy,
        '!isPlatformOwner()'
    ) !== false
    && strpos(
        $legacy,
        "!hasRole('farm_admin')"
    ) !== false,
    'Legacy Cycle Setup is restricted to Platform Owner or Farm Admin'
);

$check(
    strpos(
        $legacy,
        'verify_csrf_token('
    ) !== false
    && strpos(
        $legacy,
        'http_response_code(419)'
    ) !== false,
    'Legacy Cycle Setup POST action is CSRF protected'
);

$check(
    strpos(
        $compactLegacy,
        "\$action !== 'confirm_population_cutover'"
    ) !== false,
    'Legacy Cycle Setup accepts only the cutover compatibility action'
);

$check(
    substr_count(
        $legacy,
        'production_cycle_cutover_population_v3('
    ) === 1,
    'Legacy Cycle Setup delegates canonical cutover exactly once'
);

$check(
    strpos(
        $legacy,
        '$farmId'
    ) !== false
    && strpos(
        $legacy,
        "\$_SESSION['user_id']"
    ) !== false,
    'canonical cutover receives current farm and actor'
);

$check(
    strpos(
        $legacy,
        '$baselineQuantityRaw'
    ) !== false
    && strpos(
        $legacy,
        '$baselineDate'
    ) !== false,
    'canonical cutover receives explicit date and population'
);

$confirmPos =
    strpos(
        $legacy,
        'elseif (!$confirmed)'
    );

$servicePos =
    strpos(
        $legacy,
        'production_cycle_cutover_population_v3('
    );

$check(
    $confirmPos !== false
    && $servicePos !== false
    && $confirmPos < $servicePos,
    'physical confirmation is required before canonical cutover'
);

$check(
    strpos(
        $compactLegacy,
        "'confirmed' => false"
    ) !== false,
    'failed submissions never preserve confirmation automatically'
);

$check(
    strpos(
        $legacy,
        'production_population_establish_baseline('
    ) === false
    && strpos(
        $legacy,
        'production_population_record_movement('
    ) === false,
    'Legacy Cycle Setup does not bypass canonical cutover service'
);

$check(
    preg_match(
        '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+'
        . '`?(?:production_population_baselines|'
        . 'production_population_movements|production_cycles)`?/i',
        $legacy
    ) !== 1,
    'Legacy Cycle Setup owns no canonical mutation SQL'
);

$check(
    substr_count(
        $legacy,
        'production_population_intelligence_active_cycle_snapshots('
    ) === 1,
    'cutover eligibility uses centralized active-cycle population intelligence'
);

$check(
    strpos(
        $legacy,
        "=== 'canonical'"
    ) !== false
    && strpos(
        $legacy,
        '$legacyCycles[]'
    ) !== false,
    'canonical cycles are filtered out of Legacy Cycle Setup'
);

$check(
    strpos(
        $legacy,
        'id="population-cutover"'
    ) !== false
    && strpos(
        $legacy,
        'value="confirm_population_cutover"'
    ) !== false,
    'Legacy Cycle Setup exposes the dedicated compatibility form'
);

$check(
    strpos(
        $legacy,
        'name="baseline_quantity"'
    ) !== false
    && strpos(
        $legacy,
        '$cutoverForm[\'baseline_quantity\']'
    ) !== false,
    'population remains explicit preserved user input'
);

$check(
    strpos(
        $legacy,
        "['quantity']"
    ) === false
    && strpos(
        $legacy,
        'current_stock'
    ) === false
    && strpos(
        $legacy,
        'opening_headcount'
    ) === false,
    'Legacy Cycle Setup never prefills population from legacy estimates'
);

$check(
    strpos(
        $legacy,
        'name="confirm_cutover"'
    ) !== false
    && strpos(
        $legacy,
        'physically verified live population'
    ) !== false,
    'UI requires explicit physical-population confirmation'
);

$check(
    strpos(
        $legacy,
        'Daily Records'
    ) !== false
    && strpos(
        $legacy,
        'Animal Registry'
    ) !== false
    && strpos(
        $legacy,
        'Sales'
    ) !== false
    && strpos(
        $legacy,
        'opening stock'
    ) !== false,
    'UI rejects legacy operational records as automatic baseline sources'
);

$check(
    strpos(
        $compactLegacy,
        'Earlier historical records were not reconstructed or rewritten.'
    ) !== false
    && strpos(
        $compactLegacy,
        "before that date's population-changing activity"
    ) !== false,
    'UI explains historical non-reconstruction and same-day timing'
);

$check(
    preg_match(
        '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+'
        . '`?(?:production_population_baselines|'
        . 'production_population_movements)`?/i',
        $cycles
    ) !== 1,
    'Production Cycles still owns no canonical population writes'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    echo "V3.0 POPULATION CUTOVER ROUTE/UI: FAILED\n";
    echo "DATABASE_CONNECTION_USED=NO\n";
    echo "DATABASE_WRITE_PERFORMED=NO\n";
    exit(1);
}

echo "V3.0 POPULATION CUTOVER ROUTE/UI: PASSED\n";
echo "DATABASE_CONNECTION_USED=NO\n";
echo "DATABASE_WRITE_PERFORMED=NO\n";

exit(0);
