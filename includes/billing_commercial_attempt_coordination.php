<?php
/**
 * V2.3 commercial subscription-attempt coordination foundation.
 *
 * Serializes subscription checkout / seat-lifecycle decisions on the tenant
 * farm row without taking payment-attempt row locks.
 *
 * This avoids reversing the existing verified-payment lock order:
 * paid application locks payment attempt -> tenant farm.
 *
 * Coordination callers lock tenant farm -> inspect committed attempt state.
 * The attempt query intentionally remains a normal MVCC read.
 */

require_once __DIR__ . '/billing_payment_foundation.php';

if (!function_exists('billing_commercial_attempt_coordination')) {
    function billing_commercial_attempt_coordination(
        PDO $pdo,
        int $farmId
    ): array {
        if ($farmId < 1) {
            throw new InvalidArgumentException(
                'A valid tenant farm is required for commercial attempt coordination.'
            );
        }

        if (!$pdo->inTransaction()) {
            throw new RuntimeException(
                'Commercial attempt coordination requires an active caller transaction.'
            );
        }

        if (!billing_payment_foundation_ready($pdo)) {
            throw new RuntimeException(
                'Billing payment storage is not transactionally ready for commercial coordination.'
            );
        }

        $farmStmt = $pdo->prepare(
            "SELECT id
             FROM farms
             WHERE id = ?
               AND slug <> 'owner'
             LIMIT 1
             FOR UPDATE"
        );

        $farmStmt->execute([$farmId]);

        if (!$farmStmt->fetchColumn()) {
            throw new RuntimeException(
                'Tenant farm could not be locked for commercial coordination.'
            );
        }

        /*
         * Intentionally NOT FOR UPDATE.
         *
         * Verified payment/application currently locks:
         * payment attempt -> tenant farm.
         *
         * Taking payment-attempt row locks here after locking the farm would
         * create the reverse lock order and a deadlock opportunity.
         */
        $stmt = $pdo->prepare(
            "SELECT
                id,
                farm_id,
                purpose,
                status,
                provider,
                provider_reference,
                applied_subscription_record_id,
                failure_code,
                created_at,
                updated_at
             FROM billing_payment_attempts
             WHERE farm_id = ?
               AND purpose = 'subscription'
             ORDER BY id ASC"
        );

        $stmt->execute([$farmId]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $blocking = [];
        $settled = [];

        foreach ($rows as $row) {
            if ((int)($row['farm_id'] ?? 0) !== $farmId) {
                throw new RuntimeException(
                    'Subscription payment attempt escaped its tenant coordination scope.'
                );
            }

            $status = strtolower(trim(
                (string)($row['status'] ?? '')
            ));

            $applicationId = (int)(
                $row['applied_subscription_record_id']
                    ?? 0
            );

            if (!in_array(
                $status,
                [
                    'initialized',
                    'pending',
                    'paid',
                    'failed',
                    'cancelled',
                    'refunded',
                ],
                true
            )) {
                throw new RuntimeException(
                    'Subscription payment attempt has an unsupported coordination status.'
                );
            }

            if ($applicationId > 0
                && !in_array(
                    $status,
                    ['paid', 'refunded'],
                    true
                )) {
                throw new RuntimeException(
                    'Subscription payment application marker conflicts with attempt status.'
                );
            }

            $classification = null;

            if (in_array(
                $status,
                ['initialized', 'pending'],
                true
            )) {
                $classification = 'open_checkout';
            } elseif (in_array(
                $status,
                ['failed', 'cancelled'],
                true
            )) {
                /*
                 * Current audit policy permits a later verified provider fact
                 * to move these states to paid. They therefore cannot yet be
                 * treated as safely superseded.
                 */
                $classification = 'reconciliation_required';
            } elseif ($status === 'paid'
                && $applicationId < 1) {
                $classification = 'paid_unapplied';
            }

            $attempt = [
                'id' => (int)($row['id'] ?? 0),
                'status' => $status,
                'classification' => $classification,
                'provider' => strtolower(trim(
                    (string)($row['provider'] ?? '')
                )),
                'provider_reference' => trim(
                    (string)($row['provider_reference'] ?? '')
                ),
                'applied_subscription_record_id' =>
                    $applicationId > 0
                        ? $applicationId
                        : null,
                'failure_code' =>
                    ($row['failure_code'] ?? null),
                'created_at' =>
                    ($row['created_at'] ?? null),
                'updated_at' =>
                    ($row['updated_at'] ?? null),
            ];

            if ($classification !== null) {
                $blocking[] = $attempt;
            } else {
                $settled[] = $attempt;
            }
        }

        return [
            'farm_id' => $farmId,
            'clear' => count($blocking) === 0,
            'blocking_count' => count($blocking),
            'blocking_attempts' => $blocking,
            'settled_count' => count($settled),
            'settled_attempts' => $settled,
        ];
    }
}

if (!function_exists('billing_commercial_attempt_assert_clear')) {
    function billing_commercial_attempt_assert_clear(
        PDO $pdo,
        int $farmId
    ): array {
        $coordination =
            billing_commercial_attempt_coordination(
                $pdo,
                $farmId
            );

        if (($coordination['clear'] ?? false) !== true) {
            throw new RuntimeException(
                'An earlier subscription checkout must be reconciled before another commercial change can continue.'
            );
        }

        return $coordination;
    }
}
?>
