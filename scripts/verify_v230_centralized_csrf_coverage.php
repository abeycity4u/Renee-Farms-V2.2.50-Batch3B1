<?php
$root = dirname(__DIR__);
$checks = [];

$read = static function (string $path) use ($root): string {
    $full = $root . '/' . $path;
    return is_file($full) ? (string)file_get_contents($full) : '';
};

$contains = static function (string $content, string $needle): bool {
    return $content !== '' && strpos($content, $needle) !== false;
};

$add = static function (string $label, bool $pass, string $detail = '') use (&$checks): void {
    $checks[] = [$label, $pass, $detail];
};

$csrf = $read('includes/csrf.php');
$init = $read('init.php');
$permissions = $read('admin/permissions.php');
$permissionsSave = $read('admin/permissions_save.php');
$poultryCycle = $read('management/poultry_cycle.php');
$apiHelpers = $read('api/api_helpers.php');
$inventoryAdd = $read('inventory/add_category.php');
$inventoryDelete = $read('inventory/delete_category.php');
$animalRegistry = $read('ruminant/animal_registry.php');
$productionCycles = $read('management/production_cycles.php');
$users = $read('management/users.php');

$add('Shared CSRF field renderer exists', $contains($csrf, 'function csrf_field'));
$add('Shared browser POST CSRF guard exists', $contains($csrf, 'function require_valid_csrf_post'));
$add('Shared token extraction helper exists', $contains($csrf, 'function csrf_request_token'));
$add('Shared token validation helper exists', $contains($csrf, 'function csrf_request_is_valid'));
$add('CSRF helpers load from application bootstrap', $contains($init, "require_once __DIR__ . '/includes/csrf.php';"));

$add('Module Permissions form uses centralized CSRF field', $contains($permissions, '<?= csrf_field() ?>'));
$add('Module Permissions save uses centralized POST guard', $contains($permissionsSave, 'require_valid_csrf_post();'));
$add('Production-entry basis approval uses centralized POST guard', $contains($poultryCycle, 'require_valid_csrf_post();'));
$add('API helper delegates token validation to shared CSRF helper', $contains($apiHelpers, 'csrf_request_is_valid()'));
$add('Inventory Add Category uses centralized POST guard', $contains($inventoryAdd, 'require_valid_csrf_post();'));
$add('Inventory Delete Category uses centralized POST guard', $contains($inventoryDelete, 'require_valid_csrf_post();'));
$add('Ruminant Animal Registry uses centralized POST guard', $contains($animalRegistry, 'require_valid_csrf_post();'));

$productionCyclesLegacy = $contains($productionCycles, "verify_csrf_token(\$_POST['csrf_token'] ?? '')");
$usersLegacy = $contains($users, "verify_csrf_token(\$_POST['csrf_token'] ?? '')");
$add('Production Cycles remains CSRF-protected while awaiting guard migration', $productionCyclesLegacy || $contains($productionCycles, 'require_valid_csrf_post();'), $productionCyclesLegacy ? 'protected via legacy inline verifier' : 'centralized guard');
$add('Team Users remains CSRF-protected while awaiting guard migration', $usersLegacy || $contains($users, 'require_valid_csrf_post();'), $usersLegacy ? 'protected via legacy inline verifier' : 'centralized guard');

$failed = 0;
foreach ($checks as [$label, $pass, $detail]) {
    echo ($pass ? '[PASS] ' : '[FAIL] ') . $label;
    if ($detail !== '') echo ' - ' . $detail;
    echo PHP_EOL;
    if (!$pass) $failed++;
}

echo PHP_EOL . count($checks) . ' checks, ' . $failed . ' failure(s).' . PHP_EOL;
exit($failed === 0 ? 0 : 1);
