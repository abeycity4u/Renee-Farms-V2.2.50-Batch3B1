<?php
/**
 * Central subscription renewal reminder service.
 *
 * Reuses the in-app renewal notice as the canonical eligibility/date contract.
 * Email delivery and persistence are handled by the CLI worker.
 */
require_once __DIR__ . '/subscription_renewal_notice.php';
require_once __DIR__ . '/farm_contact_email.php';

if (!function_exists('subscription_renewal_reminder_days')) {
    function subscription_renewal_reminder_days(): array
    {
        return [14, 7, 3, 1, 0];
    }
}

if (!function_exists('subscription_renewal_reminder_candidate')) {
    function subscription_renewal_reminder_candidate(
        array $farm,
        ?DateTimeImmutable $today = null
    ): ?array {
        $notice = subscription_renewal_notice($farm, $today);
        if ($notice === null) return null;

        $daysLeft = (int)$notice['days_left'];
        if (!in_array($daysLeft, subscription_renewal_reminder_days(), true)) return null;

        try {
            $recipient = farm_contact_email_normalize($farm['contact_email'] ?? '');
        } catch (Throwable $e) {
            return null;
        }

        $farmName = trim((string)($farm['name'] ?? ''));
        if ($farmName === '') $farmName = 'your farm';

        $status = (string)$notice['status'];
        $subject = $status === 'trial'
            ? 'Your ' . $farmName . ' trial is ending soon'
            : 'Your ' . $farmName . ' subscription is ending soon';

        $body = "Hello,\n\n" . $notice['message'] . "\n\n"
            . "Farm: " . $farmName . "\n"
            . "Manage billing: https://reneefarms.com/billing/account.php\n\n"
            . "Regards,\nRenee Farms Platform";

        return [
            'farm_id' => (int)($farm['id'] ?? 0),
            'farm_name' => $farmName,
            'recipient' => $recipient,
            'status' => $status,
            'days_left' => $daysLeft,
            'subscription_ends_at' => trim((string)($farm['subscription_ends_at'] ?? '')),
            'subject' => $subject,
            'body' => $body,
        ];
    }
}
