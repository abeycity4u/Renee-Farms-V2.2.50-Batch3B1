<?php
declare(strict_types=1);

/**
 * B12 source-only invalid-auth-state policy; never used by live bootstrap.
 * Classifies trusted SERVER session/DB projection errors, not client input.
 * Does not destroy sessions, grant access, emit headers, or issue SQL.
 */
if (!function_exists('b12_auth_malformed_state_event')) {
    function b12_auth_malformed_state_event(string $source, mixed $value, ?int $now = null): ?string
    {
        if ($source === 'last_activity') {
            if (!is_int($now) || $now < 0) {
                throw new InvalidArgumentException('Missing trusted server clock.');
            }
            if (!is_int($value) && !(is_string($value) && preg_match('/^(0|[1-9][0-9]*)$/D', $value) === 1)) {
                return 'invalid_account';
            }
            // Avoid overflow and future-timestamp bypass: compare decimal
            // representation before converting to a native signed integer.
            $text = (string)$value;
            $max = (string)PHP_INT_MAX;
            if (strlen($text) > strlen($max) || (strlen($text) === strlen($max) && strcmp($text, $max) > 0)) {
                return 'invalid_account';
            }
            $last = (int)$text;
            return ($last < 0 || $last > $now) ? 'invalid_account' : null;
        }
        if ($source === 'account_projection') {
            if ($value === false) return 'invalid_account';
            if (!is_array($value)) return 'invalid_account';
            foreach (['user_type','full_name','subscription_status','is_platform_owner'] as $key) {
                if (!array_key_exists($key, $value)) return 'invalid_account';
            }
            if (!is_string($value['user_type']) || $value['user_type'] === '' ||
                !is_string($value['full_name']) ||
                !is_string($value['subscription_status']) ||
                !in_array($value['is_platform_owner'], [0,1,'0','1'], true)) {
                return 'invalid_account';
            }
            return null;
        }
        throw new InvalidArgumentException('Unknown canonical state origin.');
    }
}
