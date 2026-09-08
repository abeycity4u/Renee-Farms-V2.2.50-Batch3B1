<?php
/**
 * Focused V2.3 verifier for scheduled subscription renewal reminders.
 * Static/pure checks only; sends no email and mutates no tenant data.
 */
$root = dirname(__DIR__);
$service = $root . '/includes/subscription_renewal_reminder.php';
$notice = $root . '/includes/subscription_renewal_notice.php';
$mailer = $root . '/includes/platform_mailer.php';
$apply = $root . '/scripts/apply_v230_subscription_renewal_reminder_deliveries.php';
$worker = $root . '/scripts/run_v230_subscription_renewal_reminders.php';

$checks = [];
$failures = [];
$pass = static function (string $label, bool $ok) use (&$checks, &$failures): void {
    $checks[] = [$label, $ok];
    if (!$ok) $failures[] = $label;
};

foreach ([
    'reminder service' => $service,
    'renewal notice helper' => $notice,
    'platform mailer' => $mailer,
    'delivery ledger migration' => $apply,
    'reminder worker' => $worker,
] as $label => $file) {
    $pass($label . ' exists', is_file($file));
}

$serviceSource = is_file($service) ? (string)file_get_contents($service) : '';
$applySource = is_file($apply) ? (string)file_get_contents($apply) : '';
$workerSource = is_file($worker) ? (string)file_get_contents($worker) : '';

$pass('service reuses centralized in-app renewal notice',
    str_contains($serviceSource, 'subscription_renewal_notice.php')
    && str_contains($serviceSource, 'subscription_renewal_notice($farm, $today)'));
$pass('service reuses canonical farm contact email validation',
    str_contains($serviceSource, 'farm_contact_email.php')
    && str_contains($serviceSource, 'farm_contact_email_normalize'));
$pass('reminder cadence is explicit and bounded',
    str_contains($serviceSource, 'return [14, 7, 3, 1, 0];'));
$pass('worker is dry-run by default',
    str_contains($workerSource, '$send = in_array(\'--send\', $argv ?? [], true);')
    && str_contains($workerSource, "($send ? 'SEND' : 'DRY-RUN')"));

$mailSendCall = 'platform_' . 'mail_' . 'send(';
$pass('worker uses central mail transport',
    str_contains($workerSource, 'platform_mailer.php')
    && str_contains($workerSource, $mailSendCall));
$pass('worker targets only trial and active tenants',
    str_contains($workerSource, "subscription_status IN ('trial', 'active')"));
$pass('worker excludes platform-owner pseudo farm',
    str_contains($workerSource, "slug <> 'owner'"));
$pass('delivery ledger has idempotency unique key',
    str_contains($applySource, 'UNIQUE KEY uniq_subscription_reminder_delivery (farm_id, subscription_ends_at, days_left)'));
$pass('worker checks delivery ledger before mail send',
    ($checkPos = strpos($workerSource, 'SELECT id FROM subscription_renewal_reminder_deliveries')) !== false
    && ($sendPos = strpos($workerSource, $mailSendCall)) !== false
    && $checkPos < $sendPos);
$pass('worker records delivery only after successful mail send',
    ($sendPos = strpos($workerSource, $mailSendCall)) !== false
    && ($insertPos = strpos($workerSource, 'INSERT INTO subscription_renewal_reminder_deliveries')) !== false
    && $sendPos < $insertPos);

// Do not introspect this verifier's own source for the mail-call token: that
// token is necessarily referenced above to verify the worker. The safety
// property that matters is that this verifier never includes/executes the
// worker or mailer and contains no executable call expression.
$verifierSource = (string)file_get_contents(__FILE__);
$pass('verifier itself contains no executable mail send call',
    !preg_match('/^\s*platform_mail_send\s*\(/m', $verifierSource)
    && !str_contains($verifierSource, "require_once $worker")
    && !str_contains($verifierSource, "include $worker"));

foreach ($checks as [$label, $ok]) {
    echo ($ok ? 'PASS' : 'FAIL') . '  ' . $label . PHP_EOL;
}

echo PHP_EOL . count($checks) . ' checks, ' . count($failures) . ' failures.' . PHP_EOL;
if ($failures) exit(1);
echo 'V2.3 scheduled subscription renewal reminder contract passed.' . PHP_EOL;
