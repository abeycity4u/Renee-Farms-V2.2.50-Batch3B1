<?php

$root = dirname(__DIR__);

$configPath = $root . '/config.php';
$policyPath = $root . '/includes/csp_policy.php';
$indexPath = $root . '/index.php';
$reportPath = $root . '/csp-report.php';

$files = [
    'config.php' => $configPath,
    'includes/csp_policy.php' => $policyPath,
    'index.php' => $indexPath,
    'csp-report.php' => $reportPath,
];

$sources = [];

foreach ($files as $name => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "FAIL: {$name} not found\n");
        exit(1);
    }

    $source = file_get_contents($path);

    if ($source === false) {
        fwrite(STDERR, "FAIL: unable to read {$name}\n");
        exit(1);
    }

    $sources[$name] = $source;
}

$config = $sources['config.php'];
$policy = $sources['includes/csp_policy.php'];
$index = $sources['index.php'];
$report = $sources['csp-report.php'];

$combined = implode("\n", $sources);

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
    substr_count(
        $policy,
        'Content-Security-Policy-Report-Only:'
    ) === 1,
    'central policy defines exactly one CSP Report-Only header'
);

$check(
    preg_match(
        '/["\']Content-Security-Policy:\s*/i',
        $combined
    ) !== 1,
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
        strpos($policy, $directive) !== false,
        "policy contains {$directive}"
    );
}

$check(
    strpos($policy, 'report-uri /csp-report.php') !== false,
    'Report-Only policy sends violations to same-origin CSP collector'
);

$check(
    strpos($report, "REQUEST_METHOD'] !== 'POST'") !== false,
    'CSP collector accepts POST reports only'
);

$check(
    strpos($report, "php://input") !== false,
    'CSP collector reads the browser report body'
);

$check(
    strpos($report, "[CSP_REPORT]") !== false,
    'CSP collector records normalized violations'
);

$check(
    strpos($combined, "'unsafe-inline'") === false,
    'policy does not allow unsafe-inline'
);

$check(
    strpos($combined, "'unsafe-eval'") === false,
    'policy does not allow unsafe-eval'
);

$check(
    strpos(
        $config,
        "require_once __DIR__ . '/includes/csp_policy.php';"
    ) !== false,
    'config.php loads centralized CSP policy'
);

$check(
    substr_count(
        $config,
        'app_emit_csp_report_only_header();'
    ) === 1,
    'config.php emits centralized Report-Only policy exactly once'
);

$check(
    strpos(
        $index,
        "require_once __DIR__ . '/includes/csp_policy.php';"
    ) !== false,
    'public homepage loads centralized CSP policy'
);

$check(
    substr_count(
        $index,
        'app_emit_csp_report_only_header();'
    ) === 1,
    'public homepage emits Report-Only policy exactly once'
);

$check(
    strpos($index, 'versioned_asset(') === false,
    'standalone homepage does not depend on init.php asset helper'
);

$check(
    strpos($index, 'BASE_URL') === false,
    'standalone homepage does not depend on BASE_URL'
);

if ($failures) {
    echo "\nCSP REPORT-ONLY CONTRACT FAILED: "
        . count($failures)
        . " failure(s)\n";
    exit(1);
}

echo "\nCSP REPORT-ONLY CONTRACT PASSED\n";
