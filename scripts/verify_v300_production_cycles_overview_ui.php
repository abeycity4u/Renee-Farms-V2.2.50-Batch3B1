<?php

/**
 * V3.0 focused static verifier:
 * Production Cycles overview simplification.
 *
 * No database connection is opened.
 * No production data is written.
 */

$root = dirname(__DIR__);

$pagePath = $root . '/management/production_cycles.php';
$jsPath = $root . '/assets/js/production-cycles.js';

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

$page = is_file($pagePath)
    ? file_get_contents($pagePath)
    : false;

$js = is_file($jsPath)
    ? file_get_contents($jsPath)
    : false;

$check(
    $page !== false,
    'Production Cycles page exists and is readable'
);

$check(
    $js !== false,
    'Production Cycles JavaScript exists and is readable'
);

if ($page === false || $js === false) {
    echo "\nChecks: {$checks}\n";
    echo "Failures: {$failures}\n";
    echo "V3.0 PRODUCTION CYCLES OVERVIEW UX: FAILED\n";
    echo "DATABASE_CONNECTION_USED=NO\n";
    echo "DATABASE_WRITE_PERFORMED=NO\n";
    exit(1);
}

$pageCompact = preg_replace('/\s+/', ' ', $page) ?? $page;
$jsCompact = preg_replace('/\s+/', ' ', $js) ?? $js;

$toolsMarker = strpos($page, 'id="cycle-tools"');
$recentMarker = strpos($page, 'id="recent-cycles"');
$createMarker = strpos($page, 'id="create-cycle"');

$check(
    strpos(
        $pageCompact,
        'Start here to see the production cycles in this farm.'
    ) !== false
    && strpos(
        $pageCompact,
        'Choose a cycle below to work on it.'
    ) !== false,
    'overview copy directs users toward choosing a cycle rather than scanning forms'
);

$check(
    strpos($page, 'href="#recent-cycles"') !== false
    && strpos($page, 'Choose a Cycle') !== false,
    'overview provides one clear Choose a Cycle action'
);

$check(
    strpos($page, 'data-open-cycle-tools') !== false
    && strpos($page, 'href="#create-cycle"') !== false
    && strpos($page, 'New Cycle') !== false,
    'privileged users retain one deliberate New Cycle action'
);

$check(
    $toolsMarker !== false
    && strpos($page, '<details', max(0, $toolsMarker - 200)) !== false,
    'setup and maintenance tools are inside a collapsible details container'
);

$check(
    strpos($page, 'Setup &amp; Maintenance') !== false
    && strpos($page, 'Open tools') !== false,
    'collapsed maintenance area has a clear user-facing label'
);

$check(
    $createMarker !== false
    && $toolsMarker !== false
    && $createMarker > $toolsMarker,
    'Create Cycle form lives inside the maintenance area'
);

$check(
    $recentMarker !== false
    && $toolsMarker !== false
    && $recentMarker > $toolsMarker,
    'Production Cycles list remains after the maintenance area'
);

$toolsEnd = $recentMarker !== false
    ? $recentMarker
    : strlen($page);

$toolsSection = (
    $toolsMarker !== false
    && $toolsEnd > $toolsMarker
)
    ? substr($page, $toolsMarker, $toolsEnd - $toolsMarker)
    : null;

$check(
    $toolsSection !== null,
    'maintenance section is statically discoverable'
);

if ($toolsSection !== null) {
    $check(
        strpos($toolsSection, 'value="create_cycle"') !== false,
        'Create Cycle form remains available inside maintenance tools'
    );

    $check(
        strpos($toolsSection, 'value="close_cycle"') === false,
        'legacy Close Cycle form remains retired from Production Cycles maintenance tools'
    );

    $check(
        strpos(
            $toolsSection,
            'value="confirm_population_cutover"'
        ) !== false,
        'V3 population cutover remains available inside maintenance tools'
    );

    $check(
        strpos(
            $toolsSection,
            'value="update_bird_cost_basis"'
        ) !== false,
        'poultry bird cost maintenance remains available inside maintenance tools'
    );

    $check(
        strpos(
            $toolsSection,
            'value="record_poultry_acquisition"'
        ) !== false,
        'poultry acquisition maintenance remains available inside maintenance tools'
    );

    $check(
        strpos(
            $toolsSection,
            'value="set_initial_poultry_phase"'
        ) !== false
        || strpos(
            $toolsSection,
            'value="transition_poultry_phase"'
        ) !== false,
        'poultry lifecycle maintenance remains available inside maintenance tools'
    );
} else {
    for ($i = 0; $i < 6; $i++) {
        $check(false, 'maintenance form contract unavailable');
    }
}

$adminWindowStart = $toolsMarker !== false
    ? max(0, $toolsMarker - 1500)
    : 0;

$adminWindow = substr(
    $page,
    $adminWindowStart,
    $toolsMarker !== false
        ? ($toolsMarker - $adminWindowStart + 100)
        : 0
);

$check(
    strpos(
        $adminWindow,
        "isPlatformOwner() || hasRole('farm_admin')"
    ) !== false,
    'maintenance area remains restricted to Platform Owner/Farm Admin'
);

$check(
    strpos($page, '<h5 class="mb-0">Production Cycles</h5>') !== false,
    'cycle list is labelled as the primary Production Cycles section'
);

$check(
    strpos(
        $page,
        '/management/poultry_cycle.php?id='
    ) !== false
    && strpos($page, 'Manage Cycle') !== false,
    'existing poultry Manage Cycle route remains intact'
);

$check(
    substr_count($js, 'openTargetedCycleTools') >= 2,
    'JavaScript contains the maintenance-target opening helper and its calls'
);

$check(
    strpos(
        $jsCompact,
        "document.querySelectorAll('[data-open-cycle-tools]')"
    ) !== false
    && strpos(
        $jsCompact,
        'tools.open = true'
    ) !== false,
    'New Cycle action deliberately opens the maintenance area'
);

$check(
    strpos(
        $jsCompact,
        "window.addEventListener('hashchange', openTargetedCycleTools)"
    ) !== false,
    'deep links to maintenance tools reopen the collapsed area'
);

$check(
    strpos(
        $jsCompact,
        'if (target && tools.contains(target))'
    ) !== false,
    'hash navigation opens maintenance only for targets inside that area'
);

$check(
    strpos(
        $page,
        "<?php echo \$flash !== null ? 'open' : ''; ?>"
    ) !== false,
    'validation/error feedback automatically keeps maintenance tools open'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    echo "V3.0 PRODUCTION CYCLES OVERVIEW UX: FAILED\n";
    echo "DATABASE_CONNECTION_USED=NO\n";
    echo "DATABASE_WRITE_PERFORMED=NO\n";
    exit(1);
}

echo "V3.0 PRODUCTION CYCLES OVERVIEW UX: PASSED\n";
echo "DATABASE_CONNECTION_USED=NO\n";
echo "DATABASE_WRITE_PERFORMED=NO\n";
