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

$behaviors = file_get_contents($root . '/assets/js/app-behaviors.js');
$farms = file_get_contents($root . '/management/farms.php');

$check(
    str_contains($behaviors, "[data-farm-action]"),
    'Shared behavior recognizes farm action contract'
);

$check(
    str_contains($behaviors, 'dataset.farmAction'),
    'Shared behavior reads farm action'
);

$check(
    str_contains($behaviors, "action === 'suspend'"),
    'Shared behavior recognizes farm suspend action'
);

$check(
    str_contains($behaviors, 'window.confirmFarmSuspend(form);'),
    'Shared behavior delegates farm suspend to page-owned function'
);

$check(
    str_contains($behaviors, "action === 'delete'"),
    'Shared behavior recognizes farm delete action'
);

$check(
    str_contains($behaviors, 'dataset.farmName'),
    'Shared behavior reads farm name from declarative contract'
);

$check(
    str_contains($behaviors, 'window.confirmFarmDeletion(form, farmName);'),
    'Shared behavior delegates farm deletion to page-owned function'
);

$check(
    !preg_match('/onclick=.*(?:confirmFarmSuspend|confirmFarmDeletion)/', $farms),
    'Platform Farms no longer uses inline suspend/delete handlers'
);

$check(
    substr_count($farms, 'data-farm-action="suspend"') === 1,
    'Platform Farms has exactly one centralized suspend action contract'
);

$check(
    substr_count($farms, 'data-farm-action="delete"') === 1,
    'Platform Farms has exactly one centralized delete action contract'
);

$check(
    substr_count($farms, 'data-farm-name=') === 1,
    'Platform Farms has exactly one farm-name data contract'
);

$check(
    str_contains($farms, 'data-farm-name="<?php echo app_attr($farm[\'name\']); ?>"'),
    'Platform Farms safely HTML-escapes farm name in data attribute'
);

$check(
    str_contains($farms, 'function confirmFarmDeletion(form, farmName)'),
    'Platform Farms retains page-owned farm deletion confirmation'
);

$check(
    str_contains($farms, 'function confirmFarmSuspend(form)'),
    'Platform Farms retains page-owned farm suspension confirmation'
);

$check(
    str_contains($farms, 'Permanent tenant deletion'),
    'Platform Farms retains second-stage permanent deletion confirmation'
);

$check(
    str_contains($farms, "button.name='delete_farm'"),
    'Platform Farms deletion flow still submits delete_farm marker'
);

$check(
    str_contains($farms, "button.name = 'suspend_farm'"),
    'Platform Farms suspension flow still submits suspend_farm marker'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;
exit($failures === 0 ? 0 : 1);
