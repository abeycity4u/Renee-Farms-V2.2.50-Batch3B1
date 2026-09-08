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

$navbarHead = file_get_contents($root . '/navbar_head.php');
$behaviors = file_get_contents($root . '/assets/js/app-behaviors.js');

$targets = [
    'admin/permissions.php',
    'management/sales_records.php',
    'management/platform_tenant_view.php',
    'management/users.php',
];

$check(
    str_contains(
        $navbarHead,
        "versioned_asset('/assets/js/app-behaviors.js')"
    ),
    'Shared CSP behavior layer is loaded through navbar_head'
);

$check(
    str_contains($behaviors, "document.addEventListener('change'"),
    'Shared behavior layer delegates change events centrally'
);

$check(
    str_contains($behaviors, "[data-auto-submit]"),
    'Shared behavior layer recognizes data-auto-submit contract'
);

$check(
    str_contains($behaviors, 'form.submit();'),
    'Shared behavior layer preserves direct form submit behavior'
);

foreach ($targets as $relative) {
    $content = file_get_contents($root . '/' . $relative);

    $check(
        !str_contains($content, 'onchange="this.form.submit()"'),
        $relative . ' no longer uses inline auto-submit handler'
    );

    $check(
        str_contains($content, 'data-auto-submit'),
        $relative . ' opts into centralized auto-submit behavior'
    );
}

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;
exit($failures === 0 ? 0 : 1);
