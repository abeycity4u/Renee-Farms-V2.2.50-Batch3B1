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
    $root . '/management/profitability.php'
);

$js = file_get_contents(
    $root . '/assets/js/management-profitability.js'
);

$check(
    str_contains($php, 'id="managementProfitabilityConfig"'),
    'Profitability emits centralized page config'
);

$check(
    str_contains($php, 'data-cycles="'),
    'Profitability config exposes cycle data'
);

$check(
    str_contains($php, 'data-production-type="'),
    'Profitability config exposes selected production type'
);

$check(
    str_contains($php, 'data-cycle-id="'),
    'Profitability config exposes selected cycle ID'
);

$check(
    str_contains(
        $php,
        'app_json_script($cycles)'
    ),
    'Profitability retains safe cycle JSON serialization'
);

$check(
    str_contains(
        $php,
        "versioned_asset('/assets/js/management-profitability.js')"
    ),
    'Profitability loads versioned external asset'
);

$check(
    str_contains($php, 'ENT_QUOTES | ENT_SUBSTITUTE'),
    'Profitability safely escapes dynamic config values'
);

$check(
    !preg_match(
        '/<script\b(?![^>]*\bsrc\s*=)[^>]*>.*?<\/script\s*>/is',
        $php
    ),
    'Profitability has zero literal inline script blocks'
);

$check(
    str_contains(
        $js,
        "document.getElementById('managementProfitabilityConfig')"
    ),
    'External asset reads centralized config'
);

$check(
    str_contains($js, 'dataset.cycles'),
    'External asset parses cycle config'
);

$check(
    str_contains($js, 'dataset.productionType'),
    'External asset reads selected production type'
);

$check(
    str_contains($js, 'dataset.cycleId'),
    'External asset reads selected cycle ID'
);

$check(
    str_contains($js, 'const productionTypes'),
    'External asset retains production-type catalog'
);

$check(
    str_contains($js, 'profitFarmType')
    && str_contains($js, 'profitProductionType')
    && str_contains($js, 'profitCycleId'),
    'External asset retains profitability filter bindings'
);

$check(
    str_contains($js, 'rebuildCycles'),
    'External asset retains cycle rebuilding'
);

$check(
    str_contains($js, 'rebuildProduction'),
    'External asset retains production rebuilding'
);

$check(
    str_contains(
        $js,
        "farmSelect.addEventListener('change'"
    ),
    'External asset retains farm-type change behavior'
);

$check(
    str_contains(
        $js,
        "productionSelect.addEventListener('change'"
    ),
    'External asset retains production-type change behavior'
);

$check(
    str_contains(
        $js,
        'rebuildProduction(selectedProductionType, selectedCycleId);'
    ),
    'External asset restores initial selected filter state'
);

$check(
    !str_contains($js, '<?php')
    && !str_contains($js, '<?='),
    'External Profitability asset contains no PHP'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
