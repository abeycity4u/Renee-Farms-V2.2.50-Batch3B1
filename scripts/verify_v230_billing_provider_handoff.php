<?php
/**
 * Focused V2.3 shared provider-handoff verifier.
 *
 * Database-free and provider-network-free.
 * Proves that subscription and seat-top-up checkout share one browser
 * handoff boundary while global CSP form-action remains strict.
 */

$root = dirname(__DIR__);

$paths = [
    'handoff' =>
        $root
        . '/includes/billing_provider_handoff.php',
    'script' =>
        $root
        . '/assets/js/billing-provider-handoff.js',
    'checkout' =>
        $root
        . '/billing/checkout.php',
    'seat_checkout' =>
        $root
        . '/billing/seat_topup_checkout.php',
    'csp' =>
        $root
        . '/includes/csp_policy.php',
];

$source = [];

foreach ($paths as $key => $path) {
    if (!is_file($path)) {
        fwrite(
            STDERR,
            "FAIL: missing {$path}\n"
        );
        exit(1);
    }

    $content = file_get_contents($path);

    if ($content === false) {
        fwrite(
            STDERR,
            "FAIL: unable to read {$path}\n"
        );
        exit(1);
    }

    $source[$key] = $content;
}

require_once $paths['handoff'];

$checks = 0;
$failures = 0;

$check = static function (
    bool $ok,
    string $message
) use (&$checks, &$failures): void {
    $checks++;

    if ($ok) {
        echo "PASS: {$message}\n";
        return;
    }

    $failures++;
    echo "FAIL: {$message}\n";
};

$handoff = $source['handoff'];
$script = $source['script'];
$checkout = $source['checkout'];
$seatCheckout = $source['seat_checkout'];
$csp = $source['csp'];

$check(
    strpos(
        $handoff,
        "require_once __DIR__ . '/billing_provider_contract.php';"
    ) !== false
    && strpos(
        $handoff,
        'billing_provider_normalize_checkout_result('
    ) !== false,
    'handoff reuses the canonical normalized provider checkout contract'
);

$document = null;

try {
    $document =
        billing_provider_handoff_document([
            'provider' => 'paystack',
            'provider_reference' =>
                'v23-handoff-verifier',
            'checkout_url' =>
                'https://checkout.paystack.com/v23-handoff-verifier',
            'provider_transaction_id' =>
                null,
            'provider_subscription_id' =>
                null,
        ]);
} catch (Throwable $e) {
    $document = null;
}

$check(
    is_string($document)
    && strpos(
        $document,
        'id="billing-provider-handoff-link"'
    ) !== false
    && strpos(
        $document,
        'https://checkout.paystack.com/v23-handoff-verifier'
    ) !== false,
    'handoff document exposes only the already-normalized secure provider URL'
);

$check(
    is_string($document)
    && strpos(
        $document,
        '/assets/js/billing-provider-handoff.js'
    ) !== false
    && strpos(
        $document,
        'window.location'
    ) === false,
    'handoff document uses same-origin external JavaScript rather than inline script'
);

$check(
    is_string($document)
    && strpos(
        $document,
        '<form'
    ) === false,
    'handoff document does not create another browser form submission'
);

$invalidRejected = false;

try {
    billing_provider_handoff_document([
        'provider' => 'paystack',
        'provider_reference' =>
            'v23-invalid-handoff',
        'checkout_url' =>
            'http://example.invalid/not-secure',
        'provider_transaction_id' =>
            null,
        'provider_subscription_id' =>
            null,
    ]);
} catch (Throwable $e) {
    $invalidRejected = true;
}

$check(
    $invalidRejected,
    'handoff fails closed when provider checkout URL is not canonical HTTPS'
);

$check(
    strpos(
        $handoff,
        'http_response_code(200);'
    ) !== false
    && strpos(
        $handoff,
        "header('Location:"
    ) === false,
    'handoff breaks the form redirect chain with HTTP 200 instead of an external Location response'
);

$check(
    strpos(
        $handoff,
        "'Referrer-Policy: no-referrer'"
    ) !== false
    && strpos(
        $handoff,
        "'Cache-Control: no-store, max-age=0'"
    ) !== false,
    'handoff response is non-cacheable and does not leak billing-route referrers'
);

$check(
    strpos(
        $script,
        "getElementById(\n        'billing-provider-handoff-link'"
    ) !== false
    && strpos(
        $script,
        'window.location.replace(link.href);'
    ) !== false,
    'same-origin handoff script performs ordinary top-level provider navigation'
);

$check(
    strpos(
        $checkout,
        '/includes/billing_provider_handoff.php'
    ) !== false
    && substr_count(
        $checkout,
        'billing_provider_handoff($checkout);'
    ) === 1,
    'subscription checkout delegates provider navigation to the shared handoff exactly once'
);

$check(
    strpos(
        $seatCheckout,
        '/includes/billing_provider_handoff.php'
    ) !== false
    && substr_count(
        $seatCheckout,
        'billing_provider_handoff($checkout);'
    ) === 1,
    'seat-top-up checkout delegates provider navigation to the same shared handoff exactly once'
);

$check(
    strpos(
        $checkout,
        "\$checkout['checkout_url']"
    ) === false
    && strpos(
        $seatCheckout,
        "\$checkout['checkout_url']"
    ) === false,
    'checkout routes no longer directly emit or navigate to provider checkout URLs'
);

$check(
    strpos(
        $csp,
        "form-action 'self'"
    ) !== false,
    'global CSP keeps form-action restricted to self'
);

$check(
    stripos(
        $csp,
        'paystack'
    ) === false
    && stripos(
        $csp,
        'flutterwave'
    ) === false,
    'global CSP is not weakened with provider-specific host exceptions'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    echo "V2.3 SHARED PROVIDER HANDOFF: FAILED\n";
    exit(1);
}

echo "V2.3 SHARED PROVIDER HANDOFF: PASSED\n";
