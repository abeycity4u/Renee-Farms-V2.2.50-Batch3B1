<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$farmsPath =
    $root
    . '/management/farms.php';

$pendingPath =
    $root
    . '/includes/account_pending_user.php';

$failures = 0;

function verify_v310_farm_admin_writer(
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

verify_v310_farm_admin_writer(
    is_file($farmsPath),
    'farm management writer exists'
);

verify_v310_farm_admin_writer(
    is_file($pendingPath),
    'shared pending-account service exists'
);

if (
    !is_file($farmsPath)
    || !is_file($pendingPath)
) {
    echo "V310_FARM_ADMIN_PENDING_WRITER=FAIL\n";
    exit(1);
}

$farms =
    (string)file_get_contents(
        $farmsPath
    );

$pending =
    (string)file_get_contents(
        $pendingPath
    );

verify_v310_farm_admin_writer(
    str_contains(
        $farms,
        "require_once dirname(__DIR__) . '/includes/account_pending_user.php';"
    ),
    'farm writer loads shared pending-account authority'
);

verify_v310_farm_admin_writer(
    !str_contains(
        $farms,
        "require_once dirname(__DIR__) . '/includes/farm_onboarding_mail.php';"
    ),
    'farm writer retires legacy onboarding dependency'
);

verify_v310_farm_admin_writer(
    substr_count(
        $farms,
        'account_pending_user_create('
    ) === 2,
    'new and repaired Farm Admin paths use pending-account creation'
);

verify_v310_farm_admin_writer(
    preg_match_all(
        '/INSERT\s+INTO\s+users\b/i',
        $farms
    ) === 0,
    'farm writer has no direct user insert'
);

verify_v310_farm_admin_writer(
    substr_count(
        $farms,
        'password_security_hash($password)'
    ) === 1,
    'submitted password hashing remains only for existing-admin update'
);

verify_v310_farm_admin_writer(
    !str_contains(
        $farms,
        "isset(\$_POST['create_farm']) && (\$passwordError = password_security_validate(\$password))"
    ),
    'new Farm Admin creation no longer validates an initial password'
);

verify_v310_farm_admin_writer(
    !str_contains(
        $farms,
        "\$repairOwnerNeeded && (\$passwordError = password_security_validate(\$password))"
    ),
    'missing-admin repair no longer validates an initial password'
);

verify_v310_farm_admin_writer(
    str_contains(
        $farms,
        "isset(\$_POST['update_farm']) && !\$repairOwnerNeeded && \$password !== ''"
    ),
    'existing Farm Admin retains optional password policy validation'
);

verify_v310_farm_admin_writer(
    str_contains(
        $farms,
        '$allowExistingPasswordUpdate = !$repairOwnerNeeded && $password !== \'\';'
    ),
    'repair path cannot use submitted password to replace pending placeholder'
);

verify_v310_farm_admin_writer(
    str_contains(
        $farms,
        "(isset(\$_POST['create_farm']) || \$repairOwnerNeeded) && \$rawOwnerEmail === ''"
    ),
    'new or repaired Farm Admin requires the explicit credential email field server-side'
);

verify_v310_farm_admin_writer(
    str_contains(
        $farms,
        "<?php echo !\$editFarm || \$ownerNeedsRepair ? 'required' : ''; ?>"
    ),
    'new or repaired Farm Admin email is required in the form'
);

verify_v310_farm_admin_writer(
    str_contains(
        $farms,
        'if ($editFarm && !$ownerNeedsRepair)'
    ),
    'password field is shown only for an existing Farm Admin'
);

verify_v310_farm_admin_writer(
    !str_contains(
        $farms,
        'farm_onboarding_send_credentials('
    ),
    'farm writer performs no synchronous credential mail'
);

verify_v310_farm_admin_writer(
    !str_contains(
        $farms,
        '$onboardingPayload'
    ),
    'farm writer builds no plaintext credential payload'
);

verify_v310_farm_admin_writer(
    str_contains(
        $farms,
        '$activationQueuedFor'
    )
    && str_contains(
        $farms,
        'Farm Admin activation instructions were queued for delivery to '
    ),
    'success copy reports activation as queued rather than sent'
);

verify_v310_farm_admin_writer(
    str_contains(
        $farms,
        'Used for billing and commercial contact.'
    ),
    'billing contact remains explicitly commercial'
);

verify_v310_farm_admin_writer(
    str_contains(
        $farms,
        'Used for account activation and password recovery.'
    ),
    'Farm Admin email is explicitly the credential-delivery address'
);

$writerAnchor =
    strpos(
        $farms,
        '$createdFarmId = 0;'
    );

$begin =
    $writerAnchor === false
        ? false
        : strpos(
            $farms,
            '$pdo->beginTransaction();',
            $writerAnchor
        );

$firstPending =
    $begin === false
        ? false
        : strpos(
            $farms,
            'account_pending_user_create(',
            $begin
        );

$secondPending =
    $firstPending === false
        ? false
        : strpos(
            $farms,
            'account_pending_user_create(',
            $firstPending + 1
        );

$commit =
    $begin === false
        ? false
        : strpos(
            $farms,
            '$pdo->commit();',
            $begin
        );

verify_v310_farm_admin_writer(
    $begin !== false
    && $firstPending !== false
    && $secondPending !== false
    && $commit !== false
    && $begin < $firstPending
    && $firstPending < $secondPending
    && $secondPending < $commit,
    'both pending-account writes remain inside caller-owned farm transaction'
);

verify_v310_farm_admin_writer(
    str_contains(
        $pending,
        'account_pending_user_assert_transaction('
    )
    && !str_contains(
        $pending,
        'beginTransaction('
    )
    && !str_contains(
        $pending,
        'commit('
    )
    && !str_contains(
        $pending,
        'rollBack('
    ),
    'shared pending-account service remains caller-transaction owned'
);

verify_v310_farm_admin_writer(
    str_contains(
        $pending,
        'account_credential_outbox_enqueue('
    ),
    'shared pending-account creation durably queues activation delivery'
);

verify_v310_farm_admin_writer(
    !str_contains(
        $farms,
        'platform_mail_send('
    )
    && !str_contains(
        $farms,
        'account_credential_send('
    )
    && !str_contains(
        $farms,
        'account_credential_issue_token('
    ),
    'farm writer performs no direct mail or raw-token operation'
);

if ($failures > 0) {
    echo "V310_FARM_ADMIN_PENDING_WRITER=FAIL\n";
    exit(1);
}

echo "V310_FARM_ADMIN_PENDING_WRITER=PASS\n";
