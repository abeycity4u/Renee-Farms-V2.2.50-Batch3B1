<?php
declare(strict_types=1);

/**
 * B12 future page handler ownership audit. No app bootstrap, HTTP, database,
 * shell, migrations, edits, network or deployment. Fail closed if a page is
 * partially wired while the coordinated B12 cutover remains hard OFF.
 */
$root=dirname(__DIR__,2);
$passes=0;
function b12_page_check(bool $valid,string $why):void {
    global $passes;
    if(!$valid) throw new RuntimeException('FAIL: '.$why);
    ++$passes;
}
function b12_page_read(string $root,string $path):string {
    $data=file_get_contents($root.'/'.$path);
    if(!is_string($data)) throw new RuntimeException('Cannot inspect '.$path);
    return $data;
}
try {
    $gate=b12_page_read($root,'lib/daily_record_revision_cutover_gate.php');
    b12_page_check(
        preg_match('~function\s+daily_record_revision_cutover_enabled\s*\(\s*\)\s*:\s*bool\s*\{\s*return\s+false\s*;\s*\}~',$gate)===1,
        'B12 cutover must be literal hard-OFF'
    );

    $contract=b12_page_read($root,'docs/C137_B12_PAGE_HANDLER_CUTOVER_CONTRACT.md');
    b12_page_check(
        str_contains($contract,'SOURCE ONLY')&&
        str_contains($contract,'NOT IMPLEMENTED')&&
        str_contains($contract,'MariaDB')&&
        str_contains($contract,'all seven')&&
        str_contains($contract,'data-confirm'),
        'page handoff records coordination and outstanding certification'
    );
    $pages=[
        'layer'=>['poultry/layers_daily_record.php','assets/js/layers-daily-record.js'],
        'broiler'=>['poultry/broiler_daily_record.php','assets/js/broiler-daily-record.js'],
        'ruminant'=>['ruminant/ruminant_daily_record.php','assets/js/ruminant-daily-record.js'],
    ];
    $futureModules=[
        'daily-record-revision-modal.js',
        'daily-record-revision-transport.js',
        'daily-record-revision-editor.js',
        'daily-record-revision-submit.js',
        'daily-record-revision-workspace.js',
        'daily-record-revision-delete.js',
        'daily-record-revision-ruminant-delete.js',
    ];
    foreach($pages as $type=>[$pagePath,$scriptPath]) {
        $page=b12_page_read($root,$pagePath);
        $script=b12_page_read($root,$scriptPath);

        b12_page_check(
            preg_match('~versioned_asset\(\x27/assets/js/'.preg_quote(basename($scriptPath),'~').'\x27\)~',$page)===1 &&
            str_contains($script,'function openRecordModal(') &&
            str_contains($script,'function checkExistingRecord('),
            $type.' retains the active legacy controller while gate is OFF'
        );

        $notLoaded=true;
        foreach($futureModules as $module) {
            if(preg_match('~<script\b[^>]*src\s*=\s*["\x27][^"\x27>]*'.preg_quote($module,'~').'~i',$page)===1) {
                $notLoaded=false;break;
            }
        }
        b12_page_check($notLoaded,$type.' has no active B12 script tag');

        b12_page_check(
            preg_match('~<form\b[^>]*\bid\s*=\s*["\x27]recordForm["\x27]~i',$page)===1 &&
            str_contains($page,'id="selectedDate"') &&
            str_contains($page,'id="recordDate"') &&
            str_contains($page,'daily_record_revision_pending_form_controls('),
            $type.' form and inert revision hidden controls remain intact'
        );
        b12_page_check(
            str_contains($script,'attachEditModal(') &&
            str_contains($script,'get_record.php') &&
            str_contains($script,'get_previous_stock.php') &&
            str_contains($script,'checkExistingRecord()'),
            $type.' still owns legacy edit and population reads (must be retired together)'
        );
        b12_page_check(
            str_contains($page,'daily-population-movement-ui.js') &&
            str_contains($page,'edit-modal.js') &&
            str_contains($page,'/assets/js/main.js') &&
            str_contains($page,'data-selected-cycle-id='),
            $type.' retains the shared legacy browser dependencies'
        );
    }

    $poultry=[
        ['layer','poultry/layers_daily_record.php','assets/js/layers-daily-record.js'],
        ['broiler','poultry/broiler_daily_record.php','assets/js/broiler-daily-record.js'],
    ];
    foreach($poultry as [$type,$pagePath,$scriptPath]) {
        $page=b12_page_read($root,$pagePath);
        $script=b12_page_read($root,$scriptPath);
        b12_page_check(
            str_contains($page,'data-poultry-daily-delete-type="'.$type.'"') &&
            str_contains($page,'data-b12-delete-tokens=') &&
            str_contains($page,'if (daily_record_revision_cutover_enabled()):') &&
            str_contains($script,'delete'.ucfirst($type).'DailyRecord') &&
            str_contains($script,'api/delete_record.php'),
            $type.' original-row future tokens coexist with still-legacy delete implementation'
        );
    }

    $rumPage=b12_page_read($root,'ruminant/ruminant_daily_record.php');
    $confirm=b12_page_read($root,'assets/js/confirmations.js');
    $binder=b12_page_read($root,'assets/js/daily-record-revision-ruminant-delete.js');
    $browserDelete=b12_page_read($root,'assets/js/daily-record-revision-delete.js');
    b12_page_check(
        str_contains($rumPage,'data-confirm="Delete this record?') &&
        str_contains($rumPage,"daily_record_revision_delete_form_fields('ruminant',") &&
        !str_contains($rumPage,'data-b12-revision-delete=') &&
        !str_contains($rumPage,'daily-record-revision-ruminant-delete.js'),
        'Ruminant DELETE remains native, with guarded tokens and no B12 binding'
    );
    b12_page_check(
        str_contains($confirm,"form[data-confirm]") &&
        str_contains($confirm,'form.submit()') &&
        str_contains($binder,"form.hasAttribute('data-confirm')") &&
        str_contains($binder,"form.dataset.b12RevisionDelete !== 'ruminant'"),
        'global native-submit capture handler is incompatible with B12 binder (fail closed)'
    );

    $editor=b12_page_read($root,'assets/js/daily-record-revision-editor.js');
    $workspace=b12_page_read($root,'assets/js/daily-record-revision-workspace.js');
    b12_page_check(
        str_contains($editor,'event.preventDefault();') &&
        str_contains($editor,"reason:'submit_transport_missing'") &&
        str_contains($workspace,"typeof value !== 'string'") &&
        str_contains($workspace,'onBlocked') &&
        str_contains($workspace,'onSaved'),
        'future edit modal rejects native POST, zero/wildcard cycle and missing UI callbacks'
    );
    b12_page_check(
        str_contains($browserDelete,'createRuminantFormSender') &&
        str_contains($browserDelete,"data.set('delete_record','1')") &&
        str_contains($browserDelete,"response.status === 409") &&
        str_contains($binder,"event.preventDefault();"),
        'future Ruminant DELETE form is exclusively AJAX and 409-aware'
    );

    $generic=b12_page_read($root,'assets/js/main.js');
    b12_page_check(
        str_contains($generic,'form[data-record-form="true"]') &&
        !str_contains($rumPage,'data-record-form="true"'),
        'future page switch must avoid shared duplicate generic record submit'
    );
    echo "B12_PAGE_SWITCH_CONTRACT=PASS\nPASS_COUNT=$passes\n";
} catch (Throwable $e) {
    fwrite(STDERR,"B12_PAGE_SWITCH_CONTRACT=FAIL ".$e->getMessage().PHP_EOL);
    exit(1);
}
