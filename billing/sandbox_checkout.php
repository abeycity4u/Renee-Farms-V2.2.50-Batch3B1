<?php
/**
 * V2.3 Billing Stage 2I temporary provider sandbox checkout launcher.
 *
 * This page is intentionally not a general subscription purchase UI. It exists
 * only to drive one controlled provider test through the already-hardened
 * billing/checkout.php route. It performs no billing DML and no provider call.
 */

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/includes/billing_payment_foundation.php';
require_once dirname(__DIR__) . '/includes/billing_pricing_contract.php';
require_once dirname(__DIR__) . '/includes/billing_provider_readiness.php';
require_once dirname(__DIR__) . '/includes/farm_entitlements.php';
require_once dirname(__DIR__) . '/includes/subscription_seat_policy.php';

requireLogin();
$farmId = requireCurrentFarmId();
if (isPlatformOwner() || !hasRole('farm_admin')) {
    http_response_code(403);
    exit('Farm Admin access is required for sandbox billing QA.');
}

if (billing_provider_payment_mode() !== 'test') {
    http_response_code(404);
    exit('Not found.');
}
if (billing_provider_readiness_env_value('BILLING_SANDBOX_LAUNCHER_ENABLED') !== '1') {
    http_response_code(404);
    exit('Not found.');
}

$qaFarmRaw = billing_provider_readiness_env_value('BILLING_SANDBOX_QA_FARM_ID');
$qaFarmId = filter_var($qaFarmRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($qaFarmId === false || (int)$qaFarmId !== $farmId) {
    http_response_code(403);
    exit('This tenant is not the designated sandbox billing QA farm.');
}

$providerRaw = billing_provider_readiness_env_value('BILLING_SANDBOX_QA_PROVIDER');
$provider = strtolower(trim((string)$providerRaw));
if (!in_array($provider, billing_provider_selection_codes(), true)) {
    http_response_code(503);
    exit('A supported sandbox billing QA provider must be explicitly selected.');
}
$providerDefinition = billing_provider_selection_definition($provider);
$providerLabel = trim((string)($providerDefinition['label'] ?? ucfirst($provider)));
if ($providerLabel === '') $providerLabel = ucfirst($provider);

$readiness = billing_provider_readiness_status($provider, true);
if (($readiness['mode'] ?? null) !== 'test' || ($readiness['ready'] ?? false) !== true) {
    http_response_code(503);
    exit($providerLabel . ' test billing is not ready for sandbox checkout.');
}

$farm = currentFarm();
if (!$farm || (int)($farm['id'] ?? 0) !== $farmId) {
    http_response_code(503);
    exit('Current tenant farm could not be resolved for sandbox billing QA.');
}
$customerEmail = strtolower(trim((string)($farm['contact_email'] ?? '')));
if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(409);
    exit('A valid farm contact email is required before sandbox billing QA.');
}

$modules = array_values(array_intersect(['poultry', 'ruminant'], farm_entitlement_modules($pdo, $farmId)));
sort($modules, SORT_STRING);
if (!$modules) {
    http_response_code(409);
    exit('The designated QA farm must already have Poultry, Ruminant, or both enabled.');
}

$planOrder = ['starter', 'growth', 'pro'];
$currentPlan = strtolower(trim((string)($farm['subscription_plan'] ?? 'starter')));
if (!subscription_plan_is_valid($currentPlan)) $currentPlan = 'starter';
$currentIndex = array_search($currentPlan, $planOrder, true);
if ($currentIndex === false) $currentIndex = 0;

$seatAddOns = subscription_seat_load_addons($pdo, $farmId, $currentPlan, $modules);
$selectedPlan = null;
for ($i = (int)$currentIndex; $i < count($planOrder); $i++) {
    $candidate = $planOrder[$i];
    try {
        subscription_seat_assert_capacity($pdo, $farmId, $candidate, $modules, $seatAddOns);
        $selectedPlan = $candidate;
        break;
    } catch (RuntimeException $capacityError) {
        // Try the next larger plan before considering any extra-seat increase.
    }
}

if ($selectedPlan === null) {
    $selectedPlan = 'pro';
    $used = subscription_seat_used_role_counts($pdo, $farmId);
    $included = subscription_plan_included_role_limits($selectedPlan, $modules);
    foreach (subscription_seat_roles() as $role => $_label) {
        if (!subscription_seat_role_relevant($role, $modules)) continue;
        $needed = max(0, (int)($used[$role] ?? 0) - (int)($included[$role] ?? 0));
        $seatAddOns[$role] = max((int)($seatAddOns[$role] ?? 0), $needed);
    }
    subscription_seat_assert_capacity($pdo, $farmId, $selectedPlan, $modules, $seatAddOns);
}

$seatAddOns = subscription_seat_normalize_addons($seatAddOns);
$billingInterval = 'monthly';
$pricedQuote = billing_pricing_build_payment_quote($selectedPlan, $billingInterval, $modules, $seatAddOns);
$pricing = $pricedQuote['pricing'];

$planLabel = subscription_plan_label($selectedPlan);
$moduleLabels = array_map(static fn(string $module): string => ucfirst($module), $modules);
$amount = number_format((float)$pricing['amount'], 2);
$currency = htmlspecialchars((string)$pricing['currency'], ENT_QUOTES, 'UTF-8');
$farmName = htmlspecialchars(trim((string)($farm['name'] ?? '')) ?: 'QA Farm', ENT_QUOTES, 'UTF-8');
$providerLabelHtml = htmlspecialchars($providerLabel, ENT_QUOTES, 'UTF-8');
$providerHtml = htmlspecialchars($provider, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sandbox Billing QA</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL . versioned_asset('/assets/css/sandbox-checkout-page.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<div class="card">
    <span class="badge"><?= strtoupper($providerLabelHtml) ?> TEST MODE</span>
    <h1>Sandbox Billing QA</h1>
    <p>This launcher is restricted to the designated QA tenant. <?= $providerLabelHtml ?> test mode does not use real funds.</p>

    <div class="grid">
        <div class="item"><span class="label">Farm</span><?= $farmName ?></div>
        <div class="item"><span class="label">Provider</span><?= $providerLabelHtml ?></div>
        <div class="item"><span class="label">Plan</span><?= htmlspecialchars($planLabel, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="item"><span class="label">Billing interval</span>Monthly</div>
        <div class="item"><span class="label">Modules preserved</span><?= htmlspecialchars(implode(' + ', $moduleLabels), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="item"><span class="label">Server-authoritative test amount</span><?= $currency ?> <?= $amount ?></div>
    </div>

    <div class="warning">
        Completing the test payment will exercise the real Renee Farms billing database path for this QA tenant: verified payment, subscription history, entitlement application and seat-limit recalculation. Operational farm records are not deleted.
    </div>

    <form method="post" action="<?= htmlspecialchars(BASE_URL . '/billing/checkout.php', ENT_QUOTES, 'UTF-8') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="plan_code" value="<?= htmlspecialchars($selectedPlan, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="billing_interval" value="monthly">
        <?php foreach ($modules as $module): ?>
            <input type="hidden" name="modules[]" value="<?= htmlspecialchars($module, ENT_QUOTES, 'UTF-8') ?>">
        <?php endforeach; ?>
        <?php foreach ($seatAddOns as $role => $count): ?>
            <input type="hidden" name="seat_addons[<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>]" value="<?= (int)$count ?>">
        <?php endforeach; ?>
        <input type="hidden" name="provider" value="<?= $providerHtml ?>">
        <button type="submit">Start <?= $providerLabelHtml ?> Test Checkout</button>
    </form>

    <p class="sandbox-checkout-warning">Do not use this launcher for production billing. Disable the sandbox launcher environment flags after Stage 2I provider QA.</p>
</div>
</body>
</html>
