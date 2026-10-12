<?php
declare(strict_types=1);

/**
 * B12: dormant, pure response-format policy for a FUTURE Daily Record API.
 * NOT imported by config.php, init.php, subscription runtime or live routes.
 * No account queries, session mutations, redirects, headers or output here.
 *
 * The canonical auth/subscription services MUST make the decision, and finish
 * their existing security/session/recovery side effects, before the eventual
 * integration uses this policy to choose browser redirect vs JSON response.
 * Never infer API identity from Accept headers, query parameters or user input.
 */

if (!function_exists('b12_auth_response_is_json_route')) {
    function b12_auth_response_is_json_route(string $scriptPath): bool
    {
        if ($scriptPath === '' || !str_starts_with($scriptPath, '/') ||
            str_contains($scriptPath, '\\') || str_contains($scriptPath, "\0") ||
            str_contains($scriptPath, '?') || str_contains($scriptPath, '#') ||
            str_contains($scriptPath, '//')) {
            return false;
        }
        $parts = explode('/', substr($scriptPath, 1));
        if (count($parts) < 2 ||
            array_slice($parts, -2) !== ['api', 'daily_record_revision_read.php']) {
            return false;
        }
        foreach (array_slice($parts, 0, -2) as $part) {
            if ($part === '' || $part === '.' || $part === '..' ||
                preg_match('/^[a-zA-Z0-9_-]+$/D', $part) !== 1) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('b12_auth_response_event_policy')) {
    /**
     * Events label decisions already reached by CANONICAL validators:
     * - session_expired: config.php SESSION_TIMEOUT, identity cleared first
     * - invalid_account: requireLogin() account/farm/suspension check, cleared
     * - farm_invalid: requireCurrentFarmId(), identity cleared first
     * - subscription_recovery: canonical lifecycle first starts recovery mode
     * - subscription_expired: canonical lifecycle first clears normal identity
     * - entitlement_denied: canonical module/subscription permission boundary
     * - permission_denied: canonical granular permission boundary
     *
     * @return array{status:int,code:string,message:string,html_path:?string,html_status:int}
     */
    function b12_auth_response_event_policy(string $event): array
    {
        return match ($event) {
            'unauthenticated' => [
                'status' => 401, 'code' => 'authentication_required',
                'message' => 'Please sign in.', 'html_path' => '/login.php', 'html_status' => 302,
            ],
            'session_expired' => [
                'status' => 401, 'code' => 'session_expired',
                'message' => 'Your session has expired. Please sign in again.',
                'html_path' => '/login.php?timeout=1', 'html_status' => 302,
            ],
            'invalid_account' => [
                'status' => 401, 'code' => 'authentication_required',
                'message' => 'Please sign in.', 'html_path' => '/login.php', 'html_status' => 302,
            ],
            'farm_invalid' => [
                'status' => 401, 'code' => 'authentication_required',
                'message' => 'Please sign in.', 'html_path' => '/sign.php', 'html_status' => 302,
            ],
            'subscription_recovery' => [
                'status' => 403, 'code' => 'subscription_recovery_required',
                'message' => 'Subscription recovery is required.',
                'html_path' => '/billing/recover.php', 'html_status' => 303,
            ],
            'subscription_expired' => [
                'status' => 403, 'code' => 'subscription_access_required',
                'message' => 'Subscription access is required.',
                'html_path' => '/sign.php', 'html_status' => 303,
            ],
            'entitlement_denied', 'permission_denied' => [
                'status' => 403, 'code' => 'forbidden',
                'message' => 'Access denied.', 'html_path' => '/no_access.php', 'html_status' => 302,
            ],
            default => throw new InvalidArgumentException('Unsupported authentication response event.'),
        };
    }
}

if (!function_exists('b12_auth_response_plan')) {
    /**
     * @return array<string,mixed> One sanitized transport plan, no side effects.
     * HTML remains redirect for normal GET paths; entitlement denial on a
     * non-GET request preserves the existing 403/no-redirect posture.
     */
    function b12_auth_response_plan(string $scriptPath, string $event, string $method = 'GET'): array
    {
        if ($method === '' || preg_match('/^[A-Z]+$/D', $method) !== 1) {
            throw new InvalidArgumentException('Invalid HTTP method.');
        }
        $policy = b12_auth_response_event_policy($event);
        if (b12_auth_response_is_json_route($scriptPath)) {
            return [
                'transport' => 'json',
                'http_status' => $policy['status'],
                'body' => [
                    'success' => false,
                    'code' => $policy['code'],
                    'error' => $policy['message'],
                ],
            ];
        }
        if (in_array($event, ['entitlement_denied', 'permission_denied'], true)
            && $method !== 'GET') {
            return [
                'transport' => 'html_forbidden',
                'http_status' => 403,
                'body' => null,
            ];
        }
        return [
            'transport' => 'redirect',
            'http_status' => $policy['html_status'],
            'redirect_path' => $policy['html_path'],
        ];
    }
}
