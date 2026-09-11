<?php
/**
 * Focused static verifier for V2.3 tenant-scoped seat-top-up reconciliation.
 *
 * Database-free, provider-free and mutation-free.
 */

$root = dirname(__DIR__);

$path =
    $root
    . '/includes/billing_seat_topup_reconciliation_launcher.php';

if (!is_file($path)) {
    fwrite(
        STDERR,
        "FAIL: missing seat-top-up reconciliation launcher.\n"
    );
    exit(1);
}

$source = file_get_contents($path);

if ($source === false) {
    fwrite(
        STDERR,
        "FAIL: unable to read seat-top-up reconciliation launcher.\n"
    );
    exit(1);
}

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
        'billing_provider_contract.php'
    ) !== false
    && strpos(
        $source,
        'billing_provider_adapters.php'
    ) !== false
    && strpos(
        $source,
        'billing_payment_audit_state.php'
    ) !== false
    && strpos(
        $source,
        'billing_seat_change_request.php'
    ) !== false
    && strpos(
        $source,
        'billing_paid_attempt_dispatcher.php'
    ) !== false,
    'launcher reuses canonical provider, audit, seat-change and paid-dispatch foundations'
);

$check(
    strpos(
        $source,
        'function billing_seat_topup_reconciliation_candidate('
    ) !== false
    && strpos(
        $source,
        'function billing_seat_topup_reconcile_next_for_replacement('
    ) !== false
    && strpos(
        $source,
        'function billing_seat_topup_reconcile_open_candidates_for_replacement('
    ) !== false,
    'candidate, one-attempt reconciliation and bounded orchestration are centralized'
);

$check(
    strpos(
        $source,
        'complete target seat-add-on'
    ) !== false
    && strpos(
        $source,
        'serialized per tenant'
    ) !== false,
    'cross-role top-ups are deliberately serialized because the frozen target snapshot is tenant-wide'
);

$selectStart = strpos(
    $source,
    'SELECT'
);

$selectEnd = strpos(
    $source,
    'LIMIT 1',
    $selectStart === false
        ? 0
        : $selectStart
);

$selectSql = '';

if ($selectStart !== false
    && $selectEnd !== false) {
    $selectSql = substr(
        $source,
        $selectStart,
        ($selectEnd - $selectStart)
            + strlen('LIMIT 1')
    );
}

$check(
    $selectSql !== ''
    && strpos(
        $selectSql,
        "a.purpose = 'seat_topup'"
    ) !== false
    && strpos(
        $selectSql,
        'r.farm_id = a.farm_id'
    ) !== false
    && strpos(
        $selectSql,
        "r.change_kind = 'add'"
    ) !== false
    && strpos(
        $selectSql,
        "r.status = 'awaiting_payment'"
    ) !== false
    && strpos(
        $selectSql,
        'ORDER BY a.id ASC'
    ) !== false,
    'candidate selection is tenant, purpose and open durable-add-request scoped'
);

$check(
    $selectSql !== ''
    && strpos(
        $selectSql,
        'r.role_code = ?'
    ) === false,
    'candidate lookup is intentionally not limited to the newly requested role'
);

$check(
    $selectSql !== ''
    && preg_match(
        '/\bFOR\s+UPDATE\b/i',
        $selectSql
    ) !== 1,
    'candidate lookup deliberately takes no row lock before provider work'
);

$check(
    strpos(
        $selectSql,
        "'initialized'"
    ) !== false
    && strpos(
        $selectSql,
        "'pending'"
    ) !== false
    && strpos(
        $selectSql,
        "'paid'"
    ) !== false
    && strpos(
        $selectSql,
        "'failed'"
    ) !== false
    && strpos(
        $selectSql,
        "'cancelled'"
    ) !== false
    && strpos(
        $selectSql,
        "'refunded'"
    ) !== false,
    'candidate scope covers every unresolved payment state relevant to replacement safety'
);

$check(
    strpos(
        $source,
        'billing_seat_change_normalize_role('
    ) !== false
    && strpos(
        $source,
        "'role_code' => \$roleCode"
    ) !== false,
    'candidate role identity is derived from the durable request rather than browser input'
);

$initializedPos = strpos(
    $source,
    "=== 'initialized'"
);

$registerPos = strpos(
    $source,
    'billing_provider_register_configured_adapters('
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
    $initializedPos !== false
    && $registerPos !== false
    && $initializedPos < $registerPos
    && strpos(
        $source,
        "'outcome' => 'initialized_blocked'"
    ) !== false,
    'an in-flight initialized checkout blocks another top-up without provider guessing'
);

$check(
    $registerPos !== false
    && $verifyPos !== false
    && $beginPos !== false
    && $registerPos < $verifyPos
    && $verifyPos < $beginPos,
    'provider registration and verification occur before the reconciliation transaction'
);

$check(
    strpos(
        $source,
        'Seat-top-up provider reconciliation must start outside a database transaction.'
    ) !== false
    && strpos(
        $source,
        'Seat-top-up provider verification unexpectedly opened a database transaction.'
    ) !== false,
    'provider reconciliation fails closed if network work overlaps a transaction'
);

$attemptLockPos = strpos(
    $source,
    'billing_audit_attempt_by_id(',
    $beginPos === false
        ? 0
        : $beginPos
);

$requestLockPos = strpos(
    $source,
    'billing_seat_change_request_by_payment(',
    $attemptLockPos === false
        ? 0
        : $attemptLockPos
);

$applyVerificationPos = strpos(
    $source,
    'billing_audit_apply_verification('
);

$check(
    $attemptLockPos !== false
    && $requestLockPos !== false
    && $applyVerificationPos !== false
    && $beginPos < $attemptLockPos
    && $attemptLockPos < $requestLockPos
    && $requestLockPos < $applyVerificationPos,
    'transaction re-locks payment attempt then durable request before applying provider fact'
);

$check(
    strpos(
        $source,
        "(\$requestState['status'] ?? '')"
    ) !== false
    && strpos(
        $source,
        "!== 'awaiting_payment'"
    ) !== false
    && strpos(
        $source,
        'Seat-top-up durable request changed during provider verification.'
    ) !== false,
    'post-provider transaction fails closed if the durable request was settled concurrently'
);

$paidDispatchPos = strpos(
    $source,
    'billing_paid_attempt_dispatch('
);

$terminalPos = strpos(
    $source,
    'billing_seat_change_reconcile_terminal_payment('
);

$commitPos = strpos(
    $source,
    '$pdo->commit();'
);

$check(
    $paidDispatchPos !== false
    && $terminalPos !== false
    && $commitPos !== false
    && $applyVerificationPos < $paidDispatchPos
    && $applyVerificationPos < $terminalPos
    && $paidDispatchPos < $commitPos
    && $terminalPos < $commitPos,
    'verified paid and terminal facts delegate to canonical state services before commit'
);

$check(
    strpos(
        $source,
        "\$outcome = 'paid_applied';"
    ) !== false
    && strpos(
        $source,
        "\$outcome = 'pending_blocked';"
    ) !== false
    && strpos(
        $source,
        "\$outcome = 'terminal_settled';"
    ) !== false
    && strpos(
        $source,
        "\$outcome = 'refunded_settled';"
    ) !== false,
    'provider outcomes have explicit replacement semantics'
);

$check(
    strpos(
        $source,
        '$pdo->rollBack();'
    ) !== false
    && strpos(
        $source,
        'catch (Throwable $e)'
    ) !== false,
    'reconciliation transaction rolls back on failure'
);

$batchStart = strpos(
    $source,
    'function billing_seat_topup_reconcile_open_candidates_for_replacement('
);

$batchSource = $batchStart === false
    ? ''
    : substr(
        $source,
        $batchStart
    );

$check(
    $batchSource !== ''
    && strpos(
        $batchSource,
        'int $maxAttempts = 10'
    ) !== false
    && strpos(
        $batchSource,
        '$maxAttempts < 1'
    ) !== false
    && strpos(
        $batchSource,
        '$maxAttempts > 25'
    ) !== false,
    'bounded orchestration has an explicit finite reconciliation limit'
);

$check(
    $batchSource !== ''
    && strpos(
        $batchSource,
        'billing_seat_topup_reconcile_next_for_replacement('
    ) !== false
    && strpos(
        $batchSource,
        '$seenAttemptIds'
    ) !== false
    && strpos(
        $batchSource,
        'repeated the same payment attempt'
    ) !== false,
    'bounded orchestration delegates one attempt at a time and detects repetition'
);

$check(
    strpos(
        $batchSource,
        "'replacement_allowed' => false"
    ) !== false
    && strpos(
        $batchSource,
        "'initialized_blocked'"
    ) !== false
    && strpos(
        $batchSource,
        "'pending_blocked'"
    ) !== false
    && strpos(
        $batchSource,
        "'paid_applied'"
    ) !== false,
    'initialized, pending and newly applied prior payments prevent another top-up checkout'
);

$check(
    strpos(
        $batchSource,
        "'terminal_settled'"
    ) !== false
    && strpos(
        $batchSource,
        "'refunded_settled'"
    ) !== false
    && strpos(
        $batchSource,
        "'replacement_allowed' => true"
    ) !== false
    && strpos(
        $batchSource,
        'billing_seat_topup_reconciliation_candidate('
    ) !== false,
    'only safely settled terminal candidates allow a new top-up after bounded exhaustion'
);

$check(
    strpos(
        $source,
        'billing_commercial_attempt_mark_superseded_after_verified_terminal('
    ) === false
    && strpos(
        $source,
        'billing_commercial_attempt_reconcile_verified_fact('
    ) === false,
    'seat-top-up reconciliation does not reuse subscription-only commercial supersession'
);

$protectedDml =
    '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+'
    . '(?:billing_payment_attempts|farms|farm_modules|'
    . 'farm_role_limits|farm_subscription_seat_addons|'
    . 'subscriptions|billing_seat_change_requests)\b/i';

$check(
    preg_match(
        $protectedDml,
        $source
    ) !== 1,
    'launcher contains no direct payment, entitlement, subscription or seat-change DML'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    echo "V2.3 SEAT-TOP-UP RECONCILIATION LAUNCHER: FAILED\n";
    exit(1);
}

echo "V2.3 SEAT-TOP-UP RECONCILIATION LAUNCHER: PASSED\n";
?>
