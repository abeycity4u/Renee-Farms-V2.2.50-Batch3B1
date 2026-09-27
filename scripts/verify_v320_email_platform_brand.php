<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

$brandFile =
    $root . '/includes/platform_brand.php';

$credentialFile =
    $root . '/includes/account_credential_delivery.php';

$mailerFile =
    $root . '/includes/platform_mailer.php';

$onboardingFile =
    $root . '/includes/farm_onboarding_mail.php';

$renewalFile =
    $root . '/includes/subscription_renewal_reminder.php';

foreach ([
    $brandFile,
    $credentialFile,
    $mailerFile,
    $onboardingFile,
    $renewalFile,
] as $file) {
    if (!is_file($file)) {
        $failures[] =
            'Missing email brand file: '
            . $file;
    }
}

if ($failures === []) {
    require_once $brandFile;

    if (
        platform_brand_product_text()
        === 'Renee AgriSuite'
    ) {
        echo
            "PASS: natural-language product brand\n";
    } else {
        $failures[] =
            'Natural-language product brand is wrong';
    }

    if (
        platform_brand_plain_lockup()
        === 'RENEE AGRISUITE by Renee Farms'
    ) {
        echo
            "PASS: plain email signature lockup\n";
    } else {
        $failures[] =
            'Plain email signature lockup is wrong';
    }

    $credential =
        (string)file_get_contents(
            $credentialFile
        );

    foreach ([
        'platform_brand_product_text()',
        'platform_brand_plain_lockup()',
    ] as $needle) {
        if (
            str_contains(
                $credential,
                $needle
            )
        ) {
            echo
                'PASS: credential mail uses '
                . $needle
                . PHP_EOL;
        } else {
            $failures[] =
                'Credential mail missing '
                . $needle;
        }
    }

    foreach ([
        'Activate your Renee Farms account',
        'Your Renee Farms account is ready',
        'Reset your Renee Farms password',
        'password reset was requested for your Renee Farms account',
        'Renee Farms Platform',
    ] as $oldBrand) {
        if (
            stripos(
                $credential,
                $oldBrand
            ) !== false
        ) {
            $failures[] =
                'Credential mail retains old display brand: '
                . $oldBrand;
        } else {
            echo
                'PASS: credential old brand retired | '
                . $oldBrand
                . PHP_EOL;
        }
    }

    $mailer =
        (string)file_get_contents(
            $mailerFile
        );

    if (
        str_contains(
            $mailer,
            'platform_brand_mail_sender_name()'
        )
    ) {
        echo
            "PASS: sender fallback uses shared brand\n";
    } else {
        $failures[] =
            'Sender fallback does not use shared brand';
    }

    foreach ([
        'no-reply@reneefarms.com',
        'EHLO reneefarms.com',
        '@reneefarms.com>',
    ] as $transportIdentity) {
        if (
            str_contains(
                $mailer,
                $transportIdentity
            )
        ) {
            echo
                'PASS: transport identity preserved | '
                . $transportIdentity
                . PHP_EOL;
        } else {
            $failures[] =
                'Transport identity changed: '
                . $transportIdentity;
        }
    }

    $onboarding =
        (string)file_get_contents(
            $onboardingFile
        );

    if (
        str_contains(
            $onboarding,
            'platform_brand_product_text()'
        )
        && str_contains(
            $onboarding,
            'platform_brand_plain_lockup()'
        )
    ) {
        echo
            "PASS: onboarding uses shared brand authority\n";
    } else {
        $failures[] =
            'Onboarding does not use shared brand authority';
    }

    $renewal =
        (string)file_get_contents(
            $renewalFile
        );

    if (
        str_contains(
            $renewal,
            'platform_brand_plain_lockup()'
        )
    ) {
        echo
            "PASS: renewal signature uses shared brand\n";
    } else {
        $failures[] =
            'Renewal signature does not use shared brand';
    }

    if (
        str_contains(
            $renewal,
            'https://reneefarms.com/billing/account.php'
        )
    ) {
        echo
            "PASS: functional billing URL preserved\n";
    } else {
        $failures[] =
            'Functional billing URL changed';
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
    "PASS: V3.2 email platform-brand integration\n";
