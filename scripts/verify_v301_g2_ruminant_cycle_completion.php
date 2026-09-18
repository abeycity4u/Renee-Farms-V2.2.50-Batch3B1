<?php

/**
 * V3.0.1 G2 — ruminant production-cycle completion.
 *
 * Source-only.
 * No database connection.
 * No database writes.
 */

$root = dirname(__DIR__);

$read = static function (
    string $path
): string {
    return is_file($path)
        ? (string)file_get_contents($path)
        : '';
};

$membership =
    $read(
        $root
        . '/lib/ruminant_cycle_membership.php'
    );

$completion =
    $read(
        $root
        . '/lib/ruminant_cycle_completion.php'
    );

$page =
    $read(
        $root
        . '/management/ruminant_cycle.php'
    );

$overview =
    $read(
        $root
        . '/management/production_cycles.php'
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
    $checks[$name] = $ok;

    if (!$ok) {
        $failures[] = $name;
    }
};

$check(
    'MEMBERSHIP_AUTHORITY_READABLE',
    $membership !== ''
);

$check(
    'COMPLETION_COORDINATOR_READABLE',
    $completion !== ''
);

$check(
    'RUMINANT_MANAGE_PAGE_READABLE',
    $page !== ''
);

$check(
    'MEMBERSHIP_ADD_LOCKS_CYCLE',
    strpos(
        $membership,
        "farm_type='ruminant' LIMIT 1 FOR UPDATE"
    ) !== false
);

$check(
    'COMPLETION_BLOCKER_HELPER_EXISTS_ONCE',
    substr_count(
        $membership,
        'function ruminant_cycle_completion_membership_blockers('
    ) === 1
);

$check(
    'BLOCKERS_INCLUDE_OPEN_OR_LATER_MEMBERSHIPS',
    strpos(
        $membership,
        'm.end_date IS NULL'
    ) !== false
    && strpos(
        $membership,
        'm.end_date > ?'
    ) !== false
);

$check(
    'BLOCKER_HELPER_SUPPORTS_LOCKING',
    strpos(
        $membership,
        "if (\$forUpdate)"
    ) !== false
    && strpos(
        $membership,
        "\$sql .= ' FOR UPDATE';"
    ) !== false
);

$check(
    'COMPLETION_FUNCTION_EXISTS_ONCE',
    substr_count(
        $completion,
        'function ruminant_cycle_end_production('
    ) === 1
);

$check(
    'COORDINATOR_REQUIRES_CANONICAL_SERVICES',
    strpos(
        $completion,
        "'/production_cycle_service.php'"
    ) !== false
    && strpos(
        $completion,
        "'/ruminant_cycle_membership.php'"
    ) !== false
);

$check(
    'COORDINATOR_RESTRICTS_RUMINANT',
    strpos(
        $completion,
        "!== 'ruminant'"
    ) !== false
);

$check(
    'COORDINATOR_REQUIRES_ACTIVE_CYCLE',
    strpos(
        $completion,
        "!== 'active'"
    ) !== false
);

$check(
    'COORDINATOR_CHECKS_MEMBERSHIP_BLOCKERS_BEFORE_CLOSE',
    strpos(
        $completion,
        'ruminant_cycle_completion_membership_blockers('
    ) !== false
    && strpos(
        $completion,
        'production_cycle_close_v3('
    ) !== false
    && strpos(
        $completion,
        'ruminant_cycle_completion_membership_blockers('
    )
        < strpos(
            $completion,
            'production_cycle_close_v3('
        )
);

$check(
    'COORDINATOR_DELEGATES_CANONICAL_CLOSE_ONCE',
    substr_count(
        $completion,
        'production_cycle_close_v3('
    ) === 1
);

$check(
    'COORDINATOR_OWNS_NO_DIRECT_PERSISTENCE_SQL',
    preg_match(
        '/\b(?:INSERT|UPDATE|DELETE)\s+/i',
        $completion
    ) !== 1
);

$check(
    'COORDINATOR_DOES_NOT_AUTO_CLOSE_MEMBERSHIP',
    strpos(
        $completion,
        'ruminant_cycle_membership_close('
    ) === false
);

$check(
    'COORDINATOR_DOES_NOT_AUTO_TRANSFER',
    strpos(
        $completion,
        'ruminant_cycle_transfer_record('
    ) === false
);

$check(
    'COORDINATOR_DOES_NOT_AUTO_EXIT',
    strpos(
        $completion,
        'ruminant_manual_exit('
    ) === false
    && strpos(
        $completion,
        'ruminant_lifecycle_apply_exit_boundary('
    ) === false
);

$check(
    'POSITIVE_POPULATION_EXPLICITLY_ALLOWED',
    strpos(
        $completion,
        'Positive population is valid here.'
    ) !== false
    && strpos(
        $page,
        'A positive live population does not block'
    ) !== false
);

$check(
    'ZERO_POPULATION_NOT_REQUIRED',
    strpos(
        $completion,
        '=== 0'
    ) === false
    && strpos(
        $completion,
        '!== 0'
    ) === false
);

$check(
    'PAGE_AUTHENTICATED_AND_TENANT_SCOPED',
    strpos(
        $page,
        'requireLogin();'
    ) !== false
    && strpos(
        $page,
        'requireBusinessReportAccess();'
    ) !== false
    && strpos(
        $page,
        'requireCurrentFarmId();'
    ) !== false
);

$check(
    'PAGE_DELEGATED_VIEW_SUPPORTED',
    strpos(
        $page,
        "hasPermission(\n        getUserType(),\n        'production_cycles'"
    ) !== false
);

$check(
    'PAGE_OWNER_OR_FARM_ADMIN_MUTATION_BOUNDARY_WITH_TENANT_SAFE_COPY',
    strpos(
        $page,
        "\$canEndProduction =\n    isPlatformOwner()\n    || hasRole('farm_admin');"
    ) !== false
    && strpos(
        $page,
        'Only a Farm Admin can end production.'
    ) !== false
    && strpos(
        $page,
        'Only the Platform Owner or Farm Admin'
    ) === false
    && strpos(
        $page,
        'Production-cycle management access required.'
    ) !== false
);

$check(
    'PAGE_CSRF_PROTECTED',
    strpos(
        $page,
        'require_valid_csrf_post();'
    ) !== false
    && strpos(
        $page,
        'csrf_field()'
    ) !== false
);

$check(
    'PAGE_END_PRODUCTION_ACTION_ONCE',
    substr_count(
        $page,
        'value="end_production"'
    ) === 1
);

$check(
    'PAGE_DELEGATES_COMPLETION_ONCE',
    substr_count(
        $page,
        'ruminant_cycle_end_production('
    ) === 1
);

$check(
    'PAGE_HAS_LEGACY_SETUP_ROUTE',
    strpos(
        $page,
        '/management/legacy_cycle_setup.php#population-cutover'
    ) !== false
);

$check(
    'PAGE_DISCLOSES_MEMBERSHIP_BLOCKERS',
    strpos(
        $page,
        'Animal Membership Readiness'
    ) !== false
    && strpos(
        $page,
        'Animal Profile'
    ) !== false
);

$check(
    'PAGE_EXPLAINS_NO_PHYSICAL_EXIT',
    strpos(
        $page,
        'Ending production does not remove animals'
    ) !== false
);

$check(
    'PAGE_HAS_NO_DIRECT_SQL',
    preg_match(
        '/->\s*(?:prepare|query|exec)\s*\(/',
        $page
    ) !== 1
);

$check(
    'DASHBOARD_LINKS_RUMINANT_MANAGE_CYCLE',
    strpos(
        $overview,
        '/management/ruminant_cycle.php?id='
    ) !== false
);

$check(
    'G3_DASHBOARD_STILL_OWNS_NO_RUMINANT_CLOSE_POST',
    strpos(
        $overview,
        'close_ruminant'
    ) === false
    && strpos(
        $overview,
        'end_ruminant'
    ) === false
);

foreach (
    $checks
    as $name => $ok
) {
    echo $name
        . '='
        . ($ok ? 'PASS' : 'FAIL')
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

    echo "RESULT=FAIL\n";

    exit(1);
}

echo "RESULT=PASS\n";

exit(0);
