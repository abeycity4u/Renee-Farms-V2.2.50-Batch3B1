<?php
/**
 * Focused static verifier for V2.3 commercial reconciliation launcher.
 *
 * Database-free, provider-free and mutation-free.
 */

$root = dirname(__DIR__);

$path =
    $root
    . '/includes/billing_commercial_attempt_reconciliation_launcher.php';

if (!is_file($path)) {
    fwrite(
        STDERR,
        "FAIL: missing commercial reconciliation launcher.\n"
    );
    exit(1);
}

$source = file_get_contents($path);

if ($source === false) {
    fwrite(
        STDERR,
        "FAIL: unable to read commercial reconciliation launcher.\n"
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
        'billing_commercial_attempt_disposition.php'
    ) !== false
    && strpos(
        $source,
        'billing_commercial_attempt_reconciliation.php'
    ) !== false,
    'launcher reuses canonical provider, disposition and reconciliation foundations'
);

$check(
    strpos(
        $source,
        'function billing_commercial_attempt_reconciliation_candidate('
    ) !== false
    && strpos(
        $source,
        'function billing_commercial_attempt_reconcile_next_for_replacement('
    ) !== false,
    'candidate selection and one-attempt provider reconciliation are centralized'
);

$selectStart = strpos(
    $source,
    'SELECT *'
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
        "purpose = 'subscription'"
    ) !== false
    && strpos(
        $selectSql,
        "commercial_disposition = 'eligible'"
    ) !== false
    && strpos(
        $selectSql,
        "status IN ('failed', 'cancelled')"
    ) !== false
    && strpos(
        $selectSql,
        'ORDER BY id ASC'
    ) !== false
    && strpos(
        $selectSql,
        'LIMIT 1'
    ) !== false,
    'launcher selects one deterministic eligible terminal subscription candidate'
);

$check(
    $selectSql !== ''
    && preg_match(
        '/\bFOR\s+UPDATE\b/i',
        $selectSql
    ) !== 1,
    'candidate lookup deliberately takes no row lock before provider network work'
);

$check(
    strpos(
        $source,
        'billing_commercial_attempt_disposition_state('
    ) !== false
    && strpos(
        $source,
        'paid application evidence'
    ) !== false,
    'candidate is revalidated for disposition and absence of paid application evidence'
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
    $registerPos !== false
    && $verifyPos !== false
    && $beginPos !== false
    && $registerPos < $verifyPos
    && $verifyPos < $beginPos,
    'provider registration and verification occur before the database transaction starts'
);

$check(
    strpos(
        $source,
        'Provider reconciliation launcher must start outside a database transaction.'
    ) !== false
    && strpos(
        $source,
        'Provider verification unexpectedly opened a database transaction.'
    ) !== false,
    'launcher fails closed if provider work overlaps a database transaction'
);

$reconcilePos = strpos(
    $source,
    'billing_commercial_attempt_reconcile_verified_fact('
);

$commitPos = strpos(
    $source,
    '$pdo->commit();'
);

$check(
    $beginPos !== false
    && $reconcilePos !== false
    && $commitPos !== false
    && $beginPos < $reconcilePos
    && $reconcilePos < $commitPos,
    'verified provider fact enters canonical reconciliation only inside the short transaction'
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
    'launcher rolls back reconciliation transaction on failure'
);

$check(
    strpos(
        $source,
        "'attempt_found' => false"
    ) !== false
    && strpos(
        $source,
        "'blocking' => null"
    ) !== false,
    'absence of a terminal candidate does not falsely claim commercial coordination is clear'
);

$forbiddenDirectCalls = [
    'billing_audit_apply_verification(',
    'billing_commercial_attempt_mark_superseded_after_verified_terminal(',
    'billing_paid_attempt_dispatch(',
];

$hasForbiddenDirectCall = false;

foreach ($forbiddenDirectCalls as $needle) {
    if (strpos($source, $needle) !== false) {
        $hasForbiddenDirectCall = true;
        break;
    }
}

$check(
    !$hasForbiddenDirectCall,
    'launcher delegates state transitions entirely to the canonical reconciliation service'
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
    'launcher contains no direct billing, entitlement or subscription-state DML'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    echo "V2.3 COMMERCIAL RECONCILIATION LAUNCHER: FAILED\n";
    exit(1);
}

echo "V2.3 COMMERCIAL RECONCILIATION LAUNCHER: PASSED\n";
?>
