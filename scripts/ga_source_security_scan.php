<?php
/**
 * Renee AgriSuite GA source-security scanner.
 *
 * Repository-only and non-destructive. It intentionally distinguishes
 * high-confidence blockers from review-only findings so CI does not pretend
 * a regex is a penetration test.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$blockers = [];
$review = [];
$scanned = 0;

$skipPrefixes = [
    $root . '/vendor/',
    $root . '/.git/',
    $root . '/logs/',
    $root . '/uploads/',
];

$extensions = ['php', 'js', 'json', 'yml', 'yaml', 'xml', 'ini', 'conf'];

$highConfidence = [
    'php_eval' => '/\beval\s*\(/i',
    'tls_peer_disabled' => '/CURLOPT_SSL_VERIFYPEER\s*,\s*false/i',
    'tls_host_disabled' => '/CURLOPT_SSL_VERIFYHOST\s*,\s*0\b/i',
    'sendgrid_live_key_literal' => '/\bSG\.[A-Za-z0-9_-]{16,}\.[A-Za-z0-9_-]{16,}\b/',
    'paystack_live_key_literal' => '/\bsk_live_[A-Za-z0-9_-]{12,}\b/i',
    'flutterwave_live_key_literal' => '/\bFLWSECK-[A-Za-z0-9_-]{12,}\b/i',
    'private_key_literal' => '/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/',
];

$reviewPatterns = [
    'shell_exec' => '/\bshell_exec\s*\(/i',
    'exec' => '/(?<!->)(?<!::)\bexec\s*\(/i',
    'system' => '/(?<!->)(?<!::)\bsystem\s*\(/i',
    'passthru' => '/\bpassthru\s*\(/i',
    'proc_open' => '/\bproc_open\s*\(/i',
    'popen' => '/\bpopen\s*\(/i',
    'unserialize' => '/\bunserialize\s*\(/i',
    'assert_string' => '/\bassert\s*\(\s*[\'\"]/i',
];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(
        $root,
        FilesystemIterator::SKIP_DOTS
    )
);

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile()) continue;

    $path = str_replace('\\', '/', $fileInfo->getPathname());
    $skip = false;
    foreach ($skipPrefixes as $prefix) {
        if (str_starts_with($path, str_replace('\\', '/', $prefix))) {
            $skip = true;
            break;
        }
    }
    if ($skip) continue;

    $extension = strtolower($fileInfo->getExtension());
    if (!in_array($extension, $extensions, true)) continue;

    $relative = ltrim(str_replace(str_replace('\\', '/', $root), '', $path), '/');

    // The scanner contains the signatures it searches for; do not self-report.
    if ($relative === 'scripts/ga_source_security_scan.php') continue;

    $content = file_get_contents($fileInfo->getPathname());
    if ($content === false) {
        $blockers[] = $relative . ': unreadable source file';
        continue;
    }

    $scanned++;

    foreach ($highConfidence as $label => $pattern) {
        if (preg_match($pattern, $content) === 1) {
            $blockers[] = $relative . ': ' . $label;
        }
    }

    foreach ($reviewPatterns as $label => $pattern) {
        if (preg_match($pattern, $content) === 1) {
            $review[] = $relative . ': ' . $label;
        }
    }
}

// Contracts for known high-value boundaries that regex scanning alone cannot infer.
$pdfPath = $root . '/includes/pdf/PdfReportService.php';
$pdf = is_file($pdfPath) ? file_get_contents($pdfPath) : false;
if ($pdf === false) {
    $blockers[] = 'includes/pdf/PdfReportService.php: missing PDF security boundary';
} else {
    if (!str_contains($pdf, "set('isRemoteEnabled', false)")) {
        $blockers[] = 'includes/pdf/PdfReportService.php: remote PDF loading is not explicitly disabled';
    }
    if (!str_contains($pdf, "set('chroot', \$this->root)")) {
        $blockers[] = 'includes/pdf/PdfReportService.php: PDF engine has no application-root chroot';
    }
    if (str_contains($pdf, "echo 'PDF generation unavailable: ' . \$e->getMessage()")) {
        $blockers[] = 'includes/pdf/PdfReportService.php: raw PDF exception is disclosed to browser';
    }
}

$configPath = $root . '/config.php';
$config = is_file($configPath) ? file_get_contents($configPath) : false;
if ($config === false) {
    $blockers[] = 'config.php: missing configuration security boundary';
} else {
    foreach ([
        "ini_set('session.use_strict_mode', '1')" => 'strict session mode',
        "'httponly' => true" => 'HttpOnly session cookie',
        "'samesite' => 'Lax'" => 'SameSite session cookie',
        "X-Content-Type-Options: nosniff" => 'nosniff header',
        "X-Frame-Options: SAMEORIGIN" => 'frame protection',
        "Referrer-Policy: strict-origin-when-cross-origin" => 'referrer policy',
    ] as $needle => $label) {
        if (!str_contains($config, $needle)) {
            $blockers[] = 'config.php: missing ' . $label;
        }
    }

    if (!str_contains($config, "getenv('DB_PASS')")) {
        $blockers[] = 'config.php: DB password is not environment-backed';
    }
}

sort($blockers);
sort($review);

echo 'SCANNED_FILES=' . $scanned . PHP_EOL;
echo 'BLOCKERS=' . count($blockers) . PHP_EOL;
foreach ($blockers as $finding) {
    echo 'BLOCKER: ' . $finding . PHP_EOL;
}

echo 'REVIEW_FINDINGS=' . count($review) . PHP_EOL;
foreach ($review as $finding) {
    echo 'REVIEW: ' . $finding . PHP_EOL;
}

echo 'GA_SOURCE_SECURITY_SCAN=' . ($blockers === [] ? 'PASS' : 'FAIL') . PHP_EOL;
exit($blockers === [] ? 0 : 1);
