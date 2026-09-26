<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$migration = $root . '/migrations/085_account_credential_lifecycle.sql';

$failures = 0;

function verify_v310(bool $condition, string $message): void
{
    global $failures;

    if ($condition) {
        echo 'PASS: ' . $message . PHP_EOL;
        return;
    }

    echo 'FAIL: ' . $message . PHP_EOL;
    $failures++;
}

verify_v310(
    is_file($migration),
    'migration 085 exists'
);

$sql = is_file($migration)
    ? (string)file_get_contents($migration)
    : '';

verify_v310(
    str_contains(
        $sql,
        'ADD COLUMN credential_state'
    ),
    'users credential_state column is introduced'
);

verify_v310(
    preg_match(
        "/credential_state\\s+ENUM\\s*\\(\\s*'active'\\s*,\\s*'pending_activation'\\s*\\)/is",
        $sql
    ) === 1,
    'credential state is limited to active and pending_activation'
);

verify_v310(
    preg_match(
        "/credential_state.*NOT NULL DEFAULT 'active'/is",
        $sql
    ) === 1,
    'legacy users remain active by default'
);

verify_v310(
    str_contains(
        $sql,
        'CREATE TABLE account_credential_tokens'
    ),
    'shared account credential token table is created'
);

verify_v310(
    preg_match(
        "/purpose\\s+ENUM\\s*\\(\\s*'activation'\\s*,\\s*'password_reset'\\s*\\)/is",
        $sql
    ) === 1,
    'token purposes are activation and password_reset only'
);

verify_v310(
    preg_match(
        '/token_hash\\s+CHAR\\(64\\)\\s+NOT NULL/i',
        $sql
    ) === 1,
    'only a fixed-length token hash is persisted'
);

verify_v310(
    preg_match(
        '/UNIQUE KEY\\s+uniq_account_credential_token_hash\\s*\\(\\s*token_hash\\s*\\)/is',
        $sql
    ) === 1,
    'token hashes are unique'
);

verify_v310(
    preg_match(
        '/expires_at\\s+DATETIME\\s+NOT NULL/i',
        $sql
    ) === 1,
    'credential tokens require expiry'
);

verify_v310(
    preg_match(
        '/consumed_at\\s+DATETIME\\s+NULL/i',
        $sql
    ) === 1,
    'credential tokens support one-time consumption state'
);

verify_v310(
    preg_match(
        '/FOREIGN KEY\\s*\\(\\s*user_id\\s*\\).*REFERENCES\\s+users\\s*\\(\\s*id\\s*\\).*ON DELETE CASCADE/is',
        $sql
    ) === 1,
    'credential tokens are user-owned and cascade on user deletion'
);

verify_v310(
    !preg_match(
        '/UNIQUE(?:\\s+KEY)?[^\\n]*(?:users[_ ]?)?email|UNIQUE\\s*\\([^)]*email/is',
        $sql
    ),
    'migration does not impose global email uniqueness'
);

verify_v310(
    !preg_match(
        '/MODIFY\\s+(?:COLUMN\\s+)?email|CHANGE\\s+(?:COLUMN\\s+)?email/is',
        $sql
    ),
    'legacy nullable email contract is not tightened'
);

verify_v310(
    !preg_match(
        '/\bUPDATE\s+users\s+SET\b[^;]*\bpassword\s*=|\bALTER\s+TABLE\s+users\b[^;]*\b(?:ADD|MODIFY|CHANGE|DROP)\s+(?:COLUMN\s+)?password\b/is',
        $sql
    ),
    'existing password hashes are not modified'
);

verify_v310(
    !preg_match(
        '/\\btoken\\b\\s+(?:VARCHAR|CHAR|TEXT)/i',
        $sql
    ),
    'plaintext token column is absent'
);

verify_v310(
    str_contains(
        $sql,
        "'085_account_credential_lifecycle.sql'"
    ),
    'migration records the canonical 085 schema marker'
);

if ($failures > 0) {
    echo 'V310_ACCOUNT_CREDENTIAL_SCHEMA_CONTRACT=FAIL' . PHP_EOL;
    exit(1);
}

echo 'V310_ACCOUNT_CREDENTIAL_SCHEMA_CONTRACT=PASS' . PHP_EOL;
