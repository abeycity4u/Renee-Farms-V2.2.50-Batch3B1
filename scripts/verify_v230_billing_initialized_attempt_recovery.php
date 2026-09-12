<?php
/**
 * Focused static verifier for V2.3 initialized subscription-attempt recovery.
 *
 * Database-free, provider-free and mutation-free.
 *
 * Expected recovery contract:
 * - subscription-purpose initialized attempts only;
 * - commercially eligible attempts only;
 * - provider verification outside DB transactions;
 * - verified provider facts revalidated under row lock;
 * - paid dispatch remains centralized;
 * - pending remains blocking;
 * - verified failed/cancelled may settle safely;
 * - refunded settles without commercial application;
 * - provider/network/unknown failures never become terminal facts;
 * - no timestamp-only expiry or guessed provider state.
 */

$root = dirname(__DIR__);

$path =
    $root
    . '/includes/billing_initialized_attempt_recovery.php';

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

$check(
    is_file($path),
    'initialized subscription-attempt recovery helper exists'
);

$source = is_file($path)
    ? file_get_contents($path)
    : '';

if ($source === false) {
    $source = '';
}

$check(
    strpos(
        $source,
        'billing_provider_verify_payment('
    ) !== false,
    'recovery verifies the persisted provider reference'
);

$verifyPos = strpos(
    $source,
    'billing_provider_verify_payment('
);

$beginPos = strpos(
    $source,
    '$pdo->beginTransaction();'
);

$check(
    $verifyPos !== false
    && $beginPos !== false
    && $verifyPos < $beginPos,
    'provider verification occurs before recovery transaction begins'
);

$check(
    strpos(
        $source,
        "purpose = 'subscription'"
    ) !== false
    && strpos(
        $source,
        "commercial_disposition = 'eligible'"
    ) !== false
    && strpos(
        $source,
        "status = 'initialized'"
    ) !== false,
    'candidate scope is limited to eligible initialized subscription attempts'
);

$check(
    strpos(
        $source,
        'billing_audit_apply_verification('
    ) !== false,
    'authoritative provider fact enters the canonical payment audit layer'
);

$check(
    strpos(
        $source,
        'billing_paid_attempt_dispatch('
    ) !== false,
    'verified paid recovery uses centralized exactly-once paid dispatch'
);

$check(
    strpos(
        $source,
        "'pending_blocked'"
    ) !== false,
    'verified pending attempt remains blocking'
);

$check(
    strpos(
        $source,
        "'paid_applied'"
    ) !== false,
    'verified paid attempt stops replacement checkout after application'
);

$check(
    strpos(
        $source,
        "'refunded_settled'"
    ) !== false,
    'verified refund settles without replacement-payment ambiguity'
);

$check(
    strpos(
        $source,
        "'superseded'"
    ) !== false,
    'verified terminal failed/cancelled state can be commercially superseded'
);

$check(
    strpos(
        $source,
        "'initialized_blocked'"
    ) !== false,
    'ambiguous initialized state has an explicit blocking outcome'
);


$lockPos = strpos(
    $source,
    'billing_audit_attempt_by_id('
);

$applyPos = strpos(
    $source,
    'billing_audit_apply_verification('
);

$check(
    $verifyPos !== false
    && $beginPos !== false
    && $lockPos !== false
    && $applyPos !== false
    && $verifyPos < $beginPos
    && $beginPos < $lockPos
    && $lockPos < $applyPos,
    'recovery re-locks durable attempt after provider verification and before applying provider fact'
);

$check(
    strpos(
        $source,
        "!== 'eligible'"
    ) !== false
    && strpos(
        $source,
        "!== 'initialized'"
    ) !== false
    && strpos(
        $source,
        'changed identity during provider verification'
    ) !== false,
    'recovery revalidates tenant identity, disposition and initialized state after provider I/O'
);

$providerCatchPos = strpos(
    $source,
    'catch (Throwable $providerError)'
);

$initializedBlockedPos = strpos(
    $source,
    "'outcome' => 'initialized_blocked'",
    $providerCatchPos === false
        ? 0
        : $providerCatchPos
);

$check(
    $providerCatchPos !== false
    && $initializedBlockedPos !== false
    && $providerCatchPos < $initializedBlockedPos,
    'provider exception remains an explicit blocking initialized outcome'
);

$check(
    strpos(
        $source,
        'billing_audit_mark_initialization_failed('
    ) === false,
    'recovery never converts provider ambiguity into initialization failure'
);

$check(
    strpos(
        $source,
        'billing_commercial_attempt_mark_superseded_after_verified_terminal('
    ) !== false
    && strpos(
        $source,
        "'initialized_recovery_terminal'"
    ) !== false,
    'verified failed or cancelled recovery binds terminal supersession to the recovery reason'
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
    if (strpos($source, $needle) !== false) {
        $hasUnsafeAgeRule = true;
        break;
    }
}

$check(
    !$hasUnsafeAgeRule,
    'recovery does not expire initialized attempts solely by age'
);

$protectedDirectDml =
    '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+'
    . '(?:farms|farm_modules|farm_role_limits|'
    . 'farm_subscription_seat_addons|subscriptions)\b/i';

$check(
    preg_match(
        $protectedDirectDml,
        $source
    ) !== 1,
    'recovery performs no direct entitlement or subscription DML'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    echo "V2.3 INITIALIZED ATTEMPT RECOVERY: FAILED\n";
    exit(1);
}

echo "V2.3 INITIALIZED ATTEMPT RECOVERY: PASSED\n";
?>
