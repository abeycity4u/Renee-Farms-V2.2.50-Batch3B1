<?php
declare(strict_types=1);

/**
 * B12 ONLY: represent an already-decided early request rejection as JSON.
 *
 * Does NOT grant access, validate CSRF, change session/tenant state, query
 * SQL, or choose a permission. Calling code retains its original deny and
 * cleanup first; this changes only the response for three exact Save routes
 * after the separately controlled seven-writer gate is enabled.
 *
 * The limited route matcher is intentionally independent of BASE_URL and
 * of HTML form/client headers. A native B12 SAVE remains non-authorized.
 */
require_once __DIR__ . '/daily_record_revision_cutover_gate.php';
require_once __DIR__ . '/daily_record_revision_http_transport.php';

if (!function_exists('daily_record_revision_preroute_is_save')) {
    function daily_record_revision_preroute_is_save(
        array $server, array $post
    ): bool {
        if (!daily_record_revision_cutover_enabled()
            || strtoupper((string)($server['REQUEST_METHOD'] ?? '')) !== 'POST'
            || !isset($post['save_record'])) {
            return false;
        }
        $path = str_replace('\\', '/', (string)($server['SCRIPT_NAME'] ?? ''));
        return preg_match(
            '~(?:^|/)(?:poultry/(?:layers|broiler)_daily_record'
            . '|ruminant/ruminant_daily_record)\.php$~D',
            $path
        ) === 1;
    }
}

if (!function_exists('daily_record_revision_preroute_failure_envelope')) {
    /**
     * Fixed allowlist: caller-supplied exception/session/SQL messages
     * must never appear in JSON. This function has no request side effects.
     *
     * @return array{status:int,body:array<string,string|bool>}
     */
    function daily_record_revision_preroute_failure_envelope(
        string $reason
    ): array {
        $map = [
            'authentication_required' => [
                401, 'authentication_required',
                'Your session is no longer active. Sign in again before saving.',
            ],
            'forbidden' => [
                403, 'forbidden',
                'You do not have permission to save this Daily Record.',
            ],
            'subscription_access_required' => [
                403, 'subscription_access_required',
                'The farm subscription does not permit this action.',
            ],
            'record_conflict' => [
                409, 'record_conflict',
                'The selected Daily Record changed. Keep your draft and reload the record.',
            ],
            'storage_unavailable' => [
                503, 'storage_unavailable',
                'Saving is temporarily unavailable. Keep your draft.',
            ],
        ];
        if (!array_key_exists($reason, $map)) {
            throw new InvalidArgumentException('Unknown B12 pre-route failure reason.');
        }
        [$status, $code, $message] = $map[$reason];
        return ['status' => $status, 'body' => [
            'success' => false, 'code' => $code, 'error' => $message,
        ]];
    }
}

if (!function_exists('daily_record_revision_preroute_maybe_send')) {
    /**
     * The caller must have made its own denial decision first. Returns
     * without effect for gate-OFF, other routes, GET, and DELETE.
     */
    function daily_record_revision_preroute_maybe_send(
        string $reason, array $server, array $post
    ): void {
        if (!daily_record_revision_preroute_is_save($server, $post)) {
            return;
        }
        $result = daily_record_revision_preroute_failure_envelope($reason);
        daily_record_revision_http_send_json($result['status'], $result['body']);
    }
}
