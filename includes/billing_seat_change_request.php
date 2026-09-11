<?php
/**
 * V2.3 durable extra-seat change request foundation.
 *
 * Contract:
 * - migration 045 owns durable request storage;
 * - add requests require a seat_topup payment attempt;
 * - remove requests are no-refund scheduled changes at period end;
 * - immutable commercial request facts are SHA-256 bound;
 * - mutable workflow state is deliberately outside the request hash;
 * - this service does not call a provider or change tenant entitlement.
 */

require_once __DIR__ . '/subscription_plan_catalog.php';
require_once __DIR__ . '/subscription_seat_policy.php';
require_once __DIR__ . '/billing_payment_foundation.php';
require_once __DIR__ . '/billing_payment_audit_state.php';
require_once __DIR__ . '/billing_pricing_contract.php';
require_once __DIR__ . '/billing_current_product.php';
require_once __DIR__ . '/billing_seat_proration.php';

if (!function_exists('billing_seat_change_required_columns')) {
    function billing_seat_change_required_columns(): array
    {
        return [
            'id',
            'farm_id',
            'change_kind',
            'status',
            'role_code',
            'from_extra_seats',
            'to_extra_seats',
            'plan_code',
            'billing_interval',
            'modules_snapshot',
            'amount',
            'currency',
            'current_period_ends_at',
            'quoted_at',
            'lineage_start_at',
            'segment_start_at',
            'segment_end_at',
            'pricing_version',
            'pricing_hash',
            'unit_amount',
            'partial_unit_amount',
            'future_full_periods',
            'per_seat_amount',
            'latest_paid_subscription_id',
            'latest_paid_attempt_id',
            'effective_at',
            'payment_attempt_id',
            'initiated_by_user_id',
            'request_hash',
            'applied_at',
            'cancelled_at',
            'created_at',
            'updated_at',
        ];
    }
}

if (!function_exists('billing_seat_change_table_exists')) {
    function billing_seat_change_table_exists(PDO $pdo): bool
    {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = 'billing_seat_change_requests'"
        );
        $stmt->execute();

        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('billing_seat_change_table_ready')) {
    function billing_seat_change_table_ready(PDO $pdo): bool
    {
        if (!billing_seat_change_table_exists($pdo)) {
            return false;
        }

        $stmt = $pdo->prepare(
            "SELECT column_name
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'billing_seat_change_requests'"
        );
        $stmt->execute();

        $columns = array_fill_keys(
            $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [],
            true
        );

        foreach (
            billing_seat_change_required_columns()
            as $column
        ) {
            if (!isset($columns[$column])) {
                return false;
            }
        }

        $engineStmt = $pdo->prepare(
            "SELECT engine
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = 'billing_seat_change_requests'
             LIMIT 1"
        );
        $engineStmt->execute();

        $engine = $engineStmt->fetchColumn();

        return is_string($engine)
            && strcasecmp($engine, 'InnoDB') === 0;
    }
}

if (!function_exists('billing_seat_change_foreign_keys_ready')) {
    function billing_seat_change_foreign_keys_ready(
        PDO $pdo
    ): bool {
        $expected = [
            'fk_billing_seat_change_farm' => [
                'billing_seat_change_requests',
                'farms',
                'CASCADE',
            ],
            'fk_billing_seat_change_payment_attempt' => [
                'billing_seat_change_requests',
                'billing_payment_attempts',
                'RESTRICT',
            ],
            'fk_billing_seat_change_latest_subscription' => [
                'billing_seat_change_requests',
                'subscriptions',
                'RESTRICT',
            ],
            'fk_billing_seat_change_latest_attempt' => [
                'billing_seat_change_requests',
                'billing_payment_attempts',
                'RESTRICT',
            ],
        ];

        $stmt = $pdo->prepare(
            "SELECT
                 table_name,
                 referenced_table_name,
                 delete_rule
             FROM information_schema.referential_constraints
             WHERE constraint_schema = DATABASE()
               AND constraint_name = ?
             LIMIT 1"
        );

        foreach ($expected as $name => $definition) {
            $stmt->execute([$name]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC)
                ?: null;

            if (!$row
                || (string)$row['table_name']
                    !== $definition[0]
                || (string)$row['referenced_table_name']
                    !== $definition[1]
                || strtoupper(
                    (string)$row['delete_rule']
                ) !== $definition[2]) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('billing_seat_change_migration_ready')) {
    function billing_seat_change_migration_ready(
        PDO $pdo
    ): bool {
        $stmt = $pdo->prepare(
            "SELECT COUNT(DISTINCT filename)
             FROM schema_migrations
             WHERE filename IN (?, ?)"
        );

        $stmt->execute([
            '045_billing_seat_change_foundation.sql',
            '046_billing_seat_quote_snapshot.sql',
        ]);

        return (int)$stmt->fetchColumn() === 2;
    }
}

if (!function_exists('billing_seat_change_ready')) {
    function billing_seat_change_ready(PDO $pdo): bool
    {
        return billing_payment_foundation_ready($pdo)
            && billing_seat_change_table_ready($pdo)
            && billing_seat_change_foreign_keys_ready($pdo)
            && billing_seat_change_migration_ready($pdo);
    }
}

if (!function_exists('billing_seat_change_kinds')) {
    function billing_seat_change_kinds(): array
    {
        return ['add', 'remove'];
    }
}

if (!function_exists('billing_seat_change_statuses')) {
    function billing_seat_change_statuses(): array
    {
        return [
            'awaiting_payment',
            'scheduled',
            'applied',
            'cancelled',
            'failed',
        ];
    }
}

if (!function_exists('billing_seat_change_normalize_kind')) {
    function billing_seat_change_normalize_kind(
        string $kind
    ): string {
        $kind = strtolower(trim($kind));

        if (!in_array(
            $kind,
            billing_seat_change_kinds(),
            true
        )) {
            throw new InvalidArgumentException(
                'Unsupported seat-change kind.'
            );
        }

        return $kind;
    }
}

if (!function_exists('billing_seat_change_normalize_status')) {
    function billing_seat_change_normalize_status(
        string $status
    ): string {
        $status = strtolower(trim($status));

        if (!in_array(
            $status,
            billing_seat_change_statuses(),
            true
        )) {
            throw new InvalidArgumentException(
                'Unsupported seat-change status.'
            );
        }

        return $status;
    }
}

if (!function_exists('billing_seat_change_normalize_role')) {
    function billing_seat_change_normalize_role(
        string $roleCode
    ): string {
        $roleCode = strtolower(trim($roleCode));

        if (!array_key_exists(
            $roleCode,
            subscription_seat_roles()
        )) {
            throw new InvalidArgumentException(
                'Unsupported extra-seat role.'
            );
        }

        return $roleCode;
    }
}

if (!function_exists('billing_seat_change_normalize_count')) {
    function billing_seat_change_normalize_count(
        $value
    ): int {
        $validated = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 0,
                    'max_range' => 500,
                ],
            ]
        );

        if ($validated === false) {
            throw new InvalidArgumentException(
                'Extra-seat count must be an integer between 0 and 500.'
            );
        }

        return (int)$validated;
    }
}

if (!function_exists('billing_seat_change_normalize_plan')) {
    function billing_seat_change_normalize_plan(
        string $planCode
    ): string {
        $planCode = strtolower(trim($planCode));

        if (!subscription_plan_is_valid($planCode)) {
            throw new InvalidArgumentException(
                'Unsupported subscription plan.'
            );
        }

        return $planCode;
    }
}

if (!function_exists('billing_seat_change_normalize_modules')) {
    function billing_seat_change_normalize_modules(
        array $modules
    ): array {
        return billing_payment_normalize_modules(
            $modules
        );
    }
}

if (!function_exists('billing_seat_change_normalize_datetime')) {
    function billing_seat_change_normalize_datetime(
        $value,
        bool $nullable = false
    ): ?string {
        if ($value === null
            || trim((string)$value) === '') {
            if ($nullable) {
                return null;
            }

            throw new InvalidArgumentException(
                'A commercial period date is required.'
            );
        }

        try {
            $date = new DateTimeImmutable(
                (string)$value,
                new DateTimeZone(
                    date_default_timezone_get()
                )
            );
        } catch (Throwable $e) {
            throw new InvalidArgumentException(
                'Commercial period date is invalid.'
            );
        }

        return $date->format('Y-m-d H:i:s');
    }
}

if (!function_exists('billing_seat_change_normalize_amount')) {
    function billing_seat_change_normalize_amount(
        $amount
    ): string {
        $minor = billing_pricing_decimal_to_minor(
            $amount,
            true
        );

        return billing_pricing_minor_to_decimal(
            $minor
        );
    }
}

if (!function_exists('billing_seat_change_optional_id')) {
    function billing_seat_change_optional_id(
        $value
    ): ?int {
        if ($value === null
            || trim((string)$value) === '') {
            return null;
        }

        $validated = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($validated === false) {
            throw new InvalidArgumentException(
                'Invalid audit identity.'
            );
        }

        return (int)$validated;
    }
}

if (!function_exists('billing_seat_change_optional_amount')) {
    function billing_seat_change_optional_amount(
        $value
    ): ?string {
        if ($value === null
            || trim((string)$value) === '') {
            return null;
        }

        return billing_seat_change_normalize_amount(
            $value
        );
    }
}

if (!function_exists('billing_seat_change_optional_nonnegative_int')) {
    function billing_seat_change_optional_nonnegative_int(
        $value
    ): ?int {
        if ($value === null
            || trim((string)$value) === '') {
            return null;
        }

        $validated = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0]]
        );

        if ($validated === false) {
            throw new InvalidArgumentException(
                'Invalid non-negative commercial audit value.'
            );
        }

        return (int)$validated;
    }
}

if (!function_exists('billing_seat_change_optional_pricing_version')) {
    function billing_seat_change_optional_pricing_version(
        $value
    ): ?string {
        if ($value === null
            || trim((string)$value) === '') {
            return null;
        }

        $value = trim((string)$value);

        if (strlen($value) > 80
            || !preg_match(
                '/^[A-Za-z0-9][A-Za-z0-9_.-]*$/',
                $value
            )) {
            throw new InvalidArgumentException(
                'Invalid seat-proration pricing version.'
            );
        }

        return $value;
    }
}

if (!function_exists('billing_seat_change_optional_hash')) {
    function billing_seat_change_optional_hash(
        $value
    ): ?string {
        if ($value === null
            || trim((string)$value) === '') {
            return null;
        }

        $value = strtolower(trim((string)$value));

        if (!preg_match('/^[a-f0-9]{64}$/', $value)) {
            throw new InvalidArgumentException(
                'Invalid seat-proration pricing hash.'
            );
        }

        return $value;
    }
}

if (!function_exists('billing_seat_change_build_contract')) {
    function billing_seat_change_build_contract(
        array $input
    ): array {
        $farmId = billing_seat_change_optional_id(
            $input['farm_id'] ?? null
        );

        if ($farmId === null) {
            throw new InvalidArgumentException(
                'A valid tenant farm is required.'
            );
        }

        $kind = billing_seat_change_normalize_kind(
            (string)($input['change_kind'] ?? '')
        );

        $roleCode = billing_seat_change_normalize_role(
            (string)($input['role_code'] ?? '')
        );

        $from = billing_seat_change_normalize_count(
            $input['from_extra_seats'] ?? null
        );

        $to = billing_seat_change_normalize_count(
            $input['to_extra_seats'] ?? null
        );

        $planCode = billing_seat_change_normalize_plan(
            (string)($input['plan_code'] ?? '')
        );

        $interval = billing_payment_normalize_interval(
            (string)($input['billing_interval'] ?? '')
        );

        $modulesInput = $input['modules'] ?? null;

        if (!is_array($modulesInput)) {
            throw new InvalidArgumentException(
                'A canonical livestock module snapshot is required.'
            );
        }

        $modules = billing_seat_change_normalize_modules(
            $modulesInput
        );

        if (!subscription_seat_role_relevant(
            $roleCode,
            $modules
        )) {
            throw new InvalidArgumentException(
                'The selected seat role is unavailable for this livestock subscription.'
            );
        }

        $amount = billing_seat_change_normalize_amount(
            $input['amount'] ?? null
        );

        $currency = billing_payment_normalize_currency(
            (string)($input['currency'] ?? '')
        );

        $periodEnd =
            billing_seat_change_normalize_datetime(
                $input['current_period_ends_at'] ?? null
            );

        $quotedAt =
            billing_seat_change_normalize_datetime(
                $input['quoted_at'] ?? null,
                true
            );

        $lineageStartAt =
            billing_seat_change_normalize_datetime(
                $input['lineage_start_at'] ?? null,
                true
            );

        $segmentStartAt =
            billing_seat_change_normalize_datetime(
                $input['segment_start_at'] ?? null,
                true
            );

        $segmentEndAt =
            billing_seat_change_normalize_datetime(
                $input['segment_end_at'] ?? null,
                true
            );

        $pricingVersion =
            billing_seat_change_optional_pricing_version(
                $input['pricing_version'] ?? null
            );

        $pricingHash =
            billing_seat_change_optional_hash(
                $input['pricing_hash'] ?? null
            );

        $unitAmount =
            billing_seat_change_optional_amount(
                $input['unit_amount'] ?? null
            );

        $partialUnitAmount =
            billing_seat_change_optional_amount(
                $input['partial_unit_amount'] ?? null
            );

        $futureFullPeriods =
            billing_seat_change_optional_nonnegative_int(
                $input['future_full_periods'] ?? null
            );

        $perSeatAmount =
            billing_seat_change_optional_amount(
                $input['per_seat_amount'] ?? null
            );

        $latestPaidSubscriptionId =
            billing_seat_change_optional_id(
                $input['latest_paid_subscription_id']
                    ?? null
            );

        $latestPaidAttemptId =
            billing_seat_change_optional_id(
                $input['latest_paid_attempt_id']
                    ?? null
            );

        $effectiveAt =
            billing_seat_change_normalize_datetime(
                $input['effective_at'] ?? null,
                true
            );

        $paymentAttemptId =
            billing_seat_change_optional_id(
                $input['payment_attempt_id'] ?? null
            );

        $initiatedBy =
            billing_seat_change_optional_id(
                $input['initiated_by_user_id'] ?? null
            );

        $amountMinor =
            billing_pricing_decimal_to_minor(
                $amount,
                true
            );

        if ($kind === 'add') {
            if ($to <= $from) {
                throw new InvalidArgumentException(
                    'A seat-add request must increase extra seats.'
                );
            }

            if ($amountMinor < 1) {
                throw new InvalidArgumentException(
                    'A seat-add request requires a positive prorated payment amount.'
                );
            }

            if ($paymentAttemptId === null) {
                throw new InvalidArgumentException(
                    'A seat-add request requires a seat-top-up payment attempt.'
                );
            }

            if ($effectiveAt !== null) {
                throw new InvalidArgumentException(
                    'A seat-add request becomes effective only after verified payment application.'
                );
            }

            $requiredSnapshot = [
                $quotedAt,
                $lineageStartAt,
                $segmentStartAt,
                $segmentEndAt,
                $pricingVersion,
                $pricingHash,
                $unitAmount,
                $partialUnitAmount,
                $futureFullPeriods,
                $perSeatAmount,
                $latestPaidSubscriptionId,
                $latestPaidAttemptId,
            ];

            foreach ($requiredSnapshot as $value) {
                if ($value === null) {
                    throw new InvalidArgumentException(
                        'A seat-add request requires a complete authoritative proration snapshot.'
                    );
                }
            }

            if ($latestPaidAttemptId === $paymentAttemptId) {
                throw new InvalidArgumentException(
                    'The new seat-top-up payment cannot be its own paid commercial lineage.'
                );
            }

            $timezone = new DateTimeZone(
                date_default_timezone_get()
            );

            $quoteDate =
                new DateTimeImmutable(
                    $quotedAt,
                    $timezone
                );

            $lineageDate =
                new DateTimeImmutable(
                    $lineageStartAt,
                    $timezone
                );

            $segmentStartDate =
                new DateTimeImmutable(
                    $segmentStartAt,
                    $timezone
                );

            $segmentEndDate =
                new DateTimeImmutable(
                    $segmentEndAt,
                    $timezone
                );

            $periodEndDate =
                new DateTimeImmutable(
                    $periodEnd,
                    $timezone
                );

            if ($lineageDate > $segmentStartDate
                || $quoteDate < $segmentStartDate
                || $quoteDate >= $segmentEndDate
                || $segmentStartDate >= $segmentEndDate
                || $segmentEndDate > $periodEndDate) {
                throw new InvalidArgumentException(
                    'Seat-proration commercial timeline is invalid.'
                );
            }

            $unitMinor =
                billing_pricing_decimal_to_minor(
                    $unitAmount
                );

            $calculated =
                billing_seat_proration_amount(
                    $quoteDate,
                    $segmentStartDate,
                    $segmentEndDate,
                    $unitMinor,
                    $futureFullPeriods,
                    $to - $from
                );

            $calculatedPartial =
                billing_pricing_minor_to_decimal(
                    $calculated['partial_unit_minor']
                );

            $calculatedPerSeat =
                billing_pricing_minor_to_decimal(
                    $calculated['per_seat_minor']
                );

            $calculatedTotal =
                billing_pricing_minor_to_decimal(
                    $calculated['total_minor']
                );

            if (!hash_equals(
                    $calculatedPartial,
                    $partialUnitAmount
                )
                || !hash_equals(
                    $calculatedPerSeat,
                    $perSeatAmount
                )
                || !hash_equals(
                    $calculatedTotal,
                    $amount
                )) {
                throw new InvalidArgumentException(
                    'Seat-proration monetary snapshot is internally inconsistent.'
                );
            }
        } else {
            if ($to >= $from) {
                throw new InvalidArgumentException(
                    'A seat-removal request must reduce extra seats.'
                );
            }

            if ($amountMinor !== 0) {
                throw new InvalidArgumentException(
                    'A scheduled seat removal cannot create a refund or charge.'
                );
            }

            if ($paymentAttemptId !== null) {
                throw new InvalidArgumentException(
                    'A scheduled seat removal cannot have a payment attempt.'
                );
            }

            if ($effectiveAt === null
                || $effectiveAt !== $periodEnd) {
                throw new InvalidArgumentException(
                    'A seat removal must take effect at the current paid period end.'
                );
            }

            $removalSnapshot = [
                $quotedAt,
                $lineageStartAt,
                $segmentStartAt,
                $segmentEndAt,
                $pricingVersion,
                $pricingHash,
                $unitAmount,
                $partialUnitAmount,
                $futureFullPeriods,
                $perSeatAmount,
                $latestPaidSubscriptionId,
                $latestPaidAttemptId,
            ];

            foreach ($removalSnapshot as $value) {
                if ($value !== null) {
                    throw new InvalidArgumentException(
                        'A no-refund seat removal cannot carry a paid-proration snapshot.'
                    );
                }
            }
        }

        return [
            'farm_id' => $farmId,
            'change_kind' => $kind,
            'role_code' => $roleCode,
            'from_extra_seats' => $from,
            'to_extra_seats' => $to,
            'plan_code' => $planCode,
            'billing_interval' => $interval,
            'modules' => $modules,
            'amount' => $amount,
            'currency' => $currency,
            'current_period_ends_at' => $periodEnd,
            'quoted_at' => $quotedAt,
            'lineage_start_at' => $lineageStartAt,
            'segment_start_at' => $segmentStartAt,
            'segment_end_at' => $segmentEndAt,
            'pricing_version' => $pricingVersion,
            'pricing_hash' => $pricingHash,
            'unit_amount' => $unitAmount,
            'partial_unit_amount' => $partialUnitAmount,
            'future_full_periods' => $futureFullPeriods,
            'per_seat_amount' => $perSeatAmount,
            'latest_paid_subscription_id' =>
                $latestPaidSubscriptionId,
            'latest_paid_attempt_id' =>
                $latestPaidAttemptId,
            'effective_at' => $effectiveAt,
            'payment_attempt_id' => $paymentAttemptId,
            'initiated_by_user_id' => $initiatedBy,
        ];
    }
}

if (!function_exists('billing_seat_change_request_hash')) {
    function billing_seat_change_request_hash(
        array $contract
    ): string {
        $json = json_encode(
            $contract,
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
        );

        if ($json === false) {
            throw new RuntimeException(
                'Unable to encode immutable seat-change request facts.'
            );
        }

        return hash('sha256', $json);
    }
}

if (!function_exists('billing_seat_change_initial_status')) {
    function billing_seat_change_initial_status(
        string $kind
    ): string {
        $kind = billing_seat_change_normalize_kind(
            $kind
        );

        return $kind === 'add'
            ? 'awaiting_payment'
            : 'scheduled';
    }
}

if (!function_exists('billing_seat_change_status_allowed')) {
    function billing_seat_change_status_allowed(
        string $kind,
        string $status
    ): bool {
        $kind = billing_seat_change_normalize_kind(
            $kind
        );

        $status =
            billing_seat_change_normalize_status(
                $status
            );

        if ($kind === 'add') {
            return in_array(
                $status,
                [
                    'awaiting_payment',
                    'applied',
                    'cancelled',
                    'failed',
                ],
                true
            );
        }

        return in_array(
            $status,
            [
                'scheduled',
                'applied',
                'cancelled',
                'failed',
            ],
            true
        );
    }
}

if (!function_exists('billing_seat_change_row_contract')) {
    function billing_seat_change_row_contract(
        array $row
    ): array {
        $modules = json_decode(
            (string)($row['modules_snapshot'] ?? ''),
            true
        );

        if (!is_array($modules)) {
            throw new RuntimeException(
                'Stored seat-change module snapshot is invalid.'
            );
        }

        $contract =
            billing_seat_change_build_contract([
                'farm_id' =>
                    $row['farm_id'] ?? null,
                'change_kind' =>
                    $row['change_kind'] ?? '',
                'role_code' =>
                    $row['role_code'] ?? '',
                'from_extra_seats' =>
                    $row['from_extra_seats'] ?? null,
                'to_extra_seats' =>
                    $row['to_extra_seats'] ?? null,
                'plan_code' =>
                    $row['plan_code'] ?? '',
                'billing_interval' =>
                    $row['billing_interval'] ?? '',
                'modules' => $modules,
                'amount' =>
                    $row['amount'] ?? null,
                'currency' =>
                    $row['currency'] ?? '',
                'current_period_ends_at' =>
                    $row['current_period_ends_at']
                        ?? null,
                'quoted_at' =>
                    $row['quoted_at'] ?? null,
                'lineage_start_at' =>
                    $row['lineage_start_at'] ?? null,
                'segment_start_at' =>
                    $row['segment_start_at'] ?? null,
                'segment_end_at' =>
                    $row['segment_end_at'] ?? null,
                'pricing_version' =>
                    $row['pricing_version'] ?? null,
                'pricing_hash' =>
                    $row['pricing_hash'] ?? null,
                'unit_amount' =>
                    $row['unit_amount'] ?? null,
                'partial_unit_amount' =>
                    $row['partial_unit_amount'] ?? null,
                'future_full_periods' =>
                    $row['future_full_periods'] ?? null,
                'per_seat_amount' =>
                    $row['per_seat_amount'] ?? null,
                'latest_paid_subscription_id' =>
                    $row['latest_paid_subscription_id']
                        ?? null,
                'latest_paid_attempt_id' =>
                    $row['latest_paid_attempt_id']
                        ?? null,
                'effective_at' =>
                    $row['effective_at'] ?? null,
                'payment_attempt_id' =>
                    $row['payment_attempt_id']
                        ?? null,
                'initiated_by_user_id' =>
                    $row['initiated_by_user_id']
                        ?? null,
            ]);

        $storedHash = strtolower(
            trim((string)($row['request_hash'] ?? ''))
        );

        $rebuiltHash =
            billing_seat_change_request_hash(
                $contract
            );

        if (!preg_match(
            '/^[a-f0-9]{64}$/',
            $storedHash
        )
            || !hash_equals(
                $storedHash,
                $rebuiltHash
            )) {
            throw new RuntimeException(
                'Stored seat-change request integrity check failed.'
            );
        }

        $status =
            billing_seat_change_normalize_status(
                (string)($row['status'] ?? '')
            );

        if (!billing_seat_change_status_allowed(
            $contract['change_kind'],
            $status
        )) {
            throw new RuntimeException(
                'Stored seat-change workflow state is invalid for its request kind.'
            );
        }

        return [
            'id' => (int)($row['id'] ?? 0),
            'status' => $status,
            'contract' => $contract,
            'request_hash' => $storedHash,
            'applied_at' =>
                $row['applied_at'] ?? null,
            'cancelled_at' =>
                $row['cancelled_at'] ?? null,
        ];
    }
}

if (!function_exists('billing_seat_change_request_by_id')) {
    function billing_seat_change_request_by_id(
        PDO $pdo,
        int $requestId,
        bool $forUpdate = false
    ): ?array {
        if ($requestId < 1) {
            return null;
        }

        $sql =
            'SELECT *
             FROM billing_seat_change_requests
             WHERE id = ?
             LIMIT 1';

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$requestId]);

        return $stmt->fetch(PDO::FETCH_ASSOC)
            ?: null;
    }
}

if (!function_exists('billing_seat_change_request_by_payment')) {
    function billing_seat_change_request_by_payment(
        PDO $pdo,
        int $paymentAttemptId,
        bool $forUpdate = false
    ): ?array {
        if ($paymentAttemptId < 1) {
            return null;
        }

        $sql =
            'SELECT *
             FROM billing_seat_change_requests
             WHERE payment_attempt_id = ?
             LIMIT 1';

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$paymentAttemptId]);

        return $stmt->fetch(PDO::FETCH_ASSOC)
            ?: null;
    }
}


if (!function_exists('billing_seat_change_mark_payment_failed')) {
    function billing_seat_change_mark_payment_failed(
        PDO $pdo,
        int $paymentAttemptId
    ): array {
        if ($paymentAttemptId < 1) {
            throw new InvalidArgumentException(
                'A valid seat-top-up payment attempt is required.'
            );
        }

        if (!$pdo->inTransaction()) {
            throw new RuntimeException(
                'Seat-change payment failure transition requires an active database transaction.'
            );
        }

        // Preserve the same lock order used by paid application:
        // payment attempt first, then durable seat-change request.
        $attempt =
            billing_audit_attempt_by_id(
                $pdo,
                $paymentAttemptId,
                true
            );

        if (!$attempt) {
            throw new RuntimeException(
                'Failed seat-top-up payment attempt could not be found.'
            );
        }

        if (billing_payment_attempt_purpose(
            $attempt
        ) !== 'seat_topup') {
            throw new RuntimeException(
                'Only a seat_topup payment can fail an awaiting seat-change request.'
            );
        }

        if (strtolower(trim(
            (string)($attempt['status'] ?? '')
        )) !== 'failed') {
            throw new RuntimeException(
                'Seat-change request can be failed only after its payment attempt is failed.'
            );
        }

        $requestRow =
            billing_seat_change_request_by_payment(
                $pdo,
                $paymentAttemptId,
                true
            );

        if (!$requestRow) {
            throw new RuntimeException(
                'Failed seat-top-up payment has no durable seat-change request.'
            );
        }

        $requestState =
            billing_seat_change_row_contract(
                $requestRow
            );

        $contract = $requestState['contract'];

        if (($contract['change_kind'] ?? '')
                !== 'add'
            || (int)(
                $contract['payment_attempt_id']
                    ?? 0
            ) !== $paymentAttemptId
            || (int)(
                $contract['farm_id']
                    ?? 0
            ) !== (int)(
                $attempt['farm_id']
                    ?? 0
            )) {
            throw new RuntimeException(
                'Failed payment does not match the durable seat-add request.'
            );
        }

        if ($requestState['status'] === 'failed') {
            return [
                'changed' => false,
                'idempotent' => true,
                'request' => $requestState,
            ];
        }

        if ($requestState['status']
            !== 'awaiting_payment') {
            throw new RuntimeException(
                'Only an awaiting-payment seat-add request can be failed.'
            );
        }

        $update = $pdo->prepare(
            "UPDATE billing_seat_change_requests
             SET status = 'failed'
             WHERE id = ?
               AND status = 'awaiting_payment'"
        );

        $update->execute([
            (int)$requestState['id'],
        ]);

        if ($update->rowCount() !== 1) {
            throw new RuntimeException(
                'Seat-change request could not be failed exactly once.'
            );
        }

        $failedRow =
            billing_seat_change_request_by_id(
                $pdo,
                (int)$requestState['id'],
                false
            );

        if (!$failedRow) {
            throw new RuntimeException(
                'Failed seat-change request could not be reloaded.'
            );
        }

        $failedState =
            billing_seat_change_row_contract(
                $failedRow
            );

        if ($failedState['status'] !== 'failed') {
            throw new RuntimeException(
                'Failed seat-change request could not be verified.'
            );
        }

        return [
            'changed' => true,
            'idempotent' => false,
            'request' => $failedState,
        ];
    }
}


if (!function_exists('billing_seat_change_attempt_modules')) {
    function billing_seat_change_attempt_modules(
        $raw
    ): array {
        $decoded = json_decode(
            (string)$raw,
            true
        );

        if (!is_array($decoded)) {
            throw new RuntimeException(
                'Seat-top-up payment module snapshot is invalid.'
            );
        }

        $normalized =
            billing_payment_normalize_modules(
                $decoded
            );

        if ($decoded !== $normalized) {
            throw new RuntimeException(
                'Seat-top-up payment module snapshot is not canonical.'
            );
        }

        return $normalized;
    }
}

if (!function_exists('billing_seat_change_attempt_seats')) {
    function billing_seat_change_attempt_seats(
        $raw
    ): array {
        $decoded = json_decode(
            (string)$raw,
            true
        );

        if (!is_array($decoded)) {
            throw new RuntimeException(
                'Seat-top-up payment seat snapshot is invalid.'
            );
        }

        ksort($decoded, SORT_STRING);

        $normalized =
            billing_payment_normalize_seat_addons(
                $decoded
            );

        if ($decoded !== $normalized) {
            throw new RuntimeException(
                'Seat-top-up payment seat snapshot is not canonical.'
            );
        }

        return $normalized;
    }
}

if (!function_exists('billing_seat_change_current_context')) {
    function billing_seat_change_current_context(
        PDO $pdo,
        array $contract
    ): array {
        $current = billing_current_product(
            $pdo,
            $contract['farm_id'],
            ['active']
        );

        $currentPlan = strtolower(trim(
            (string)$current['plan_code']
        ));

        $currentModules =
            billing_payment_normalize_modules(
                is_array($current['modules'] ?? null)
                    ? $current['modules']
                    : []
            );

        $currentInterval =
            billing_payment_normalize_interval(
                (string)(
                    $current['pricing']['billing_interval']
                    ?? ''
                )
            );

        if ($currentPlan !== $contract['plan_code']
            || $currentModules !== $contract['modules']
            || $currentInterval
                !== $contract['billing_interval']) {
            throw new RuntimeException(
                'Seat-change request no longer matches the tenant current commercial product.'
            );
        }

        $latest =
            $current['latest_subscription'] ?? null;

        if (!is_array($latest)) {
            throw new RuntimeException(
                'Current commercial subscription history is unavailable.'
            );
        }

        $currentPeriodEnd =
            billing_seat_change_normalize_datetime(
                $latest['current_period_ends_at']
                    ?? null
            );

        if ($currentPeriodEnd
            !== $contract['current_period_ends_at']) {
            throw new RuntimeException(
                'Seat-change request no longer matches the tenant current paid period.'
            );
        }

        $currentSeats =
            subscription_seat_normalize_addons(
                is_array($current['seat_addons'] ?? null)
                    ? $current['seat_addons']
                    : []
            );

        $role = $contract['role_code'];

        if ((int)$currentSeats[$role]
            !== $contract['from_extra_seats']) {
            throw new RuntimeException(
                'Seat-change request is stale because the current extra-seat count has changed.'
            );
        }

        $targetSeats = $currentSeats;
        $targetSeats[$role] =
            $contract['to_extra_seats'];

        $targetSeats =
            subscription_seat_normalize_addons(
                $targetSeats
            );

        ksort($targetSeats, SORT_STRING);

        if ($contract['change_kind'] === 'remove') {
            subscription_seat_assert_capacity(
                $pdo,
                $contract['farm_id'],
                $contract['plan_code'],
                $contract['modules'],
                $targetSeats
            );
        }

        return [
            'current_product' => $current,
            'current_seat_addons' => $currentSeats,
            'target_seat_addons' => $targetSeats,
        ];
    }
}

if (!function_exists('billing_seat_change_assert_attempt')) {
    function billing_seat_change_assert_attempt(
        PDO $pdo,
        array $contract,
        array $targetSeatAddOns
    ): array {
        $stmt = $pdo->prepare(
            "SELECT *
             FROM billing_payment_attempts
             WHERE id = ?
               AND farm_id = ?
             LIMIT 1
             FOR UPDATE"
        );

        $stmt->execute([
            $contract['payment_attempt_id'],
            $contract['farm_id'],
        ]);

        $attempt =
            $stmt->fetch(PDO::FETCH_ASSOC)
            ?: null;

        if (!$attempt) {
            throw new RuntimeException(
                'Seat-top-up payment attempt could not be found for this tenant.'
            );
        }

        if (billing_payment_attempt_purpose(
            $attempt
        ) !== 'seat_topup') {
            throw new RuntimeException(
                'Seat-add request requires a seat_topup payment purpose.'
            );
        }

        $status = strtolower(trim(
            (string)($attempt['status'] ?? '')
        ));

        if ($status !== 'initialized') {
            throw new RuntimeException(
                'Seat-top-up payment must be bound to its request before provider processing begins.'
            );
        }

        $attemptPlan = strtolower(trim(
            (string)($attempt['plan_code'] ?? '')
        ));

        $attemptInterval =
            billing_payment_normalize_interval(
                (string)(
                    $attempt['billing_interval'] ?? ''
                )
            );

        $attemptAmount =
            billing_payment_normalize_amount(
                (string)($attempt['amount'] ?? '')
            );

        $attemptCurrency =
            billing_payment_normalize_currency(
                (string)($attempt['currency'] ?? '')
            );

        $attemptModules =
            billing_seat_change_attempt_modules(
                $attempt['modules_snapshot']
                    ?? null
            );

        $attemptSeats =
            billing_seat_change_attempt_seats(
                $attempt['seat_addons_snapshot']
                    ?? null
            );

        $rebuilt = billing_payment_build_quote(
            $attemptPlan,
            $attemptInterval,
            $attemptAmount,
            $attemptCurrency,
            $attemptModules,
            $attemptSeats
        );

        $storedQuoteHash = strtolower(trim(
            (string)($attempt['quote_hash'] ?? '')
        ));

        if (!preg_match(
            '/^[a-f0-9]{64}$/',
            $storedQuoteHash
        )
            || !hash_equals(
                $storedQuoteHash,
                (string)$rebuilt['quote_hash']
            )) {
            throw new RuntimeException(
                'Seat-top-up payment quote integrity check failed.'
            );
        }

        if ($attemptPlan
                !== $contract['plan_code']
            || $attemptInterval
                !== $contract['billing_interval']
            || !hash_equals(
                $attemptAmount,
                $contract['amount']
            )
            || !hash_equals(
                $attemptCurrency,
                $contract['currency']
            )
            || $attemptModules
                !== $contract['modules']
            || $attemptSeats
                !== $targetSeatAddOns) {
            throw new RuntimeException(
                'Seat-top-up payment does not match the immutable seat-change request.'
            );
        }

        $attemptActor =
            billing_seat_change_optional_id(
                $attempt['initiated_by_user_id']
                    ?? null
            );

        if ($attemptActor
            !== $contract['initiated_by_user_id']) {
            throw new RuntimeException(
                'Seat-top-up payment actor does not match the seat-change request.'
            );
        }

        return $attempt;
    }
}

if (!function_exists('billing_seat_change_assert_authoritative_proration')) {
    function billing_seat_change_assert_authoritative_proration(
        PDO $pdo,
        array $contract,
        array $targetSeatAddOns
    ): array {
        if ($contract['change_kind'] !== 'add') {
            throw new InvalidArgumentException(
                'Authoritative proration applies only to paid seat additions.'
            );
        }

        $timezone = new DateTimeZone(
            date_default_timezone_get()
        );

        $quoteNow = new DateTimeImmutable(
            $contract['quoted_at'],
            $timezone
        );

        $serverNow = new DateTimeImmutable(
            'now',
            $timezone
        );

        $ageSeconds =
            $serverNow->getTimestamp()
            - $quoteNow->getTimestamp();

        if ($ageSeconds < -30
            || $ageSeconds > 300) {
            throw new RuntimeException(
                'Seat-proration quote is stale or has a future timestamp.'
            );
        }

        $quantity =
            $contract['to_extra_seats']
            - $contract['from_extra_seats'];

        $quote = billing_seat_proration_quote(
            $pdo,
            $contract['farm_id'],
            $contract['role_code'],
            $quantity,
            $quoteNow
        );

        $expected = [
            'farm_id' =>
                $contract['farm_id'],
            'role_code' =>
                $contract['role_code'],
            'quantity' =>
                $quantity,
            'from_extra_seats' =>
                $contract['from_extra_seats'],
            'to_extra_seats' =>
                $contract['to_extra_seats'],
            'plan_code' =>
                $contract['plan_code'],
            'modules' =>
                $contract['modules'],
            'billing_interval' =>
                $contract['billing_interval'],
            'pricing_version' =>
                $contract['pricing_version'],
            'pricing_hash' =>
                $contract['pricing_hash'],
            'currency' =>
                $contract['currency'],
            'unit_amount' =>
                $contract['unit_amount'],
            'partial_unit_amount' =>
                $contract['partial_unit_amount'],
            'future_full_periods' =>
                $contract['future_full_periods'],
            'per_seat_amount' =>
                $contract['per_seat_amount'],
            'amount' =>
                $contract['amount'],
            'quoted_at' =>
                $contract['quoted_at'],
            'lineage_start' =>
                $contract['lineage_start_at'],
            'segment_start' =>
                $contract['segment_start_at'],
            'segment_end' =>
                $contract['segment_end_at'],
            'current_period_ends_at' =>
                $contract['current_period_ends_at'],
            'latest_paid_subscription_id' =>
                $contract['latest_paid_subscription_id'],
            'latest_paid_attempt_id' =>
                $contract['latest_paid_attempt_id'],
        ];

        $actual = [
            'farm_id' =>
                (int)($quote['farm_id'] ?? 0),
            'role_code' =>
                (string)($quote['role_code'] ?? ''),
            'quantity' =>
                (int)($quote['quantity'] ?? 0),
            'from_extra_seats' =>
                (int)($quote['from_extra_seats'] ?? -1),
            'to_extra_seats' =>
                (int)($quote['to_extra_seats'] ?? -1),
            'plan_code' =>
                (string)($quote['plan_code'] ?? ''),
            'modules' =>
                $quote['modules'] ?? null,
            'billing_interval' =>
                (string)(
                    $quote['billing_interval']
                    ?? ''
                ),
            'pricing_version' =>
                (string)(
                    $quote['pricing_version']
                    ?? ''
                ),
            'pricing_hash' =>
                strtolower((string)(
                    $quote['pricing_hash']
                    ?? ''
                )),
            'currency' =>
                (string)($quote['currency'] ?? ''),
            'unit_amount' =>
                (string)($quote['unit_amount'] ?? ''),
            'partial_unit_amount' =>
                (string)(
                    $quote['partial_unit_amount']
                    ?? ''
                ),
            'future_full_periods' =>
                isset($quote['future_full_periods'])
                    ? (int)$quote['future_full_periods']
                    : null,
            'per_seat_amount' =>
                (string)(
                    $quote['per_seat_amount']
                    ?? ''
                ),
            'amount' =>
                (string)($quote['amount'] ?? ''),
            'quoted_at' =>
                (string)($quote['quoted_at'] ?? ''),
            'lineage_start' =>
                (string)(
                    $quote['lineage_start']
                    ?? ''
                ),
            'segment_start' =>
                (string)(
                    $quote['segment_start']
                    ?? ''
                ),
            'segment_end' =>
                (string)(
                    $quote['segment_end']
                    ?? ''
                ),
            'current_period_ends_at' =>
                (string)(
                    $quote['current_period_ends_at']
                    ?? ''
                ),
            'latest_paid_subscription_id' =>
                (int)(
                    $quote['latest_paid_subscription_id']
                    ?? 0
                ),
            'latest_paid_attempt_id' =>
                (int)(
                    $quote['latest_paid_attempt_id']
                    ?? 0
                ),
        ];

        if ($actual !== $expected) {
            throw new RuntimeException(
                'Seat-change request does not match the authoritative server proration quote.'
            );
        }

        $quotedTarget =
            subscription_seat_normalize_addons(
                is_array(
                    $quote['target_seat_addons']
                        ?? null
                )
                    ? $quote['target_seat_addons']
                    : []
            );

        $expectedTarget =
            subscription_seat_normalize_addons(
                $targetSeatAddOns
            );

        ksort($quotedTarget, SORT_STRING);
        ksort($expectedTarget, SORT_STRING);

        if ($quotedTarget !== $expectedTarget) {
            throw new RuntimeException(
                'Seat-proration target capacity snapshot does not match the durable request.'
            );
        }

        return $quote;
    }
}

if (!function_exists('billing_seat_change_request_insert')) {
    function billing_seat_change_request_insert(
        PDO $pdo,
        array $input
    ): array {
        if (!billing_seat_change_ready($pdo)) {
            throw new RuntimeException(
                'Seat-change request storage is not transactionally ready.'
            );
        }

        $contract =
            billing_seat_change_build_contract(
                $input
            );

        $startedTransaction =
            !$pdo->inTransaction();

        if ($startedTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $farmStmt = $pdo->prepare(
                "SELECT id
                 FROM farms
                 WHERE id = ?
                   AND slug <> 'owner'
                 LIMIT 1
                 FOR UPDATE"
            );

            $farmStmt->execute([
                $contract['farm_id'],
            ]);

            if (!$farmStmt->fetchColumn()) {
                throw new RuntimeException(
                    'Tenant farm could not be found for seat change.'
                );
            }

            $context =
                billing_seat_change_current_context(
                    $pdo,
                    $contract
                );

            $openStmt = $pdo->prepare(
                "SELECT id
                 FROM billing_seat_change_requests
                 WHERE farm_id = ?
                   AND role_code = ?
                   AND status IN (
                       'awaiting_payment',
                       'scheduled'
                   )
                 LIMIT 1"
            );

            $openStmt->execute([
                $contract['farm_id'],
                $contract['role_code'],
            ]);

            if ($openStmt->fetchColumn()) {
                throw new RuntimeException(
                    'Another pending seat change already exists for this role.'
                );
            }

            if ($contract['change_kind'] === 'add') {
                billing_seat_change_assert_authoritative_proration(
                    $pdo,
                    $contract,
                    $context['target_seat_addons']
                );

                billing_seat_change_assert_attempt(
                    $pdo,
                    $contract,
                    $context['target_seat_addons']
                );
            }

            $status =
                billing_seat_change_initial_status(
                    $contract['change_kind']
                );

            $requestHash =
                billing_seat_change_request_hash(
                    $contract
                );

            $modulesJson = json_encode(
                $contract['modules'],
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
            );

            if ($modulesJson === false) {
                throw new RuntimeException(
                    'Unable to encode seat-change module snapshot.'
                );
            }

            $stmt = $pdo->prepare(
                'INSERT INTO billing_seat_change_requests (
                    farm_id,
                    change_kind,
                    status,
                    role_code,
                    from_extra_seats,
                    to_extra_seats,
                    plan_code,
                    billing_interval,
                    modules_snapshot,
                    amount,
                    currency,
                    current_period_ends_at,
                    quoted_at,
                    lineage_start_at,
                    segment_start_at,
                    segment_end_at,
                    pricing_version,
                    pricing_hash,
                    unit_amount,
                    partial_unit_amount,
                    future_full_periods,
                    per_seat_amount,
                    latest_paid_subscription_id,
                    latest_paid_attempt_id,
                    effective_at,
                    payment_attempt_id,
                    initiated_by_user_id,
                    request_hash
                 ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?
                 )'
            );

            $stmt->execute([
                $contract['farm_id'],
                $contract['change_kind'],
                $status,
                $contract['role_code'],
                $contract['from_extra_seats'],
                $contract['to_extra_seats'],
                $contract['plan_code'],
                $contract['billing_interval'],
                $modulesJson,
                $contract['amount'],
                $contract['currency'],
                $contract['current_period_ends_at'],
                $contract['quoted_at'],
                $contract['lineage_start_at'],
                $contract['segment_start_at'],
                $contract['segment_end_at'],
                $contract['pricing_version'],
                $contract['pricing_hash'],
                $contract['unit_amount'],
                $contract['partial_unit_amount'],
                $contract['future_full_periods'],
                $contract['per_seat_amount'],
                $contract['latest_paid_subscription_id'],
                $contract['latest_paid_attempt_id'],
                $contract['effective_at'],
                $contract['payment_attempt_id'],
                $contract['initiated_by_user_id'],
                $requestHash,
            ]);

            $id = (int)$pdo->lastInsertId();

            if ($id < 1) {
                throw new RuntimeException(
                    'Unable to create durable seat-change request.'
                );
            }

            $row =
                billing_seat_change_request_by_id(
                    $pdo,
                    $id,
                    false
                );

            if (!$row) {
                throw new RuntimeException(
                    'Created seat-change request could not be reloaded.'
                );
            }

            $result =
                billing_seat_change_row_contract(
                    $row
                );

            if ($startedTransaction) {
                $pdo->commit();
            }

            return $result;
        } catch (Throwable $e) {
            if ($startedTransaction
                && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }
}
