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

$pagePath = $root . '/includes/dashboard_action_permissions.php';
$cssPath = $root . '/assets/css/dashboard-sales-rep.css';

$page = is_file($pagePath) ? file_get_contents($pagePath) : '';
$css = is_file($cssPath) ? file_get_contents($cssPath) : '';

$check(
    $page !== '',
    'Dashboard action permission helper exists and is readable'
);

$check(
    $css !== '',
    'Sales Rep dashboard stylesheet exists and is readable'
);

$check(
    !str_contains($css, '<?php')
    && !str_contains($css, '<?='),
    'Sales Rep dashboard stylesheet contains no PHP'
);

$check(
    preg_match_all(
        '/<style\b[^>]*>.*?<\/style\s*>/is',
        $page
    ) === 1,
    'Only one dashboard action inline style block remains'
);

$check(
    substr_count(
        $page,
        'dashboard-stock-update-prepaint'
    ) === 1,
    'Stock update permission prepaint remains untouched'
);

$check(
    !str_contains(
        $page,
        'sales-rep-dashboard-polish'
    ),
    'Sales Rep inline style block has been removed'
);

$check(
    substr_count(
        $page,
        '/assets/css/dashboard-sales-rep.css'
    ) === 2,
    'Sales Rep stylesheet has fallback and versioned asset references'
);

$check(
    substr_count(
        $page,
        '$salesCss = \'<link rel="stylesheet" href="\''
    ) === 1,
    'Sales Rep stylesheet link markup is constructed exactly once'
);

$check(
    substr_count(
        $page,
        '$salesCss . \'</head>\''
    ) === 1,
    'Sales Rep stylesheet is injected into head exactly once'
);

foreach ([
    'body.dashboard-role-sales_rep .dashboard-hero .card-body',
    'body.dashboard-role-sales_rep .dashboard-container',
    'body.dashboard-role-sales_rep .sales-rep-summary-grid',
    'body.dashboard-role-sales_rep .sales-summary-card',
    'body.dashboard-role-sales_rep .sales-summary-icon',
    'body.dashboard-role-sales_rep .sales-summary-label',
    'body.dashboard-role-sales_rep .sales-summary-value',
    'body.dashboard-role-sales_rep .sales-summary-subtext',
    'body.dashboard-role-sales_rep #salesReceivablesSummary .receivable-total',
    'body.dashboard-role-sales_rep #salesReceivablesSummary .receivable-row',
    'body.dashboard-role-sales_rep .sales-rep-monitoring-column>.dashboard-card',
    '@media(max-width:767.98px)',
] as $token) {
    $check(
        str_contains($css, $token),
        'Sales Rep stylesheet retains ' . $token
    );
}

$check(
    str_contains(
        $page,
        '$style = \'<style id="dashboard-stock-update-prepaint">'
    ),
    'Stock permission prepaint still uses its existing inline style contract'
);

echo PHP_EOL
    . $checks
    . ' checks, '
    . $failures
    . ' failure(s).'
    . PHP_EOL;

exit($failures === 0 ? 0 : 1);
