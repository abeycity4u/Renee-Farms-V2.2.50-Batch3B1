<?php
/**
 * V2.3 Billing Stage 2F audit-only payment state transitions.
 *
 * This layer may update billing_payment_attempts and billing_provider_events only.
 * It must never mutate farms, farm_modules, seat limits/add-ons or subscriptions.
 */

if (!function_exists('billing_audit_attempt_by_reference')) {
    function billing_audit_attempt_by_reference(
        PDO $pdo,
        string $provider,
        string $providerReference,
        ?int $farmId = null,
        bool $forUpdate = false
    ): ?array {
        $provider = function_exists('billing_payment_normalize_provider')
            ? billing_payment_normalize_provider($provider)
            : strtolower(trim($provider));
        $providerReference = function_exists('billing_payment_normalize_reference')
            ? billing_payment_normalize_reference($providerReference)
            : trim($providerReference);

        $sql = 'SELECT * FROM billing_payment_attempts WHERE provider = ? AND provider_reference = ?';
        $params = [$provider, $providerReference];
        if ($farmId !== null) {
            if ($farmId < 1) throw new InvalidArgumentException('A valid tenant farm is required.');
            $sql .= ' AND farm_id = ?';
            $params[] = $farmId;
        }
        $sql .= ' LIMIT 1';
        if ($forUpdate) $sql .= ' FOR UPDATE';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

if (!function_exists('billing_audit_attempt_by_id')) {
    function billing_audit_attempt_by_id(PDO $pdo, int $attemptId, bool $forUpdate = false): ?array
    {
        if ($attemptId < 1) throw new InvalidArgumentException('A valid billing payment attempt is required.');
        $sql = 'SELECT * FROM billing_payment_attempts WHERE id = ? LIMIT 1';
        if ($forUpdate) $sql .= ' FOR UPDATE';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$attemptId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

if (!function_exists('billing_audit_optional_identifier')) {
    function billing_audit_optional_identifier($value, int $maxLength = 150): ?string
    {
        if ($value === null) return null;
        $value = trim((string)$value);
        if ($value === '') return null;
        if (strlen($value) > $maxLength || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new RuntimeException('Provider returned an invalid billing identifier.');
        }
        return $value;
    }
}

if (!function_exists('billing_audit_datetime')) {
    function billing_audit_datetime($value): ?string
    {
        if ($value === null || trim((string)$value) === '') return null;
        try {
            $date = new DateTimeImmutable((string)$value);
            return $date->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('billing_audit_assert_provider_identity')) {
    function billing_audit_assert_provider_identity(array $attempt, array $verification): void
    {
        $attemptProvider = strtolower(trim((string)($attempt['provider'] ?? '')));
        $resultProvider = strtolower(trim((string)($verification['provider'] ?? '')));
        if ($attemptProvider === '' || !hash_equals($attemptProvider, $resultProvider)) {
            throw new RuntimeException('Provider verification does not belong to this billing attempt.');
        }

        $attemptReference = (string)($attempt['provider_reference'] ?? '');
        $resultReference = (string)($verification['provider_reference'] ?? '');
        if ($attemptReference === '' || !hash_equals($attemptReference, $resultReference)) {
            throw new RuntimeException('Provider verification returned a different billing reference.');
        }

        $attemptAmount = function_exists('billing_payment_normalize_amount')
            ? billing_payment_normalize_amount((string)($attempt['amount'] ?? ''))
            : trim((string)($attempt['amount'] ?? ''));
        $resultAmount = function_exists('billing_payment_normalize_amount')
            ? billing_payment_normalize_amount($verification['amount'] ?? null)
            : trim((string)($verification['amount'] ?? ''));
        if (!hash_equals($attemptAmount, $resultAmount)) {
            throw new RuntimeException('Verified provider amount does not match the frozen billing attempt.');
        }

        $attemptCurrency = strtoupper(trim((string)($attempt['currency'] ?? '')));
        $resultCurrency = strtoupper(trim((string)($verification['currency'] ?? '')));
        if ($attemptCurrency === '' || !hash_equals($attemptCurrency, $resultCurrency)) {
            throw new RuntimeException('Verified provider currency does not match the frozen billing attempt.');
        }

        if (($verification['verified'] ?? null) !== true) {
            throw new RuntimeException('Unverified provider payment result cannot update billing state.');
        }
    }
}

if (!function_exists('billing_audit_merge_provider_identifier')) {
    function billing_audit_merge_provider_identifier(?string $existing, ?string $incoming, string $label): ?string
    {
        $existing = billing_audit_optional_identifier($existing);
        $incoming = billing_audit_optional_identifier($incoming);
        if ($existing !== null && $incoming !== null && !hash_equals($existing, $incoming)) {
            throw new RuntimeException($label . ' changed for an existing billing attempt.');
        }
        return $existing ?? $incoming;
    }
}

if (!function_exists('billing_audit_effective_status')) {
    function billing_audit_effective_status(string $current, string $incoming): string
    {
        $allowed = ['initialized', 'pending', 'paid', 'failed', 'cancelled', 'refunded'];
        if (!in_array($current, $allowed, true) || !in_array($incoming, $allowed, true)) {
            throw new RuntimeException('Unsupported billing attempt status transition.');
        }
        if ($current === 'refunded') return 'refunded';
        if ($current === 'paid' && $incoming !== 'refunded') return 'paid';
        return $incoming;
    }
}

if (!function_exists('billing_audit_mark_pending')) {
    function billing_audit_mark_pending(
        PDO $pdo,
        int $attemptId,
        ?string $providerTransactionId = null,
        ?string $providerSubscriptionId = null
    ): array {
        $attempt = billing_audit_attempt_by_id($pdo, $attemptId, true);
        if (!$attempt) throw new RuntimeException('Billing payment attempt could not be found.');

        $current = (string)$attempt['status'];
        $status = in_array($current, ['paid', 'refunded'], true) ? $current : 'pending';
        $transactionId = billing_audit_merge_provider_identifier(
            $attempt['provider_transaction_id'] ?? null,
            $providerTransactionId,
            'Provider transaction id'
        );
        $subscriptionId = billing_audit_merge_provider_identifier(
            $attempt['provider_subscription_id'] ?? null,
            $providerSubscriptionId,
            'Provider subscription id'
        );

        $stmt = $pdo->prepare(
            'UPDATE billing_payment_attempts
             SET status = ?, provider_transaction_id = ?, provider_subscription_id = ?,
                 failed_at = CASE WHEN ? IN (\'paid\', \'refunded\') THEN failed_at ELSE NULL END,
                 failure_code = CASE WHEN ? IN (\'paid\', \'refunded\') THEN failure_code ELSE NULL END
             WHERE id = ?'
        );
        $stmt->execute([$status, $transactionId, $subscriptionId, $status, $status, $attemptId]);
        return billing_audit_attempt_by_id($pdo, $attemptId, false) ?: $attempt;
    }
}

if (!function_exists('billing_audit_apply_verification')) {
    function billing_audit_apply_verification(PDO $pdo, int $attemptId, array $verification): array
    {
        $attempt = billing_audit_attempt_by_id($pdo, $attemptId, true);
        if (!$attempt) throw new RuntimeException('Billing payment attempt could not be found.');
        billing_audit_assert_provider_identity($attempt, $verification);

        $incoming = strtolower(trim((string)($verification['status'] ?? '')));
        $current = strtolower(trim((string)($attempt['status'] ?? '')));
        $status = billing_audit_effective_status($current, $incoming);

        $transactionId = billing_audit_merge_provider_identifier(
            $attempt['provider_transaction_id'] ?? null,
            $verification['provider_transaction_id'] ?? null,
            'Provider transaction id'
        );
        $subscriptionId = billing_audit_merge_provider_identifier(
            $attempt['provider_subscription_id'] ?? null,
            $verification['provider_subscription_id'] ?? null,
            'Provider subscription id'
        );

        $paidAt = $attempt['paid_at'] ?? null;
        if ($status === 'paid' && !$paidAt) {
            $paidAt = billing_audit_datetime($verification['paid_at'] ?? null) ?? date('Y-m-d H:i:s');
        }

        $failedAt = $attempt['failed_at'] ?? null;
        $failureCode = $attempt['failure_code'] ?? null;
        if (in_array($status, ['failed', 'cancelled'], true)) {
            $failedAt = $failedAt ?: date('Y-m-d H:i:s');
            $failureCode = billing_audit_optional_identifier($verification['failure_code'] ?? $status, 80) ?? $status;
        } elseif (!in_array($status, ['paid', 'refunded'], true)) {
            $failedAt = null;
            $failureCode = null;
        } else {
            $failedAt = null;
            $failureCode = null;
        }

        $stmt = $pdo->prepare(
            'UPDATE billing_payment_attempts
             SET status = ?, provider_transaction_id = ?, provider_subscription_id = ?,
                 verified_at = ?, paid_at = ?, failed_at = ?, failure_code = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $status,
            $transactionId,
            $subscriptionId,
            date('Y-m-d H:i:s'),
            $paidAt,
            $failedAt,
            $failureCode,
            $attemptId,
        ]);

        return billing_audit_attempt_by_id($pdo, $attemptId, false) ?: $attempt;
    }
}

if (!function_exists('billing_audit_mark_initialization_failed')) {
    function billing_audit_mark_initialization_failed(PDO $pdo, int $attemptId, string $code = 'checkout_initialization_failed'): array
    {
        $attempt = billing_audit_attempt_by_id($pdo, $attemptId, true);
        if (!$attempt) throw new RuntimeException('Billing payment attempt could not be found.');
        if (in_array((string)$attempt['status'], ['paid', 'refunded'], true)) return $attempt;

        $code = billing_audit_optional_identifier($code, 80) ?? 'checkout_initialization_failed';
        $stmt = $pdo->prepare(
            'UPDATE billing_payment_attempts
             SET status = \'failed\', failed_at = ?, failure_code = ?
             WHERE id = ?'
        );
        $stmt->execute([date('Y-m-d H:i:s'), $code, $attemptId]);
        return billing_audit_attempt_by_id($pdo, $attemptId, false) ?: $attempt;
    }
}

if (!function_exists('billing_audit_event_terminal')) {
    function billing_audit_event_terminal(array $event): bool
    {
        return in_array((string)($event['processing_status'] ?? ''), ['processed', 'ignored'], true);
    }
}

if (!function_exists('billing_audit_event_mark')) {
    function billing_audit_event_mark(PDO $pdo, int $eventId, string $status, ?string $errorMessage = null): array
    {
        if ($eventId < 1) throw new InvalidArgumentException('A valid provider event is required.');
        if (!in_array($status, ['processed', 'ignored', 'failed'], true)) {
            throw new InvalidArgumentException('Unsupported provider event processing status.');
        }
        $errorMessage = $errorMessage === null ? null : trim($errorMessage);
        if ($errorMessage !== null && strlen($errorMessage) > 255) {
            $errorMessage = substr($errorMessage, 0, 255);
        }
        if ($status !== 'failed') $errorMessage = null;

        $stmt = $pdo->prepare(
            'UPDATE billing_provider_events
             SET processing_status = ?, error_message = ?, processed_at = ?
             WHERE id = ?'
        );
        $stmt->execute([$status, $errorMessage, date('Y-m-d H:i:s'), $eventId]);

        $read = $pdo->prepare('SELECT * FROM billing_provider_events WHERE id = ? LIMIT 1');
        $read->execute([$eventId]);
        $row = $read->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('Provider event could not be found after processing update.');
        return $row;
    }
}
