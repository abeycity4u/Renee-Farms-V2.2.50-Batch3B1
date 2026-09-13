<?php
/**
 * V2.3 Gap D Phase 3B seat-top-up refund-reversal verifier.
 *
 * Read-only. It never invokes billing_refund_resolution_resolve_reverse().
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__
    . '/../includes/billing_refund_resolution.php';

$servicePath =
    dirname(__DIR__)
    . '/includes/billing_refund_resolution.php';

$lines =
    file($servicePath);

if (!is_array($lines)) {
    throw new RuntimeException(
        'Unable to inspect refund-resolution service.'
    );
}

$extract =
    static function (
        string $functionName
    ) use ($lines): string {
        $reflection =
            new ReflectionFunction(
                $functionName
            );

        return implode(
            '',
            array_slice(
                $lines,
                $reflection->getStartLine() - 1,
                $reflection->getEndLine()
                    - $reflection->getStartLine()
                    + 1
            )
        );
    };

$checks = 0;
$failures = 0;

$check =
    static function (
        bool $ok,
        string $message
    ) use (&$checks, &$failures): void {
        $checks++;

        echo ($ok ? 'PASS' : 'FAIL')
            . ': '
            . $message
            . PHP_EOL;

        if ($ok === false) {
            $failures++;
        }
    };

$check(
    function_exists(
        'billing_refund_resolution_resolve_reverse'
    )
    && function_exists(
        'billing_refund_resolution_resolve_reverse_seat_topup_pending'
    )
    && function_exists(
        'billing_refund_resolution_finalize_reverse'
    ),
    'shared reverse resolver, seat branch and finalizer exist'
);

$main =
    $extract(
        'billing_refund_resolution_resolve_reverse'
    );

$lineage =
    $extract(
        'billing_refund_resolution_assert_locked_lineage'
    );

$seat =
    $extract(
        'billing_refund_resolution_resolve_reverse_seat_topup_pending'
    );

$finalizer =
    $extract(
        'billing_refund_resolution_finalize_reverse'
    );

$check(
    strpos(
        $main,
        "'seat_topup'"
    ) !== false
    && strpos(
        $main,
        'billing_refund_resolution_resolve_reverse_seat_topup_pending('
    ) !== false,
    'public resolver dispatches seat-top-up to bounded seat branch'
);

$check(
    strpos(
        $lineage,
        "'seat_change_applied_subscription_record_id'"
    ) !== false
    && strpos(
        $lineage,
        "'seat_request_contract'"
    ) !== false,
    'locked seat lineage carries exact application history and request contract'
);

$check(
    strpos(
        $seat,
        '$pdo->inTransaction()'
    ) !== false,
    'seat reversal requires caller-owned transaction'
);

$check(
    strpos(
        $seat,
        'FROM farms'
    ) !== false
    && strpos(
        $seat,
        'FOR UPDATE'
    ) !== false,
    'seat reversal row-locks tenant runtime'
);

$check(
    strpos(
        $seat,
        'seat_topup_payment_applied'
    ) !== false
    && strpos(
        $seat,
        'newer tenant commercial history exists'
    ) !== false,
    'seat application history must still be latest'
);

$check(
    strpos(
        $seat,
        'subscription_record_build_snapshot('
    ) !== false
    && strpos(
        $seat,
        'current commercial state no longer matches the applied top-up'
    ) !== false,
    'current runtime must hash-match applied top-up history'
);

$check(
    strpos(
        $seat,
        'id < ?'
    ) !== false
    && strpos(
        $seat,
        'predecessorHistoryId'
    ) !== false,
    'seat reversal requires immediate same-tenant predecessor'
);

$check(
    strpos(
        $seat,
        '$commercialInvariantOk'
    ) !== false
    && strpos(
        $seat,
        'differs outside the purchased seat change'
    ) !== false,
    'plan status term and modules must remain invariant'
);

$check(
    strpos(
        $seat,
        '$fromExtraSeats'
    ) !== false
    && strpos(
        $seat,
        '$toExtraSeats'
    ) !== false
    && strpos(
        $seat,
        'requested role seat transition'
    ) !== false,
    'target role must match exact from-to purchased seat transition'
);

$check(
    strpos(
        $seat,
        'unrelated seat changes'
    ) !== false,
    'all non-target seat roles must remain unchanged'
);

$check(
    strpos(
        $seat,
        'subscription_seat_assert_capacity('
    ) !== false,
    'assigned-user capacity is checked before seat reduction'
);

$check(
    strpos(
        $seat,
        'subscription_seat_save_addons('
    ) !== false
    && strpos(
        $seat,
        'subscription_seat_save_effective_limits('
    ) !== false,
    'seat restoration uses shared canonical seat writers'
);

$check(
    preg_match(
        '/\bUPDATE\s+farms\b/i',
        $seat
    ) !== 1
    && strpos(
        $seat,
        'sync_farm_entitlements('
    ) === false,
    'seat reversal does not rewrite plan status dates or modules'
);

$check(
    strpos(
        $seat,
        'failed to restore the exact predecessor commercial snapshot'
    ) !== false,
    'restored runtime must exactly match predecessor snapshot'
);

$check(
    strpos(
        $seat,
        'subscription_record_append_from_history_source('
    ) !== false
    && strpos(
        $seat,
        "'billing_refund_reversed'"
    ) !== false,
    'seat reversal appends immutable compensating commercial history'
);

$check(
    strpos(
        $seat,
        'billing_refund_resolution_finalize_reverse('
    ) !== false,
    'seat reversal uses shared exactly-once finalizer'
);

$check(
    substr_count(
        $finalizer,
        'UPDATE billing_refund_resolutions'
    ) === 1
    && strpos(
        $finalizer,
        'reversal_subscription_record_id = ?'
    ) !== false
    && strpos(
        $finalizer,
        "'reverse_entitlement'"
    ) !== false
    && strpos(
        $finalizer,
        "AND status = 'pending_review'"
    ) !== false,
    'shared finalizer records exact compensation history exactly once'
);

$forbiddenPaymentDml =
    preg_match(
        '/\b(?:UPDATE|DELETE\s+FROM|INSERT\s+INTO)\s+'
        . 'billing_payment_attempts\b/i',
        $seat . "\n" . $finalizer
    ) === 1;

$forbiddenRequestDml =
    preg_match(
        '/\b(?:UPDATE|DELETE\s+FROM|INSERT\s+INTO)\s+'
        . 'billing_seat_change_requests\b/i',
        $seat . "\n" . $finalizer
    ) === 1;

$forbiddenHistoryRewrite =
    preg_match(
        '/\b(?:UPDATE|DELETE\s+FROM)\s+subscriptions\b/i',
        $seat . "\n" . $finalizer
    ) === 1;

$providerIo =
    stripos(
        $seat . "\n" . $finalizer,
        'curl_'
    ) !== false
    || stripos(
        $seat . "\n" . $finalizer,
        'paystack'
    ) !== false
    || stripos(
        $seat . "\n" . $finalizer,
        'https://'
    ) !== false
    || stripos(
        $seat . "\n" . $finalizer,
        'http://'
    ) !== false;

$check(
    $forbiddenPaymentDml === false
    && $forbiddenRequestDml === false
    && $forbiddenHistoryRewrite === false
    && $providerIo === false,
    'seat reversal preserves payment/request/history facts and performs no provider I/O'
);

$check(
    billing_refund_resolution_ready(
        $pdo
    )
    && billing_seat_change_ready(
        $pdo
    ),
    'refund and seat application storage contracts are ready'
);

/*
 * Production checks remain read-only.
 */
$appliedSeatTopups =
    (int)$pdo->query(
        "SELECT COUNT(*)
         FROM billing_seat_change_requests
         WHERE status = 'applied'"
    )->fetchColumn();

$refundRows =
    (int)$pdo->query(
        "SELECT COUNT(*)
         FROM billing_refund_resolutions"
    )->fetchColumn();

$seatRefundRows =
    (int)$pdo->query(
        "SELECT COUNT(*)
         FROM billing_refund_resolutions
         WHERE purpose = 'seat_topup'"
    )->fetchColumn();

$check(
    $appliedSeatTopups === 0
    && $refundRows === 0
    && $seatRefundRows === 0,
    'production has no applied seat-top-up or live seat refund row for mutating QA'
);

$attempt21 =
    $pdo->query(
        "SELECT
             id,
             farm_id,
             purpose,
             status,
             applied_subscription_record_id,
             paid_at,
             verified_at
         FROM billing_payment_attempts
         WHERE id = 21
         LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC)
    ?: null;

$request3 =
    $pdo->query(
        "SELECT
             id,
             farm_id,
             change_kind,
             status,
             role_code,
             from_extra_seats,
             to_extra_seats,
             payment_attempt_id,
             applied_subscription_record_id,
             applied_at,
             cancelled_at
         FROM billing_seat_change_requests
         WHERE id = 3
         LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC)
    ?: null;

$protectedOk =
    is_array($attempt21)
    && (int)$attempt21['farm_id'] === 22
    && (string)$attempt21['purpose']
        === 'seat_topup'
    && (string)$attempt21['status']
        === 'pending'
    && $attempt21[
        'applied_subscription_record_id'
    ] === null
    && $attempt21['paid_at'] === null
    && $attempt21['verified_at'] === null
    && is_array($request3)
    && (int)$request3['farm_id'] === 22
    && (string)$request3['change_kind']
        === 'add'
    && (string)$request3['status']
        === 'awaiting_payment'
    && (string)$request3['role_code']
        === 'poultry_manager'
    && (int)$request3['from_extra_seats']
        === 1
    && (int)$request3['to_extra_seats']
        === 3
    && (int)$request3['payment_attempt_id']
        === 21
    && $request3[
        'applied_subscription_record_id'
    ] === null
    && $request3['applied_at'] === null
    && $request3['cancelled_at'] === null;

$check(
    $protectedOk,
    'protected attempt 21 and request 3 remain unchanged'
);

echo 'APPLIED_SEAT_TOPUPS='
    . $appliedSeatTopups
    . PHP_EOL;

echo 'REFUND_RESOLUTION_ROWS='
    . $refundRows
    . PHP_EOL;

echo 'SEAT_REFUND_ROWS='
    . $seatRefundRows
    . PHP_EOL;

echo 'CHECKS='
    . $checks
    . PHP_EOL;

echo 'FAILURES='
    . $failures
    . PHP_EOL;

echo $failures === 0
    ? 'V2.3 SEAT-TOP-UP REFUND REVERSAL: PASSED'
    : 'V2.3 SEAT-TOP-UP REFUND REVERSAL: FAILED';

echo PHP_EOL;

exit(
    $failures === 0
        ? 0
        : 1
);
