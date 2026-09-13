<?php
/**
 * V2.3 shared payment-provider browser handoff.
 *
 * Provider initialization and pending-state persistence happen before this
 * helper is called. The helper deliberately ends the browser form submission
 * with an HTTP 200 same-origin document, then lets same-origin external JS
 * perform ordinary top-level navigation to the already-normalized provider
 * checkout URL.
 *
 * This avoids weakening the platform-wide CSP form-action 'self' boundary and
 * prevents Paystack/Flutterwave checkout behavior from being duplicated across
 * subscription and seat-top-up routes.
 */

require_once __DIR__ . '/billing_provider_contract.php';

if (!function_exists(
    'billing_provider_handoff_document'
)) {
    function billing_provider_handoff_document(
        array $checkout
    ): string {
        /*
         * Reuse the canonical provider-result normalizer rather than creating
         * a second URL/security policy at the browser boundary.
         */
        $provider =
            billing_provider_normalize_code(
                (string)($checkout['provider'] ?? '')
            );

        $normalized =
            billing_provider_normalize_checkout_result(
                $provider,
                $checkout
            );

        $checkoutUrl =
            (string)$normalized['checkout_url'];

        $scriptAssetPath =
            '/assets/js/billing-provider-handoff.js';

        $styleAssetPath =
            '/assets/css/billing-provider-handoff.css';

        if (function_exists('versioned_asset')) {
            $scriptAssetPath =
                versioned_asset(
                    $scriptAssetPath
                );

            $styleAssetPath =
                versioned_asset(
                    $styleAssetPath
                );
        }

        $baseUrl =
            defined('BASE_URL')
                ? (string)BASE_URL
                : '';

        $scriptUrl =
            $baseUrl . $scriptAssetPath;

        $styleUrl =
            $baseUrl . $styleAssetPath;

        $providerLabel =
            ucfirst($provider);

        $checkoutUrlHtml = htmlspecialchars(
            $checkoutUrl,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        $providerLabelHtml = htmlspecialchars(
            $providerLabel,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        $scriptUrlHtml = htmlspecialchars(
            $scriptUrl,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        $styleUrlHtml = htmlspecialchars(
            $styleUrl,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <meta name="theme-color" content="#118653">
    <title>Opening secure payment</title>
    <link rel="stylesheet" href="{$styleUrlHtml}">
</head>
<body class="billing-handoff-page">
<main class="billing-handoff-shell" aria-labelledby="billing-handoff-title">
    <section class="billing-handoff-card">
        <div class="billing-handoff-indicator" aria-hidden="true">
            <span class="billing-handoff-spinner"></span>
        </div>

        <p class="billing-handoff-eyebrow">Secure checkout</p>

        <h1 id="billing-handoff-title">
            Opening secure payment
        </h1>

        <p class="billing-handoff-lead" aria-live="polite">
            Connecting you securely to {$providerLabelHtml}. This should only take a moment.
        </p>

        <a
            id="billing-provider-handoff-link"
            class="billing-handoff-button"
            href="{$checkoutUrlHtml}"
            rel="noreferrer"
        >
            Continue to {$providerLabelHtml}
        </a>

        <p class="billing-handoff-help">
            If nothing happens automatically, use the secure button above.
        </p>

        <noscript>
            <p class="billing-handoff-noscript">
                JavaScript is disabled. Use the secure button above to continue.
            </p>
        </noscript>
    </section>

    <p class="billing-handoff-footnote">
        Secure payment handoff
    </p>
</main>
<script src="{$scriptUrlHtml}"></script>
</body>
</html>
HTML;
    }
}

if (!function_exists(
    'billing_provider_handoff'
)) {
    function billing_provider_handoff(
        array $checkout
    ): void {
        /*
         * Build/validate before emitting any response body so malformed
         * provider state fails closed through the caller's existing error path.
         */
        $document =
            billing_provider_handoff_document(
                $checkout
            );

        if (!headers_sent()) {
            http_response_code(200);

            header(
                'Content-Type: text/html; charset=UTF-8'
            );

            header(
                'Cache-Control: no-store, max-age=0'
            );

            /*
             * Do not disclose the tenant billing route as a cross-origin
             * referrer when the browser leaves for the payment provider.
             */
            header(
                'Referrer-Policy: no-referrer'
            );

            header(
                'X-Robots-Tag: noindex, nofollow, noarchive'
            );
        }

        echo $document;
        exit();
    }
}
