<?php
/**
 * V2.3 tenant-facing paid billing receipt.
 *
 * Read-only route:
 * - normal Farm Admin authentication only;
 * - tenant identity comes only from the authenticated actor;
 * - only a paid attempt belonging to that farm can render;
 * - viewing never verifies with a provider or changes commercial state.
 */

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/includes/billing_tenant_actor.php';
require_once dirname(__DIR__) . '/includes/billing_payment_receipt.php';
require_once dirname(__DIR__) . '/includes/subscription_plan_catalog.php';
require_once dirname(__DIR__) . '/includes/pdf/PdfReportService.php';

if (
    strtoupper(
        (string)($_SERVER['REQUEST_METHOD'] ?? 'GET')
    ) !== 'GET'
) {
    http_response_code(405);
    exit('Method not allowed.');
}

$actor = billing_require_farm_admin_actor(
    $pdo,
    false
);

$farmId = (int)$actor['farm_id'];

$attemptIdRaw = trim(
    (string)($_GET['id'] ?? '')
);

if (!preg_match('/^[1-9][0-9]*$/', $attemptIdRaw)) {
    http_response_code(404);
    exit('Payment receipt could not be found.');
}

$attemptId = (int)$attemptIdRaw;

try {
    $receipt = billing_payment_receipt_find(
        $pdo,
        $farmId,
        $attemptId
    );
} catch (Throwable $e) {
    error_log(
        'Billing receipt lookup failed for farm '
        . $farmId
        . ': '
        . $e->getMessage()
    );

    http_response_code(503);
    exit(
        'Payment receipt is temporarily unavailable. '
        . 'Please try again later.'
    );
}

if (!$receipt) {
    http_response_code(404);
    exit('Payment receipt could not be found.');
}

$farm = billing_tenant_actor_farm(
    $pdo,
    $actor
);

$farmName = trim(
    (string)($farm['name'] ?? '')
);

if ($farmName === '') {
    $farmName = farmBrandName();
}

$purpose = (string)$receipt['purpose'];

$purposeLabel = match ($purpose) {
    'subscription' => 'Subscription payment',
    'seat_topup' => 'Seat top-up payment',
    default => 'Billing payment',
};

$planCode = strtolower(
    trim(
        (string)($receipt['plan_code'] ?? '')
    )
);

$planLabel = $planCode === ''
    ? '—'
    : subscription_plan_label($planCode);

$provider = strtolower(
    trim(
        (string)($receipt['provider'] ?? '')
    )
);

$providerLabel = $provider === ''
    ? '—'
    : ucfirst($provider);

$providerReference = trim(
    (string)($receipt['provider_reference'] ?? '')
);

$providerTransactionId = trim(
    (string)($receipt['provider_transaction_id'] ?? '')
);

$currency = strtoupper(
    trim(
        (string)($receipt['currency'] ?? '')
    )
);

$interval = ucfirst(
    strtolower(
        trim(
            (string)($receipt['billing_interval'] ?? '')
        )
    )
);

$receiptNumber = 'BILL-'
    . str_pad(
        (string)$attemptId,
        8,
        '0',
        STR_PAD_LEFT
    );

$commercialDisposition = strtolower(
    trim(
        (string)(
            $receipt['commercial_disposition'] ?? ''
        )
    )
);

$isSuperseded = $purpose === 'subscription'
    && $commercialDisposition === 'superseded';

$paidAt = trim(
    (string)($receipt['paid_at'] ?? '')
);

if ($paidAt === '') {
    $paidAt = trim(
        (string)($receipt['verified_at'] ?? '')
    );
}

$formatDateTime = static function ($value): string {
    $value = trim((string)$value);

    if ($value === '') {
        return '—';
    }

    $time = strtotime($value);

    return $time === false
        ? $value
        : date('d M Y, H:i', $time);
};

$escape = static function ($value): string {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
};

$receiptPlatformName = 'MyFarms';
$receiptIssuerName = 'Renee Farms Limited';
$receiptIssuerBrand =
    $receiptPlatformName . ' by ' . $receiptIssuerName;

$pdfRequested = pdf_report_is_requested();

$pdfUrl = BASE_URL
    . '/billing/receipt.php?'
    . http_build_query([
        'id' => $attemptId,
        'pdf' => '1',
    ]);

$supersededWarning =
    'This payment was received, but the checkout had already '
    . 'been replaced. This receipt confirms the payment record '
    . 'only; it does not indicate that a subscription change '
    . 'was applied.';

$passiveNote =
    'This receipt is generated from the payment record stored '
    . 'by the platform. Viewing it does not contact the payment '
    . 'provider or change the farm subscription, seats or '
    . 'payment state.';

if ($pdfRequested) {
    pdf_report_begin();
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payment Receipt <?= $escape($receiptNumber) ?></title>
</head>
<body>

<h1>Payment receipt</h1>

<p>
    <strong>Receipt:</strong>
    <?= $escape($receiptNumber) ?>
</p>

<?php if ($isSuperseded): ?>
    <p>
        <strong>Important:</strong>
        <?= $escape($supersededWarning) ?>
    </p>
<?php endif; ?>

<table class="table">
    <thead>
        <tr>
            <th>Field</th>
            <th>Details</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <th>Status</th>
            <td>Paid</td>
        </tr>
        <tr>
            <th>Issued by</th>
            <td><?= $escape($receiptIssuerName) ?></td>
        </tr>
        <tr>
            <th>Platform</th>
            <td><?= $escape($receiptPlatformName) ?></td>
        </tr>
        <tr>
            <th>Customer / Farm</th>
            <td><?= $escape($farmName) ?></td>
        </tr>
        <tr>
            <th>Paid on</th>
            <td><?= $escape($formatDateTime($paidAt)) ?></td>
        </tr>
        <tr>
            <th>Amount</th>
            <td>
                <?= $escape($currency) ?>
                <?= $escape(
                    number_format(
                        (float)($receipt['amount'] ?? 0),
                        2
                    )
                ) ?>
            </td>
        </tr>
        <tr>
            <th>Provider</th>
            <td><?= $escape($providerLabel) ?></td>
        </tr>
        <tr>
            <th>Payment type</th>
            <td><?= $escape($purposeLabel) ?></td>
        </tr>
        <tr>
            <th>Plan</th>
            <td><?= $escape($planLabel) ?></td>
        </tr>
        <tr>
            <th>Billing interval</th>
            <td><?= $escape($interval ?: '—') ?></td>
        </tr>
        <tr>
            <th>Receipt reference</th>
            <td><?= $escape($receiptNumber) ?></td>
        </tr>
        <tr>
            <th>Provider reference</th>
            <td><?= $escape($providerReference ?: '—') ?></td>
        </tr>
        <tr>
            <th>Provider transaction ID</th>
            <td><?= $escape($providerTransactionId ?: '—') ?></td>
        </tr>
    </tbody>
</table>

<p><?= $escape($passiveNote) ?></p>

</body>
</html>
<?php

    pdf_report_finish(
        'payment-receipt-' . $receiptNumber . '.pdf',
        'portrait',
        'Payment Receipt ' . $receiptNumber,
        $receiptIssuerBrand
    );
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include dirname(__DIR__) . '/navbar_head.php'; ?>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <title>Payment Receipt | <?= $escape($farmName) ?></title>
    <link
        rel="stylesheet"
        href="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/css/billing-account-page.css'); ?>"
    >
</head>
<body>
<?php include dirname(__DIR__) . '/navbar.php'; ?>

<div class="billing-shell">

    <div class="card billing-hero mb-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <div class="text-uppercase small fw-semibold opacity-75 mb-1">
                        Farm Admin · Billing
                    </div>
                    <h1 class="h3 mb-2">Payment receipt</h1>
                    <p class="mb-1 opacity-75">
                        <?= $escape($receiptPlatformName) ?>
                        by <?= $escape($receiptIssuerName) ?>
                    </p>
                    <p class="mb-0 opacity-75">
                        Payment recorded for <?= $escape($farmName) ?>.
                    </p>
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <a
                        class="btn btn-outline-light fw-semibold"
                        href="<?= $escape($pdfUrl) ?>"
                    >
                        <i class="bi bi-file-earmark-pdf me-1"></i>
                        View PDF receipt
                    </a>

                    <a
                        class="btn btn-light fw-semibold"
                        href="<?= $escape(BASE_URL . '/billing/account.php') ?>"
                    >
                        <i class="bi bi-arrow-left me-1"></i>
                        Back to Billing
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if ($isSuperseded): ?>
        <div class="alert alert-warning">
            <?= $escape($supersededWarning) ?>
        </div>
    <?php endif; ?>

    <div class="card billing-card">
        <div class="card-header bg-transparent border-0 pt-3 px-3">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <h2 class="h5 mb-1">
                        <?= $escape($purposeLabel) ?>
                    </h2>
                    <div class="small text-muted">
                        Receipt <?= $escape($receiptNumber) ?>
                    </div>
                </div>

                <span class="badge text-bg-success">
                    Paid
                </span>
            </div>
        </div>

        <div class="card-body pt-2">
            <div class="row g-4">

                <div class="col-md-6">
                    <div class="metric-label">Issued by</div>
                    <div class="metric-value mt-1">
                        <?= $escape($receiptIssuerName) ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="metric-label">Platform</div>
                    <div class="metric-value mt-1">
                        <?= $escape($receiptPlatformName) ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="metric-label">Customer / Farm</div>
                    <div class="metric-value mt-1">
                        <?= $escape($farmName) ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="metric-label">Paid on</div>
                    <div class="metric-value mt-1">
                        <?= $escape($formatDateTime($paidAt)) ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="metric-label">Amount</div>
                    <div class="metric-value mt-1">
                        <?= $escape($currency) ?>
                        <?= $escape(
                            number_format(
                                (float)($receipt['amount'] ?? 0),
                                2
                            )
                        ) ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="metric-label">Provider</div>
                    <div class="metric-value mt-1">
                        <?= $escape($providerLabel) ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="metric-label">Payment type</div>
                    <div class="metric-value mt-1">
                        <?= $escape($purposeLabel) ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="metric-label">Plan</div>
                    <div class="metric-value mt-1">
                        <?= $escape($planLabel) ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="metric-label">Billing interval</div>
                    <div class="metric-value mt-1">
                        <?= $escape($interval ?: '—') ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="metric-label">Receipt reference</div>
                    <div class="metric-value mt-1">
                        <?= $escape($receiptNumber) ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="metric-label">Provider reference</div>
                    <div class="metric-value mt-1 text-break">
                        <?= $escape($providerReference ?: '—') ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="metric-label">
                        Provider transaction ID
                    </div>
                    <div class="metric-value mt-1 text-break">
                        <?= $escape($providerTransactionId ?: '—') ?>
                    </div>
                </div>

            </div>

            <div class="billing-note mt-4">
                <?= $escape($passiveNote) ?>
            </div>
        </div>
    </div>

</div>

</body>
</html>
