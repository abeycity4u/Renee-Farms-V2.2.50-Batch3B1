<?php
/**
 * Central CSRF helpers for browser-based state-changing requests.
 *
 * Low-level token generation/verification remains in config.php so legacy
 * callers continue to work. New and migrated form/API endpoints should use
 * these helpers so token extraction and verification stay consistent.
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

if (!function_exists('csrf_request_token')) {
    function csrf_request_token(): string
    {
        return (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''));
    }
}

if (!function_exists('csrf_request_is_valid')) {
    function csrf_request_is_valid(): bool
    {
        return function_exists('verify_csrf_token') && verify_csrf_token(csrf_request_token());
    }
}

if (!function_exists('require_valid_csrf_post')) {
    function require_valid_csrf_post(): void
    {
        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            http_response_code(405);
            exit('Method not allowed.');
        }

        if (!csrf_request_is_valid()) {
            http_response_code(419);
            exit('Invalid request token.');
        }
    }
}
