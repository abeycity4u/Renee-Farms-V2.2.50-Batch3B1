<?php
/**
 * V3.0 focused static contract verifier:
 * Production Cycles population-cutover route and UI.
 *
 * No database connection is opened and no migration is executed.
 */

$root = dirname(__DIR__);
$pagePath = $root . '/management/production_cycles.php';

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

$source = is_file($pagePath)
    ? file_get_contents($pagePath)
    : false;

$check(
    $source !== false,
    'Production Cycles page exists and is readable'
);

if ($source === false) {
    echo "\nChecks: {$checks}\n";
    echo "Failures: {$failures}\n";
    echo "V3.0 POPULATION CUTOVER ROUTE/UI: FAILED\n";
    echo "DATABASE_CONNECTION_USED=NO\n";
    echo "DATABASE_WRITE_PERFORMED=NO\n";
    exit(1);
}

$compact = preg_replace('/\s+/', ' ', $source) ?? $source;

$routeStart = strpos(
    $source,
    "if (\$action === 'confirm_population_cutover')"
);

$routeEnd = strpos(
    $source,
    "if (\$action === 'close_cycle')",
    $routeStart !== false ? $routeStart : 0
);

$route = (
    $routeStart !== false
    && $routeEnd !== false
    && $routeEnd > $routeStart
)
    ? substr($source, $routeStart, $routeEnd - $routeStart)
    : null;

$uiStart = strpos($source, 'id="population-cutover"');
$uiEnd = strpos(
    $source,
    '<strong>Poultry Bird Cost Basis</strong>',
    $uiStart !== false ? $uiStart : 0
);

$ui = (
    $uiStart !== false
    && $uiEnd !== false
    && $uiEnd > $uiStart
)
    ? substr($source, $uiStart, $uiEnd - $uiStart)
    : null;

$uiCompact = $ui !== null
    ? (preg_replace('/\\s+/', ' ', $ui) ?? $ui)
    : null;

$check(
    strpos(
        $source,
        "require_once(__DIR__ . '/../lib/production_cycle_service.php');"
    ) !== false,
    'page loads the shared production cycle service'
);

$check(
    preg_match(
        '/REQUEST_METHOD[^;]*POST.*?'
        . 'isPlatformOwner\(\).*?hasRole\(\s*[\'"]farm_admin[\'"]\s*\).*?'
        . '403/s',
        $source
    ) === 1,
    'all Production Cycles POST mutations remain Platform Owner/Farm Admin only'
);

$check(
    strpos($source, 'verify_csrf_token(') !== false
    && strpos($source, "http_response_code(419)") !== false,
    'existing CSRF gate still protects POST mutations'
);

$check(
    $route !== null,
    'population cutover POST adapter is isolated and discoverable'
);

if ($route !== null) {
    $serviceCallCount = preg_match_all(
        '/\bproduction_cycle_cutover_population_v3\s*\(/',
        $route
    );

    $check(
        $serviceCallCount === 1,
        'POST adapter delegates exactly once to the shared cutover service'
    );

    $check(
        strpos($route, '$tenantFarmId') !== false
        && strpos($route, '$_SESSION[\'user_id\']') !== false,
        'POST adapter passes current tenant and actor to the shared service'
    );

    $check(
        strpos($route, '$baselineQuantityRaw') !== false
        && strpos($route, '$baselineDate') !== false,
        'POST adapter forwards explicit user-entered date and headcount'
    );

    $confirmGuard = strpos($route, 'elseif (!$confirmed)');
    $serviceCall = strpos(
        $route,
        'production_cycle_cutover_population_v3('
    );

    $check(
        $confirmGuard !== false
        && $serviceCall !== false
        && $confirmGuard < $serviceCall,
        'explicit confirmation is enforced before the cutover writer can run'
    );

    $check(
        preg_match(
            '/[\'"]confirmed[\'"]\s*=>\s*false/',
            $route
        ) === 1,
        'failed submissions require fresh confirmation'
    );

    $check(
        strpos(
            $route,
            'production_population_establish_baseline('
        ) === false
        && strpos(
            $route,
            'production_population_record_movement('
        ) === false,
        'route does not bypass the shared cycle-service boundary'
    );

    $check(
        preg_match(
            '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\b/i',
            $route
        ) !== 1,
        'cutover POST adapter contains no direct write SQL'
    );

    $check(
        strpos($route, 'production_population_baselines') === false
        && strpos($route, 'production_population_movements') === false,
        'cutover POST adapter does not own population-table persistence'
    );
} else {
    for ($i = 0; $i < 8; $i++) {
        $check(false, 'cutover route contract unavailable');
    }
}

$check(
    strpos($source, 'production_population_state(') !== false
    && strpos($source, '$populationCutoverCycles[] = $cycle;') !== false,
    'cutover eligibility is derived from canonical population state'
);

$check(
    $ui !== null,
    'population cutover UI section is isolated and discoverable'
);

if ($ui !== null) {
    $check(
        strpos(
            substr(
                $source,
                max(0, $uiStart - 200),
                250
            ),
            "isPlatformOwner() || hasRole('farm_admin')"
        ) !== false,
        'cutover management card is rendered only for privileged cycle managers'
    );

    $check(
        strpos($ui, 'name="csrf_token"') !== false
        && strpos(
            $ui,
            'value="confirm_population_cutover"'
        ) !== false,
        'cutover form carries CSRF token and explicit action'
    );

    $check(
        strpos($ui, 'name="baseline_quantity"') !== false
        && strpos(
            $ui,
            '$cutoverForm[\'baseline_quantity\']'
        ) !== false,
        'baseline quantity is an explicit preserved user input'
    );

    $check(
        strpos($ui, 'getCycleCurrentStock(') === false
        && strpos($ui, 'current_stock') === false
        && strpos($ui, 'opening_headcount') === false,
        'cutover UI never prefills baseline quantity from legacy stock estimates'
    );

    $check(
        strpos($ui, 'name="confirm_cutover"') !== false
        && strpos($ui, 'required') !== false
        && strpos(
            $ui,
            'physically verified live population'
        ) !== false,
        'UI requires explicit physical-population confirmation'
    );

    $check(
        strpos($ui, 'Daily Records') !== false
        && strpos($ui, 'Animal Registry') !== false
        && strpos($ui, 'Sales') !== false
        && strpos($ui, 'opening stock') !== false,
        'UI explicitly rejects legacy operational records as baseline inference sources'
    );

    $check(
        $uiCompact !== null
        && strpos($uiCompact, 'not silently rewritten') !== false
        && strpos(
            $uiCompact,
            'before that date\'s V3 population-changing activity is recorded'
        ) !== false,
        'UI explains immutable baseline and same-day cutover timing'
    );
} else {
    for ($i = 0; $i < 7; $i++) {
        $check(false, 'cutover UI contract unavailable');
    }
}

$check(
    preg_match(
        '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+'
        . '`?production_population_baselines`?/i',
        $source
    ) !== 1
    && preg_match(
        '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+'
        . '`?production_population_movements`?/i',
        $source
    ) !== 1,
    'Production Cycles page performs no direct canonical population writes'
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
