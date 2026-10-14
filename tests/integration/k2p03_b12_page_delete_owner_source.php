<?php
declare(strict_types=1);
/**
 * B12 DELETE single-handler ownership source audit.
 * No app bootstrap, HTTP, DB, schema migrations, or live PHP page changes.
 */
$root=dirname(__DIR__,2);
$pass=0;
function b12_delete_owner_check(bool $valid,string $name):void {
    global $pass;
    if(!$valid)throw new RuntimeException('FAIL '.$name);
    ++$pass;
}
function b12_delete_owner_read(string $root,string $path):string {
    $s=file_get_contents($root.'/'.$path);
    if(!is_string($s))throw new RuntimeException('Missing '.$path);
    return $s;
}
try {
    $gate=b12_delete_owner_read($root,'lib/daily_record_revision_cutover_gate.php');
    b12_delete_owner_check(
        preg_match('~function daily_record_revision_cutover_enabled\(\): bool\s*\{\s*return false;\s*\}~',$gate)===1,
        'revision cutover remains literal hard OFF'
    );
    $owner=b12_delete_owner_read($root,'assets/js/daily-record-revision-page-delete.js');
    $page=b12_delete_owner_read($root,'assets/js/daily-record-revision-page-adapters.js');
    $transport=b12_delete_owner_read($root,'assets/js/daily-record-revision-delete.js');
    $rumBinder=b12_delete_owner_read($root,'assets/js/daily-record-revision-ruminant-delete.js');
    $http=b12_delete_owner_read($root,'lib/daily_record_revision_delete_http.php');

    b12_delete_owner_check(
        str_contains($owner,'function createBindings(options)') &&
        str_contains($owner,'function preflight()') &&
        str_contains($owner,'function bind()') &&
        str_contains($page,'deletes.preflight();') &&
        str_contains($page,'deletes.bind();') &&
        strpos($page,'deletes.preflight();') <
            strpos($page,'derived.bind();') &&
        strpos($page,'deletes.bind();') <
            strpos($page,"button.addEventListener('click',"),
        'shared page owner authorizes DELETE before installing calendar controls'
    );
    b12_delete_owner_check(
        str_contains($owner,'bound.has(button)') &&
        str_contains($owner,"button.getAttribute('onclick')!==null") &&
        str_contains($owner,'ds.b12DeleteTokens') &&
        str_contains($owner,'options.csrfToken') &&
        str_contains($owner,'shared.createSender('),
        'Poultry original-row DELETE token/CSRF and duplicate handler preflight'
    );
    b12_delete_owner_check(
        str_contains($owner,"event.preventDefault()") &&
        str_contains($owner,"event.stopImmediatePropagation()") &&
        str_contains($owner,"options.confirm(button)") &&
        str_contains($owner,"status==='conflict'") &&
        str_contains($owner,"reason:'reload_required'") &&
        !str_contains($owner,"form.submit()") &&
        !str_contains($owner,"method:'GET'"),
        'confirmed Poultry DELETE AJAX excludes native submit, refresh and fallback'
    );
    b12_delete_owner_check(
        str_contains($owner,"'form[data-confirm]'") &&
        str_contains($owner,"'form[data-b12-revision-delete=\"ruminant\"]'") &&
        str_contains($owner,'ReneeDailyRecordRevisionRuminantDelete.createBindings(') &&
        str_contains($rumBinder,"form.hasAttribute('data-confirm')") &&
        str_contains($rumBinder,"event.preventDefault();"),
        'Ruminant future AJAX DELETE must replace legacy native confirmation'
    );
    b12_delete_owner_check(
        str_contains($transport,'data-b12-delete-tokens') &&
        str_contains($transport,"const endpoint = '../api/delete_record.php';") &&
        str_contains($transport,"data.code==='record_conflict'") &&
        str_contains($transport,"createRuminantFormSender"),
        'original revision tokens, stable endpoint and server 409 required'
    );
    b12_delete_owner_check(
        str_contains($http,"'code' => 'record_conflict'") &&
        str_contains($http,"'status' => 409") &&
        str_contains($http,'function daily_record_revision_delete_require_ajax('),
        'Ruminant privileged and delegated routes expose stable JSON conflict'
    );

    foreach([
        'poultry/layers_daily_record.php'=>['layer','layers-daily-record.js'],
        'poultry/broiler_daily_record.php'=>['broiler','broiler-daily-record.js'],
    ] as $path=>[$type,$legacy]) {
        $html=b12_delete_owner_read($root,$path);
        b12_delete_owner_check(
            str_contains($html,'data-poultry-daily-delete-type="'.$type.'"') &&
            str_contains($html,'data-b12-delete-tokens=') &&
            str_contains($html,'daily_record_revision_cutover_enabled()') &&
            str_contains($html,$legacy) &&
            !str_contains($html,'daily-record-revision-page-delete.js'),
            $type.' original-row future DELETE tokens guarded and page still legacy'
        );
    }
    $rum=b12_delete_owner_read($root,'ruminant/ruminant_daily_record.php');
    b12_delete_owner_check(
        str_contains($rum,'data-confirm="Delete this record?') &&
        str_contains($rum,"daily_record_revision_delete_form_fields('ruminant',") &&
        !str_contains($rum,'daily-record-revision-page-delete.js') &&
        !str_contains($rum,'data-b12-revision-delete='),
        'Ruminant existing form still legacy while future binder unloaded'
    );
    $runner=b12_delete_owner_read($root,'scripts/verify_k2p03_b12_offline.sh');
    b12_delete_owner_check(
        str_contains($runner,'k2p03_b12_page_delete_ownership.test.cjs') &&
        str_contains($runner,'k2p03_b12_page_delete_owner_source.php'),
        'focused source and fake browser DELETE tests are in offline runner'
    );
    echo "B12_PAGE_DELETE_OWNER_SOURCE=PASS\nPASS_COUNT=$pass\n";
} catch(Throwable $e) {
    fwrite(STDERR,"B12_PAGE_DELETE_OWNER_SOURCE=FAIL ".$e->getMessage().PHP_EOL);
    exit(1);
}
