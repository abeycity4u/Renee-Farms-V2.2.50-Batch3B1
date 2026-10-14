<?php
declare(strict_types=1);
/**
 * B12 staged/unloaded thin Daily Record page owner source contract.
 * No bootstrap, DB access, shell, HTTP, migration or deployment.
 */
$root=dirname(__DIR__,2);
$checks=0;
function b12_adapters_check(bool $pass,string $name):void {
    global $checks;
    if(!$pass)throw new RuntimeException('FAILED '.$name);
    ++$checks;
}
function b12_adapters_file(string $root,string $name):string {
    $v=file_get_contents($root.'/'.$name);
    if(!is_string($v))throw new RuntimeException('Missing '.$name);
    return $v;
}
try {
    $gate=b12_adapters_file($root,'lib/daily_record_revision_cutover_gate.php');
    b12_adapters_check(
        preg_match('~function daily_record_revision_cutover_enabled\(\): bool\s*\{\s*return false;\s*\}~',$gate)===1,
        'B12 server cutover literal false'
    );
    $common=b12_adapters_file($root,'assets/js/daily-record-revision-page-adapters.js');
    $life=b12_adapters_file($root,'assets/js/daily-record-revision-page-lifecycle.js');
    $workspace=b12_adapters_file($root,'assets/js/daily-record-revision-workspace.js');
    $editor=b12_adapters_file($root,'assets/js/daily-record-revision-editor.js');
    b12_adapters_check(
        str_contains($common,'ReneeDailyRecordRevisionPageLifecycle') &&
        str_contains($common,'function createAdapter(type, options)') &&
        str_contains($common,'function ownership()') &&
        str_contains($common,'function bind()'),
        'one shared lifecycle / handler ownership policy'
    );
    b12_adapters_check(
        str_contains($common,'options.pageMarker.dataset.b12RevisionPage!==type') &&
        str_contains($common,'options.legacyHandlersRetired!==true') &&
        str_contains($common,"options.deleteOwnership!=='replacement'") &&
        str_contains($common,"'(data-record-form)'")===false &&
        str_contains($common,"form.getAttribute('data-record-form')"),
        'no partial handler ownership or competing generic record handler'
    );
    b12_adapters_check(
        str_contains($common,"script.getAttribute('src')") &&
        str_contains($common,'retiredScript.test(') &&
        str_contains($common,"'form[data-confirm]'") &&
        str_contains($common,"'[data-poultry-daily-delete-id]'") &&
        str_contains($common,'button.dataset.b12DeleteTokens'),
        'legacy page and DELETE takeover preflight prevents collisions'
    );
    b12_adapters_check(
        str_contains($common,'form.reset()') &&
        str_contains($common,'options.confirmReplace(selection)') &&
        str_contains($common,'switchGeneration') &&
        str_contains($common,'return lifecycle.select();'),
        'only authorized explicit selection clears an old draft'
    );
    b12_adapters_check(
        str_contains($common,'options.display.render(') &&
        str_contains($common,'options.display.clear()') &&
        str_contains($common,'save.disabled=true') &&
        str_contains($common,'save.disabled=false'),
        'shared UI population rendering and disabled-submit state'
    );
    b12_adapters_check(
        str_contains($life,'canSubmit:() => populationReady === true') &&
        str_contains($workspace,'canSubmit:options.canSubmit') &&
        str_contains($editor,"reason:'population_pending'") &&
        str_contains($editor,'event.preventDefault();'),
        'POST cannot bypass pending population check by keyboard or script'
    );
    foreach([
        'layer'=>['poultry/layers_daily_record.php','layers-daily-record.js','Layer'],
        'broiler'=>['poultry/broiler_daily_record.php','broiler-daily-record.js','Broiler'],
        'ruminant'=>['ruminant/ruminant_daily_record.php','ruminant-daily-record.js','Ruminant'],
    ] as $type=>[$pagePath,$legacyScript,$label]) {
        $page=b12_adapters_file($root,$pagePath);
        $thin='assets/js/daily-record-revision-'.$type.'-page.js';
        $wrapper=b12_adapters_file($root,$thin);
        b12_adapters_check(
            str_contains($wrapper,"shared.createAdapter('$type', options)") &&
            str_contains($wrapper,'function create(options)') &&
            !str_contains($wrapper,'fetch(') &&
            !str_contains($wrapper,'addEventListener('),
            $type.' single thin page adapter has no duplicated fetching/listeners'
        );
        b12_adapters_check(
            str_contains($page,"'/assets/js/".$legacyScript."'") &&
            !str_contains($page,basename($thin)) &&
            !str_contains($page,'daily-record-revision-page-adapters.js') &&
            !str_contains($page,'daily-record-revision-page-lifecycle.js') &&
            !str_contains($page,'data-b12-revision-page='),
            $type.' still runs original controller, new page owner unloaded'
        );
    }
    echo "B12_THIN_PAGE_ADAPTERS_SOURCE=PASS\nPASS_COUNT=$checks\n";
} catch(Throwable $e){
    fwrite(STDERR,'B12_THIN_PAGE_ADAPTERS_SOURCE=FAIL '.$e->getMessage().PHP_EOL);
    exit(1);
}
