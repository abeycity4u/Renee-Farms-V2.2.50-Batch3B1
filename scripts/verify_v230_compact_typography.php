<?php
/**
 * V2.3 centralized compact typography / density verifier.
 * Static contract only: no DB writes, tenant mutations, payments or sessions.
 */

$root = dirname(__DIR__);
$paths = [
    'responsive' => $root . '/assets/css/responsive.css',
    'head' => $root . '/navbar_head.php',
    'billing_account' => $root . '/billing/account.php',
    'recovery' => $root . '/billing/recover.php',
];

$checks = [];
$failures = [];
$pass = static function (string $label, bool $ok) use (&$checks, &$failures): void {
    $checks[] = [$label, $ok];
    if (!$ok) $failures[] = $label;
};

$content = [];
foreach ($paths as $key => $path) {
    $exists = is_file($path);
    $pass($key . ' file exists', $exists);
    $content[$key] = $exists ? (string)file_get_contents($path) : '';
}

$responsive = $content['responsive'];
$head = $content['head'];
$billingAccount = $content['billing_account'];
$recovery = $content['recovery'];

$pass('responsive.css is loaded by shared authenticated head',
    str_contains($head, "versioned_asset('/assets/css/responsive.css')"));

$stylePos = strpos($head, "versioned_asset('/assets/css/style.css')");
$dashboardPos = strpos($head, "versioned_asset('/assets/css/dashboard.css')");
$themePos = strpos($head, "versioned_asset('/assets/css/theme.css')");
$responsivePos = strpos($head, "versioned_asset('/assets/css/responsive.css')");
$pass('responsive.css remains last in shared CSS stack',
    $stylePos !== false && $dashboardPos !== false && $themePos !== false && $responsivePos !== false
    && $responsivePos > $stylePos && $responsivePos > $dashboardPos && $responsivePos > $themePos);

$pass('desktop baseline is centrally 15px',
    str_contains($responsive, '--app-font-size-base: 15px;')
    && str_contains($responsive, 'font-size: var(--app-font-size-base);'));

$pass('compact baseline applies only from tablet/desktop width',
    str_contains($responsive, '@media (min-width: 768px)'));

$pass('mobile baseline remains 16px for readability',
    str_contains($responsive, '@media (max-width: 767.98px)')
    && preg_match('/@media \(max-width: 767\.98px\)[\s\S]*?html\s*\{\s*font-size:\s*16px;/m', $responsive) === 1);

$pass('shared nav typography is centrally scaled',
    str_contains($responsive, '#appNavbar .nav-link')
    && str_contains($responsive, '--app-font-size-nav: .93rem;'));

$pass('shared tables are centrally scaled',
    str_contains($responsive, '--app-font-size-table: .92rem;')
    && str_contains($responsive, '.table-responsive'));

$pass('shared controls are centrally scaled',
    str_contains($responsive, '--app-font-size-control: .92rem;')
    && str_contains($responsive, '.form-control')
    && str_contains($responsive, '.form-select')
    && str_contains($responsive, '.btn'));

$pass('heading hierarchy is centrally bounded',
    str_contains($responsive, 'h1, .h1 { font-size: 2rem; }')
    && str_contains($responsive, 'h3, .h3 { font-size: 1.4rem; }')
    && str_contains($responsive, 'h6, .h6 { font-size: .95rem; }'));

$pass('small helper text retains a readability floor',
    str_contains($responsive, 'font-size: max(.78rem, 12px);'));

$pass('compact architecture does not use browser zoom emulation',
    !preg_match('/(^|[;{\s])zoom\s*:/i', $responsive)
    && !preg_match('/transform\s*:\s*scale\s*\(/i', $responsive));

$pass('legacy Primary billing badge is centrally hidden',
    str_contains($responsive, '.provider-primary')
    && preg_match('/\.provider-primary\s*\{\s*display:\s*none\s*!important;/m', $responsive) === 1);

$pass('billing account still uses server-defined provider ordering',
    str_contains($billingAccount, 'billing_provider_selection_codes()')
    && str_contains($billingAccount, 'billing_provider_readiness_status($provider, false)'));

$pass('Primary badge removal does not alter payment provider values',
    str_contains($billingAccount, 'name="provider"')
    && str_contains($billingAccount, '$provider[\'code\']'));

$pass('standalone recovery page is not double-scaled by navbar_head',
    !str_contains($recovery, 'navbar_head.php'));

foreach ($checks as [$label, $ok]) {
    echo ($ok ? 'PASS' : 'FAIL') . '  ' . $label . PHP_EOL;
}

echo PHP_EOL . count($checks) . ' checks, ' . count($failures) . ' failures.' . PHP_EOL;
if ($failures) exit(1);
echo 'V2.3 centralized compact typography / density contract passed.' . PHP_EOL;
