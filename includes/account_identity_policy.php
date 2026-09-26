<?php

declare(strict_types=1);

/**
 * Shared account identity/profile storage policy.
 *
 * Responsibilities:
 * - normalize required account username;
 * - normalize required account full name;
 * - enforce the users-table character limits centrally.
 *
 * Non-responsibilities:
 * - credential email;
 * - password policy;
 * - credential lifecycle state;
 * - account type / role authorization;
 * - tenant role assignment;
 * - transaction ownership.
 */

const ACCOUNT_IDENTITY_USERNAME_MAX = 100;
const ACCOUNT_IDENTITY_FULL_NAME_MAX = 150;

if (!function_exists('account_identity_character_length')) {
    function account_identity_character_length(
        string $value
    ): int {
        if (function_exists('mb_strlen')) {
            return mb_strlen(
                $value,
                'UTF-8'
            );
        }

        return strlen($value);
    }
}

if (!function_exists('account_identity_normalize_username')) {
    function account_identity_normalize_username(
        string $username
    ): string {
        $username = trim($username);

        if ($username === '') {
            throw new InvalidArgumentException(
                'Enter a username.'
            );
        }

        if (
            account_identity_character_length($username)
            > ACCOUNT_IDENTITY_USERNAME_MAX
        ) {
            throw new InvalidArgumentException(
                'Username must be '
                . ACCOUNT_IDENTITY_USERNAME_MAX
                . ' characters or fewer.'
            );
        }

        return $username;
    }
}

if (!function_exists('account_identity_normalize_full_name')) {
    function account_identity_normalize_full_name(
        string $fullName
    ): string {
        $fullName = trim($fullName);

        if ($fullName === '') {
            throw new InvalidArgumentException(
                'Enter the account holder name.'
            );
        }

        if (
            account_identity_character_length($fullName)
            > ACCOUNT_IDENTITY_FULL_NAME_MAX
        ) {
            throw new InvalidArgumentException(
                'Account holder name must be '
                . ACCOUNT_IDENTITY_FULL_NAME_MAX
                . ' characters or fewer.'
            );
        }

        return $fullName;
    }
}
