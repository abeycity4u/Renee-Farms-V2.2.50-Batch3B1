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
    'poultry/layers_daily_record.php',
    'poultry/broiler_daily_record.php',
    'ruminant/ruminant_daily_record.php',
];

$check(
    str_contains($behaviors, "[data-check-existing-record]"),
    'Shared behavior layer recognizes data-check-existing-record contract'
);

$check(
    str_contains($behaviors, "typeof window.checkExistingRecord !== 'function'"),
    'Shared behavior safely guards missing checkExistingRecord function'
);

$check(
    str_contains($behaviors, 'window.checkExistingRecord();'),
    'Shared behavior invokes the page-owned checkExistingRecord function'
);

foreach ($targets as $relative) {
    $content = file_get_contents($root . '/' . $relative);

    $check(
        !str_contains($content, 'onchange="checkExistingRecord()"'),
        $relative . ' no longer uses inline checkExistingRecord handler'
    );

    $check(
        str_contains($content, 'data-check-existing-record'),
        $relative . ' opts into centralized existing-record behavior'
    );

    $check(
        str_contains($content, 'function checkExistingRecord('),
        $relative . ' retains its page-specific existing-record implementation'
    );
}

$ruminant = file_get_contents($root . '/ruminant/ruminant_daily_record.php');

$check(
    substr_count($ruminant, 'checkExistingRecord();') === 1,
    'Ruminant page retains exactly one separate programmatic checkExistingRecord call'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;
exit($failures === 0 ? 0 : 1);
