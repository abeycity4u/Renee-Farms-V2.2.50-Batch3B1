<?php

declare(strict_types=1);

/**
 * V3.2 shared account-activation completion authority.
 *
 * Responsibilities:
 * - own the outer transaction for successful account activation;
 * - reuse the existing credential lifecycle;
 * - detect whether the activated account is the bound Farm Admin
 *   for a provisioned trial-onboarding request;
 * - start that tenant's canonical 14-day trial exactly once;
 * - move the onboarding request from provisioned to activated;
 * - append the resulting commercial subscription snapshot.
 *
 * Ordinary account activations that are not bound to a trial request
 * remain ordinary credential activations and do not touch tenant clocks.
 *
 * Explicit non-responsibilities:
 * - token generation;
 * - token hashing/validation policy;
 * - password hashing/policy;
 * - email delivery;
 * - public HTTP/CSRF/rate-limit policy;
 * - tenant provisioning;
 * - billing payment application.
 */

require_once __DIR__
    . '/account_credential_lifecycle.php';

require_once __DIR__
    . '/trial_onboarding_request.php';

require_once __DIR__
    . '/subscription_record.php';

if (!function_exists(
    'account_activation_completion_timestamp'
)) {
    /**
     * Capture one canonical database timestamp for the whole activation
     * completion transaction.
     */
    function account_activation_completion_timestamp(
        PDO $pdo
    ): string {
        $value =
            $pdo->query(
                "SELECT DATE_FORMAT(
                    NOW(),
                    '%Y-%m-%d %H:%i:%s'
                 )"
            )->fetchColumn();

        $value =
            trim((string)$value);

        if ($value === '') {
            throw new RuntimeException(
                'Activation timestamp could not be established.'
            );
        }

        return $value;
    }
}

if (!function_exists(
    'account_activation_completion_trial_end'
)) {
    /**
     * Calculate the exact canonical trial boundary from the single
     * captured activation timestamp.
     */
    function account_activation_completion_trial_end(
        PDO $pdo,
        string $activatedAt,
        int $trialDays
    ): string {
        if ($trialDays < 1 || $trialDays > 365) {
            throw new InvalidArgumentException(
                'Trial duration is outside the allowed range.'
            );
        }

        /*
         * $trialDays comes only from the canonical shared trial policy
         * and is cast before becoming SQL syntax.
         */
        $days =
            (int)$trialDays;

        $stmt =
            $pdo->prepare(
                "SELECT DATE_FORMAT(
                    DATE_ADD(
                        CAST(? AS DATETIME),
                        INTERVAL {$days} DAY
                    ),
                    '%Y-%m-%d %H:%i:%s'
                 )"
            );

        $stmt->execute([
            $activatedAt,
        ]);

        $value =
            trim(
                (string)$stmt->fetchColumn()
            );

        if ($value === '') {
            throw new RuntimeException(
                'Trial end timestamp could not be established.'
            );
        }

        return $value;
    }
}

if (!function_exists(
    'account_activation_completion_lock_request'
)) {
    /**
     * Load the unique onboarding request bound to this Farm Admin.
     *
     * No row means this is an ordinary non-trial account activation.
     */
    function account_activation_completion_lock_request(
        PDO $pdo,
        int $userId
    ): ?array {
        if ($userId < 1) {
            throw new InvalidArgumentException(
                'A valid activated user is required.'
            );
        }

        $stmt =
            $pdo->prepare(
                "SELECT
                    id,
                    request_reference,
                    status,
                    approved_plan_code,
                    approved_trial_days,
                    farm_id,
                    farm_admin_user_id,
                    provisioned_at,
                    activated_at
                 FROM trial_onboarding_requests
                 WHERE farm_admin_user_id = ?
                 LIMIT 1
                 FOR UPDATE"
            );

        $stmt->execute([
            $userId,
        ]);

        return
            $stmt->fetch(PDO::FETCH_ASSOC)
            ?: null;
    }
}

if (!function_exists(
    'account_activation_completion_lock_user'
)) {
    function account_activation_completion_lock_user(
        PDO $pdo,
        int $userId
    ): array {
        $stmt =
            $pdo->prepare(
                "SELECT
                    id,
                    farm_id,
                    user_type,
                    credential_state
                 FROM users
                 WHERE id = ?
                 LIMIT 1
                 FOR UPDATE"
            );

        $stmt->execute([
            $userId,
        ]);

        $user =
            $stmt->fetch(PDO::FETCH_ASSOC)
            ?: null;

        if (!is_array($user)) {
            throw new RuntimeException(
                'Activated account could not be reloaded.'
            );
        }

        return $user;
    }
}

if (!function_exists(
    'account_activation_completion_lock_farm'
)) {
    function account_activation_completion_lock_farm(
        PDO $pdo,
        int $farmId
    ): array {
        if ($farmId < 1) {
            throw new InvalidArgumentException(
                'A valid trial tenant is required.'
            );
        }

        $stmt =
            $pdo->prepare(
                "SELECT
                    id,
                    subscription_plan,
                    subscription_status,
                    subscription_starts_at,
                    trial_ends_at,
                    subscription_ends_at
                 FROM farms
                 WHERE id = ?
                 LIMIT 1
                 FOR UPDATE"
            );

        $stmt->execute([
            $farmId,
        ]);

        $farm =
            $stmt->fetch(PDO::FETCH_ASSOC)
            ?: null;

        if (!is_array($farm)) {
            throw new RuntimeException(
                'Provisioned trial tenant could not be found.'
            );
        }

        return $farm;
    }
}

if (!function_exists(
    'account_activation_complete'
)) {
    /**
     * Complete one account activation atomically.
     *
     * This service deliberately owns the outer transaction so the generic
     * credential lifecycle, optional trial-clock start, request transition,
     * and subscription-history capture either all succeed or all roll back.
     */
    function account_activation_complete(
        PDO $pdo,
        string $rawToken,
        string $newPassword
    ): array {
        if ($pdo->inTransaction()) {
            throw new RuntimeException(
                'Account activation completion requires control of its transaction boundary.'
            );
        }

        $pdo->beginTransaction();

        try {
            /*
             * Capture one timestamp before any successful state transition.
             * A trial activation uses this same value for request activation
             * and tenant subscription start.
             */
            $activatedAt =
                account_activation_completion_timestamp(
                    $pdo
                );

            /*
             * Existing lifecycle owns token validity, password policy,
             * password hashing, credential-state transition, token
             * consumption and outstanding-token invalidation.
             *
             * Because our transaction is already open, it does not commit
             * independently.
             */
            $credential =
                account_credential_consume_activation(
                    $pdo,
                    $rawToken,
                    $newPassword
                );

            $userId =
                (int)(
                    $credential['user_id']
                    ?? 0
                );

            if ($userId < 1) {
                throw new RuntimeException(
                    'Credential activation returned no account identity.'
                );
            }

            $request =
                account_activation_completion_lock_request(
                    $pdo,
                    $userId
                );

            /*
             * Ordinary activation path.
             *
             * No trial request is bound to this account, therefore credential
             * activation is the entire operation.
             */
            if ($request === null) {
                $pdo->commit();

                return [
                    'user_id' =>
                        $userId,

                    'credential_state' =>
                        'active',

                    'trial_started' =>
                        false,

                    'request_id' =>
                        null,

                    'farm_id' =>
                        null,

                    'activated_at' =>
                        $activatedAt,

                    'trial_ends_at' =>
                        null,
                ];
            }

            $requestStatus =
                (string)(
                    $request['status']
                    ?? ''
                );

            if (
                $requestStatus !== 'provisioned'
                || !empty(
                    $request['activated_at']
                )
            ) {
                throw new RuntimeException(
                    'Bound trial onboarding request is not awaiting activation.'
                );
            }

            trial_onboarding_request_assert_transition(
                'provisioned',
                'activated'
            );

            $canonicalTrialDays =
                trial_onboarding_trial_days();

            $approvedTrialDays =
                (int)(
                    $request[
                        'approved_trial_days'
                    ] ?? 0
                );

            if (
                $approvedTrialDays
                !== $canonicalTrialDays
            ) {
                throw new RuntimeException(
                    'Approved trial duration no longer matches canonical policy.'
                );
            }

            $farmId =
                (int)(
                    $request['farm_id']
                    ?? 0
                );

            $farmAdminUserId =
                (int)(
                    $request[
                        'farm_admin_user_id'
                    ] ?? 0
                );

            if (
                $farmId < 1
                || $farmAdminUserId !== $userId
            ) {
                throw new RuntimeException(
                    'Trial onboarding binding is incomplete or inconsistent.'
                );
            }

            $user =
                account_activation_completion_lock_user(
                    $pdo,
                    $userId
                );

            if (
                (int)(
                    $user['farm_id']
                    ?? 0
                ) !== $farmId
                || (
                    $user['user_type']
                    ?? ''
                ) !== 'farm_admin'
                || (
                    $user[
                        'credential_state'
                    ] ?? ''
                ) !== 'active'
            ) {
                throw new RuntimeException(
                    'Activated account does not match the provisioned Farm Admin binding.'
                );
            }

            $farm =
                account_activation_completion_lock_farm(
                    $pdo,
                    $farmId
                );

            $approvedPlan =
                strtolower(
                    trim(
                        (string)(
                            $request[
                                'approved_plan_code'
                            ] ?? ''
                        )
                    )
                );

            if (
                $approvedPlan === ''
                || (
                    $farm[
                        'subscription_plan'
                    ] ?? ''
                ) !== $approvedPlan
                || (
                    $farm[
                        'subscription_status'
                    ] ?? ''
                ) !== 'trial'
            ) {
                throw new RuntimeException(
                    'Provisioned tenant commercial state no longer matches the approved trial.'
                );
            }

            if (
                $farm[
                    'subscription_starts_at'
                ] !== null
                || $farm[
                    'trial_ends_at'
                ] !== null
                || $farm[
                    'subscription_ends_at'
                ] !== null
            ) {
                throw new RuntimeException(
                    'Provisioned trial clock has already been started or altered.'
                );
            }

            $trialEndsAt =
                account_activation_completion_trial_end(
                    $pdo,
                    $activatedAt,
                    $canonicalTrialDays
                );

            /*
             * Start all three tenant clock fields from one activation moment.
             * subscription_ends_at remains the canonical runtime expiry field;
             * trial_ends_at is retained for trial reporting/audit compatibility.
             */
            $startTrial =
                $pdo->prepare(
                    "UPDATE farms
                     SET
                        subscription_starts_at = ?,
                        trial_ends_at = ?,
                        subscription_ends_at = ?
                     WHERE id = ?
                       AND subscription_plan = ?
                       AND subscription_status = 'trial'
                       AND subscription_starts_at IS NULL
                       AND trial_ends_at IS NULL
                       AND subscription_ends_at IS NULL"
                );

            $startTrial->execute([
                $activatedAt,
                $trialEndsAt,
                $trialEndsAt,
                $farmId,
                $approvedPlan,
            ]);

            if (
                $startTrial->rowCount()
                !== 1
            ) {
                throw new RuntimeException(
                    'Trial clock could not be started exactly once.'
                );
            }

            $markActivated =
                $pdo->prepare(
                    "UPDATE trial_onboarding_requests
                     SET
                        status = 'activated',
                        activated_at = ?
                     WHERE id = ?
                       AND status = 'provisioned'
                       AND activated_at IS NULL
                       AND farm_id = ?
                       AND farm_admin_user_id = ?"
                );

            $markActivated->execute([
                $activatedAt,
                (int)$request['id'],
                $farmId,
                $userId,
            ]);

            if (
                $markActivated->rowCount()
                !== 1
            ) {
                throw new RuntimeException(
                    'Trial onboarding request could not complete activation exactly once.'
                );
            }

            $history =
                subscription_record_capture(
                    $pdo,
                    $farmId,
                    'trial_onboarding_activated',
                    $userId
                );

            if (
                empty($history['id'])
            ) {
                throw new RuntimeException(
                    'Activated trial subscription history was not recorded.'
                );
            }

            $pdo->commit();

            return [
                'user_id' =>
                    $userId,

                'credential_state' =>
                    'active',

                'trial_started' =>
                    true,

                'request_id' =>
                    (int)$request['id'],

                'farm_id' =>
                    $farmId,

                'activated_at' =>
                    $activatedAt,

                'trial_ends_at' =>
                    $trialEndsAt,

                'subscription_record_id' =>
                    (int)$history['id'],

                'subscription_record_inserted' =>
                    !empty(
                        $history[
                            'inserted'
                        ]
                    ),
            ];

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }
}
