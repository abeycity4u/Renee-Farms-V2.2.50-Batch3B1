<?php

$root = dirname(__DIR__);
$configPath = $root . '/config.php';

if (!is_file($configPath)) {
    fwrite(STDERR, "FAIL: config.php not found\n");
    exit(1);
}

$source = file_get_contents($configPath);
if ($source === false) {
    fwrite(STDERR, "FAIL: unable to read config.php\n");
    exit(1);
}

$failures = [];

$check = static function (bool $ok, string $message) use (&$failures): void {
    if ($ok) {
        echo "PASS: {$message}\n";
        return;
    }

    echo "FAIL: {$message}\n";
    $failures[] = $message;
};

$check(
    substr_count($source, 'Content-Security-Policy-Report-Only:') === 1,
    'exactly one CSP Report-Only header is defined'
);

$check(
    preg_match('/["\']Content-Security-Policy:\s*/i', $source) !== 1,
    'no enforcing Content-Security-Policy header is defined'
);

$requiredDirectives = [
    "default-src 'self'",
    "base-uri 'self'",
    "object-src 'none'",
    "script-src 'self'",
    "style-src 'self'",
    "font-src 'self'",
    "img-src 'self' data:",
    "connect-src 'self'",
    "frame-src 'none'",
    "frame-ancestors 'self'",
    "form-action 'self'",
];

foreach ($requiredDirectives as $directive) {
    $check(
        str_contains($source, $directive),
        "policy contains {$directive}"
    );
}

$check(
    !str_contains($source, "'unsafe-inline'"),
    "policy does not allow unsafe-inline"
);

$check(
    !str_contains($source, "'unsafe-eval'"),
    "policy does not allow unsafe-eval"
);

$check(
    str_contains($source, "if (!headers_sent())"),
    'security headers remain guarded by headers_sent()'
);

if ($failures) {
    echo "\nCSP REPORT-ONLY CONTRACT FAILED: "
        . count($failures)
        . " failure(s)\n";
    exit(1);
}

echo "\nCSP REPORT-ONLY CONTRACT PASSED\n";
