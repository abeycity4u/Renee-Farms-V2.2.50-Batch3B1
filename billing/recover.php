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
            'role' => (string)($definition['role'] ?? 'secondary'),
        ];
    }
} catch (Throwable $e) {
    error_log('Subscription recovery quote unavailable: ' . $e->getMessage());
    $error = 'Subscription recovery is temporarily unavailable. Please contact support if the problem continues.';
}

$farmName = htmlspecialchars((string)$recovery['farm_name'], ENT_QUOTES, 'UTF-8');
$statusLabel = ucfirst(strtolower((string)$recovery['subscription_status']));
$pricing = $reactivation['pricing'] ?? null;
$planLabel = $pricing ? subscription_plan_label((string)$pricing['plan_code']) : '';
$moduleLabels = $pricing ? array_map(static fn(string $m): string => ucfirst($m), $pricing['modules']) : [];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Subscription Recovery | <?= $farmName ?></title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif; background:#f4f7f5; color:#18352a; }
        .shell { max-width:760px; margin:0 auto; padding:36px 18px 56px; }
        .card { background:#fff; border:1px solid #dce8e1; border-radius:18px; box-shadow:0 16px 38px rgba(24,53,42,.09); padding:28px; }
        .eyebrow { font-size:12px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; color:#2d6a4f; }
        h1 { margin:8px 0 10px; font-size:clamp(1.7rem,4vw,2.25rem); }
        p { line-height:1.55; color:#526b61; }
        .notice { margin:20px 0; padding:14px 16px; border-radius:12px; background:#fff8e8; border:1px solid #f1dca5; color:#6d5520; }
        .error { background:#fff0f0; border-color:#efc8c8; color:#8b2929; }
        .summary { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; margin:22px 0; }
        .item { padding:14px; border-radius:12px; background:#f6faf8; border:1px solid #e5eee9; }
        .label { display:block; font-size:12px; color:#6d8279; margin-bottom:5px; }
        .value { font-weight:750; color:#193d2e; }
        fieldset { border:0; padding:0; margin:18px 0; }
        legend { font-weight:750; margin-bottom:10px; }
        .provider { display:flex; align-items:center; gap:10px; padding:12px 14px; border:1px solid #dce8e1; border-radius:12px; margin:9px 0; cursor:pointer; }
        .provider input { accent-color:#2d6a4f; }
        .primary { margin-left:auto; font-size:11px; font-weight:800; color:#2d6a4f; background:#eaf5ef; padding:4px 7px; border-radius:999px; }
        button { width:100%; border:0; border-radius:12px; padding:14px 18px; background:#2d6a4f; color:#fff; font-size:16px; font-weight:800; cursor:pointer; }
        .foot { margin-top:18px; text-align:center; font-size:13px; }
        .foot a { color:#2d6a4f; }
        @media (max-width:580px){ .summary{grid-template-columns:1fr;} .card{padding:22px 18px;} }
    </style>
</head>
<body>
<div class="shell">
    <div class="card">
        <div class="eyebrow">Restricted billing access</div>
        <h1>Restore <?= $farmName ?> access</h1>
        <p>Your Farm Admin credentials were verified. Operational records remain locked while the subscription is <?= htmlspecialchars(strtolower($statusLabel), ENT_QUOTES, 'UTF-8') ?>. This recovery workspace can only renew the current subscription.</p>

        <div class="notice">No farm records are deleted during suspension or past-due recovery. Successful verified payment restores the current plan, livestock bundle and purchased seat allowance.</div>

        <?php if ($error !== null): ?>
            <div class="notice error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php elseif (!$providers): ?>
            <div class="notice error">Payment recovery is temporarily unavailable. Please contact support and try again later.</div>
        <?php else: ?>
            <div class="summary">
                <div class="item"><span class="label">Farm</span><span class="value"><?= $farmName ?></span></div>
                <div class="item"><span class="label">Status</span><span class="value"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></span></div>
                <div class="item"><span class="label">Plan</span><span class="value"><?= htmlspecialchars($planLabel, ENT_QUOTES, 'UTF-8') ?></span></div>
                <div class="item"><span class="label">Billing interval</span><span class="value"><?= htmlspecialchars(ucfirst((string)$pricing['billing_interval']), ENT_QUOTES, 'UTF-8') ?></span></div>
                <div class="item"><span class="label">Livestock bundle</span><span class="value"><?= htmlspecialchars(implode(' + ', $moduleLabels), ENT_QUOTES, 'UTF-8') ?></span></div>
                <div class="item"><span class="label">Renewal amount</span><span class="value"><?= htmlspecialchars((string)$pricing['currency'], ENT_QUOTES, 'UTF-8') ?> <?= number_format((float)$pricing['amount'], 2) ?></span></div>
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
                    <?php foreach ($providers as $index => $provider): ?>
                        <label class="provider">
                            <input type="radio" name="provider" value="<?= htmlspecialchars($provider['code'], ENT_QUOTES, 'UTF-8') ?>" <?= $index === 0 ? 'checked' : '' ?> required>
                            <strong><?= htmlspecialchars($provider['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <?php if ($provider['role'] === 'primary'): ?><span class="primary">Primary</span><?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
                <button type="submit">Continue to secure payment</button>
            </form>
        <?php endif; ?>

        <p class="foot">Need a different plan or module bundle? Contact support before paying. <a href="<?= htmlspecialchars(BASE_URL . '/login.php', ENT_QUOTES, 'UTF-8') ?>">Return to sign in</a></p>
    </div>
</div>
</body>
</html>
