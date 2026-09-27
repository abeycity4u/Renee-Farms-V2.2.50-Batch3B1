<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$managementFile =
    $root
    . '/management/farms.php';

$serviceFile =
    $root
    . '/includes/farm_profile.php';

$failures = [];

if (
    !is_file($managementFile)
    || !is_file($serviceFile)
) {
    fwrite(
        STDERR,
        "FAIL: integration files missing\n"
    );

    exit(1);
}

$management =
    (string)file_get_contents(
        $managementFile
    );

$service =
    (string)file_get_contents(
        $serviceFile
    );

$contracts = [
    'Platform Owner loads shared Farm Profile authority'
        => str_contains(
            $management,
            "require_once dirname(__DIR__) . '/includes/farm_profile.php';"
        ),

    'local editableFarm helper retired'
        => !str_contains(
            $management,
            'function editableFarm'
        ),

    'local logo detection helper retired'
        => !str_contains(
            $management,
            'function detectFarmLogoExtension'
        ),

    'local logo save helper retired'
        => !str_contains(
            $management,
            'function saveFarmLogoUpload'
        ),

    'Platform Owner uses shared profile lookup'
        => str_contains(
            $management,
            'farm_profile_load($pdo,'
        ),

    'Platform Owner uses shared profile normalization'
        => str_contains(
            $management,
            'farm_profile_normalize_identity'
        ),

    'Platform Owner uses shared workspace uniqueness'
        => str_contains(
            $management,
            'farm_profile_assert_workspace_id_available'
        ),

    'Platform Owner uses shared logo detection'
        => str_contains(
            $management,
            'farm_profile_detect_logo_extension'
        ),

    'Platform Owner uses shared logo storage'
        => str_contains(
            $management,
            'farm_profile_save_logo_upload'
        ),

    'Platform Owner uses shared profile update'
        => str_contains(
            $management,
            'farm_profile_update_identity'
        ),
];

foreach (
    $contracts as $label => $pass
) {
    if ($pass) {
        echo
            'PASS: '
            . $label
            . PHP_EOL;
    } else {
        $failures[] = $label;
    }
}

if (
    str_contains(
        $management,
        "preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', \$slug)"
    )
    || str_contains(
        $management,
        "preg_match('/^#[0-9a-fA-F]{6}$/', \$color)"
    )
) {
    $failures[] =
        'Platform Owner still duplicates Farm Profile validation';
} else {
    echo
        "PASS: page-local Farm Profile validation retired\n";
}

if (
    str_contains(
        $management,
        'UPDATE farms SET name = ?, slug = ?, primary_color = ?'
    )
) {
    $failures[] =
        'Platform Owner still duplicates Farm Profile identity update SQL';
} else {
    echo
        "PASS: page-local Farm Profile identity update SQL retired\n";
}

if (
    !str_contains(
        $management,
        'subscription_plan = ?'
    )
    || !str_contains(
        $management,
        'subscription_status = ?'
    )
    || !str_contains(
        $management,
        'subscription_record_capture'
    )
) {
    $failures[] =
        'Platform Owner commercial responsibilities were disturbed';
} else {
    echo
        "PASS: Platform Owner commercial responsibilities preserved\n";
}

if (
    !str_contains(
        $management,
        'account_pending_user_create'
    )
    || !str_contains(
        $management,
        'assign_protected_farm_admin_role'
    )
) {
    $failures[] =
        'Farm Admin lifecycle responsibilities were disturbed';
} else {
    echo
        "PASS: Farm Admin lifecycle responsibilities preserved\n";
}

if (
    !str_contains(
        $management,
        'sync_farm_entitlements'
    )
    || !str_contains(
        $management,
        'subscription_seat_save_addons'
    )
) {
    $failures[] =
        'Entitlement/seat responsibilities were disturbed';
} else {
    echo
        "PASS: entitlement and seat responsibilities preserved\n";
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
        'Shared Farm Profile authority crossed lifecycle/commercial boundary';
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
    "PASS: V3.2 Platform Owner shared Farm Profile integration\n";
