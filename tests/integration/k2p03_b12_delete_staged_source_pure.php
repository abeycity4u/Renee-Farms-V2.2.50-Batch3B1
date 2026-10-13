<?php
declare(strict_types=1);
/**
 * B12 source-only DELETE staged cutover inventory. This verifier only reads
 * source files and does not certify transactions, true browser behavior, or
 * real multi-connection MariaDB concurrency.
 */
$root = dirname(__DIR__,2);
$checks=0;
function b12_delete_stage_check(bool $good, string $label):void {
    global $checks;
    if(!$good)throw new RuntimeException('FAILED: '.$label);
    $checks++;
}
function b12_delete_stage_read(string $root,string $path):string {
    $text=file_get_contents($root.'/'.$path);
    if(!is_string($text))throw new RuntimeException('Missing source '.$path);
    return $text;
}
try {
    $gate=b12_delete_stage_read($root,'lib/daily_record_revision_cutover_gate.php');
    b12_delete_stage_check(
        preg_match('/function daily_record_revision_cutover_enabled\(\): bool\s*\{\s*return false;\s*\}/D',$gate)===1 &&
        !str_contains($gate,'function_exists'),
        'hard-off cutover cannot be overridden'
    );
    $common=b12_delete_stage_read($root,'lib/daily_record_revision_delete_pipeline.php');
    foreach ([
        'daily_record_revision_parse_expected(',
        'daily_record_revision_validate_scope(',
        'daily_record_revision_lock_and_compare(',
        'daily_record_revision_delete_locked(',
        'delete_daily_feed_usage(',
        'daily_population_remove_mortality(',
        'sales_rebuild_layer_egg_allocations(',
        'daily_record_revision_form_fields(',
    ] as $marker) {
        b12_delete_stage_check(str_contains($common,$marker),
            'shared DELETE contract has '.$marker);
    }
    $first=strpos($common,'daily_record_revision_lock_and_compare(');
    $feed=strpos($common,'delete_daily_feed_usage(');
    $mortality=strpos($common,'daily_population_remove_mortality(');
    $cas=strpos($common,'daily_record_revision_delete_locked(');
    $sales=strpos($common,'sales_rebuild_layer_egg_allocations(');
    b12_delete_stage_check(
        $first!==false&&$feed!==false&&$mortality!==false&&$cas!==false&&$sales!==false&&
        $first<$feed&&$feed<$mortality&&$mortality<$cas&&$cas<$sales,
        'source lock -> feed -> mortality -> CAS -> Layer sales'
    );
    b12_delete_stage_check(
        str_contains($common,'$pdo->rollBack();')&&
        str_contains($common,'$pdo->commit()')&&
        str_contains($common,'if ($type === \'layer\')'),
        'rollback and Layer-only sales rebuild retained'
    );

    $api=b12_delete_stage_read($root,'api/delete_record.php');
    b12_delete_stage_check(
        str_contains($api,'require_http_method(\'POST\')')&&
        str_contains($api,'require_csrf_token();')&&
        str_contains($api,'require_rate_limit('),
        'poultry delete endpoint retains session/CSRF/rate-limiter'
    );
    b12_delete_stage_check(
        substr_count($api,'if (daily_record_revision_cutover_enabled())')===1&&
        substr_count($api,'daily_record_revision_delete_transaction(')===1&&
        str_contains($api,"'code'=>'record_conflict'")&&
        str_contains($api,'], 409)'),
        'poultry staged delete gated and returns 409 on stale revision'
    );
    b12_delete_stage_check(
        str_contains($api,'daily_record_revision_lock_source_for_delete(')&&
        str_contains($api,'DELETE FROM layer_daily_records WHERE id = ? AND farm_id = ?')&&
        str_contains($api,'DELETE FROM broiler_daily_records WHERE id = ? AND farm_id = ?'),
        'legacy Poultry exact-delete path still intact'
    );

    $ruminant=b12_delete_stage_read($root,'lib/ruminant_daily_record_deletion.php');
    b12_delete_stage_check(
        substr_count($ruminant,'if (daily_record_revision_cutover_enabled())')===1&&
        substr_count($ruminant,'daily_record_revision_delete_transaction(')===1&&
        str_contains($ruminant,'?array $post = null'),
        'shared Ruminant delete service preserves compatible signature'
    );
    b12_delete_stage_check(
        str_contains($ruminant,'daily_record_revision_lock_source_for_delete(')&&
        str_contains($ruminant,'delete_daily_feed_usage(')&&
        str_contains($ruminant,'daily_population_remove_mortality(')&&
        str_contains($ruminant,'DELETE FROM ruminant_daily_records'),
        'existing delegated/privileged shared reversals retained'
    );
    foreach([
        'ruminant/ruminant_daily_record.php',
        'includes/permission_runtime.php'
    ] as $caller) {
        $source=b12_delete_stage_read($root,$caller);
        b12_delete_stage_check(
            str_contains($source,'ruminant_daily_record_delete_canonical(')&&
            str_contains($source,'$_POST'),
            $caller.' forwards browser POST to future B12 contract'
        );
    }
    $page=b12_delete_stage_read($root,'ruminant/ruminant_daily_record.php');
    b12_delete_stage_check(
        str_contains($page,'daily_record_revision_delete_form_fields(')&&
        str_contains($page,'if (daily_record_revision_cutover_enabled()):')&&
        str_contains($page,'if ($b12Name === \'record_id\') continue;'),
        'Ruminant delete tokens rendered only behind cutover without duplicate id'
    );
    echo "B12_DELETE_STAGED_SOURCE_PURE=PASS\nPASS_COUNT={$checks}\n";
} catch(Throwable $e){
    fwrite(STDERR,"B12_DELETE_STAGED_SOURCE_PURE=FAIL ".$e->getMessage()."\n");
    exit(1);
}
