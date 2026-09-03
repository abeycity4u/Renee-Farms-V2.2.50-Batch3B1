<?php
/**
 * Central CSRF helpers for browser-based state-changing requests.
 *
 * Low-level token generation/verification remains in config.php so legacy
 * callers continue to work. New and migrated form endpoints should use these
 * helpers to keep rendering and enforcement consistent.
 */

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        $token = function_exists('csrf_token') ? csrf_token() : '';
        return '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
            . '">';
    }
}

if (!function_exists('require_valid_csrf_post')) {
    function require_valid_csrf_post(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            exit('Method not allowed.');
        }

        $token = $_POST['csrf_token'] ?? '';
        if (!function_exists('verify_csrf_token') || !verify_csrf_token($token)) {
            http_response_code(419);
            exit('Invalid request token.');
        }
    }
}
