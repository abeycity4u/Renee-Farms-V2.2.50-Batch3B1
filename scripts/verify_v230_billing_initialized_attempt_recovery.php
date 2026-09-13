<?php
/**
 * Focused static verifier for V2.3 initialized subscription-attempt recovery.
 *
 * Database-free, provider-free and mutation-free.
 */

$root = dirname(__DIR__);

$wrapperPath =
    $root
    . '/includes/billing_initialized_attempt_recovery.php';

$corePath =
    $root
    . '/includes/billing_initialized_attempt_recovery_core.php';

$checks = 0;
$failures = 0;

$check = static function (
    bool $ok,
    string $message
) use (&$checks, &$failures): void {
    $checks++;

    if ($ok) {
        echo "PASS: {$message}\n";
        return;
    }

    $failures++;
    echo "FAIL: {$message}\n";
};

$wrapper =
    is_file($wrapperPath)
        ? file_get_contents($wrapperPath)
        : '';

$core =
    is_file($corePath)
        ? file_get_contents($corePath)
        : '';

if ($wrapper === false) $wrapper = '';
if ($core === false) $core = '';

$check(
    $wrapper !== ''
    && $core !== '',
    'subscription wrapper and shared initialized-recovery core exist'
);

$check(
    preg_match(
        '/require_once\s+__DIR__\s*\.\s*[\'"]\/billing_initialized_attempt_recovery_core\.php[\'"]\s*;/',
        $wrapper
    ) === 1,
    'subscription recovery loads the shared initialized-recovery core'
);

$check(
    strpos(
        $wrapper,
        "purpose = 'subscription'"
    ) !== false
    && strpos(
        $wrapper,
        "commercial_disposition = 'eligible'"
    ) !== false
    && strpos(
        $wrapper,
        "status = 'initialized'"
    ) !== false,
    'candidate scope remains limited to eligible initialized subscription attempts'
);

$check(
    strpos(
        $wrapper,
        'billing_initialized_attempt_recovery_core('
    ) !== false
    && preg_match(
        '/billing_initialized_attempt_recovery_core\s*\([\s\S]*?[\'"]subscription[\'"]/',
        $wrapper
    ) === 1,
    'subscription recovery delegates initialized mechanics with subscription purpose'
);

$verifyPos = strpos(
    $core,
    'billing_provider_verify_payment('
);

$beginPos = strpos(
    $core,
    '$pdo->beginTransaction();'
);

$lockPos = strpos(
    $core,
    'billing_audit_attempt_by_id(',
    $beginPos === false ? 0 : $beginPos
);

$applyPos = strpos(
    $core,
    'billing_audit_apply_verification(',
    $lockPos === false
        ? 0
        : $lockPos
);

$settlePos = strpos(
    $core,
    '$settleVerified(',
    $applyPos === false
        ? 0
        : $applyPos
);

$commitPos = strpos(
    $core,
    '$pdo->commit();',
    $settlePos === false
        ? 0
        : $settlePos
);

$check(
    $verifyPos !== false
    && $beginPos !== false
    && $lockPos !== false
    && $applyPos !== false
    && $settlePos !== false
    && $commitPos !== false
    && $verifyPos < $beginPos
    && $beginPos < $lockPos
    && $lockPos < $applyPos
    && $applyPos < $settlePos
    && $settlePos < $commitPos,
    'shared core verifies outside the transaction then locks, audits, settles and commits in order'
);

$check(
    strpos(
        $core,
        'catch (Throwable $providerError)'
    ) !== false
    && strpos(
        $core,
        "'outcome' => 'initialized_blocked'"
    ) !== false,
    'provider/network ambiguity remains an explicit initialized blocking outcome'
);

$check(
    strpos(
        $core,
        "!== 'initialized'"
    ) !== false
    && strpos(
        $core,
        '$expectedPurpose'
    ) !== false
    && strpos(
        $core,
        'billing_payment_attempt_purpose('
    ) !== false,
    'shared core revalidates initialized state and expected payment purpose under lock'
);

$stalePos = strpos(
    $core,
    '$staleBlocked'
);

$applyAfterStalePos = strpos(
    $core,
    'billing_audit_apply_verification(',
    $stalePos === false
        ? 0
        : $stalePos
);

$check(
    $stalePos !== false
    && $applyAfterStalePos !== false
    && $stalePos < $applyAfterStalePos
    && strpos(
        $core,
        "'outcome' => 'initialized_blocked'",
        $stalePos
    ) !== false
    && strpos(
        $wrapper,
        "'stale_blocked' => true"
    ) !== false
    && strpos(
        $wrapper,
        "'stale_blocked' => false"
    ) !== false,
    'purpose-specific stale state blocks safely before provider fact application'
);

$check(
    strpos(
        $core,
        'billing_audit_mark_initialization_failed('
    ) === false,
    'shared recovery never converts provider ambiguity into initialization failure'
);

$check(
    strpos(
        $wrapper,
        'billing_commercial_attempt_disposition_state('
    ) !== false
    && strpos(
        $wrapper,
        "!== 'eligible'"
    ) !== false
    && strpos(
        $wrapper,
        'applied_subscription_record_id'
    ) !== false
    && strpos(
        $wrapper,
        "['paid_at']"
    ) !== false,
    'subscription callback preserves commercial eligibility and conflicting-paid-evidence guards'
);

$check(
    strpos(
        $wrapper,
        'billing_paid_attempt_dispatch('
    ) !== false
    && strpos(
        $wrapper,
        "'paid_applied'"
    ) !== false
    && strpos(
        $wrapper,
        "'pending_blocked'"
    ) !== false
    && strpos(
        $wrapper,
        "'refunded_settled'"
    ) !== false
    && strpos(
        $wrapper,
        "'superseded'"
    ) !== false,
    'subscription-specific settlement outcomes remain unchanged'
);

$check(
    strpos(
        $wrapper,
        'billing_commercial_attempt_mark_superseded_after_verified_terminal('
    ) !== false
    && strpos(
        $wrapper,
        "'initialized_recovery_terminal'"
    ) !== false,
    'verified failed/cancelled subscription recovery retains canonical supersession'
);

$check(
    strpos(
        $wrapper,
        'billing_provider_verify_payment('
    ) === false
    && strpos(
        $wrapper,
        'billing_audit_apply_verification('
    ) === false,
    'subscription wrapper no longer duplicates provider verification or audit application mechanics'
);

$unsafeAgePatterns = [
    'TIMESTAMPDIFF(',
    'DATE_SUB(',
    'INTERVAL ',
    'created_at <',
    'updated_at <',
];

$hasUnsafeAgeRule = false;

foreach ($unsafeAgePatterns as $needle) {
    if (strpos($wrapper, $needle) !== false
        || strpos($core, $needle) !== false) {
        $hasUnsafeAgeRule = true;
        break;
    }
}

$check(
    !$hasUnsafeAgeRule,
    'initialized recovery still has no timestamp-only expiry rule'
);

$protectedDirectDml =
    '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+'
    . '(?:farms|farm_modules|farm_role_limits|'
    . 'farm_subscription_seat_addons|subscriptions)\b/i';

$check(
    preg_match(
        $protectedDirectDml,
        $wrapper
    ) !== 1
    && preg_match(
        $protectedDirectDml,
        $core
    ) !== 1,
    'shared core and subscription wrapper perform no direct entitlement or subscription DML'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    echo "V2.3 INITIALIZED ATTEMPT RECOVERY: FAILED\n";
    exit(1);
}

echo "V2.3 INITIALIZED ATTEMPT RECOVERY: PASSED\n";
?>
