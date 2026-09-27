<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$navbar =
    (string)file_get_contents(
        $root . '/navbar.php'
    );

$style =
    (string)file_get_contents(
        $root . '/assets/css/style.css'
    );

$failures = [];

$contracts = [
    'legacy 10rem main-nav gap retired'
        => !str_contains(
            $style,
            'margin-left: 10rem;'
        ),

    'responsive desktop nav spacing exists'
        => str_contains(
            $style,
            'margin-left: clamp(1rem, 2.5vw, 3rem);'
        ),

    'right account area receives desktop breathing room'
        => str_contains(
            $style,
            '#appNavbar .navbar-nav.ms-auto'
        )
        && str_contains(
            $style,
            'margin-right: 1.25rem;'
        ),

    'Account dropdown remains end-aligned'
        => str_contains(
            $navbar,
            'dropdown-menu dropdown-menu-end'
        ),

    'Farm Admin Account & Settings link exists'
        => str_contains(
            $navbar,
            '/billing/account.php'
        )
        && str_contains(
            $navbar,
            'Account &amp; Settings'
        ),

    'Account settings link stays Farm Admin scoped'
        => str_contains(
            $navbar,
            "!isPlatformOwner() && hasRole('farm_admin')"
        ),

    'theme quick toggle remains'
        => str_contains(
            $navbar,
            'themeQuickToggle'
        ),

    'Account dropdown theme toggle remains'
        => str_contains(
            $navbar,
            'id="themeToggle"'
        ),

    'Logout remains'
        => str_contains(
            $navbar,
            '/logout.php'
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
    "PASS: V3.2 Account navbar visibility contract\n";
