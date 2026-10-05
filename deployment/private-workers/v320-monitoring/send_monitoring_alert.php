<?php

declare(strict_types=1);

$home = getenv('HOME');

if (!is_string($home) || $home === '') {
    $home = '/home/renee';
}

$bridge =
    $home
    . '/renee-private/v310-credential-worker/'
    . 'v310_private_cli_bridge.php';

$authority =
    $home
    . '/public_html/.htaccess';

$mailer =
    $home
    . '/public_html/includes/platform_mailer.php';

$recipientFile =
    $home
    . '/renee-private/v320-monitoring/alert-recipient.txt';

foreach (
    [$bridge, $authority, $mailer, $recipientFile]
    as $required
) {
    if (!is_file($required) || !is_readable($required)) {
        fwrite(STDERR, "MONITOR_MAIL_REQUIRED_FILE=FAIL\n");
        exit(1);
    }
}

$args = getopt(
    '',
    [
        'endpoint:',
        'state:',
        'http:',
        'latency:',
    ]
);

$endpoint =
    strtolower(
        trim(
            (string)($args['endpoint'] ?? '')
        )
    );

$state =
    strtoupper(
        trim(
            (string)($args['state'] ?? '')
        )
    );

$http =
    trim(
        (string)($args['http'] ?? '')
    );

$latency =
    trim(
        (string)($args['latency'] ?? '')
    );

$allowedEndpoints = [
    'production' => 'Production',
    'staging' => 'Staging',
];

if (!isset($allowedEndpoints[$endpoint])) {
    fwrite(STDERR, "MONITOR_ENDPOINT=INVALID\n");
    exit(1);
}

if (!in_array($state, ['FAIL', 'RECOVERED'], true)) {
    fwrite(STDERR, "MONITOR_STATE=INVALID\n");
    exit(1);
}

if (
    preg_match('/^(?:[0-9]{3}|curl_error)$/', $http)
    !== 1
) {
    fwrite(STDERR, "MONITOR_HTTP=INVALID\n");
    exit(1);
}

if (
    preg_match('/^[0-9]+(?:\.[0-9]+)?$/', $latency)
    !== 1
) {
    fwrite(STDERR, "MONITOR_LATENCY=INVALID\n");
    exit(1);
}

require_once $bridge;

try {
    v310_private_cli_bridge_import($authority);
} catch (Throwable $e) {
    fwrite(STDERR, "MONITOR_AUTHORITY_IMPORT=FAIL\n");
    exit(1);
}

if (!v310_private_cli_mail_authority_ready()) {
    fwrite(STDERR, "MONITOR_MAIL_AUTHORITY=FAIL\n");
    exit(1);
}

require_once $mailer;

$recipient =
    strtolower(
        trim(
            (string)file_get_contents(
                $recipientFile
            )
        )
    );

if (
    $recipient === ''
    || strlen($recipient) > 254
    || filter_var(
        $recipient,
        FILTER_VALIDATE_EMAIL
    ) === false
) {
    fwrite(STDERR, "MONITOR_RECIPIENT=INVALID\n");
    exit(1);
}

$label = $allowedEndpoints[$endpoint];

if ($state === 'FAIL') {
    $subject =
        'Renee AgriSuite alert: '
        . $label
        . ' unavailable';

    $body =
        "Renee AgriSuite availability alert.\n\n"
        . "Environment: {$label}\n"
        . "State: FAILURE\n"
        . "HTTP status: {$http}\n"
        . "Response time: {$latency} seconds\n"
        . "Detected: "
        . date(DATE_RFC2822)
        . "\n\n"
        . "The monitor will continue checking automatically. "
        . "A recovery email will be sent when the endpoint "
        . "returns to normal.";
} else {
    $subject =
        'Renee AgriSuite recovered: '
        . $label;

    $body =
        "Renee AgriSuite recovery notification.\n\n"
        . "Environment: {$label}\n"
        . "State: RECOVERED\n"
        . "HTTP status: {$http}\n"
        . "Response time: {$latency} seconds\n"
        . "Recovered: "
        . date(DATE_RFC2822)
        . "\n\n"
        . "The endpoint is responding normally again.";
}

$result =
    platform_mail_send(
        $recipient,
        $subject,
        $body,
        [
            'sender' => 'notifications',
        ]
    );

if (($result['sent'] ?? false) !== true) {
    fwrite(
        STDERR,
        'MONITOR_MAIL_SEND=FAIL'
        . PHP_EOL
    );
    exit(1);
}

echo "MONITOR_MAIL_SEND=PASS\n";
exit(0);
