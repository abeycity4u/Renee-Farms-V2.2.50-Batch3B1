<?php
/**
 * V2.3 seat-top-up checkout initiation foundation.
 *
 * Contract:
 * - only server-authoritative current commercial state may price a top-up;
 * - this service owns its transaction so the payment attempt and durable
 *   seat-change request are committed together before any provider call;
 * - the tenant farm is locked before the proration quote is calculated;
 * - the payment attempt is frozen with purpose seat_topup;
 * - the durable request receives the exact authoritative proration snapshot;
 * - provider/network initialization is deliberately outside this service;
 * - this service does not grant seats or mutate current subscription state.
 */

require_once __DIR__ . '/billing_payment_foundation.php';
require_once __DIR__ . '/billing_pricing_contract.php';
require_once __DIR__ . '/billing_seat_proration.php';
require_once __DIR__ . '/billing_seat_change_request.php';

if (!function_exists('billing_seat_topup_build_checkout_quote')) {
    function billing_seat_topup_build_checkout_quote(
        array $quote
    ): array {
        $modules = $quote['modules'] ?? null;
        $targetSeats =
            $quote['target_seat_addons'] ?? null;

        if (!is_array($modules)
            || !is_array($targetSeats)) {
            throw new InvalidArgumentException(
                'Seat-top-up quote is missing its canonical commercial snapshot.'
            );
        }

        $planCode = strtolower(trim(
            (string)($quote['plan_code'] ?? '')
        ));

        $interval =
            billing_payment_normalize_interval(
                (string)(
                    $quote['billing_interval']
                        ?? ''
                )
            );

        $amount =
            billing_payment_normalize_amount(
                (string)($quote['amount'] ?? '')
            );

        $currency =
            billing_payment_normalize_currency(
                (string)($quote['currency'] ?? '')
            );

        $modules =
            billing_payment_normalize_modules(
                $modules
            );

        $targetSeats =
            billing_payment_normalize_seat_addons(
                $targetSeats
            );

        $pricingVersion = trim(
            (string)(
                $quote['pricing_version']
                    ?? ''
            )
        );

        $pricingHash = strtolower(trim(
            (string)(
                $quote['pricing_hash']
                    ?? ''
            )
        ));

        if ($pricingVersion === ''
            || strlen($pricingVersion) > 80
            || !preg_match(
                '/^[A-Za-z0-9][A-Za-z0-9_.-]*$/',
                $pricingVersion
            )
            || !preg_match(
                '/^[a-f0-9]{64}$/',
                $pricingHash
            )) {
            throw new RuntimeException(
                'Seat-top-up quote has invalid pricing identity.'
            );
        }

        $paymentQuote =
            billing_payment_build_quote(
                $planCode,
                $interval,
                $amount,
                $currency,
                $modules,
                $targetSeats
            );

        return [
            'pricing' => [
                'pricing_version' =>
                    $pricingVersion,
                'pricing_hash' =>
                    $pricingHash,
                'plan_code' => $planCode,
                'billing_interval' =>
                    $interval,
                'modules' => $modules,
                'seat_addons' =>
                    $targetSeats,
                'amount' => $amount,
                'currency' => $currency,
            ],
            'payment_quote' =>
                $paymentQuote,
        ];
    }
}

if (!function_exists('billing_seat_topup_request_input')) {
    function billing_seat_topup_request_input(
        array $quote,
        int $paymentAttemptId,
        int $initiatedByUserId
    ): array {
        if ($paymentAttemptId < 1) {
            throw new InvalidArgumentException(
                'A valid seat-top-up payment attempt is required.'
            );
        }

        if ($initiatedByUserId < 1) {
            throw new InvalidArgumentException(
                'A valid seat-top-up initiating user is required.'
            );
        }

        $checkout =
            billing_seat_topup_build_checkout_quote(
                $quote
            );

        $payment =
            $checkout['payment_quote']['quote'];

        return [
            'farm_id' =>
                (int)($quote['farm_id'] ?? 0),
            'change_kind' => 'add',
            'role_code' =>
                (string)($quote['role_code'] ?? ''),
            'from_extra_seats' =>
                $quote['from_extra_seats'] ?? null,
            'to_extra_seats' =>
                $quote['to_extra_seats'] ?? null,
            'plan_code' =>
                $payment['plan_code'],
            'billing_interval' =>
                $payment['billing_interval'],
            'modules' =>
                $payment['modules'],
            'amount' =>
                $payment['amount'],
            'currency' =>
                $payment['currency'],
            'current_period_ends_at' =>
                $quote[
                    'current_period_ends_at'
                ] ?? null,
            'quoted_at' =>
                $quote['quoted_at'] ?? null,
            'lineage_start_at' =>
                $quote['lineage_start'] ?? null,
            'segment_start_at' =>
                $quote['segment_start'] ?? null,
            'segment_end_at' =>
                $quote['segment_end'] ?? null,
            'pricing_version' =>
                $checkout['pricing'][
                    'pricing_version'
                ],
            'pricing_hash' =>
                $checkout['pricing'][
                    'pricing_hash'
                ],
            'unit_amount' =>
                $quote['unit_amount'] ?? null,
            'partial_unit_amount' =>
                $quote[
                    'partial_unit_amount'
                ] ?? null,
            'future_full_periods' =>
                $quote[
                    'future_full_periods'
                ] ?? null,
            'per_seat_amount' =>
                $quote[
                    'per_seat_amount'
                ] ?? null,
            'latest_paid_subscription_id' =>
                $quote[
                    'latest_paid_subscription_id'
                ] ?? null,
            'latest_paid_attempt_id' =>
                $quote[
                    'latest_paid_attempt_id'
                ] ?? null,
            'effective_at' => null,
            'payment_attempt_id' =>
                $paymentAttemptId,
            'initiated_by_user_id' =>
                $initiatedByUserId,
        ];
    }
}

if (!function_exists('billing_seat_topup_prepare')) {
    function billing_seat_topup_prepare(
        PDO $pdo,
        int $farmId,
        string $provider,
        string $providerReference,
        string $roleCode,
        int $quantity,
        int $initiatedByUserId
    ): array {
        if ($farmId < 1) {
            throw new InvalidArgumentException(
                'A valid tenant farm is required for seat top-up.'
            );
        }

        if ($initiatedByUserId < 1) {
            throw new InvalidArgumentException(
                'A valid initiating user is required for seat top-up.'
            );
        }

        if ($pdo->inTransaction()) {
            throw new RuntimeException(
                'Seat-top-up initiation must own its database transaction.'
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
            // Serialize the tenant commercial snapshot before quoting.
            $farmLock = $pdo->prepare(
                "SELECT id
                 FROM farms
                 WHERE id = ?
                   AND slug <> 'owner'
                 LIMIT 1
                 FOR UPDATE"
            );

            $farmLock->execute([$farmId]);

            if (!$farmLock->fetchColumn()) {
                throw new RuntimeException(
                    'Tenant farm could not be locked for seat-top-up initiation.'
                );
            }

            $quote =
                billing_seat_proration_quote(
                    $pdo,
                    $farmId,
                    $roleCode,
                    $quantity
                );

            if ((int)($quote['farm_id'] ?? 0)
                !== $farmId) {
                throw new RuntimeException(
                    'Seat-top-up quote does not belong to the requested tenant.'
                );
            }

            $checkoutQuote =
                billing_seat_topup_build_checkout_quote(
                    $quote
                );

            $payment =
                $checkoutQuote[
                    'payment_quote'
                ]['quote'];

            $created =
                billing_payment_attempt_create(
                    $pdo,
                    $farmId,
                    $provider,
                    $providerReference,
                    $payment['plan_code'],
                    $payment[
                        'billing_interval'
                    ],
                    $payment['amount'],
                    $payment['currency'],
                    $payment['modules'],
                    $payment['seat_addons'],
                    $initiatedByUserId,
                    'seat_topup'
                );

            if (($created['inserted'] ?? false)
                !== true) {
                throw new RuntimeException(
                    'Seat-top-up initiation requires a fresh provider reference.'
                );
            }

            $attemptId =
                (int)($created['id'] ?? 0);

            if ($attemptId < 1) {
                throw new RuntimeException(
                    'Seat-top-up payment attempt could not be created.'
                );
            }

            $expectedQuoteHash =
                (string)(
                    $checkoutQuote[
                        'payment_quote'
                    ]['quote_hash'] ?? ''
                );

            $storedQuoteHash =
                (string)(
                    $created['quote_hash']
                        ?? ''
                );

            if ($expectedQuoteHash === ''
                || !hash_equals(
                    $expectedQuoteHash,
                    $storedQuoteHash
                )) {
                throw new RuntimeException(
                    'Seat-top-up payment quote was not frozen exactly.'
                );
            }

            $requestInput =
                billing_seat_topup_request_input(
                    $quote,
                    $attemptId,
                    $initiatedByUserId
                );

            $request =
                billing_seat_change_request_insert(
                    $pdo,
                    $requestInput
                );

            $requestContract =
                $request['contract'] ?? null;

            if (($request['status'] ?? '')
                    !== 'awaiting_payment'
                || !is_array($requestContract)
                || (int)(
                    $requestContract[
                        'payment_attempt_id'
                    ] ?? 0
                ) !== $attemptId
                || (int)(
                    $requestContract[
                        'farm_id'
                    ] ?? 0
                ) !== $farmId
                || ($requestContract[
                    'change_kind'
                ] ?? '') !== 'add') {
                throw new RuntimeException(
                    'Durable seat-top-up request was not bound to its payment attempt.'
                );
            }

            $pdo->commit();

            return [
                'farm_id' => $farmId,
                'provider' => $provider,
                'provider_reference' =>
                    $providerReference,
                'attempt_id' => $attemptId,
                'request_id' =>
                    (int)($request['id'] ?? 0),
                'role_code' =>
                    (string)$quote['role_code'],
                'quantity' =>
                    (int)$quote['quantity'],
                'amount' =>
                    (string)$payment['amount'],
                'currency' =>
                    (string)$payment['currency'],
                'quote' => $quote,
                'checkout_quote' =>
                    $checkoutQuote,
                'request' => $request,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }
}
