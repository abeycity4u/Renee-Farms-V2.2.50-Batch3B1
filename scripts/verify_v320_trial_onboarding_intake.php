<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$target =
    $root
    . '/includes/trial_onboarding_intake.php';

$failures = 0;

function verify_pass(
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

$source =
    is_file($target)
        ? (string)file_get_contents($target)
        : '';

verify_pass(
    $source !== '',
    'public trial intake authority exists'
);

$requiredIncludes = [
    'trial_onboarding_request.php',
    'farm_profile.php',
    'account_identity_policy.php',
    'account_credential_lifecycle.php',
    'farm_contact_email.php',
    'farm_entitlements.php',
];

foreach ($requiredIncludes as $include) {
    verify_pass(
        str_contains(
            $source,
            $include
        ),
        'intake delegates to '
        . $include
    );
}

$requiredFunctions = [
    'trial_onboarding_intake_text',
    'trial_onboarding_intake_source_fingerprint',
    'trial_onboarding_intake_open_statuses',
    'trial_onboarding_intake_normalize',
    'trial_onboarding_intake_assert_available',
    'trial_onboarding_intake_create',
];

foreach ($requiredFunctions as $function) {
    verify_pass(
        str_contains(
            $source,
            'function ' . $function
        ),
        'intake owns '
        . $function
    );
}

verify_pass(
    str_contains(
        $source,
        'farm_profile_assert_workspace_id_available'
    ),
    'existing farm workspace uniqueness stays centralized'
);

verify_pass(
    str_contains(
        $source,
        'account_identity_normalize_username'
    )
    && str_contains(
        $source,
        'account_identity_normalize_full_name'
    ),
    'Farm Admin identity normalization stays centralized'
);

verify_pass(
    str_contains(
        $source,
        'account_credential_normalize_email'
    ),
    'credential email normalization stays centralized'
);

verify_pass(
    str_contains(
        $source,
        'farm_entitlement_normalize_modules'
    ),
    'requested module normalization stays centralized'
);

verify_pass(
    str_contains(
        $source,
        "'pending_review'"
    ),
    'public intake creates pending-review requests'
);

verify_pass(
    str_contains(
        $source,
        'approved_plan_code'
    )
    && str_contains(
        $source,
        'approved_modules_snapshot'
    )
    && str_contains(
        $source,
        'approved_role_limits_snapshot'
    )
    && str_contains(
        $source,
        'approved_trial_days'
    ),
    'approval-owned fields are represented explicitly'
);

verify_pass(
    preg_match(
        "/approved_plan_code,[\\s\\S]*?NULL,[\\s\\S]*?NULL,[\\s\\S]*?NULL,[\\s\\S]*?NULL,/m",
        $source
    ) === 1,
    'public intake leaves approval-owned snapshots null'
);

verify_pass(
    !preg_match(
        '/INSERT\\s+INTO\\s+farms\\b/i',
        $source
    ),
    'intake does not create farms'
);

verify_pass(
    !preg_match(
        '/INSERT\\s+INTO\\s+users\\b/i',
        $source
    ),
    'intake does not create users'
);

verify_pass(
    !str_contains(
        $source,
        'tenant_provisioning_create('
    ),
    'intake does not provision tenants'
);

verify_pass(
    !str_contains(
        $source,
        'account_credential_issue_token('
    ),
    'intake does not issue credential tokens'
);

verify_pass(
    !str_contains(
        $source,
        'account_credential_send('
    ),
    'intake does not send activation credentials'
);

verify_pass(
    !preg_match(
        '/INSERT\\s+INTO\\s+subscriptions\\b/i',
        $source
    ),
    'intake does not write subscription history'
);

verify_pass(
    !preg_match(
        '/UPDATE\\s+farms\\b/i',
        $source
    ),
    'intake does not start or alter tenant subscription state'
);

verify_pass(
    str_contains(
        $source,
        'source_fingerprint'
    )
    && str_contains(
        $source,
        "hash(\n            'sha256'"
    ),
    'source fingerprint is stored as a one-way SHA-256 digest'
);

verify_pass(
    str_contains(
        $source,
        "'pending_review',"
    )
    && str_contains(
        $source,
        "'approved',"
    )
    && str_contains(
        $source,
        "'provisioning',"
    )
    && str_contains(
        $source,
        "'provisioned',"
    ),
    'duplicate-open-request policy covers all non-terminal pre-activation states'
);

verify_pass(
    str_contains(
        $source,
        '$startedTransaction'
    )
    && str_contains(
        $source,
        '$pdo->beginTransaction()'
    )
    && str_contains(
        $source,
        '$pdo->commit()'
    )
    && str_contains(
        $source,
        '$pdo->rollBack()'
    ),
    'intake supports caller-owned transactions'
);


verify_pass(
    str_contains(
        $source,
        'final class TrialOnboardingIntakeConflict'
    )
    && str_contains(
        $source,
        'extends DomainException'
    )
    && str_contains(
        $source,
        'function reasonCode()'
    ),
    'intake exposes typed open-request conflicts'
);

foreach ([
    'workspace_request_in_progress',
    'admin_email_request_in_progress',
    'open_request_exists',
] as $reasonCode) {
    verify_pass(
        str_contains(
            $source,
            "'" . $reasonCode . "'"
        ),
        'intake supports conflict reason '
        . $reasonCode
    );
}

verify_pass(
    str_contains(
        $source,
        '$workspaceMatched'
    )
    && str_contains(
        $source,
        '$emailMatched'
    )
    && str_contains(
        $source,
        '$fingerprintMatched'
    ),
    'intake classifies matching open-request signals centrally'
);

verify_pass(
    !str_contains(
        $source,
        'A matching trial request is already being processed.'
    ),
    'legacy undifferentiated conflict message is retired'
);


exit(
    $failures === 0
        ? 0
        : 1
);
