<?php

declare(strict_types=1);

/**
 * V3.1 shared pending-account writer.
 *
 * Responsibilities:
 * - normalize required credential email through the central lifecycle;
 * - create an unknowable server-generated placeholder password hash;
 * - insert a new account explicitly as pending_activation;
 * - enqueue activation delivery in the same caller-owned transaction;
 * - atomically correct a pending account credential email and enqueue
 *   another activation-delivery intent.
 *
 * Explicit non-responsibilities:
 * - opening, committing, or rolling back caller transactions;
 * - tenant role assignment;
 * - seat/capacity policy;
 * - farm creation/update policy;
 * - HTTP/forms/CSRF;
 * - synchronous mail delivery;
 * - activation-token generation.
 *
 * Callers MUST own the surrounding database transaction so user creation,
 * tenant role assignment, and activation-outbox intent commit or roll back
 * together.
 */

require_once __DIR__
    . '/account_credential_lifecycle.php';

require_once __DIR__
    . '/account_credential_outbox.php';

if (!function_exists('account_pending_user_assert_transaction')) {
    function account_pending_user_assert_transaction(
        PDO $pdo
    ): void {
        if (!$pdo->inTransaction()) {
            throw new RuntimeException(
                'Pending account writes require a caller-owned database transaction.'
            );
        }
    }
}

if (!function_exists('account_pending_user_placeholder_hash')) {
    function account_pending_user_placeholder_hash(): string
    {
        /*
         * The random plaintext exists only long enough to be hashed here.
         * It is never returned, persisted, emailed, rendered, or logged.
         *
         * 32 random bytes -> 64 hexadecimal characters, comfortably above
         * the shared minimum password length.
         */
        $unusableSecret =
            bin2hex(
                random_bytes(32)
            );

        return password_security_hash(
            $unusableSecret
        );
    }
}

if (!function_exists('account_pending_user_normalize_username')) {
    function account_pending_user_normalize_username(
        string $username
    ): string {
        $username =
            trim(
                $username
            );

        if (
            $username === ''
            || strlen($username) > 120
        ) {
            throw new InvalidArgumentException(
                'Enter a valid username.'
            );
        }

        return $username;
    }
}

if (!function_exists('account_pending_user_normalize_user_type')) {
    function account_pending_user_normalize_user_type(
        string $userType
    ): string {
        $userType =
            trim(
                $userType
            );

        if (
            $userType === ''
            || strlen($userType) > 80
        ) {
            throw new InvalidArgumentException(
                'A valid account type is required.'
            );
        }

        return $userType;
    }
}

if (!function_exists('account_pending_user_normalize_full_name')) {
    function account_pending_user_normalize_full_name(
        string $fullName
    ): string {
        $fullName =
            trim(
                $fullName
            );

        if (strlen($fullName) > 180) {
            throw new InvalidArgumentException(
                'Account name is too long.'
            );
        }

        return $fullName;
    }
}

if (!function_exists('account_pending_user_create')) {
    function account_pending_user_create(
        PDO $pdo,
        int $farmId,
        string $username,
        string $email,
        string $userType,
        string $fullName
    ): array {
        account_pending_user_assert_transaction(
            $pdo
        );

        if ($farmId < 1) {
            throw new InvalidArgumentException(
                'A valid farm is required.'
            );
        }

        $username =
            account_pending_user_normalize_username(
                $username
            );

        $email =
            account_credential_normalize_email(
                $email
            );

        $userType =
            account_pending_user_normalize_user_type(
                $userType
            );

        $fullName =
            account_pending_user_normalize_full_name(
                $fullName
            );

        $placeholderHash =
            account_pending_user_placeholder_hash();

        $stmt =
            $pdo->prepare(
                "INSERT INTO users (
                    farm_id,
                    username,
                    password,
                    email,
                    credential_state,
                    user_type,
                    full_name
                 ) VALUES (
                    ?,
                    ?,
                    ?,
                    ?,
                    'pending_activation',
                    ?,
                    ?
                 )"
            );

        $stmt->execute([
            $farmId,
            $username,
            $placeholderHash,
            $email,
            $userType,
            $fullName,
        ]);

        $userId =
            (int)$pdo->lastInsertId();

        if ($userId < 1) {
            throw new RuntimeException(
                'Pending account creation did not return a user identity.'
            );
        }

        /*
         * The outbox INSERT participates in this same caller-owned
         * transaction. If it fails, the caller rolls back the account and
         * any role assignments instead of leaving a stranded pending user.
         */
        $outboxJobId =
            account_credential_outbox_enqueue(
                $pdo,
                $userId,
                'activation'
            );

        return [
            'user_id' => $userId,
            'email' => $email,
            'credential_state' =>
                'pending_activation',
            'outbox_job_id' =>
                $outboxJobId,
        ];
    }
}

if (!function_exists('account_pending_user_update_email')) {
    function account_pending_user_update_email(
        PDO $pdo,
        int $userId,
        string $email
    ): array {
        account_pending_user_assert_transaction(
            $pdo
        );

        if ($userId < 1) {
            throw new InvalidArgumentException(
                'A valid pending account is required.'
            );
        }

        $email =
            account_credential_normalize_email(
                $email
            );

        $select =
            $pdo->prepare(
                "SELECT
                    id,
                    email,
                    credential_state
                 FROM users
                 WHERE id = ?
                 LIMIT 1
                 FOR UPDATE"
            );

        $select->execute([
            $userId,
        ]);

        $user =
            $select->fetch(PDO::FETCH_ASSOC)
            ?: null;

        if (!$user) {
            throw new RuntimeException(
                'Pending account could not be found.'
            );
        }

        if (
            (string)($user['credential_state'] ?? '')
            !== 'pending_activation'
        ) {
            throw new RuntimeException(
                'Credential email correction is only available before account activation.'
            );
        }

        $currentEmail =
            account_credential_normalize_email(
                (string)($user['email'] ?? '')
            );

        /*
         * An ordinary edit that leaves the normalized credential email
         * unchanged is a true no-op. Resending activation is an explicit,
         * separately named operation so unrelated edits cannot create
         * duplicate activation emails or supersede an earlier link.
         */
        if (hash_equals($currentEmail, $email)) {
            return [
                'user_id' => $userId,
                'email' => $email,
                'credential_state' =>
                    'pending_activation',
                'email_changed' => false,
                'outbox_job_id' => null,
            ];
        }

        $update =
            $pdo->prepare(
                "UPDATE users
                 SET email = ?
                 WHERE id = ?
                   AND credential_state =
                       'pending_activation'"
            );

        $update->execute([
            $email,
            $userId,
        ]);

        if ($update->rowCount() !== 1) {
            throw new RuntimeException(
                'Pending account email could not be updated.'
            );
        }

        $outboxJobId =
            account_credential_outbox_enqueue(
                $pdo,
                $userId,
                'activation'
            );

        return [
            'user_id' => $userId,
            'email' => $email,
            'credential_state' =>
                'pending_activation',
            'email_changed' => true,
            'outbox_job_id' =>
                $outboxJobId,
        ];
    }
}

if (!function_exists('account_pending_user_resend_activation')) {
    function account_pending_user_resend_activation(
        PDO $pdo,
        int $userId
    ): array {
        account_pending_user_assert_transaction(
            $pdo
        );

        if ($userId < 1) {
            throw new InvalidArgumentException(
                'A valid pending account is required.'
            );
        }

        $select =
            $pdo->prepare(
                "SELECT
                    id,
                    email,
                    credential_state
                 FROM users
                 WHERE id = ?
                 LIMIT 1
                 FOR UPDATE"
            );

        $select->execute([
            $userId,
        ]);

        $user =
            $select->fetch(PDO::FETCH_ASSOC)
            ?: null;

        if (!$user) {
            throw new RuntimeException(
                'Pending account could not be found.'
            );
        }

        if (
            (string)($user['credential_state'] ?? '')
            !== 'pending_activation'
        ) {
            throw new RuntimeException(
                'Activation resend is only available before account activation.'
            );
        }

        $email =
            account_credential_normalize_email(
                (string)($user['email'] ?? '')
            );

        $outboxJobId =
            account_credential_outbox_enqueue(
                $pdo,
                $userId,
                'activation'
            );

        return [
            'user_id' => $userId,
            'email' => $email,
            'credential_state' =>
                'pending_activation',
            'outbox_job_id' =>
                $outboxJobId,
        ];
    }
}
