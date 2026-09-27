<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$accountFile =
    $root
    . '/billing/account.php';

$serviceFile =
    $root
    . '/includes/farm_profile.php';

$failures = [];

$account =
    (string)file_get_contents(
        $accountFile
    );

$service =
    (string)file_get_contents(
        $serviceFile
    );

$contracts = [
    'Account loads shared Farm Profile authority'
        => str_contains(
            $account,
            "require_once dirname(__DIR__) . '/includes/farm_profile.php';"
        ),

    'Account remains Farm Admin tenant-scoped'
        => str_contains(
            $account,
            'billing_require_farm_admin_actor'
        ),

    'Account retains centralized billing overview'
        => str_contains(
            $account,
            'billing_account_overview'
        ),

    'Account retains centralized contact email authority'
        => str_contains(
            $account,
            'farm_contact_email_update'
        ),

    'Account uses shared profile lookup'
        => str_contains(
            $account,
            'farm_profile_load'
        ),

    'Account uses shared profile normalization'
        => str_contains(
            $account,
            'farm_profile_normalize_identity'
        ),

    'Account uses shared workspace uniqueness'
        => str_contains(
            $account,
            'farm_profile_assert_workspace_id_available'
        ),

    'Account uses shared logo validation'
        => str_contains(
            $account,
            'farm_profile_detect_logo_extension'
        ),

    'Account uses shared logo storage'
        => str_contains(
            $account,
            'farm_profile_save_logo_upload'
        ),

    'Account uses shared profile update'
        => str_contains(
            $account,
            'farm_profile_update_identity'
        ),

    'Farm Profile form exists'
        => str_contains(
            $account,
            'name="save_farm_profile"'
        ),

    'Farm Profile contains farm name'
        => str_contains(
            $account,
            'id="farmProfileName"'
        ),

    'Farm Profile contains Workspace ID'
        => str_contains(
            $account,
            'id="farmProfileWorkspace"'
        ),

    'Farm Profile contains logo upload'
        => str_contains(
            $account,
            'id="farmProfileLogo"'
        ),

    'Farm Profile contains primary colour'
        => str_contains(
            $account,
            'id="farmProfileColor"'
        ),

    'Team Users remains linked to specialist page'
        => str_contains(
            $account,
            "/management/users.php"
        ),

    'Account UI becomes Account & Settings'
        => str_contains(
            $account,
            'Account &amp; Settings'
        ),

    'Subscription & Billing remains visible'
        => str_contains(
            $account,
            'Subscription &amp; Billing'
        ),
];

foreach ($contracts as $label => $pass) {
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

if (
    str_contains(
        $account,
        'UPDATE farms SET name'
    )
    || str_contains(
        $account,
        'UPDATE farms\n'
    )
) {
    $failures[] =
        'Account must not own page-local Farm Profile SQL';
} else {
    echo
        "PASS: no page-local Farm Profile SQL\n";
}

if (
    str_contains(
        $account,
        "preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/"
    )
    || str_contains(
        $account,
        "preg_match('/^#[0-9a-fA-F]{6}$/"
    )
) {
    $failures[] =
        'Account must not duplicate profile validation';
} else {
    echo
        "PASS: no page-local profile validation\n";
}

if (
    !str_contains(
        $account,
        'save_contact_email'
    )
    || !str_contains(
        $account,
        'billing/checkout.php'
    )
    || !str_contains(
        $account,
        'seat_topup_checkout.php'
    )
    || !str_contains(
        $account,
        'seat_reduction_schedule.php'
    )
) {
    $failures[] =
        'Existing billing/account controls were disturbed';
} else {
    echo
        "PASS: existing billing controls preserved\n";
}

if (
    str_contains(
        $service,
        'UPDATE users'
    )
    || str_contains(
        $service,
        'INSERT INTO users'
    )
    || str_contains(
        $service,
        'UPDATE subscriptions'
    )
) {
    $failures[] =
        'Shared Farm Profile boundary was widened incorrectly';
} else {
    echo
        "PASS: shared Farm Profile boundary remains narrow\n";
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
    "PASS: V3.2 Farm Admin Account & Settings profile integration\n";
