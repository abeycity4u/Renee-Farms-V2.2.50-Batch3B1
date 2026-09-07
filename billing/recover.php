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
    <style>
        :root {
            color-scheme: light;
            --ink:#173f30;
            --ink-soft:#526b61;
            --green:#2d6a4f;
            --green-dark:#21543e;
            --green-pale:#eef7f2;
            --line:#dce8e1;
            --surface:#ffffff;
            --page:#f3f7f5;
            --amber:#8a6418;
            --amber-bg:#fff9ea;
            --amber-line:#efd79b;
            --danger:#8b2929;
        }
        * { box-sizing:border-box; }
        body {
            margin:0;
            min-height:100vh;
            font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;
            color:var(--ink);
            background:
                radial-gradient(circle at 8% 8%, rgba(45,106,79,.09), transparent 28rem),
                radial-gradient(circle at 92% 18%, rgba(116,198,157,.10), transparent 24rem),
                linear-gradient(180deg,#f8fbf9 0%,var(--page) 100%);
        }
        .shell { width:min(900px,100%); margin:0 auto; padding:42px 20px 64px; }
        .card {
            overflow:hidden;
            background:var(--surface);
            border:1px solid var(--line);
            border-radius:24px;
            box-shadow:0 22px 55px rgba(23,63,48,.10);
        }
        .accent { height:5px; background:linear-gradient(90deg,var(--green-dark),#40916c); }
        .content { padding:34px; }
        .head { display:flex; gap:18px; align-items:flex-start; justify-content:space-between; }
        .eyebrow { font-size:12px; font-weight:800; letter-spacing:.10em; text-transform:uppercase; color:var(--green); }
        h1 { margin:7px 0 10px; font-size:clamp(1.85rem,4vw,2.5rem); line-height:1.12; letter-spacing:-.025em; }
        .lead { margin:0; max-width:68ch; color:var(--ink-soft); line-height:1.65; font-size:1rem; }
        .status-pill { flex:0 0 auto; white-space:nowrap; padding:8px 12px; border-radius:999px; background:#fff3cf; border:1px solid #edd387; color:#76530d; font-size:12px; font-weight:800; }
        .notice { display:flex; gap:12px; align-items:flex-start; margin:25px 0; padding:16px 17px; border-radius:14px; background:var(--amber-bg); border:1px solid var(--amber-line); color:#6f5419; }
        .notice-icon { flex:0 0 30px; width:30px; height:30px; display:grid; place-items:center; border-radius:50%; background:#fff2ca; font-weight:900; }
        .notice strong { display:block; color:#664b13; margin-bottom:3px; }
        .notice p { margin:0; color:#765d26; line-height:1.5; font-size:.93rem; }
        .notice.error { background:#fff0f0; border-color:#efc8c8; color:var(--danger); }
        .section-title { margin:27px 0 12px; font-size:1rem; font-weight:800; color:var(--ink); }
        .summary { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; }
        .item { min-height:82px; padding:15px 16px; border-radius:14px; background:#f8fbf9; border:1px solid #e2ece7; }
        .item.amount { background:var(--green-pale); border-color:#cfe5d8; }
        .label { display:block; font-size:12px; color:#70867c; margin-bottom:6px; }
        .value { display:block; font-weight:800; color:#193d2e; line-height:1.3; }
        .amount .value { font-size:1.08rem; color:#205b40; }
        fieldset { border:0; padding:0; margin:0; }
        legend { width:100%; margin:27px 0 12px; font-size:1rem; font-weight:800; color:var(--ink); }
        .providers { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }
        .provider { position:relative; display:flex; align-items:center; gap:11px; min-height:60px; padding:14px 16px; border:1px solid var(--line); border-radius:14px; background:#fff; cursor:pointer; transition:border-color .18s ease, box-shadow .18s ease, background .18s ease; }
        .provider:hover { border-color:#a9cdb9; background:#fbfefc; }
        .provider:has(input:checked) { border-color:#5b9b76; background:#f3faf6; box-shadow:0 0 0 3px rgba(45,106,79,.08); }
        .provider input { width:17px; height:17px; margin:0; accent-color:var(--green); }
        .provider strong { font-size:.97rem; }
        .secure-note { display:flex; gap:8px; align-items:center; margin:14px 2px 16px; color:#71867c; font-size:.83rem; line-height:1.4; }
        .secure-lock { width:20px; height:20px; display:grid; place-items:center; border-radius:50%; background:var(--green-pale); color:var(--green-dark); font-size:11px; font-weight:900; }
        button { width:100%; border:0; border-radius:13px; padding:14px 18px; background:linear-gradient(135deg,var(--green-dark),#347b59); color:#fff; font-size:16px; font-weight:800; cursor:pointer; box-shadow:0 10px 22px rgba(45,106,79,.20); transition:transform .18s ease, box-shadow .18s ease; }
        button:hover { transform:translateY(-1px); box-shadow:0 13px 27px rgba(45,106,79,.25); }
        button:focus-visible, .provider:focus-within, .foot a:focus-visible { outline:3px solid rgba(45,106,79,.22); outline-offset:3px; }
        .foot { margin:22px 0 0; padding-top:19px; border-top:1px solid #e9f0ec; text-align:center; color:#70867c; font-size:13px; line-height:1.55; }
        .foot a { color:var(--green); font-weight:700; }
        @media (max-width:720px) {
            .shell { padding:22px 12px 40px; }
            .content { padding:25px 20px; }
            .head { display:block; }
            .status-pill { display:inline-block; margin-top:8px; }
            .summary { grid-template-columns:repeat(2,minmax(0,1fr)); }
        }
        @media (max-width:500px) {
            .summary, .providers { grid-template-columns:1fr; }
            .item { min-height:auto; }
        }
    </style>
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
