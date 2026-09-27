<?php

$root = dirname(__DIR__);
$cssPath = $root . '/assets/css/billing-account-page.css';
$accountPath = $root . '/billing/account.php';
$receiptPath = $root . '/billing/receipt.php';

$checks = 0;
$failures = 0;

$check = static function (bool $ok, string $message) use (&$checks, &$failures): void {
    $checks++;
    if ($ok) {
        echo "PASS: {$message}\n";
    } else {
        $failures++;
        echo "FAIL: {$message}\n";
    }
};

$css = is_file($cssPath) ? file_get_contents($cssPath) : false;
$account = is_file($accountPath) ? file_get_contents($accountPath) : false;
$receipt = is_file($receiptPath) ? file_get_contents($receiptPath) : false;

$check(
    is_string($css),
    'shared billing stylesheet is readable'
);

$check(
    is_string($account)
        && strpos($account, "billing-account-page.css") !== false,
    'billing account uses shared billing stylesheet'
);

$check(
    is_string($receipt)
        && strpos($receipt, "billing-account-page.css") !== false,
    'billing receipt uses shared billing stylesheet'
);

$check(
    is_string($css)
        && strpos(
            $css,
            'html[data-theme="dark"] .metric-value'
        ) !== false
        && strpos(
            $css,
            'color: var(--bs-body-color)'
        ) !== false,
    'dark billing metric values use theme-aware readable body colour'
);

$check(
    is_string($css)
        && strpos(
            $css,
            'html[data-theme="dark"] .metric-label'
        ) !== false
        && strpos(
            $css,
            'color: var(--bs-secondary-color)'
        ) !== false,
    'dark billing labels use theme-aware secondary colour'
);

$check(
    is_string($css)
        && strpos(
            $css,
            'html[data-theme="dark"] .billing-note'
        ) !== false
        && strpos(
            $css,
            'background: var(--bs-tertiary-bg)'
        ) !== false,
    'dark billing notes use theme-aware surface'
);

$check(
    is_string($css)
        && strpos(
            $css,
            'html[data-theme="dark"] .billing-email-box'
        ) !== false,
    'dark billing email box receives shared contrast treatment'
);

$check(
    is_string($css)
        && strpos(
            $css,
            'html[data-theme="dark"] .provider-option'
        ) !== false,
    'dark payment provider choices receive theme-aware border'
);

$check(
    is_string($css)
        && substr_count(
            $css,
            'A7H shared dark-theme billing contrast'
        ) === 1,
    'shared dark billing contrast block exists exactly once'
);

echo "\n{$checks} checks, {$failures} failure(s).\n";

if ($failures === 0) {
    echo "BILLING_DARK_CONTRAST_VERIFIER=PASS\n";
    exit(0);
}

echo "BILLING_DARK_CONTRAST_VERIFIER=FAIL\n";
exit(1);
