<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$bridgePath =
    $root
    . '/scripts/v310_private_cli_bridge.php';

$workerPath =
    $root
    . '/scripts/run_v310_account_credential_outbox.php';

if (
    !is_file($bridgePath)
    || !is_file($workerPath)
) {
    echo "FAIL: private worker source contract is incomplete.\n";
    exit(1);
}

require_once $bridgePath;

$bridge =
    (string)file_get_contents(
        $bridgePath
    );

$worker =
    (string)file_get_contents(
        $workerPath
    );

$checks = [];

$check = static function (
    string $label,
    bool $ok
) use (&$checks): void {
    $checks[] = $ok;

    echo ($ok ? 'PASS: ' : 'FAIL: ')
        . $label
        . PHP_EOL;
};

$check(
    'Bridge has explicit required authority whitelist',
    str_contains(
        $bridge,
        'v310_private_cli_bridge_required_names'
    )
);

$check(
    'Bridge has explicit optional authority whitelist',
    str_contains(
        $bridge,
        'v310_private_cli_bridge_optional_names'
    )
);

$check(
    'Bridge rejects duplicate authority names',
    str_contains(
        $bridge,
        'Private CLI authority appears more than once: '
    )
);

$check(
    'Bridge imports only into process environment',
    str_contains(
        $bridge,
        'putenv('
    )
    && str_contains(
        $bridge,
        '$_ENV[$name] = $value;'
    )
    && str_contains(
        $bridge,
        '$_SERVER[$name] = $value;'
    )
);

$check(
    'Bridge defines canonical public URL readiness',
    str_contains(
        $bridge,
        'v310_private_cli_public_url_ready'
    )
);

$check(
    'Bridge requires SMTP send authority',
    str_contains(
        $bridge,
        "!== 'smtp'"
    )
    && str_contains(
        $bridge,
        "'PLATFORM_SMTP_PASSWORD'"
    )
);

$check(
    'Worker supports explicit application root',
    str_contains(
        $worker,
        'RENEE_CREDENTIAL_APP_ROOT'
    )
);

$check(
    'Worker supports explicit authority source',
    str_contains(
        $worker,
        'RENEE_CREDENTIAL_ENV_SOURCE'
    )
);

$check(
    'Worker requires private bridge',
    str_contains(
        $worker,
        "/v310_private_cli_bridge.php"
    )
);

$check(
    'Worker SEND has fail-closed authority gate',
    str_contains(
        $worker,
        '$send'
    )
    && str_contains(
        $worker,
        'v310_private_cli_send_authority_ready()'
    )
    && str_contains(
        $worker,
        'credential worker SEND authority is incomplete'
    )
);

$check(
    'Worker dry-run contract remains present',
    str_contains(
        $worker,
        'Mode: DRY-RUN'
    )
    && str_contains(
        $worker,
        'no jobs were claimed or sent'
    )
);

$check(
    'Worker shared processor remains canonical',
    str_contains(
        $worker,
        'account_credential_outbox_process_one('
    )
);

/*
 * Functional parser test uses only synthetic non-secret values.
 */
$temp =
    tempnam(
        sys_get_temp_dir(),
        'v310-bridge-'
    );

if ($temp === false) {
    echo "FAIL: unable to create verifier fixture.\n";
    exit(1);
}

$fixture =
    implode(
        PHP_EOL,
        [
            'SetEnv DB_HOST localhost',
            'SetEnv DB_USER qa_user',
            'SetEnv DB_PASS qa_password',
            'SetEnv DB_NAME qa_database',
            'SetEnv BILLING_PUBLIC_BASE_URL "https://example.test"',
            'SetEnv PLATFORM_MAIL_TRANSPORT smtp',
            'SetEnv PLATFORM_MAIL_FROM no-reply@example.test',
            'SetEnv PLATFORM_SMTP_HOST smtp.example.test',
            'SetEnv PLATFORM_SMTP_PORT 587',
            'SetEnv PLATFORM_SMTP_ENCRYPTION tls',
            'SetEnv PLATFORM_SMTP_USERNAME qa_mailer',
            'SetEnv PLATFORM_SMTP_PASSWORD qa_mail_password',
            'SetEnv PLATFORM_MAIL_FROM_NAME "QA Platform"',
        ]
    )
    . PHP_EOL;

file_put_contents(
    $temp,
    $fixture
);

try {
    v310_private_cli_bridge_import(
        $temp
    );

    $check(
        'Synthetic authority import is accepted',
        true
    );

    $check(
        'Synthetic SEND authority becomes ready',
        v310_private_cli_send_authority_ready()
    );
} catch (Throwable $e) {
    $check(
        'Synthetic authority import is accepted',
        false
    );

    $check(
        'Synthetic SEND authority becomes ready',
        false
    );
}

/*
 * Duplicate-authority rejection test.
 */
file_put_contents(
    $temp,
    $fixture
    . 'SetEnv DB_HOST second-host'
    . PHP_EOL
);

$duplicateRejected = false;

try {
    v310_private_cli_bridge_read(
        $temp
    );
} catch (Throwable $e) {
    $duplicateRejected = true;
}

$check(
    'Duplicate authority is rejected',
    $duplicateRejected
);

@unlink($temp);

$pass =
    !in_array(
        false,
        $checks,
        true
    );

echo $pass
    ? "PRIVATE_CLI_BRIDGE_CONTRACT=PASS\n"
    : "PRIVATE_CLI_BRIDGE_CONTRACT=FAIL\n";

exit($pass ? 0 : 1);
