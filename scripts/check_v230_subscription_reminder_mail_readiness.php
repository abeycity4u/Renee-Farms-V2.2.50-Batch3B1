<?php
/**
 * V2.3 subscription reminder mail CLI readiness check.
 *
 * Read-only. Reports only whether the CLI/cron environment can see the
 * centralized outbound-mail configuration required by a future scheduled
 * subscription reminder worker. Secret values are never printed and no email
 * is sent.
 *
 * Usage:
 *   php scripts/check_v230_subscription_reminder_mail_readiness.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/includes/platform_mailer.php';

$yesNo = static fn(bool $value): string => $value ? 'YES' : 'NO';
$enabledRaw = strtolower(platform_mail_env('PLATFORM_MAIL_ENABLED'));
$mailEnabled = !in_array($enabledRaw, ['0', 'false', 'off', 'disabled'], true);
$transport = platform_mail_transport();

$checks = [
    'PLATFORM_MAIL_ENABLED' => platform_mail_env('PLATFORM_MAIL_ENABLED') !== '',
    'PLATFORM_MAIL_TRANSPORT' => platform_mail_env('PLATFORM_MAIL_TRANSPORT') !== '',
    'PLATFORM_MAIL_FROM' => platform_mail_env('PLATFORM_MAIL_FROM') !== '',
    'PLATFORM_MAIL_FROM_NAME' => platform_mail_env('PLATFORM_MAIL_FROM_NAME') !== '',
];

if ($transport === 'smtp') {
    $checks += [
        'PLATFORM_SMTP_HOST' => platform_mail_env('PLATFORM_SMTP_HOST') !== '',
        'PLATFORM_SMTP_PORT' => platform_mail_env('PLATFORM_SMTP_PORT') !== '',
        'PLATFORM_SMTP_ENCRYPTION' => platform_mail_env('PLATFORM_SMTP_ENCRYPTION') !== '',
        'PLATFORM_SMTP_USERNAME' => platform_mail_env('PLATFORM_SMTP_USERNAME') !== '',
        'PLATFORM_SMTP_PASSWORD' => platform_mail_env('PLATFORM_SMTP_PASSWORD') !== '',
    ];
}

$from = platform_mail_from_address();
$fromValid = filter_var($from, FILTER_VALIDATE_EMAIL) !== false;
$transportValid = in_array($transport, ['smtp', 'php_mail'], true);
$portValid = true;
$encryptionValid = true;
if ($transport === 'smtp') {
    $port = (int)platform_mail_env('PLATFORM_SMTP_PORT');
    $portValid = $port >= 1 && $port <= 65535;
    $encryptionValid = in_array(strtolower(platform_mail_env('PLATFORM_SMTP_ENCRYPTION')), ['ssl', 'tls'], true);
}

$missing = array_keys(array_filter($checks, static fn(bool $present): bool => !$present));
$ready = $mailEnabled
    && $transportValid
    && $fromValid
    && $portValid
    && $encryptionValid
    && !$missing;

echo 'Runtime:                 PHP CLI' . PHP_EOL;
echo 'Mail enabled:            ' . $yesNo($mailEnabled) . PHP_EOL;
echo 'Transport:               ' . strtoupper($transport) . PHP_EOL;
echo 'From address valid:      ' . $yesNo($fromValid) . PHP_EOL;
if ($transport === 'smtp') {
    echo 'SMTP port valid:         ' . $yesNo($portValid) . PHP_EOL;
    echo 'SMTP encryption valid:   ' . $yesNo($encryptionValid) . PHP_EOL;
}
echo 'Required env present:    ' . $yesNo(!$missing) . PHP_EOL;
echo 'Overall CLI ready:       ' . $yesNo($ready) . PHP_EOL;

if ($missing) {
    echo 'Missing environment:     ' . implode(', ', $missing) . PHP_EOL;
}

echo PHP_EOL;
if ($ready) {
    echo 'PASS: CLI/cron mail prerequisites are visible to this PHP process.' . PHP_EOL;
    echo 'NOTE: no email was sent and no credential values were printed.' . PHP_EOL;
    exit(0);
}

echo 'FAIL: CLI/cron mail prerequisites are not ready for scheduled subscription reminders.' . PHP_EOL;
echo 'NOTE: web-server/.htaccess environment values do not automatically prove CLI visibility.' . PHP_EOL;
echo 'NOTE: no email was sent and no credential values were printed.' . PHP_EOL;
exit(1);
