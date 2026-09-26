<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$service =
    $root . '/includes/account_credential_lifecycle.php';

$schema =
    $root . '/migrations/085_account_credential_lifecycle.sql';

$failures = 0;

function check_v310(
    bool $condition,
    string $message
): void {
    global $failures;

    if ($condition) {
        echo 'PASS: ' . $message . PHP_EOL;
        return;
    }

    echo 'FAIL: ' . $message . PHP_EOL;
    $failures++;
}

check_v310(
    is_file($service),
    'shared account credential lifecycle service exists'
);

$source =
    is_file($service)
        ? (string)file_get_contents($service)
        : '';

$schemaSource =
    is_file($schema)
        ? (string)file_get_contents($schema)
        : '';

check_v310(
    str_contains(
        $source,
        "require_once __DIR__ . '/password_security.php';"
    ),
    'service composes the central password security policy'
);

foreach ([
    'account_credential_normalize_email',
    'account_credential_normalize_purpose',
    'account_credential_token_hash',
    'account_credential_generate_token',
    'account_credential_login_allowed',
    'account_credential_load_user_for_update',
    'account_credential_invalidate_tokens',
    'account_credential_issue_token',
    'account_credential_lock_token',
    'account_credential_mark_consumed',
    'account_credential_consume_activation',
    'account_credential_consume_password_reset',
] as $function) {
    check_v310(
        str_contains(
            $source,
            'function ' . $function . '('
        ),
        $function . ' is centrally defined'
    );
}

check_v310(
    str_contains(
        $source,
        'bin2hex(random_bytes(32))'
    ),
    'credential tokens use cryptographic randomness'
);

check_v310(
    str_contains(
        $source,
        "hash('sha256', \$rawToken)"
    ),
    'credential token persistence uses SHA-256 token hashes'
);

check_v310(
    str_contains(
        $source,
        'token_hash'
    )
    && !preg_match(
        '/INSERT\s+INTO\s+account_credential_tokens[\s\S]*?\btoken\b\s*,/i',
        $source
    ),
    'raw credential token is not inserted into the database'
);

check_v310(
    str_contains(
        $source,
        "['activation', 'password_reset']"
    ),
    'service accepts only activation and password_reset purposes'
);

check_v310(
    str_contains(
        $source,
        'DATE_ADD(NOW(), INTERVAL ? SECOND)'
    ),
    'issued tokens have explicit database expiry'
);

check_v310(
    str_contains(
        $source,
        'AND consumed_at IS NULL'
    ),
    'token mutation is constrained to unconsumed tokens'
);

check_v310(
    str_contains(
        $source,
        'AND t.expires_at > NOW()'
    )
    && !str_contains(
        $source,
        '$expiresAt <= time()'
    )
    && !str_contains(
        $source,
        'strtotime((string)$token[\'expires_at\'])'
    ),
    'token expiry uses database clock authority'
);

check_v310(
    str_contains(
        $source,
        "credential_state = 'active'"
    ),
    'activation centrally transitions credential state to active'
);

check_v310(
    str_contains(
        $source,
        "credential_state = 'pending_activation'"
    ),
    'activation requires pending_activation state'
);

check_v310(
    substr_count(
        $source,
        'password_security_validate($newPassword)'
    ) === 2,
    'activation and reset both use central password validation'
);

check_v310(
    substr_count(
        $source,
        'password_security_hash($newPassword)'
    ) === 2,
    'activation and reset both use central password hashing'
);

check_v310(
    substr_count(
        $source,
        'account_credential_invalidate_tokens('
    ) >= 4,
    'service centrally invalidates superseded credential tokens'
);

check_v310(
    str_contains(
        $source,
        "\$purpose === 'activation'"
    )
    && str_contains(
        $source,
        "\$credentialState !== 'pending_activation'"
    ),
    'activation tokens are issued only to pending_activation accounts'
);

check_v310(
    str_contains(
        $source,
        "\$purpose === 'password_reset'"
    )
    && str_contains(
        $source,
        "\$credentialState !== 'active'"
    ),
    'password reset tokens are issued only to active accounts'
);

check_v310(
    preg_match(
        "/UPDATE users\\s+SET password = \\?\\s+WHERE id = \\?\\s+AND credential_state = 'active'/is",
        $source
    ) === 1,
    'password reset consumption requires the account to remain active'
);

check_v310(
    substr_count(
        $source,
        '$pdo->beginTransaction()'
    ) >= 3
    && substr_count(
        $source,
        '$pdo->commit()'
    ) >= 3
    && substr_count(
        $source,
        '$pdo->rollBack()'
    ) >= 3,
    'credential state changes use transaction ownership'
);

check_v310(
    str_contains(
        $source,
        'FOR UPDATE'
    ),
    'credential consumption uses row locking'
);

check_v310(
    str_contains(
        $source,
        "hash_equals(\n                'active',"
    ),
    'login eligibility is explicit active-state policy'
);

check_v310(
    !preg_match(
        '/\bmail\s*\(|\bplatform_mail\b|\bBASE_URL\b|\bheader\s*\(/i',
        $source
    ),
    'persistence service does not own mail, URL, or HTTP routing'
);

check_v310(
    !str_contains(
        $source,
        'require_rate_limit('
    )
    && !str_contains(
        $source,
        'csrf_token('
    )
    && !str_contains(
        $source,
        'verify_csrf_token('
    ),
    'route-level rate limiting and CSRF stay outside persistence service'
);

check_v310(
    str_contains(
        $schemaSource,
        'account_credential_tokens'
    )
    && str_contains(
        $schemaSource,
        'credential_state'
    ),
    'service is backed by migration 085 schema authority'
);

if ($failures > 0) {
    echo "V310_ACCOUNT_CREDENTIAL_LIFECYCLE_CONTRACT=FAIL\n";
    exit(1);
}

echo "V310_ACCOUNT_CREDENTIAL_LIFECYCLE_CONTRACT=PASS\n";
