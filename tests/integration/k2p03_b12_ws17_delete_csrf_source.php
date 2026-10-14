<?php
declare(strict_types=1);
/**
 * WS17 Ruminant DELETE rejected-CSRF response source contract.
 * Pure PHP/source inspection only: no app bootstrap, SQL, network, HTTP
 * listener, test DB, staging, migrations or repository mutations.
 * This does NOT certify two real authenticated DELETE caller paths.
 */
$root = dirname(__DIR__, 2);
$count = 0;
function ws17_check(bool $ok, string $description): void
{
    global $count;
    if (!$ok) throw new RuntimeException('FAIL ' . $description);
    ++$count;
}
function ws17_read(string $file): string
{
    global $root;
    $source = file_get_contents($root . '/' . $file);
    if (!is_string($source)) throw new RuntimeException('Missing ' . $file);
    return $source;
}
try {
    $gate = ws17_read('lib/daily_record_revision_cutover_gate.php');
    ws17_check(
        preg_match('~function\s+daily_record_revision_cutover_enabled\s*\(\s*\)\s*:\s*bool\s*\{\s*return\s+false\s*;\s*\}~', $gate) === 1,
        'checked-in B12 cutover remains literally hard OFF'
    );
    require_once $root . '/lib/daily_record_revision_delete_http.php';
    ws17_check(daily_record_revision_cutover_enabled() === false,
        'requiring shared Ruminant DELETE responder cannot enable B12');
    $shared = ws17_read('lib/daily_record_revision_delete_http.php');
    $start = strpos($shared, 'function daily_record_revision_delete_http_csrf_failure()');
    $next = strpos($shared, "if (!function_exists('daily_record_revision_delete_require_ajax'))", $start === false ? 0 : $start);
    ws17_check($start !== false && $next !== false && $start < $next,
        'single shared response helper precedes existing non-AJAX preflight');
    $scope = substr($shared, $start, $next - $start);
    foreach ([
        'if (!daily_record_revision_cutover_enabled())',
        'http_response_code(419)',
        "exit('Invalid request token.')",
        'daily_record_revision_http_send_json(419',
        "'success' => false",
        "'code' => 'invalid_csrf'",
        "'error' => 'Invalid request token.",
    ] as $required) {
        ws17_check(str_contains($scope, $required), 'shared rejection owns ' . $required);
    }
    ws17_check(
        strpos($scope, 'if (!daily_record_revision_cutover_enabled())') <
        strpos($scope, 'daily_record_revision_http_send_json(419') &&
        !str_contains($scope, '->prepare(') &&
        !str_contains($scope, '->execute(') &&
        !str_contains($scope, '->commit(') &&
        !str_contains($scope, 'header(\'Location:') &&
        !str_contains($scope, '$_SESSION'),
        'shared helper preserves OFF and cannot authorize, mutate or redirect'
    );
    ws17_check(
        substr_count($shared, 'function daily_record_revision_delete_http_csrf_failure()') === 1,
        'one canonical 419 response function'
    );
    foreach ([
        'includes/permission_runtime.php' => [
            "require_once dirname(__DIR__) . '/lib/daily_record_revision_delete_http.php';",
            'delegated Ruminant manager',
        ],
        'ruminant/ruminant_daily_record.php' => [
            "require_once __DIR__ . '/../lib/daily_record_revision_delete_http.php';",
            'privileged Ruminant page',
        ],
    ] as $file => [$require, $label]) {
        $page = ws17_read($file);
        $csrfNeedle = <<<'PHP'
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
PHP;
        $csrfAt = strpos($page, $csrfNeedle);
        $endAt = $csrfAt !== false
            ? strpos($page, "exit('Invalid request token.');", $csrfAt)
            : false;
        ws17_check($csrfAt !== false && $endAt !== false,
            $label . ' retains original CSRF rejection');
        $segment = substr($page, $csrfAt, $endAt - $csrfAt + 32);
        $guard = strpos($segment, 'if (daily_record_revision_cutover_enabled())');
        $import = strpos($segment, $require);
        $send = strpos($segment, 'daily_record_revision_delete_http_csrf_failure();');
        $status = strpos($segment, 'http_response_code(419);');
        ws17_check(
            $guard !== false && $import !== false &&
            $send !== false && $status !== false &&
            $guard < $import && $import < $send && $send < $status,
            $label . ' sends gated JSON only after invalid-CSRF decision'
        );
        ws17_check(
            substr_count($segment, 'daily_record_revision_delete_http_csrf_failure();') === 1 &&
            str_contains($segment, "exit('Invalid request token.');"),
            $label . ' preserves literal OFF 419 plaintext after guarded helper'
        );
        $deleteService = strpos($page, 'ruminant_daily_record_delete_canonical(', $endAt);
        ws17_check($deleteService !== false && $endAt < $deleteService,
            $label . ' rejects invalid CSRF before shared mutation service');
        ws17_check(
            str_contains($page, 'daily_record_revision_delete_require_ajax($_SERVER);') &&
            str_contains($page, 'daily_record_revision_delete_http_send(null, $recordId);'),
            $label . ' retains existing future AJAX preflight and success contract'
        );
    }
    $submit = ws17_read('assets/js/daily-record-revision-delete.js');
    ws17_check(
        str_contains($submit, "response.status !== 200") &&
        str_contains($submit, "return Object.freeze({status:'blocked'});") &&
        str_contains($submit, "redirect:'error'"),
        'existing Ruminant browser fails closed on JSON/plaintext 419, without retry'
    );
    $preroute = ws17_read('lib/daily_record_revision_preroute_http.php');
    ws17_check(str_contains($preroute, "isset(\$post['save_record'])") &&
        !str_contains($preroute, "isset(\$post['delete_record'])"),
        'WS15 exact SAVE pre-route matcher remains unchanged and excludes DELETE');
    echo "B12_WS17_DELETE_CSRF_SOURCE=PASS\nPASS_COUNT=$count\n";
} catch (Throwable $error) {
    fwrite(STDERR, "B12_WS17_DELETE_CSRF_SOURCE=FAIL " . $error->getMessage() . PHP_EOL);
    exit(1);
}
