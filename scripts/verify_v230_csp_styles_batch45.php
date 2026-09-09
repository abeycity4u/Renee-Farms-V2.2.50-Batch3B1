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
    $root . '/navbar_head.php'
);

$css = file_get_contents(
    $root . '/assets/css/notifications.css'
);

$styleBlocks = preg_match_all(
    '/<style\b[^>]*>.*?<\/style\s*>/is',
    $php
);

$check(
    $styleBlocks === 2,
    'navbar_head now retains exactly two dynamic inline style blocks'
);

$check(
    str_contains(
        $php,
        "versioned_asset('/assets/css/notifications.css')"
    ),
    'navbar_head loads versioned notification stylesheet'
);

$check(
    !str_contains($css, '<?php')
    && !str_contains($css, '<?='),
    'Notification stylesheet contains no PHP'
);

foreach ([
    '.app-notifications',
    '.app-notification',
    '.app-notification-success',
    '.app-notification-warning',
    '.app-notification-info',
    '.app-notification-error',
    '.app-notification-icon',
    '.app-notification-title',
    '.app-notification-message',
    '.app-notification-close',
    '@keyframes appNotifyIn',
    '@keyframes appNotifyOut',
    '@media (max-width: 760px)',
    '@media (prefers-reduced-motion: reduce)',
] as $token) {
    $check(
        str_contains($css, $token),
        'Notification stylesheet retains ' . $token
    );
}

$check(
    str_contains(
        $php,
        '$salesReceivableActionRules'
    )
    && str_contains(
        $php,
        '$managementExpenseActionRules'
    ),
    'Dynamic permission CSS generation remains present'
);

$check(
    str_contains(
        $php,
        '<style><?php echo implode'
    ),
    'Dynamic permission style block remains inline for later CSP redesign'
);

$check(
    str_contains(
        $php,
        '--farm-primary: <?php echo htmlspecialchars($tenantPrimaryColor'
    ),
    'Tenant primary dynamic CSS remains inline for later CSP redesign'
);

$check(
    str_contains(
        $php,
        "versioned_asset('/assets/js/app-notify.js')"
    ),
    'Notification runtime asset remains loaded'
);

$check(
    str_contains(
        $php,
        "versioned_asset('/assets/css/confirmations.css')"
    ),
    'Shared confirmation stylesheet remains loaded'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
