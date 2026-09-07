<?php
/**
 * Static contract verifier for Farm Admin billing-contact self-service and
 * Platform Owner farm onboarding email.
 */

$root = dirname(__DIR__);
$files = [
    'contact' => $root . '/includes/farm_contact_email.php',
    'mailer' => $root . '/includes/platform_mailer.php',
    'onboarding' => $root . '/includes/farm_onboarding_mail.php',
    'billing' => $root . '/billing/account.php',
    'farms' => $root . '/management/farms.php',
];

$failures = 0;
$checks = 0;

$pass = static function (string $message) use (&$checks): void {
    $checks++;
    echo 'PASS: ' . $message . PHP_EOL;
};
$fail = static function (string $message) use (&$checks, &$failures): void {
    $checks++;
    $failures++;
    echo 'FAIL: ' . $message . PHP_EOL;
};
$assert = static function (bool $condition, string $message) use ($pass, $fail): void {
    $condition ? $pass($message) : $fail($message);
};

foreach ($files as $label => $path) {
    $assert(is_file($path), $label . ' file exists.');
}

$contact = is_file($files['contact']) ? file_get_contents($files['contact']) : '';
$mailer = is_file($files['mailer']) ? file_get_contents($files['mailer']) : '';
$onboarding = is_file($files['onboarding']) ? file_get_contents($files['onboarding']) : '';
$billing = is_file($files['billing']) ? file_get_contents($files['billing']) : '';
$farms = is_file($files['farms']) ? file_get_contents($files['farms']) : '';

$assert(str_contains($contact, 'function farm_contact_email_pair'), 'contact email fallback is centralized.');
$assert(str_contains($contact, "WHERE id = ? AND slug <> 'owner'"), 'contact email update is tenant-pinned and excludes owner workspace.');
$assert(str_contains($contact, 'WHERE id = ? AND farm_id = ?'), 'Farm Admin user backfill is tenant-pinned.');
$assert(str_contains($billing, 'billing_require_farm_admin_actor($pdo, false)'), 'billing contact edit requires canonical Farm Admin actor.');
$assert(str_contains($billing, 'require_valid_csrf_post()'), 'billing contact edit requires CSRF.');
$assert(str_contains($billing, "\$allowedEmailKeys = ['csrf_token', 'contact_email', 'save_contact_email']"), 'billing contact edit accepts only narrow form keys.');
$assert(!str_contains($billing, "\$_POST['farm_id']"), 'billing contact edit never accepts browser farm id.');
$assert(str_contains($billing, 'farm_contact_email_update('), 'billing workspace delegates email mutation to central service.');
$assert(str_contains($billing, 'Save a valid billing contact email above to enable subscription checkout.'), 'missing email is recoverable by Farm Admin on billing page.');
$assert(str_contains($farms, 'farm_contact_email_pair($rawOwnerEmail, $rawContactEmail)'), 'Platform Owner create/edit uses the same email-pair contract.');
$assert(str_contains($farms, "'contact_email' => \$contactEmail"), 'farm creation prepares canonical contact email for onboarding.');
$assert(str_contains($farms, 'farm_onboarding_send_credentials($onboardingPayload)'), 'farm creation delegates credential email to onboarding service.');
$assert(str_contains($farms, '$pdo->commit();') && strpos($farms, 'farm_onboarding_send_credentials($onboardingPayload)') > strpos($farms, '$pdo->commit();'), 'onboarding email is attempted only after farm transaction commit.');
$assert(str_contains($farms, 'The farm was created, but the credential email could not be sent.'), 'mail failure does not roll back a successfully created farm.');
$assert(str_contains($onboarding, "'password'") === false || !preg_match('/error_log\s*\([^;]*password/is', $onboarding), 'onboarding service never logs the password.');
$assert(str_contains($onboarding, "billing_route_public_url('/login.php')"), 'onboarding login URL uses configured canonical public base URL.');
$assert(str_contains($mailer, "return 'no-reply@reneefarms.com';"), 'mailer has a deterministic Renee Farms sender fallback.');
$assert(str_contains($mailer, 'mail($to, $subject, $body'), 'outbound mail uses one centralized transport call.');
$assert(!preg_match('/error_log\s*\([^;]*\$body/is', $mailer), 'mail transport never logs message body.');

echo 'Checks: ' . $checks . PHP_EOL;
echo 'Failures: ' . $failures . PHP_EOL;
if ($failures > 0) exit(1);
echo 'PASS: V2.3 billing contact self-service and farm onboarding mail contract is centralized.' . PHP_EOL;
