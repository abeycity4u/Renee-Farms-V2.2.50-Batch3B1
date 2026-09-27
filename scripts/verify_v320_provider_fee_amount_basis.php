<?php

declare(strict_types=1);

require_once dirname(__DIR__)
    . '/includes/billing_provider_contract.php';

require_once dirname(__DIR__)
    . '/includes/billing_payment_audit_state.php';

$fail = 0;

function qa_pass(string $message): void
{
    echo "PASS: {$message}\n";
}

function qa_fail(string $message): void
{
    global $fail;
    $fail = 1;
    echo "FAIL: {$message}\n";
}

function qa_expect_accept(
    string $label,
    array $attempt,
    array $verification
): void {
    try {
        billing_audit_assert_provider_identity(
            $attempt,
            $verification
        );
        qa_pass($label);
    } catch (Throwable $e) {
        qa_fail(
            $label
            . ' | unexpected rejection: '
            . $e->getMessage()
        );
    }
}

function qa_expect_reject(
    string $label,
    array $attempt,
    array $verification
): void {
    try {
        billing_audit_assert_provider_identity(
            $attempt,
            $verification
        );

        qa_fail(
            $label
            . ' | unexpectedly accepted'
        );
    } catch (Throwable $e) {
        qa_pass($label);
    }
}

$attempt = [
    'provider' => 'paystack',
    'provider_reference' => 'qa-ref-1',
    'amount' => '10000.00',
    'currency' => 'NGN',
];

$base = [
    'provider' => 'paystack',
    'verified' => true,
    'status' => 'paid',
    'provider_reference' => 'qa-ref-1',
    'amount' => '10000.00',
    'requested_amount' => null,
    'provider_fee' => null,
    'currency' => 'NGN',
];

qa_expect_accept(
    'legacy exact provider amount remains accepted',
    $attempt,
    $base
);

$legacyMismatch = $base;
$legacyMismatch['amount'] = '10001.00';

qa_expect_reject(
    'legacy provider overpayment without authoritative fee remains rejected',
    $attempt,
    $legacyMismatch
);

$paystackFee = $base;
$paystackFee['amount'] = '10253.81';
$paystackFee['requested_amount'] = '10000.00';
$paystackFee['provider_fee'] = '253.81';

qa_expect_accept(
    'provider charged amount may exceed frozen amount only when requested amount matches and fee equation proves surcharge',
    $attempt,
    $paystackFee
);

$wrongRequested = $paystackFee;
$wrongRequested['requested_amount'] = '9999.00';

qa_expect_reject(
    'provider requested amount mismatch is rejected',
    $attempt,
    $wrongRequested
);

$wrongFee = $paystackFee;
$wrongFee['provider_fee'] = '200.00';

qa_expect_reject(
    'provider fee equation mismatch is rejected',
    $attempt,
    $wrongFee
);

$missingFee = $paystackFee;
$missingFee['provider_fee'] = null;

qa_expect_reject(
    'provider surcharge without authoritative fee is rejected',
    $attempt,
    $missingFee
);

$underpaid = $paystackFee;
$underpaid['amount'] = '9999.99';
$underpaid['provider_fee'] = '0.00';

qa_expect_reject(
    'provider charged amount below requested commercial amount is rejected',
    $attempt,
    $underpaid
);

echo "=== PROVIDER RESULT NORMALIZATION ===\n";

try {
    $normalized =
        billing_provider_normalize_payment_result(
            'paystack',
            [
                'verified' => true,
                'status' => 'paid',
                'provider_reference'
                    => 'qa-ref-1',
                'amount'
                    => '10253.81',
                'requested_amount'
                    => '10000.00',
                'provider_fee'
                    => '253.81',
                'currency'
                    => 'NGN',
            ]
        );

    if (
        ($normalized['requested_amount'] ?? null)
            === '10000.00'
        && ($normalized['provider_fee'] ?? null)
            === '253.81'
        && ($normalized['amount'] ?? null)
            === '10253.81'
    ) {
        qa_pass(
            'central provider normalization preserves requested amount and provider fee facts'
        );
    } else {
        qa_fail(
            'central provider normalization did not preserve provider fee facts'
        );
    }
} catch (Throwable $e) {
    qa_fail(
        'central provider normalization threw: '
        . $e->getMessage()
    );
}

echo "=== PAYSTACK ADAPTER STRUCTURE ===\n";

$paystackSource =
    file_get_contents(
        dirname(__DIR__)
        . '/includes/billing_provider_paystack.php'
    );

if (
    is_string($paystackSource)
    && strpos(
        $paystackSource,
        "'requested_amount'"
    ) !== false
    && strpos(
        $paystackSource,
        "'provider_fee'"
    ) !== false
) {
    qa_pass(
        'Paystack adapter exports authoritative requested amount and fee facts'
    );
} else {
    qa_fail(
        'Paystack adapter fee fact export missing'
    );
}

if ($fail === 0) {
    echo "PROVIDER_FEE_AMOUNT_BASIS_VERIFIER=PASS\n";
    exit(0);
}

echo "PROVIDER_FEE_AMOUNT_BASIS_VERIFIER=FAIL\n";
exit(1);
