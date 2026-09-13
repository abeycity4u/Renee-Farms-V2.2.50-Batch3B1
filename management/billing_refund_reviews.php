<?php
require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/billing_refund_resolution.php';

requireLogin();
requirePlatformOwner();

function redirectBillingRefundReviews(): void
{
    header(
        'Location: '
        . BASE_URL
        . '/management/billing_refund_reviews.php'
    );
    exit();
}

$requestMethod =
    strtoupper(
        (string)($_SERVER['REQUEST_METHOD'] ?? 'GET')
    );

if ($requestMethod === 'POST') {
    require_valid_csrf_post();

    $paymentAttemptId =
        filter_var(
            $_POST['payment_attempt_id'] ?? null,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                ],
            ]
        );

    $resolvedByUserId =
        (int)($_SESSION['user_id'] ?? 0);

    $reason =
        trim(
            (string)(
                $_POST['resolution_reason']
                ?? ''
            )
        );

    if (!isset(
        $_POST['preserve_entitlement']
    )) {
        $_SESSION['error'] =
            'Unsupported refund review action.';

        redirectBillingRefundReviews();
    }

    if ($paymentAttemptId === false
        || $resolvedByUserId < 1) {
        $_SESSION['error'] =
            'The refund review request is invalid.';

        redirectBillingRefundReviews();
    }

    try {
        $pdo->beginTransaction();

        $result =
            billing_refund_resolution_resolve_preserve(
                $pdo,
                (int)$paymentAttemptId,
                $resolvedByUserId,
                $reason
            );

        $pdo->commit();

        if (!empty($result['idempotent'])) {
            $_SESSION['success'] =
                'This refund review was already resolved by preserving the existing tenant entitlement.';
        } else {
            $_SESSION['success'] =
                'Refund review resolved. The existing tenant entitlement was deliberately preserved.';
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            'Platform Owner refund preserve resolution failed for payment attempt '
            . (int)$paymentAttemptId
            . ': '
            . $e->getMessage()
        );

        $_SESSION['error'] =
            'Unable to resolve this refund review. No commercial entitlement or refund-review change was saved.';
    }

    redirectBillingRefundReviews();
}

if ($requestMethod !== 'GET') {
    http_response_code(405);
    exit('Method not allowed.');
}

if (!billing_refund_resolution_ready($pdo)) {
    http_response_code(503);
    exit(
        'Refund-resolution storage is not ready.'
    );
}

$stmt =
    $pdo->query(
        "SELECT
             rr.id,
             rr.farm_id,
             rr.payment_attempt_id,
             rr.purpose,
             rr.status,
             rr.resolution_action,
             rr.refund_verified_at,
             rr.applied_subscription_record_id,
             rr.seat_change_request_id,
             rr.resolved_at,
             rr.resolved_by_user_id,
             rr.resolution_reason,
             rr.created_at,
             rr.updated_at,
             f.name AS farm_name,
             pa.provider,
             pa.provider_reference,
             pa.amount,
             pa.currency,
             pa.status AS payment_status,
             pa.paid_at
         FROM billing_refund_resolutions rr
         INNER JOIN farms f
                 ON f.id = rr.farm_id
         INNER JOIN billing_payment_attempts pa
                 ON pa.id = rr.payment_attempt_id
                AND pa.farm_id = rr.farm_id
         ORDER BY
             CASE
                 WHEN rr.status = 'pending_review'
                 THEN 0
                 ELSE 1
             END,
             rr.refund_verified_at DESC,
             rr.id DESC
         LIMIT 100"
    );

$reviews =
    $stmt->fetchAll(PDO::FETCH_ASSOC)
    ?: [];

$pendingCount = 0;

foreach ($reviews as $review) {
    if ((string)$review['status']
        === 'pending_review') {
        $pendingCount++;
    }
}

$h = static function ($value): string {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
};

$pageTitle =
    'Billing Refund Reviews';
?>
<!doctype html>
<html lang="en">
<head>
    <?php include dirname(__DIR__) . '/navbar_head.php'; ?>
    <title>Billing Refund Reviews - Renee Farms Platform</title>
    <link
        rel="stylesheet"
        href="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/css/management-workspaces.css'); ?>"
    >
</head>
<body>
<?php include dirname(__DIR__) . '/navbar.php'; ?>

<div class="container-fluid py-3">
<div class="tenant-view-shell">

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
        <div>
            <h3 class="mb-1">
                <i class="bi bi-arrow-counterclockwise"></i>
                Billing Refund Reviews
            </h3>
            <div class="text-muted">
                Platform Owner commercial review of provider-confirmed refunds that occurred after tenant entitlement was already applied.
            </div>
        </div>

        <a
            class="btn btn-outline-secondary"
            href="<?php echo $h(BASE_URL . '/management/farms.php'); ?>"
        >
            <i class="bi bi-arrow-left"></i>
            Farm Accounts
        </a>
    </div>

    <div class="alert alert-info d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <strong>Preserve entitlement</strong>
            records an explicit commercial decision to leave the tenant's already-applied subscription or purchased seats unchanged after the provider refund.
            It does not contact the payment provider and does not change the provider refund fact.
        </div>

        <span class="badge text-bg-light border">
            Pending review:
            <?php echo number_format($pendingCount); ?>
        </span>
    </div>

    <?php if (!$reviews): ?>
        <div class="card tenant-view-card">
            <div class="card-body text-center py-5">
                <i class="bi bi-check-circle fs-2 text-success"></i>
                <h5 class="mt-3 mb-1">No refund reviews</h5>
                <div class="text-muted">
                    There are currently no captured post-application refund reviews.
                </div>
            </div>
        </div>
    <?php else: ?>

        <div class="card tenant-view-card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="fw-semibold">
                    <i class="bi bi-receipt"></i>
                    Commercial Refund Review Queue
                </div>

                <span class="badge text-bg-light border">
                    Latest 100
                </span>
            </div>

            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Farm</th>
                        <th>Payment</th>
                        <th>Purpose</th>
                        <th>Refund Verified</th>
                        <th>Application Lineage</th>
                        <th>Status</th>
                        <th>Resolution</th>
                    </tr>
                    </thead>
                    <tbody>

                    <?php foreach ($reviews as $review): ?>
                        <?php
                        $isPending =
                            (string)$review['status']
                            === 'pending_review';

                        $purposeLabel =
                            (string)$review['purpose']
                            === 'seat_topup'
                                ? 'Seat top-up'
                                : 'Subscription';

                        $lineageLabel =
                            (string)$review['purpose']
                            === 'seat_topup'
                                ? 'Seat request #'
                                    . (int)$review[
                                        'seat_change_request_id'
                                    ]
                                : 'Subscription record #'
                                    . (int)$review[
                                        'applied_subscription_record_id'
                                    ];
                        ?>

                        <tr>
                            <td>
                                <div class="fw-semibold">
                                    <?php echo $h($review['farm_name']); ?>
                                </div>
                                <div class="text-muted small">
                                    Farm #<?php echo (int)$review['farm_id']; ?>
                                </div>
                            </td>

                            <td>
                                <div class="fw-semibold">
                                    Attempt #<?php echo (int)$review['payment_attempt_id']; ?>
                                </div>
                                <div class="text-muted small">
                                    <?php echo $h(strtoupper((string)$review['provider'])); ?>
                                    ·
                                    <?php echo $h($review['currency']); ?>
                                    <?php echo number_format((float)$review['amount'], 2); ?>
                                </div>
                                <div class="text-muted small">
                                    Ref:
                                    <?php echo $h($review['provider_reference']); ?>
                                </div>
                            </td>

                            <td>
                                <?php echo $h($purposeLabel); ?>
                            </td>

                            <td>
                                <?php echo $h($review['refund_verified_at']); ?>
                            </td>

                            <td>
                                <?php echo $h($lineageLabel); ?>
                            </td>

                            <td>
                                <?php if ($isPending): ?>
                                    <span class="badge text-bg-warning">
                                        Pending review
                                    </span>
                                <?php else: ?>
                                    <span class="badge text-bg-success">
                                        Resolved
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="refund-review-action-cell">
                                <?php if ($isPending): ?>
                                    <form
                                        method="post"
                                        action="<?php echo $h(BASE_URL . '/management/billing_refund_reviews.php'); ?>"
                                        class="d-flex flex-column gap-2"
                                        data-confirm="Preserve this tenant's already-applied commercial entitlement despite the verified provider refund?"
                                        data-confirm-title="Preserve entitlement?"
                                        data-confirm-button="Preserve Entitlement"
                                        data-confirm-tone="primary"
                                    >
                                        <?php echo csrf_field(); ?>

                                        <input
                                            type="hidden"
                                            name="payment_attempt_id"
                                            value="<?php echo (int)$review['payment_attempt_id']; ?>"
                                        >

                                        <label
                                            class="visually-hidden"
                                            for="refundReason<?php echo (int)$review['id']; ?>"
                                        >
                                            Resolution reason
                                        </label>

                                        <input
                                            type="text"
                                            class="form-control form-control-sm"
                                            id="refundReason<?php echo (int)$review['id']; ?>"
                                            name="resolution_reason"
                                            maxlength="160"
                                            required
                                            placeholder="Reason for preserving entitlement"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-outline-primary"
                                            name="preserve_entitlement"
                                            value="1"
                                        >
                                            <i class="bi bi-shield-check"></i>
                                            Preserve entitlement
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <div class="fw-semibold text-capitalize">
                                        <?php echo $h(
                                            str_replace(
                                                '_',
                                                ' ',
                                                (string)$review['resolution_action']
                                            )
                                        ); ?>
                                    </div>

                                    <div class="text-muted small">
                                        <?php echo $h($review['resolved_at']); ?>
                                    </div>

                                    <?php if (
                                        trim(
                                            (string)$review['resolution_reason']
                                        ) !== ''
                                    ): ?>
                                        <div class="small mt-1">
                                            <?php echo $h($review['resolution_reason']); ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    </tbody>
                </table>
            </div>
        </div>

    <?php endif; ?>

</div>
</div>

</body>
</html>
