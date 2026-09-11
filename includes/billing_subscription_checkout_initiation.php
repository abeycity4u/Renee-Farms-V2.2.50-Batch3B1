<?php
/**
 * V2.3 subscription checkout initiation foundation.
 *
 * Contract:
 * - browser product fields are assertions only;
 * - this service owns the attempt-creation transaction;
 * - commercial activity is serialized on the tenant farm before renewal
 *   pricing is resolved;
 * - scheduled seat reductions cannot be bypassed by an early renewal;
 * - at/after their paid-period boundary, scheduled reductions determine the
 *   server-authoritative renewal seat snapshot and price;
 * - the payment attempt is committed before provider/network initialization;
 * - this service performs no provider/network work and changes no entitlement.
 */

require_once __DIR__ . '/billing_payment_foundation.php';
require_once __DIR__ . '/billing_commercial_attempt_coordination.php';
require_once __DIR__ . '/billing_renewal_seat_target.php';

if (!function_exists('billing_subscription_checkout_policy')) {
    function billing_subscription_checkout_policy(
        array $target,
        ?DateTimeImmutable $now = null
    ): array {
        $farmId = (int)($target['farm_id'] ?? 0);

        if ($farmId < 1) {
            throw new RuntimeException(
                'Renewal checkout policy is missing its tenant identity.'
            );
        }

        $pricing =
            $target['pricing'] ?? null;

        $paymentQuote =
            $target['payment_quote'] ?? null;

        if (!is_array($pricing)
            || !is_array($paymentQuote)) {
            throw new RuntimeException(
                'Renewal checkout policy is missing server-authoritative pricing.'
            );
        }

        $planCode = strtolower(trim(
            (string)($target['plan_code'] ?? '')
        ));

        if (!subscription_plan_is_valid($planCode)) {
            throw new RuntimeException(
                'Renewal checkout policy contains an invalid plan.'
            );
        }

        $interval =
            billing_payment_normalize_interval(
                (string)(
                    $target['billing_interval']
                        ?? ''
                )
            );

        $modules =
            billing_payment_normalize_modules(
                is_array($target['modules'] ?? null)
                    ? $target['modules']
                    : []
            );

        $renewalSeats =
            billing_payment_normalize_seat_addons(
                is_array(
                    $target[
                        'renewal_seat_addons'
                    ] ?? null
                )
                    ? $target[
                        'renewal_seat_addons'
                    ]
                    : []
            );

        $periodEnd =
            billing_seat_change_normalize_datetime(
                $target[
                    'current_period_ends_at'
                ] ?? null
            );

        $scheduledIds =
            is_array(
                $target[
                    'scheduled_request_ids'
                ] ?? null
            )
                ? array_values(
                    $target[
                        'scheduled_request_ids'
                    ]
                )
                : [];

        $scheduledCount =
            (int)($target['scheduled_count'] ?? 0);

        $hasScheduled =
            ($target[
                'has_scheduled_reductions'
            ] ?? false) === true;

        if ($scheduledCount !== count($scheduledIds)
            || $hasScheduled
                !== ($scheduledCount > 0)) {
            throw new RuntimeException(
                'Renewal checkout policy received inconsistent scheduled-reduction state.'
            );
        }

        $timezone = new DateTimeZone(
            date_default_timezone_get()
        );

        $periodEndDate =
            new DateTimeImmutable(
                $periodEnd,
                $timezone
            );

        $now = $now
            ?? new DateTimeImmutable(
                'now',
                $timezone
            );

        if ($hasScheduled
            && $now < $periodEndDate) {
            throw new RuntimeException(
                'Subscription renewal cannot start before scheduled seat reductions reach the current paid-period end.'
            );
        }

        $pricingPlan = strtolower(trim(
            (string)($pricing['plan_code'] ?? '')
        ));

        $pricingInterval =
            billing_payment_normalize_interval(
                (string)(
                    $pricing[
                        'billing_interval'
                    ] ?? ''
                )
            );

        $pricingModules =
            billing_payment_normalize_modules(
                is_array(
                    $pricing['modules'] ?? null
                )
                    ? $pricing['modules']
                    : []
            );

        $pricingSeats =
            billing_payment_normalize_seat_addons(
                is_array(
                    $pricing[
                        'seat_addons'
                    ] ?? null
                )
                    ? $pricing[
                        'seat_addons'
                    ]
                    : []
            );

        if ($pricingPlan !== $planCode
            || $pricingInterval !== $interval
            || $pricingModules !== $modules
            || $pricingSeats !== $renewalSeats) {
            throw new RuntimeException(
                'Renewal checkout pricing does not match the authoritative renewal target.'
            );
        }

        $currentProduct =
            $target['current_product']
                ?? null;

        $farm = is_array($currentProduct)
            ? ($currentProduct['farm'] ?? null)
            : null;

        if (!is_array($farm)
            || (int)($farm['id'] ?? 0)
                !== $farmId) {
            throw new RuntimeException(
                'Renewal checkout policy could not resolve its tenant farm.'
            );
        }

        return [
            'farm_id' => $farmId,
            'farm' => $farm,
            'plan_code' => $planCode,
            'billing_interval' =>
                $interval,
            'modules' => $modules,
            'seat_addons' =>
                $renewalSeats,
            'current_period_ends_at' =>
                $periodEnd,
            'scheduled_request_ids' =>
                $scheduledIds,
            'scheduled_count' =>
                $scheduledCount,
            'uses_scheduled_reductions' =>
                $hasScheduled,
            'pricing' => $pricing,
            'payment_quote' =>
                $paymentQuote,
            'renewal_target' => $target,
        ];
    }
}

if (!function_exists('billing_subscription_checkout_assert_selection')) {
    function billing_subscription_checkout_assert_selection(
        array $policy,
        array $selection
    ): void {
        $expectedPlan =
            strtolower(trim(
                (string)(
                    $policy['plan_code']
                        ?? ''
                )
            ));

        $expectedInterval =
            billing_payment_normalize_interval(
                (string)(
                    $policy[
                        'billing_interval'
                    ] ?? ''
                )
            );

        $expectedModules =
            billing_payment_normalize_modules(
                is_array(
                    $policy['modules'] ?? null
                )
                    ? $policy['modules']
                    : []
            );

        $expectedSeats =
            billing_payment_normalize_seat_addons(
                is_array(
                    $policy[
                        'seat_addons'
                    ] ?? null
                )
                    ? $policy[
                        'seat_addons'
                    ]
                    : []
            );

        $actualPlan =
            strtolower(trim(
                (string)(
                    $selection['plan_code']
                        ?? ''
                )
            ));

        $actualInterval =
            billing_payment_normalize_interval(
                (string)(
                    $selection[
                        'billing_interval'
                    ] ?? ''
                )
            );

        $actualModules =
            billing_payment_normalize_modules(
                is_array(
                    $selection['modules'] ?? null
                )
                    ? $selection['modules']
                    : []
            );

        $actualSeats =
            billing_payment_normalize_seat_addons(
                is_array(
                    $selection[
                        'seat_addons'
                    ] ?? null
                )
                    ? $selection[
                        'seat_addons'
                    ]
                    : []
            );

        if ($actualPlan !== $expectedPlan
            || $actualInterval
                !== $expectedInterval
            || $actualModules
                !== $expectedModules
            || $actualSeats
                !== $expectedSeats) {
            throw new InvalidArgumentException(
                'Subscription checkout no longer matches the server-authoritative renewal product.'
            );
        }
    }
}

if (!function_exists('billing_subscription_checkout_prepare')) {
    function billing_subscription_checkout_prepare(
        PDO $pdo,
        int $farmId,
        string $provider,
        string $providerReference,
        array $selection,
        int $initiatedByUserId,
        array $allowedStatuses
    ): array {
        if ($farmId < 1) {
            throw new InvalidArgumentException(
                'A valid tenant farm is required for subscription checkout.'
            );
        }

        if ($initiatedByUserId < 1) {
            throw new InvalidArgumentException(
                'A valid initiating user is required for subscription checkout.'
            );
        }

        if ($pdo->inTransaction()) {
            throw new RuntimeException(
                'Subscription checkout initiation must own its database transaction.'
            );
        }

        if (!billing_payment_foundation_ready(
            $pdo
        )) {
            throw new RuntimeException(
                'Billing payment storage is not transactionally ready.'
            );
        }

        $provider =
            billing_payment_normalize_provider(
                $provider
            );

        $providerReference =
            billing_payment_normalize_reference(
                $providerReference
            );

        $pdo->beginTransaction();

        try {
            /*
             * Shared farm serialization happens before renewal resolution and
             * before a fresh payment attempt is created.
             */
            billing_commercial_attempt_assert_clear(
                $pdo,
                $farmId
            );

            $target =
                billing_renewal_seat_target(
                    $pdo,
                    $farmId,
                    true,
                    $allowedStatuses
                );

            $policy =
                billing_subscription_checkout_policy(
                    $target
                );

            billing_subscription_checkout_assert_selection(
                $policy,
                $selection
            );

            $pricing =
                $policy['pricing'];

            $created =
                billing_payment_attempt_create(
                    $pdo,
                    $farmId,
                    $provider,
                    $providerReference,
                    $pricing['plan_code'],
                    $pricing[
                        'billing_interval'
                    ],
                    $pricing['amount'],
                    $pricing['currency'],
                    $pricing['modules'],
                    $pricing['seat_addons'],
                    $initiatedByUserId,
                    'subscription'
                );

            if (($created['inserted'] ?? false)
                !== true) {
                throw new RuntimeException(
                    'Subscription checkout initiation requires a fresh provider reference.'
                );
            }

            $attemptId =
                (int)($created['id'] ?? 0);

            if ($attemptId < 1) {
                throw new RuntimeException(
                    'Subscription checkout payment attempt could not be created.'
                );
            }

            $attempt =
                $created['attempt'] ?? null;

            if (!is_array($attempt)
                || (int)($attempt['id'] ?? 0)
                    !== $attemptId
                || (int)($attempt['farm_id'] ?? 0)
                    !== $farmId
                || strtolower(trim(
                    (string)($attempt['status'] ?? '')
                )) !== 'initialized'
                || !hash_equals(
                    strtolower(trim(
                        (string)(
                            $attempt['provider'] ?? ''
                        )
                    )),
                    $provider
                )
                || !hash_equals(
                    trim((string)(
                        $attempt[
                            'provider_reference'
                        ] ?? ''
                    )),
                    $providerReference
                )
                || billing_payment_attempt_purpose(
                    $attempt
                ) !== 'subscription') {
                throw new RuntimeException(
                    'Subscription checkout created an invalid persisted payment attempt.'
                );
            }

            $pricedPaymentQuote =
                $policy[
                    'payment_quote'
                ]['payment_quote'] ?? null;

            if (!is_array($pricedPaymentQuote)) {
                throw new RuntimeException(
                    'Subscription checkout is missing its authoritative payment quote.'
                );
            }

            $expectedQuoteHash =
                strtolower(trim(
                    (string)(
                        $pricedPaymentQuote[
                            'quote_hash'
                        ] ?? ''
                    )
                ));

            $storedQuoteHash =
                strtolower(trim(
                    (string)(
                        $created[
                            'quote_hash'
                        ] ?? ''
                    )
                ));

            $persistedQuoteHash =
                strtolower(trim(
                    (string)(
                        $attempt[
                            'quote_hash'
                        ] ?? ''
                    )
                ));

            if (!preg_match(
                '/^[a-f0-9]{64}$/',
                $expectedQuoteHash
            )
                || !preg_match(
                    '/^[a-f0-9]{64}$/',
                    $storedQuoteHash
                )
                || !preg_match(
                    '/^[a-f0-9]{64}$/',
                    $persistedQuoteHash
                )
                || !hash_equals(
                    $expectedQuoteHash,
                    $storedQuoteHash
                )
                || !hash_equals(
                    $expectedQuoteHash,
                    $persistedQuoteHash
                )) {
                throw new RuntimeException(
                    'Subscription checkout payment quote was not frozen exactly.'
                );
            }

            $pdo->commit();

            return [
                'farm_id' => $farmId,
                'provider' => $provider,
                'provider_reference' =>
                    $providerReference,
                'attempt_id' =>
                    $attemptId,
                'policy' => $policy,
                'pricing' =>
                    $policy['pricing'],
                'payment_quote' =>
                    $policy[
                        'payment_quote'
                    ],
                'attempt' => $attempt,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }
}
?>
