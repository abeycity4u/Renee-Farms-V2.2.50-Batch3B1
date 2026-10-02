<?php
/**
 * GA browser-API security contract audit.
 *
 * This is a source contract, not a substitute for runtime IDOR/authorization
 * probes. It proves minimum entry-point controls consistently exist while
 * recognizing the application's canonical shared helper names.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$apiDir = $root . '/api';
$failures = 0;
$review = [];
$checked = 0;

function api_contract_check(bool $condition, string $label): void
{
    global $failures;
    if ($condition) {
        echo "PASS: {$label}\n";
        return;
    }
    $failures++;
    echo "FAIL: {$label}\n";
}

$files = glob($apiDir . '/*.php') ?: [];
sort($files);

foreach ($files as $file) {
    $name = basename($file);
    if ($name === 'api_helpers.php') continue;

    $source = file_get_contents($file);
    api_contract_check($source !== false, $name . ' is readable');
    if ($source === false) continue;

    $checked++;

    $authenticated = str_contains($source, 'requireLogin(');

    api_contract_check(
        $authenticated,
        $name . ' requires authenticated application session'
    );

    $isMutation = preg_match('/^(?:create|update|delete)_/i', $name) === 1;
    if (!$isMutation) {
        if (
            !str_contains($source, 'farm_id')
            && !str_contains($source, 'getCurrentFarmId')
            && !str_contains($source, 'requireCurrentFarmId')
        ) {
            $review[] = $name . ': read route has no obvious direct farm scope token; confirm tenant binding through delegated service';
        }
        continue;
    }

    $postGuard =
        str_contains($source, "require_http_method('POST')")
        || str_contains($source, 'require_valid_csrf_post()')
        || preg_match('/REQUEST_METHOD[^\n]{0,100}POST/i', $source) === 1;

    $csrfGuard =
        str_contains($source, 'require_csrf_token()')
        || str_contains($source, 'require_valid_csrf_post()')
        || str_contains($source, 'csrf_request_is_valid(')
        || str_contains($source, 'verify_csrf_token(');

    api_contract_check($postGuard, $name . ' enforces POST mutation method');
    api_contract_check($csrfGuard, $name . ' enforces CSRF for browser mutation');

    if (
        !str_contains($source, 'farm_id')
        && !str_contains($source, 'getCurrentFarmId')
        && !str_contains($source, 'requireCurrentFarmId')
    ) {
        $review[] = $name . ': mutation has no obvious direct farm scope token; confirm tenant binding through delegated service';
    }
}

sort($review);
echo 'API_ROUTES_CHECKED=' . $checked . PHP_EOL;
echo 'API_REVIEW_FINDINGS=' . count($review) . PHP_EOL;
foreach ($review as $finding) {
    echo 'REVIEW: ' . $finding . PHP_EOL;
}
echo 'FAILURES=' . $failures . PHP_EOL;
echo 'API_SECURITY_CONTRACT=' . ($failures === 0 ? 'PASS' : 'FAIL') . PHP_EOL;
exit($failures === 0 ? 0 : 1);
