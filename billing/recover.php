<?php
/**
 * V2.3 tenant subscription recovery workspace.
 *
 * This page accepts only the short-lived recovery session created after the
 * protected Farm Admin re-enters valid credentials for a suspended/cancelled/
 * past-due tenant. It never opens operational records.
 */

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/includes/subscription_recovery.php';
require_once dirname(__DIR__) . '/includes/billing_payment_foundation.php';
require_once dirname(__DIR__) . '/includes/billing_reactivation_quote.php';
require_once dirname(__DIR__) . '/includes/billing_provider_selection.php';
require_once dirname(__DIR__) . '/includes/billing_provider_readiness.php';

$recovery = subscription_recovery_require($pdo, ['suspended', 'cancelled', 'past_due', 'active']);
if (strtolower((string)$recovery['subscription_status']) === 'active') {
    subscription_recovery_promote_to_login($pdo);
    $_SESSION['success'] = 'Subscription active. Welcome back to your farm workspace.';
    header('Location: ' . BASE_URL . '/dashboard.php', true, 303);
    exit();
}

$error = null;
$reactivation = null;
$providers = [];
try {
    $reactivation = billing_reactivation_quote($pdo, (int)$recovery['farm_id']);
    foreach (billing_provider_selection_codes() as $provider) {
        $status = billing_provider_readiness_status($provider, false);
        if (($status['ready'] ?? false) !== true) continue;
        $definition = billing_provider_selection_definition($provider);
        $providers[] = [
            'code' => $provider,
            'label' => (string)($definition['label'] ?? ucfirst($provider)),
        ];
    }
} catch (Throwable $e) {
    error_log('Subscription recovery quote unavailable: ' . $e->getMessage());
    $error = 'Subscription recovery is temporarily unavailable. Please contact support if the problem continues.';
}

$farmName = htmlspecialchars((string)$recovery['farm_name'], ENT_QUOTES, 'UTF-8');
$statusCode = strtolower(trim((string)$recovery['subscription_status']));
$statusLabels = [
    'past_due' => 'Past due',
    'suspended' => 'Suspended',
    'cancelled' => 'Cancelled',
    'active' => 'Active',
];
$statusLabel = $statusLabels[$statusCode] ?? ucfirst(str_replace('_', ' ', $statusCode));
$pricing = $reactivation['pricing'] ?? null;
$planLabel = $pricing ? subscription_plan_label((string)$pricing['plan_code']) : '';
$moduleLabels = $pricing ? array_map(static fn(string $m): string => ucfirst($m), $pricing['modules']) : [];

if ($statusCode === 'past_due') {
    $introCopy = 'Your subscription has expired, so operational access is temporarily paused. Your farm records remain safe and unchanged. Renew the current subscription below to restore workspace access.';
} else {
    $introCopy = 'Operational access is temporarily paused for this farm. Your records remain safe and unchanged. Renew the current subscription below to restore workspace access.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#173f30">
    <title>Restore subscription | <?= $farmName ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL . versioned_asset('/assets/css/subscription-recovery-page.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<div class="shell">
    <main class="card">
        <div class="accent"></div>
        <div class="content">
            <div class="head">
                <div>
                    <div class="eyebrow">Subscription recovery</div>
                    <h1>Restore <?= $farmName ?> access</h1>
                    <p class="lead"><?= htmlspecialchars($introCopy, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <span class="status-pill"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <div class="notice">
                <div class="notice-icon">✓</div>
                <div>
                    <strong>Your farm data is safe</strong>
                    <p>Renewal does not replace your plan or livestock setup. After a successful verified payment, your current plan, livestock bundle and purchased seat allowances are restored with workspace access.</p>
                </div>
            </div>

            <?php if ($error !== null): ?>
                <div class="notice error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php elseif (!$providers): ?>
                <div class="notice error">Payment recovery is temporarily unavailable. Please contact support and try again later.</div>
            <?php else: ?>
                <div class="section-title">Subscription to renew</div>
                <div class="summary">
                    <div class="item"><span class="label">Farm</span><span class="value"><?= $farmName ?></span></div>
                    <div class="item"><span class="label">Status</span><span class="value"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></span></div>
                    <div class="item"><span class="label">Plan</span><span class="value"><?= htmlspecialchars($planLabel, ENT_QUOTES, 'UTF-8') ?></span></div>
                    <div class="item"><span class="label">Billing interval</span><span class="value"><?= htmlspecialchars(ucfirst((string)$pricing['billing_interval']), ENT_QUOTES, 'UTF-8') ?></span></div>
                    <div class="item"><span class="label">Livestock bundle</span><span class="value"><?= htmlspecialchars(implode(' + ', $moduleLabels), ENT_QUOTES, 'UTF-8') ?></span></div>
                    <div class="item amount"><span class="label">Renewal amount</span><span class="value"><?= htmlspecialchars((string)$pricing['currency'], ENT_QUOTES, 'UTF-8') ?> <?= number_format((float)$pricing['amount'], 2) ?></span></div>
                </div>

                <form method="post" action="<?= htmlspecialchars(BASE_URL . '/billing/checkout.php', ENT_QUOTES, 'UTF-8') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="plan_code" value="<?= htmlspecialchars((string)$pricing['plan_code'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="billing_interval" value="<?= htmlspecialchars((string)$pricing['billing_interval'], ENT_QUOTES, 'UTF-8') ?>">
                    <?php foreach ($pricing['modules'] as $module): ?>
                        <input type="hidden" name="modules[]" value="<?= htmlspecialchars((string)$module, ENT_QUOTES, 'UTF-8') ?>">
                    <?php endforeach; ?>
                    <?php foreach ($pricing['seat_addons'] as $role => $count): ?>
                        <input type="hidden" name="seat_addons[<?= htmlspecialchars((string)$role, ENT_QUOTES, 'UTF-8') ?>]" value="<?= (int)$count ?>">
                    <?php endforeach; ?>

                    <fieldset>
                        <legend>Choose payment provider</legend>
                        <div class="providers">
                            <?php foreach ($providers as $index => $provider): ?>
                                <label class="provider">
                                    <input type="radio" name="provider" value="<?= htmlspecialchars($provider['code'], ENT_QUOTES, 'UTF-8') ?>" <?= $index === 0 ? 'checked' : '' ?> required>
                                    <strong><?= htmlspecialchars($provider['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <div class="secure-note"><span class="secure-lock">✓</span><span>You’ll continue to your selected payment provider to complete payment securely.</span></div>
                    <button type="submit">Continue to secure payment</button>
                </form>
            <?php endif; ?>

            <p class="foot">Need a different plan or livestock bundle? Contact support before paying. &nbsp; <a href="<?= htmlspecialchars(BASE_URL . '/login.php', ENT_QUOTES, 'UTF-8') ?>">Return to sign in</a></p>
        </div>
    </main>
</div>
</body>
</html>
