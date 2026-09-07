<?php
/**
 * V2.3 centralized pre-expiry subscription notice contract.
 *
 * Pure presentation/read-model logic only. It never mutates tenant state,
 * payments, sessions or subscription dates. Callers decide where/how to render.
 */

if (!function_exists('subscription_renewal_notice_window_days')) {
    function subscription_renewal_notice_window_days(): int
    {
        return 14;
    }
}

if (!function_exists('subscription_renewal_notice')) {
    function subscription_renewal_notice(
        array $farm,
        ?DateTimeImmutable $today = null
    ): ?array {
        $status = strtolower(trim((string)($farm['subscription_status'] ?? '')));
        if (!in_array($status, ['trial', 'active'], true)) return null;

        $rawEndsAt = trim((string)($farm['subscription_ends_at'] ?? ''));
        if ($rawEndsAt === '') return null;

        $timezone = new DateTimeZone(date_default_timezone_get());
        $today = $today ?: new DateTimeImmutable('today', $timezone);
        $today = $today->setTimezone($timezone)->setTime(0, 0, 0);

        try {
            $endsAt = new DateTimeImmutable($rawEndsAt, $timezone);
        } catch (Throwable $e) {
            return null;
        }
        $endDate = $endsAt->setTimezone($timezone)->setTime(0, 0, 0);
        $daysLeft = (int)$today->diff($endDate)->format('%r%a');

        if ($daysLeft < 0 || $daysLeft > subscription_renewal_notice_window_days()) {
            return null;
        }

        $when = $daysLeft === 0
            ? 'today'
            : 'in ' . $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's');

        if ($status === 'trial') {
            $message = 'Your trial ends ' . $when . '. Choose your subscription before access is paused.';
            $actionLabel = 'Review subscription';
        } else {
            $message = 'Your subscription ends ' . $when . '. Renew now to keep workspace access uninterrupted.';
            $actionLabel = 'Renew subscription';
        }

        return [
            'status' => $status,
            'days_left' => $daysLeft,
            'severity' => $daysLeft <= 3 ? 'danger' : 'warning',
            'message' => $message,
            'action_label' => $actionLabel,
            'action_path' => '/billing/account.php',
        ];
    }
}
