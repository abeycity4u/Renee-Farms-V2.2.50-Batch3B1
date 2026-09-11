<?php
/**
 * Focused static verifier for the V2.3 commercial subscription-attempt
 * coordination foundation.
 */

$root = dirname(__DIR__);
$path =
    $root
    . '/includes/billing_commercial_attempt_coordination.php';

if (!is_file($path)) {
    fwrite(
        STDERR,
        "FAIL: missing commercial attempt coordination helper.\n"
    );
    exit(1);
}

$source = file_get_contents($path);

if ($source === false) {
    fwrite(
        STDERR,
        "FAIL: unable to read commercial attempt coordination helper.\n"
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
        "require_once __DIR__ . '/billing_payment_foundation.php';"
    ) !== false,
    'coordination reuses canonical billing payment foundation'
);

$check(
    strpos(
        $source,
        'function billing_commercial_attempt_coordination('
    ) !== false
    && strpos(
        $source,
        'function billing_commercial_attempt_assert_clear('
    ) !== false,
    'centralized coordination and fail-closed assertion helpers exist'
);

$check(
    strpos(
        $source,
        'if (!$pdo->inTransaction())'
    ) !== false
    && strpos(
        $source,
        'requires an active caller transaction'
    ) !== false,
    'coordination requires caller-owned transaction'
);

$check(
    strpos(
        $source,
        'FROM farms'
    ) !== false
    && strpos(
        $source,
        "slug <> 'owner'"
    ) !== false
    && strpos(
        $source,
        'FOR UPDATE'
    ) !== false,
    'tenant farm is the serialization lock'
);

$check(
    strpos(
        $source,
        'FROM billing_payment_attempts'
    ) !== false
    && strpos(
        $source,
        "purpose = 'subscription'"
    ) !== false,
    'coordination inspects only tenant subscription attempts'
);

$paymentSelect = '';

if (preg_match(
    '/\$stmt\s*=\s*\$pdo->prepare\(\s*"(?P<sql>SELECT.*?FROM\s+billing_payment_attempts.*?WHERE\s+farm_id\s*=\s*\?.*?purpose\s*=\s*\'subscription\'.*?ORDER\s+BY\s+id\s+ASC)"\s*\);/is',
    $source,
    $paymentSelectMatch
) === 1) {
    $paymentSelect =
        (string)($paymentSelectMatch['sql'] ?? '');
}

$check(
    $paymentSelect !== ''
    && preg_match(
        '/\bFOR\s+UPDATE\b/i',
        $paymentSelect
    ) !== 1,
    'payment-attempt inspection deliberately avoids reverse row-lock ordering'
);

$check(
    strpos(
        $source,
        "['initialized', 'pending']"
    ) !== false
    && strpos(
        $source,
        "'open_checkout'"
    ) !== false,
    'initialized and pending subscription attempts block new commercial changes'
);

$check(
    strpos(
        $source,
        "['failed', 'cancelled']"
    ) !== false
    && strpos(
        $source,
        "'reconciliation_required'"
    ) !== false,
    'failed and cancelled attempts remain blocking until safely reconciled'
);

$check(
    strpos(
        $source,
        "\$status === 'paid'"
    ) !== false
    && strpos(
        $source,
        "\$applicationId < 1"
    ) !== false
    && strpos(
        $source,
        "'paid_unapplied'"
    ) !== false,
    'paid subscription attempt without application marker blocks fail closed'
);

$check(
    strpos(
        $source,
        "['paid', 'refunded']"
    ) !== false
    && strpos(
        $source,
        'application marker conflicts with attempt status'
    ) !== false,
    'application markers are accepted only on paid/refunded attempts'
);

$check(
    strpos(
        $source,
        "'clear' => count(\$blocking) === 0"
    ) !== false
    && strpos(
        $source,
        "'blocking_attempts' => \$blocking"
    ) !== false,
    'coordination exposes explicit blocking state for later shared callers'
);

$check(
    strpos(
        $source,
        'must be reconciled before another commercial change can continue'
    ) !== false,
    'fail-closed assertion has one centralized customer-safe boundary'
);

$check(
    strpos(
        $source,
        "billing_commercial_attempt_disposition.php"
    ) !== false
    && strpos(
        $source,
        'billing_commercial_attempt_disposition_storage_ready('
    ) !== false,
    'coordination requires the commercial disposition foundation and migration readiness'
);

$check(
    strpos(
        $paymentSelect,
        'commercial_disposition'
    ) !== false
    && strpos(
        $paymentSelect,
        'commercial_superseded_at'
    ) !== false
    && strpos(
        $paymentSelect,
        'commercial_supersession_verified_at'
    ) !== false,
    'coordination reads the commercial disposition audit evidence with each subscription attempt'
);

$dispositionStatePos = strpos(
    $source,
    'billing_commercial_attempt_disposition_state('
);

$supersededBranchPos = strpos(
    $source,
    "\$disposition === 'superseded'"
);

$openCheckoutPos = strpos(
    $source,
    "'open_checkout'"
);

$check(
    $dispositionStatePos !== false
    && $supersededBranchPos !== false
    && $openCheckoutPos !== false
    && $dispositionStatePos < $supersededBranchPos
    && $supersededBranchPos < $openCheckoutPos,
    'valid superseded attempts are normalized before ordinary blocking classification and become non-blocking'
);

$check(
    strpos(
        $source,
        "'commercial_disposition' => \$disposition"
    ) !== false
    && strpos(
        $source,
        "'commercial_supersession_verified_at'"
    ) !== false
    && strpos(
        $source,
        "'commercial_supersession_reason'"
    ) !== false,
    'coordination exposes normalized supersession evidence for settled-attempt diagnostics'
);

$forbiddenDml = [
    'UPDATE billing_payment_attempts',
    'INSERT INTO billing_payment_attempts',
    'DELETE FROM billing_payment_attempts',
    'UPDATE farms',
    'INSERT INTO farms',
    'DELETE FROM farms',
    'UPDATE billing_seat_change_requests',
    'INSERT INTO billing_seat_change_requests',
    'DELETE FROM billing_seat_change_requests',
];

$hasDml = false;

foreach ($forbiddenDml as $needle) {
    if (stripos($source, $needle) !== false) {
        $hasDml = true;
        break;
    }
}

$check(
    !$hasDml,
    'coordination foundation performs no commercial database mutation'
);

$check(
    strpos(
        $source,
        'billing_provider_'
    ) === false
    && strpos(
        $source,
        'curl_'
    ) === false,
    'coordination foundation performs no provider or network work'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    echo "V2.3 COMMERCIAL ATTEMPT COORDINATION FOUNDATION: FAILED\n";
    exit(1);
}

echo "V2.3 COMMERCIAL ATTEMPT COORDINATION FOUNDATION: PASSED\n";
?>
