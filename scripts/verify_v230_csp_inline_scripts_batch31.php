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
    $root . '/includes/subscription_plan_farms.php'
);

$js = file_get_contents(
    $root . '/assets/js/subscription-plan-farms.js'
);

$check(
    str_contains($php, 'id="subscriptionPlanSeatUiConfig"'),
    'Platform Farms emits centralized seat UI config element'
);

$check(
    str_contains($php, 'data-plan-catalog="')
    && str_contains($php, 'data-seat-addons="'),
    'Platform Farms exposes plan catalog and seat add-on config'
);

$check(
    str_contains(
        $php,
        "BASE_URL . '/assets/js/subscription-plan-farms.js'"
    ),
    'Platform Farms retains external asset fallback'
);

$check(
    str_contains(
        $php,
        '/assets/js/subscription-plan-farms.js'
    )
    && str_contains($php, 'versioned_asset'),
    'Platform Farms uses versioned external asset when available'
);

$check(
    str_contains($php, 'ENT_QUOTES | ENT_SUBSTITUTE'),
    'Platform Farms safely escapes config and asset output'
);

$check(
    !str_contains($php, 'DOMContentLoaded'),
    'PHP contains no embedded seat UI browser behavior'
);

$check(
    !preg_match(
        '/<script\b(?![^>]*\bsrc\s*=)[^>]*>.*?<\/script\s*>/is',
        $php
    ),
    'Platform Farms include has zero literal inline script blocks'
);

$check(
    str_contains(
        $js,
        "document.getElementById('subscriptionPlanSeatUiConfig')"
    ),
    'External asset reads seat UI config element'
);

$check(
    str_contains(
        $js,
        "document.getElementById('farmAccountForm')"
    ),
    'External asset retains Farm Account form binding'
);

$check(
    str_contains($js, 'dataset.planCatalog')
    && str_contains($js, 'dataset.seatAddons'),
    'External asset parses both server-generated config datasets'
);

$check(
    str_contains($js, 'poultry_manager')
    && str_contains($js, 'ruminant_manager')
    && str_contains($js, 'sales_rep')
    && str_contains($js, 'viewer'),
    'External asset retains all seat role contracts'
);

$check(
    str_contains($js, 'role_limits['),
    'External asset retains effective role-limit field updates'
);

$check(
    str_contains($js, 'seat_addons['),
    'External asset retains purchased extra-seat inputs'
);

$check(
    str_contains($js, 'included_role_limits'),
    'External asset retains plan included-seat calculations'
);

$check(
    str_contains($js, 'Included seats:')
    && str_contains($js, 'Total seats:')
    && str_contains($js, 'Extra seats'),
    'External asset retains seat summary UI'
);

$check(
    str_contains($js, "addon.min = '0'")
    && str_contains($js, "addon.max = '500'"),
    'External asset retains seat add-on bounds'
);

$check(
    str_contains($js, 'included + extra'),
    'External asset retains effective seat total calculation'
);

$check(
    str_contains($js, 'hasLivestock'),
    'External asset retains shared Sales/Viewer livestock relevance logic'
);

$check(
    str_contains($js, "plan.addEventListener('change', refresh)")
    && str_contains($js, "element.addEventListener('change', refresh)")
    && str_contains($js, "extras[role].addEventListener('input', refresh)"),
    'External asset retains reactive plan/module/add-on refresh behavior'
);

$check(
    str_contains($js, 'refresh();'),
    'External asset performs initial seat UI refresh'
);

$check(
    !str_contains($js, '<?php')
    && !str_contains($js, '<?='),
    'External subscription seat asset contains no PHP'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
