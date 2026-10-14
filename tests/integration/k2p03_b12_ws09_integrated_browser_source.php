<?php
declare(strict_types=1);
/**
 * WS09 integrated browser/source audit only.
 * Does not evaluate gate-ON pages, connect HTTP/DB or run migrations.
 */
$root=dirname(__DIR__,2);
$n=0;
function ws09(bool $ok,string $why):void {
    global $n;
    if(!$ok)throw new RuntimeException('FAIL: '.$why);
    ++$n;
}
function ws09_file(string $file):string {
    global $root;
    $source=file_get_contents($root.'/'.$file);
    if(!is_string($source))throw new RuntimeException('Missing '.$file);
    return $source;
}
try {
    $gate=ws09_file('lib/daily_record_revision_cutover_gate.php');
    ws09(preg_match('~function\s+daily_record_revision_cutover_enabled\s*\(\s*\)\s*:\s*bool\s*\{\s*return\s+false\s*;\s*\}~',$gate)===1,
        'literal hard-OFF cutover');
    require_once $root.'/lib/daily_record_revision_page_switch.php';
    foreach(['layer','broiler','ruminant'] as $type) {
        ws09(daily_record_revision_page_future_script_tags($type,0)==='' &&
            daily_record_revision_page_future_script_tags($type,11)==='' &&
            daily_record_revision_page_future_markers($type,0)==='' &&
            daily_record_revision_page_future_markers($type,11)==='',
            $type.' OFF branch never loads future owners');
    }
    ws09(daily_record_revision_page_ruminant_delete_marker()==='',
        'Ruminant native-delete confirmation remains unchanged when OFF');

    $switch=ws09_file('lib/daily_record_revision_page_switch.php');
    ws09(str_contains($switch,"if (!daily_record_revision_cutover_enabled()) {") &&
        str_contains($switch,'if ($cycleId <= 0) {') &&
        str_contains($switch,'daily-record-revision-readonly-nav.js') &&
        str_contains($switch,'ReneeDailyRecordRevisionReadOnlyNav') &&
        str_contains($switch,'ReneeDailyRecordRevisionPageBootstrap'),
        'future exact-cycle page and read-only all-cycles have distinct browser owners');
    ws09(str_contains($switch,'daily_record_revision_page_asset_plan($type)') &&
        str_contains($switch,'versioned_asset(') &&
        str_contains($switch,'htmlspecialchars(') &&
        !str_contains($switch,'$_POST') &&
        !str_contains($switch,'->prepare('),
        'closed allowlist and escaped assets with no permission or SQL decisions');

    foreach([
        'layer'=>['poultry/layers_daily_record.php','$canDelete','layers-daily-record.js'],
        'broiler'=>['poultry/broiler_daily_record.php','$canDelete','broiler-daily-record.js'],
        'ruminant'=>['ruminant/ruminant_daily_record.php','$canDeleteRecords','ruminant-daily-record.js']
    ] as $type=>[$path,$permission,$legacy]){
        $page=ws09_file($path);
        ws09(str_contains($page,"daily_record_revision_page_future_script_tags('".$type."', (int)\$selectedCycleId)") &&
            str_contains($page,"versioned_asset('/assets/js/".$legacy."')") &&
            preg_match('~if\s*\(daily_record_revision_cutover_enabled\(\)\):\s*echo daily_record_revision_page_future_script_tags\(~',$page)===1,
            $type.' future browser scripts and legacy scripts remain mutually exclusive');
        ws09(str_contains($page,
            'if ('.$permission.' && (!daily_record_revision_cutover_enabled() || $selectedCycleId > 0)):'),
            $type.' All Cycles has NO DELETE element when cutover is ON');
        ws09(str_contains($page,"daily_record_revision_pending_form_controls('".$type."')") &&
            str_contains($page,'data-open-record-modal') &&
            str_contains($page,'data-check-existing-record required'),
            $type.' shared original revision form controls remain present');
    }

    $rum=ws09_file('ruminant/ruminant_daily_record.php');
    ws09(str_contains($rum,'daily_record_revision_page_ruminant_delete_marker()') &&
        str_contains($rum,'data-confirm="Delete this record? This action cannot be undone."') &&
        str_contains($rum,"daily_record_revision_delete_form_fields('ruminant', \$tenantFarmId, \$record)"),
        'Ruminant form ownership is separated and tokens predate DELETE');

    $modal=ws09_file('assets/js/daily-record-revision-modal.js');
    $editor=ws09_file('assets/js/daily-record-revision-editor.js');
    $submit=ws09_file('assets/js/daily-record-revision-submit.js');
    $transport=ws09_file('assets/js/daily-record-revision-transport.js');
    ws09(str_contains($modal,"apiParameters(ticket, 'scope')") &&
        str_contains($modal,"apiParameters(ticket, 'exact', expectedId)") &&
        str_contains($modal,'decimal(tokens.expected_revision') &&
        str_contains($modal,"if (scopeData.status === 'absent')") &&
        str_contains($modal,"if (scopeData.status !== 'unique')"),
        'scope GET then exact GET, no ambiguous EDIT to CREATE fallback');
    ws09(str_contains($editor,'event.preventDefault();') &&
        str_contains($editor,"reason: outcome && outcome.status === 'conflict'") &&
        str_contains($editor,'invalidate();') &&
        str_contains($editor,"notify(options.onBlocked, {reason:'submit_not_ready'})"),
        'stale submit response clears authority without native POST');
    ws09(str_contains($submit,"redirect: 'error'") &&
        str_contains($submit,"credentials: 'same-origin'") &&
        str_contains($submit,"response.status === 409") &&
        str_contains($submit,"status:'conflict'") &&
        str_contains($submit,"data.set('save_record', '1')"),
        'shared AJAX save transport sends explicit intent with conflict feedback');
    ws09(str_contains($transport,"redirect: 'error'") &&
        str_contains($transport,"method: 'GET'") &&
        str_contains($transport,'daily_record_revision_read.php'),
        'reader remains same-origin authenticated GET, no redirect fallback');

    $delete=ws09_file('assets/js/daily-record-revision-delete.js');
    $owner=ws09_file('assets/js/daily-record-revision-page-delete.js');
    $rumBinder=ws09_file('assets/js/daily-record-revision-ruminant-delete.js');
    $rumHttp=ws09_file('lib/daily_record_revision_delete_http.php');
    $delegated=ws09_file('includes/permission_runtime.php');
    ws09(str_contains($delete,'button.dataset.b12DeleteTokens') &&
        str_contains($delete,'response.status===409') &&
        str_contains($delete,'createRuminantFormSender') &&
        !str_contains($delete,'daily_record_revision_read.php'),
        'both DELETE transports use original revision, no DELETE-time GET');
    ws09(str_contains($owner,'deletes')===false ||
        str_contains($owner,'function preflight()'),
        'shared DELETE owner requires explicit marker preflight');
    ws09(str_contains($rumBinder,"form.hasAttribute('data-confirm')") &&
        str_contains($rumBinder,'event.preventDefault();') &&
        str_contains($rumHttp,"'code' => 'record_conflict'") &&
        str_contains($delegated,'daily_record_revision_delete_http_send($e);'),
        'delegated Ruminant DELETE refuses native confirm and preserves 409 JSON');
    $nav=ws09_file('assets/js/daily-record-revision-readonly-nav.js');
    ws09(str_contains($nav,"config.dataset.selectedCycleId!=='0'") &&
        str_contains($nav,"monthSelector") &&
        str_contains($nav,"activeCycleSelector") &&
        !str_contains($nav,'fetch(') &&
        !str_contains($nav,'submit('),
        'All Cycles navigation cannot fetch, save or DELETE');
    echo "B12_WS09_INTEGRATED_BROWSER_SOURCE=PASS\nPASS_COUNT={$n}\n";
} catch(Throwable $e) {
    fwrite(STDERR,'B12_WS09_INTEGRATED_BROWSER_SOURCE=FAIL '.
        $e->getMessage()."\n");
    exit(1);
}
