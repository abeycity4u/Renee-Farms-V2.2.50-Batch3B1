<?php
declare(strict_types=1);
/**
 * B12 offline source parity contract. No app bootstrap, SQL, network,
 * browser activation, database query, deployment or mutation.
 */
$root=dirname(__DIR__,2);
$count=0;
function b12_fields_check(bool $pass,string $name):void {
    global $count;
    if(!$pass)throw new RuntimeException('FAILED '.$name);
    ++$count;
}
function b12_fields_get(string $root,string $file):string {
    $value=file_get_contents($root.'/'.$file);
    if(!is_string($value))throw new RuntimeException('Missing '.$file);
    return $value;
}
try {
    $gate=b12_fields_get($root,'lib/daily_record_revision_cutover_gate.php');
    b12_fields_check(
        preg_match('~function daily_record_revision_cutover_enabled\(\): bool\s*\{\s*return false;\s*\}~',$gate)===1,
        'hard OFF revision gate'
    );
    $new=b12_fields_get($root,'assets/js/daily-record-revision-page-fields.js');
    $layer=b12_fields_get($root,'assets/js/layers-daily-record.js');
    $rum=b12_fields_get($root,'assets/js/ruminant-daily-record.js');
    b12_fields_check(
        str_contains($new,'function createFields(options)') &&
        str_contains($new,'function bind()') &&
        !str_contains($new,'fetch(') &&
        !str_contains($new,'XMLHttpRequest') &&
        !str_contains($new,'form.submit('),
        'one inert UI parity helper without network or write'
    );
    b12_fields_check(
        str_contains($layer,"const crates = eggProduction / 30;") &&
        str_contains($new,"(eggs/30).toFixed(2)") &&
        str_contains($layer,'(eggProduction / openingStock) * 100') &&
        str_contains($new,'(eggs/opening)*100') &&
        str_contains($new,'toFixed(2)'),
        'Layer existing 30-egg crates and two-decimal rate preserved'
    );
    b12_fields_check(
        str_contains($rum,'option.dataset.unit') &&
        str_contains($rum,'feed_consumption_unit') &&
        str_contains($new,"'feedConsumptionUnit'") &&
        str_contains($new,'record.feed_consumption_unit') &&
        str_contains($new,"label.textContent='Feed Consumption ('+unit+')'"),
        'Ruminant item unit and persisted-edit unit rules preserved'
    );
    b12_fields_check(
        str_contains($new,'options.form.querySelector') &&
        str_contains($new,"field.addEventListener(event,callback)") &&
        str_contains($new,'if(bound)return;') &&
        !str_contains($new,'get_record.php') &&
        !str_contains($new,'check_record.php') &&
        !str_contains($new,'get_previous_stock.php'),
        'UI helper cannot take over authoritative readers'
    );
    foreach([
        'poultry/layers_daily_record.php',
        'poultry/broiler_daily_record.php',
        'ruminant/ruminant_daily_record.php',
    ] as $page) {
        $html=b12_fields_get($root,$page);
        b12_fields_check(
            !str_contains($html,'daily-record-revision-page-fields.js') &&
            !str_contains($html,'daily-record-revision-page-adapters.js') &&
            !str_contains($html,'data-b12-revision-page='),
            $page.' continues with legacy handler only'
        );
    }
    echo "B12_PAGE_FIELDS_PARITY_SOURCE=PASS\nPASS_COUNT=$count\n";
} catch(Throwable $e) {
    fwrite(STDERR,"B12_PAGE_FIELDS_PARITY_SOURCE=FAIL ".$e->getMessage().PHP_EOL);
    exit(1);
}
