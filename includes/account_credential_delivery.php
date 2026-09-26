<?php

declare(strict_types=1);

/**
 * Shared V3.1 credential-delivery orchestration.
 *
 * Responsibilities:
 * - central activation/password-reset TTL policy;
 * - canonical account context for credential messages;
 * - token issuance through account_credential_lifecycle.php;
 * - canonical public activation/reset links;
 * - activation/reset message construction;
 * - outbound delivery through the central platform mail transport.
 *
 * Explicit non-responsibilities:
 * - public account lookup / enumeration policy;
 * - CSRF;
 * - guest rate limiting;
 * - HTTP routing or redirects;
 * - Farm Admin / Team User creation policy;
 * - login/session establishment;
 * - password mutation or token persistence internals.
 *
 * Mail rejection does not attempt broad token invalidation. The issued secret
 * remains undisclosed and expires normally; a retry issues a new same-purpose
 * token and the lifecycle service supersedes the previous one atomically.
 */

require_once __DIR__ . '/account_credential_lifecycle.php';
require_once __DIR__ . '/platform_public_url.php';
require_once __DIR__ . '/platform_mailer.php';

if (!function_exists('account_credential_activation_ttl_seconds')) {
    function account_credential_activation_ttl_seconds(): int
    {
        return 86400;
    }
}

if (!function_exists('account_credential_password_reset_ttl_seconds')) {
    function account_credential_password_reset_ttl_seconds(): int
    {
        return 3600;
    }
}

if (!function_exists('account_credential_delivery_ttl_seconds')) {
    function account_credential_delivery_ttl_seconds(
        string $purpose
    ): int {
        $purpose =
            account_credential_normalize_purpose(
                $purpose
            );

        return $purpose === 'activation'
            ? account_credential_activation_ttl_seconds()
            : account_credential_password_reset_ttl_seconds();
    }
}

if (!function_exists('account_credential_delivery_text')) {
    function account_credential_delivery_text(
        $value,
        int $maxLength = 180
    ): string {
        if ($maxLength < 1 || $maxLength > 500) {
            throw new InvalidArgumentException(
                'Credential message text length is invalid.'
            );
        }

        $value = trim(
            preg_replace(
                '/[\x00-\x1F\x7F]+/',
                ' ',
                (string)$value
            ) ?? ''
        );

        return substr(
            $value,
            0,
            $maxLength
        );
    }
}

if (!function_exists('account_credential_delivery_context')) {
    function account_credential_delivery_context(
        PDO $pdo,
        int $userId
    ): array {
        if ($userId < 1) {
            throw new InvalidArgumentException(
                'A valid user is required.'
            );
        }

        $stmt = $pdo->prepare(
            "SELECT
                u.id,
                u.farm_id,
                u.username,
                u.email,
                u.user_type,
                u.full_name,
                u.credential_state,
                f.name AS farm_name,
                f.slug AS workspace_id
             FROM users u
             LEFT JOIN farms f
                ON f.id = u.farm_id
             WHERE u.id = ?
             LIMIT 1"
        );

        $stmt->execute([$userId]);

        $account =
            $stmt->fetch(PDO::FETCH_ASSOC)
            ?: null;

        if (!$account) {
            throw new RuntimeException(
                'Account could not be found.'
            );
        }

        return [
            'user_id' => (int)$account['id'],
            'farm_id' => isset($account['farm_id'])
                ? (int)$account['farm_id']
                : null,
            'username' =>
                account_credential_delivery_text(
                    $account['username'] ?? '',
                    120
                ),
            'email' =>
                account_credential_normalize_email(
                    (string)($account['email'] ?? '')
                ),
            'user_type' =>
                account_credential_delivery_text(
                    $account['user_type'] ?? '',
                    80
                ),
            'full_name' =>
                account_credential_delivery_text(
                    $account['full_name'] ?? '',
                    160
                ),
            'credential_state' =>
                (string)($account['credential_state'] ?? ''),
            'farm_name' =>
                account_credential_delivery_text(
                    $account['farm_name'] ?? '',
                    180
                ),
            'workspace_id' =>
                account_credential_delivery_text(
                    $account['workspace_id'] ?? '',
                    120
                ),
        ];
    }
}

if (!function_exists('account_credential_delivery_link')) {
    function account_credential_delivery_link(
        string $purpose,
        string $rawToken
    ): string {
        $purpose =
            account_credential_normalize_purpose(
                $purpose
            );

        if ($rawToken === '') {
            throw new InvalidArgumentException(
                'Credential token is required.'
            );
        }

        $path = $purpose === 'activation'
            ? '/account/activate.php'
            : '/account/reset_password.php';

        return platform_public_url(
            $path,
            [
                'token' => $rawToken,
            ]
        );
    }
}

if (!function_exists('account_credential_delivery_expiry_text')) {
    function account_credential_delivery_expiry_text(
        int $ttlSeconds
    ): string {
        if ($ttlSeconds === 3600) {
            return '1 hour';
        }

        if ($ttlSeconds === 86400) {
            return '24 hours';
        }

        $minutes = max(
            1,
            (int)ceil($ttlSeconds / 60)
        );

        return $minutes . ' minutes';
    }
}

if (!function_exists('account_credential_delivery_message')) {
    function account_credential_delivery_message(
        string $purpose,
        array $account,
        string $link,
        int $ttlSeconds
    ): array {
        $purpose =
            account_credential_normalize_purpose(
                $purpose
            );

        $username =
            account_credential_delivery_text(
                $account['username'] ?? '',
                120
            );

        if ($username === '') {
            throw new InvalidArgumentException(
                'Account username is required for credential delivery.'
            );
        }

        $fullName =
            account_credential_delivery_text(
                $account['full_name'] ?? '',
                160
            );

        $farmName =
            account_credential_delivery_text(
                $account['farm_name'] ?? '',
                180
            );

        $workspaceId =
            account_credential_delivery_text(
                $account['workspace_id'] ?? '',
                120
            );

        $userType =
            strtolower(
                account_credential_delivery_text(
                    $account['user_type'] ?? '',
                    80
                )
            );

        $greeting =
            $fullName !== ''
                ? 'Hello ' . $fullName . ','
                : 'Hello,';

        $workspaceLines = '';

        /*
         * The owner workspace is internal platform identity and is not
         * presented as a tenant workspace in credential mail.
         */
        if (
            $workspaceId !== ''
            && $workspaceId !== 'owner'
            && !in_array(
                $userType,
                ['platform_owner', 'platform_admin'],
                true
            )
        ) {
            if ($farmName !== '') {
                $workspaceLines .=
                    'Farm: ' . $farmName . "\n";
            }

            $workspaceLines .=
                'Farm Workspace ID: '
                . $workspaceId
                . "\n";
        }

        $expiresText =
            account_credential_delivery_expiry_text(
                $ttlSeconds
            );

        if ($purpose === 'activation') {
            $subject =
                'Activate your Renee Farms account';

            $body =
                $greeting . "\n\n"
                . "Your Renee Farms account is ready.\n\n"
                . $workspaceLines
                . 'Username: ' . $username . "\n\n"
                . "Create your password and activate your account:\n"
                . $link . "\n\n"
                . 'This activation link expires in '
                . $expiresText
                . ".\n"
                . "If you were not expecting this account, you can ignore this message.\n\n"
                . "Renee Farms Platform\n";

            return [
                'subject' => $subject,
                'body' => $body,
            ];
        }

        $subject =
            'Reset your Renee Farms password';

        $body =
            $greeting . "\n\n"
            . "A password reset was requested for your Renee Farms account.\n\n"
            . $workspaceLines
            . 'Username: ' . $username . "\n\n"
            . "Choose a new password:\n"
            . $link . "\n\n"
            . 'This password reset link expires in '
            . $expiresText
            . ".\n"
            . "If you did not request this change, you can ignore this message. "
            . "Your existing password remains unchanged.\n\n"
            . "Renee Farms Platform\n";

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }
}

if (!function_exists('account_credential_send')) {
    function account_credential_send(
        PDO $pdo,
        int $userId,
        string $purpose
    ): array {
        $purpose =
            account_credential_normalize_purpose(
                $purpose
            );

        /*
         * Load message context before issuing a token so obvious context
         * failures do not create an otherwise valid credential link.
         */
        $account =
            account_credential_delivery_context(
                $pdo,
                $userId
            );

        $ttlSeconds =
            account_credential_delivery_ttl_seconds(
                $purpose
            );

        /*
         * Lifecycle service owns state validation, row locking,
         * same-purpose supersession, secure token generation and storage.
         */
        $issued =
            account_credential_issue_token(
                $pdo,
                $userId,
                $purpose,
                $ttlSeconds
            );

        $rawToken =
            (string)($issued['token'] ?? '');

        if ($rawToken === '') {
            throw new RuntimeException(
                'Credential token issuance did not return a delivery secret.'
            );
        }

        $link =
            account_credential_delivery_link(
                $purpose,
                $rawToken
            );

        $message =
            account_credential_delivery_message(
                $purpose,
                $account,
                $link,
                $ttlSeconds
            );

        $mail =
            platform_mail_send(
                (string)$issued['email'],
                (string)$message['subject'],
                (string)$message['body']
            );

        /*
         * Never expose the raw token or credential link to route/caller
         * return values. The secret exists only transiently for delivery.
         */
        return [
            'user_id' => $userId,
            'purpose' => $purpose,
            'email' => (string)$issued['email'],
            'expires_in' => $ttlSeconds,
            'sent' => ($mail['sent'] ?? false) === true,
            'transport' =>
                (string)($mail['transport'] ?? ''),
            'reason' =>
                $mail['reason'] ?? null,
        ];
    }
}

if (!function_exists('account_credential_send_activation')) {
    function account_credential_send_activation(
        PDO $pdo,
        int $userId
    ): array {
        return account_credential_send(
            $pdo,
            $userId,
            'activation'
        );
    }
}

if (!function_exists('account_credential_send_password_reset')) {
    function account_credential_send_password_reset(
        PDO $pdo,
        int $userId
    ): array {
        return account_credential_send(
            $pdo,
            $userId,
            'password_reset'
        );
    }
}
