<?php
/**
 * Central password-security policy for Renee Farms authentication and account management.
 *
 * Keep the minimum requirement and hashing/verification primitives in one place so
 * Farm Admin and Team User account flows do not drift apart during commercial hardening.
 */

if (!function_exists('password_security_min_length')) {
    function password_security_min_length(): int
    {
        return 8;
    }
}

if (!function_exists('password_security_validate')) {
    function password_security_validate(string $password): ?string
    {
        if (strlen($password) < password_security_min_length()) {
            return 'Password must be at least ' . password_security_min_length() . ' characters.';
        }
        return null;
    }
}

if (!function_exists('password_security_hash')) {
    function password_security_hash(string $password): string
    {
        $error = password_security_validate($password);
        if ($error !== null) throw new InvalidArgumentException($error);
        return password_hash($password, PASSWORD_DEFAULT);
    }
}

if (!function_exists('password_security_verify')) {
    /** Hash-only verification. Plaintext compatibility is intentionally excluded. */
    function password_security_verify(string $password, string $storedHash): bool
    {
        if ($password === '' || $storedHash === '') return false;
        if (password_get_info($storedHash)['algo'] === 0) return false;
        return password_verify($password, $storedHash);
    }
}
