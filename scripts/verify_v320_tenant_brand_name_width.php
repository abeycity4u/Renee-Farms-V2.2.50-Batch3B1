<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$css =
    (string)file_get_contents(
        $root . '/assets/css/platform-brand.css'
    );

$failures = [];

$contracts = [
    'desktop tenant name width is 16rem'
        => str_contains(
            $css,
            'max-width: 16rem;'
        ),

    'tenant name still uses ellipsis safety'
        => str_contains(
            $css,
            'text-overflow: ellipsis;'
        ),

    'tenant name remains single line'
        => str_contains(
            $css,
            'white-space: nowrap;'
        ),

    'mobile tenant width remains constrained'
        => str_contains(
            $css,
            'max-width: 7rem;'
        ),

    'platform and tenant identities remain separate'
        => str_contains(
            $css,
            '.platform-navbar-identity'
        )
        && str_contains(
            $css,
            '.tenant-brand-lockup'
        ),
];

foreach ($contracts as $label => $pass) {
    if ($pass) {
        echo
            'PASS: '
            . $label
            . PHP_EOL;
    } else {
        $failures[] = $label;
    }
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
    "PASS: V3.2 tenant brand name width contract\n";
