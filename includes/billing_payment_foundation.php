<?php
/**
 * V2.3 provider-neutral billing/payment audit foundation.
 *
 * Stage 1 contract:
 * - server code freezes a canonical quote before a provider interaction;
 * - payment attempts are idempotent by (provider, provider_reference);
 * - provider events are idempotent by (provider, provider_event_id);
 * - only a SHA-256 payload hash is persisted for provider events;
 * - recording an attempt/event NEVER grants or changes tenant entitlement.
 *
 * This file intentionally contains no writes to farms, farm_modules,
 * farm_role_limits, farm_subscription_seat_addons, or subscriptions. A later,
 * separately verified payment-application bridge will own that transition.
 */

if (!function_exists('billing_payment_required_columns')) {
    function billing_payment_required_columns(): array
    {
        return [
            'billing_payment_attempts' => [
                'id', 'farm_id', 'status', 'provider', 'provider_reference',
                'provider_transaction_id', 'provider_subscription_id', 'plan_code',
                'billing_interval', 'amount', 'currency', 'modules_snapshot',
                'seat_addons_snapshot', 'quote_hash', 'initiated_by_user_id',
                'verified_at', 'paid_at', 'failed_at', 'failure_code',
                'applied_subscription_record_id', 'created_at', 'updated_at',
            ],
            'billing_provider_events' => [
                'id', 'provider', 'provider_event_id', 'event_type', 'payload_hash',
                'payment_attempt_id', 'processing_status', 'error_message',
                'received_at', 'processed_at',
            ],
        ];
    }
}

if (!function_exists('billing_payment_table_exists')) {
    function billing_payment_table_exists(PDO $pdo, string $table): bool
    {
        if (!array_key_exists($table, billing_payment_required_columns())) return false;
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('billing_payment_table_ready')) {
    function billing_payment_table_ready(PDO $pdo, string $table): bool
    {
        $required = billing_payment_required_columns()[$table] ?? null;
        if (!is_array($required) || !billing_payment_table_exists($pdo, $table)) return false;

        $stmt = $pdo->prepare(
            'SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $stmt->execute([$table]);
        $columns = array_fill_keys($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [], true);
        foreach ($required as $column) {
            if (!isset($columns[$column])) return false;
        }
        return true;
    }
}

if (!function_exists('billing_payment_foundation_ready')) {
    function billing_payment_foundation_ready(PDO $pdo): bool
    {
        return billing_payment_table_ready($pdo, 'billing_payment_attempts')
            && billing_payment_table_ready($pdo, 'billing_provider_events');
    }
}

if (!function_exists('billing_payment_normalize_provider')) {
    function billing_payment_normalize_provider(string $provider): string
    {
        $provider = strtolower(trim($provider));
        if ($provider === '' || strlen($provider) > 40 || !preg_match('/^[a-z0-9][a-z0-9_.-]*$/', $provider)) {
            throw new InvalidArgumentException('A valid billing provider code is required.');
        }
        return $provider;
    }
}

if (!function_exists('billing_payment_normalize_reference')) {
    function billing_payment_normalize_reference(string $reference, int $maxLength = 150): string
    {
        $reference = trim($reference);
        if ($reference === '' || strlen($reference) > $maxLength || preg_match('/[\x00-\x1F\x7F]/', $reference)) {
            throw new InvalidArgumentException('A valid provider reference is required.');
        }
        return $reference;
    }
}

if (!function_exists('billing_payment_normalize_interval')) {
    function billing_payment_normalize_interval(string $interval): string
    {
        $interval = strtolower(trim($interval));
        if (!in_array($interval, ['monthly', 'annual'], true)) {
            throw new InvalidArgumentException('Billing interval must be monthly or annual.');
        }
        return $interval;
    }
}

if (!function_exists('billing_payment_normalize_currency')) {
    function billing_payment_normalize_currency(string $currency): string
    {
        $currency = strtoupper(trim($currency));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Billing currency must be a three-letter code.');
        }
        return $currency;
    }
}

if (!function_exists('billing_payment_normalize_amount')) {
    function billing_payment_normalize_amount($amount): string
    {
        if (is_int($amount)) $amount = (string)$amount;
        if (!is_string($amount)) {
            throw new InvalidArgumentException('Billing amount must be supplied as an exact decimal value.');
        }

        $amount = trim($amount);
        if (!preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/', $amount, $match)) {
            throw new InvalidArgumentException('Billing amount is not a valid DECIMAL(12,2) value.');
        }

        $whole = ltrim($match[1], '0');
        if ($whole === '') $whole = '0';
        $fraction = str_pad((string)($match[2] ?? ''), 2, '0', STR_PAD_RIGHT);
        $normalized = $whole . '.' . $fraction;
        if ($normalized === '0.00') {
            throw new InvalidArgumentException('Billing amount must be greater than zero.');
        }
        return $normalized;
    }
}

if (!function_exists('billing_payment_normalize_modules')) {
    function billing_payment_normalize_modules(array $modules): array
    {
        $normalized = [];
        foreach ($modules as $module) {
            $module = strtolower(trim((string)$module));
            if (in_array($module, ['poultry', 'ruminant'], true)) $normalized[$module] = true;
        }
        $normalized = array_keys($normalized);
        sort($normalized, SORT_STRING);
        if (!$normalized) {
            throw new InvalidArgumentException('A billing quote requires Poultry, Ruminant, or both.');
        }
        return $normalized;
    }
}

if (!function_exists('billing_payment_normalize_seat_addons')) {
    function billing_payment_normalize_seat_addons(array $seatAddOns): array
    {
        if (function_exists('subscription_seat_normalize_addons')) {
            $normalized = subscription_seat_normalize_addons($seatAddOns);
        } else {
            $normalized = [];
            foreach (['poultry_manager', 'ruminant_manager', 'sales_rep', 'viewer'] as $role) {
                $value = filter_var(
                    $seatAddOns[$role] ?? 0,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 0, 'max_range' => 500]]
                );
                if ($value === false) throw new InvalidArgumentException('Seat add-ons must be non-negative integers.');
                $normalized[$role] = (int)$value;
            }
        }
        ksort($normalized, SORT_STRING);
        return $normalized;
    }
}

if (!function_exists('billing_payment_build_quote')) {
    function billing_payment_build_quote(
        string $planCode,
        string $billingInterval,
        $amount,
        string $currency,
        array $modules,
        array $seatAddOns = []
    ): array {
        $planCode = strtolower(trim($planCode));
        if (!function_exists('subscription_plan_is_valid') || !subscription_plan_is_valid($planCode)) {
            throw new InvalidArgumentException('Unknown subscription plan.');
        }

        $quote = [
            'plan_code' => $planCode,
            'billing_interval' => billing_payment_normalize_interval($billingInterval),
            'amount' => billing_payment_normalize_amount($amount),
            'currency' => billing_payment_normalize_currency($currency),
            'modules' => billing_payment_normalize_modules($modules),
            'seat_addons' => billing_payment_normalize_seat_addons($seatAddOns),
        ];

        $json = json_encode($quote, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) throw new RuntimeException('Unable to encode the billing quote snapshot.');

        return [
            'quote' => $quote,
            'quote_json' => $json,
            'quote_hash' => hash('sha256', $json),
        ];
    }
}

if (!function_exists('billing_payment_attempt_by_reference')) {
    function billing_payment_attempt_by_reference(PDO $pdo, string $provider, string $providerReference): ?array
    {
        if (!billing_payment_table_exists($pdo, 'billing_payment_attempts')) return null;
        $provider = billing_payment_normalize_provider($provider);
        $providerReference = billing_payment_normalize_reference($providerReference);
        $stmt = $pdo->prepare(
            'SELECT * FROM billing_payment_attempts WHERE provider = ? AND provider_reference = ? LIMIT 1'
        );
        $stmt->execute([$provider, $providerReference]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

if (!function_exists('billing_payment_attempt_create')) {
    function billing_payment_attempt_create(
        PDO $pdo,
        int $farmId,
        string $provider,
        string $providerReference,
        string $planCode,
        string $billingInterval,
        $amount,
        string $currency,
        array $modules,
        array $seatAddOns = [],
        ?int $initiatedByUserId = null
    ): array {
        if (!billing_payment_foundation_ready($pdo)) {
            throw new RuntimeException('Billing payment storage is not installed. Apply migration 042_billing_payment_foundation.sql first.');
        }
        if ($farmId < 1) throw new InvalidArgumentException('A valid tenant farm is required.');

        $farmStmt = $pdo->prepare("SELECT id FROM farms WHERE id = ? AND slug <> 'owner' LIMIT 1");
        $farmStmt->execute([$farmId]);
        if (!$farmStmt->fetchColumn()) throw new RuntimeException('Tenant farm could not be found for billing.');

        $provider = billing_payment_normalize_provider($provider);
        $providerReference = billing_payment_normalize_reference($providerReference);
        $built = billing_payment_build_quote($planCode, $billingInterval, $amount, $currency, $modules, $seatAddOns);
        $quote = $built['quote'];
        $quoteHash = (string)$built['quote_hash'];

        $existing = billing_payment_attempt_by_reference($pdo, $provider, $providerReference);
        if ($existing) {
            if (!hash_equals((string)$existing['quote_hash'], $quoteHash)) {
                throw new RuntimeException('Provider reference already belongs to a different billing quote.');
            }
            return ['inserted' => false, 'id' => (int)$existing['id'], 'quote_hash' => $quoteHash, 'attempt' => $existing];
        }

        $initiatedByUserId = ($initiatedByUserId !== null && $initiatedByUserId > 0) ? $initiatedByUserId : null;
        $stmt = $pdo->prepare(
            'INSERT INTO billing_payment_attempts (
                farm_id, status, provider, provider_reference, plan_code,
                billing_interval, amount, currency, modules_snapshot,
                seat_addons_snapshot, quote_hash, initiated_by_user_id
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        try {
            $stmt->execute([
                $farmId,
                'initialized',
                $provider,
                $providerReference,
                $quote['plan_code'],
                $quote['billing_interval'],
                $quote['amount'],
                $quote['currency'],
                json_encode($quote['modules'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                json_encode($quote['seat_addons'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $quoteHash,
                $initiatedByUserId,
            ]);
        } catch (PDOException $e) {
            $driverCode = (int)($e->errorInfo[1] ?? 0);
            if ($driverCode !== 1062) throw $e;
            $existing = billing_payment_attempt_by_reference($pdo, $provider, $providerReference);
            if (!$existing || !hash_equals((string)$existing['quote_hash'], $quoteHash)) {
                throw new RuntimeException('Provider reference collision detected while creating the billing attempt.', 0, $e);
            }
            return ['inserted' => false, 'id' => (int)$existing['id'], 'quote_hash' => $quoteHash, 'attempt' => $existing];
        }

        $id = (int)$pdo->lastInsertId();
        $read = $pdo->prepare('SELECT * FROM billing_payment_attempts WHERE id = ? LIMIT 1');
        $read->execute([$id]);
        return ['inserted' => true, 'id' => $id, 'quote_hash' => $quoteHash, 'attempt' => $read->fetch(PDO::FETCH_ASSOC) ?: null];
    }
}

if (!function_exists('billing_provider_event_register')) {
    function billing_provider_event_register(
        PDO $pdo,
        string $provider,
        string $providerEventId,
        string $eventType,
        string $rawPayload,
        ?int $paymentAttemptId = null
    ): array {
        if (!billing_payment_foundation_ready($pdo)) {
            throw new RuntimeException('Billing payment storage is not installed. Apply migration 042_billing_payment_foundation.sql first.');
        }

        $provider = billing_payment_normalize_provider($provider);
        $providerEventId = billing_payment_normalize_reference($providerEventId);
        $eventType = trim($eventType);
        if ($eventType === '' || strlen($eventType) > 100 || preg_match('/[\x00-\x1F\x7F]/', $eventType)) {
            throw new InvalidArgumentException('A valid provider event type is required.');
        }
        $payloadHash = hash('sha256', $rawPayload);

        if ($paymentAttemptId !== null) {
            if ($paymentAttemptId < 1) throw new InvalidArgumentException('Payment attempt id must be positive.');
            $attemptStmt = $pdo->prepare('SELECT id, provider FROM billing_payment_attempts WHERE id = ? LIMIT 1');
            $attemptStmt->execute([$paymentAttemptId]);
            $attempt = $attemptStmt->fetch(PDO::FETCH_ASSOC);
            if (!$attempt) throw new RuntimeException('Billing payment attempt could not be found for provider event linkage.');
            if (!hash_equals((string)$attempt['provider'], $provider)) {
                throw new RuntimeException('Provider event cannot be linked to a payment attempt from another provider.');
            }
        }

        $find = $pdo->prepare(
            'SELECT * FROM billing_provider_events WHERE provider = ? AND provider_event_id = ? LIMIT 1'
        );
        $find->execute([$provider, $providerEventId]);
        $existing = $find->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($existing) {
            if (!hash_equals((string)$existing['payload_hash'], $payloadHash)) {
                throw new RuntimeException('Provider event id was reused with a different payload.');
            }
            return ['inserted' => false, 'id' => (int)$existing['id'], 'payload_hash' => $payloadHash, 'event' => $existing];
        }

        $insert = $pdo->prepare(
            'INSERT INTO billing_provider_events (
                provider, provider_event_id, event_type, payload_hash,
                payment_attempt_id, processing_status
             ) VALUES (?, ?, ?, ?, ?, ?)'
        );
        try {
            $insert->execute([$provider, $providerEventId, $eventType, $payloadHash, $paymentAttemptId, 'received']);
        } catch (PDOException $e) {
            $driverCode = (int)($e->errorInfo[1] ?? 0);
            if ($driverCode !== 1062) throw $e;
            $find->execute([$provider, $providerEventId]);
            $existing = $find->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$existing || !hash_equals((string)$existing['payload_hash'], $payloadHash)) {
                throw new RuntimeException('Provider event id collision detected while registering the event.', 0, $e);
            }
            return ['inserted' => false, 'id' => (int)$existing['id'], 'payload_hash' => $payloadHash, 'event' => $existing];
        }

        $id = (int)$pdo->lastInsertId();
        $read = $pdo->prepare('SELECT * FROM billing_provider_events WHERE id = ? LIMIT 1');
        $read->execute([$id]);
        return ['inserted' => true, 'id' => $id, 'payload_hash' => $payloadHash, 'event' => $read->fetch(PDO::FETCH_ASSOC) ?: null];
    }
}
