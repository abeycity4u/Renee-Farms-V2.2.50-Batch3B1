<?php

declare(strict_types=1);

/**
 * V3.2 canonical tenant provisioning authority.
 *
 * Responsibilities:
 * - normalize and validate tenant creation contract;
 * - enforce workspace uniqueness;
 * - create the farms row;
 * - create the initial Farm Admin as pending_activation;
 * - persist tenant entitlements;
 * - assign the protected Farm Admin role;
 * - persist effective role limits;
 * - persist seat add-ons;
 * - capture initial subscription history.
 *
 * Transaction contract:
 * - caller MUST own an open database transaction;
 * - this service never begins, commits, or rolls back that transaction.
 *
 * Explicit non-responsibilities:
 * - HTTP/forms/CSRF;
 * - public onboarding approval policy;
 * - onboarding-request state transitions;
 * - logo file uploads;
 * - credential token generation;
 * - synchronous credential email delivery;
 * - trial activation/start clock;
 * - billing checkout/payment application.
 */

require_once __DIR__ . '/farm_contact_email.php';
require_once __DIR__ . '/farm_profile.php';
require_once __DIR__ . '/account_identity_policy.php';
require_once __DIR__ . '/account_pending_user.php';
require_once __DIR__ . '/farm_entitlements.php';
require_once __DIR__ . '/subscription_plan_catalog.php';
require_once __DIR__ . '/subscription_seat_policy.php';
require_once __DIR__ . '/subscription_record.php';

if (!function_exists('tenant_provisioning_assert_transaction')) {
    function tenant_provisioning_assert_transaction(
        PDO $pdo
    ): void {
        if (!$pdo->inTransaction()) {
            throw new RuntimeException(
                'Tenant provisioning requires a caller-owned database transaction.'
            );
        }
    }
}

if (!function_exists('tenant_provisioning_create_statuses')) {
    function tenant_provisioning_create_statuses(): array
    {
        return [
            'trial',
            'active',
            'past_due',
            'suspended',
        ];
    }
}

if (!function_exists('tenant_provisioning_normalize_datetime')) {
    function tenant_provisioning_normalize_datetime(
        $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim((string)$value);

        if ($value === '') {
            return null;
        }

        $date =
            DateTimeImmutable::createFromFormat(
                '!Y-m-d H:i:s',
                $value
            );

        if (
            $date === false
            || $date->format('Y-m-d H:i:s') !== $value
        ) {
            throw new InvalidArgumentException(
                'Subscription timestamps must use Y-m-d H:i:s.'
            );
        }

        return $value;
    }
}

if (!function_exists('tenant_provisioning_ensure_farm_admin_role')) {
    function tenant_provisioning_ensure_farm_admin_role(
        PDO $pdo
    ): void {
        tenant_provisioning_assert_transaction($pdo);

        $stmt = $pdo->prepare(
            "INSERT INTO roles (
                code,
                name,
                is_platform_role
             ) VALUES (
                'farm_admin',
                'Admin / Farm Owner',
                0
             )
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                is_platform_role = 0"
        );

        $stmt->execute();
    }
}

if (!function_exists('tenant_provisioning_save_role_limits')) {
    function tenant_provisioning_save_role_limits(
        PDO $pdo,
        int $farmId,
        array $limits
    ): void {
        tenant_provisioning_assert_transaction($pdo);

        if ($farmId < 1) {
            throw new InvalidArgumentException(
                'A valid farm is required for role limits.'
            );
        }

        $stmt = $pdo->prepare(
            "INSERT INTO farm_role_limits (
                farm_id,
                role_code,
                max_users
             ) VALUES (
                ?,
                ?,
                ?
             )
             ON DUPLICATE KEY UPDATE
                max_users = VALUES(max_users)"
        );

        foreach ($limits as $role => $maxUsers) {
            $stmt->execute([
                $farmId,
                (string)$role,
                (int)$maxUsers,
            ]);
        }
    }
}

if (!function_exists('tenant_provisioning_normalize_contract')) {
    function tenant_provisioning_normalize_contract(
        array $input
    ): array {
        $profile =
            farm_profile_normalize_identity([
                'name' =>
                    (string)($input['name'] ?? ''),
                'slug' =>
                    (string)($input['slug'] ?? ''),
                'primary_color' =>
                    (string)(
                        $input['primary_color']
                        ?? '#198754'
                    ),
            ]);

        $username =
            account_identity_normalize_username(
                (string)(
                    $input['admin_username']
                    ?? ''
                )
            );

        $fullName =
            account_identity_normalize_full_name(
                (string)(
                    $input['admin_full_name']
                    ?? ''
                )
            );

        $emailPair =
            farm_contact_email_pair(
                (string)(
                    $input['admin_email']
                    ?? ''
                ),
                (string)(
                    $input['contact_email']
                    ?? ''
                )
            );

        $modules =
            farm_entitlement_normalize_modules(
                is_array($input['modules'] ?? null)
                    ? $input['modules']
                    : []
            );

        if (!$modules) {
            throw new InvalidArgumentException(
                'Select at least one tenant service entitlement.'
            );
        }

        $planCode =
            strtolower(
                trim(
                    (string)(
                        $input['plan_code']
                        ?? 'starter'
                    )
                )
            );

        if (!subscription_plan_is_valid($planCode)) {
            throw new InvalidArgumentException(
                'Unknown subscription plan.'
            );
        }

        $status =
            strtolower(
                trim(
                    (string)(
                        $input['subscription_status']
                        ?? 'trial'
                    )
                )
            );

        if (!in_array(
            $status,
            tenant_provisioning_create_statuses(),
            true
        )) {
            throw new InvalidArgumentException(
                'Unsupported initial subscription status.'
            );
        }

        $seatAddOns =
            subscription_seat_normalize_addons(
                is_array(
                    $input['seat_addons']
                    ?? null
                )
                    ? $input['seat_addons']
                    : []
            );

        $roleLimits =
            subscription_plan_effective_role_limits(
                $planCode,
                $modules,
                $seatAddOns
            );

        $recordedByUserId =
            filter_var(
                $input['recorded_by_user_id']
                    ?? null,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                    ],
                ]
            );

        return [
            'name' => $profile['name'],
            'slug' => $profile['slug'],
            'primary_color' =>
                $profile['primary_color'],

            'contact_name' =>
                trim(
                    (string)(
                        $input['contact_name']
                        ?? ''
                    )
                ),

            'contact_email' =>
                $emailPair['contact_email'],

            'admin_username' => $username,
            'admin_full_name' => $fullName,
            'admin_email' =>
                $emailPair['owner_email'],

            'plan_code' => $planCode,

            'subscription_status' =>
                $status,

            'subscription_starts_at' =>
                tenant_provisioning_normalize_datetime(
                    $input[
                        'subscription_starts_at'
                    ] ?? null
                ),

            'trial_ends_at' =>
                tenant_provisioning_normalize_datetime(
                    $input[
                        'trial_ends_at'
                    ] ?? null
                ),

            'subscription_ends_at' =>
                tenant_provisioning_normalize_datetime(
                    $input[
                        'subscription_ends_at'
                    ] ?? null
                ),

            'modules' => $modules,
            'seat_addons' => $seatAddOns,
            'role_limits' => $roleLimits,

            'recorded_by_user_id' =>
                $recordedByUserId === false
                    ? null
                    : (int)$recordedByUserId,

            'history_reason' =>
                trim(
                    (string)(
                        $input['history_reason']
                        ?? 'tenant_created'
                    )
                ) ?: 'tenant_created',
        ];
    }
}

if (!function_exists('tenant_provisioning_create')) {
    function tenant_provisioning_create(
        PDO $pdo,
        array $input
    ): array {
        tenant_provisioning_assert_transaction(
            $pdo
        );

        $contract =
            tenant_provisioning_normalize_contract(
                $input
            );

        farm_profile_assert_workspace_id_available(
            $pdo,
            $contract['slug']
        );

        tenant_provisioning_ensure_farm_admin_role(
            $pdo
        );

        $stmt = $pdo->prepare(
            "INSERT INTO farms (
                name,
                slug,
                primary_color,
                contact_name,
                contact_email,
                subscription_plan,
                subscription_status,
                subscription_starts_at,
                trial_ends_at,
                subscription_ends_at
             ) VALUES (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
             )"
        );

        $stmt->execute([
            $contract['name'],
            $contract['slug'],
            $contract['primary_color'],
            $contract['contact_name'],
            $contract['contact_email'],
            $contract['plan_code'],
            $contract['subscription_status'],
            $contract['subscription_starts_at'],
            $contract['trial_ends_at'],
            $contract['subscription_ends_at'],
        ]);

        $farmId =
            (int)$pdo->lastInsertId();

        if ($farmId < 1) {
            throw new RuntimeException(
                'Tenant creation did not return a farm identity.'
            );
        }

        $pendingAdmin =
            account_pending_user_create(
                $pdo,
                $farmId,
                $contract['admin_username'],
                $contract['admin_email'],
                'farm_admin',
                $contract['admin_full_name']
            );

        $farmAdminUserId =
            (int)$pendingAdmin['user_id'];

        sync_farm_entitlements(
            $pdo,
            $farmId,
            $contract['modules']
        );

        assign_protected_farm_admin_role(
            $pdo,
            $farmId,
            $farmAdminUserId
        );

        tenant_provisioning_save_role_limits(
            $pdo,
            $farmId,
            $contract['role_limits']
        );

        subscription_seat_save_addons(
            $pdo,
            $farmId,
            $contract['seat_addons']
        );

        $subscriptionRecord =
            subscription_record_capture(
                $pdo,
                $farmId,
                $contract['history_reason'],
                $contract['recorded_by_user_id']
            );

        return [
            'farm_id' => $farmId,

            'farm_admin_user_id' =>
                $farmAdminUserId,

            'credential_state' =>
                (string)$pendingAdmin[
                    'credential_state'
                ],

            'activation_email' =>
                (string)$pendingAdmin[
                    'email'
                ],

            'activation_outbox_job_id' =>
                (int)$pendingAdmin[
                    'outbox_job_id'
                ],

            'plan_code' =>
                $contract['plan_code'],

            'subscription_status' =>
                $contract[
                    'subscription_status'
                ],

            'modules' =>
                $contract['modules'],

            'seat_addons' =>
                $contract['seat_addons'],

            'role_limits' =>
                $contract['role_limits'],

            'subscription_record' =>
                $subscriptionRecord,
        ];
    }
}
