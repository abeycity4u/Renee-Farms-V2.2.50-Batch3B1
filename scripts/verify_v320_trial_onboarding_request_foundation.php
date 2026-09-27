<?php

$root = dirname(__DIR__);

$migration =
    $root
    . '/migrations/087_trial_onboarding_requests.sql';

$service =
    $root
    . '/includes/trial_onboarding_request.php';

$fail = false;

function verify_trial_onboarding(
    bool $condition,
    string $message
): void {
    global $fail;

    echo ($condition ? 'PASS: ' : 'FAIL: ')
        . $message
        . PHP_EOL;

    if (!$condition) {
        $fail = true;
    }
}

verify_trial_onboarding(
    is_file($migration),
    'migration 087 exists'
);

verify_trial_onboarding(
    is_file($service),
    'shared request lifecycle authority exists'
);

$migrationSource =
    is_file($migration)
        ? (string)file_get_contents($migration)
        : '';

$serviceSource =
    is_file($service)
        ? (string)file_get_contents($service)
        : '';

verify_trial_onboarding(
    str_contains(
        $migrationSource,
        'CREATE TABLE trial_onboarding_requests'
    ),
    'migration creates dedicated onboarding request table'
);

foreach ([
    'pending_review',
    'approved',
    'provisioning',
    'provisioned',
    'activated',
    'rejected',
    'cancelled',
] as $status) {
    verify_trial_onboarding(
        str_contains(
            $migrationSource,
            "'" . $status . "'"
        ),
        'schema contains request status ' . $status
    );
}

verify_trial_onboarding(
    str_contains(
        $migrationSource,
        'approval_mode ENUM('
    )
        && str_contains(
            $migrationSource,
            "'auto'"
        )
        && str_contains(
            $migrationSource,
            "'manual'"
        ),
    'approval mode supports auto and manual'
);

verify_trial_onboarding(
    str_contains(
        $migrationSource,
        'UNIQUE KEY uniq_trial_onboarding_request_reference'
    ),
    'request reference is unique'
);

verify_trial_onboarding(
    str_contains(
        $migrationSource,
        'UNIQUE KEY uniq_trial_onboarding_request_farm'
    ),
    'farm binding is uniqueness protected'
);

verify_trial_onboarding(
    str_contains(
        $migrationSource,
        'UNIQUE KEY uniq_trial_onboarding_request_admin'
    ),
    'Farm Admin binding is uniqueness protected'
);

verify_trial_onboarding(
    str_contains(
        $migrationSource,
        'approved_trial_days'
    ),
    'approved trial duration is auditable'
);

verify_trial_onboarding(
    str_contains(
        $migrationSource,
        'source_fingerprint'
    ),
    'abuse-control fingerprint field exists'
);

$schemaOnly =
    preg_replace(
        '/--.*$/m',
        '',
        $migrationSource
    ) ?? '';

verify_trial_onboarding(
    !preg_match(
        '/\b(password|token_hash|raw_token|payment_secret|raw_ip)\b/i',
        $schemaOnly
    ),
    'request schema stores no credential/payment/raw-IP secret fields'
);

verify_trial_onboarding(
    str_contains(
        $serviceSource,
        'function trial_onboarding_request_transition_map'
    ),
    'shared service owns transition map'
);

verify_trial_onboarding(
    str_contains(
        $serviceSource,
        'function trial_onboarding_request_can_transition'
    ),
    'shared service owns transition validation'
);

verify_trial_onboarding(
    str_contains(
        $serviceSource,
        'function trial_onboarding_request_is_provisionable'
    ),
    'shared service owns provisionable-state policy'
);

require_once $service;

verify_trial_onboarding(
    trial_onboarding_request_can_transition(
        'pending_review',
        'approved'
    ),
    'pending request may be approved'
);

verify_trial_onboarding(
    trial_onboarding_request_can_transition(
        'pending_review',
        'rejected'
    ),
    'pending request may be rejected'
);

verify_trial_onboarding(
    trial_onboarding_request_can_transition(
        'approved',
        'provisioning'
    ),
    'approved request may enter provisioning'
);

verify_trial_onboarding(
    trial_onboarding_request_can_transition(
        'provisioning',
        'provisioned'
    ),
    'provisioning may complete to provisioned'
);

verify_trial_onboarding(
    trial_onboarding_request_can_transition(
        'provisioned',
        'activated'
    ),
    'provisioned request may become activated'
);

verify_trial_onboarding(
    !trial_onboarding_request_can_transition(
        'pending_review',
        'provisioned'
    ),
    'request cannot skip approval/provisioning'
);

verify_trial_onboarding(
    !trial_onboarding_request_can_transition(
        'activated',
        'approved'
    ),
    'activated request is terminal'
);

verify_trial_onboarding(
    trial_onboarding_request_is_terminal(
        'activated'
    )
        && trial_onboarding_request_is_terminal(
            'rejected'
        )
        && trial_onboarding_request_is_terminal(
            'cancelled'
        ),
    'terminal request states are centralized'
);

verify_trial_onboarding(
    trial_onboarding_request_is_provisionable(
        'approved'
    )
        && !trial_onboarding_request_is_provisionable(
            'pending_review'
        ),
    'only approved requests are provisionable'
);

verify_trial_onboarding(
    trial_onboarding_trial_days() === 14,
    'canonical trial duration is 14 days'
);

$reference =
    trial_onboarding_request_reference();

verify_trial_onboarding(
    preg_match(
        '/^[a-f0-9]{32}$/',
        $reference
    ) === 1,
    'request reference is 128-bit random hex'
);

verify_trial_onboarding(
    !str_contains(
        $serviceSource,
        'INSERT INTO farms'
    )
        && !str_contains(
            $serviceSource,
            'INSERT INTO users'
        )
        && !str_contains(
            $serviceSource,
            'account_credential_issue_token'
        )
        && !str_contains(
            $serviceSource,
            'billing_payment_attempts'
        )
        && !str_contains(
            $serviceSource,
            'UPDATE farms'
        ),
    'request lifecycle service does not duplicate tenant credential or billing writers'
);

verify_trial_onboarding(
    str_contains(
        $migrationSource,
        "VALUES ('087_trial_onboarding_requests.sql')"
    ),
    'migration registers schema marker'
);

echo $fail
    ? "T14_B1_FOUNDATION=FAIL\n"
    : "T14_B1_FOUNDATION=PASS\n";

exit($fail ? 1 : 0);
