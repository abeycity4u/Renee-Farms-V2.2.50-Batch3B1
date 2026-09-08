<?php
/**
 * V2.3 read-only request session-lock release helper.
 *
 * PHP's default file-backed session handler holds an exclusive lock for the
 * lifetime of a request. Read-only AJAX/API requests do not need to keep that
 * lock once authentication/session context has been resolved. Closing the
 * session early preserves the in-memory $_SESSION values for reads while
 * allowing concurrent requests from the same browser session to proceed.
 */

if (!function_exists('release_readonly_session_lock')) {
    function release_readonly_session_lock(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        session_write_close();
        return session_status() !== PHP_SESSION_ACTIVE;
    }
}
