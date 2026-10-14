<?php
declare(strict_types=1);

/**
 * B12 source-only Poultry DELETE browser contract. No app bootstrap, HTTP,
 * DB, deployment, migration or CI. Ruminant uses the existing guarded
 * server-rendered DELETE form, NOT this Poultry endpoint.
 */
$root = dirname(__DIR__, 2);
$n = 0;
function b12_browser_delete_check(bool $ok, string $message): void {
    global $n;
    if (!$ok) throw new RuntimeException('FAILED ' . $message);
    ++$n;
}
function b12_browser_delete_source(string $root, string $path): string {
    $value = file_get_contents($root . '/' . $path);
    if (!is_string($value)) throw new RuntimeException('Unavailable source ' . $path);
    return $value;
}
try {
    $gate = b12_browser_delete_source($root,'lib/daily_record_revision_cutover_gate.php');
    b12_browser_delete_check(
        preg_match('~function daily_record_revision_cutover_enabled\(\): bool\s*\{\s*return false;\s*\}~', $gate) === 1,
        'cutover still literal hard OFF'
    );
    foreach (['layer'=>'poultry/layers_daily_record.php',
              'broiler'=>'poultry/broiler_daily_record.php'] as $type=>$path) {
        $page = b12_browser_delete_source($root,$path);
        // Button attributes may put the title BEFORE or AFTER the data
        // attributes (Broiler and Layer differ). Scope the check to the
        // actual delete button, not a brittle title-ordering assumption.
        $needle = 'data-poultry-daily-delete-id=';
        $idIndex = strpos($page, $needle);
        $buttonStart = $idIndex === false ? false
            : strrpos(substr($page, 0, $idIndex), '<button');
        $buttonEnd = $idIndex === false ? false
            : strpos($page, '</button>', $idIndex);
        $button = $buttonStart !== false && $buttonEnd !== false
            ? substr($page, $buttonStart, $buttonEnd - $buttonStart)
            : '';
        $idInButton = strpos($button, $needle);
        $gateInButton = strpos($button, 'if (daily_record_revision_cutover_enabled()):');
        $tokenInButton = strpos($button, 'data-b12-delete-tokens=');
        $endifInButton = $tokenInButton === false ? false
            : strpos($button, '<?php endif; ?>', $tokenInButton);
        b12_browser_delete_check(
            $button !== '' &&
            str_contains($button, 'data-poultry-daily-delete-type="' . $type . '"') &&
            str_contains($button, 'title="Delete Record"') &&
            substr_count($button, $needle) === 1 &&
            substr_count($button, 'data-b12-delete-tokens=') === 1 &&
            $idInButton !== false &&
            $gateInButton !== false && $tokenInButton !== false &&
            $endifInButton !== false &&
            $idInButton < $gateInButton &&
            $gateInButton < $tokenInButton &&
            $tokenInButton < $endifInButton,
            $type . ' token attribute exists only within button hard-off guard'
        );
        b12_browser_delete_check(
            str_contains($page,"daily_record_revision_delete_form_fields('".$type."', \$tenantFarmId, \$record)") &&
            str_contains($page,'json_encode($b12DeleteTokens, JSON_THROW_ON_ERROR)') &&
            str_contains($page,"htmlspecialchars(json_encode(") &&
            !str_contains($page,"data-b12-delete-tokens=\"<?php echo \$_GET") &&
            !str_contains($page,"data-b12-delete-tokens=\"<?php echo \$_POST"),
            $type . ' tokens generated from originally loaded, farm-scoped row'
        );
        b12_browser_delete_check(
            !str_contains($page,'daily-record-revision-delete.js'),
            $type . ' new transport remains NOT loaded by live page'
        );
    }
    $ruminant=b12_browser_delete_source($root,'ruminant/ruminant_daily_record.php');
    b12_browser_delete_check(
        str_contains($ruminant,"daily_record_revision_delete_form_fields('ruminant', \$tenantFarmId, \$record)") &&
        str_contains($ruminant,'if ($b12Name === \'record_id\') continue;') &&
        !str_contains($ruminant,'daily-record-revision-delete.js'),
        'Ruminant original server-rendered tokens remain separate and guarded'
    );
    $transport=b12_browser_delete_source($root,'assets/js/daily-record-revision-delete.js');
    b12_browser_delete_check(
        str_contains($transport,"'../api/delete_record.php'") &&
        str_contains($transport,"redirect:'error'") &&
        str_contains($transport,"credentials:'same-origin'") &&
        str_contains($transport,"raw.write_intent !== 'delete'") &&
        str_contains($transport,"response.status===409") &&
        str_contains($transport,"pending.has(key)") &&
        !str_contains($transport,'get_record.php') &&
        !str_contains($transport,'daily_record_revision_read.php'),
        'shared AJAX delete cannot retrieve or manufacture original edit tokens'
    );
    $api=b12_browser_delete_source($root,'api/delete_record.php');
    b12_browser_delete_check(
        str_contains($api,"require_http_method('POST')") &&
        str_contains($api,'require_csrf_token();') &&
        str_contains($api,'daily_record_revision_delete_transaction(') &&
        str_contains($api,"'record_id'=>\$deleted['record_id']") &&
        str_contains($api,"'code'=>'record_conflict'"),
        'Poultry cutover endpoint validates original tokens and returns 409'
    );
    echo "B12_DELETE_BROWSER_SOURCE=PASS\nPASS_COUNT={$n}\n";
} catch (Throwable $e) {
    fwrite(STDERR,'B12_DELETE_BROWSER_SOURCE=FAIL ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
