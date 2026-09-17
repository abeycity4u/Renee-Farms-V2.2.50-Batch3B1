<?php

$root = dirname(__DIR__);
$failures = 0;
$checks = 0;

$check = function (bool $ok, string $label) use (&$failures, &$checks): void {
    $checks++;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;

    if (!$ok) {
        $failures++;
    }
};

$php = file_get_contents(
    $root . '/includes/production_cycle_view_permissions.php'
);

$js = file_get_contents(
    $root . '/assets/js/production-cycle-view-permissions.js'
);

$check(
    str_contains(
        $php,
        "BASE_URL . '/assets/js/production-cycle-view-permissions.js'"
    ),
    'Production Cycle view permissions retains external asset fallback'
);

$check(
    str_contains(
        $php,
        "versioned_asset(\n            '/assets/js/production-cycle-view-permissions.js'"
    )
    || str_contains(
        $php,
        "versioned_asset('/assets/js/production-cycle-view-permissions.js')"
    ),
    'Production Cycle view permissions uses versioned external asset'
);

$check(
    str_contains($php, '$script = \'<script src="\''),
    'Permission filter emits external script tag'
);

$check(
    !str_contains($php, 'DOMContentLoaded'),
    'PHP contains no embedded Production Cycle browser behavior'
);

$check(
    !preg_match(
        '/<script\b(?![^>]*\bsrc\s*=)[^>]*>.*?<\/script\s*>/is',
        $php
    ),
    'Production Cycle permission include has zero literal inline script blocks'
);

$check(
    str_contains(
        $js,
        "document.addEventListener('DOMContentLoaded'"
    ),
    'External asset waits for DOM readiness'
);

$check(
    str_contains(
        $js,
        'form[method="post"],form[method="POST"]'
    ),
    'External asset retains POST form filtering'
);

$check(
    str_contains($js, "action === 'create_cycle'")
    && str_contains($js, "action === 'close_cycle'"),
    'External asset retains create/close cycle restrictions'
);

$check(
    !str_contains(
        $js,
        "action === 'update_bird_cost_basis'"
    ),
    'External asset no longer carries retired manual Bird Cost Basis behavior'
);

$check(
    !str_contains(
        $js,
        "action === 'record_poultry_acquisition'"
    ),
    'External asset keeps retired standalone poultry acquisition behavior absent'
);

$check(
    str_contains($js, "text === 'Manage Cycle'")
    && str_contains($js, "anchor.textContent = 'View Cycle'"),
    'External asset retains Manage Cycle to View Cycle relabel'
);

$check(
    str_contains(
        $js,
        'Manage acquisition records on Production Cycles'
    )
    && str_contains(
        $js,
        'View acquisition records on Production Cycles'
    ),
    'External asset retains acquisition relabel'
);

$check(
    str_contains(
        $js,
        'Manage lifecycle on Production Cycles'
    )
    && str_contains(
        $js,
        'View lifecycle on Production Cycles'
    ),
    'External asset retains lifecycle relabel'
);

$check(
    !str_contains($js, '<?php')
    && !str_contains($js, '<?='),
    'External Production Cycle asset contains no PHP'
);

$check(
    str_contains(
        $php,
        "BASE_URL . '/assets/css/prepaint-production-cycle-readonly.css'"
    )
    && str_contains(
        $php,
        "versioned_asset('/assets/css/prepaint-production-cycle-readonly.css')"
    )
    && str_contains(
        $php,
        '$style = \'<link rel="stylesheet" href="\''
    ),
    'Existing read-only prepaint uses the external versioned stylesheet contract'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
