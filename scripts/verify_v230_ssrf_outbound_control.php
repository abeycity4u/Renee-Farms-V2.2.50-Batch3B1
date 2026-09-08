<?php
$root = dirname(__DIR__);
$failures = 0;
$checks = 0;

$check = function (bool $ok, string $label) use (&$failures, &$checks): void {
    $checks++;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) $failures++;
};

$pdf = file_get_contents($root . '/includes/pdf/PdfReportService.php');
$billing = file_get_contents($root . '/includes/billing_http_transport.php');
$mailer = file_get_contents($root . '/includes/platform_mailer.php');

$check(
    str_contains($pdf, "\$options->set('isRemoteEnabled', false);"),
    'PDF service disables remote resource fetching'
);

$check(
    !str_contains($pdf, "\$options->set('isRemoteEnabled', true);"),
    'PDF service does not enable remote resource fetching'
);

$check(
    str_contains($pdf, "\$options->set('chroot', \$this->root);"),
    'PDF service keeps filesystem access chrooted to application root'
);

$check(
    str_contains($billing, "'api.paystack.co'")
    && str_contains($billing, "'api.flutterwave.com'"),
    'Billing transport uses exact provider host allowlist'
);

$check(
    str_contains($billing, "strtolower((string)(\$parts['scheme'] ?? '')) !== 'https'"),
    'Billing transport requires HTTPS'
);

$check(
    str_contains($billing, "CURLOPT_FOLLOWLOCATION => false"),
    'Billing transport disables redirects'
);

$check(
    str_contains($billing, "CURLOPT_SSL_VERIFYPEER => true")
    && str_contains($billing, "CURLOPT_SSL_VERIFYHOST => 2"),
    'Billing transport verifies TLS peer and hostname'
);

$check(
    str_contains($billing, "CURLOPT_PROTOCOLS")
    && str_contains($billing, "CURLPROTO_HTTPS"),
    'Billing transport constrains cURL protocol to HTTPS when supported'
);

$check(
    str_contains($mailer, "platform_mail_env('PLATFORM_SMTP_HOST')")
    && str_contains($mailer, "platform_mail_env('PLATFORM_SMTP_PORT')"),
    'SMTP destination comes from server configuration'
);

$check(
    !preg_match('/\$_(?:GET|POST|REQUEST)\s*\[[^\]]+\].*stream_socket_client/s', $mailer),
    'SMTP socket destination is not request-controlled'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
