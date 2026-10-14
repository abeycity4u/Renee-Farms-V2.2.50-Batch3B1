<?php
declare(strict_types=1);
/**
 * Inert B12 three-page browser initializer source contract.
 * Never bootstrap app, DB, HTTP, migrations, browser, or production.
 */
$root=dirname(__DIR__,2);
$pass=0;
function b12_boot_check(bool $ok,string $name):void {
    global $pass;
    if(!$ok)throw new RuntimeException('FAIL: '.$name);
    ++$pass;
}
function b12_boot_file(string $root,string $file):string {
    $str=file_get_contents($root.'/'.$file);
    if(!is_string($str))throw new RuntimeException('Missing '.$file);
    return $str;
}
try {
    $gate=b12_boot_file($root,'lib/daily_record_revision_cutover_gate.php');
    b12_boot_check(
        preg_match('~function daily_record_revision_cutover_enabled\(\): bool\s*\{\s*return false\s*;\s*\}~',$gate)===1,
        'B12 gate is literally OFF'
    );
    $boot=b12_boot_file($root,'assets/js/daily-record-revision-page-bootstrap.js');
    b12_boot_check(
        str_contains($boot,'function create(type,options)') &&
        str_contains($boot,'ReneeDailyRecordRevisionPageBootstrap') &&
        str_contains($boot,'return Object.freeze({bind,adapter});') &&
        !str_contains($boot,'DOMContentLoaded') &&
        !str_contains($boot,'window.onload') &&
        !str_contains($boot,'document.addEventListener('),
        'browser bootstrap is explicitly created and bound; never auto-starts'
    );
    b12_boot_check(
        str_contains($boot,"ds.b12RevisionPage!==type") &&
        str_contains($boot,"ds.b12LegacyRetired!=='1'") &&
        str_contains($boot,"ds.b12DeleteOwnership!=='replacement'") &&
        str_contains($boot,'Legacy Daily Record controller is still loaded.') &&
        str_contains($boot,"activeDocuments.has(document)"),
        'server-rendered gate ownership, legacy-script and duplicate owner checks'
    );
    b12_boot_check(
        str_contains($boot,'function positive(value)') &&
        str_contains($boot,'maxId') &&
        str_contains($boot,'function validDate(value)') &&
        str_contains($boot,"target!=='0'&&!positive(target)") &&
        str_contains($boot,"if(!positive(cycle)") &&
        str_contains($boot,'ds.selectedCycleAnimalType'),
        'exact positive edit cycle, safe date, explicit NAV-only zero'
    );
    b12_boot_check(
        str_contains($boot,'createRuminantMovementDisplay()') &&
        str_contains($boot,'createSoldStockDisplay({type,cycleId:') &&
        str_contains($boot,'options.AppNotify.success(') &&
        str_contains($boot,'options.AppNotify.error(') &&
        str_contains($boot,'options.AppConfirm.ask(') &&
        str_contains($boot,'options.bootstrap.Modal.getOrCreateInstance('),
        'shared styled confirm/toast, Bootstrap modal and existing population displays'
    );
    b12_boot_check(
        str_contains($boot,'monthPicker.addEventListener(') &&
        str_contains($boot,'cyclePicker.addEventListener(') &&
        str_contains($boot,'options.location.assign(') &&
        str_contains($boot,'descriptor.page+') &&
        !str_contains($boot,'fetch(') &&
        !str_contains($boot,'method:'),
        'navigation uses fixed page routes and never directly writes or fetches'
    );
    b12_boot_check(
        str_contains($boot,'confirmReplace') &&
        str_contains($boot,'confirmDelete') &&
        str_contains($boot,'onDeleteConflict') &&
        str_contains($boot,'onDeleteBlocked') &&
        str_contains($boot,'adapter.bind();') &&
        !str_contains($boot,'form.submit('),
        'shared form and DELETE authority delegated; no native submit fallback'
    );
    foreach([
        'layer'=>['poultry/layers_daily_record.php','layerDailyConfig'],
        'broiler'=>['poultry/broiler_daily_record.php','broilerDailyConfig'],
        'ruminant'=>['ruminant/ruminant_daily_record.php','ruminantDailyConfig'],
    ] as $type=>[$file,$config]) {
        $html=b12_boot_file($root,$file);
        b12_boot_check(
            str_contains($html,'id="'.$config.'"') &&
            !str_contains($html,'daily-record-revision-page-bootstrap.js') &&
            !str_contains($html,'data-b12-revision-page=') &&
            !str_contains($html,'data-b12-legacy-retired=') &&
            !str_contains($html,'data-b12-delete-ownership='),
            $type.' existing page still has NO revision browser initializer/markers'
        );
    }
    $manifest=b12_boot_file($root,'lib/daily_record_revision_page_asset_plan.php');
    b12_boot_check(
        str_contains($manifest,"'daily-record-revision-page-bootstrap.js'") &&
        str_contains($manifest,"$"."shared[] = $"."pages[$"."type]['entry'];") &&
        str_contains($manifest,'if (!daily_record_revision_cutover_enabled())') &&
        str_contains($manifest,'return [];'),
        'manifest declares bootstrap after species factory yet gate-OFF returns no assets'
    );
    echo "B12_PAGE_BOOTSTRAP_SOURCE=PASS\nPASS_COUNT=$pass\n";
} catch(Throwable $e) {
    fwrite(STDERR,"B12_PAGE_BOOTSTRAP_SOURCE=FAIL ".$e->getMessage().PHP_EOL);
    exit(1);
}
