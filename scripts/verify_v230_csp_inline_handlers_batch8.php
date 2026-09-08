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
$layers = file_get_contents($root . '/poultry/layers_daily_record.php');
$broilers = file_get_contents($root . '/poultry/broiler_daily_record.php');

$check(
    str_contains($behaviors, "[data-poultry-daily-delete-id]"),
    'Shared behavior recognizes poultry daily delete contract'
);

$check(
    str_contains($behaviors, 'dataset.poultryDailyDeleteId'),
    'Shared behavior reads poultry daily record id'
);

$check(
    str_contains($behaviors, 'dataset.poultryDailyDeleteType'),
    'Shared behavior reads poultry daily record type'
);

$check(
    str_contains($behaviors, 'Number.isInteger(recordId) || recordId <= 0'),
    'Shared behavior rejects invalid poultry daily record ids'
);

$check(
    str_contains($behaviors, "recordType === 'layer'"),
    'Shared behavior recognizes layer daily deletes'
);

$check(
    str_contains($behaviors, "recordType === 'broiler'"),
    'Shared behavior recognizes broiler daily deletes'
);

$check(
    str_contains($behaviors, 'window.deleteLayerDailyRecord(recordId);'),
    'Shared behavior delegates layer delete to page-owned function'
);

$check(
    str_contains($behaviors, 'window.deleteBroilerDailyRecord(recordId);'),
    'Shared behavior delegates broiler delete to page-owned function'
);

$check(
    !str_contains($layers, 'onclick="deleteLayerDailyRecord('),
    'Layer Daily Records no longer uses inline delete handler'
);

$check(
    substr_count($layers, 'data-poultry-daily-delete-type="layer"') === 1,
    'Layer Daily Records has exactly one layer delete type contract'
);

$check(
    substr_count($layers, 'data-poultry-daily-delete-id=') === 1,
    'Layer Daily Records has exactly one centralized delete id contract'
);

$check(
    str_contains($layers, 'function deleteLayerDailyRecord(recordId)'),
    'Layer Daily Records retains its page-owned delete implementation'
);

$check(
    !str_contains($broilers, 'onclick="deleteBroilerDailyRecord('),
    'Broiler Daily Records no longer uses inline delete handler'
);

$check(
    substr_count($broilers, 'data-poultry-daily-delete-type="broiler"') === 1,
    'Broiler Daily Records has exactly one broiler delete type contract'
);

$check(
    substr_count($broilers, 'data-poultry-daily-delete-id=') === 1,
    'Broiler Daily Records has exactly one centralized delete id contract'
);

$check(
    str_contains($broilers, 'function deleteBroilerDailyRecord(recordId)'),
    'Broiler Daily Records retains its page-owned delete implementation'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;
exit($failures === 0 ? 0 : 1);
