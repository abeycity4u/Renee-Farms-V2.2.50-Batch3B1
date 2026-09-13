<?php
/**
 * V2.3 Gap D Phase 3B subscription refund-reversal verifier.
 *
 * Read-only. It never invokes billing_refund_resolution_resolve_reverse().
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__
    . '/../includes/billing_refund_resolution.php';

$root = dirname(__DIR__);
$servicePath =
    $root
    . '/includes/billing_refund_resolution.php';

$checks = 0;
$failures = 0;

$check = static function (
    bool $ok,
    string $message
) use (&$checks, &$failures): void {
    $checks++;

    echo ($ok ? 'PASS' : 'FAIL')
        . ': '
        . $message
        . PHP_EOL;

    if (!$ok) {
        $failures++;
    }
};

$check(
    function_exists(
        'billing_refund_resolution_resolve_reverse'
    ),
    'subscription reverse-entitlement resolver exists'
);

$check(
    in_array(
        'reversal_subscription_record_id',
        billing_refund_resolution_required_columns(),
        true
    ),
    'refund-resolution contract requires durable reversal history link'
);

$check(
    billing_refund_resolution_migration_ready(
        $pdo
    ),
    'refund-resolution readiness requires migrations 050 and 052'
);

$check(
    billing_refund_resolution_foreign_keys_ready(
        $pdo
    ),
    'refund-resolution foreign-key contract includes reversal history link'
);

$check(
    billing_refund_resolution_ready(
        $pdo
    ),
    'refund-resolution service is transactionally ready after migration 052'
);

/*
 * Pure row-contract semantics.
 */
$base = [
    'id' => 1,
    'farm_id' => 1,
    'payment_attempt_id' => 1,
    'purpose' => 'subscription',
    'status' => 'pending_review',
    'resolution_action' => null,
    'refund_verified_at' =>
        '2026-09-13 12:00:00',
    'applied_subscription_record_id' => 10,
    'seat_change_request_id' => null,
    'reversal_subscription_record_id' => null,
    'resolved_at' => null,
    'resolved_by_user_id' => null,
    'resolution_reason' => null,
];

$pending =
    billing_refund_resolution_row_contract(
        $base
    );

$check(
    $pending[
        'reversal_subscription_record_id'
    ] === null,
    'pending review carries no reversal history link'
);

$pendingBad = $base;
$pendingBad[
    'reversal_subscription_record_id'
] = 11;

$pendingRejected = false;

try {
    billing_refund_resolution_row_contract(
        $pendingBad
    );
} catch (Throwable $e) {
    $pendingRejected = true;
}

$check(
    $pendingRejected,
    'pending review rejects premature reversal history linkage'
);

$reverse = $base;
$reverse['status'] = 'resolved';
$reverse['resolution_action'] =
    'reverse_entitlement';
$reverse['resolved_at'] =
    '2026-09-13 12:10:00';
$reverse['resolved_by_user_id'] = 1;
$reverse['resolution_reason'] =
    'Verified compensating reversal.';
$reverse[
    'reversal_subscription_record_id'
] = 11;

$reverseContract =
    billing_refund_resolution_row_contract(
        $reverse
    );

$check(
    (int)$reverseContract[
        'reversal_subscription_record_id'
    ] === 11,
    'resolved reverse action requires and exposes exact compensation history'
);

$reverseMissing = $reverse;
$reverseMissing[
    'reversal_subscription_record_id'
] = null;

$reverseMissingRejected = false;

try {
    billing_refund_resolution_row_contract(
        $reverseMissing
    );
} catch (Throwable $e) {
    $reverseMissingRejected = true;
}

$check(
    $reverseMissingRejected,
    'resolved reverse action rejects missing compensation history'
);

$preserve = $reverse;
$preserve['resolution_action'] =
    'preserve_entitlement';
$preserve[
    'reversal_subscription_record_id'
] = null;

$preserveContract =
    billing_refund_resolution_row_contract(
        $preserve
    );

$check(
    $preserveContract[
        'reversal_subscription_record_id'
    ] === null,
    'preserve action remains valid without compensation history'
);

$preserveBad = $preserve;
$preserveBad[
    'reversal_subscription_record_id'
] = 11;

$preserveRejected = false;

try {
    billing_refund_resolution_row_contract(
        $preserveBad
    );
} catch (Throwable $e) {
    $preserveRejected = true;
}

$check(
    $preserveRejected,
    'preserve action rejects reversal history linkage'
);

/*
 * Static resolver contract.
 */
$reflection =
    new ReflectionFunction(
        'billing_refund_resolution_resolve_reverse'
    );

$lines =
    file($servicePath);

$source =
    implode(
        '',
        array_slice(
            $lines,
            $reflection->getStartLine() - 1,
            $reflection->getEndLine()
                - $reflection->getStartLine()
                + 1
        )
    );

$finalizerReflection =
    new ReflectionFunction(
        'billing_refund_resolution_finalize_reverse'
    );

$finalizerSource =
    implode(
        '',
        array_slice(
            $lines,
            $finalizerReflection->getStartLine() - 1,
            $finalizerReflection->getEndLine()
                - $finalizerReflection->getStartLine()
                + 1
        )
    );

$check(
    strpos(
        $source,
        '$pdo->inTransaction()'
    ) !== false,
    'reverse resolver requires caller-owned transaction'
);

$check(
    strpos(
        $source,
        "billing_audit_attempt_by_id("
    ) !== false
    && strpos(
        $source,
        "billing_refund_resolution_by_payment("
    ) !== false,
    'reverse resolver locks payment fact then refund review'
);

$check(
    strpos(
        $source,
        "billing_refund_resolution_resolve_reverse_seat_topup_pending("
    ) !== false
    && strpos(
        $source,
        "Seat-top-up refund reversal is not enabled"
    ) === false,
    'shared reverse resolver dispatches seat-top-up refunds to the dedicated bounded branch'
);

$check(
    strpos(
        $source,
        "FROM farms"
    ) !== false
    && strpos(
        $source,
        "FOR UPDATE"
    ) !== false,
    'tenant runtime anchor is row-locked before compensation'
);

$check(
    strpos(
        $source,
        "ORDER BY id DESC"
    ) !== false
    && strpos(
        $source,
        "newer tenant commercial history exists"
    ) !== false,
    'reverse resolver fails closed when applied history is no longer latest'
);

$check(
    strpos(
        $source,
        "subscription_record_build_snapshot("
    ) !== false
    && strpos(
        $source,
        "hash_equals("
    ) !== false,
    'current runtime must hash-match refunded application before reversal'
);

$check(
    strpos(
        $source,
        "id < ?"
    ) !== false
    && strpos(
        $source,
        "predecessorSubscriptionRecordId"
    ) !== false,
    'reverse resolver uses immediate same-tenant predecessor'
);

$check(
    strpos(
        $source,
        "subscription_seat_assert_capacity("
    ) !== false,
    'predecessor capacity is checked before current-state mutation'
);

$check(
    strpos(
        $source,
        "sync_farm_entitlements("
    ) !== false
    && strpos(
        $source,
        "subscription_seat_save_addons("
    ) !== false
    && strpos(
        $source,
        "subscription_seat_save_effective_limits("
    ) !== false,
    'runtime restoration uses shared entitlement and seat writers'
);

$check(
    strpos(
        $source,
        "subscription_record_append_from_history_source("
    ) !== false
    && strpos(
        $source,
        "billing_refund_reversed"
    ) !== false,
    'reverse resolver appends explicit compensating immutable history'
);

$check(
    strpos(
        $source,
        "billing_refund_resolution_finalize_reverse("
    ) !== false
    && strpos(
        $finalizerSource,
        "UPDATE billing_refund_resolutions"
    ) !== false
    && strpos(
        $finalizerSource,
        "reversal_subscription_record_id = ?"
    ) !== false
    && strpos(
        $finalizerSource,
        "'reverse_entitlement'"
    ) !== false,
    'resolution uses shared exactly-once finalizer with exact compensation history'
);

$forbiddenPaymentDml =
    preg_match(
        '/\b(?:UPDATE|DELETE\s+FROM|INSERT\s+INTO)\s+'
        . 'billing_payment_attempts\b/i',
        $source
    );

$forbiddenHistoryRewrite =
    preg_match(
        '/\b(?:UPDATE|DELETE\s+FROM)\s+'
        . 'subscriptions\b/i',
        $source
    );

$providerIo =
    stripos($source, 'curl_') !== false
    || stripos($source, 'paystack') !== false
    || stripos($source, 'https://') !== false
    || stripos($source, 'http://') !== false;

$check(
    $forbiddenPaymentDml === 0
    && $forbiddenHistoryRewrite === 0
    && $providerIo === false,
    'reverse resolver never rewrites provider/payment fact or immutable history'
);

/*
 * Production remains read-only during verifier execution.
 * Reconfirm the proven static subscription eligibility matrix.
 */
$rows =
    $pdo->query(
        "SELECT
             a.id AS attempt_id,
             a.farm_id,
             a.applied_subscription_record_id AS applied_id,
             (
                 SELECT MAX(s.id)
                 FROM subscriptions s
                 WHERE s.farm_id = a.farm_id
             ) AS latest_id,
             (
                 SELECT MAX(prev.id)
                 FROM subscriptions prev
                 WHERE prev.farm_id = a.farm_id
                   AND prev.id <
                       a.applied_subscription_record_id
             ) AS predecessor_id
         FROM billing_payment_attempts a
         WHERE a.purpose = 'subscription'
           AND a.applied_subscription_record_id
               IS NOT NULL
         ORDER BY a.id"
    )->fetchAll(PDO::FETCH_ASSOC);

$eligible = 0;
$structuralFailures = 0;

foreach ($rows as $row) {
    try {
        $farmId =
            (int)$row['farm_id'];

        $appliedId =
            (int)$row['applied_id'];

        $predecessorId =
            (int)$row['predecessor_id'];

        $applied =
            subscription_record_history_source_contract(
                $pdo,
                $farmId,
                $appliedId
            );

        $runtime =
            subscription_record_build_snapshot(
                $pdo,
                $farmId
            );

        $runtimeMatches =
            hash_equals(
                (string)$applied[
                    'snapshot_hash'
                ],
                (string)$runtime[
                    'snapshot_hash'
                ]
            );

        $capacityOk = false;

        if ($predecessorId > 0) {
            $predecessor =
                subscription_record_history_source_contract(
                    $pdo,
                    $farmId,
                    $predecessorId
                );

            $target =
                $predecessor['snapshot'];

            try {
                subscription_seat_assert_capacity(
                    $pdo,
                    $farmId,
                    (string)$target['plan_code'],
                    $target['modules'],
                    $target['seat_addons']
                );

                $capacityOk = true;
            } catch (Throwable $e) {
                $capacityOk = false;
            }
        }

        if (
            (int)$row['latest_id']
                === $appliedId
            && $runtimeMatches
            && $predecessorId > 0
            && $capacityOk
        ) {
            $eligible++;
        }
    } catch (Throwable $e) {
        $structuralFailures++;
    }
}

echo 'APPLIED_SUBSCRIPTION_ATTEMPTS='
    . count($rows)
    . PHP_EOL;

echo 'STATICALLY_REVERSIBLE_CANDIDATES='
    . $eligible
    . PHP_EOL;

echo 'STRUCTURAL_FAILURES='
    . $structuralFailures
    . PHP_EOL;

$check(
    count($rows) === 7
    && $eligible === 2
    && $structuralFailures === 0,
    'current production matrix still has exactly two statically reversible subscription candidates'
);

$refundCount =
    (int)$pdo->query(
        "SELECT COUNT(*)
         FROM billing_refund_resolutions"
    )->fetchColumn();

$reversalLinkedCount =
    (int)$pdo->query(
        "SELECT COUNT(*)
         FROM billing_refund_resolutions
         WHERE reversal_subscription_record_id
               IS NOT NULL"
    )->fetchColumn();

$check(
    $refundCount === 0
    && $reversalLinkedCount === 0,
    'production has no live refund review or reversal row for mutating QA'
);

echo 'CHECKS='
    . $checks
    . PHP_EOL;

echo 'FAILURES='
    . $failures
    . PHP_EOL;

echo $failures === 0
    ? 'V2.3 SUBSCRIPTION REFUND REVERSAL: PASSED'
    : 'V2.3 SUBSCRIPTION REFUND REVERSAL: FAILED';

echo PHP_EOL;

exit(
    $failures === 0
        ? 0
        : 1
);
