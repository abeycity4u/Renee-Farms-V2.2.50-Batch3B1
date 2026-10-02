<?php

declare(strict_types=1);

/**
 * V3.2 approved trial-request provisioning authority.
 *
 * Responsibilities:
 * - own the atomic provisioning transaction;
 * - lock exactly one onboarding request FOR UPDATE;
 * - accept only an approved request for first-time provisioning;
 * - validate approved commercial/entitlement snapshots;
 * - call the canonical tenant provisioner exactly once;
 * - bind the resulting farm and Farm Admin back to the request;
 * - transition approved -> provisioning -> provisioned atomically;
 * - return an existing provisioned binding safely on retry.
 *
 * Explicit non-responsibilities:
 * - public request submission;
 * - approval/rejection decisions;
 * - abuse/rate-limit policy;
 * - credential activation;
 * - starting the 14-day trial clock;
 * - billing/payment application;
 * - synchronous email delivery.
 */

require_once __DIR__
    . '/trial_onboarding_request.php';

require_once __DIR__
    . '/tenant_provisioning.php';

require_once __DIR__
    . '/billing_commercial_product.php';

if (!function_exists(
    'trial_onboarding_provisioning_decode_json_array'
)) {
    function trial_onboarding_provisioning_decode_json_array(
        ?string $value,
        string $fieldName
    ): array {
        $value = trim((string)$value);

        if ($value === '') {
            throw new RuntimeException(
                $fieldName
                . ' is required before trial provisioning.'
            );
        }

        try {
            $decoded =
                json_decode(
                    $value,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
        } catch (JsonException $e) {
            throw new RuntimeException(
                $fieldName
                . ' must contain valid JSON.'
            );
        }

        if (!is_array($decoded)) {
            throw new RuntimeException(
                $fieldName
                . ' must contain a JSON array/object.'
            );
        }

        return $decoded;
    }
}

if (!function_exists(
    'trial_onboarding_provisioning_normalize_role_limits'
)) {
    function trial_onboarding_provisioning_normalize_role_limits(
        array $limits
    ): array {
        $normalized = [];

        foreach (
            subscription_seat_roles()
            as $roleCode => $_label
        ) {
            if (!array_key_exists(
                $roleCode,
                $limits
            )) {
                throw new RuntimeException(
                    'Approved role-limit snapshot is incomplete.'
                );
            }

            $value =
                filter_var(
                    $limits[$roleCode],
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
                    'Approved role-limit snapshot contains an invalid limit.'
                );
            }

            $normalized[$roleCode] =
                (int)$value;
        }

        ksort($normalized);

        return $normalized;
    }
}

if (!function_exists(
    'trial_onboarding_provisioning_assert_approved_contract'
)) {
    function trial_onboarding_provisioning_assert_approved_contract(
        array $request
    ): array {
        $status =
            trial_onboarding_request_normalize_status(
                (string)(
                    $request['status']
                    ?? ''
                )
            );

        if (!trial_onboarding_request_is_provisionable(
            $status
        )) {
            throw new RuntimeException(
                'Only an approved trial request can be provisioned.'
            );
        }

        $farmName =
            trim(
                (string)(
                    $request['farm_name']
                    ?? ''
                )
            );

        $workspaceId =
            trim(
                (string)(
                    $request['requested_workspace_id']
                    ?? ''
                )
            );

        $adminFullName =
            trim(
                (string)(
                    $request['admin_full_name']
                    ?? ''
                )
            );

        $adminUsername =
            trim(
                (string)(
                    $request['admin_username']
                    ?? ''
                )
            );

        $adminEmail =
            trim(
                (string)(
                    $request['admin_email']
                    ?? ''
                )
            );

        if (
            $farmName === ''
            || $workspaceId === ''
            || $adminFullName === ''
            || $adminUsername === ''
            || $adminEmail === ''
        ) {
            throw new RuntimeException(
                'Approved trial request is missing required tenant identity fields.'
            );
        }

        $planCode =
            strtolower(
                trim(
                    (string)(
                        $request['approved_plan_code']
                        ?? ''
                    )
                )
            );

        if (
            $planCode === ''
            || !subscription_plan_is_valid(
                $planCode
            )
        ) {
            throw new RuntimeException(
                'Approved trial request does not contain a valid plan.'
            );
        }

        $modulesSnapshot =
            trial_onboarding_provisioning_decode_json_array(
                $request[
                    'approved_modules_snapshot'
                ] ?? null,
                'Approved modules snapshot'
            );

        try {
            $modules =
                billing_commercial_product_selection_modules(
                    $modulesSnapshot
                );
        } catch (InvalidArgumentException $exception) {
            throw new RuntimeException(
                'Approved trial request contains an invalid commercial product selection.',
                0,
                $exception
            );
        }

        $trialDays =
            filter_var(
                $request['approved_trial_days']
                    ?? null,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                        'max_range' => 365,
                    ],
                ]
            );

        if (
            $trialDays === false
            || (int)$trialDays
                !== trial_onboarding_trial_days()
        ) {
            throw new RuntimeException(
                'Approved trial duration does not match the canonical trial policy.'
            );
        }

        $approvedRoleLimits =
            trial_onboarding_provisioning_normalize_role_limits(
                trial_onboarding_provisioning_decode_json_array(
                    $request[
                        'approved_role_limits_snapshot'
                    ] ?? null,
                    'Approved role-limit snapshot'
                )
            );

        $seatAddOns =
            subscription_seat_normalize_addons(
                []
            );

        $derivedRoleLimits =
            subscription_plan_effective_role_limits(
                $planCode,
                $modules,
                $seatAddOns
            );

        $derivedRoleLimits =
            trial_onboarding_provisioning_normalize_role_limits(
                $derivedRoleLimits
            );

        if (
            $approvedRoleLimits
            !== $derivedRoleLimits
        ) {
            throw new RuntimeException(
                'Approved role-limit snapshot no longer matches the current plan policy. Reapproval is required.'
            );
        }

        $contactName =
            trim(
                (string)(
                    $request['contact_name']
                    ?? ''
                )
            );

        $contactEmail =
            trim(
                (string)(
                    $request['contact_email']
                    ?? ''
                )
            );

        if ($contactEmail === '') {
            $contactEmail = $adminEmail;
        }

        $recordedByUserId =
            filter_var(
                $request['approved_by_user_id']
                    ?? null,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                    ],
                ]
            );

        return [
            'name' =>
                $farmName,

            'slug' =>
                $workspaceId,

            'primary_color' =>
                '#198754',

            'contact_name' =>
                $contactName,

            'contact_email' =>
                $contactEmail,

            'admin_username' =>
                $adminUsername,

            'admin_full_name' =>
                $adminFullName,

            'admin_email' =>
                $adminEmail,

            'plan_code' =>
                $planCode,

            'subscription_status' =>
                'trial',

            /*
             * Trial time deliberately starts later,
             * when the Farm Admin activates.
             */
            'subscription_starts_at' =>
                null,

            'trial_ends_at' =>
                null,

            'subscription_ends_at' =>
                null,

            'modules' =>
                $modules,

            'seat_addons' =>
                $seatAddOns,

            'recorded_by_user_id' =>
                $recordedByUserId === false
                    ? null
                    : (int)$recordedByUserId,

            'history_reason' =>
                'trial_onboarding_provisioned',

            'approved_trial_days' =>
                (int)$trialDays,

            'approved_role_limits' =>
                $approvedRoleLimits,
        ];
    }
}

if (!function_exists(
    'trial_onboarding_provisioning_existing_result'
)) {
    function trial_onboarding_provisioning_existing_result(
        array $request
    ): array {
        $farmId =
            (int)(
                $request['farm_id']
                ?? 0
            );

        $farmAdminUserId =
            (int)(
                $request['farm_admin_user_id']
                ?? 0
            );

        if (
            $farmId < 1
            || $farmAdminUserId < 1
        ) {
            throw new RuntimeException(
                'Provisioned trial request is missing its tenant binding.'
            );
        }

        return [
            'request_id' =>
                (int)$request['id'],

            'request_reference' =>
                (string)$request[
                    'request_reference'
                ],

            'status' =>
                (string)$request['status'],

            'farm_id' =>
                $farmId,

            'farm_admin_user_id' =>
                $farmAdminUserId,

            'already_provisioned' =>
                true,
        ];
    }
}

if (!function_exists(
    'trial_onboarding_provision_approved_request'
)) {
    function trial_onboarding_provision_approved_request(
        PDO $pdo,
        int $requestId
    ): array {
        if ($requestId < 1) {
            throw new InvalidArgumentException(
                'A valid trial onboarding request is required.'
            );
        }

        if ($pdo->inTransaction()) {
            throw new RuntimeException(
                'Trial onboarding provisioning owns its database transaction.'
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

            if (in_array(
                $status,
                [
                    'provisioned',
                    'activated',
                ],
                true
            )) {
                $existing =
                    trial_onboarding_provisioning_existing_result(
                        $request
                    );

                $pdo->commit();

                return $existing;
            }

            /*
             * Because approved -> provisioning -> provisioned
             * occurs inside this one transaction, a committed
             * provisioning state without bindings is abnormal.
             */
            if ($status === 'provisioning') {
                throw new RuntimeException(
                    'Trial onboarding request is in an incomplete provisioning state and requires review.'
                );
            }

            $tenantInput =
                trial_onboarding_provisioning_assert_approved_contract(
                    $request
                );

            trial_onboarding_request_assert_transition(
                $status,
                'provisioning'
            );

            $markProvisioning =
                $pdo->prepare(
                    "UPDATE trial_onboarding_requests
                     SET
                        status = 'provisioning',
                        provisioning_started_at = NOW()
                     WHERE id = ?
                       AND status = 'approved'"
                );

            $markProvisioning->execute([
                $requestId,
            ]);

            if (
                $markProvisioning->rowCount()
                !== 1
            ) {
                throw new RuntimeException(
                    'Trial onboarding request could not enter provisioning.'
                );
            }

            $provisioned =
                tenant_provisioning_create(
                    $pdo,
                    $tenantInput
                );

            $farmId =
                (int)$provisioned[
                    'farm_id'
                ];

            $farmAdminUserId =
                (int)$provisioned[
                    'farm_admin_user_id'
                ];

            if (
                $farmId < 1
                || $farmAdminUserId < 1
            ) {
                throw new RuntimeException(
                    'Tenant provisioner did not return a complete tenant binding.'
                );
            }

            trial_onboarding_request_assert_transition(
                'provisioning',
                'provisioned'
            );

            $markProvisioned =
                $pdo->prepare(
                    "UPDATE trial_onboarding_requests
                     SET
                        status = 'provisioned',
                        farm_id = ?,
                        farm_admin_user_id = ?,
                        provisioned_at = NOW()
                     WHERE id = ?
                       AND status = 'provisioning'
                       AND farm_id IS NULL
                       AND farm_admin_user_id IS NULL"
                );

            $markProvisioned->execute([
                $farmId,
                $farmAdminUserId,
                $requestId,
            ]);

            if (
                $markProvisioned->rowCount()
                !== 1
            ) {
                throw new RuntimeException(
                    'Trial onboarding request could not be bound to the provisioned tenant.'
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
                    'provisioned',

                'farm_id' =>
                    $farmId,

                'farm_admin_user_id' =>
                    $farmAdminUserId,

                'activation_email' =>
                    (string)$provisioned[
                        'activation_email'
                    ],

                'activation_outbox_job_id' =>
                    (int)$provisioned[
                        'activation_outbox_job_id'
                    ],

                'already_provisioned' =>
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
