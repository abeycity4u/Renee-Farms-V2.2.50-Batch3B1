<?php
/**
 * Same-origin CSP Report-Only collector.
 *
 * Receives browser violation reports without starting an application session
 * or touching tenant/business data. Reports are normalized before entering
 * the PHP/web-server error log.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}

header('Cache-Control: no-store');

$maxBytes = 32768;
$contentLength = isset($_SERVER['CONTENT_LENGTH'])
    ? (int)$_SERVER['CONTENT_LENGTH']
    : 0;

if ($contentLength > $maxBytes) {
    http_response_code(413);
    exit;
}

$raw = file_get_contents('php://input', false, null, 0, $maxBytes + 1);

if ($raw === false || strlen($raw) > $maxBytes) {
    http_response_code(413);
    exit;
}

$payload = json_decode($raw, true);

if (!is_array($payload)) {
    http_response_code(400);
    exit;
}

$report = isset($payload['csp-report']) && is_array($payload['csp-report'])
    ? $payload['csp-report']
    : $payload;

$cleanText = static function ($value, $maxLength = 500) {
    $value = str_replace(
        ["\r", "\n", "\t"],
        ' ',
        trim((string)$value)
    );

    return substr($value, 0, $maxLength);
};

$cleanUrl = static function ($value) use ($cleanText) {
    $value = $cleanText($value, 1000);

    if ($value === '') {
        return '';
    }

    $parts = parse_url($value);

    if (!is_array($parts)) {
        return $cleanText($value, 500);
    }

    $scheme = strtolower((string)($parts['scheme'] ?? ''));

    if (in_array($scheme, ['data', 'blob'], true)) {
        return $scheme . ':';
    }

    if ($scheme !== 'http' && $scheme !== 'https') {
        return $cleanText($value, 500);
    }

    $host = strtolower((string)($parts['host'] ?? ''));
    $path = (string)($parts['path'] ?? '');

    if ($host === '') {
        return $cleanText($path, 500);
    }

    return $cleanText($scheme . '://' . $host . $path, 500);
};

$documentUri = $cleanUrl($report['document-uri'] ?? '');

$requestHost = strtolower(
    preg_replace(
        '/:\d+$/',
        '',
        (string)($_SERVER['HTTP_HOST'] ?? '')
    )
);

$documentHost = strtolower(
    (string)(parse_url($documentUri, PHP_URL_HOST) ?: '')
);

/*
 * Ignore reports claiming another document origin. This does not authenticate
 * a public report endpoint, but prevents accidental cross-origin log noise.
 */
if (
    $requestHost !== ''
    && $documentHost !== ''
    && $requestHost !== $documentHost
) {
    http_response_code(204);
    exit;
}

$normalized = [
    'document_uri' => $documentUri,
    'effective_directive' => $cleanText(
        $report['effective-directive']
            ?? $report['violated-directive']
            ?? '',
        160
    ),
    'blocked_uri' => $cleanUrl($report['blocked-uri'] ?? ''),
    'source_file' => $cleanUrl($report['source-file'] ?? ''),
    'line_number' => isset($report['line-number'])
        ? (int)$report['line-number']
        : null,
    'column_number' => isset($report['column-number'])
        ? (int)$report['column-number']
        : null,
    'status_code' => isset($report['status-code'])
        ? (int)$report['status-code']
        : null,
    'disposition' => $cleanText(
        $report['disposition'] ?? 'report',
        32
    ),
];

if (
    $normalized['document_uri'] !== ''
    || $normalized['effective_directive'] !== ''
) {
    $encoded = json_encode(
        $normalized,
        JSON_UNESCAPED_SLASHES
    );

    if ($encoded !== false) {
        error_log('[CSP_REPORT] ' . $encoded);
    }
}

http_response_code(204);
