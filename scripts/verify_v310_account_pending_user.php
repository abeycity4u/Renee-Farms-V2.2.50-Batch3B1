<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$servicePath =
    $root
    . '/includes/account_pending_user.php';

$failures = 0;

function verify_v310_pending_user(
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

verify_v310_pending_user(
    is_file($servicePath),
    'shared pending-account service exists'
);

if (!is_file($servicePath)) {
    echo "V310_PENDING_ACCOUNT_SERVICE=FAIL\n";
    exit(1);
}

$source =
    (string)file_get_contents(
        $servicePath
    );

verify_v310_pending_user(
    str_contains(
        $source,
        "'/account_credential_lifecycle.php'"
    ),
    'service reuses credential lifecycle authority'
);

verify_v310_pending_user(
    str_contains(
        $source,
        "'/account_credential_outbox.php'"
    ),
    'service reuses durable credential outbox'
);

verify_v310_pending_user(
    str_contains(
        $source,
        'function account_pending_user_assert_transaction('
    )
    && str_contains(
        $source,
        '$pdo->inTransaction()'
    ),
    'pending-account mutation requires caller transaction'
);

verify_v310_pending_user(
    str_contains(
        $source,
        'function account_pending_user_placeholder_hash('
    )
    && str_contains(
        $source,
        'random_bytes(32)'
    )
    && str_contains(
        $source,
        'password_security_hash('
    ),
    'placeholder password is random and centrally hashed'
);

verify_v310_pending_user(
    str_contains(
        $source,
        'account_credential_normalize_email('
    ),
    'credential email uses lifecycle normalization'
);

verify_v310_pending_user(
    str_contains(
        $source,
        "'pending_activation'"
    ),
    'new accounts explicitly use pending activation state'
);

verify_v310_pending_user(
    preg_match(
        '/INSERT\s+INTO\s+users[\s\S]{0,700}credential_state[\s\S]{0,700}pending_activation/i',
        $source
    ) === 1,
    'user insert explicitly persists pending activation'
);

verify_v310_pending_user(
    substr_count(
        $source,
        "account_credential_outbox_enqueue("
    ) >= 2,
    'create and email-correction flows enqueue activation intent'
);

verify_v310_pending_user(
    substr_count(
        $source,
        "'activation'"
    ) >= 2,
    'outbox intent is activation purpose'
);

verify_v310_pending_user(
    str_contains(
        $source,
        'function account_pending_user_update_email('
    ),
    'pending credential-email correction is centralized'
);

verify_v310_pending_user(
    str_contains(
        $source,
        'FOR UPDATE'
    ),
    'pending email correction locks account row'
);

verify_v310_pending_user(
    preg_match(
        '/credential_state[\s\S]{0,240}pending_activation/',
        $source
    ) === 1,
    'email correction is restricted to pending accounts'
);

verify_v310_pending_user(
    !str_contains(
        $source,
        'platform_mail_send('
    )
    && !str_contains(
        $source,
        'account_credential_send('
    ),
    'writer service performs no synchronous mail delivery'
);

verify_v310_pending_user(
    !str_contains(
        $source,
        'account_credential_issue_token('
    ),
    'writer service never creates raw credential tokens'
);

verify_v310_pending_user(
    !str_contains(
        $source,
        'beginTransaction('
    )
    && !str_contains(
        $source,
        'commit('
    )
    && !str_contains(
        $source,
        'rollBack('
    ),
    'writer service never owns caller transaction boundaries'
);

$placeholderReturnsRawSecret =
    preg_match(
        '/return\s+\$unusableSecret\s*;/',
        $source
    ) === 1;

$placeholderReturnsHashVariable =
    preg_match(
        '/return\s+\$placeholderHash\s*;/',
        $source
    ) === 1;

$placeholderReturnsOneWayHash =
    preg_match(
        '/return\s+password_security_hash\s*\(\s*\$unusableSecret\s*\)\s*;/s',
        $source
    ) === 1;

$publicResultLeaksPlaceholder =
    preg_match(
        '/[\'"](?:placeholder|placeholder_hash|password|raw_password|unusable_secret)[\'"]\s*=>/i',
        $source
    ) === 1;

verify_v310_pending_user(
    !$placeholderReturnsRawSecret
    && !$placeholderReturnsHashVariable
    && $placeholderReturnsOneWayHash
    && !$publicResultLeaksPlaceholder,
    'placeholder raw credential material is never returned'
);


verify_v310_pending_user(
    str_contains(
        $source,
        'function account_pending_user_resend_activation('
    ),
    'activation resend is a separate explicit operation'
);

$updateEmailStart =
    strpos(
        $source,
        'function account_pending_user_update_email('
    );

$resendStart =
    strpos(
        $source,
        'function account_pending_user_resend_activation('
    );

$updateEmailBody =
    (
        $updateEmailStart !== false
        && $resendStart !== false
        && $updateEmailStart < $resendStart
    )
        ? substr(
            $source,
            $updateEmailStart,
            $resendStart - $updateEmailStart
        )
        : '';

verify_v310_pending_user(
    str_contains(
        $updateEmailBody,
        'if (hash_equals($currentEmail, $email))'
    )
    && str_contains(
        $updateEmailBody,
        "'email_changed' => false"
    )
    && str_contains(
        $updateEmailBody,
        "'outbox_job_id' => null"
    ),
    'unchanged normalized email is a no-op without delivery job'
);

$unchangedReturnPos =
    strpos(
        $updateEmailBody,
        "'outbox_job_id' => null"
    );

$emailUpdatePos =
    strpos(
        $updateEmailBody,
        'UPDATE users'
    );

$emailOutboxPos =
    strpos(
        $updateEmailBody,
        'account_credential_outbox_enqueue('
    );

verify_v310_pending_user(
    $unchangedReturnPos !== false
    && $emailUpdatePos !== false
    && $emailOutboxPos !== false
    && $unchangedReturnPos < $emailUpdatePos
    && $emailUpdatePos < $emailOutboxPos,
    'changed email alone reaches update and activation enqueue'
);

verify_v310_pending_user(
    str_contains(
        $updateEmailBody,
        "'email_changed' => true"
    ),
    'changed email result records the mutation'
);

$resendBody =
    $resendStart === false
        ? ''
        : substr(
            $source,
            $resendStart
        );

verify_v310_pending_user(
    str_contains(
        $resendBody,
        'account_pending_user_assert_transaction('
    ),
    'explicit resend requires caller-owned transaction'
);

verify_v310_pending_user(
    str_contains(
        $resendBody,
        'FOR UPDATE'
    )
    && str_contains(
        $resendBody,
        'pending_activation'
    ),
    'explicit resend locks and restricts account to pending state'
);

verify_v310_pending_user(
    str_contains(
        $resendBody,
        'account_credential_normalize_email('
    ),
    'explicit resend validates current credential email'
);

verify_v310_pending_user(
    str_contains(
        $resendBody,
        'account_credential_outbox_enqueue('
    )
    && str_contains(
        $resendBody,
        "'activation'"
    ),
    'explicit resend creates durable activation intent'
);

verify_v310_pending_user(
    !str_contains(
        $resendBody,
        'platform_mail_send('
    )
    && !str_contains(
        $resendBody,
        'account_credential_send('
    )
    && !str_contains(
        $resendBody,
        'account_credential_issue_token('
    ),
    'explicit resend performs no mail or raw-token work'
);

if ($failures > 0) {
    echo "V310_PENDING_ACCOUNT_SERVICE=FAIL\n";
    exit(1);
}

echo "V310_PENDING_ACCOUNT_SERVICE=PASS\n";
