<?php
/**
 * Scheduled subscription renewal reminder worker.
 *
 * Default mode is dry-run. Pass --send to send eligible reminders.
 * Requires CLI/cron mail environment to be loaded before execution.
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/platform_mailer.php';
require_once dirname(__DIR__) . '/includes/subscription_renewal_reminder.php';

$send = in_array('--send', $argv ?? [], true);
$today = new DateTimeImmutable('today');

try {
    $stmt = $pdo->query(
        "SELECT id, name, slug, contact_email, subscription_status, subscription_ends_at
         FROM farms
         WHERE slug <> 'owner'
           AND subscription_status IN ('trial', 'active')
           AND subscription_ends_at IS NOT NULL"
    );
    $farms = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: unable to load tenant subscriptions: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$eligible = 0;
$alreadySent = 0;
$sent = 0;
$failed = 0;
$skippedNoContact = 0;

foreach ($farms as $farm) {
    $candidate = subscription_renewal_reminder_candidate($farm, $today);
    if ($candidate === null) {
        $notice = subscription_renewal_notice($farm, $today);
        if ($notice !== null && in_array((int)$notice['days_left'], subscription_renewal_reminder_days(), true)) {
            $skippedNoContact++;
        }
        continue;
    }

    $eligible++;
    $check = $pdo->prepare(
        "SELECT id FROM subscription_renewal_reminder_deliveries
         WHERE farm_id = ? AND subscription_ends_at = ? AND days_left = ?
         LIMIT 1"
    );
    $check->execute([
        $candidate['farm_id'],
        $candidate['subscription_ends_at'],
        $candidate['days_left'],
    ]);
    if ($check->fetchColumn()) {
        $alreadySent++;
        continue;
    }

    if (!$send) {
        echo 'DRY-RUN: farm_id=' . $candidate['farm_id']
            . ' days_left=' . $candidate['days_left']
            . ' recipient=' . $candidate['recipient'] . PHP_EOL;
        continue;
    }

    $result = platform_mail_send(
        $candidate['recipient'],
        $candidate['subject'],
        $candidate['body']
    );

    if (($result['sent'] ?? false) !== true) {
        $failed++;
        fwrite(STDERR, 'FAIL: reminder mail rejected for farm_id=' . $candidate['farm_id'] . PHP_EOL);
        continue;
    }

    try {
        $record = $pdo->prepare(
            "INSERT INTO subscription_renewal_reminder_deliveries
             (farm_id, subscription_ends_at, days_left, recipient, sent_at)
             VALUES (?, ?, ?, ?, NOW())"
        );
        $record->execute([
            $candidate['farm_id'],
            $candidate['subscription_ends_at'],
            $candidate['days_left'],
            $candidate['recipient'],
        ]);
        $sent++;
    } catch (PDOException $e) {
        if ((string)$e->getCode() === '23000') {
            $alreadySent++;
            continue;
        }
        throw $e;
    }
}

echo PHP_EOL;
echo 'Mode:                    ' . ($send ? 'SEND' : 'DRY-RUN') . PHP_EOL;
echo 'Eligible reminders:      ' . $eligible . PHP_EOL;
echo 'Already sent:            ' . $alreadySent . PHP_EOL;
echo 'Sent now:                ' . $sent . PHP_EOL;
echo 'Mail failures:           ' . $failed . PHP_EOL;
echo 'Missing contact email:   ' . $skippedNoContact . PHP_EOL;

if ($failed > 0) exit(1);
echo ($send ? 'PASS: renewal reminder worker completed.' : 'PASS: dry-run completed; no email was sent.') . PHP_EOL;
