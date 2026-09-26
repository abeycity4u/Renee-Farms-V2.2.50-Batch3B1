<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$migration =
    $root
    . '/migrations/086_account_credential_delivery_outbox.sql';

$failures = 0;

function check_v310_outbox_schema(
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

check_v310_outbox_schema(
    is_file($migration),
    'credential delivery outbox migration exists'
);

if (!is_file($migration)) {
    echo "V310_ACCOUNT_CREDENTIAL_OUTBOX_SCHEMA=FAIL\n";
    exit(1);
}

$sql =
    (string)file_get_contents(
        $migration
    );

check_v310_outbox_schema(
    str_contains(
        $sql,
        'CREATE TABLE account_credential_delivery_outbox'
    ),
    'migration creates dedicated account credential delivery outbox'
);

check_v310_outbox_schema(
    preg_match(
        '/\buser_id\s+INT\s+NULL\b/i',
        $sql
    ) === 1,
    'outbox user reference is nullable signed INT matching live users.id'
);

check_v310_outbox_schema(
    str_contains(
        $sql,
        "purpose ENUM("
    )
    && str_contains(
        $sql,
        "'activation'"
    )
    && str_contains(
        $sql,
        "'password_reset'"
    ),
    'outbox supports activation and password reset through one shared purpose contract'
);

foreach ([
    'pending',
    'processing',
    'sent',
    'discarded',
    'failed',
] as $status) {
    check_v310_outbox_schema(
        str_contains(
            $sql,
            "'" . $status . "'"
        ),
        'outbox status includes ' . $status
    );
}

check_v310_outbox_schema(
    preg_match(
        '/\battempt_count\s+INT\s+UNSIGNED\s+NOT\s+NULL\s+DEFAULT\s+0\b/i',
        $sql
    ) === 1,
    'outbox has bounded retry accounting field'
);

check_v310_outbox_schema(
    preg_match(
        '/\bavailable_at\s+DATETIME\s+NOT\s+NULL\b/i',
        $sql
    ) === 1,
    'outbox has durable availability scheduling field'
);

check_v310_outbox_schema(
    preg_match(
        '/\bprocessed_at\s+DATETIME\s+NULL\b/i',
        $sql
    ) === 1,
    'outbox has nullable completion timestamp'
);

check_v310_outbox_schema(
    preg_match(
        '/\blast_error_code\s+VARCHAR\(80\)\s+NULL\b/i',
        $sql
    ) === 1,
    'outbox stores bounded non-secret error classification'
);

check_v310_outbox_schema(
    str_contains(
        $sql,
        'FOREIGN KEY (user_id)'
    )
    && str_contains(
        $sql,
        'REFERENCES users(id)'
    )
    && str_contains(
        $sql,
        'ON DELETE SET NULL'
    ),
    'outbox user foreign key follows platform historical SET NULL deletion semantics'
);

check_v310_outbox_schema(
    str_contains(
        $sql,
        'ENGINE=InnoDB'
    ),
    'outbox explicitly uses InnoDB'
);

check_v310_outbox_schema(
    str_contains(
        $sql,
        'idx_account_credential_outbox_pending'
    )
    && preg_match(
        '/idx_account_credential_outbox_pending\s*\(\s*status\s*,\s*available_at\s*,\s*id\s*\)/is',
        $sql
    ) === 1,
    'outbox has worker candidate index by status availability and id'
);

check_v310_outbox_schema(
    str_contains(
        $sql,
        'idx_account_credential_outbox_user_purpose'
    ),
    'outbox has user purpose status lookup index'
);

/*
 * Secret/account-identity columns must never enter this table.
 * Comments are removed first so the documented privacy contract itself
 * cannot create false positives.
 */
$executable =
    preg_replace(
        '#/\*.*?\*/#s',
        ' ',
        $sql
    );

$executable =
    preg_replace(
        '/^[ \t]*--.*$/m',
        ' ',
        (string)$executable
    );

foreach ([
    'username',
    'workspace_id',
    'workspace',
    'email',
    'token',
    'token_hash',
    'raw_token',
    'credential_url',
    'message_body',
    'body',
    'subject',
    'recipient',
] as $forbidden) {
    check_v310_outbox_schema(
        preg_match(
            '/\b'
            . preg_quote($forbidden, '/')
            . '\b/i',
            (string)$executable
        ) !== 1,
        'outbox executable schema does not persist ' . $forbidden
    );
}

check_v310_outbox_schema(
    !preg_match(
        '/\blast_error_message\b|\berror_message\b|\bexception_message\b/i',
        (string)$executable
    ),
    'outbox does not persist raw exception or transport message text'
);

check_v310_outbox_schema(
    str_contains(
        $sql,
        "'086_account_credential_delivery_outbox.sql'"
    ),
    'migration records canonical schema migration marker'
);

$tablePos =
    strpos(
        $sql,
        'CREATE TABLE account_credential_delivery_outbox'
    );

$markerPos =
    strpos(
        $sql,
        'INSERT INTO schema_migrations'
    );

check_v310_outbox_schema(
    $tablePos !== false
    && $markerPos !== false
    && $tablePos < $markerPos,
    'migration marker follows outbox DDL'
);

if ($failures > 0) {
    echo "V310_ACCOUNT_CREDENTIAL_OUTBOX_SCHEMA=FAIL\n";
    exit(1);
}

echo "V310_ACCOUNT_CREDENTIAL_OUTBOX_SCHEMA=PASS\n";
