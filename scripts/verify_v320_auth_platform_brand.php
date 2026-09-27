<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$files = [
    'sign.php',
    'account/activate.php',
    'account/forgot_password.php',
    'account/reset_password.php',
];

$failures = [];

foreach ($files as $relative) {
    $path = $root . '/' . $relative;

    if (!is_file($path)) {
        $failures[] =
            'Missing auth surface: ' . $relative;
        continue;
    }

    $content =
        (string)file_get_contents($path);

    if (
        !str_contains(
            $content,
            'platform_brand.php'
        )
    ) {
        $failures[] =
            $relative
            . ' does not load shared brand authority';
    } else {
        echo
            'PASS: shared brand authority | '
            . $relative
            . PHP_EOL;
    }

    if (
        !str_contains(
            $content,
            'platform_brand_html'
        )
    ) {
        $failures[] =
            $relative
            . ' does not render shared brand lockup';
    } else {
        echo
            'PASS: shared lockup | '
            . $relative
            . PHP_EOL;
    }

    if (
        !str_contains(
            $content,
            'platform_brand_plain_lockup'
        )
    ) {
        $failures[] =
            $relative
            . ' does not use shared accessible logo identity';
    } else {
        echo
            'PASS: shared logo identity | '
            . $relative
            . PHP_EOL;
    }

    if (
        !str_contains(
            $content,
            'platform-brand.css'
        )
    ) {
        $failures[] =
            $relative
            . ' does not load platform brand CSS';
    } else {
        echo
            'PASS: platform brand CSS | '
            . $relative
            . PHP_EOL;
    }

    if (
        str_contains(
            $content,
            '<strong>RENEE FARMS LTD</strong>'
        )
    ) {
        $failures[] =
            $relative
            . ' still renders old product lockup';
    } else {
        echo
            'PASS: old visible lockup retired | '
            . $relative
            . PHP_EOL;
    }

    if (
        !str_contains(
            $content,
            'class="auth-brand-copy"'
        )
        || !str_contains(
            $content,
            'class="auth-workspace-label"'
        )
        || !str_contains(
            $content,
            '>Farm Operations Workspace</span>'
        )
    ) {
        $failures[] =
            $relative
            . ' does not separate workspace label from platform lockup';
    } else {
        echo
            'PASS: workspace label is a dedicated second-line element | '
            . $relative
            . PHP_EOL;
    }
}

/*
 * sign.php intentionally retains "Renee Farms Platform"
 * in the platform-owner workspace DB compatibility path.
 * That persisted/functional identity is not display branding.
 */
$sign =
    (string)file_get_contents(
        $root . '/sign.php'
    );

if (
    !str_contains(
        $sign,
        "SELECT 'Renee Farms Platform', 'owner', 'platform', 'active'"
    )
) {
    $failures[] =
        'Platform-owner workspace compatibility identity changed';
} else {
    echo
        "PASS: platform-owner DB compatibility identity preserved\n";
}

require_once
    $root . '/includes/platform_brand.php';

if (
    platform_brand_plain_lockup()
    !== 'RENEE AGRISUITE by Renee Farms'
) {
    $failures[] =
        'Shared auth lockup text contract is wrong';
} else {
    echo
        "PASS: auth lockup text contract\n";
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(
            STDERR,
            'FAIL: '
            . $failure
            . PHP_EOL
        );
    }

    exit(1);
}

echo
    "PASS: V3.2 authentication platform-brand integration\n";
