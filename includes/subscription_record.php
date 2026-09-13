<?php
/**
 * Canonical V2.3 commercial subscription record service.
 *
 * Contract:
 * - farms remains the runtime/current subscription snapshot used by the app;
 * - subscriptions is the append-only commercial history table;
 * - farm_modules and farm_subscription_seat_addons remain the current entitlement
 *   and purchased-extra-seat sources;
 * - consecutive no-op captures do not create duplicate history rows;
 * - real plan/status/date/module/seat changes create a new immutable snapshot row;
 * - existing billing/provider metadata is carried forward until the billing phase
 *   starts writing those fields deliberately.
 */

if (!function_exists('subscription_record_required_columns')) {
    function subscription_record_required_columns(): array
    {
        return [
            'farm_id',
            'plan_code',
            'status',
            'billing_interval',
            'amount',
            'currency',
            'provider',
            'provider_subscription_id',
            'current_period_ends_at',
            'subscription_starts_at',
            'subscription_ends_at',
            'modules_snapshot',
            'seat_addons_snapshot',
            'change_reason',
            'recorded_by_user_id',
            'snapshot_hash',
            'created_at',
        ];
    }
}

if (!function_exists('subscription_record_table_exists')) {
    function subscription_record_table_exists(PDO $pdo): bool
    {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'subscriptions'"
        );
        $stmt->execute();
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('subscription_record_table_ready')) {
    function subscription_record_table_ready(PDO $pdo): bool
    {
        if (!subscription_record_table_exists($pdo)) return false;
        $stmt = $pdo->prepare(
            "SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'subscriptions'"
        );
        $stmt->execute();
        $columns = array_fill_keys($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [], true);
        foreach (subscription_record_required_columns() as $column) {
            if (!isset($columns[$column])) return false;
        }
        return true;
    }
}

if (!function_exists('subscription_record_commercial_modules')) {
    function subscription_record_commercial_modules(PDO $pdo, int $farmId): array
    {
        if ($farmId < 1) return [];
        $modules = function_exists('farm_entitlement_modules')
            ? farm_entitlement_modules($pdo, $farmId)
            : [];
        $modules = array_values(array_intersect(['poultry', 'ruminant'], $modules));
        sort($modules, SORT_STRING);
        return $modules;
    }
}

if (!function_exists('subscription_record_build_snapshot')) {
    function subscription_record_build_snapshot(PDO $pdo, int $farmId): array
    {
        if ($farmId < 1) throw new InvalidArgumentException('A valid farm is required for a subscription record.');

        $stmt = $pdo->prepare(
            "SELECT id, name, slug, subscription_plan, subscription_status,
                    subscription_starts_at, subscription_ends_at
             FROM farms
             WHERE id = ? AND slug <> 'owner'
             LIMIT 1"
        );
        $stmt->execute([$farmId]);
        $farm = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$farm) throw new RuntimeException('Tenant farm could not be found for subscription recording.');

        $planCode = strtolower(trim((string)($farm['subscription_plan'] ?? 'starter')));
        $modules = subscription_record_commercial_modules($pdo, $farmId);
        $seatAddOns = function_exists('subscription_seat_load_addons')
            ? subscription_seat_load_addons($pdo, $farmId, $planCode, $modules)
            : ['poultry_manager' => 0, 'ruminant_manager' => 0, 'sales_rep' => 0, 'viewer' => 0];
        if (function_exists('subscription_seat_normalize_addons')) {
            $seatAddOns = subscription_seat_normalize_addons($seatAddOns);
        }
        ksort($seatAddOns, SORT_STRING);

        $snapshot = [
            'plan_code' => $planCode,
            'status' => strtolower(trim((string)($farm['subscription_status'] ?? 'trial'))),
            'subscription_starts_at' => $farm['subscription_starts_at'] ?: null,
            'subscription_ends_at' => $farm['subscription_ends_at'] ?: null,
            'modules' => $modules,
            'seat_addons' => $seatAddOns,
        ];
        $canonical = json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($canonical === false) throw new RuntimeException('Unable to encode the commercial subscription snapshot.');

        return [
            'farm' => $farm,
            'snapshot' => $snapshot,
            'snapshot_json' => $canonical,
            'snapshot_hash' => hash('sha256', $canonical),
        ];
    }
}

if (!function_exists('subscription_record_latest')) {
    function subscription_record_latest(PDO $pdo, int $farmId): ?array
    {
        if ($farmId < 1 || !subscription_record_table_exists($pdo)) return null;
        $stmt = $pdo->prepare('SELECT * FROM subscriptions WHERE farm_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$farmId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}


if (!function_exists('subscription_record_normalize_reason')) {
    function subscription_record_normalize_reason(
        string $reason
    ): string {
        $reason =
            strtolower(
                trim($reason)
            );

        $reason =
            preg_replace(
                '/[^a-z0-9_.-]+/',
                '_',
                $reason
            )
            ?: 'subscription_change';

        return substr(
            $reason,
            0,
            80
        );
    }
}

if (!function_exists('subscription_record_normalize_actor')) {
    function subscription_record_normalize_actor(
        ?int $recordedByUserId
    ): ?int {
        return (
            $recordedByUserId !== null
            && $recordedByUserId > 0
        )
            ? $recordedByUserId
            : null;
    }
}

if (!function_exists(
    'subscription_record_history_source_contract'
)) {
    /**
     * Load one explicit immutable subscription-history row and
     * prove that its stored commercial snapshot and billing metadata
     * are internally self-consistent.
     *
     * No runtime state is read or changed here.
     */
    function subscription_record_history_source_contract(
        PDO $pdo,
        int $farmId,
        int $sourceRecordId
    ): array {
        if ($farmId < 1 || $sourceRecordId < 1) {
            throw new InvalidArgumentException(
                'A valid tenant and subscription history source are required.'
            );
        }

        if (!subscription_record_table_ready($pdo)) {
            throw new RuntimeException(
                'Commercial subscription record storage is not ready.'
            );
        }

        $stmt =
            $pdo->prepare(
                "SELECT *
                 FROM subscriptions
                 WHERE id = ?
                   AND farm_id = ?
                 LIMIT 1"
            );

        $stmt->execute([
            $sourceRecordId,
            $farmId,
        ]);

        $source =
            $stmt->fetch(PDO::FETCH_ASSOC)
            ?: null;

        if (!is_array($source)) {
            throw new RuntimeException(
                'Explicit subscription history source could not be found for this tenant.'
            );
        }

        $planCode =
            strtolower(
                trim(
                    (string)(
                        $source['plan_code']
                        ?? ''
                    )
                )
            );

        $status =
            strtolower(
                trim(
                    (string)(
                        $source['status']
                        ?? ''
                    )
                )
            );

        if ($planCode === '' || $status === '') {
            throw new RuntimeException(
                'Subscription history source has invalid commercial identity.'
            );
        }

        $modules =
            json_decode(
                (string)(
                    $source['modules_snapshot']
                    ?? ''
                ),
                true
            );

        if (!is_array($modules)) {
            throw new RuntimeException(
                'Subscription history source has invalid module snapshot.'
            );
        }

        $modules =
            array_values(
                array_intersect(
                    [
                        'poultry',
                        'ruminant',
                    ],
                    array_map(
                        static fn($value): string =>
                            strtolower(
                                trim(
                                    (string)$value
                                )
                            ),
                        $modules
                    )
                )
            );

        sort(
            $modules,
            SORT_STRING
        );

        $seatAddons =
            json_decode(
                (string)(
                    $source[
                        'seat_addons_snapshot'
                    ] ?? ''
                ),
                true
            );

        if (!is_array($seatAddons)) {
            throw new RuntimeException(
                'Subscription history source has invalid seat-add-on snapshot.'
            );
        }

        if (!function_exists(
            'subscription_seat_normalize_addons'
        )) {
            throw new RuntimeException(
                'Shared subscription seat policy is required for historical source validation.'
            );
        }

        $seatAddons =
            subscription_seat_normalize_addons(
                $seatAddons
            );

        ksort(
            $seatAddons,
            SORT_STRING
        );

        $snapshot = [
            'plan_code' =>
                $planCode,
            'status' =>
                $status,
            'subscription_starts_at' =>
                (
                    $source[
                        'subscription_starts_at'
                    ] ?? null
                )
                ?: null,
            'subscription_ends_at' =>
                (
                    $source[
                        'subscription_ends_at'
                    ] ?? null
                )
                ?: null,
            'modules' =>
                $modules,
            'seat_addons' =>
                $seatAddons,
        ];

        $snapshotJson =
            json_encode(
                $snapshot,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            );

        if ($snapshotJson === false) {
            throw new RuntimeException(
                'Unable to encode subscription history source snapshot.'
            );
        }

        $snapshotHash =
            hash(
                'sha256',
                $snapshotJson
            );

        $storedHash =
            strtolower(
                trim(
                    (string)(
                        $source['snapshot_hash']
                        ?? ''
                    )
                )
            );

        if (
            preg_match(
                '/^[a-f0-9]{64}$/',
                $storedHash
            ) !== 1
            || !hash_equals(
                $storedHash,
                $snapshotHash
            )
        ) {
            throw new RuntimeException(
                'Subscription history source snapshot hash is invalid.'
            );
        }

        $billingInterval =
            strtolower(
                trim(
                    (string)(
                        $source['billing_interval']
                        ?? ''
                    )
                )
            );

        if (!in_array(
            $billingInterval,
            [
                'monthly',
                'annual',
            ],
            true
        )) {
            throw new RuntimeException(
                'Subscription history source has invalid billing interval.'
            );
        }

        $amountRaw =
            trim(
                (string)(
                    $source['amount']
                    ?? ''
                )
            );

        if (
            preg_match(
                '/^[0-9]+(?:\.[0-9]{1,2})?$/',
                $amountRaw
            ) !== 1
            || (float)$amountRaw < 0
        ) {
            throw new RuntimeException(
                'Subscription history source has invalid amount.'
            );
        }

        $amount =
            number_format(
                (float)$amountRaw,
                2,
                '.',
                ''
            );

        $currency =
            strtoupper(
                trim(
                    (string)(
                        $source['currency']
                        ?? ''
                    )
                )
            );

        if (
            preg_match(
                '/^[A-Z]{3}$/',
                $currency
            ) !== 1
        ) {
            throw new RuntimeException(
                'Subscription history source has invalid currency.'
            );
        }

        $provider =
            trim(
                (string)(
                    $source['provider']
                    ?? ''
                )
            );

        $provider =
            $provider !== ''
                ? $provider
                : null;

        $providerSubscriptionId =
            trim(
                (string)(
                    $source[
                        'provider_subscription_id'
                    ] ?? ''
                )
            );

        $providerSubscriptionId =
            $providerSubscriptionId !== ''
                ? $providerSubscriptionId
                : null;

        $currentPeriodEndsAt =
            $source[
                'current_period_ends_at'
            ] ?? null;

        $currentPeriodEndsAt =
            (
                $currentPeriodEndsAt !== null
                && trim(
                    (string)$currentPeriodEndsAt
                ) !== ''
            )
                ? (string)$currentPeriodEndsAt
                : null;

        return [
            'id' =>
                (int)$source['id'],
            'farm_id' =>
                (int)$source['farm_id'],
            'snapshot' =>
                $snapshot,
            'snapshot_json' =>
                $snapshotJson,
            'snapshot_hash' =>
                $snapshotHash,
            'billing_interval' =>
                $billingInterval,
            'amount' =>
                $amount,
            'currency' =>
                $currency,
            'provider' =>
                $provider,
            'provider_subscription_id' =>
                $providerSubscriptionId,
            'current_period_ends_at' =>
                $currentPeriodEndsAt,
        ];
    }
}

if (!function_exists(
    'subscription_record_append_from_history_source'
)) {
    /**
     * Append one new immutable commercial-history row after a caller
     * has restored current tenant runtime state to an explicit
     * historical source snapshot.
     *
     * Billing/provider metadata comes only from sourceRecordId.
     * The latest history row is deliberately not used as a metadata
     * source.
     *
     * This helper performs no entitlement restoration itself.
     */
    function subscription_record_append_from_history_source(
        PDO $pdo,
        int $farmId,
        int $sourceRecordId,
        string $reason,
        ?int $recordedByUserId = null
    ): array {
        if (!$pdo->inTransaction()) {
            throw new RuntimeException(
                'Compensating subscription history append requires an active database transaction.'
            );
        }

        $source =
            subscription_record_history_source_contract(
                $pdo,
                $farmId,
                $sourceRecordId
            );

        $built =
            subscription_record_build_snapshot(
                $pdo,
                $farmId
            );

        $runtimeHash =
            (string)(
                $built['snapshot_hash']
                ?? ''
            );

        if (
            $runtimeHash === ''
            || !hash_equals(
                (string)$source[
                    'snapshot_hash'
                ],
                $runtimeHash
            )
        ) {
            throw new RuntimeException(
                'Current tenant commercial state does not exactly match the chosen historical compensation source.'
            );
        }

        $snapshot =
            $built['snapshot'];

        $reason =
            subscription_record_normalize_reason(
                $reason
            );

        $recordedByUserId =
            subscription_record_normalize_actor(
                $recordedByUserId
            );

        $stmt =
            $pdo->prepare(
                'INSERT INTO subscriptions (
                    farm_id,
                    plan_code,
                    status,
                    billing_interval,
                    amount,
                    currency,
                    provider,
                    provider_subscription_id,
                    current_period_ends_at,
                    subscription_starts_at,
                    subscription_ends_at,
                    modules_snapshot,
                    seat_addons_snapshot,
                    change_reason,
                    recorded_by_user_id,
                    snapshot_hash
                 ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?
                 )'
            );

        $stmt->execute([
            $farmId,
            $snapshot['plan_code'],
            $snapshot['status'],
            $source['billing_interval'],
            $source['amount'],
            $source['currency'],
            $source['provider'],
            $source[
                'provider_subscription_id'
            ],
            $source[
                'current_period_ends_at'
            ],
            $snapshot[
                'subscription_starts_at'
            ],
            $snapshot[
                'subscription_ends_at'
            ],
            json_encode(
                $snapshot['modules'],
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            ),
            json_encode(
                $snapshot['seat_addons'],
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            ),
            $reason,
            $recordedByUserId,
            $runtimeHash,
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException(
                'Compensating commercial-history row could not be appended exactly once.'
            );
        }

        $insertedId =
            (int)$pdo->lastInsertId();

        if ($insertedId < 1) {
            throw new RuntimeException(
                'Compensating commercial-history row has no valid identity.'
            );
        }

        $reload =
            $pdo->prepare(
                "SELECT *
                 FROM subscriptions
                 WHERE id = ?
                   AND farm_id = ?
                 LIMIT 1"
            );

        $reload->execute([
            $insertedId,
            $farmId,
        ]);

        $inserted =
            $reload->fetch(PDO::FETCH_ASSOC)
            ?: null;

        if (!is_array($inserted)) {
            throw new RuntimeException(
                'Compensating commercial-history row could not be reloaded.'
            );
        }

        $insertedProvider =
            trim(
                (string)(
                    $inserted['provider']
                    ?? ''
                )
            );

        $insertedProvider =
            $insertedProvider !== ''
                ? $insertedProvider
                : null;

        $insertedProviderSubscriptionId =
            trim(
                (string)(
                    $inserted[
                        'provider_subscription_id'
                    ] ?? ''
                )
            );

        $insertedProviderSubscriptionId =
            $insertedProviderSubscriptionId !== ''
                ? $insertedProviderSubscriptionId
                : null;

        $insertedCurrentPeriodEndsAt =
            $inserted[
                'current_period_ends_at'
            ] ?? null;

        $insertedCurrentPeriodEndsAt =
            (
                $insertedCurrentPeriodEndsAt
                    !== null
                && trim(
                    (string)$insertedCurrentPeriodEndsAt
                ) !== ''
            )
                ? (string)$insertedCurrentPeriodEndsAt
                : null;

        $auditOk =
            (int)$inserted['id']
                === $insertedId
            && (int)$inserted['farm_id']
                === $farmId
            && hash_equals(
                $runtimeHash,
                strtolower(
                    trim(
                        (string)(
                            $inserted[
                                'snapshot_hash'
                            ] ?? ''
                        )
                    )
                )
            )
            && (string)$inserted[
                'billing_interval'
            ] === $source[
                'billing_interval'
            ]
            && (string)$inserted['amount']
                === $source['amount']
            && strtoupper(
                (string)$inserted['currency']
            ) === $source['currency']
            && $insertedProvider
                === $source['provider']
            && $insertedProviderSubscriptionId
                === $source[
                    'provider_subscription_id'
                ]
            && $insertedCurrentPeriodEndsAt
                === $source[
                    'current_period_ends_at'
                ]
            && (string)$inserted[
                'change_reason'
            ] === $reason
            && (
                $recordedByUserId === null
                    ? $inserted[
                        'recorded_by_user_id'
                    ] === null
                    : (int)$inserted[
                        'recorded_by_user_id'
                    ] === $recordedByUserId
            );

        if (!$auditOk) {
            throw new RuntimeException(
                'Compensating commercial-history row failed its post-insert audit contract.'
            );
        }

        return [
            'inserted' => true,
            'id' =>
                $insertedId,
            'source_record_id' =>
                $sourceRecordId,
            'snapshot_hash' =>
                $runtimeHash,
            'snapshot' =>
                $snapshot,
            'billing_metadata' => [
                'billing_interval' =>
                    $source[
                        'billing_interval'
                    ],
                'amount' =>
                    $source['amount'],
                'currency' =>
                    $source['currency'],
                'provider' =>
                    $source['provider'],
                'provider_subscription_id' =>
                    $source[
                        'provider_subscription_id'
                    ],
                'current_period_ends_at' =>
                    $source[
                        'current_period_ends_at'
                    ],
            ],
        ];
    }
}

if (!function_exists('subscription_record_capture')) {
    function subscription_record_capture(
        PDO $pdo,
        int $farmId,
        string $reason = 'platform_owner_update',
        ?int $recordedByUserId = null,
        ?array $billingMetadata = null
    ): array {
        if (!subscription_record_table_ready($pdo)) {
            throw new RuntimeException(
                'Commercial subscription record storage is not installed. Apply migration 041_commercial_subscription_records.sql first.'
            );
        }

        $built = subscription_record_build_snapshot($pdo, $farmId);
        $snapshot = $built['snapshot'];
        $hash = (string)$built['snapshot_hash'];
        $latest = subscription_record_latest($pdo, $farmId);

        if ($latest && hash_equals((string)($latest['snapshot_hash'] ?? ''), $hash)) {
            return [
                'inserted' => false,
                'id' => (int)$latest['id'],
                'snapshot_hash' => $hash,
                'snapshot' => $snapshot,
            ];
        }

        $billingMetadata =
            $billingMetadata ?? [];

        $billingIntervalExplicit =
            array_key_exists(
                'billing_interval',
                $billingMetadata
            );

        $billingInterval = strtolower(trim(
            (string)(
                $billingIntervalExplicit
                    ? $billingMetadata[
                        'billing_interval'
                    ]
                    : (
                        $latest[
                            'billing_interval'
                        ] ?? 'monthly'
                    )
            )
        ));

        if (!in_array(
            $billingInterval,
            ['monthly', 'annual'],
            true
        )) {
            if ($billingIntervalExplicit) {
                throw new InvalidArgumentException(
                    'Explicit commercial-history billing interval is invalid.'
                );
            }

            $billingInterval = 'monthly';
        }

        $amountExplicit =
            array_key_exists(
                'amount',
                $billingMetadata
            );

        $amountRaw =
            $amountExplicit
                ? $billingMetadata['amount']
                : ($latest['amount'] ?? null);

        if (is_numeric($amountRaw)
            && (float)$amountRaw >= 0) {
            $amount = number_format(
                (float)$amountRaw,
                2,
                '.',
                ''
            );
        } elseif ($amountExplicit) {
            throw new InvalidArgumentException(
                'Explicit commercial-history amount is invalid.'
            );
        } else {
            $amount = '0.00';
        }

        $currencyExplicit =
            array_key_exists(
                'currency',
                $billingMetadata
            );

        $currency = strtoupper(trim(
            (string)(
                $currencyExplicit
                    ? $billingMetadata['currency']
                    : ($latest['currency'] ?? 'USD')
            )
        ));

        if (!preg_match(
            '/^[A-Z]{3}$/',
            $currency
        )) {
            if ($currencyExplicit) {
                throw new InvalidArgumentException(
                    'Explicit commercial-history currency is invalid.'
                );
            }

            $currency = 'USD';
        }

        $providerSource =
            array_key_exists(
                'provider',
                $billingMetadata
            )
                ? $billingMetadata['provider']
                : ($latest['provider'] ?? null);

        $provider =
            trim(
                (string)(
                    $providerSource ?? ''
                )
            );

        $provider =
            $provider !== ''
                ? $provider
                : null;

        $providerSubscriptionSource =
            array_key_exists(
                'provider_subscription_id',
                $billingMetadata
            )
                ? $billingMetadata[
                    'provider_subscription_id'
                ]
                : (
                    $latest[
                        'provider_subscription_id'
                    ] ?? null
                );

        $providerSubscriptionId =
            trim(
                (string)(
                    $providerSubscriptionSource
                    ?? ''
                )
            );

        $providerSubscriptionId =
            $providerSubscriptionId !== ''
                ? $providerSubscriptionId
                : null;

        $currentPeriodEndsAt =
            array_key_exists(
                'current_period_ends_at',
                $billingMetadata
            )
                ? $billingMetadata[
                    'current_period_ends_at'
                ]
                : (
                    $latest[
                        'current_period_ends_at'
                    ] ?? null
                );

        $reason =
            subscription_record_normalize_reason(
                $reason
            );

        $recordedByUserId =
            subscription_record_normalize_actor(
                $recordedByUserId
            );

        $stmt = $pdo->prepare(
            'INSERT INTO subscriptions (
                farm_id, plan_code, status, billing_interval, amount, currency,
                provider, provider_subscription_id, current_period_ends_at,
                subscription_starts_at, subscription_ends_at,
                modules_snapshot, seat_addons_snapshot,
                change_reason, recorded_by_user_id, snapshot_hash
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $farmId,
            $snapshot['plan_code'],
            $snapshot['status'],
            $billingInterval,
            $amount,
            $currency,
            $provider,
            $providerSubscriptionId,
            $currentPeriodEndsAt,
            $snapshot['subscription_starts_at'],
            $snapshot['subscription_ends_at'],
            json_encode($snapshot['modules'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            json_encode($snapshot['seat_addons'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $reason,
            $recordedByUserId,
            $hash,
        ]);

        return [
            'inserted' => true,
            'id' => (int)$pdo->lastInsertId(),
            'snapshot_hash' => $hash,
            'snapshot' => $snapshot,
            'billing_metadata' => [
                'billing_interval' =>
                    $billingInterval,
                'amount' =>
                    $amount,
                'currency' =>
                    $currency,
                'provider' =>
                    $provider,
                'provider_subscription_id' =>
                    $providerSubscriptionId,
                'current_period_ends_at' =>
                    $currentPeriodEndsAt,
            ],
        ];
    }
}

if (!function_exists('subscription_record_history')) {
    function subscription_record_history(PDO $pdo, int $farmId, int $limit = 20): array
    {
        if ($farmId < 1 || !subscription_record_table_exists($pdo)) return [];
        $limit = max(1, min(100, $limit));
        $stmt = $pdo->prepare(
            'SELECT * FROM subscriptions WHERE farm_id = ? ORDER BY id DESC LIMIT ' . $limit
        );
        $stmt->execute([$farmId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
