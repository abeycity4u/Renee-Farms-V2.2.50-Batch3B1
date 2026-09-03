<?php
$root = dirname(__DIR__);
$fail = [];
$pass = [];
function check(bool $ok, string $label) {
    global $fail, $pass;
    if ($ok) { $pass[] = $label; } else { $fail[] = $label; }
}
function containsFile(string $path, string $needle): bool {
    return is_file($path) && strpos((string)file_get_contents($path), $needle) !== false;
}

$config = $root . '/config.php';
check(containsFile($config, "getenv('APP_TIMEZONE') ?: 'Africa/Lagos'"), 'APP_TIMEZONE defaults to Africa/Lagos');
check(containsFile($config, 'date_default_timezone_set(APP_TIMEZONE)'), 'PHP business timezone centralized');
check(containsFile($config, "SET time_zone = "), 'MySQL session timezone aligned');
check(containsFile($config, 'function app_today()'), 'Central app_today helper exists');

$head = $root . '/navbar_head.php';
check(containsFile($head, 'window.ReneeCalendar'), 'Browser receives centralized application calendar date');
check(containsFile($head, 'app-today'), 'Application today meta is published');

$main = $root . '/assets/js/main.js';
check(containsFile($main, 'function appToday()'), 'Shared JS appToday helper exists');
check(!containsFile($main, 'input.valueAsDate = new Date()'), 'Date picker default no longer relies on browser Date object assignment');

foreach (['poultry/layers_daily_record.php','poultry/broiler_daily_record.php','ruminant/ruminant_daily_record.php'] as $rel) {
    $path = $root . '/' . $rel;
    check(!containsFile($path, "new Date().toISOString().split('T')[0]"), "$rel does not use UTC ISO today");
    check(containsFile($path, 'window.ReneeCalendar'), "$rel uses centralized calendar today");
}

$migration = $root . '/migrations/027_inventory_default_production_attribution.sql';
check(is_file($migration), 'Migration 027 exists');
check(containsFile($migration, 'default_production_type'), 'Migration 027 adds default production attribution');

$inv = $root . '/inventory.php';
check(containsFile($inv, 'Default Production Attribution'), 'Add Item exposes default production attribution');
check(containsFile($inv, 'Production Attribution'), 'Update Stock exposes per-transaction production attribution');
check(containsFile($inv, 'default_production_type'), 'Inventory backend persists default production attribution');
check(containsFile($inv, '$movementProductionType'), 'Inventory Update Stock computes movement production attribution');
check(containsFile($inv, 'Unit Cost (₦) for Received Stock'), 'Received Stock Unit Cost regression remains fixed');

$service = $root . '/lib/stock_service.php';
check(containsFile($service, '?string $productionTypeOverride = null'), 'Canonical stock service accepts production attribution override');
check(containsFile($service, '$requestedProductionType = $productionTypeOverride'), 'Canonical stock service snapshots requested production attribution');

$financial = $root . '/lib/inventory_financial.php';
check(containsFile($financial, 'inventory_default_production_types'), 'Inventory attribution choices centralized in helper');
check(containsFile($financial, "'layer' => 'Layer'"), 'Poultry Layer attribution supported');
check(containsFile($financial, "'cattle' => 'Cattle'"), 'Ruminant Cattle attribution supported');

$api = $root . '/api/update_stock.php';
check(containsFile($api, 'inventory_normalize_default_production_type'), 'Inventory API also respects production attribution');

$rootNotes = glob($root . '/*NOTES*.txt') ?: [];
sort($rootNotes);
$noteNames = array_map('basename', $rootNotes);
check($noteNames === ['BATCH_NOTES.txt', 'RELEASE_NOTES.txt'], 'Only the two agreed root note files exist');

foreach ($pass as $label) echo "PASS: $label\n";
foreach ($fail as $label) echo "FAIL: $label\n";
if ($fail) { echo "\nFAILED " . count($fail) . " check(s).\n"; exit(1); }
echo "\nALL CHECKS PASSED (" . count($pass) . ").\n";
