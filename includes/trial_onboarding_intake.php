<?php

declare(strict_types=1);

/**
 * V3.2 public trial-onboarding intake authority.
 *
 * Responsibilities:
 * - normalize and validate public request identity;
 * - normalize requested modules through canonical entitlement policy;
 * - generate the opaque request reference;
 * - derive a one-way source fingerprint;
 * - prevent duplicate open requests;
 * - persist exactly one pending_review request.
 *
 * Deliberately does NOT:
 * - approve or reject requests;
 * - choose a subscription plan;
 * - choose trial length;
 * - persist approved module/role-limit snapshots;
 * - create farms;
 * - create users;
 * - issue credentials;
 * - send activation mail;
 * - start a trial;
 * - mutate billing/subscriptions.
 *
 * Transaction ownership:
 * - owns its transaction only when the caller has not already opened one.
 */

require_once __DIR__ . '/trial_onboarding_request.php';
require_once __DIR__ . '/farm_profile.php';
require_once __DIR__ . '/account_identity_policy.php';
require_once __DIR__ . '/account_credential_lifecycle.php';
require_once __DIR__ . '/farm_contact_email.php';
require_once __DIR__ . '/farm_entitlements.php';

if (!class_exists(
    'TrialOnboardingIntakeConflict'
)) {
    final class TrialOnboardingIntakeConflict
        extends DomainException
    {
        private string $reasonCode;

        public function __construct(
            string $reasonCode
        ) {
            $allowed = [
                'workspace_request_in_progress',
                'admin_email_request_in_progress',
                'open_request_exists',
            ];

            if (
                !in_array(
                    $reasonCode,
                    $allowed,
                    true
                )
            ) {
                $reasonCode =
                    'open_request_exists';
            }

            $this->reasonCode =
                $reasonCode;

            parent::__construct(
                'Trial onboarding request conflicts with an existing open request.'
            );
        }

        public function reasonCode(): string
        {
            return $this->reasonCode;
        }
    }
}

if (!function_exists('trial_onboarding_intake_text')) {
    function trial_onboarding_intake_text(
        string $value,
        int $maxCharacters,
        string $fieldLabel,
        bool $required = true
    ): ?string {
        $value = trim($value);

        if ($value === '') {
            if ($required) {
                throw new InvalidArgumentException(
                    $fieldLabel . ' is required.'
                );
            }

            return null;
        }

        $length =
            function_exists('account_identity_character_length')
                ? account_identity_character_length($value)
                : strlen($value);

        if ($length > $maxCharacters) {
            throw new InvalidArgumentException(
                $fieldLabel
                . ' must be '
                . $maxCharacters
                . ' characters or fewer.'
            );
        }

        return $value;
    }
}

if (!function_exists('trial_onboarding_intake_source_fingerprint')) {
    function trial_onboarding_intake_source_fingerprint(
        string $adminEmail,
        string $workspaceId,
        ?string $ipAddress = null
    ): string {
        $ipAddress =
            trim(
                $ipAddress
                ?? (string)($_SERVER['REMOTE_ADDR'] ?? '')
            );

        /*
         * Store only a one-way digest.
         * The raw IP is never persisted by this authority.
         */
        return hash(
            'sha256',
            strtolower(trim($adminEmail))
            . "\n"
            . strtolower(trim($workspaceId))
            . "\n"
            . $ipAddress
        );
    }
}

if (!function_exists('trial_onboarding_intake_open_statuses')) {
    function trial_onboarding_intake_open_statuses(): array
    {
        return [
            'pending_review',
            'approved',
            'provisioning',
            'provisioned',
        ];
    }
}

if (!function_exists('trial_onboarding_intake_normalize')) {
    function trial_onboarding_intake_normalize(
        array $input,
        ?string $ipAddress = null
    ): array {
        $farmName =
            (string)trial_onboarding_intake_text(
                (string)($input['farm_name'] ?? ''),
                150,
                'Farm name'
            );

        farm_profile_validate_name(
            $farmName
        );

        $workspaceId =
            farm_profile_normalize_workspace_id(
                (string)(
                    $input['requested_workspace_id']
                    ?? ''
                )
            );

        farm_profile_validate_workspace_id(
            $workspaceId
        );

        $adminFullName =
            account_identity_normalize_full_name(
                (string)($input['admin_full_name'] ?? '')
            );

        $adminUsername =
            account_identity_normalize_username(
                (string)($input['admin_username'] ?? '')
            );

        $adminEmail =
            account_credential_normalize_email(
                (string)($input['admin_email'] ?? '')
            );

        $contactName =
            trial_onboarding_intake_text(
                (string)($input['contact_name'] ?? ''),
                150,
                'Contact name',
                false
            );

        $contactEmailRaw =
            trim(
                (string)($input['contact_email'] ?? '')
            );

        $contactEmail =
            $contactEmailRaw === ''
                ? null
                : farm_contact_email_normalize(
                    $contactEmailRaw
                );

        $modulesRaw =
            $input['modules']
            ?? $input['requested_modules']
            ?? [];

        if (!is_array($modulesRaw)) {
            throw new InvalidArgumentException(
                'Select at least one requested module.'
            );
        }

        $modules =
            farm_entitlement_normalize_modules(
                $modulesRaw
            );

        if ($modules === []) {
            throw new InvalidArgumentException(
                'Select at least one requested module.'
            );
        }

        sort(
            $modules,
            SORT_STRING
        );

        $modulesJson =
            json_encode(
                $modules,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            );

        if (!is_string($modulesJson)) {
            throw new RuntimeException(
                'Requested modules could not be encoded.'
            );
        }

        return [
            'farm_name' =>
                $farmName,

            'requested_workspace_id' =>
                $workspaceId,

            'admin_full_name' =>
                $adminFullName,

            'admin_username' =>
                $adminUsername,

            'admin_email' =>
                $adminEmail,

            'contact_name' =>
                $contactName,

            'contact_email' =>
                $contactEmail,

            'requested_modules' =>
                $modules,

            'requested_modules_snapshot' =>
                $modulesJson,

            'source_fingerprint' =>
                trial_onboarding_intake_source_fingerprint(
                    $adminEmail,
                    $workspaceId,
                    $ipAddress
                ),
        ];
    }
}

if (!function_exists('trial_onboarding_intake_assert_available')) {
    function trial_onboarding_intake_assert_available(
        PDO $pdo,
        array $contract
    ): void {
        /*
         * The requested workspace may not collide with a provisioned farm.
         */
        farm_profile_assert_workspace_id_available(
            $pdo,
            (string)$contract['requested_workspace_id']
        );

        $statuses =
            trial_onboarding_intake_open_statuses();

        $placeholders =
            implode(
                ',',
                array_fill(
                    0,
                    count($statuses),
                    '?'
                )
            );

        /*
         * Keep duplicate-open-request policy inside this authority.
         * No status-specific duplicate logic belongs in the public route.
         */
        $stmt = $pdo->prepare(
            "SELECT
                id,
                admin_email,
                requested_workspace_id,
                source_fingerprint,
                status
             FROM trial_onboarding_requests
             WHERE (
                    admin_email = ?
                    OR requested_workspace_id = ?
                    OR source_fingerprint = ?
                   )
               AND status IN ($placeholders)
             ORDER BY id DESC
             FOR UPDATE"
        );

        $stmt->execute(
            array_merge(
                [
                    (string)$contract['admin_email'],
                    (string)$contract['requested_workspace_id'],
                    (string)$contract['source_fingerprint'],
                ],
                $statuses
            )
        );

        $matches =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];

        if ($matches === []) {
            return;
        }

        $workspaceMatched = false;
        $emailMatched = false;
        $fingerprintMatched = false;

        foreach ($matches as $match) {
            if (
                hash_equals(
                    (string)$contract[
                        'requested_workspace_id'
                    ],
                    (string)(
                        $match[
                            'requested_workspace_id'
                        ]
                        ?? ''
                    )
                )
            ) {
                $workspaceMatched = true;
            }

            if (
                hash_equals(
                    (string)$contract[
                        'admin_email'
                    ],
                    (string)(
                        $match['admin_email']
                        ?? ''
                    )
                )
            ) {
                $emailMatched = true;
            }

            if (
                hash_equals(
                    (string)$contract[
                        'source_fingerprint'
                    ],
                    (string)(
                        $match[
                            'source_fingerprint'
                        ]
                        ?? ''
                    )
                )
            ) {
                $fingerprintMatched = true;
            }
        }

        /*
         * Do not expose which identity field matched when multiple
         * signals point to an existing request.
         */
        if (
            $workspaceMatched
            && !$emailMatched
        ) {
            throw new TrialOnboardingIntakeConflict(
                'workspace_request_in_progress'
            );
        }

        if (
            $emailMatched
            && !$workspaceMatched
        ) {
            throw new TrialOnboardingIntakeConflict(
                'admin_email_request_in_progress'
            );
        }

        if (
            $workspaceMatched
            || $emailMatched
            || $fingerprintMatched
        ) {
            throw new TrialOnboardingIntakeConflict(
                'open_request_exists'
            );
        }

        throw new TrialOnboardingIntakeConflict(
            'open_request_exists'
        );
    }
}

if (!function_exists('trial_onboarding_intake_create')) {
    function trial_onboarding_intake_create(
        PDO $pdo,
        array $input,
        ?string $ipAddress = null
    ): array {
        $contract =
            trial_onboarding_intake_normalize(
                $input,
                $ipAddress
            );

        $startedTransaction =
            !$pdo->inTransaction();

        try {
            if ($startedTransaction) {
                $pdo->beginTransaction();
            }

            trial_onboarding_intake_assert_available(
                $pdo,
                $contract
            );

            $requestReference =
                trial_onboarding_request_reference();

            $stmt = $pdo->prepare(
                "INSERT INTO trial_onboarding_requests (
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
                    approved_role_limits_snapshot,
                    approved_trial_days,

                    review_reason_code,
                    rejection_reason_code,

                    source_fingerprint,

                    farm_id,
                    farm_admin_user_id,
                    approved_by_user_id,

                    approved_at,
                    provisioning_started_at,
                    provisioned_at,
                    activated_at,
                    rejected_at,
                    cancelled_at
                ) VALUES (
                    ?,
                    'pending_review',
                    NULL,

                    ?,
                    ?,

                    ?,
                    ?,
                    ?,

                    ?,
                    ?,

                    ?,

                    NULL,
                    NULL,
                    NULL,
                    NULL,

                    NULL,
                    NULL,

                    ?,

                    NULL,
                    NULL,
                    NULL,

                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    NULL
                )"
            );

            $stmt->execute([
                $requestReference,

                $contract['farm_name'],
                $contract['requested_workspace_id'],

                $contract['admin_full_name'],
                $contract['admin_username'],
                $contract['admin_email'],

                $contract['contact_name'],
                $contract['contact_email'],

                $contract['requested_modules_snapshot'],

                $contract['source_fingerprint'],
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException(
                    'Trial request was not created.'
                );
            }

            $requestId =
                (int)$pdo->lastInsertId();

            if ($requestId < 1) {
                throw new RuntimeException(
                    'Trial request identity was not created.'
                );
            }

            if ($startedTransaction) {
                $pdo->commit();
            }

            return [
                'request_id' =>
                    $requestId,

                'request_reference' =>
                    $requestReference,

                'status' =>
                    'pending_review',

                'farm_name' =>
                    $contract['farm_name'],

                'requested_workspace_id' =>
                    $contract['requested_workspace_id'],

                'admin_full_name' =>
                    $contract['admin_full_name'],

                'admin_username' =>
                    $contract['admin_username'],

                'admin_email' =>
                    $contract['admin_email'],

                'contact_name' =>
                    $contract['contact_name'],

                'contact_email' =>
                    $contract['contact_email'],

                'requested_modules' =>
                    $contract['requested_modules'],
            ];

        } catch (Throwable $exception) {
            if (
                $startedTransaction
                && $pdo->inTransaction()
            ) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }
}
