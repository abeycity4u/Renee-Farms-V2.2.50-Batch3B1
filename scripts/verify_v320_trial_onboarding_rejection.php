<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$path =
    $root
    . '/includes/trial_onboarding_review.php';

$source =
    is_file($path)
        ? (string)file_get_contents($path)
        : '';

$failures = 0;

function rejection_check(
    bool $condition,
    string $message
): void {
    global $failures;

    echo ($condition ? 'PASS: ' : 'FAIL: ')
        . $message
        . PHP_EOL;

    if (!$condition) {
        $failures++;
    }
}

rejection_check(
    str_contains(
        $source,
        'trial_onboarding_review_reject'
    ),
    'shared rejection entry point exists'
);

rejection_check(
    str_contains(
        $source,
        'trial_onboarding_review_existing_rejection'
    ),
    'idempotent existing rejection helper exists'
);

rejection_check(
    str_contains(
        $source,
        'trial_onboarding_review_normalize_reason_code'
    ),
    'rejection consumes shared reason-code normalization'
);

rejection_check(
    str_contains(
        $source,
        "'A rejection reason code is required.'"
    ),
    'rejection reason code is mandatory'
);

rejection_check(
    str_contains(
        $source,
        "'Rejecting Platform Owner is invalid.'"
    ),
    'rejection requires an actor identity'
);

rejection_check(
    str_contains(
        $source,
        'FOR UPDATE'
    ),
    'rejection locks request row'
);

rejection_check(
    str_contains(
        $source,
        "trial_onboarding_request_assert_transition(
                \$status,
                'rejected'
            )"
    ),
    'rejection consumes shared lifecycle authority'
);

rejection_check(
    preg_match(
        "/status\\s*=\\s*'rejected'/",
        $source
    ) === 1,
    'rejection writes rejected lifecycle state'
);

rejection_check(
    str_contains(
        $source,
        'rejection_reason_code = ?'
    ),
    'rejection freezes reason code'
);

rejection_check(
    str_contains(
        $source,
        'rejected_by_user_id = ?'
    ),
    'rejection freezes rejecting actor'
);

rejection_check(
    str_contains(
        $source,
        'rejected_at = NOW()'
    ),
    'rejection freezes rejection timestamp'
);

rejection_check(
    str_contains(
        $source,
        "if (\$status === 'rejected')"
    ),
    'already-rejected retry uses idempotent path'
);

rejection_check(
    str_contains(
        $source,
        "if (\$status !== 'pending_review')"
    ),
    'first-time rejection is pending-review only'
);

rejection_check(
    str_contains(
        $source,
        "AND approved_plan_code IS NULL"
    )
    && str_contains(
        $source,
        "AND approved_by_user_id IS NULL"
    )
    && str_contains(
        $source,
        "AND provisioning_started_at IS NULL"
    ),
    'rejection fails closed on approval or provisioning state'
);

rejection_check(
    str_contains(
        $source,
        'Trial onboarding review owns its database transaction.'
    ),
    'rejection authority owns transaction boundary'
);

rejection_check(
    !preg_match(
        '/trial_onboarding_review_reject[\s\S]{0,12000}tenant_provisioning_create\s*\(/',
        $source
    ),
    'rejection does not provision tenant'
);

rejection_check(
    !preg_match(
        '/trial_onboarding_review_reject[\s\S]{0,12000}INSERT\s+INTO\s+(farms|users)\b/i',
        $source
    ),
    'rejection does not create farms or users'
);

rejection_check(
    !preg_match(
        '/trial_onboarding_review_reject[\s\S]{0,12000}(subscription_starts_at|trial_ends_at)/i',
        $source
    ),
    'rejection does not start trial clocks'
);

rejection_check(
    !preg_match(
        '/trial_onboarding_review_reject[\s\S]{0,12000}(activation_token|credential_outbox|password)/i',
        $source
    ),
    'rejection owns no credential material'
);

rejection_check(
    !preg_match(
        '/trial_onboarding_review_reject[\s\S]{0,12000}(payment|billing_payment|subscription_application)/i',
        $source
    ),
    'rejection does not apply billing'
);

exit(
    $failures === 0
        ? 0
        : 1
);
