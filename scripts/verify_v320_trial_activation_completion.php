<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__)
    . '/includes/account_activation_completion.php';

$sourcePath =
    dirname(__DIR__)
    . '/includes/account_activation_completion.php';

$routePath =
    dirname(__DIR__)
    . '/account/activate.php';

$credentialPath =
    dirname(__DIR__)
    . '/includes/account_credential_lifecycle.php';

$requestPath =
    dirname(__DIR__)
    . '/includes/trial_onboarding_request.php';

$source =
    file_get_contents($sourcePath);

$route =
    file_get_contents($routePath);

$credentialSource =
    file_get_contents($credentialPath);

$requestSource =
    file_get_contents($requestPath);

$failed = false;

function verify_check(
    bool $condition,
    string $message
): void {
    global $failed;

    echo ($condition ? 'PASS: ' : 'FAIL: ')
        . $message
        . PHP_EOL;

    if (!$condition) {
        $failed = true;
    }
}

verify_check(
    is_string($source)
        && $source !== '',
    'shared activation-completion authority exists'
);

verify_check(
    str_contains(
        $source,
        "require_once __DIR__\n"
        . "    . '/account_credential_lifecycle.php';"
    ),
    'authority composes existing credential lifecycle'
);

verify_check(
    str_contains(
        $source,
        "require_once __DIR__\n"
        . "    . '/trial_onboarding_request.php';"
    ),
    'authority composes trial request lifecycle'
);

verify_check(
    str_contains(
        $source,
        "require_once __DIR__\n"
        . "    . '/subscription_record.php';"
    ),
    'authority composes subscription history authority'
);

verify_check(
    substr_count(
        $source,
        'account_credential_consume_activation('
    ) === 1,
    'generic credential activation is called exactly once'
);

verify_check(
    substr_count(
        $source,
        'subscription_record_capture('
    ) === 1,
    'subscription history capture is called exactly once'
);

verify_check(
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
    'authority owns atomic transaction boundary'
);

$begin =
    strpos(
        $source,
        '$pdo->beginTransaction();'
    );

$credential =
    strpos(
        $source,
        'account_credential_consume_activation(',
        $begin !== false ? $begin : 0
    );

$requestLock =
    strpos(
        $source,
        'account_activation_completion_lock_request(',
        $credential !== false ? $credential : 0
    );

$startTrial =
    strpos(
        $source,
        '$startTrial->execute(',
        $requestLock !== false
            ? $requestLock
            : 0
    );

$markActivated =
    strpos(
        $source,
        '$markActivated->execute(',
        $startTrial !== false
            ? $startTrial
            : 0
    );

$history =
    strpos(
        $source,
        'subscription_record_capture(',
        $markActivated !== false
            ? $markActivated
            : 0
    );

$finalCommit =
    strrpos(
        $source,
        '$pdo->commit();'
    );

verify_check(
    $begin !== false
        && $credential !== false
        && $requestLock !== false
        && $startTrial !== false
        && $markActivated !== false
        && $history !== false
        && $finalCommit !== false
        && $begin < $credential
        && $credential < $requestLock
        && $requestLock < $startTrial
        && $startTrial < $markActivated
        && $markActivated < $history
        && $history < $finalCommit,
    'trial activation ordering is transaction -> credential -> request -> clock -> request activated -> history -> commit'
);

verify_check(
    str_contains(
        $source,
        "if (\$request === null)"
    )
        && str_contains(
            $source,
            "'trial_started' =>\n"
            . "                        false"
        ),
    'ordinary non-trial activation remains supported'
);

verify_check(
    str_contains(
        $source,
        "\$requestStatus !== 'provisioned'"
    )
        && str_contains(
            $source,
            "'provisioned',\n"
            . "                'activated'"
        ),
    'trial request must be provisioned before activation'
);

verify_check(
    str_contains(
        $source,
        'trial_onboarding_trial_days()'
    )
        && str_contains(
            $source,
            '$approvedTrialDays'
        )
        && str_contains(
            $source,
            '!== $canonicalTrialDays'
        ),
    'approved duration must match canonical trial policy'
);

verify_check(
    str_contains(
        $source,
        "\$farmAdminUserId !== \$userId"
    )
        && str_contains(
            $source,
            ") !== 'farm_admin'"
        )
        && str_contains(
            $source,
            ") !== 'active'"
        ),
    'activated account must match bound active Farm Admin'
);

verify_check(
    str_contains(
        $source,
        "subscription_status'\n"
        . "                    ] ?? ''\n"
        . "                ) !== 'trial'"
    ),
    'tenant must still be in trial commercial state'
);

verify_check(
    str_contains(
        $source,
        "subscription_starts_at'\n"
        . "                ] !== null"
    )
        && str_contains(
            $source,
            "trial_ends_at'\n"
            . "                ] !== null"
        )
        && str_contains(
            $source,
            "subscription_ends_at'\n"
            . "                ] !== null"
        ),
    'pre-activation trial clocks must all still be null'
);

verify_check(
    str_contains(
        $source,
        'subscription_starts_at = ?'
    )
        && str_contains(
            $source,
            'trial_ends_at = ?'
        )
        && str_contains(
            $source,
            'subscription_ends_at = ?'
        ),
    'activation starts all three tenant clock fields'
);

verify_check(
    str_contains(
        $source,
        '$activatedAt,'
    )
        && substr_count(
            $source,
            '$trialEndsAt,'
        ) >= 2,
    'one activation timestamp and one derived trial end drive tenant clock'
);

verify_check(
    str_contains(
        $source,
        "AND subscription_starts_at IS NULL"
    )
        && str_contains(
            $source,
            "AND trial_ends_at IS NULL"
        )
        && str_contains(
            $source,
            "AND subscription_ends_at IS NULL"
        ),
    'trial clock write refuses overwrite'
);

verify_check(
    str_contains(
        $source,
        "status = 'activated'"
    )
        && str_contains(
            $source,
            "AND status = 'provisioned'"
        )
        && str_contains(
            $source,
            "AND activated_at IS NULL"
        ),
    'request activation write is exactly-once guarded'
);

verify_check(
    str_contains(
        $source,
        "'trial_onboarding_activated'"
    ),
    'activation history uses dedicated change reason'
);

verify_check(
    !preg_match(
        '/password_hash\s*\(|random_bytes\s*\(|'
        . 'INSERT\s+INTO\s+account_credential_tokens|'
        . '\bmail\s*\(|billing_payment_attempts/i',
        $source
    ),
    'authority does not duplicate credential, mail, or payment responsibilities'
);

/*
 * Exact 14-day boundary helper test.
 * Read-only: SELECT DATE_ADD only.
 */
$knownStart =
    '2026-09-27 23:00:00';

$knownEnd =
    account_activation_completion_trial_end(
        $pdo,
        $knownStart,
        trial_onboarding_trial_days()
    );

verify_check(
    $knownEnd
        === '2026-10-11 23:00:00',
    'canonical trial end is exactly 14 days after activation timestamp'
);

$invalidDurationRejected = false;

try {
    account_activation_completion_trial_end(
        $pdo,
        $knownStart,
        0
    );
} catch (InvalidArgumentException $e) {
    $invalidDurationRejected = true;
}

verify_check(
    $invalidDurationRejected,
    'invalid trial duration is rejected'
);

/*
 * Do not invoke account_activation_complete() here:
 * that is the live mutation writer.
 */
verify_check(
    str_contains(
        $route,
        '/includes/account_activation_completion.php'
    )
        && str_contains(
            $route,
            'account_activation_complete('
        )
        && !str_contains(
            $route,
            'account_credential_consume_activation('
        ),
    'public activation route delegates POST completion to shared authority'
);

verify_check(
    str_contains(
        $credentialSource,
        '$startedTransaction = !$pdo->inTransaction();'
    ),
    'credential lifecycle participates in caller-owned transaction'
);

verify_check(
    str_contains(
        $requestSource,
        "'provisioned' => [\n"
        . "                'activated',"
    ),
    'shared request lifecycle authorizes provisioned to activated'
);

verify_check(
    str_contains(
        $requestSource,
        'function trial_onboarding_trial_days()'
    )
        && str_contains(
            $requestSource,
            'return 14;'
        ),
    'shared request lifecycle owns canonical 14-day policy'
);

echo $failed
    ? "T14_B4B2_ACTIVATION_COMPLETION_VERIFIER=FAIL\n"
    : "T14_B4B2_ACTIVATION_COMPLETION_VERIFIER=PASS\n";

exit($failed ? 1 : 0);
