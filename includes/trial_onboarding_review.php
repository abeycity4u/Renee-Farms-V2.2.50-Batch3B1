<?php

declare(strict_types=1);

/**
 * V3.2 trial onboarding review/approval authority.
 *
 * Responsibilities:
 * - own the pending_review -> approved decision transaction;
 * - lock exactly one request FOR UPDATE;
 * - freeze approved plan/module/seat/trial snapshots;
 * - derive role limits from canonical commercial policy;
 * - preserve manual/automatic approval attribution;
 * - make an already-approved request safe to read on retry.
 *
 * Explicit non-responsibilities:
 * - public request submission;
 * - tenant/farm creation;
 * - Farm Admin creation;
 * - credential/token issuance;
 * - activation email delivery;
 * - trial-clock start;
 * - provisioning;
 * - billing/payment application.
 */

require_once __DIR__
    . '/trial_onboarding_request.php';

require_once __DIR__
    . '/farm_entitlements.php';

require_once __DIR__
    . '/billing_commercial_product.php';

require_once __DIR__
    . '/subscription_plan_catalog.php';

require_once __DIR__
    . '/subscription_seat_policy.php';

if (!function_exists(
    'trial_onboarding_review_decode_requested_modules'
)) {
    function trial_onboarding_review_decode_requested_modules(
        array $request
    ): array {
        $raw = trim(
            (string)(
                $request['requested_modules_snapshot']
                ?? ''
            )
        );

        if ($raw === '') {
            throw new RuntimeException(
                'Trial request does not contain requested modules.'
            );
        }

        try {
            $decoded = json_decode(
                $raw,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            throw new RuntimeException(
                'Requested module snapshot is invalid.'
            );
        }

        if (!is_array($decoded)) {
            throw new RuntimeException(
                'Requested module snapshot is invalid.'
            );
        }

        $modules =
            farm_entitlement_normalize_modules(
                $decoded
            );

        if (!$modules) {
            throw new RuntimeException(
                'Trial request has no valid requested module.'
            );
        }

        sort($modules);

        return $modules;
    }
}

if (!function_exists(
    'trial_onboarding_review_normalize_reason_code'
)) {
    function trial_onboarding_review_normalize_reason_code(
        ?string $reasonCode
    ): ?string {
        $reasonCode =
            strtolower(
                trim((string)$reasonCode)
            );

        if ($reasonCode === '') {
            return null;
        }

        if (
            strlen($reasonCode) > 80
            || !preg_match(
                '/^[a-z0-9][a-z0-9_-]*$/',
                $reasonCode
            )
        ) {
            throw new InvalidArgumentException(
                'Review reason code is invalid.'
            );
        }

        return $reasonCode;
    }
}

if (!function_exists(
    'trial_onboarding_review_approved_modules'
)) {
    function trial_onboarding_review_approved_modules(
        array $request,
        ?array $selectedModules = null
    ): array {
        $requested =
            trial_onboarding_review_decode_requested_modules(
                $request
            );

        $approved =
            billing_commercial_product_selection_modules(
                $selectedModules === null
                    ? $requested
                    : $selectedModules
            );

        sort($approved);

        foreach ($approved as $module) {
            if (!in_array(
                $module,
                $requested,
                true
            )) {
                throw new InvalidArgumentException(
                    'Approval cannot grant a module that was not requested.'
                );
            }
        }

        return $approved;
    }
}

if (!function_exists(
    'trial_onboarding_review_role_limits'
)) {
    function trial_onboarding_review_role_limits(
        string $planCode,
        array $modules
    ): array {
        $planCode =
            strtolower(
                trim($planCode)
            );

        if (
            $planCode === ''
            || !subscription_plan_is_valid(
                $planCode
            )
        ) {
            throw new InvalidArgumentException(
                'Select a valid subscription plan.'
            );
        }

        $modules =
            farm_entitlement_normalize_modules(
                $modules
            );

        if (!$modules) {
            throw new InvalidArgumentException(
                'At least one module is required.'
            );
        }

        $seatAddOns =
            subscription_seat_normalize_addons(
                []
            );

        $derived =
            subscription_plan_effective_role_limits(
                $planCode,
                $modules,
                $seatAddOns
            );

        $limits = [];

        foreach (
            subscription_seat_roles()
            as $roleCode => $_label
        ) {
            $value =
                filter_var(
                    $derived[$roleCode] ?? 0,
                    FILTER_VALIDATE_INT,
                    [
                        'options' => [
                            'min_range' => 0,
                            'max_range' => 500,
                        ],
                    ]
                );

            if ($value === false) {
                throw new RuntimeException(
                    'Commercial role-limit policy returned an invalid value.'
                );
            }

            /*
             * Preserve the shared seat-policy interpretation:
             * roles outside the approved module scope receive zero
             * effective seats.
             */
            $limits[$roleCode] =
                subscription_seat_role_relevant(
                    $roleCode,
                    $modules
                )
                    ? (int)$value
                    : 0;
        }

        ksort($limits);

        return $limits;
    }
}

if (!function_exists(
    'trial_onboarding_review_json_snapshot'
)) {
    function trial_onboarding_review_json_snapshot(
        array $value
    ): string {
        return json_encode(
            $value,
            JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_SLASHES
        );
    }
}

if (!function_exists(
    'trial_onboarding_review_existing_approval'
)) {
    function trial_onboarding_review_existing_approval(
        array $request
    ): array {
        return [
            'request_id' =>
                (int)$request['id'],

            'request_reference' =>
                (string)$request[
                    'request_reference'
                ],

            'status' =>
                (string)$request['status'],

            'approval_mode' =>
                $request['approval_mode']
                    ?? null,

            'approved_plan_code' =>
                $request['approved_plan_code']
                    ?? null,

            'approved_trial_days' =>
                isset(
                    $request[
                        'approved_trial_days'
                    ]
                )
                    ? (int)$request[
                        'approved_trial_days'
                    ]
                    : null,

            'approved_by_user_id' =>
                isset(
                    $request[
                        'approved_by_user_id'
                    ]
                )
                    ? (int)$request[
                        'approved_by_user_id'
                    ]
                    : null,

            'already_approved' =>
                true,
        ];
    }
}

if (!function_exists(
    'trial_onboarding_review_approve'
)) {
    function trial_onboarding_review_approve(
        PDO $pdo,
        int $requestId,
        string $approvalMode,
        string $planCode,
        ?array $approvedModules,
        ?int $approvedByUserId,
        ?string $reviewReasonCode = null
    ): array {
        if ($requestId < 1) {
            throw new InvalidArgumentException(
                'A valid trial request is required.'
            );
        }

        $approvalMode =
            trial_onboarding_request_normalize_approval_mode(
                $approvalMode
            );

        $planCode =
            strtolower(
                trim($planCode)
            );

        if (
            !subscription_plan_is_valid(
                $planCode
            )
        ) {
            throw new InvalidArgumentException(
                'Select a valid subscription plan.'
            );
        }

        if (
            $approvalMode === 'manual'
            && (
                $approvedByUserId === null
                || $approvedByUserId < 1
            )
        ) {
            throw new InvalidArgumentException(
                'Manual approval requires a valid Platform Owner.'
            );
        }

        if (
            $approvedByUserId !== null
            && $approvedByUserId < 1
        ) {
            throw new InvalidArgumentException(
                'Approving user is invalid.'
            );
        }

        $reviewReasonCode =
            trial_onboarding_review_normalize_reason_code(
                $reviewReasonCode
            );

        if ($pdo->inTransaction()) {
            throw new RuntimeException(
                'Trial onboarding review owns its database transaction.'
            );
        }

        try {
            $pdo->beginTransaction();

            $stmt =
                $pdo->prepare(
                    "SELECT *
                     FROM trial_onboarding_requests
                     WHERE id = ?
                     LIMIT 1
                     FOR UPDATE"
                );

            $stmt->execute([
                $requestId,
            ]);

            $request =
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                )
                ?: null;

            if (!$request) {
                throw new RuntimeException(
                    'Trial onboarding request was not found.'
                );
            }

            $status =
                trial_onboarding_request_normalize_status(
                    (string)$request['status']
                );

            if ($status === 'approved') {
                $existing =
                    trial_onboarding_review_existing_approval(
                        $request
                    );

                $pdo->commit();

                return $existing;
            }

            if ($status !== 'pending_review') {
                throw new RuntimeException(
                    'Only a pending trial request can be approved.'
                );
            }

            $modules =
                trial_onboarding_review_approved_modules(
                    $request,
                    $approvedModules
                );

            $roleLimits =
                trial_onboarding_review_role_limits(
                    $planCode,
                    $modules
                );

            $trialDays =
                trial_onboarding_trial_days();

            trial_onboarding_request_assert_transition(
                $status,
                'approved'
            );

            $update =
                $pdo->prepare(
                    "UPDATE trial_onboarding_requests
                     SET
                        status = 'approved',
                        approval_mode = ?,
                        approved_plan_code = ?,
                        approved_modules_snapshot = ?,
                        approved_role_limits_snapshot = ?,
                        approved_trial_days = ?,
                        review_reason_code = ?,
                        rejection_reason_code = NULL,
                        approved_by_user_id = ?,
                        approved_at = NOW(),
                        rejected_at = NULL
                     WHERE id = ?
                       AND status = 'pending_review'
                       AND farm_id IS NULL
                       AND farm_admin_user_id IS NULL"
                );

            $update->execute([
                $approvalMode,
                $planCode,
                trial_onboarding_review_json_snapshot(
                    $modules
                ),
                trial_onboarding_review_json_snapshot(
                    $roleLimits
                ),
                $trialDays,
                $reviewReasonCode,
                $approvedByUserId,
                $requestId,
            ]);

            if ($update->rowCount() !== 1) {
                throw new RuntimeException(
                    'Trial onboarding request could not be approved.'
                );
            }

            $pdo->commit();

            return [
                'request_id' =>
                    $requestId,

                'request_reference' =>
                    (string)$request[
                        'request_reference'
                    ],

                'status' =>
                    'approved',

                'approval_mode' =>
                    $approvalMode,

                'approved_plan_code' =>
                    $planCode,

                'approved_modules' =>
                    $modules,

                'approved_role_limits' =>
                    $roleLimits,

                'approved_trial_days' =>
                    $trialDays,

                'approved_by_user_id' =>
                    $approvedByUserId,

                'already_approved' =>
                    false,
            ];

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }
}

if (!function_exists(
    'trial_onboarding_review_existing_rejection'
)) {
    function trial_onboarding_review_existing_rejection(
        array $request
    ): array {
        return [
            'request_id' =>
                (int)$request['id'],

            'request_reference' =>
                (string)$request[
                    'request_reference'
                ],

            'status' =>
                (string)$request['status'],

            'rejection_reason_code' =>
                $request[
                    'rejection_reason_code'
                ] ?? null,

            'rejected_by_user_id' =>
                isset(
                    $request[
                        'rejected_by_user_id'
                    ]
                )
                    ? (int)$request[
                        'rejected_by_user_id'
                    ]
                    : null,

            'already_rejected' =>
                true,
        ];
    }
}

if (!function_exists(
    'trial_onboarding_review_reject'
)) {
    function trial_onboarding_review_reject(
        PDO $pdo,
        int $requestId,
        int $rejectedByUserId,
        string $rejectionReasonCode
    ): array {
        if ($requestId < 1) {
            throw new InvalidArgumentException(
                'A valid trial request is required.'
            );
        }

        if ($rejectedByUserId < 1) {
            throw new InvalidArgumentException(
                'Rejecting Platform Owner is invalid.'
            );
        }

        $rejectionReasonCode =
            trial_onboarding_review_normalize_reason_code(
                $rejectionReasonCode
            );

        if ($rejectionReasonCode === null) {
            throw new InvalidArgumentException(
                'A rejection reason code is required.'
            );
        }

        if ($pdo->inTransaction()) {
            throw new RuntimeException(
                'Trial onboarding review owns its database transaction.'
            );
        }

        try {
            $pdo->beginTransaction();

            $stmt =
                $pdo->prepare(
                    "SELECT *
                     FROM trial_onboarding_requests
                     WHERE id = ?
                     LIMIT 1
                     FOR UPDATE"
                );

            $stmt->execute([
                $requestId,
            ]);

            $request =
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                )
                ?: null;

            if (!$request) {
                throw new RuntimeException(
                    'Trial onboarding request was not found.'
                );
            }

            $status =
                trial_onboarding_request_normalize_status(
                    (string)$request['status']
                );

            if ($status === 'rejected') {
                $existing =
                    trial_onboarding_review_existing_rejection(
                        $request
                    );

                $pdo->commit();

                return $existing;
            }

            if ($status !== 'pending_review') {
                throw new RuntimeException(
                    'Only a pending trial request can be rejected.'
                );
            }

            /*
             * A pending request must not carry provisioning or approval
             * state. Fail closed rather than converting a malformed row
             * into a terminal rejection and hiding inconsistent history.
             */
            if (
                $request['farm_id'] !== null
                || $request['farm_admin_user_id'] !== null
                || $request['approved_plan_code'] !== null
                || $request['approved_modules_snapshot'] !== null
                || $request['approved_role_limits_snapshot'] !== null
                || $request['approved_trial_days'] !== null
                || $request['approved_by_user_id'] !== null
                || $request['approved_at'] !== null
                || $request['provisioning_started_at'] !== null
                || $request['provisioned_at'] !== null
                || $request['activated_at'] !== null
            ) {
                throw new RuntimeException(
                    'Pending trial request contains incompatible approval or provisioning state.'
                );
            }

            trial_onboarding_request_assert_transition(
                $status,
                'rejected'
            );

            $update =
                $pdo->prepare(
                    "UPDATE trial_onboarding_requests
                     SET
                        status = 'rejected',
                        rejection_reason_code = ?,
                        rejected_by_user_id = ?,
                        rejected_at = NOW()
                     WHERE id = ?
                       AND status = 'pending_review'
                       AND farm_id IS NULL
                       AND farm_admin_user_id IS NULL
                       AND approved_plan_code IS NULL
                       AND approved_modules_snapshot IS NULL
                       AND approved_role_limits_snapshot IS NULL
                       AND approved_trial_days IS NULL
                       AND approved_by_user_id IS NULL
                       AND approved_at IS NULL
                       AND provisioning_started_at IS NULL
                       AND provisioned_at IS NULL
                       AND activated_at IS NULL"
                );

            $update->execute([
                $rejectionReasonCode,
                $rejectedByUserId,
                $requestId,
            ]);

            if ($update->rowCount() !== 1) {
                throw new RuntimeException(
                    'Trial onboarding request could not be rejected.'
                );
            }

            $pdo->commit();

            return [
                'request_id' =>
                    $requestId,

                'request_reference' =>
                    (string)$request[
                        'request_reference'
                    ],

                'status' =>
                    'rejected',

                'rejection_reason_code' =>
                    $rejectionReasonCode,

                'rejected_by_user_id' =>
                    $rejectedByUserId,

                'already_rejected' =>
                    false,
            ];

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }
}
