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

$targets = [
    'ruminant/ruminant_daily_record.php',
    'poultry/layers_daily_record.php',
    'poultry/broiler_daily_record.php',
];

$check(
    str_contains($behaviors, "[data-open-record-modal]"),
    'Shared behavior layer recognizes data-open-record-modal contract'
);

$check(
    str_contains($behaviors, "typeof window.openRecordModal !== 'function'"),
    'Shared behavior safely guards missing openRecordModal function'
);

$check(
    str_contains($behaviors, 'window.openRecordModal();'),
    'Shared behavior invokes the page-owned openRecordModal function'
);

foreach ($targets as $relative) {
    $content = file_get_contents($root . '/' . $relative);

    $check(
        !str_contains($content, 'onclick="openRecordModal()"'),
        $relative . ' no longer uses inline openRecordModal handler'
    );

    $check(
        str_contains($content, 'data-open-record-modal'),
        $relative . ' opts into centralized open-record behavior'
    );

    $check(
        str_contains($content, 'function openRecordModal('),
        $relative . ' retains its page-specific modal implementation'
    );
}

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;
exit($failures === 0 ? 0 : 1);
