<?php
declare(strict_types=1);
/**
 * B12 guarded Ruminant deletion page/permission source contract.
 * Static only: no HTTP, SQL, app bootstrap or source mutation.
 */
$root = dirname(__DIR__, 2);
$passes=0;
function b12_rum_stage_check(bool $check,string $label):void {
    global $passes;
    if(!$check)throw new RuntimeException('FAIL: '.$label);
    ++$passes;
}
function b12_rum_stage_file(string $root,string $name):string {
    $source=file_get_contents($root.'/'.$name);
    if(!is_string($source))throw new RuntimeException('Missing '.$name);
    return $source;
}
try {
    $gate=b12_rum_stage_file($root,'lib/daily_record_revision_cutover_gate.php');
    b12_rum_stage_check(
        preg_match('~function daily_record_revision_cutover_enabled\(\): bool\s*\{\s*return false;\s*\}~',$gate)===1,
        'cutover is hard OFF'
    );
    $helper=b12_rum_stage_file($root,'lib/daily_record_revision_delete_http.php');
    foreach([
        'daily_record_revision_delete_is_ajax(',
        'daily_record_revision_delete_http_envelope(',
        'daily_record_revision_delete_http_send(',
        'daily_record_revision_delete_require_ajax(',
        "'record_conflict'",
        "'revision_client_required'",
        "'forbidden'",
        "'invalid_delete_request'",
        "'delete_failed'",
        "'deleted'",
        "http_response_code(",
        "header('Content-Type: application/json",
    ] as $snippet) {
        b12_rum_stage_check(str_contains($helper,$snippet),'one shared policy has '.$snippet);
    }
    b12_rum_stage_check(
        !str_contains($helper,'header(\'Location:') &&
        !str_contains($helper,'->beginTransaction(') &&
        !str_contains($helper,'->commit(') &&
        !str_contains($helper,'->prepare('),
        'shared response policy cannot navigate or mutate database'
    );

    $routes=[
        'ruminant/ruminant_daily_record.php' => 'privileged page',
        'includes/permission_runtime.php' => 'delegated permission runtime',
    ];
    foreach ($routes as $file=>$label) {
        $source=b12_rum_stage_file($root,$file);
        b12_rum_stage_check(
            str_contains($source,'ruminant_daily_record_delete_canonical(') &&
            str_contains($source,'$_POST'),
            $label.' still uses shared delete transaction with original POST'
        );
        $guard=strpos($source,'$b12DeleteAjax = daily_record_revision_cutover_enabled();');
        $preflight=strpos($source,'daily_record_revision_delete_require_ajax($_SERVER);',$guard===false?0:$guard);
        $service=strpos($source,'ruminant_daily_record_delete_canonical(',$guard===false?0:$guard);
        $success=strpos($source,'daily_record_revision_delete_http_send(null, $recordId);',$guard===false?0:$guard);
        $failure=strpos($source,'daily_record_revision_delete_http_send($e);',$guard===false?0:$guard);
        $redirect=strpos($source,"header('Location:",$guard===false?0:$guard);
        b12_rum_stage_check(
            $guard!==false && $preflight!==false &&
            $service!==false && $success!==false && $failure!==false &&
            $guard<$preflight && $preflight<$service &&
            $service<$success && $success<$failure,
            $label.' checks the B12 transport before mutation and sends JSON after'
        );
        b12_rum_stage_check(
            $redirect!==false && $redirect>$failure &&
            str_contains($source,'$_SESSION[\'success\']') &&
            str_contains($source,'$_SESSION[\'error\']'),
            $label.' legacy redirect and notification remain for hard-off state'
        );
        b12_rum_stage_check(
            str_contains($source,"new InvalidArgumentException('Invalid daily record ID.')") &&
            str_contains($source,'daily_record_revision_delete_http_send('),
            $label.' malformed IDs return structured 422 after cutover'
        );
    }
    $page=b12_rum_stage_file($root,'ruminant/ruminant_daily_record.php');
    b12_rum_stage_check(
        str_contains($page,"daily_record_revision_delete_form_fields('ruminant', \$tenantFarmId, \$record)") &&
        str_contains($page,'data-confirm="Delete this record?') &&
        !str_contains($page,'daily-record-revision-delete.js'),
        'page still has originally rendered guarded form tokens, legacy modal and NO activated AJAX client'
    );
    echo "B12_RUMINANT_DELETE_HTTP_SOURCE=PASS\nPASS_COUNT=$passes\n";
} catch (Throwable $e) {
    fwrite(STDERR,'B12_RUMINANT_DELETE_HTTP_SOURCE=FAIL '.$e->getMessage().PHP_EOL);
    exit(1);
}
