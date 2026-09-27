<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$failures = [];

$brandFile =
    $root . '/includes/platform_brand.php';

$cssFile =
    $root . '/assets/css/platform-brand.css';

if (!is_file($brandFile)) {
    $failures[] =
        'platform_brand.php is missing';
}

if (!is_file($cssFile)) {
    $failures[] =
        'platform-brand.css is missing';
}

if ($failures === []) {
    require_once $brandFile;

    $contracts = [
        'product name'
            => platform_brand_product_name()
                === 'RENEE AGRISUITE',

        'parent name'
            => platform_brand_parent_name()
                === 'Renee Farms',

        'parent byline'
            => platform_brand_parent_byline()
                === 'by Renee Farms',

        'plain lockup'
            => platform_brand_plain_lockup()
                === 'RENEE AGRISUITE by Renee Farms',

        'page identity'
            => platform_brand_page_name()
                === 'RENEE AGRISUITE',

        'mail sender identity'
            => platform_brand_mail_sender_name()
                === 'RENEE AGRISUITE by Renee Farms',
    ];

    foreach ($contracts as $name => $pass) {
        if (!$pass) {
            $failures[] =
                'Brand contract failed: '
                . $name;
        } else {
            echo 'PASS: ' . $name . PHP_EOL;
        }
    }

    $html =
        platform_brand_html();

    if (
        substr_count(
            $html,
            'platform-brand-product'
        ) !== 1
        || substr_count(
            $html,
            'platform-brand-parent'
        ) !== 1
    ) {
        $failures[] =
            'HTML lockup structure is invalid';
    } else {
        echo
            "PASS: shared HTML lockup structure\n";
    }

    if (
        strpos(
            $html,
            'RENEE AGRISUITE'
        ) === false
        || strpos(
            $html,
            'by Renee Farms'
        ) === false
    ) {
        $failures[] =
            'HTML lockup content is invalid';
    } else {
        echo
            "PASS: shared HTML lockup content\n";
    }

    $css =
        file_get_contents($cssFile);

    $requiredCss = [
        '.platform-brand-lockup',
        '.platform-brand-product',
        '.platform-brand-parent',
        '"Tiscali"',
        'white-space: nowrap',
        'font-size: 0.52em',
    ];

    foreach ($requiredCss as $needle) {
        if (
            strpos(
                (string)$css,
                $needle
            ) === false
        ) {
            $failures[] =
                'Missing CSS contract: '
                . $needle;
        } else {
            echo
                'PASS: CSS contract '
                . $needle
                . PHP_EOL;
        }
    }

    if (
        strpos(
            (string)$css,
            'flex-direction: column'
        ) !== false
    ) {
        $failures[] =
            'Brand lockup must not become stacked';
    } else {
        echo
            "PASS: brand lockup remains horizontal\n";
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
    "PASS: V3.2 central platform brand foundation\n";
