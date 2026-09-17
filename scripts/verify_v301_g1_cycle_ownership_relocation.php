<?php
/**
 * V3.0.1 G1 — Cycle ownership relocation verifier.
 *
 * Source-only.
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

$edit =
    (string)file_get_contents(
        $root
        . '/management/production_cycle_edit.php'
    );

$service =
    (string)file_get_contents(
        $root
        . '/lib/production_cycle_service.php'
    );

$permissionJs =
    (string)file_get_contents(
        $root
        . '/assets/js/production-cycle-view-permissions.js'
    );

$checks = [];
$failures = [];

$check = static function (
    string $name,
    bool $ok
) use (
    &$checks,
    &$failures
): void {
    $checks[$name] =
        $ok;

    if (!$ok) {
        $failures[] =
            $name;
    }
};

$check(
    'NORMAL_PAGE_MANUAL_BIRD_COST_ROUTE_RETIRED',
    strpos(
        $cycles,
        "if (\$action === 'update_bird_cost_basis')"
    ) === false
);

$check(
    'NORMAL_PAGE_MANUAL_BIRD_COST_FORM_RETIRED',
    strpos(
        $cycles,
        'value="update_bird_cost_basis"'
    ) === false
    && strpos(
        $cycles,
        'name="bird_unit_cost"'
    ) === false
    && strpos(
        $cycles,
        '<strong>Poultry Bird Cost Basis</strong>'
    ) === false
);

$check(
    'NORMAL_PAGE_LOCAL_BIRD_COST_SQL_RETIRED',
    preg_match(
        '/UPDATE\s+production_cycles\s+SET\s+bird_unit_cost\s*=/i',
        $cycles
    ) !== 1
);

$check(
    'DEAD_BIRD_COST_PERMISSION_JS_RETIRED',
    strpos(
        $permissionJs,
        "action === 'update_bird_cost_basis'"
    ) === false
);

$check(
    'CENTRAL_BIRD_COST_MUTATOR_PRESERVED',
    strpos(
        $service,
        'function production_cycle_update_bird_cost_basis('
    ) !== false
);

$check(
    'EDIT_DERIVED_BIRD_COST_PATH_PRESERVED',
    strpos(
        $edit,
        'poultry_acquisition_cost_per_bird('
    ) !== false
    && strpos(
        $edit,
        'production_cycle_update_bird_cost_basis('
    ) !== false
);

$check(
    'CREATE_DERIVED_BIRD_COST_STORAGE_PRESERVED',
    strpos(
        $cycles,
        '$derivedBirdUnitCost'
    ) !== false
    && strpos(
        $cycles,
        "'bird_unit_cost' =>"
    ) !== false
);

$check(
    'NORMAL_PAGE_CUTOVER_ROUTE_RETIRED',
    strpos(
        $cycles,
        "if (\$action === 'confirm_population_cutover')"
    ) === false
    && strpos(
        $cycles,
        'value="confirm_population_cutover"'
    ) === false
);

$check(
    'NORMAL_PAGE_CUTOVER_ANCHOR_RETIRED',
    strpos(
        $cycles,
        'id="population-cutover"'
    ) === false
);

$check(
    'NORMAL_PAGE_LINKS_LEGACY_SETUP',
    strpos(
        $cycles,
        '/management/legacy_cycle_setup.php'
    ) !== false
    && strpos(
        $cycles,
        'Legacy Cycle Setup'
    ) !== false
);

$check(
    'MANAGE_CYCLE_LINK_RELOCATED',
    strpos(
        $manage,
        '/management/legacy_cycle_setup.php#population-cutover'
    ) !== false
    && strpos(
        $manage,
        '/management/production_cycles.php#population-cutover'
    ) === false
);

$check(
    'LEGACY_PAGE_AUTHENTICATED',
    strpos(
        $legacy,
        'requireLogin();'
    ) !== false
    && strpos(
        $legacy,
        'requireBusinessReportAccess();'
    ) !== false
);

$check(
    'LEGACY_PAGE_PRIVILEGED',
    strpos(
        $legacy,
        '!isPlatformOwner()'
    ) !== false
    && strpos(
        $legacy,
        "!hasRole('farm_admin')"
    ) !== false
);

$check(
    'LEGACY_PAGE_CSRF_PROTECTED',
    strpos(
        $legacy,
        'verify_csrf_token('
    ) !== false
    && strpos(
        $legacy,
        'http_response_code(419)'
    ) !== false
);

$check(
    'LEGACY_PAGE_CANONICAL_CUTOVER_ONCE',
    substr_count(
        $legacy,
        'production_cycle_cutover_population_v3('
    ) === 1
);

$check(
    'LEGACY_PAGE_CENTRAL_READ_MODEL_ONCE',
    substr_count(
        $legacy,
        'production_population_intelligence_active_cycle_snapshots('
    ) === 1
);

$check(
    'LEGACY_PAGE_FILTERS_CANONICAL_CYCLES',
    strpos(
        $legacy,
        "=== 'canonical'"
    ) !== false
    && strpos(
        $legacy,
        '$legacyCycles[]'
    ) !== false
);

$check(
    'LEGACY_PAGE_DOES_NOT_PREFILL_LEGACY_QUANTITY',
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
    ) === false
);

$check(
    'LEGACY_PAGE_PHYSICAL_CONFIRMATION_REQUIRED',
    strpos(
        $legacy,
        'physically verified live population'
    ) !== false
    && strpos(
        $legacy,
        'name="confirm_cutover"'
    ) !== false
);

$check(
    'LEGACY_PAGE_NO_DIRECT_CANONICAL_MUTATION_SQL',
    preg_match(
        '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+'
        . '`?(?:production_population_baselines|'
        . 'production_population_movements|production_cycles)`?/i',
        $legacy
    ) !== 1
);

$check(
    'PRODUCTION_CYCLES_PRG_DROPS_RETIRED_ACTIONS',
    strpos(
        $cycles,
        "'update_bird_cost_basis' =>"
    ) === false
    && strpos(
        $cycles,
        "'confirm_population_cutover' =>"
    ) === false
    && strpos(
        $cycles,
        "'cutover_form'"
    ) === false
    && strpos(
        $cycles,
        '$cutoverForm'
    ) === false
);

foreach (
    $checks
    as $name => $ok
) {
    echo $name
        . '='
        . (
            $ok
                ? 'PASS'
                : 'FAIL'
        )
        . PHP_EOL;
}

echo 'CHECK_COUNT='
    . count($checks)
    . PHP_EOL;

echo 'DATABASE_CONNECTION=NONE'
    . PHP_EOL;

echo 'DATABASE_WRITE=NONE'
    . PHP_EOL;

if ($failures) {
    echo 'FAILED='
        . implode(
            ',',
            $failures
        )
        . PHP_EOL;

    echo 'RESULT=FAIL'
        . PHP_EOL;

    exit(1);
}

echo 'RESULT=PASS'
    . PHP_EOL;

exit(0);
