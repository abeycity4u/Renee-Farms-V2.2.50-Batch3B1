<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$serviceFile =
    $root
    . '/includes/farm_profile.php';

$managementFile =
    $root
    . '/management/farms.php';

$failures = [];

if (!is_file($serviceFile)) {
    fwrite(
        STDERR,
        "FAIL: shared farm profile authority missing\n"
    );
    exit(1);
}

require_once $serviceFile;

$contracts = [
    'normalizes farm name'
        => farm_profile_normalize_name(
            '  Farm A LLC  '
        ) === 'Farm A LLC',

    'normalizes workspace ID'
        => farm_profile_normalize_workspace_id(
            '  FARM-A  '
        ) === 'farm-a',

    'defaults primary colour'
        => farm_profile_normalize_primary_color(
            ''
        ) === '#198754',

    'keeps valid primary colour'
        => farm_profile_normalize_primary_color(
            '#123ABC'
        ) === '#123ABC',

    'platform owner workspace is reserved'
        => in_array(
            'owner',
            farm_profile_reserved_workspace_ids(),
            true
        ),

    'no-upload logo validation is neutral'
        => farm_profile_detect_logo_extension(
            null
        ) === null,
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

$normalized =
    farm_profile_normalize_identity([
        'name' =>
            ' Farm A LLC ',

        'slug' =>
            ' Farm-A ',

        'primary_color' =>
            '#198754',
    ]);

if (
    $normalized['name']
        === 'Farm A LLC'
    && $normalized['slug']
        === 'farm-a'
    && $normalized['primary_color']
        === '#198754'
) {
    echo
        "PASS: complete profile identity normalization\n";
} else {
    $failures[] =
        'Complete identity normalization failed';
}

$invalidCases = [
    [
        'label' =>
            'blank farm name rejected',

        'call' =>
            static function (): void {
                farm_profile_validate_name(
                    ''
                );
            },
    ],
    [
        'label' =>
            'invalid workspace ID rejected',

        'call' =>
            static function (): void {
                farm_profile_validate_workspace_id(
                    'Farm A !!'
                );
            },
    ],
    [
        'label' =>
            'owner workspace ID rejected',

        'call' =>
            static function (): void {
                farm_profile_validate_workspace_id(
                    'owner'
                );
            },
    ],
    [
        'label' =>
            'invalid primary colour rejected',

        'call' =>
            static function (): void {
                farm_profile_validate_primary_color(
                    'green'
                );
            },
    ],
];

foreach ($invalidCases as $case) {
    $rejected = false;

    try {
        $case['call']();
    } catch (InvalidArgumentException $e) {
        $rejected = true;
    }

    if ($rejected) {
        echo
            'PASS: '
            . $case['label']
            . PHP_EOL;
    } else {
        $failures[] =
            $case['label'];
    }
}

$source =
    (string)file_get_contents(
        $serviceFile
    );

foreach ([
    'farm_profile_load',
    'farm_profile_workspace_id_available',
    'farm_profile_assert_workspace_id_available',
    'farm_profile_detect_logo_extension',
    'farm_profile_save_logo_upload',
    'farm_profile_update_identity',
] as $functionName) {
    if (
        str_contains(
            $source,
            'function '
            . $functionName
        )
    ) {
        echo
            'PASS: shared function '
            . $functionName
            . PHP_EOL;
    } else {
        $failures[] =
            'Missing shared function '
            . $functionName;
    }
}

if (
    str_contains(
        $source,
        'UPDATE farms'
    )
    && str_contains(
        $source,
        'WHERE id = ?'
    )
    && !str_contains(
        $source,
        'UPDATE users'
    )
) {
    echo
        "PASS: profile update remains farm-scoped\n";
} else {
    $failures[] =
        'Farm profile update scope is wrong';
}

if (
    str_contains(
        $source,
        'subscription_plan ='
    )
    || str_contains(
        $source,
        'subscription_status ='
    )
    || str_contains(
        $source,
        'UPDATE subscriptions'
    )
) {
    $failures[] =
        'Farm Profile authority must not own subscription mutation';
} else {
    echo
        "PASS: subscription mutation remains outside Farm Profile authority\n";
}

if (
    str_contains(
        $source,
        'UPDATE users'
    )
    || str_contains(
        $source,
        'INSERT INTO users'
    )
) {
    $failures[] =
        'Farm Profile authority must not own user mutation';
} else {
    echo
        "PASS: user lifecycle remains outside Farm Profile authority\n";
}

$management =
    (string)file_get_contents(
        $managementFile
    );

if (
    str_contains(
        $management,
        'function detectFarmLogoExtension'
    )
    && str_contains(
        $management,
        'function saveFarmLogoUpload'
    )
) {
    echo
        "PASS: legacy Platform Owner helpers intentionally remain pending A3 integration\n";
} else {
    $failures[] =
        'A2 unexpectedly changed Platform Owner helper ownership';
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
    "PASS: V3.2 shared Farm Profile foundation\n";
