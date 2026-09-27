<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$navbarFile = $root . '/navbar.php';
$headFile = $root . '/navbar_head.php';
$brandFile = $root . '/includes/platform_brand.php';
$cssFile = $root . '/assets/css/platform-brand.css';

$failures = [];

foreach ([
    $navbarFile,
    $headFile,
    $brandFile,
    $cssFile,
] as $file) {
    if (!is_file($file)) {
        $failures[] = 'Missing file: ' . $file;
    }
}

if ($failures === []) {
    $navbar = (string)file_get_contents($navbarFile);
    $head = (string)file_get_contents($headFile);

    $contracts = [
        'navbar loads central brand authority'
            => str_contains(
                $navbar,
                "require_once __DIR__ . '/includes/platform_brand.php';"
            ),

        'navbar renders shared lockup'
            => str_contains(
                $navbar,
                "platform_brand_html('platform-brand-navbar')"
            ),

        'navbar logo alt uses shared brand authority'
            => str_contains(
                $navbar,
                'platform_brand_plain_lockup()'
            ),

        'navbar uses platform logo asset'
            => str_contains(
                $navbar,
                '/assets/images/logo.jpg?v=2024.06.01'
            ),

        'shared brand CSS is loaded globally'
            => str_contains(
                $head,
                "versioned_asset('/assets/css/platform-brand.css')"
            ),
    ];

    foreach ($contracts as $name => $pass) {
        if ($pass) {
            echo 'PASS: ' . $name . PHP_EOL;
        } else {
            $failures[] = 'Contract failed: ' . $name;
        }
    }

    if (
        str_contains($navbar, 'farmBrandName()')
        || str_contains($navbar, 'farmLogoUrl()')
    ) {
        $failures[] =
            'Navbar product identity still depends on tenant brand helpers';
    } else {
        echo
            "PASS: navbar product identity is separated from tenant brand helpers\n";
    }

    if (
        substr_count(
            $navbar,
            "platform_brand_html('platform-brand-navbar')"
        ) !== 1
    ) {
        $failures[] =
            'Navbar must render exactly one shared platform lockup';
    } else {
        echo
            "PASS: navbar renders exactly one platform lockup\n";
    }

    require_once $brandFile;

    if (
        platform_brand_plain_lockup()
        !== 'RENEE AGRISUITE by Renee Farms'
    ) {
        $failures[] =
            'Shared navbar lockup text contract is wrong';
    } else {
        echo
            "PASS: navbar lockup text contract\n";
    }

    $css = (string)file_get_contents($cssFile);

    if (
        str_contains(
            $css,
            'flex-direction: column'
        )
    ) {
        $failures[] =
            'Brand lockup must remain horizontal';
    } else {
        echo
            "PASS: navbar lockup remains horizontal\n";
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(
            STDERR,
            'FAIL: ' . $failure . PHP_EOL
        );
    }

    exit(1);
}

echo
    "PASS: V3.2 navbar platform-brand integration\n";
