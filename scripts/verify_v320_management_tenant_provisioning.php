<?php

$root = dirname(__DIR__);

$page =
    $root
    . '/management/farms.php';

$service =
    $root
    . '/includes/tenant_provisioning.php';

$fail = false;

function verify_management_provisioning(
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

verify_management_provisioning(
    is_file($page),
    'management farms page exists'
);

verify_management_provisioning(
    is_file($service),
    'shared tenant provisioner exists'
);

$pageSource =
    is_file($page)
        ? (string)file_get_contents($page)
        : '';

verify_management_provisioning(
    str_contains(
        $pageSource,
        "/includes/tenant_provisioning.php"
    ),
    'management page loads shared tenant provisioner'
);

verify_management_provisioning(
    substr_count(
        $pageSource,
        'tenant_provisioning_create('
    ) === 1,
    'management create path calls shared provisioner exactly once'
);

verify_management_provisioning(
    !str_contains(
        $pageSource,
        'INSERT INTO farms (name, slug, primary_color'
    ),
    'page-local direct tenant create SQL is retired'
);

verify_management_provisioning(
    !str_contains(
        $pageSource,
        'function ensureTenantRoles'
    ),
    'page-local Farm Admin role bootstrap is retired'
);

verify_management_provisioning(
    !str_contains(
        $pageSource,
        'function saveRoleLimits'
    ),
    'page-local role-limit writer is retired'
);

verify_management_provisioning(
    str_contains(
        $pageSource,
        'tenant_provisioning_ensure_farm_admin_role('
    ),
    'update path uses shared Farm Admin role bootstrap'
);

verify_management_provisioning(
    str_contains(
        $pageSource,
        'tenant_provisioning_save_role_limits('
    ),
    'update path uses shared role-limit persistence'
);

verify_management_provisioning(
    str_contains(
        $pageSource,
        "isset(\$_POST['create_farm'])"
    )
        && str_contains(
            $pageSource,
            "isset(\$_POST['update_farm'])"
        ),
    'create and update route branches remain present'
);

verify_management_provisioning(
    str_contains(
        $pageSource,
        "isset(\$_POST['delete_farm'], \$_POST['farm_id'])"
    ),
    'delete-farm route remains present'
);

verify_management_provisioning(
    str_contains(
        $pageSource,
        "isset(\$_POST['resend_activation'], \$_POST['farm_id'])"
    ),
    'Farm Admin activation resend route remains present'
);

verify_management_provisioning(
    str_contains(
        $pageSource,
        "isset(\$_POST['suspend_farm'], \$_POST['farm_id'])"
    )
        && str_contains(
            $pageSource,
            "isset(\$_POST['reactivate_farm'], \$_POST['farm_id'])"
        ),
    'suspend/reactivate routes remain present'
);

verify_management_provisioning(
    str_contains(
        $pageSource,
        'farm_profile_save_logo_upload('
    ),
    'management route still owns logo-file handling'
);

verify_management_provisioning(
    str_contains(
        $pageSource,
        '$pdo->beginTransaction();'
    )
        && str_contains(
            $pageSource,
            '$pdo->commit();'
        )
        && str_contains(
            $pageSource,
            '$pdo->rollBack();'
        ),
    'management route still owns outer transactions'
);

verify_management_provisioning(
    str_contains(
        $pageSource,
        'account_pending_user_update_email('
    ),
    'existing pending Farm Admin email repair remains present'
);

verify_management_provisioning(
    str_contains(
        $pageSource,
        "=== 'pending_activation'"
    )
        || str_contains(
            $pageSource,
            "!== 'pending_activation'"
        ),
    'pending Farm Admin credential-state handling remains present'
);

verify_management_provisioning(
    str_contains(
        $pageSource,
        'subscription_seat_assert_capacity('
    ),
    'existing update seat-capacity guard remains present'
);

verify_management_provisioning(
    str_contains(
        $pageSource,
        'subscription_record_capture('
    ),
    'non-create management subscription-history paths remain present'
);

verify_management_provisioning(
    !str_contains(
        $pageSource,
        'trial_onboarding_requests'
    ),
    'management farm route remains decoupled from public trial request storage'
);

verify_management_provisioning(
    !str_contains(
        $pageSource,
        'farm_onboarding_mail'
    ),
    'legacy plaintext onboarding mail remains retired'
);

echo $fail
    ? "T14_B2D_MANAGEMENT_INTEGRATION=FAIL\n"
    : "T14_B2D_MANAGEMENT_INTEGRATION=PASS\n";

exit($fail ? 1 : 0);
