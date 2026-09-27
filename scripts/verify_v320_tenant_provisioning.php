<?php

$root = dirname(__DIR__);

$service =
    $root
    . '/includes/tenant_provisioning.php';

$fail = false;

function verify_tenant_provisioning(
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

verify_tenant_provisioning(
    is_file($service),
    'shared tenant provisioning service exists'
);

$source =
    is_file($service)
        ? (string)file_get_contents($service)
        : '';

foreach ([
    'farm_contact_email.php',
    'farm_profile.php',
    'account_identity_policy.php',
    'account_pending_user.php',
    'farm_entitlements.php',
    'subscription_plan_catalog.php',
    'subscription_seat_policy.php',
    'subscription_record.php',
] as $dependency) {
    verify_tenant_provisioning(
        str_contains(
            $source,
            $dependency
        ),
        'shared dependency declared: '
            . $dependency
    );
}

verify_tenant_provisioning(
    str_contains(
        $source,
        'function tenant_provisioning_assert_transaction'
    ),
    'service owns caller-transaction assertion'
);

verify_tenant_provisioning(
    !preg_match(
        '/->\s*(beginTransaction|commit|rollBack)\s*\(/',
        $source
    ),
    'service does not own transaction begin/commit/rollback'
);

verify_tenant_provisioning(
    str_contains(
        $source,
        'function tenant_provisioning_normalize_contract'
    ),
    'service owns canonical creation-contract normalization'
);

verify_tenant_provisioning(
    str_contains(
        $source,
        'farm_profile_normalize_identity'
    )
        && str_contains(
            $source,
            'farm_profile_assert_workspace_id_available'
        ),
    'service reuses canonical farm identity authorities'
);

verify_tenant_provisioning(
    str_contains(
        $source,
        'account_identity_normalize_username'
    )
        && str_contains(
            $source,
            'account_identity_normalize_full_name'
        ),
    'service reuses canonical account identity policy'
);

verify_tenant_provisioning(
    str_contains(
        $source,
        'farm_contact_email_pair'
    ),
    'Farm Admin credential email and farm contact email use shared pairing policy'
);

verify_tenant_provisioning(
    str_contains(
        $source,
        'subscription_plan_effective_role_limits'
    ),
    'effective role limits come from plan authority'
);

verify_tenant_provisioning(
    str_contains(
        $source,
        'account_pending_user_create'
    ),
    'initial Farm Admin uses shared pending-account lifecycle'
);

verify_tenant_provisioning(
    str_contains(
        $source,
        'sync_farm_entitlements'
    ),
    'tenant modules use shared entitlement authority'
);

verify_tenant_provisioning(
    str_contains(
        $source,
        'assign_protected_farm_admin_role'
    ),
    'Farm Admin role assignment uses shared protected-role authority'
);

verify_tenant_provisioning(
    str_contains(
        $source,
        'subscription_seat_save_addons'
    ),
    'seat add-ons use shared commercial authority'
);

verify_tenant_provisioning(
    str_contains(
        $source,
        'subscription_record_capture'
    ),
    'initial commercial history uses shared subscription record authority'
);

verify_tenant_provisioning(
    !str_contains(
        $source,
        'account_credential_issue_token'
    )
        && !str_contains(
            $source,
            'mail('
        )
        && !str_contains(
            $source,
            'farm_onboarding_mail'
        ),
    'service does not duplicate token generation or synchronous onboarding mail'
);

verify_tenant_provisioning(
    !str_contains(
        $source,
        'trial_onboarding_requests'
    ),
    'generic tenant provisioner is not coupled to public onboarding request storage'
);

verify_tenant_provisioning(
    !str_contains(
        $source,
        'billing_payment_attempts'
    )
        && !str_contains(
            $source,
            'billing_subscription_application'
        ),
    'generic tenant provisioner does not duplicate paid billing application'
);

require_once $service;

verify_tenant_provisioning(
    tenant_provisioning_create_statuses()
        === [
            'trial',
            'active',
            'past_due',
            'suspended',
        ],
    'initial status contract preserves current management create policy'
);

verify_tenant_provisioning(
    tenant_provisioning_normalize_datetime(
        '2026-09-27 22:00:00'
    ) === '2026-09-27 22:00:00',
    'canonical datetime accepts exact Y-m-d H:i:s'
);

verify_tenant_provisioning(
    tenant_provisioning_normalize_datetime(
        null
    ) === null
        && tenant_provisioning_normalize_datetime(
            ''
        ) === null,
    'canonical datetime preserves nullable subscription boundaries'
);

$badDateRejected = false;

try {
    tenant_provisioning_normalize_datetime(
        '27/09/2026'
    );
} catch (InvalidArgumentException $e) {
    $badDateRejected = true;
}

verify_tenant_provisioning(
    $badDateRejected,
    'invalid subscription datetime is rejected centrally'
);

$contract = null;

try {
    $contract =
        tenant_provisioning_normalize_contract([
            'name' =>
                'Provisioning Contract QA',

            'slug' =>
                'provisioning-contract-qa',

            'primary_color' =>
                '#198754',

            'admin_username' =>
                'provisionqa01',

            'admin_full_name' =>
                'Provisioning QA Admin',

            'admin_email' =>
                'provisioning@example.com',

            'contact_name' =>
                'Provisioning Contact',

            'contact_email' =>
                'billing@example.com',

            'plan_code' =>
                'starter',

            'subscription_status' =>
                'trial',

            'subscription_starts_at' =>
                null,

            'trial_ends_at' =>
                null,

            'subscription_ends_at' =>
                null,

            'modules' => [
                'poultry',
            ],

            'seat_addons' => [],

            'history_reason' =>
                'tenant_created',
        ]);
} catch (Throwable $e) {
    echo 'NORMALIZE_CONTRACT_ERROR='
        . $e->getMessage()
        . PHP_EOL;
}

verify_tenant_provisioning(
    is_array($contract),
    'pure tenant creation contract normalizes successfully'
);

if (is_array($contract)) {
    verify_tenant_provisioning(
        $contract['name']
            === 'Provisioning Contract QA'
            && $contract['slug']
                === 'provisioning-contract-qa',
        'farm identity survives canonical normalization'
    );

    verify_tenant_provisioning(
        $contract['admin_username']
            === 'provisionqa01'
            && $contract['admin_full_name']
                === 'Provisioning QA Admin',
        'Farm Admin identity survives canonical normalization'
    );

    verify_tenant_provisioning(
        $contract['admin_email']
            === 'provisioning@example.com'
            && $contract['contact_email']
                === 'billing@example.com',
        'credential email remains distinct from farm contact email'
    );

    verify_tenant_provisioning(
        $contract['plan_code']
            === 'starter'
            && $contract['subscription_status']
                === 'trial',
        'Starter trial contract is normalized'
    );

    verify_tenant_provisioning(
        $contract['subscription_starts_at']
            === null
            && $contract['trial_ends_at']
                === null
            && $contract['subscription_ends_at']
                === null,
        'pre-activation trial may be provisioned with no trial clock'
    );

    verify_tenant_provisioning(
        in_array(
            'poultry',
            $contract['modules'],
            true
        ),
        'Poultry entitlement survives normalization'
    );

    verify_tenant_provisioning(
        ($contract['role_limits'][
            'poultry_manager'
        ] ?? null) === 1
            && ($contract['role_limits'][
                'ruminant_manager'
            ] ?? null) === 0,
        'Starter role limits are derived from selected modules'
    );

    verify_tenant_provisioning(
        ($contract['seat_addons'][
            'poultry_manager'
        ] ?? null) === 0
            && ($contract['seat_addons'][
                'viewer'
            ] ?? null) === 0,
        'new tenant defaults to zero paid seat add-ons'
    );
}

$noModulesRejected = false;

try {
    tenant_provisioning_normalize_contract([
        'name' => 'No Module QA',
        'slug' => 'no-module-qa',
        'admin_username' => 'nomoduleqa',
        'admin_full_name' => 'No Module QA',
        'admin_email' => 'nomodule@example.com',
        'contact_email' => 'nomodule@example.com',
        'modules' => [],
    ]);
} catch (InvalidArgumentException $e) {
    $noModulesRejected = true;
}

verify_tenant_provisioning(
    $noModulesRejected,
    'tenant without service entitlement is rejected'
);

$badPlanRejected = false;

try {
    tenant_provisioning_normalize_contract([
        'name' => 'Bad Plan QA',
        'slug' => 'bad-plan-qa',
        'admin_username' => 'badplanqa',
        'admin_full_name' => 'Bad Plan QA',
        'admin_email' => 'badplan@example.com',
        'contact_email' => 'badplan@example.com',
        'modules' => ['poultry'],
        'plan_code' => 'not-a-plan',
    ]);
} catch (InvalidArgumentException $e) {
    $badPlanRejected = true;
}

verify_tenant_provisioning(
    $badPlanRejected,
    'unknown subscription plan is rejected'
);

$badStatusRejected = false;

try {
    tenant_provisioning_normalize_contract([
        'name' => 'Bad Status QA',
        'slug' => 'bad-status-qa',
        'admin_username' => 'badstatusqa',
        'admin_full_name' => 'Bad Status QA',
        'admin_email' => 'badstatus@example.com',
        'contact_email' => 'badstatus@example.com',
        'modules' => ['poultry'],
        'subscription_status' => 'cancelled',
    ]);
} catch (InvalidArgumentException $e) {
    $badStatusRejected = true;
}

verify_tenant_provisioning(
    $badStatusRejected,
    'unsupported initial status is rejected'
);

echo $fail
    ? "T14_B2C_PROVISIONING_VERIFIER=FAIL\n"
    : "T14_B2C_PROVISIONING_VERIFIER=PASS\n";

exit($fail ? 1 : 0);
