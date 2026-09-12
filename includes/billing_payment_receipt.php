<?php
/**
 * V2.3 tenant billing payment receipt read model.
 *
 * Read-only contract:
 * - caller supplies the authenticated tenant farm id;
 * - lookup is pinned by payment-attempt id + farm id;
 * - only paid attempts are eligible for receipts;
 * - no provider call or commercial-state mutation occurs here.
 */

require_once __DIR__ . '/billing_payment_foundation.php';

if (!function_exists('billing_payment_receipt_find')) {
    function billing_payment_receipt_find(
        PDO $pdo,
        int $farmId,
        int $attemptId
    ): ?array {
        if ($farmId < 1) {
            throw new InvalidArgumentException(
                'A valid tenant farm is required.'
            );
        }

        if ($attemptId < 1) {
            throw new InvalidArgumentException(
                'A valid billing payment attempt is required.'
            );
        }

        if (!billing_payment_table_exists(
            $pdo,
            'billing_payment_attempts'
        )) {
            return null;
        }

        $stmt = $pdo->prepare(
            "SELECT
                id,
                farm_id,
                purpose,
                status,
                commercial_disposition,
                provider,
                provider_reference,
                provider_transaction_id,
                plan_code,
                billing_interval,
                amount,
                currency,
                modules_snapshot,
                seat_addons_snapshot,
                verified_at,
                paid_at,
                created_at,
                applied_subscription_record_id
             FROM billing_payment_attempts
             WHERE id = ?
               AND farm_id = ?
               AND status = 'paid'
             LIMIT 1"
        );

        $stmt->execute([
            $attemptId,
            $farmId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $row['purpose'] =
            billing_payment_attempt_purpose($row);

        return $row;
    }
}
?>
