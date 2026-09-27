<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$navbarFile =
    $root . '/navbar.php';

$headFile =
    $root . '/navbar_head.php';

$brandFile =
    $root . '/includes/platform_brand.php';

$cssFile =
    $root . '/assets/css/platform-brand.css';

$failures = [];

foreach ([
    $navbarFile,
    $headFile,
    $brandFile,
    $cssFile,
] as $file) {
    if (!is_file($file)) {
        $failures[] =
            'Missing file: ' . $file;
    }
}

if ($failures === []) {
    $navbar =
        (string)file_get_contents(
            $navbarFile
        );

    $head =
        (string)file_get_contents(
            $headFile
        );

    $css =
        (string)file_get_contents(
            $cssFile
        );

    $contracts = [
        'navbar loads central platform brand authority'
            => str_contains(
                $navbar,
                "require_once __DIR__ . '/includes/platform_brand.php';"
            ),

        'navbar renders platform product lockup'
            => str_contains(
                $navbar,
                "platform_brand_html('platform-brand-navbar')"
            ),

        'navbar restores tenant farm-name authority'
            => str_contains(
                $navbar,
                'farmBrandName()'
            ),

        'navbar restores tenant uploaded-logo authority'
            => str_contains(
                $navbar,
                'farmLogoUrl()'
            ),

        'navbar renders tenant identity separately'
            => str_contains(
                $navbar,
                'tenant-brand-lockup'
            )
            && str_contains(
                $navbar,
                'tenant-brand-name'
            ),

        'navbar separates platform and tenant identities'
            => str_contains(
                $navbar,
                'navbar-brand-identity-separator'
            ),

        'shared brand CSS is loaded globally'
            => str_contains(
                $head,
                "versioned_asset('/assets/css/platform-brand.css')"
            ),

        'shared CSS contains tenant composition'
            => str_contains(
                $css,
                '.tenant-brand-lockup'
            )
            && str_contains(
                $css,
                '.tenant-brand-name'
            ),
    ];

    foreach ($contracts as $name => $pass) {
        if ($pass) {
            echo
                'PASS: '
                . $name
                . PHP_EOL;
        } else {
            $failures[] =
                'Contract failed: '
                . $name;
        }
    }

    if (
        substr_count(
            $navbar,
            "platform_brand_html('platform-brand-navbar')"
        ) !== 1
    ) {
        $failures[] =
            'Navbar must render exactly one platform lockup';
    } else {
        echo
            "PASS: exactly one platform navbar lockup\n";
    }

    if (
        substr_count(
            $navbar,
            'farmLogoUrl()'
        ) < 1
        || substr_count(
            $navbar,
            'farmBrandName()'
        ) < 2
    ) {
        $failures[] =
            'Tenant logo/name are not fully restored';
    } else {
        echo
            "PASS: dynamic tenant logo and name restored\n";
    }

    if (
        str_contains(
            $navbar,
            '/assets/images/logo.jpg?v=2024.06.01'
        )
    ) {
        $failures[] =
            'Navbar still substitutes fixed platform logo for tenant logo';
    } else {
        echo
            "PASS: fixed platform logo no longer replaces tenant logo\n";
    }

    require_once $brandFile;

    if (
        platform_brand_plain_lockup()
        !== 'RENEE AGRISUITE by Renee Farms'
    ) {
        $failures[] =
            'Shared platform lockup text contract is wrong';
    } else {
        echo
            "PASS: shared platform lockup preserved\n";
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
    "PASS: V3.2 navbar platform + tenant identity composition\n";
