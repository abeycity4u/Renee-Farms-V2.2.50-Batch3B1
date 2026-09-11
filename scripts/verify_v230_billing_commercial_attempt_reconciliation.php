<?php
/**
 * Focused static verifier for V2.3 commercial subscription-attempt
 * reconciliation.
 *
 * Database-free, provider-free and mutation-free.
 */

$root = dirname(__DIR__);

$path =
    $root
    . '/includes/billing_commercial_attempt_reconciliation.php';

if (!is_file($path)) {
    fwrite(
        STDERR,
        "FAIL: missing commercial attempt reconciliation helper.\n"
    );
    exit(1);
}

$source = file_get_contents($path);

if ($source === false) {
    fwrite(
        STDERR,
        "FAIL: unable to read commercial attempt reconciliation helper.\n"
    );
    exit(1);
}

$compact = preg_replace('/\s+/', '', $source);

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
    strpos(
        $source,
        "billing_payment_foundation.php"
    ) !== false
    && strpos(
        $source,
        "billing_payment_audit_state.php"
    ) !== false
    && strpos(
        $source,
        "billing_commercial_attempt_disposition.php"
    ) !== false
    && strpos(
        $source,
        "billing_paid_attempt_dispatcher.php"
    ) !== false,
    'reconciliation reuses canonical payment, audit, disposition and paid-dispatch foundations'
);

$check(
    strpos(
        $source,
        'function billing_commercial_attempt_reconcile_verified_fact('
    ) !== false,
    'one centralized verified-fact reconciliation service exists'
);

$check(
    strpos(
        $source,
        'if (!$pdo->inTransaction())'
    ) !== false
    && strpos(
        $source,
        'requires an active caller transaction'
    ) !== false
    && strpos(
        $source,
        '$pdo->beginTransaction()'
    ) === false
    && strpos(
        $source,
        '$pdo->commit()'
    ) === false,
    'reconciliation preserves caller-owned transaction scope'
);

$attemptLockPos = strpos(
    $source,
    'billing_audit_attempt_by_id('
);

$auditApplyPos = strpos(
    $source,
    'billing_audit_apply_verification('
);

$check(
    $attemptLockPos !== false
    && $auditApplyPos !== false
    && $attemptLockPos < $auditApplyPos,
    'reconciliation locks the authoritative attempt before applying the fresh provider fact'
);

$check(
    strpos(
        $source,
        "(int)(\$attempt['farm_id'] ?? 0) !== \$farmId"
    ) !== false
    && strpos(
        $source,
        "billing_payment_attempt_purpose("
    ) !== false
    && strpos(
        $source,
        "'subscription'"
    ) !== false,
    'reconciliation is tenant-bound and subscription-purpose only'
);

$check(
    strpos(
        $source,
        "['failed', 'cancelled']"
    ) !== false
    && strpos(
        $source,
        "!== 'eligible'"
    ) !== false,
    'only commercially eligible failed or cancelled attempts enter replacement reconciliation'
);

$check(
    strpos(
        $source,
        'applied_subscription_record_id'
    ) !== false
    && strpos(
        $source,
        'existing paid evidence'
    ) !== false,
    'already-applied or already-paid attempts fail closed before reconciliation'
);

$check(
    strpos(
        $source,
        "(\$verification['verified'] ?? null) !== true"
    ) !== false
    && preg_match(
        "/\\['pending','paid','failed','cancelled','refunded',?\\]/",
        $compact
    ) === 1,
    'reconciliation accepts only a server-verified normalized provider payment fact'
);

$check(
    strpos(
        $source,
        'billing_audit_apply_verification('
    ) !== false
    && strpos(
        $source,
        'Reconciled payment attempt identity changed unexpectedly.'
    ) !== false
    && strpos(
        $source,
        'Reconciled payment attempt purpose changed unexpectedly.'
    ) !== false,
    'canonical audit transition is followed by identity and purpose revalidation'
);

$terminalBranchPos = strpos(
    $source,
    "['failed', 'cancelled']",
    $auditApplyPos === false
        ? 0
        : $auditApplyPos
);

$supersedePos = strpos(
    $source,
    'billing_commercial_attempt_mark_superseded_after_verified_terminal('
);

$check(
    $terminalBranchPos !== false
    && $supersedePos !== false
    && $terminalBranchPos < $supersedePos
    && strpos(
        $source,
        "\$updated['verified_at']"
    ) !== false
    && strpos(
        $source,
        "'outcome' => 'superseded'"
    ) !== false,
    'fresh verified failed/cancelled fact is bound to its exact audit timestamp before supersession'
);

$paidBranchPos = strpos(
    $source,
    "\$status === 'paid'"
);

$dispatchPos = strpos(
    $source,
    'billing_paid_attempt_dispatch('
);

$check(
    $paidBranchPos !== false
    && $dispatchPos !== false
    && $paidBranchPos < $dispatchPos
    && strpos(
        $source,
        "'outcome' => 'paid_applied'"
    ) !== false
    && strpos(
        $source,
        'audit_only'
    ) !== false,
    'fresh verified paid fact uses central paid dispatch and rejects unexpected audit-only handling'
);

$finalReloadPos = strpos(
    $source,
    '$finalAttempt =',
    $dispatchPos === false
        ? 0
        : $dispatchPos
);

$check(
    $dispatchPos !== false
    && $finalReloadPos !== false
    && $dispatchPos < $finalReloadPos
    && strpos(
        $source,
        "'applied_subscription_record_id'"
    ) !== false
    && strpos(
        $source,
        'did not persist its final attempt linkage'
    ) !== false
    && strpos(
        $source,
        "'attempt' => $finalAttempt"
    ) !== false,
    'paid reconciliation reloads and returns the final persisted attempt after subscription application'
);

$check(
    strpos(
        $source,
        "\$status === 'refunded'"
    ) !== false
    && strpos(
        $source,
        "'outcome' => 'refunded_settled'"
    ) !== false
    && strpos(
        $source,
        "'blocking' => false"
    ) !== false,
    'verified refunded fact settles without commercial application'
);

$check(
    strpos(
        $source,
        "\$status === 'pending'"
    ) !== false
    && strpos(
        $source,
        "'outcome' => 'pending_blocked'"
    ) !== false
    && strpos(
        $source,
        "'blocking' => true"
    ) !== false,
    'verified pending fact remains explicitly blocking'
);

$protectedDml =
    '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+'
    . '(?:farms|farm_modules|farm_role_limits|'
    . 'farm_subscription_seat_addons|subscriptions|'
    . 'billing_seat_change_requests)\b/i';

$check(
    preg_match(
        $protectedDml,
        $source
    ) !== 1,
    'reconciliation helper contains no direct entitlement or subscription-state DML'
);

$check(
    strpos(
        $source,
        'billing_provider_verify_payment('
    ) === false
    && strpos(
        $source,
        'billing_provider_initialize_checkout('
    ) === false
    && strpos(
        $source,
        'curl_'
    ) === false,
    'reconciliation helper performs no provider or network work'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    echo "V2.3 COMMERCIAL ATTEMPT RECONCILIATION FOUNDATION: FAILED\n";
    exit(1);
}

echo "V2.3 COMMERCIAL ATTEMPT RECONCILIATION FOUNDATION: PASSED\n";
?>
