<?php
declare(strict_types=1);

/**
 * B12 canonical authentication decision extraction — DORMANT / SOURCE ONLY.
 * Pure classification of values already read by the established config.php
 * bootstrap and requireLogin() query. No DB, session, headers or side effects.
 * Do not wire until account/session and subscription transports are reviewed.
 */
if (!function_exists('b12_auth_core_session_timeout_event')) {
    function b12_auth_core_session_timeout_event(
        array $session,
        int $now,
        int $timeoutSeconds
    ): ?string {
        if ($timeoutSeconds < 1) {
            throw new InvalidArgumentException('Invalid session timeout policy.');
        }
        // Match config.php: missing LAST_ACTIVITY or user_id does not expire.
        if (!isset($session['user_id'], $session['LAST_ACTIVITY'])) {
            return null;
        }
        // The future caller provides the original value only after its normal
        // session verification; no client-submitted time is accepted here.
        $last = $session['LAST_ACTIVITY'];
        if (!is_int($last) && !(is_string($last) && preg_match('/^[0-9]+$/D', $last) === 1)) {
            throw new InvalidArgumentException('Invalid server session timestamp.');
        }
        if ($now < 0 || (int)$last > $now) {
            throw new InvalidArgumentException('Invalid server session clock.');
        }
        return ($now - (int)$last > $timeoutSeconds) ? 'session_expired' : null;
    }
}

if (!function_exists('b12_auth_core_farm_event')) {
    function b12_auth_core_farm_event(int $canonicalFarmId): ?string {
        return $canonicalFarmId < 1 ? 'farm_invalid' : null;
    }
}

if (!function_exists('b12_auth_core_account_outcome')) {
    /**
     * Consumes the exact requireLogin() SELECT result (array|false).
     * The caller must perform existing session cleanup / fresh role updates.
     * Status is NOT derived from client input; it comes from the DB row.
     * @return array{event:?string,user_type:?string,full_name:?string}
     */
    function b12_auth_core_account_outcome(array|false $account): array {
        if ($account === false) {
            return ['event'=>'invalid_account', 'user_type'=>null, 'full_name'=>null];
        }
        foreach (['user_type','full_name','subscription_status','is_platform_owner'] as $key) {
            if (!array_key_exists($key, $account)) {
                throw new InvalidArgumentException('Incomplete canonical account lookup.');
            }
        }
        $status = $account['subscription_status'];
        $owner = $account['is_platform_owner'];
        if (!is_string($status) || !in_array((string)$owner, ['0','1'], true) ||
            !is_string($account['user_type']) || !is_string($account['full_name'])) {
            throw new InvalidArgumentException('Invalid canonical account lookup.');
        }
        // Preserve requireLogin() behavior: owner bypass and exact status
        // comparison. Past-due is owned by subscription_lifecycle_request_guard.
        $deny = (string)$owner !== '1' && in_array($status, ['suspended','cancelled'], true);
        return [
            'event' => $deny ? 'invalid_account' : null,
            'user_type' => $account['user_type'],
            'full_name' => $account['full_name'],
        ];
    }
}
