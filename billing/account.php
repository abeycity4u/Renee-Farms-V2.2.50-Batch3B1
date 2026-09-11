<?php
/**
 * V2.3 tenant Billing & Subscription workspace.
 *
 * Account information comes from the centralized billing account read model.
 * The only local mutation is the tenant-pinned billing/contact email update;
 * subscription payment still delegates to the canonical checkout route, which
 * recalculates price server-side and owns payment-attempt creation.
 */

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/includes/billing_tenant_actor.php';
require_once dirname(__DIR__) . '/includes/billing_account_overview.php';
require_once dirname(__DIR__) . '/includes/billing_provider_selection.php';
require_once dirname(__DIR__) . '/includes/billing_provider_readiness.php';
require_once dirname(__DIR__) . '/includes/farm_contact_email.php';

$actor = billing_require_farm_admin_actor($pdo, false);
$farmId = (int)$actor['farm_id'];
$emailFormError = null;
$emailFormValue = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_post();

    $allowedEmailKeys = ['csrf_token', 'contact_email', 'save_contact_email'];
    foreach (array_keys($_POST) as $key) {
        if (!in_array((string)$key, $allowedEmailKeys, true)) {
            http_response_code(422);
            exit('Invalid billing contact update request.');
        }
    }
    if (!isset($_POST['save_contact_email'])) {
        http_response_code(422);
        exit('Invalid billing contact update request.');
    }

    $emailFormValue = trim((string)($_POST['contact_email'] ?? ''));
    try {
        $updated = farm_contact_email_update(
            $pdo,
            $farmId,
            (int)$actor['user_id'],
            $emailFormValue
        );
        $_SESSION['success'] = 'Billing contact email updated to ' . $updated['contact_email'] . '.';
        header('Location: ' . BASE_URL . '/billing/account.php', true, 303);
        exit();
    } catch (InvalidArgumentException $e) {
        $emailFormError = $e->getMessage();
    } catch (Throwable $e) {
        error_log('Farm billing contact email update failed for farm ' . $farmId . ': ' . $e->getMessage());
        $emailFormError = 'Unable to update the billing contact email right now. Please try again.';
    }
}

$pageError = null;
$overview = null;
$providers = [];
$seatTopupStorageReady = false;

try {
    $overview = billing_account_overview($pdo, $farmId);
    $seatTopupStorageReady =
        ($overview['seat_change_ready'] ?? false)
        === true;
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
    error_log('Billing account overview unavailable: ' . $e->getMessage());
    $pageError = 'Billing account information is temporarily unavailable. Please try again later.';
}

$farm = $overview['farm'] ?? currentFarm();
$farmName = (string)($farm['name'] ?? farmBrandName());
$status = strtolower(trim((string)($farm['subscription_status'] ?? 'unknown')));
$statusLabel = ucwords(str_replace('_', ' ', $status));
$pricing = $overview['pricing'] ?? null;
$modules = $overview['modules'] ?? [];
$moduleLabel = $modules ? implode(' + ', array_map(static fn(string $m): string => ucfirst($m), $modules)) : '—';
$subscriptionEndsAt = $farm['subscription_ends_at'] ?? null;
$contactEmail = $emailFormValue !== null
    ? strtolower(trim($emailFormValue))
    : strtolower(trim((string)($farm['contact_email'] ?? '')));
$checkoutReady = $overview !== null
    && ($overview['payment_foundation_ready'] ?? false) === true
    && !empty($providers)
    && filter_var($contactEmail, FILTER_VALIDATE_EMAIL);

$seatTopupReady = $checkoutReady
    && $seatTopupStorageReady
    && $status === 'active'
    && !empty($overview['seat_summary']);

$renewalSeatTarget =
    is_array(
        $overview['renewal_seat_target']
            ?? null
    )
        ? $overview['renewal_seat_target']
        : null;

$renewalExtras =
    is_array(
        $renewalSeatTarget[
            'renewal_seat_addons'
        ] ?? null
    )
        ? $renewalSeatTarget[
            'renewal_seat_addons'
        ]
        : [];

$scheduledReductions = [];
$reductionCandidates = [];

foreach (($overview['seat_summary'] ?? []) as $seat) {
    $role =
        (string)($seat['role'] ?? '');

    $currentExtra =
        max(0, (int)($seat['extra'] ?? 0));

    $renewalExtra =
        array_key_exists(
            $role,
            $renewalExtras
        )
            ? max(
                0,
                (int)$renewalExtras[$role]
            )
            : $currentExtra;

    if ($renewalExtra < $currentExtra) {
        $seat['renewal_extra'] =
            $renewalExtra;

        $seat['removal_quantity'] =
            $currentExtra - $renewalExtra;

        $scheduledReductions[] =
            $seat;

        continue;
    }

    if ($currentExtra > 0) {
        $reductionCandidates[] =
            $seat;
    }
}

$seatReductionReady =
    $status === 'active'
    && $seatTopupStorageReady
    && !empty($reductionCandidates);

$buttonLabel = match ($status) {
    'trial' => 'Start paid subscription',
    'past_due' => 'Resolve subscription payment',
    default => 'Renew current subscription',
};

$statusClass = static function (string $value): string {
    return match (strtolower($value)) {
        'active', 'paid' => 'success',
        'trial', 'initialized' => 'info',
        'past_due', 'pending' => 'warning',
        'failed' => 'danger',
        'cancelled', 'refunded' => 'secondary',
        default => 'light',
    };
};

$formatDate = static function ($value): string {
    $value = trim((string)$value);
    if ($value === '') return '—';
    $time = strtotime($value);
    return $time === false ? $value : date('d M Y', $time);
};

$decodeModules = static function ($json): string {
    $decoded = json_decode((string)$json, true);
    if (!is_array($decoded)) return '—';
    $clean = array_values(array_intersect(['poultry', 'ruminant'], array_map('strtolower', $decoded)));
    return $clean ? implode(' + ', array_map('ucfirst', $clean)) : '—';
};
?>
<!doctype html>
<html lang="en">
<head>
    <?php include dirname(__DIR__) . '/navbar_head.php'; ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Billing &amp; Subscription | <?= htmlspecialchars($farmName, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/css/billing-account-page.css'); ?>">
</head>
<body>
<?php include dirname(__DIR__) . '/navbar.php'; ?>

<div class="billing-shell">
    <div class="card billing-hero mb-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <div class="text-uppercase small fw-semibold opacity-75 mb-1">Farm Admin · Billing</div>
                    <h1 class="h3 mb-2">Billing &amp; Subscription</h1>
                    <p class="mb-0 opacity-75">Review <?= htmlspecialchars($farmName, ENT_QUOTES, 'UTF-8') ?> subscription, seat allowance and payment history.</p>
                </div>
                <a class="btn btn-light fw-semibold" href="<?= htmlspecialchars(BASE_URL . '/dashboard.php', ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-arrow-left me-1"></i> Back to dashboard</a>
            </div>
        </div>
    </div>

    <?php if ($pageError !== null): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($pageError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php else: ?>
        <div class="row g-3 mb-4">
            <div class="col-md-6 col-xl-3"><div class="card billing-card h-100"><div class="card-body"><div class="metric-label">Status</div><div class="metric-value mt-1"><span class="badge text-bg-<?= htmlspecialchars($statusClass($status), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></span></div></div></div></div>
            <div class="col-md-6 col-xl-3"><div class="card billing-card h-100"><div class="card-body"><div class="metric-label">Plan</div><div class="metric-value mt-1"><?= htmlspecialchars((string)$overview['plan_label'], ENT_QUOTES, 'UTF-8') ?></div></div></div></div>
            <div class="col-md-6 col-xl-3"><div class="card billing-card h-100"><div class="card-body"><div class="metric-label">Livestock bundle</div><div class="metric-value mt-1"><?= htmlspecialchars($moduleLabel, ENT_QUOTES, 'UTF-8') ?></div></div></div></div>
            <div class="col-md-6 col-xl-3"><div class="card billing-card h-100"><div class="card-body"><div class="metric-label">Current period ends</div><div class="metric-value mt-1"><?= htmlspecialchars($formatDate($subscriptionEndsAt), ENT_QUOTES, 'UTF-8') ?></div></div></div></div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-7">
                <div class="card billing-card h-100">
                    <div class="card-header bg-transparent border-0 pt-3 px-3"><h2 class="h5 mb-0">Current subscription</h2></div>
                    <div class="card-body pt-2">
                        <div class="row g-3">
                            <div class="col-sm-6"><div class="metric-label">Billing interval</div><div class="metric-value mt-1"><?= htmlspecialchars(ucfirst((string)$pricing['billing_interval']), ENT_QUOTES, 'UTF-8') ?></div></div>
                            <div class="col-sm-6"><div class="metric-label">Current renewal price</div><div class="metric-value mt-1"><?= htmlspecialchars((string)$pricing['currency'], ENT_QUOTES, 'UTF-8') ?> <?= number_format((float)$pricing['amount'], 2) ?></div></div>
                        </div>
                        <div class="billing-note mt-3">The renewal price is calculated from the current server-side price book. This page never sends an amount, currency or tenant id to checkout.</div>
                        <div class="mt-3 small text-muted">Need a different plan, livestock bundle or seat package? Contact support before renewing. This first self-service screen renews the product currently assigned to your farm.</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card billing-card h-100">
                    <div class="card-header bg-transparent border-0 pt-3 px-3"><h2 class="h5 mb-0">Renew securely</h2></div>
                    <div class="card-body pt-2">
                        <div class="billing-email-box mb-3">
                            <form method="post" action="<?= htmlspecialchars(BASE_URL . '/billing/account.php', ENT_QUOTES, 'UTF-8') ?>">
                                <?= csrf_field() ?>
                                <label class="form-label fw-semibold" for="billingContactEmail">Billing contact email</label>
                                <div class="input-group">
                                    <input
                                        id="billingContactEmail"
                                        class="form-control"
                                        type="email"
                                        name="contact_email"
                                        maxlength="254"
                                        autocomplete="email"
                                        value="<?= htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8') ?>"
                                        placeholder="owner@example.com"
                                        required
                                    >
                                    <button class="btn btn-success fw-semibold" type="submit" name="save_contact_email" value="1">Save email</button>
                                </div>
                                <div class="form-text">Used for secure checkout and billing notices. Saving here also updates the farm contact email visible to the Platform Owner.</div>
                            </form>
                        </div>

                        <?php if ($emailFormError !== null): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($emailFormError, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>

                        <?php if (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)): ?>
                            <div class="alert alert-warning mb-0">Save a valid billing contact email above to enable subscription checkout.</div>
                        <?php elseif (!$overview['payment_foundation_ready']): ?>
                            <div class="alert alert-warning mb-0">Billing payment storage is temporarily unavailable. Please try again later.</div>
                        <?php elseif (!$providers): ?>
                            <div class="alert alert-warning mb-0">No payment provider is currently available. Please try again later.</div>
                        <?php else: ?>
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
                                <?php foreach ($providers as $index => $provider): ?>
                                    <label class="provider-option">
                                        <input class="form-check-input mt-0" type="radio" name="provider" value="<?= htmlspecialchars($provider['code'], ENT_QUOTES, 'UTF-8') ?>" <?= $index === 0 ? 'checked' : '' ?> required>
                                        <strong><?= htmlspecialchars($provider['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        <?php if ($provider['role'] === 'primary'): ?><span class="badge text-bg-success provider-primary">Primary</span><?php endif; ?>
                                    </label>
                                <?php endforeach; ?>
                                <button type="submit" class="btn btn-success w-100 fw-semibold mt-2" <?= $checkoutReady ? '' : 'disabled' ?>><?= htmlspecialchars($buttonLabel, ENT_QUOTES, 'UTF-8') ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card billing-card mb-4">
            <div class="card-header bg-transparent border-0 pt-3 px-3"><h2 class="h5 mb-0">Seat allowance</h2></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Role</th><th>Used</th><th>Effective limit</th><th>Purchased extra</th></tr></thead>
                    <tbody>
                    <?php foreach ($overview['seat_summary'] as $seat): ?>
                        <tr>
                            <td class="fw-semibold"><?= htmlspecialchars((string)$seat['label'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= (int)$seat['used'] ?></td>
                            <td class="seat-meter"><?= (int)$seat['limit'] ?></td>
                            <td><?= (int)$seat['extra'] > 0 ? '+' . (int)$seat['extra'] : 'None' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="card-footer bg-transparent border-0 px-3 pb-3">
                <div class="border rounded-3 p-3">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                        <div>
                            <h3 class="h6 mb-1">Add extra seats</h3>
                            <div class="small text-muted">
                                Buy additional seats for the remaining paid subscription period.
                            </div>
                        </div>
                    </div>

                    <?php if ($status !== 'active'): ?>
                        <div class="alert alert-info mb-0">
                            Seat top-up is available while the paid subscription is active.
                        </div>
                    <?php elseif (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)): ?>
                        <div class="alert alert-warning mb-0">
                            Save a valid billing contact email above to enable seat top-up checkout.
                        </div>
                    <?php elseif (!$seatTopupStorageReady): ?>
                        <div class="alert alert-warning mb-0">
                            Seat top-up service is temporarily unavailable. Please try again later.
                        </div>
                    <?php elseif (!$providers): ?>
                        <div class="alert alert-warning mb-0">
                            No payment provider is currently available. Please try again later.
                        </div>
                    <?php elseif (empty($overview['seat_summary'])): ?>
                        <div class="alert alert-info mb-0">
                            No additional seat roles are available for the current livestock bundle.
                        </div>
                    <?php else: ?>
                        <form
                            method="post"
                            action="<?= htmlspecialchars(BASE_URL . '/billing/seat_topup_checkout.php', ENT_QUOTES, 'UTF-8') ?>"
                            class="row g-3 align-items-end"
                        >
                            <?= csrf_field() ?>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold" for="seatTopupRole">
                                    Role
                                </label>
                                <select
                                    id="seatTopupRole"
                                    class="form-select"
                                    name="role_code"
                                    required
                                >
                                    <?php foreach ($overview['seat_summary'] as $seat): ?>
                                        <option value="<?= htmlspecialchars((string)$seat['role'], ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars((string)$seat['label'], ENT_QUOTES, 'UTF-8') ?>
                                            · currently <?= (int)$seat['limit'] ?> allowed
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold" for="seatTopupQuantity">
                                    Extra seats
                                </label>
                                <input
                                    id="seatTopupQuantity"
                                    class="form-control"
                                    type="number"
                                    name="quantity"
                                    min="1"
                                    max="500"
                                    step="1"
                                    value="1"
                                    inputmode="numeric"
                                    required
                                >
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold" for="seatTopupProvider">
                                    Payment provider
                                </label>
                                <select
                                    id="seatTopupProvider"
                                    class="form-select"
                                    name="provider"
                                    required
                                >
                                    <?php foreach ($providers as $provider): ?>
                                        <option value="<?= htmlspecialchars($provider['code'], ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($provider['label'], ENT_QUOTES, 'UTF-8') ?>
                                            <?= $provider['role'] === 'primary' ? ' · Primary' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <button
                                    type="submit"
                                    class="btn btn-success w-100 fw-semibold"
                                    <?= $seatTopupReady ? '' : 'disabled' ?>
                                >
                                    Buy seats
                                </button>
                            </div>
                        </form>

                        <div class="form-text mt-2">
                            The final prorated price is calculated securely on the server from the current plan and remaining paid period. This form never sends an amount, currency or tenant id.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card billing-card mb-4">
            <div class="card-header bg-transparent border-0 pt-3 px-3">
                <h2 class="h5 mb-0">Reduce extra seats at renewal</h2>
                <div class="small text-muted">
                    Scheduled reductions affect the next renewal only. Current paid-term seat limits stay unchanged and no refund is created.
                </div>
            </div>
            <div class="card-body pt-2">
                <?php if ($scheduledReductions): ?>
                    <div class="alert alert-info">
                        <div class="fw-semibold mb-2">Scheduled for next renewal</div>
                        <?php foreach ($scheduledReductions as $seat): ?>
                            <div class="d-flex justify-content-between gap-3 flex-wrap">
                                <span>
                                    <?= htmlspecialchars((string)$seat['label'], ENT_QUOTES, 'UTF-8') ?>:
                                    purchased extra
                                    <?= (int)$seat['extra'] ?>
                                    → <?= (int)$seat['renewal_extra'] ?>
                                </span>
                                <span class="text-nowrap">
                                    Effective
                                    <?= htmlspecialchars(
                                        $formatDate(
                                            $renewalSeatTarget['current_period_ends_at']
                                                ?? null
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($status !== 'active'): ?>
                    <div class="alert alert-info mb-0">
                        Seat reduction can be scheduled while the paid subscription is active.
                    </div>
                <?php elseif (!$seatTopupStorageReady): ?>
                    <div class="alert alert-warning mb-0">
                        Seat-change service is temporarily unavailable. Please try again later.
                    </div>
                <?php elseif (!$reductionCandidates): ?>
                    <div class="alert alert-light border mb-0">
                        There are no additional purchased extra-seat roles available to reduce right now.
                    </div>
                <?php else: ?>
                    <form
                        method="post"
                        action="<?= htmlspecialchars(BASE_URL . '/billing/seat_reduction_schedule.php', ENT_QUOTES, 'UTF-8') ?>"
                        class="row g-3 align-items-end"
                    >
                        <?= csrf_field() ?>

                        <div class="col-md-5">
                            <label class="form-label fw-semibold" for="seatReductionRole">
                                Role
                            </label>
                            <select
                                id="seatReductionRole"
                                class="form-select"
                                name="role_code"
                                required
                            >
                                <?php foreach ($reductionCandidates as $seat): ?>
                                    <option value="<?= htmlspecialchars((string)$seat['role'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars((string)$seat['label'], ENT_QUOTES, 'UTF-8') ?>
                                        · <?= (int)$seat['extra'] ?> purchased extra
                                        · <?= (int)$seat['used'] ?> currently used
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold" for="seatReductionQuantity">
                                Extra seats to remove
                            </label>
                            <input
                                id="seatReductionQuantity"
                                class="form-control"
                                type="number"
                                name="quantity"
                                min="1"
                                max="500"
                                step="1"
                                value="1"
                                inputmode="numeric"
                                required
                            >
                        </div>

                        <div class="col-md-4">
                            <button
                                type="submit"
                                name="schedule_seat_reduction"
                                value="1"
                                class="btn btn-outline-danger w-100 fw-semibold"
                                <?= $seatReductionReady ? '' : 'disabled' ?>
                            >
                                Schedule reduction
                            </button>
                        </div>
                    </form>

                    <div class="form-text mt-2">
                        The server checks the current purchased seats, assigned users and paid-period end again before saving. A reduction that would leave too few seats is rejected.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card billing-card mb-4">
            <div class="card-header bg-transparent border-0 pt-3 px-3"><h2 class="h5 mb-0">Recent payments</h2><div class="small text-muted">Provider-neutral payment attempts for this farm only.</div></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Date</th><th>Provider</th><th>Plan</th><th>Interval</th><th>Amount</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if (!$overview['payment_attempts']): ?><tr><td colspan="6" class="empty-state">No payment attempts have been recorded yet.</td></tr><?php endif; ?>
                    <?php foreach ($overview['payment_attempts'] as $attempt): $attemptStatus = strtolower((string)($attempt['status'] ?? '')); ?>
                        <tr>
                            <td class="text-nowrap"><?= htmlspecialchars($formatDate($attempt['created_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-capitalize"><?= htmlspecialchars((string)($attempt['provider'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-capitalize"><?= htmlspecialchars((string)($attempt['plan_code'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-capitalize"><?= htmlspecialchars((string)($attempt['billing_interval'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($attempt['currency'] ?? ''), ENT_QUOTES, 'UTF-8') ?> <?= number_format((float)($attempt['amount'] ?? 0), 2) ?></td>
                            <td><span class="badge text-bg-<?= htmlspecialchars($statusClass($attemptStatus), ENT_QUOTES, 'UTF-8') ?> text-capitalize"><?= htmlspecialchars(str_replace('_', ' ', $attemptStatus ?: 'unknown'), ENT_QUOTES, 'UTF-8') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card billing-card">
            <div class="card-header bg-transparent border-0 pt-3 px-3"><h2 class="h5 mb-0">Subscription history</h2><div class="small text-muted">Recent immutable commercial snapshots for this farm.</div></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Date</th><th>Plan</th><th>Status</th><th>Interval</th><th>Modules</th><th>Period end</th></tr></thead>
                    <tbody>
                    <?php if (!$overview['subscription_history']): ?><tr><td colspan="6" class="empty-state">No commercial subscription history has been recorded yet.</td></tr><?php endif; ?>
                    <?php foreach ($overview['subscription_history'] as $history): $historyStatus = strtolower((string)($history['status'] ?? '')); ?>
                        <tr>
                            <td class="text-nowrap"><?= htmlspecialchars($formatDate($history['created_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-capitalize"><?= htmlspecialchars((string)($history['plan_code'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="badge text-bg-<?= htmlspecialchars($statusClass($historyStatus), ENT_QUOTES, 'UTF-8') ?> text-capitalize"><?= htmlspecialchars(str_replace('_', ' ', $historyStatus ?: 'unknown'), ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td class="text-capitalize"><?= htmlspecialchars((string)($history['billing_interval'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($decodeModules($history['modules_snapshot'] ?? '[]'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-nowrap"><?= htmlspecialchars($formatDate($history['subscription_ends_at'] ?? ($history['current_period_ends_at'] ?? null)), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>