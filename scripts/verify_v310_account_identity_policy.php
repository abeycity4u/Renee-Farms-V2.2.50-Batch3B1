<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$path =
    $root
    . '/includes/account_identity_policy.php';

$failures = 0;

function verify_identity(
    bool $condition,
    string $message
): void {
    global $failures;

    if ($condition) {
        echo 'PASS: '
            . $message
            . PHP_EOL;
        return;
    }

    echo 'FAIL: '
        . $message
        . PHP_EOL;

    $failures++;
}

verify_identity(
    is_file($path),
    'shared account identity policy exists'
);

if (!is_file($path)) {
    echo "V310_ACCOUNT_IDENTITY_POLICY=FAIL\n";
    exit(1);
}

require_once $path;

verify_identity(
    defined('ACCOUNT_IDENTITY_USERNAME_MAX')
    && ACCOUNT_IDENTITY_USERNAME_MAX === 100,
    'username policy mirrors varchar(100)'
);

verify_identity(
    defined('ACCOUNT_IDENTITY_FULL_NAME_MAX')
    && ACCOUNT_IDENTITY_FULL_NAME_MAX === 150,
    'full-name policy mirrors varchar(150)'
);

verify_identity(
    account_identity_normalize_username(
        '  farm.manager  '
    ) === 'farm.manager',
    'username is trimmed'
);

verify_identity(
    account_identity_normalize_full_name(
        '  Farm Manager  '
    ) === 'Farm Manager',
    'full name is trimmed'
);

$username100 =
    str_repeat(
        'u',
        100
    );

$fullName150 =
    str_repeat(
        'n',
        150
    );

verify_identity(
    account_identity_normalize_username(
        $username100
    ) === $username100,
    'username accepts exactly 100 characters'
);

verify_identity(
    account_identity_normalize_full_name(
        $fullName150
    ) === $fullName150,
    'full name accepts exactly 150 characters'
);

$blankUsernameRejected = false;

try {
    account_identity_normalize_username('   ');
} catch (InvalidArgumentException $e) {
    $blankUsernameRejected = true;
}

verify_identity(
    $blankUsernameRejected,
    'blank username is rejected'
);

$blankFullNameRejected = false;

try {
    account_identity_normalize_full_name('   ');
} catch (InvalidArgumentException $e) {
    $blankFullNameRejected = true;
}

verify_identity(
    $blankFullNameRejected,
    'blank full name is rejected'
);

$longUsernameRejected = false;

try {
    account_identity_normalize_username(
        str_repeat(
            'u',
            101
        )
    );
} catch (InvalidArgumentException $e) {
    $longUsernameRejected = true;
}

verify_identity(
    $longUsernameRejected,
    'username longer than 100 is rejected'
);

$longFullNameRejected = false;

try {
    account_identity_normalize_full_name(
        str_repeat(
            'n',
            151
        )
    );
} catch (InvalidArgumentException $e) {
    $longFullNameRejected = true;
}

verify_identity(
    $longFullNameRejected,
    'full name longer than 150 is rejected'
);

$source =
    (string)file_get_contents(
        $path
    );

$executablePolicySignals = [
    'password_security_',
    'password_hash(',
    'password_verify(',
    "['password']",
    'credential_state',
    'account_credential_',
    "['user_type']",
    'beginTransaction(',
    'commit(',
    'rollBack(',
    'INSERT INTO users',
    'UPDATE users',
    'DELETE FROM users',
];

$ownsForbiddenPolicy = false;

foreach ($executablePolicySignals as $signal) {
    if (str_contains($source, $signal)) {
        $ownsForbiddenPolicy = true;
        break;
    }
}

verify_identity(
    !$ownsForbiddenPolicy,
    'identity policy owns no credential, role, persistence, or transaction behavior'
);

if ($failures > 0) {
    echo "V310_ACCOUNT_IDENTITY_POLICY=FAIL\n";
    exit(1);
}

echo "V310_ACCOUNT_IDENTITY_POLICY=PASS\n";
