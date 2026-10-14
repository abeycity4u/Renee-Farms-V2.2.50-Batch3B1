<?php
declare(strict_types=1);

/**
 * Source-only, hard-gated SAVE browser protocol. No SQL/CSRF/permission logic.
 * All three page routes use the same pre-dispatch error/transport policy;
 * unchanged native legacy redirects are retained while the fuse stays OFF.
 */
require_once __DIR__ . '/daily_record_revision_cutover_gate.php';
require_once __DIR__ . '/daily_record_revision_http_transport.php';

if (!function_exists('daily_record_revision_save_http_error')) {
    /** JSON fields are intentionally sanitized through caller-owned messages. */
    function daily_record_revision_save_http_error(
        int $status, string $code, string $message
    ): never {
        daily_record_revision_http_send_json($status, [
            'success' => false, 'code' => $code, 'error' => $message
        ]);
    }
}

if (!function_exists('daily_record_revision_save_http_require_ajax')) {
    function daily_record_revision_save_http_require_ajax(array $server): void
    {
        if (!daily_record_revision_cutover_enabled()) {
            // Existing native legacy SAVE must never be rejected as AJAX.
            return;
        }
        if (!daily_record_revision_http_is_ajax($server)) {
            daily_record_revision_save_http_error(
                409, 'revision_client_required',
                'Reload the Daily Record page before saving.'
            );
        }
    }
}

if (!function_exists('daily_record_revision_save_http_csrf_failure')) {
    function daily_record_revision_save_http_csrf_failure(): never
    {
        if (daily_record_revision_cutover_enabled()) {
            daily_record_revision_save_http_error(
                419, 'invalid_csrf', 'Invalid request token. Reload and try again.'
            );
        }
        // Preserve historical OFF response (text/plain-ish 419, no redirect).
        http_response_code(419);
        exit('Invalid request token.');
    }
}

if (!function_exists('daily_record_revision_save_http_validation_failure')) {
    function daily_record_revision_save_http_validation_failure(
        string $message, string $legacyRedirect
    ): never {
        if (daily_record_revision_cutover_enabled()) {
            daily_record_revision_save_http_error(
                422, 'invalid_record', $message
            );
        }
        // Only legacy OFF follows a location header; maintain old session UX.
        $_SESSION['error'] = $message;
        header('Location: ' . $legacyRedirect);
        exit;
    }
}
