<?php
/**
 * Focused verifier for V2.3 terminal seat-payment route wiring.
 *
 * Read-only, database-free and provider-network-free.
 */

$root = dirname(__DIR__);

$paths = [
    'return' => $root . '/billing/return.php',
    'webhook' => $root . '/billing/webhook.php',
];

$source = [];

foreach ($paths as $key => $path) {
    if (!is_file($path)) {
        fwrite(
            STDERR,
            "FAIL: missing {$path}\n"
        );
        exit(1);
    }

    $content = file_get_contents($path);

    if ($content === false) {
        fwrite(
            STDERR,
            "FAIL: unable to read {$path}\n"
        );
        exit(1);
    }

    $source[$key] = $content;
}

$return = $source['return'];
$webhook = $source['webhook'];

$returnCompact = preg_replace(
    '/\s+/',
    '',
    $return
);

$webhookCompact = preg_replace(
    '/\s+/',
    '',
    $webhook
);

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
        $return,
        'billing_seat_change_request.php'
    ) !== false
    && strpos(
        $webhook,
        'billing_seat_change_request.php'
    ) !== false,
    'return and webhook explicitly load the centralized seat-change service'
);

$check(
    substr_count(
        $return,
        'billing_seat_change_reconcile_terminal_payment('
    ) === 1
    && substr_count(
        $webhook,
        'billing_seat_change_reconcile_terminal_payment('
    ) === 1,
    'each verified-payment route has exactly one terminal reconciler call'
);

$returnAudit = strpos(
    $return,
    'billing_audit_apply_verification('
);

$returnReconcile = strpos(
    $return,
    'billing_seat_change_reconcile_terminal_payment('
);

$returnDispatch = strpos(
    $return,
    'billing_paid_attempt_dispatch('
);

$returnBegin = strpos(
    $return,
    'beginTransaction()'
);

$returnCommit = strpos(
    $return,
    '$pdo->commit();'
);

$check(
    $returnBegin !== false
    && $returnAudit !== false
    && $returnReconcile !== false
    && $returnDispatch !== false
    && $returnCommit !== false
    && $returnBegin < $returnAudit
    && $returnAudit < $returnReconcile
    && $returnReconcile < $returnDispatch
    && $returnDispatch < $returnCommit,
    'return route applies verification, reconciles terminal state, dispatches paid state and commits in order'
);

$webhookAudit = strpos(
    $webhook,
    'billing_audit_apply_verification('
);

$webhookReconcile = strpos(
    $webhook,
    'billing_seat_change_reconcile_terminal_payment('
);

$webhookDispatch = strpos(
    $webhook,
    'billing_paid_attempt_dispatch('
);

$webhookProcessed = strpos(
    $webhook,
    "billing_audit_event_mark(\$pdo, \$registeredEventId, 'processed')"
);

$webhookBegin = strpos(
    $webhook,
    'beginTransaction()'
);

$webhookCommit = strpos(
    $webhook,
    '$pdo->commit();'
);

$check(
    $webhookBegin !== false
    && $webhookAudit !== false
    && $webhookReconcile !== false
    && $webhookDispatch !== false
    && $webhookProcessed !== false
    && $webhookCommit !== false
    && $webhookBegin < $webhookAudit
    && $webhookAudit < $webhookReconcile
    && $webhookReconcile < $webhookDispatch
    && $webhookDispatch < $webhookProcessed
    && $webhookProcessed < $webhookCommit,
    'webhook applies verification, reconciles terminal state, dispatches paid state, marks event and commits in order'
);

$lockedAttemptCall =
    "billing_seat_change_reconcile_terminal_payment("
    . "\$pdo,(int)\$locked['id']);";

$check(
    strpos(
        $returnCompact,
        $lockedAttemptCall
    ) !== false
    && strpos(
        $webhookCompact,
        $lockedAttemptCall
    ) !== false,
    'both routes reconcile exactly the already-locked verified payment attempt'
);

$check(
    strpos(
        $return,
        "=== 'paid'"
    ) !== false
    && $returnReconcile < $returnDispatch
    && strpos(
        $webhook,
        "=== 'paid'"
    ) !== false
    && $webhookReconcile < $webhookDispatch,
    'terminal reconciliation runs before the existing canonical paid-only dispatcher gate'
);

$combined = $return . "\n" . $webhook;

$directSeatDml =
    '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+'
    . 'billing_seat_change_requests\b/i';

$check(
    !preg_match(
        $directSeatDml,
        $combined
    ),
    'routes delegate seat-request lifecycle mutation instead of duplicating seat-change DML'
);

$directCommercialDml =
    '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+'
    . '(?:farms|farm_modules|farm_role_limits|'
    . 'farm_subscription_seat_addons|subscriptions)\b/i';

$check(
    !preg_match(
        $directCommercialDml,
        $combined
    ),
    'terminal route wiring adds no direct entitlement or subscription DML'
);

$check(
    substr_count(
        $return,
        'billing_paid_attempt_dispatch('
    ) === 1
    && substr_count(
        $webhook,
        'billing_paid_attempt_dispatch('
    ) === 1,
    'existing centralized paid-purpose dispatcher remains the only paid application call site'
);

$check(
    strpos(
        $return,
        'billing_provider_initialize_checkout('
    ) === false
    && strpos(
        $webhook,
        'billing_provider_initialize_checkout('
    ) === false,
    'verification routes do not initialize another provider checkout'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    echo "V2.3 TERMINAL PAYMENT ROUTE WIRING: FAILED\n";
    exit(1);
}

echo "V2.3 TERMINAL PAYMENT ROUTE WIRING: PASSED\n";
