<?php

declare(strict_types=1);

/**
 * Renee AgriSuite v3.2 GA source-security contract.
 *
 * This is intentionally a high-confidence source contract rather than a generic
 * vulnerability scanner. It must not print secret values, environment values,
 * tokens, credentials, request bodies, or source snippets that might contain
 * secrets. Dependency advisories are handled separately by `composer audit`.
 */

$root = dirname(__DIR__);
$failures = 0;
$warnings = 0;

$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . ltrim($relative, '/');
    $data = @file_get_contents($path);
    if (!is_string($data)) {
        throw new RuntimeException('Required source file is unavailable: ' . $relative);
    }
    return $data;
};

$check = static function (bool $condition, string $label) use (&$failures): void {
    if ($condition) {
        echo 'PASS: ' . $label . PHP_EOL;
        return;
    }
    echo 'FAIL: ' . $label . PHP_EOL;
    $failures++;
};

$warn = static function (bool $condition, string $label) use (&$warnings): void {
    if (!$condition) return;
    echo 'WARN: ' . $label . PHP_EOL;
    $warnings++;
};

try {
    $config = $read('config.php');
    $sign = $read('sign.php');
    $passwordSecurity = $read('includes/password_security.php');
    $csrf = $read('includes/csrf.php');
    $output = $read('includes/output_security.php');
    $csp = $read('includes/csp_policy.php');
    $api = $read('api/api_helpers.php');
    $webhook = $read('billing/webhook.php');
    $paystack = $read('includes/billing_provider_paystack.php');
    $scriptsHtaccess = $read('scripts/.htaccess');
    $gitignore = $read('.gitignore');
} catch (Throwable $e) {
    echo 'FAIL: required GA security source could not be read.' . PHP_EOL;
    echo 'FAILURES=1' . PHP_EOL;
    echo 'WARNINGS=0' . PHP_EOL;
    echo 'GA_SOURCE_SECURITY_CONTRACT=FAIL' . PHP_EOL;
    exit(1);
}

$check(
    str_contains($config, "session.use_strict_mode', '1")
        || str_contains($config, 'session.use_strict_mode", "1'),
    'PHP session strict mode is enabled'
);

$check(
    str_contains($config, "'httponly' => true"),
    'session cookie is HttpOnly'
);

$check(
    str_contains($config, "'samesite' => 'Lax'"),
    'session cookie has SameSite policy'
);

$check(
    str_contains($sign, 'password_security_verify(')
        && str_contains($passwordSecurity, 'password_verify('),
    'login uses central hash-only password verification'
);

$check(
    str_contains($passwordSecurity, "password_get_info(\$storedHash)['algo'] === 0"),
    'central password verifier rejects plaintext compatibility'
);

$check(
    str_contains($sign, 'session_regenerate_id(true)'),
    'successful login rotates session identifier'
);

$check(
    str_contains($sign, "'/includes/csrf.php'")
        && str_contains($sign, 'csrf_validate_request()')
        && str_contains($sign, 'csrf_field()'),
    'login form and POST path use shared CSRF protection'
);

$check(
    preg_match("/require_rate_limit\\(\\s*'login_attempt'\\s*,\\s*12\\s*,\\s*300\\s*\\)/", $sign) === 1,
    'login attempt rate limit is active'
);

$check(
    str_contains($csrf, 'verify_csrf_token('),
    'shared CSRF adapter delegates to canonical verifier'
);

$check(
    str_contains($output, 'ENT_QUOTES | ENT_SUBSTITUTE')
        && str_contains($output, "'UTF-8'"),
    'central HTML escaping uses strict UTF-8 attribute-safe encoding'
);

$check(
    str_contains($output, 'JSON_HEX_TAG')
        && str_contains($output, 'JSON_HEX_AMP')
        && str_contains($output, 'JSON_HEX_APOS')
        && str_contains($output, 'JSON_HEX_QUOT'),
    'central script JSON helper hex-escapes HTML-significant characters'
);

$check(
    str_contains($csp, "Content-Security-Policy'")
        || str_contains($csp, 'Content-Security-Policy:'),
    'application has centralized enforcing CSP support'
);

$check(
    str_contains($webhook, 'billing_provider_verify_webhook('),
    'billing webhook authenticates provider event'
);

$check(
    str_contains($webhook, 'billing_provider_verify_payment('),
    'billing webhook independently verifies payment with provider'
);

$verifyWebhookPos = strpos($webhook, 'billing_provider_verify_webhook(');
$registerEventPos = strpos($webhook, 'billing_provider_event_register(');
$check(
    $verifyWebhookPos !== false
        && $registerEventPos !== false
        && $verifyWebhookPos < $registerEventPos,
    'webhook authentication occurs before event persistence'
);

$check(
    str_contains($paystack, "hash_hmac('sha512'")
        && str_contains($paystack, 'hash_equals('),
    'Paystack webhook uses HMAC-SHA512 and timing-safe comparison'
);

$check(
    str_contains($scriptsHtaccess, 'Require all denied')
        && str_contains($scriptsHtaccess, 'deny from all'),
    'maintenance/verifier scripts are denied over Apache HTTP'
);

$check(
    str_contains($gitignore, "\n.env\n")
        && str_contains($gitignore, "\n.env.*\n")
        && str_contains($gitignore, "\n/.htaccess\n"),
    'runtime secrets and production root htaccess are excluded from Git'
);

$check(
    str_contains($api, 'PDOException')
        && str_contains($api, 'SQLSTATE'),
    'API exception helper suppresses database/SQLSTATE details'
);

// Candidate findings are warnings until caller/reachability analysis proves a defect.
$warn(
    str_contains($api, 'return $message;'),
    'GA-SEC-002 candidate: non-PDO Throwable messages may be returned by API helper; caller audit required'
);

$warn(
    str_contains($config, "'/login.php'")
        || str_contains($config, "'/login.php?timeout=1'"),
    'GA-SEC-001: legacy /login.php redirect target remains in config; canonical-route remediation required'
);

$functions = $read('includes/functions.php');
$warn(
    str_contains($functions, 'function runSchemaMigrations('),
    'GA-ARCH-001 candidate: legacy runtime schema migration entry point exists; reachability audit required'
);

// Never print matching source, secret-like values, environment contents, or request data.
echo 'FAILURES=' . $failures . PHP_EOL;
echo 'WARNINGS=' . $warnings . PHP_EOL;
echo 'GA_SOURCE_SECURITY_CONTRACT=' . ($failures === 0 ? 'PASS' : 'FAIL') . PHP_EOL;

exit($failures === 0 ? 0 : 1);
