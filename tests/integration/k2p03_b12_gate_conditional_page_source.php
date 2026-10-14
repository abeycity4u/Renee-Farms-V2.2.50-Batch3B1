<?php
declare(strict_types=1);

/**
 * WS08B source-only future rendering contract.
 * Reads source strings; no app bootstrap, PHP page execution, HTTP/DB,
 * migrations, native browser, or cutover activation.
 */
$root=dirname(__DIR__,2);
$passed=0;
function ws08b_check(bool $ok,string $label):void {
    global $passed;
    if (!$ok) throw new RuntimeException('FAIL '.$label);
    $passed++;
}
function ws08b_read(string $path):string {
    global $root;
    $source=file_get_contents($root.'/'.$path);
    if (!is_string($source)) throw new RuntimeException('Missing '.$path);
    return $source;
}
try {
    $gate=ws08b_read('lib/daily_record_revision_cutover_gate.php');
    ws08b_check(
        preg_match('~function\s+daily_record_revision_cutover_enabled\s*\(\s*\)\s*:\s*bool\s*\{\s*return\s+false\s*;\s*\}~',$gate)===1,
        'cutover fuse remains literal false'
    );
    // Execute only the hard-OFF branch of the pure presentation helper.
    // No HTML page, PDO, authenticated request or future ON path is executed.
    require_once $root.'/lib/daily_record_revision_page_switch.php';
    foreach (['layer','broiler','ruminant'] as $species) {
        ws08b_check(
            daily_record_revision_page_future_markers($species,17)==='' &&
            daily_record_revision_page_future_script_tags($species,17)==='' &&
            daily_record_revision_page_future_script_tags($species,0)==='',
            $species.' exact-cycle and all-cycle rendering stay inert with gate OFF'
        );
    }
    ws08b_check(
        daily_record_revision_page_ruminant_delete_marker()==='',
        'gate OFF Ruminant DELETE never receives AJAX ownership marker'
    );
    try {
        daily_record_revision_page_future_markers('other',17);
        throw new RuntimeException('Unknown species accepted.');
    } catch(InvalidArgumentException $expected) {
        ws08b_check(true,'closed page species allowlist is enforced');
    }
    $helper=ws08b_read('lib/daily_record_revision_page_switch.php');
    ws08b_check(
        str_contains($helper,"require_once __DIR__ . '/daily_record_revision_page_asset_plan.php';") &&
        str_contains($helper,'daily_record_revision_page_asset_plan($type)') &&
        !str_contains($helper,'$_POST') &&
        !str_contains($helper,'$_GET') &&
        !str_contains($helper,'->prepare('),
        'future markup uses shared allowlisted assets; no request or DB authority'
    );
    ws08b_check(
        str_contains($helper,'daily_record_revision_page_future_markers(') &&
        str_contains($helper,'daily_record_revision_page_future_script_tags(') &&
        str_contains($helper,'daily_record_revision_page_ruminant_delete_marker(') &&
        str_contains($helper,'!daily_record_revision_cutover_enabled() || $cycleId <= 0') &&
        str_contains($helper,'if (!daily_record_revision_cutover_enabled()) {') &&
        str_contains($helper,'daily-record-revision-readonly-nav.js') &&
        str_contains($helper,'daily_record_revision_cutover_enabled()') &&
        str_contains($helper,'? \' data-b12-revision-delete="ruminant"\' : \'\''),
        'shared marker and asset rendering only behind explicit hard-off gate'
    );
    ws08b_check(
        str_contains($helper,'data-b12-revision-page=') &&
        str_contains($helper,'data-b12-legacy-retired="1"') &&
        str_contains($helper,'data-b12-delete-ownership="replacement"') &&
        str_contains($helper,'htmlspecialchars(') &&
        str_contains($helper,'versioned_asset('),
        'future marker/script output uses expected ownership flags and escaped assets'
    );
    ws08b_check(
        str_contains($helper,'window.ReneeDailyRecordRevisionPageBootstrap') &&
        str_contains($helper,"'document:document,'") &&
        str_contains($helper,"'fetchImpl:window.fetch.bind(window),'") &&
        str_contains($helper,"'AppConfirm:window.AppConfirm,'") &&
        str_contains($helper,"'AppNotify:window.AppNotify,'") &&
        str_contains($helper,"'populationUI:window.DailyPopulationMovementUI,'") &&
        str_contains($helper,"'location:window.location'") &&
        str_contains($helper,"'}).bind();'") &&
        !str_contains($helper,'data-confirm="') &&
        !str_contains($helper,'onload='),
        'future one-time browser bootstrap reuses existing notifications, confirm and population UI'
    );
    $types=[
        'layer'=>['poultry/layers_daily_record.php','layers-daily-record.js','layerDailyConfig'],
        'broiler'=>['poultry/broiler_daily_record.php','broiler-daily-record.js','broilerDailyConfig'],
        'ruminant'=>['ruminant/ruminant_daily_record.php','ruminant-daily-record.js','ruminantDailyConfig'],
    ];
    foreach($types as $type=>[$path,$legacy,$config]) {
        $page=ws08b_read($path);
        ws08b_check(
            str_contains($page,'id="'.$config.'"') &&
            str_contains($page,"daily_record_revision_page_future_markers('".$type."', (int)\$selectedCycleId)") &&
            str_contains($page,'daily_record_revision_page_switch.php'),
            $type.' config acquires only the shared gated B12 markers'
        );
        $site=strpos($page,"daily_record_revision_page_future_script_tags('".$type."', (int)\$selectedCycleId)");
        $guard=strrpos(substr($page,0,$site===false?0:$site),'if (daily_record_revision_cutover_enabled()):');
        $else=$site===false?false:strpos($page,'else:', $site);
        $legacyAsset="versioned_asset('/assets/js/".$legacy."')";
        $legacyPos=$else===false?false:strpos($page,$legacyAsset,$else);
        $endif=$legacyPos===false?false:strpos($page,'<?php endif; ?>',$legacyPos);
        ws08b_check(
            $site!==false && $guard!==false && $else!==false &&
            $legacyPos!==false && $endif!==false &&
            $guard<$site && $site<$else && $else<$legacyPos &&
            $legacyPos<$endif,
            $type.' B12 scripts and old controller are mutually exclusive PHP branches'
        );
        ws08b_check(
            str_contains($page,"daily_record_revision_pending_form_controls('".$type."')") &&
            str_contains($page,'data-check-existing-record required') &&
            str_contains($page,'data-open-record-modal') &&
            str_contains($page,'daily-population-movement-ui.js') &&
            str_contains($page,'/assets/js/main.js'),
            $type.' edit tokens, existing control identities and common UI are intact'
        );
        ws08b_check(
            !str_contains($page,'daily-record-revision-page-bootstrap.js') &&
            !str_contains($page,'data-b12-revision-page=') &&
            !str_contains($page,'data-b12-legacy-retired=') &&
            !str_contains($page,'data-b12-delete-ownership='),
            $type.' active PHP template does not unconditionally emit B12 browser ownership'
        );
    }
    foreach([
        ['layer','poultry/layers_daily_record.php'],
        ['broiler','poultry/broiler_daily_record.php'],
    ] as [$type,$path]) {
        $page=ws08b_read($path);
        ws08b_check(
            str_contains($page,'data-poultry-daily-delete-type="'.$type.'"') &&
            str_contains($page,'if (daily_record_revision_cutover_enabled()):') &&
            str_contains($page,"daily_record_revision_delete_form_fields('".$type."', \$tenantFarmId, \$record)") &&
            str_contains($page,'data-b12-delete-tokens='),
            $type.' original-row revision DELETE is guarded and persisted-row sourced'
        );
    }
    $r=ws08b_read('ruminant/ruminant_daily_record.php');
    $formStart=strpos($r,'<form method="POST" class="d-inline" <?php if (daily_record_revision_cutover_enabled()): ?>');
    $legacy=strpos($r,'data-confirm="Delete this record? This action cannot be undone."',$formStart===false?0:$formStart);
    $marker=strpos($r,'daily_record_revision_page_ruminant_delete_marker()',$formStart===false?0:$formStart);
    $else=$formStart===false?false:strpos($r,'<?php else: ?>',$formStart);
    $end=$formStart===false?false:strpos($r,'<?php endif; ?>',$formStart);
    ws08b_check(
        $formStart!==false && $marker!==false && $else!==false &&
        $legacy!==false && $end!==false && $marker<$else &&
        $else<$legacy && $legacy<$end,
        'Ruminant AJAX DELETE future marker and legacy native confirm are exclusive'
    );
    ws08b_check(
        str_contains($r,"daily_record_revision_delete_form_fields('ruminant', \$tenantFarmId, \$record)") &&
        str_contains($r,"if (\$b12Name === 'record_id') continue;") &&
        !str_contains($r,'data-b12-revision-delete='),
        'Ruminant tokens retain original source and a single record_id input'
    );
    echo "B12_WS08B_PAGE_SOURCE=PASS\nPASS_COUNT={$passed}\n";
} catch(Throwable $e) {
    fwrite(STDERR,"B12_WS08B_PAGE_SOURCE=FAIL ".$e->getMessage()."\n");
    exit(1);
}
