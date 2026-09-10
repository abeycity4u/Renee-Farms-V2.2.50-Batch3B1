<?php
/**
 * V2.3 verified paid seat-top-up application service.
 *
 * Contract:
 * - consumes only an already provider-verified paid seat_topup attempt;
 * - requires the durable add-seat request bound to that exact attempt;
 * - revalidates immutable request/payment facts and current paid lineage;
 * - changes only purchased seat add-ons, effective role limits and commercial
 *   history before marking the durable request applied;
 * - application is exactly-once through the locked request workflow state;
 * - no provider/network call occurs here.
 *
 * This service is reached only through the central paid-purpose dispatcher after provider verification and audit-state application.
 */

require_once __DIR__ . '/subscription_plan_catalog.php';
require_once __DIR__ . '/subscription_seat_policy.php';
require_once __DIR__ . '/subscription_record.php';
require_once __DIR__ . '/billing_payment_foundation.php';
require_once __DIR__ . '/billing_payment_audit_state.php';
require_once __DIR__ . '/billing_currency_policy.php';
require_once __DIR__ . '/billing_provider_selection.php';
require_once __DIR__ . '/billing_seat_change_request.php';
require_once __DIR__ . '/billing_seat_proration.php';

if (!function_exists('billing_seat_topup_application_table_engine')) {
    function billing_seat_topup_application_table_engine(
        PDO $pdo,
        string $table
    ): ?string {
        $allowed = [
            'farms',
            'farm_role_limits',
            'farm_subscription_seat_addons',
            'subscriptions',
            'billing_payment_attempts',
            'billing_seat_change_requests',
        ];

        if (!in_array($table, $allowed, true)) {
            return null;
        }

        $stmt = $pdo->prepare(
            'SELECT engine '
            . 'FROM information_schema.tables '
            . 'WHERE table_schema = DATABASE() '
            . 'AND table_name = ? '
            . 'LIMIT 1'
        );

        $stmt->execute([$table]);

        $engine = $stmt->fetchColumn();

        return $engine === false
            ? null
            : (string)$engine;
    }
}

if (!function_exists('billing_seat_topup_application_ready')) {
    function billing_seat_topup_application_ready(
        PDO $pdo
    ): bool {
        if (!billing_seat_change_ready($pdo)
            || !subscription_record_table_ready($pdo)
            || !subscription_seat_addon_table_exists($pdo)) {
            return false;
        }

        foreach ([
            'farms',
            'farm_role_limits',
            'farm_subscription_seat_addons',
            'subscriptions',
            'billing_payment_attempts',
            'billing_seat_change_requests',
        ] as $table) {
            $engine =
                billing_seat_topup_application_table_engine(
                    $pdo,
                    $table
                );

            if (!is_string($engine)
                || strcasecmp(
                    $engine,
                    'InnoDB'
                ) !== 0) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('billing_seat_topup_attempt_contract')) {
    function billing_seat_topup_attempt_contract(
        array $attempt
    ): array {
        $attemptId =
            (int)($attempt['id'] ?? 0);

        $farmId =
            (int)($attempt['farm_id'] ?? 0);

        if ($attemptId < 1 || $farmId < 1) {
            throw new RuntimeException(
                'Verified seat-top-up payment identity is invalid.'
            );
        }

        if (billing_payment_attempt_purpose(
            $attempt
        ) !== 'seat_topup') {
            throw new RuntimeException(
                'Only seat_topup payment attempts can enter seat-top-up application.'
            );
        }

        if (strtolower(trim(
            (string)($attempt['status'] ?? '')
        )) !== 'paid') {
            throw new RuntimeException(
                'Only a verified paid seat-top-up attempt can be applied.'
            );
        }

        $verifiedAt =
            billing_audit_datetime(
                $attempt['verified_at'] ?? null
            );

        $paidAt =
            billing_audit_datetime(
                $attempt['paid_at'] ?? null
            );

        if ($verifiedAt === null
            || $paidAt === null) {
            throw new RuntimeException(
                'Paid seat-top-up attempt is missing provider verification timestamps.'
            );
        }

        $provider =
            billing_payment_normalize_provider(
                (string)($attempt['provider'] ?? '')
            );

        if (!in_array(
            $provider,
            billing_provider_selection_codes(),
            true
        )) {
            throw new RuntimeException(
                'Paid seat-top-up attempt uses an unsupported provider.'
            );
        }

        $providerReference =
            billing_payment_normalize_reference(
                (string)(
                    $attempt['provider_reference']
                    ?? ''
                )
            );

        $providerTransactionId =
            billing_audit_optional_identifier(
                $attempt['provider_transaction_id']
                    ?? null
            );

        if ($providerTransactionId === null) {
            throw new RuntimeException(
                'Paid seat-top-up attempt is missing its provider transaction id.'
            );
        }

        $planCode = strtolower(trim(
            (string)($attempt['plan_code'] ?? '')
        ));

        if (!subscription_plan_is_valid(
            $planCode
        )) {
            throw new RuntimeException(
                'Paid seat-top-up attempt contains an unknown subscription plan.'
            );
        }

        $billingInterval =
            billing_payment_normalize_interval(
                (string)(
                    $attempt['billing_interval']
                    ?? ''
                )
            );

        $amount =
            billing_payment_normalize_amount(
                (string)($attempt['amount'] ?? '')
            );

        $currency =
            billing_currency_policy_normalize(
                billing_payment_normalize_currency(
                    (string)(
                        $attempt['currency']
                        ?? ''
                    )
                )
            );

        $modules =
            billing_seat_change_attempt_modules(
                $attempt['modules_snapshot']
                    ?? null
            );

        $seatAddOns =
            billing_seat_change_attempt_seats(
                $attempt['seat_addons_snapshot']
                    ?? null
            );

        $rebuilt = billing_payment_build_quote(
            $planCode,
            $billingInterval,
            $amount,
            $currency,
            $modules,
            $seatAddOns
        );

        $storedHash = strtolower(trim(
            (string)(
                $attempt['quote_hash']
                ?? ''
            )
        ));

        if (!preg_match(
            '/^[a-f0-9]{64}$/',
            $storedHash
        )
            || !hash_equals(
                $storedHash,
                (string)$rebuilt['quote_hash']
            )) {
            throw new RuntimeException(
                'Frozen seat-top-up payment quote integrity check failed.'
            );
        }

        $initiatedBy =
            (int)(
                $attempt['initiated_by_user_id']
                ?? 0
            );

        return [
            'attempt_id' => $attemptId,
            'farm_id' => $farmId,
            'provider' => $provider,
            'provider_reference' =>
                $providerReference,
            'provider_transaction_id' =>
                $providerTransactionId,
            'plan_code' => $planCode,
            'billing_interval' =>
                $billingInterval,
            'amount' => $amount,
            'currency' => $currency,
            'modules' => $modules,
            'seat_addons' => $seatAddOns,
            'quote_hash' => $storedHash,
            'verified_at' => $verifiedAt,
            'paid_at' => $paidAt,
            'initiated_by_user_id' =>
                $initiatedBy > 0
                    ? $initiatedBy
                    : null,
        ];
    }
}

if (!function_exists('billing_seat_topup_assert_request_matches_attempt')) {
    function billing_seat_topup_assert_request_matches_attempt(
        array $requestContract,
        array $attemptContract,
        array $targetSeatAddOns
    ): void {
        if (($requestContract['change_kind'] ?? '')
            !== 'add') {
            throw new RuntimeException(
                'Paid seat-top-up application requires an add-seat request.'
            );
        }

        if ((int)(
            $requestContract['payment_attempt_id']
                ?? 0
        ) !== (int)$attemptContract['attempt_id']) {
            throw new RuntimeException(
                'Seat-change request is not bound to this paid attempt.'
            );
        }

        if ((int)(
            $requestContract['farm_id']
                ?? 0
        ) !== (int)$attemptContract['farm_id']) {
            throw new RuntimeException(
                'Seat-change request and paid attempt belong to different tenants.'
            );
        }

        $requestActor =
            billing_seat_change_optional_id(
                $requestContract[
                    'initiated_by_user_id'
                ] ?? null
            );

        if ($requestActor !==
            $attemptContract[
                'initiated_by_user_id'
            ]) {
            throw new RuntimeException(
                'Seat-change request actor does not match the paid attempt.'
            );
        }

        $targetSeatAddOns =
            subscription_seat_normalize_addons(
                $targetSeatAddOns
            );

        ksort(
            $targetSeatAddOns,
            SORT_STRING
        );

        if (($requestContract['plan_code'] ?? '')
                !== $attemptContract['plan_code']
            || ($requestContract[
                'billing_interval'
            ] ?? '')
                !== $attemptContract[
                    'billing_interval'
                ]
            || !hash_equals(
                (string)$requestContract['amount'],
                (string)$attemptContract['amount']
            )
            || !hash_equals(
                (string)$requestContract['currency'],
                (string)$attemptContract['currency']
            )
            || ($requestContract['modules'] ?? null)
                !== $attemptContract['modules']
            || $targetSeatAddOns
                !== $attemptContract[
                    'seat_addons'
                ]) {
            throw new RuntimeException(
                'Paid seat-top-up attempt does not match the immutable seat-change request.'
            );
        }
    }
}

if (!function_exists('billing_seat_topup_assert_current_lineage')) {
    function billing_seat_topup_assert_current_lineage(
        PDO $pdo,
        array $requestContract
    ): array {
        $timeline =
            billing_seat_proration_timeline(
                $pdo,
                (int)$requestContract['farm_id']
            );

        $lineageStart =
            $timeline['lineage_start']
                instanceof DateTimeImmutable
            ? $timeline['lineage_start']
                ->format('Y-m-d H:i:s')
            : null;

        $paidEnd =
            $timeline['paid_end']
                instanceof DateTimeImmutable
            ? $timeline['paid_end']
                ->format('Y-m-d H:i:s')
            : null;

        if ((int)(
                $timeline[
                    'latest_paid_subscription_id'
                ] ?? 0
            ) !== (int)$requestContract[
                'latest_paid_subscription_id'
            ]
            || (int)(
                $timeline[
                    'latest_paid_attempt_id'
                ] ?? 0
            ) !== (int)$requestContract[
                'latest_paid_attempt_id'
            ]
            || (string)(
                $timeline['plan_code'] ?? ''
            ) !== (string)$requestContract[
                'plan_code'
            ]
            || (string)(
                $timeline[
                    'billing_interval'
                ] ?? ''
            ) !== (string)$requestContract[
                'billing_interval'
            ]
            || ($timeline['modules'] ?? null)
                !== $requestContract['modules']
            || $lineageStart !==
                $requestContract[
                    'lineage_start_at'
                ]
            || $paidEnd !==
                $requestContract[
                    'current_period_ends_at'
                ]) {
            throw new RuntimeException(
                'Paid seat-top-up request no longer matches the current authoritative paid lineage.'
            );
        }

        return $timeline;
    }
}

if (!function_exists('billing_seat_topup_apply_paid_attempt')) {
    function billing_seat_topup_apply_paid_attempt(
        PDO $pdo,
        int $attemptId
    ): array {
        if ($attemptId < 1) {
            throw new InvalidArgumentException(
                'A valid seat-top-up payment attempt is required.'
            );
        }

        if (!billing_seat_topup_application_ready(
            $pdo
        )) {
            throw new RuntimeException(
                'Seat-top-up application storage is not transactionally ready.'
            );
        }

        $startedTransaction =
            !$pdo->inTransaction();

        if ($startedTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $attempt =
                billing_audit_attempt_by_id(
                    $pdo,
                    $attemptId,
                    true
                );

            if (!$attempt) {
                throw new RuntimeException(
                    'Seat-top-up payment attempt could not be found for application.'
                );
            }

            $attemptContract =
                billing_seat_topup_attempt_contract(
                    $attempt
                );

            $requestRow =
                billing_seat_change_request_by_payment(
                    $pdo,
                    $attemptId,
                    true
                );

            if (!$requestRow) {
                throw new RuntimeException(
                    'Paid seat-top-up attempt has no durable seat-change request.'
                );
            }

            $requestState =
                billing_seat_change_row_contract(
                    $requestRow
                );

            $requestContract =
                $requestState['contract'];

            billing_seat_topup_assert_request_matches_attempt(
                $requestContract,
                $attemptContract,
                $attemptContract['seat_addons']
            );

            if ($requestState['status']
                === 'applied') {
                if (empty(
                    $requestState['applied_at']
                )) {
                    throw new RuntimeException(
                        'Applied seat-change request is missing its application timestamp.'
                    );
                }

                if ($startedTransaction) {
                    $pdo->commit();
                }

                return [
                    'applied' => false,
                    'idempotent' => true,
                    'attempt_id' => $attemptId,
                    'request_id' =>
                        (int)$requestState['id'],
                    'farm_id' =>
                        $attemptContract['farm_id'],
                    'role_code' =>
                        $requestContract[
                            'role_code'
                        ],
                    'to_extra_seats' =>
                        $requestContract[
                            'to_extra_seats'
                        ],
                    'applied_at' =>
                        $requestState[
                            'applied_at'
                        ],
                ];
            }

            if ($requestState['status']
                !== 'awaiting_payment') {
                throw new RuntimeException(
                    'Seat-top-up request is not awaiting verified payment application.'
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

            $farmStmt->execute([
                $attemptContract['farm_id'],
            ]);

            if (!$farmStmt->fetchColumn()) {
                throw new RuntimeException(
                    'Tenant farm could not be locked for seat-top-up application.'
                );
            }

            $context =
                billing_seat_change_current_context(
                    $pdo,
                    $requestContract
                );

            billing_seat_topup_assert_request_matches_attempt(
                $requestContract,
                $attemptContract,
                $context[
                    'target_seat_addons'
                ]
            );

            billing_seat_topup_assert_current_lineage(
                $pdo,
                $requestContract
            );

            subscription_seat_assert_capacity(
                $pdo,
                $attemptContract['farm_id'],
                $requestContract['plan_code'],
                $requestContract['modules'],
                $context[
                    'target_seat_addons'
                ]
            );

            subscription_seat_save_addons(
                $pdo,
                $attemptContract['farm_id'],
                $context[
                    'target_seat_addons'
                ]
            );

            $effectiveLimits =
                subscription_seat_save_effective_limits(
                    $pdo,
                    $attemptContract['farm_id'],
                    $requestContract['plan_code'],
                    $requestContract['modules'],
                    $context[
                        'target_seat_addons'
                    ]
                );

            $history =
                subscription_record_capture(
                    $pdo,
                    $attemptContract['farm_id'],
                    'seat_topup_payment_applied',
                    $attemptContract[
                        'initiated_by_user_id'
                    ]
                );

            $historySeats =
                subscription_seat_normalize_addons(
                    is_array(
                        $history['snapshot'][
                            'seat_addons'
                        ] ?? null
                    )
                        ? $history['snapshot'][
                            'seat_addons'
                        ]
                        : []
                );

            $targetSeats =
                subscription_seat_normalize_addons(
                    $context[
                        'target_seat_addons'
                    ]
                );

            if ($historySeats !== $targetSeats) {
                throw new RuntimeException(
                    'Recorded commercial history does not match the applied seat top-up.'
                );
            }

            $update = $pdo->prepare(
                "UPDATE billing_seat_change_requests
                 SET status = 'applied',
                     applied_at = CURRENT_TIMESTAMP
                 WHERE id = ?
                   AND status = 'awaiting_payment'"
            );

            $update->execute([
                (int)$requestState['id'],
            ]);

            if ($update->rowCount() !== 1) {
                throw new RuntimeException(
                    'Seat-top-up request could not be marked applied exactly once.'
                );
            }

            $appliedRow =
                billing_seat_change_request_by_id(
                    $pdo,
                    (int)$requestState['id'],
                    false
                );

            if (!$appliedRow
                || (string)(
                    $appliedRow['status']
                    ?? ''
                ) !== 'applied'
                || empty(
                    $appliedRow['applied_at']
                )) {
                throw new RuntimeException(
                    'Applied seat-top-up request could not be verified.'
                );
            }

            if ($startedTransaction) {
                $pdo->commit();
            }

            return [
                'applied' => true,
                'idempotent' => false,
                'attempt_id' => $attemptId,
                'request_id' =>
                    (int)$requestState['id'],
                'farm_id' =>
                    $attemptContract['farm_id'],
                'role_code' =>
                    $requestContract['role_code'],
                'from_extra_seats' =>
                    $requestContract[
                        'from_extra_seats'
                    ],
                'to_extra_seats' =>
                    $requestContract[
                        'to_extra_seats'
                    ],
                'effective_role_limits' =>
                    $effectiveLimits,
                'history_record_id' =>
                    (int)($history['id'] ?? 0),
                'applied_at' =>
                    $appliedRow['applied_at'],
            ];
        } catch (Throwable $e) {
            if ($startedTransaction
                && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }
}
