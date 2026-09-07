<?php
/**
 * Focused V2.3 subscription renewal notice verifier.
 * Pure/static checks only; no database, payment, mail or tenant mutation.
 */

$root = dirname(__DIR__);
$helper = $root . '/includes/subscription_renewal_notice.php';
$checks = [];
$failures = [];
$pass = static function (string $label, bool $ok) use (&$checks, &$failures): void {
    $checks[] = [$label, $ok];
    if (!$ok) $failures[] = $label;
};

$pass('renewal notice helper exists', is_file($helper));
if (is_file($helper)) require_once $helper;

$pass('notice window is centrally 14 days',
    function_exists('subscription_renewal_notice_window_days')
    && subscription_renewal_notice_window_days() === 14);

$today = new DateTimeImmutable('2026-09-08 00:00:00', new DateTimeZone(date_default_timezone_get()));

$active14 = subscription_renewal_notice([
    'subscription_status' => 'active',
    'subscription_ends_at' => '2026-09-22 23:59:59',
], $today);
$pass('active subscription enters notice window at 14 days',
    is_array($active14) && (int)$active14['days_left'] === 14);
$pass('active notice points to self-service billing',
    ($active14['action_path'] ?? '') === '/billing/account.php'
    && ($active14['action_label'] ?? '') === 'Renew subscription');
$pass('active notice no longer instructs customer to contact platform owner',
    !str_contains(strtolower((string)($active14['message'] ?? '')), 'platform owner'));

$active15 = subscription_renewal_notice([
    'subscription_status' => 'active',
    'subscription_ends_at' => '2026-09-23 23:59:59',
], $today);
$pass('active subscription outside 14-day window stays quiet', $active15 === null);

$trial3 = subscription_renewal_notice([
    'subscription_status' => 'trial',
    'subscription_ends_at' => '2026-09-11 23:59:59',
], $today);
$pass('trial receives subscription-choice notice',
    is_array($trial3)
    && ($trial3['action_label'] ?? '') === 'Review subscription'
    && str_contains((string)($trial3['message'] ?? ''), 'trial ends in 3 days'));
$pass('three-day threshold is urgent', ($trial3['severity'] ?? '') === 'danger');

$active7 = subscription_renewal_notice([
    'subscription_status' => 'active',
    'subscription_ends_at' => '2026-09-15 23:59:59',
], $today);
$pass('seven-day notice remains warning severity', ($active7['severity'] ?? '') === 'warning');

$endsToday = subscription_renewal_notice([
    'subscription_status' => 'active',
    'subscription_ends_at' => '2026-09-08 23:59:59',
], $today);
$pass('same-day expiry uses human-readable today wording',
    is_array($endsToday)
    && (int)$endsToday['days_left'] === 0
    && str_contains((string)$endsToday['message'], 'ends today'));

$pastDue = subscription_renewal_notice([
    'subscription_status' => 'past_due',
    'subscription_ends_at' => '2026-09-06 23:59:59',
], $today);
$pass('past_due is excluded from normal pre-expiry notice', $pastDue === null);

$suspended = subscription_renewal_notice([
    'subscription_status' => 'suspended',
    'subscription_ends_at' => '2026-09-20 23:59:59',
], $today);
$pass('suspended is excluded from normal pre-expiry notice', $suspended === null);

$noEnd = subscription_renewal_notice([
    'subscription_status' => 'active',
    'subscription_ends_at' => null,
], $today);
$pass('missing end date stays quiet', $noEnd === null);

$expiredDateStillActive = subscription_renewal_notice([
    'subscription_status' => 'active',
    'subscription_ends_at' => '2026-09-07 23:59:59',
], $today);
$pass('past end date is not rendered as renewal warning', $expiredDateStillActive === null);

foreach ($checks as [$label, $ok]) {
    echo ($ok ? 'PASS' : 'FAIL') . '  ' . $label . PHP_EOL;
}

echo PHP_EOL . count($checks) . ' checks, ' . count($failures) . ' failures.' . PHP_EOL;
if ($failures) exit(1);
echo 'V2.3 subscription renewal notice contract passed.' . PHP_EOL;
