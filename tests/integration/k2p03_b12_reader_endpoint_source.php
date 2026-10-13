<?php
declare(strict_types=1);

/**
 * B12 source-only authenticated read cutover verifier.
 * It reads source text. It does not run a PHP HTTP server, access tenant DB,
 * perform an API request or certify schema/browsers/concurrent writes.
 */
$root=dirname(__DIR__,2);
$passed=0;
function b12_read_check(bool $ok,string $label):void
{
    global $passed;
    if(!$ok)throw new RuntimeException('FAILED: '.$label);
    ++$passed;
}
function b12_read_source(string $root,string $path):string
{
    $data=file_get_contents($root.'/'.$path);
    if(!is_string($data))throw new RuntimeException('Missing '.$path);
    return $data;
}
try {
    $gate=b12_read_source($root,'lib/daily_record_revision_cutover_gate.php');
    b12_read_check(
        preg_match('/function daily_record_revision_cutover_enabled\(\): bool\s*\{\s*return false;\s*\}/',$gate)===1 &&
        !str_contains($gate,'function_exists('),
        'cutover permanently OFF in current source'
    );

    $api=b12_read_source($root,'api/daily_record_revision_read.php');
    b12_read_check(
        str_starts_with($api,"<?php\ndeclare(strict_types=1);"),
        'PHP strict_types first statement'
    );
    b12_read_check(
        str_contains($api,"require_once dirname(__DIR__) . '/init.php';") &&
        str_contains($api,'requireLogin();') &&
        str_contains($api,"require_http_method('GET');"),
        'existing authentication and method guards retained'
    );
    $auth=strpos($api,'requireLogin();');
    $method=strpos($api,"require_http_method('GET');");
    $fuse=strpos($api,'if (!daily_record_revision_cutover_enabled()) {');
    $disabled=strpos($api,"'code' => 'revision_cutover_disabled'");
    $limit=strpos($api,"require_rate_limit('daily_record_revision_read', 120, 60);");
    $adapter=strpos($api,"require_once __DIR__ . '/../lib/daily_record_revision_api_adapter.php';");
    b12_read_check(
        $auth!==false&&$method!==false&&$fuse!==false&&
        $disabled!==false&&$limit!==false&&$adapter!==false&&
        $auth<$method&&$method<$fuse&&$fuse<$disabled&&
        $disabled<$limit&&$limit<$adapter,
        'authenticated method -> hard-off 503 -> limit -> read adapter ordering'
    );
    b12_read_check(
        str_contains($api,'], 503);')&&
        str_contains($api,"send_json(\$result['body'], \$result['http_status']);"),
        '503 while disabled and canonical no-store JSON when enabled'
    );
    b12_read_check(
        !str_contains($api,'$_POST') &&
        !str_contains($api,'$_REQUEST') &&
        !preg_match('/\b(?:INSERT|UPDATE|DELETE)\s+(?:INTO|FROM|[a-z_]+)/i',$api),
        'reader endpoint has no POST or SQL mutation'
    );

    $adapterSource=b12_read_source($root,'lib/daily_record_revision_api_adapter.php');
    b12_read_check(
        str_contains($adapterSource,"if (\$method !== 'GET')") &&
        str_contains($adapterSource,'daily_record_revision_reader_check_scope(') &&
        str_contains($adapterSource,'daily_record_revision_reader_read_exact('),
        'existing strict scope/exact adapter remains source authority'
    );
    b12_read_check(
        str_contains($adapterSource,'daily_record_revision_api_require_schema($pdo);') &&
        str_contains($adapterSource,'daily_record_revision_api_authorized_farm('),
        'authorized farm and all-table revision schema gate still checked'
    );

    $transport=b12_read_source($root,'assets/js/daily-record-revision-transport.js');
    b12_read_check(
        str_contains($transport,'../api/daily_record_revision_read.php') &&
        str_contains($transport,"method: 'GET'") &&
        str_contains($transport,"credentials: 'same-origin'") &&
        str_contains($transport,"redirect: 'error'") &&
        str_contains($transport,"cache: 'no-store'"),
        'same-origin nonredirecting GET transport'
    );
    b12_read_check(
        str_contains($transport,"'farm_id'")===false &&
        str_contains($transport,'response.status !== 200') &&
        str_contains($transport,"input.mode === 'exact'"),
        'client cannot select tenant and must reject non-200 exact reads'
    );

    foreach([
        'poultry/layers_daily_record.php',
        'poultry/broiler_daily_record.php',
        'ruminant/ruminant_daily_record.php'
    ] as $page) {
        $source=b12_read_source($root,$page);
        b12_read_check(
            !str_contains($source,'daily-record-revision-transport.js') &&
            !str_contains($source,'daily_record_revision_read.php'),
            $page.' does not silently activate reader transport'
        );
    }
    echo "B12_READER_ENDPOINT_SOURCE=PASS\nPASS_COUNT={$passed}\n";
} catch(Throwable $error) {
    fwrite(STDERR,'B12_READER_ENDPOINT_SOURCE=FAIL '.$error->getMessage()."\n");
    exit(1);
}
