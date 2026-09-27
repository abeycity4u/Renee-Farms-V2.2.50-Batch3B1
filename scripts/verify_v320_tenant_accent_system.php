<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$file =
    $root
    . '/tenant_theme.css.php';

$navbarHead =
    (string)file_get_contents(
        $root
        . '/navbar_head.php'
    );

$source =
    (string)file_get_contents(
        $file
    );

$failures = [];

$contracts = [
    'tenant colour still comes from current farm'
        => str_contains(
            $source,
            "currentFarm()['primary_color']"
        ),

    'invalid colours retain safe fallback'
        => str_contains(
            $source,
            "'#198754'"
        )
        && str_contains(
            $source,
            "/^#[0-9a-fA-F]{6}$/"
        ),

    'central RGB token exists'
        => str_contains(
            $source,
            '--farm-primary-rgb:'
        ),

    'central hover token exists'
        => str_contains(
            $source,
            '--farm-primary-hover:'
        ),

    'central active token exists'
        => str_contains(
            $source,
            '--farm-primary-active:'
        ),

    'central contrast token exists'
        => str_contains(
            $source,
            '--farm-primary-contrast:'
        ),

    'central soft accent exists'
        => str_contains(
            $source,
            '--farm-primary-soft:'
        ),

    'central focus ring exists'
        => str_contains(
            $source,
            '--farm-primary-focus:'
        ),

    'Bootstrap primary RGB is centralized'
        => str_contains(
            $source,
            '--bs-primary-rgb:var(--farm-primary-rgb)'
        ),

    'primary button consumes tenant accent'
        => str_contains(
            $source,
            '.btn-primary{'
        ),

    'outline primary consumes tenant accent'
        => str_contains(
            $source,
            '.btn-outline-primary{'
        ),

    'primary text consumes tenant accent'
        => str_contains(
            $source,
            '.text-primary{'
        ),

    'primary background consumes tenant accent'
        => str_contains(
            $source,
            '.bg-primary,'
        ),

    'primary subtle background consumes tenant accent'
        => str_contains(
            $source,
            '.bg-primary-subtle{'
        ),

    'primary border consumes tenant accent'
        => str_contains(
            $source,
            '.border-primary{'
        ),

    'selected nav pills consume tenant accent'
        => str_contains(
            $source,
            '.nav-pills .nav-link.active'
        ),

    'active pagination consumes tenant accent'
        => str_contains(
            $source,
            '.page-item.active .page-link'
        ),

    'checked controls consume tenant accent'
        => str_contains(
            $source,
            '.form-check-input:checked'
        ),

    'dark mode has accent emphasis'
        => str_contains(
            $source,
            'html[data-theme="dark"]'
        )
        && str_contains(
            $source,
            '--farm-primary-emphasis-dark'
        ),

    'tenant theme remains globally loaded'
        => str_contains(
            $navbarHead,
            '/tenant_theme.css.php'
        ),
];

foreach (
    $contracts
    as $label => $pass
) {
    if ($pass) {
        echo
            'PASS: '
            . $label
            . PHP_EOL;
    } else {
        $failures[] =
            $label;
    }
}

/*
 * Semantic colours must remain separate.
 * Reject any attempt to map those Bootstrap classes to farm-primary.
 */
$forbidden = [
    '.btn-success{'
        => 'success button',

    '.text-success{'
        => 'success text',

    '.bg-success{'
        => 'success background',

    '.text-warning{'
        => 'warning text',

    '.bg-warning{'
        => 'warning background',

    '.text-danger{'
        => 'danger text',

    '.bg-danger{'
        => 'danger background',

    '.text-info{'
        => 'info text',

    '.bg-info{'
        => 'info background',
];

foreach ($forbidden as $needle => $label) {
    if (
        str_contains(
            $source,
            $needle
        )
    ) {
        $failures[] =
            'Tenant theme must not own '
            . $label;
    }
}

if ($failures === []) {
    echo
        "PASS: semantic status colours remain independent\n";
}

if (
    preg_match(
        '/\\.navbar\\.bg-success\\s*\\{/',
        $source
    ) !== 1
) {
    echo
        "PASS: platform navbar remains outside tenant accent ownership\n";
} else {
    $failures[] =
        'Tenant accent authority must not override the platform navbar';
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
    "PASS: V3.2 centralized tenant accent system\n";
