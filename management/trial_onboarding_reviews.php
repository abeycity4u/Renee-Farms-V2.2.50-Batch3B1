<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/trial_onboarding_review.php';
require_once dirname(__DIR__) . '/includes/trial_onboarding_provisioning.php';
require_once dirname(__DIR__) . '/includes/platform_owner_tenant_workspace.php';

requireLogin();
requirePlatformOwner();

function redirectTrialOnboardingReviews(): void
{
    header(
        'Location: '
        . BASE_URL
        . '/management/trial_onboarding_reviews.php',
        true,
        303
    );

    exit();
}

$requestMethod =
    strtoupper(
        (string)(
            $_SERVER['REQUEST_METHOD']
            ?? 'GET'
        )
    );

if ($requestMethod === 'POST') {
    require_valid_csrf_post();

    $approveRequested =
        isset($_POST['approve_request'])
        && (string)$_POST['approve_request'] === '1';

    $rejectRequested =
        isset($_POST['reject_request'])
        && (string)$_POST['reject_request'] === '1';

    $provisionRequested =
        isset($_POST['provision_request'])
        && (string)$_POST['provision_request'] === '1';

    $requestedActionCount =
        (int)$approveRequested
        + (int)$rejectRequested
        + (int)$provisionRequested;

    if ($requestedActionCount !== 1) {
        $_SESSION['error'] =
            'Unsupported trial review action.';

        redirectTrialOnboardingReviews();
    }

    $requestId =
        filter_var(
            $_POST['request_id'] ?? null,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                ],
            ]
        );

    $planCode =
        strtolower(
            trim(
                (string)(
                    $_POST['plan_code']
                    ?? ''
                )
            )
        );

    $approvedModules =
        is_array(
            $_POST['approved_modules']
            ?? null
        )
            ? $_POST['approved_modules']
            : [];

    $reviewedByUserId =
        (int)(
            $_SESSION['user_id']
            ?? 0
        );

    $rejectionReasonCode =
        strtolower(
            trim(
                (string)(
                    $_POST['rejection_reason_code']
                    ?? ''
                )
            )
        );

    if (
        $requestId === false
        || $reviewedByUserId < 1
    ) {
        $_SESSION['error'] =
            'The trial review request is invalid.';

        redirectTrialOnboardingReviews();
    }

    try {
        if ($rejectRequested) {
            $result =
                trial_onboarding_review_reject(
                    $pdo,
                    (int)$requestId,
                    $reviewedByUserId,
                    $rejectionReasonCode
                );

            if (
                !empty(
                    $result['already_rejected']
                )
            ) {
                $_SESSION['success'] =
                    'This trial request was already rejected. Its rejection decision was left unchanged.';
            } else {
                $_SESSION['success'] =
                    'Trial request rejected. No tenant was provisioned and no trial clock was started.';
            }

        } elseif ($provisionRequested) {
            $result =
                trial_onboarding_provision_approved_request(
                    $pdo,
                    (int)$requestId
                );

            if (
                !empty(
                    $result['already_provisioned']
                )
            ) {
                $_SESSION['success'] =
                    'This approved trial request was already provisioned. Its existing tenant binding was left unchanged.';
            } else {
                $_SESSION['success'] =
                    'Trial tenant provisioned. The Farm Admin activation invitation is queued. The 14-day trial will start only after successful activation.';
            }

        } else {
            $result =
                trial_onboarding_review_approve(
                    $pdo,
                    (int)$requestId,
                    'manual',
                    $planCode,
                    $approvedModules,
                    $reviewedByUserId,
                    'manual_review'
                );

            if (
                !empty(
                    $result['already_approved']
                )
            ) {
                $_SESSION['success'] =
                    'This trial request was already approved. Its approved commercial snapshot was left unchanged.';
            } else {
                $_SESSION['success'] =
                    'Trial request approved. The commercial snapshot is now frozen and ready for the separate provisioning step.';
            }
        }

    } catch (InvalidArgumentException $e) {
        $_SESSION['error'] =
            $e->getMessage();

    } catch (RuntimeException $e) {
        error_log(
            'Platform Owner trial review failed for request '
            . (int)$requestId
            . ': '
            . $e->getMessage()
        );

        $_SESSION['error'] =
            'Unable to complete this trial review action. No tenant was provisioned and no trial clock was started.';

    } catch (Throwable $e) {
        error_log(
            'Unexpected Platform Owner trial review failure for request '
            . (int)$requestId
            . ': '
            . get_class($e)
        );

        $_SESSION['error'] =
            'Unable to complete this trial review action. No tenant was provisioned and no trial clock was started.';
    }

    redirectTrialOnboardingReviews();
}

if ($requestMethod !== 'GET') {
    http_response_code(405);
    exit('Method not allowed.');
}

$stmt =
    $pdo->query(
        "SELECT
            id,
            request_reference,
            status,
            approval_mode,
            farm_name,
            requested_workspace_id,
            admin_full_name,
            admin_username,
            admin_email,
            contact_name,
            contact_email,
            requested_modules_snapshot,
            approved_plan_code,
            approved_modules_snapshot,
            approved_trial_days,
            review_reason_code,
            rejection_reason_code,
            approved_by_user_id,
            rejected_by_user_id,
            approved_at,
            provisioning_started_at,
            provisioned_at,
            activated_at,
            rejected_at,
            cancelled_at,
            created_at,
            updated_at
         FROM trial_onboarding_requests
         ORDER BY
            CASE
                WHEN status = 'pending_review'
                THEN 0
                WHEN status = 'approved'
                THEN 1
                ELSE 2
            END,
            created_at DESC,
            id DESC
         LIMIT 100"
    );

$requests =
    $stmt->fetchAll(PDO::FETCH_ASSOC)
    ?: [];

$pendingCount = 0;

foreach ($requests as $request) {
    if (
        (string)$request['status']
        === 'pending_review'
    ) {
        $pendingCount++;
    }
}

$planCatalog =
    subscription_plan_catalog();

$moduleLabels =
    farm_entitlement_module_labels();

$decodeModules =
    static function (
        ?string $snapshot
    ): array {
        $snapshot =
            trim(
                (string)$snapshot
            );

        if ($snapshot === '') {
            return [];
        }

        try {
            $decoded =
                json_decode(
                    $snapshot,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
        } catch (Throwable $e) {
            return [];
        }

        if (!is_array($decoded)) {
            return [];
        }

        $modules =
            farm_entitlement_normalize_modules(
                $decoded
            );

        sort($modules);

        return $modules;
    };

$h =
    static function ($value): string {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            'UTF-8'
        );
    };

$statusLabel =
    static function (
        string $status
    ): string {
        return ucwords(
            str_replace(
                '_',
                ' ',
                $status
            )
        );
    };

$pageTitle =
    'Trial Requests';
?>
<!doctype html>
<html lang="en">
<head>
    <?php
    include dirname(__DIR__)
        . '/navbar_head.php';
    ?>

    <title>
        <?php
        echo $h(
            platform_brand_document_title(
                $pageTitle
            )
        );
        ?>
    </title>

    <link
        rel="stylesheet"
        href="<?php
            echo $h(
                BASE_URL
                . versioned_asset(
                    '/assets/css/management-workspaces.css'
                )
            );
        ?>"
    >
</head>
<body>

<?php
include dirname(__DIR__)
    . '/navbar.php';
?>

<div class="container-fluid py-3">
<div class="tenant-view-shell">

    <?php
        platform_owner_tenant_workspace_render(
            $pdo,
            'trial_requests'
        );
    ?>

    <div
        class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3"
    >
        <div>
            <h3 class="mb-1">
                <i class="bi bi-person-check"></i>
                Trial Requests
            </h3>

            <div class="text-muted">
                Platform Owner review of public Renee AgriSuite trial requests.
                Approval freezes the selected plan, requested service modules,
                role limits, and the canonical 14-day trial duration.
                Approval does not create a tenant and does not start the trial clock.
            </div>
        </div>

    </div>

    <div
        class="alert alert-info d-flex justify-content-between align-items-center flex-wrap gap-2"
    >
        <div>
            <strong>Manual review.</strong>
            This workspace delegates approval and rejection to the shared onboarding-review authority.
            Approval freezes the commercial trial snapshot.
            Rejection records a terminal decision with the rejecting Platform Owner and reason code.
            Provisioning remains a separate controlled action after approval.
        </div>

        <span class="badge text-bg-light border">
            Pending review:
            <?php
            echo number_format(
                $pendingCount
            );
            ?>
        </span>
    </div>

    <?php if (!$requests): ?>

        <div class="card tenant-view-card">
            <div class="card-body text-center py-5">
                <i class="bi bi-check-circle fs-2 text-success"></i>

                <h5 class="mt-3 mb-1">
                    No trial requests
                </h5>

                <div class="text-muted">
                    There are currently no public trial requests to review.
                </div>
            </div>
        </div>

    <?php else: ?>

        <div class="card tenant-view-card">
            <div
                class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2"
            >
                <div class="fw-semibold">
                    <i class="bi bi-inbox"></i>
                    Trial Request Queue
                </div>

                <span class="badge text-bg-light border">
                    Latest 100
                </span>
            </div>

            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Request</th>
                        <th>Farm</th>
                        <th>Farm Admin</th>
                        <th>Requested modules</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Review</th>
                    </tr>
                    </thead>

                    <tbody>

                    <?php
                    foreach (
                        $requests
                        as $request
                    ):
                    ?>

                        <?php
                        $requestId =
                            (int)$request['id'];

                        $status =
                            (string)$request[
                                'status'
                            ];

                        $isPending =
                            $status
                            === 'pending_review';

                        $requestedModules =
                            $decodeModules(
                                $request[
                                    'requested_modules_snapshot'
                                ] ?? null
                            );
                        ?>

                        <tr>
                            <td>
                                <div class="fw-semibold">
                                    #<?php echo $requestId; ?>
                                </div>

                                <div class="text-muted small">
                                    <?php
                                    echo $h(
                                        $request[
                                            'request_reference'
                                        ]
                                    );
                                    ?>
                                </div>
                            </td>

                            <td>
                                <div class="fw-semibold">
                                    <?php
                                    echo $h(
                                        $request[
                                            'farm_name'
                                        ]
                                    );
                                    ?>
                                </div>

                                <div class="text-muted small">
                                    Workspace:
                                    <?php
                                    echo $h(
                                        $request[
                                            'requested_workspace_id'
                                        ]
                                    );
                                    ?>
                                </div>
                            </td>

                            <td>
                                <div class="fw-semibold">
                                    <?php
                                    echo $h(
                                        $request[
                                            'admin_full_name'
                                        ]
                                    );
                                    ?>
                                </div>

                                <div class="text-muted small">
                                    <?php
                                    echo $h(
                                        $request[
                                            'admin_email'
                                        ]
                                    );
                                    ?>
                                </div>

                                <div class="text-muted small">
                                    Username:
                                    <?php
                                    echo $h(
                                        $request[
                                            'admin_username'
                                        ]
                                    );
                                    ?>
                                </div>
                            </td>

                            <td>
                                <?php
                                if (!$requestedModules):
                                ?>
                                    <span class="text-muted">
                                        None
                                    </span>
                                <?php
                                else:
                                ?>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php
                                        foreach (
                                            $requestedModules
                                            as $module
                                        ):
                                        ?>
                                            <span
                                                class="badge text-bg-light border"
                                            >
                                                <?php
                                                echo $h(
                                                    $moduleLabels[
                                                        $module
                                                    ]
                                                    ?? ucfirst(
                                                        $module
                                                    )
                                                );
                                                ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if ($isPending): ?>
                                    <span class="badge text-bg-warning">
                                        Pending review
                                    </span>
                                <?php elseif ($status === 'approved'): ?>
                                    <span class="badge text-bg-primary">
                                        Approved
                                    </span>
                                <?php elseif ($status === 'activated'): ?>
                                    <span class="badge text-bg-success">
                                        Activated
                                    </span>
                                <?php else: ?>
                                    <span class="badge text-bg-light border">
                                        <?php
                                        echo $h(
                                            $statusLabel(
                                                $status
                                            )
                                        );
                                        ?>
                                    </span>
                                <?php endif; ?>

                                <?php
                                if (
                                    !empty(
                                        $request[
                                            'approved_plan_code'
                                        ]
                                    )
                                ):
                                ?>
                                    <div class="small text-muted mt-1">
                                        Plan:
                                        <?php
                                        echo $h(
                                            subscription_plan_label(
                                                (string)$request[
                                                    'approved_plan_code'
                                                ]
                                            )
                                        );
                                        ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php
                                echo $h(
                                    $request[
                                        'created_at'
                                    ]
                                );
                                ?>
                            </td>

                            <td>
                                <?php if ($status === 'approved'): ?>

                                    <form
                                        method="post"
                                        action="<?php
                                            echo $h(
                                                BASE_URL
                                                . '/management/trial_onboarding_reviews.php'
                                            );
                                        ?>"
                                        class="d-flex flex-column gap-2"
                                        data-confirm="Provision this approved trial request now? This creates the tenant and pending Farm Admin account, queues the activation invitation, and keeps the 14-day trial clock stopped until activation."
                                        data-confirm-title="Provision trial tenant?"
                                        data-confirm-button="Provision Tenant"
                                        data-confirm-tone="primary"
                                    >
                                        <?php
                                        echo csrf_field();
                                        ?>

                                        <input
                                            type="hidden"
                                            name="request_id"
                                            value="<?php
                                                echo $requestId;
                                            ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="provision_request"
                                            value="1"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-primary btn-sm"
                                        >
                                            <i class="bi bi-building-add"></i>
                                            Provision Tenant
                                        </button>

                                        <div class="small text-muted">
                                            Creates the tenant and pending Farm Admin.
                                            Trial time starts only after Farm Admin activation.
                                        </div>
                                    </form>

                                <?php endif; ?>

                                <?php if ($isPending): ?>

                                    <form
                                        method="post"
                                        action="<?php
                                            echo $h(
                                                BASE_URL
                                                . '/management/trial_onboarding_reviews.php'
                                            );
                                        ?>"
                                        class="d-flex flex-column gap-2"
                                        data-confirm="Approve this trial request and freeze its commercial trial snapshot? This does not provision the tenant or start the trial clock."
                                        data-confirm-title="Approve trial request?"
                                        data-confirm-button="Approve Request"
                                        data-confirm-tone="primary"
                                    >
                                        <?php
                                        echo csrf_field();
                                        ?>

                                        <input
                                            type="hidden"
                                            name="request_id"
                                            value="<?php
                                                echo $requestId;
                                            ?>"
                                        >

                                        <label
                                            class="form-label small mb-0"
                                            for="trialPlan<?php
                                                echo $requestId;
                                            ?>"
                                        >
                                            Trial plan
                                        </label>

                                        <select
                                            class="form-select form-select-sm"
                                            id="trialPlan<?php
                                                echo $requestId;
                                            ?>"
                                            name="plan_code"
                                            required
                                        >
                                            <?php
                                            foreach (
                                                $planCatalog
                                                as $planCode =>
                                                    $definition
                                            ):
                                            ?>
                                                <option
                                                    value="<?php
                                                        echo $h(
                                                            $planCode
                                                        );
                                                    ?>"
                                                >
                                                    <?php
                                                    echo $h(
                                                        $definition[
                                                            'label'
                                                        ]
                                                        ?? ucfirst(
                                                            $planCode
                                                        )
                                                    );
                                                    ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <fieldset>
                                            <legend
                                                class="form-label small mb-1"
                                            >
                                                Approved modules
                                            </legend>

                                            <?php
                                            foreach (
                                                $requestedModules
                                                as $module
                                            ):
                                            ?>
                                                <div class="form-check">
                                                    <input
                                                        class="form-check-input"
                                                        type="checkbox"
                                                        name="approved_modules[]"
                                                        id="trialModule<?php
                                                            echo $requestId;
                                                        ?>_<?php
                                                            echo $h(
                                                                $module
                                                            );
                                                        ?>"
                                                        value="<?php
                                                            echo $h(
                                                                $module
                                                            );
                                                        ?>"
                                                        checked
                                                    >

                                                    <label
                                                        class="form-check-label small"
                                                        for="trialModule<?php
                                                            echo $requestId;
                                                        ?>_<?php
                                                            echo $h(
                                                                $module
                                                            );
                                                        ?>"
                                                    >
                                                        <?php
                                                        echo $h(
                                                            $moduleLabels[
                                                                $module
                                                            ]
                                                            ?? ucfirst(
                                                                $module
                                                            )
                                                        );
                                                        ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </fieldset>

                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-primary"
                                            name="approve_request"
                                            value="1"
                                            <?php
                                            echo !$requestedModules
                                                ? 'disabled'
                                                : '';
                                            ?>
                                        >
                                            <i class="bi bi-check2-circle"></i>
                                            Approve request
                                        </button>
                                    </form>

                                    <form
                                        method="post"
                                        action="<?php
                                            echo $h(
                                                BASE_URL
                                                . '/management/trial_onboarding_reviews.php'
                                            );
                                        ?>"
                                        class="d-flex flex-column gap-2 mt-3"
                                        data-confirm="Reject this trial request? Rejection is terminal and does not provision a tenant or start a trial."
                                        data-confirm-title="Reject trial request?"
                                        data-confirm-button="Reject Request"
                                        data-confirm-tone="danger"
                                    >
                                        <?php
                                        echo csrf_field();
                                        ?>

                                        <input
                                            type="hidden"
                                            name="request_id"
                                            value="<?php
                                                echo $requestId;
                                            ?>"
                                        >

                                        <label
                                            class="form-label small mb-0"
                                            for="trialRejectReason<?php
                                                echo $requestId;
                                            ?>"
                                        >
                                            Rejection reason code
                                        </label>

                                        <input
                                            type="text"
                                            class="form-control form-control-sm"
                                            id="trialRejectReason<?php
                                                echo $requestId;
                                            ?>"
                                            name="rejection_reason_code"
                                            maxlength="80"
                                            pattern="[A-Za-z0-9][A-Za-z0-9_-]*"
                                            required
                                            placeholder="e.g. unsupported_request"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-outline-danger"
                                            name="reject_request"
                                            value="1"
                                        >
                                            <i class="bi bi-x-circle"></i>
                                            Reject request
                                        </button>
                                    </form>

                                <?php else: ?>

                                    <div class="small">
                                        <?php
                                        echo $h(
                                            $statusLabel(
                                                $status
                                            )
                                        );
                                        ?>
                                    </div>

                                    <?php
                                    if (
                                        !empty(
                                            $request[
                                                'approved_at'
                                            ]
                                        )
                                    ):
                                    ?>
                                        <div class="text-muted small">
                                            Approved:
                                            <?php
                                            echo $h(
                                                $request[
                                                    'approved_at'
                                                ]
                                            );
                                            ?>
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

<script src="<?php
    echo htmlspecialchars(
        BASE_URL
        . versioned_asset(
            '/assets/js/commercial-product-selection.js'
        ),
        ENT_QUOTES,
        'UTF-8'
    );
?>"></script>

</body>
</html>
