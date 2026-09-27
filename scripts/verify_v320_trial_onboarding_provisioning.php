<?php

$root = dirname(__DIR__);

$service =
    $root
    . '/includes/trial_onboarding_provisioning.php';

$fail = false;

function verify_trial_provisioning(
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

verify_trial_provisioning(
    is_file($service),
    'trial onboarding provisioning authority exists'
);

$source =
    is_file($service)
        ? (string)file_get_contents($service)
        : '';

verify_trial_provisioning(
    str_contains(
        $source,
        "/trial_onboarding_request.php"
    )
        && str_contains(
            $source,
            "/tenant_provisioning.php"
        ),
    'service composes request lifecycle and tenant provisioner authorities'
);

verify_trial_provisioning(
    substr_count(
        $source,
        'tenant_provisioning_create('
    ) === 1,
    'exactly one canonical tenant creation call exists'
);

verify_trial_provisioning(
    str_contains(
        $source,
        'FOR UPDATE'
    ),
    'request row is locked before provisioning'
);

verify_trial_provisioning(
    str_contains(
        $source,
        '$pdo->beginTransaction();'
    )
        && str_contains(
            $source,
            '$pdo->commit();'
        )
        && str_contains(
            $source,
            '$pdo->rollBack();'
        ),
    'provisioning authority owns atomic transaction boundary'
);

$beginPos =
    strpos(
        $source,
        '$pdo->beginTransaction();'
    );

$lockPos =
    strpos(
        $source,
        "                     FOR UPDATE",
        $beginPos === false
            ? 0
            : $beginPos
    );

$markProvisioningPos =
    strpos(
        $source,
        "status = 'provisioning'"
    );

$tenantCreatePos =
    strpos(
        $source,
        'tenant_provisioning_create('
    );

$markProvisionedPos =
    strpos(
        $source,
        "status = 'provisioned'"
    );

$commitPos =
    strrpos(
        $source,
        '$pdo->commit();'
    );

verify_trial_provisioning(
    $beginPos !== false
        && $lockPos !== false
        && $markProvisioningPos !== false
        && $tenantCreatePos !== false
        && $markProvisionedPos !== false
        && $commitPos !== false
        && $beginPos < $lockPos
        && $lockPos < $markProvisioningPos
        && $markProvisioningPos < $tenantCreatePos
        && $tenantCreatePos < $markProvisionedPos
        && $markProvisionedPos < $commitPos,
    'exactly-once write order is transaction -> lock -> provisioning -> tenant -> binding -> commit'
);

verify_trial_provisioning(
    str_contains(
        $source,
        "AND status = 'approved'"
    ),
    'first provisioning transition is conditional on approved status'
);

verify_trial_provisioning(
    str_contains(
        $source,
        'farm_id IS NULL'
    )
        && str_contains(
            $source,
            'farm_admin_user_id IS NULL'
        ),
    'final binding refuses to overwrite an existing tenant binding'
);

verify_trial_provisioning(
    str_contains(
        $source,
        "'provisioned'"
    )
        && str_contains(
            $source,
            "'activated'"
        )
        && str_contains(
            $source,
            "'already_provisioned'"
        ),
    'committed provisioned/activated requests use idempotent existing binding path'
);

verify_trial_provisioning(
    str_contains(
        $source,
        "if (\$status === 'provisioning')"
    )
        && str_contains(
            $source,
            'requires review'
        ),
    'unexpected persisted provisioning state fails closed'
);

verify_trial_provisioning(
    !preg_match(
        '/mail\s*\(|password_hash\s*\(|account_credential_issue_token|billing_payment_attempts/',
        $source
    ),
    'service does not own mail, password, token, or payment application'
);

require_once $service;

$modules = [
    'poultry',
];

$seatAddOns =
    subscription_seat_normalize_addons(
        []
    );

$roleLimits =
    subscription_plan_effective_role_limits(
        'starter',
        $modules,
        $seatAddOns
    );

$approvedRequest = [
    'id' => 900001,

    'request_reference' =>
        '0123456789abcdef0123456789abcdef',

    'status' =>
        'approved',

    'approval_mode' =>
        'auto',

    'farm_name' =>
        'Trial Provisioning QA',

    'requested_workspace_id' =>
        'trial-provisioning-qa',

    'admin_full_name' =>
        'Trial QA Admin',

    'admin_username' =>
        'trialqaadmin',

    'admin_email' =>
        'trialqa@example.com',

    'contact_name' =>
        'Trial QA Contact',

    'contact_email' =>
        'billingqa@example.com',

    'approved_plan_code' =>
        'starter',

    'approved_modules_snapshot' =>
        json_encode(
            $modules,
            JSON_THROW_ON_ERROR
        ),

    'approved_role_limits_snapshot' =>
        json_encode(
            $roleLimits,
            JSON_THROW_ON_ERROR
        ),

    'approved_trial_days' =>
        14,

    'approved_by_user_id' =>
        null,

    'farm_id' =>
        null,

    'farm_admin_user_id' =>
        null,
];

$contract = null;

try {
    $contract =
        trial_onboarding_provisioning_assert_approved_contract(
            $approvedRequest
        );

} catch (Throwable $e) {
    echo 'APPROVED_CONTRACT_ERROR='
        . $e->getMessage()
        . PHP_EOL;
}

verify_trial_provisioning(
    is_array($contract),
    'valid approved request normalizes to tenant contract'
);

if (is_array($contract)) {
    verify_trial_provisioning(
        $contract['name']
            === 'Trial Provisioning QA'
            && $contract['slug']
                === 'trial-provisioning-qa',
        'approved tenant identity survives normalization'
    );

    verify_trial_provisioning(
        $contract['admin_username']
            === 'trialqaadmin'
            && $contract['admin_email']
                === 'trialqa@example.com',
        'approved Farm Admin identity survives normalization'
    );

    verify_trial_provisioning(
        $contract['contact_email']
            === 'billingqa@example.com',
        'farm contact email remains separate from credential email'
    );

    verify_trial_provisioning(
        $contract['plan_code']
            === 'starter'
            && $contract['subscription_status']
                === 'trial',
        'approved request produces Starter trial tenant contract'
    );

    verify_trial_provisioning(
        $contract['subscription_starts_at']
            === null
            && $contract['trial_ends_at']
                === null
            && $contract['subscription_ends_at']
                === null,
        'provisioning does not start trial clock'
    );

    verify_trial_provisioning(
        $contract['approved_trial_days']
            === trial_onboarding_trial_days()
            && $contract['approved_trial_days']
                === 14,
        'approved duration matches canonical 14-day policy'
    );

    verify_trial_provisioning(
        $contract['approved_role_limits']
            === trial_onboarding_provisioning_normalize_role_limits(
                $roleLimits
            ),
        'approved role-limit snapshot matches current plan policy'
    );

    verify_trial_provisioning(
        $contract['recorded_by_user_id']
            === null,
        'auto approval may provision without a user approver identity'
    );
}

$contactFallback =
    $approvedRequest;

$contactFallback['contact_email'] = '';

$fallbackContract = null;

try {
    $fallbackContract =
        trial_onboarding_provisioning_assert_approved_contract(
            $contactFallback
        );
} catch (Throwable $e) {
}

verify_trial_provisioning(
    is_array($fallbackContract)
        && $fallbackContract['contact_email']
            === 'trialqa@example.com',
    'missing contact email safely falls back to Farm Admin email'
);

$badStatus =
    $approvedRequest;

$badStatus['status'] =
    'pending_review';

$badStatusRejected = false;

try {
    trial_onboarding_provisioning_assert_approved_contract(
        $badStatus
    );
} catch (RuntimeException $e) {
    $badStatusRejected = true;
}

verify_trial_provisioning(
    $badStatusRejected,
    'non-approved request cannot enter first-time provisioning'
);

$badDuration =
    $approvedRequest;

$badDuration['approved_trial_days'] =
    13;

$badDurationRejected = false;

try {
    trial_onboarding_provisioning_assert_approved_contract(
        $badDuration
    );
} catch (RuntimeException $e) {
    $badDurationRejected = true;
}

verify_trial_provisioning(
    $badDurationRejected,
    'non-canonical trial duration is rejected'
);

$missingWorkspace =
    $approvedRequest;

$missingWorkspace['requested_workspace_id'] =
    '';

$missingWorkspaceRejected = false;

try {
    trial_onboarding_provisioning_assert_approved_contract(
        $missingWorkspace
    );
} catch (RuntimeException $e) {
    $missingWorkspaceRejected = true;
}

verify_trial_provisioning(
    $missingWorkspaceRejected,
    'approved request without workspace identity is rejected'
);

$badModulesJson =
    $approvedRequest;

$badModulesJson['approved_modules_snapshot'] =
    '{bad json';

$badModulesJsonRejected = false;

try {
    trial_onboarding_provisioning_assert_approved_contract(
        $badModulesJson
    );
} catch (RuntimeException $e) {
    $badModulesJsonRejected = true;
}

verify_trial_provisioning(
    $badModulesJsonRejected,
    'malformed approved modules snapshot is rejected'
);

$missingModules =
    $approvedRequest;

$missingModules['approved_modules_snapshot'] =
    json_encode(
        [],
        JSON_THROW_ON_ERROR
    );

$missingModulesRejected = false;

try {
    trial_onboarding_provisioning_assert_approved_contract(
        $missingModules
    );
} catch (RuntimeException $e) {
    $missingModulesRejected = true;
}

verify_trial_provisioning(
    $missingModulesRejected,
    'approved request without service entitlement is rejected'
);

$driftedRoleLimits =
    $roleLimits;

$driftedRoleLimits['viewer'] =
    (int)$driftedRoleLimits['viewer'] + 1;

$policyDrift =
    $approvedRequest;

$policyDrift['approved_role_limits_snapshot'] =
    json_encode(
        $driftedRoleLimits,
        JSON_THROW_ON_ERROR
    );

$policyDriftRejected = false;

try {
    trial_onboarding_provisioning_assert_approved_contract(
        $policyDrift
    );
} catch (RuntimeException $e) {
    $policyDriftRejected =
        str_contains(
            $e->getMessage(),
            'Reapproval is required'
        );
}

verify_trial_provisioning(
    $policyDriftRejected,
    'plan-policy drift requires reapproval instead of silent entitlement change'
);

$incompleteRoleLimits =
    $roleLimits;

unset(
    $incompleteRoleLimits[
        'viewer'
    ]
);

$incompleteSnapshot =
    $approvedRequest;

$incompleteSnapshot['approved_role_limits_snapshot'] =
    json_encode(
        $incompleteRoleLimits,
        JSON_THROW_ON_ERROR
    );

$incompleteSnapshotRejected = false;

try {
    trial_onboarding_provisioning_assert_approved_contract(
        $incompleteSnapshot
    );
} catch (RuntimeException $e) {
    $incompleteSnapshotRejected = true;
}

verify_trial_provisioning(
    $incompleteSnapshotRejected,
    'incomplete approved role-limit snapshot is rejected'
);

$existingRequest = [
    'id' => 900002,

    'request_reference' =>
        'fedcba9876543210fedcba9876543210',

    'status' =>
        'provisioned',

    'farm_id' =>
        321,

    'farm_admin_user_id' =>
        654,
];

$existing =
    trial_onboarding_provisioning_existing_result(
        $existingRequest
    );

verify_trial_provisioning(
    $existing['request_id']
        === 900002
        && $existing['farm_id']
            === 321
        && $existing['farm_admin_user_id']
            === 654
        && $existing['already_provisioned']
            === true,
    'retry result returns existing tenant binding without provisioning'
);

$missingExistingBindingRejected = false;

try {
    trial_onboarding_provisioning_existing_result([
        'id' => 900003,
        'request_reference' =>
            'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'status' =>
            'provisioned',
        'farm_id' =>
            null,
        'farm_admin_user_id' =>
            null,
    ]);
} catch (RuntimeException $e) {
    $missingExistingBindingRejected = true;
}

verify_trial_provisioning(
    $missingExistingBindingRejected,
    'provisioned request without binding fails closed'
);

/*
 * Do NOT call:
 * trial_onboarding_provision_approved_request()
 *
 * That is the live database writer and belongs to later controlled QA.
 */

verify_trial_provisioning(
    !str_contains(
        file_get_contents(__FILE__),
        "trial_onboarding_provision_approved_request(\$pdo"
    ),
    'focused verifier does not invoke live provisioning writer'
);

echo $fail
    ? "T14_B3B_PROVISIONING_VERIFIER=FAIL\n"
    : "T14_B3B_PROVISIONING_VERIFIER=PASS\n";

exit($fail ? 1 : 0);
