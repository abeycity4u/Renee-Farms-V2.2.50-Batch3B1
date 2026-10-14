<?php
declare(strict_types=1);

/**
 * Single B12 revision-aware AJAX transport and JSON envelope owner.
 * Headers distinguish the supported browser protocol; they do NOT confer
 * authentication, CSRF, tenancy, permission or revision authority.
 * Source only; importing this module performs no I/O or response.
 */
if (!function_exists('daily_record_revision_http_is_ajax')) {
    function daily_record_revision_http_is_ajax(array $server): bool
    {
        $method = strtoupper((string)($server['REQUEST_METHOD'] ?? ''));
        $requestedWith = (string)($server['HTTP_X_REQUESTED_WITH'] ?? '');
        $accept = (string)($server['HTTP_ACCEPT'] ?? '');
        return $method === 'POST'
            && strcasecmp($requestedWith, 'XMLHttpRequest') === 0
            && preg_match('~(?:^|,)\s*application/json\s*(?:;[^,]*)?(?:,|$)~i', $accept) === 1;
    }
}
if (!function_exists('daily_record_revision_http_send_json')) {
    function daily_record_revision_http_send_json(int $status, array $body): never
    {
        if ($status < 200 || $status > 599) {
            throw new InvalidArgumentException('Invalid revision HTTP response status.');
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($body, JSON_THROW_ON_ERROR);
        exit;
    }
}
