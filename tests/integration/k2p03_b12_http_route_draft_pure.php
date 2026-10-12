<?php
declare(strict_types=1);

/** Semantic PHP-token preflight for the inert, documented B12 route draft. */
$root = dirname(__DIR__, 2);
$draft = $root . '/docs/C137_GC24_K2_P03_B12_HTTP_ROUTE_DRAFT.php.txt';
$code = file_get_contents($draft);
if (!is_string($code) || $code === '') {
    fwrite(STDERR, 'B12_HTTP_ROUTE_DRAFT=FAIL missing draft' . PHP_EOL);
    exit(1);
}
$all = token_get_all($code, TOKEN_PARSE);
$tokens = [];
foreach ($all as $t) {
    if (is_array($t)) {
        if (in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) continue;
        $tokens[] = $t[1];
    } else {
        $tokens[] = $t;
    }
}
$plain = implode(' ', $tokens);
$checks = 0;
function b12_route_check(bool $ok, string $what): void {
    global $checks;
    if (!$ok) {
        fwrite(STDERR, 'FAIL: ' . $what . PHP_EOL);
        exit(1);
    }
    ++$checks;
    echo 'PASS: ' . $what . PHP_EOL;
}
function b12_route_call_index(array $tokens, string $name): int {
    foreach ($tokens as $i => $token) {
        if ($token === $name && ($tokens[$i + 1] ?? null) === '(') return $i;
    }
    return -1;
}
function b12_route_sequence_index(array $tokens, array $needle): int {
    $n=count($needle);
    for ($i=0;$i<=count($tokens)-$n;$i++) {
        if (array_slice($tokens,$i,$n)===$needle) return $i;
    }
    return -1;
}

try {
    b12_route_check(str_ends_with($draft, '.php.txt'), 'draft is not executable php URL');
    b12_route_check(!str_contains($draft, '/api/'), 'draft resides outside api routes');
    b12_route_check(b12_route_sequence_index($tokens, ['declare','(','strict_types','=','1',')',';']) >= 0,
        'strict PHP source draft syntax');
    $failStatus = b12_route_sequence_index($tokens, ['http_response_code','(','503',')',';']);
    $exitPoint = array_search('exit', $tokens, true);
    $firstRequire = array_search('require_once', $tokens, true);
    b12_route_check($failStatus >= 0, '503 fail-closed response is present');
    b12_route_check(is_int($exitPoint) && $exitPoint > $failStatus, 'unconditional exit follows 503');
    b12_route_check(is_int($firstRequire) && $firstRequire > $exitPoint,
        'all app/database bootstrap blocked behind unconditional exit');
    b12_route_check(b12_route_call_index($tokens,'isLoggedIn') >= 0, 'explicit unauthenticated response guard');
    b12_route_check(b12_route_call_index($tokens,'requireLogin') > $firstRequire,
        'existing account freshness function required by eventual integration');
    b12_route_check(b12_route_call_index($tokens,'daily_record_revision_api_handle') >
        b12_route_call_index($tokens,'requireLogin'),
        'adapter only called after canonical authentication');
    b12_route_check(b12_route_call_index($tokens,'send_json') >= 0,
        'existing shared JSON/no-store responder reused');
    b12_route_check(b12_route_sequence_index($tokens, ['$_GET']) >= 0,
        'future request reads GET query only');
    b12_route_check(b12_route_sequence_index($tokens, ['$_SERVER','[',"'REQUEST_METHOD'",']']) >= 0,
        'future method obtained from request runtime');
    b12_route_check(!str_contains($plain, '$_POST') && !str_contains($plain, '$_REQUEST'),
        'no POST or permissive mixed-input fallback');
    b12_route_check(!preg_match('/\b(?:INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP|CREATE)\s+(?:INTO|FROM|TABLE|DATABASE|\w+)/i',
        preg_replace('~(?:/\*.*?\*/|//[^\n]*|#[^\n]*)~s','',$code) ?? ''),
        'no embedded mutating SQL');
    b12_route_check(!str_contains($code, '$_GET[\'farm_id\']') &&
        !str_contains($code, '$_REQUEST[\'farm_id\']'),
        'no client-provided tenant ID');
    b12_route_check(substr_count($code, 'daily_record_revision_api_handle(') === 1,
        'one shared API adapter call, not duplicated business logic');
    b12_route_check(substr_count($code, 'requireLogin()') >= 1,
        'source keeps canonical account guard requirement visible');
    b12_route_check(str_contains($code, 'BLOCKED until canonical JSON-capable account guard'),
        'redirect-to-JSON auth incompatibility explicitly blocked');
    echo 'B12_HTTP_ROUTE_DRAFT_PURE=PASS' . PHP_EOL;
    echo 'PASS_COUNT=' . $checks . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'B12_HTTP_ROUTE_DRAFT_PURE=FAIL '.get_class($e).' '.$e->getMessage().PHP_EOL);
    exit(1);
}
