<?php

if (!function_exists('app_csp_report_only_policy')) {
    function app_csp_report_only_policy(): string
    {
        return "default-src 'self'; "
            . "base-uri 'self'; "
            . "object-src 'none'; "
            . "script-src 'self'; "
            . "style-src 'self'; "
            . "font-src 'self'; "
            . "img-src 'self' data:; "
            . "connect-src 'self'; "
            . "frame-src 'none'; "
            . "frame-ancestors 'self'; "
            . "form-action 'self'; "
            . "report-uri /csp-report.php";
    }
}

if (!function_exists('app_emit_csp_report_only_header')) {
    function app_emit_csp_report_only_header(): void
    {
        if (!headers_sent()) {
            header(
                'Content-Security-Policy-Report-Only: '
                . app_csp_report_only_policy()
            );
        }
    }
}
