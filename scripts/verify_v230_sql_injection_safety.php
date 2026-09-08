<?php
/**
 * V2.3 SQL injection / unsafe dynamic-query structural verifier.
 *
 * This verifier intentionally focuses on high-risk contracts identified during
 * the security audit: request data must remain parameterized, dynamic table
 * names must come from fixed allowlists, and dynamic LIMIT / IN lists must be
 * integer-clamped or placeholder-generated.
 */

$root = dirname(__DIR__);
$checks = 0;
$failures = 0;

function check_contract(bool $ok, string $label): void
{
    global $checks, $failures;
    $checks++;
    if ($ok) {
        echo "PASS: {$label}\n";
    } else {
        $failures++;
        echo "FAIL: {$label}\n";
    }
}

function source(string $relative): string
{
    global $root;
    $path = $root . '/' . $relative;
    return is_file($path) ? (string)file_get_contents($path) : '';
}

$previous = source('api/get_previous_stock.php');
check_contract($previous !== '', 'Previous-stock API exists');
check_contract(str_contains($previous, "'layer' => ['table' => 'layer_daily_records'"), 'Previous-stock Layer table is fixed in allowlist');
check_contract(str_contains($previous, "'broiler' => ['table' => 'broiler_daily_records'"), 'Previous-stock Broiler table is fixed in allowlist');
check_contract(str_contains($previous, "'ruminant' => ['table' => 'ruminant_daily_records'"), 'Previous-stock Ruminant table is fixed in allowlist');
check_contract(str_contains($previous, 'if (!isset($tableMap[$type])'), 'Previous-stock rejects unknown dynamic table type');
check_contract(str_contains($previous, '$stmt = $pdo->prepare($sql);') && str_contains($previous, '$stmt->execute($params);'), 'Previous-stock values use prepared parameters');

$farms = source('management/farms.php');
check_contract($farms !== '', 'Farm management source exists');
check_contract(str_contains($farms, 'foreach ([\'sales_allocations\''), 'Tenant purge table names come from explicit fixed list');
check_contract(str_contains($farms, 'tableExists($pdo, $table)') && str_contains($farms, 'tableHasFarmId($pdo, $table)'), 'Tenant purge validates dynamic table before DELETE');
check_contract(str_contains($farms, '$pdo->prepare("DELETE FROM {$table} WHERE farm_id = ?")'), 'Tenant purge data value remains parameterized');

$diagnostics = source('lib/poultry_diagnostics.php');
check_contract($diagnostics !== '', 'Poultry diagnostics source exists');
check_contract(str_contains($diagnostics, 'max(1,min(60,$limit))') || str_contains($diagnostics, 'max(1, min(60, $limit))'), 'Poultry diagnostics LIMIT is integer-clamped');

$followup = source('lib/investigation_followup.php');
check_contract($followup !== '', 'Investigation follow-up source exists');
check_contract(str_contains($followup, 'max(1,min(100,$limit))') || str_contains($followup, 'max(1, min(100, $limit))'), 'Investigation history LIMIT is integer-clamped');
check_contract(str_contains($followup, 'implode(\',\',array_fill(0,count($ids),\'?\'))') || str_contains($followup, "implode(',',array_fill(0,count(\$ids),'?'))") || str_contains($followup, "implode(',', array_fill(0, count(\$ids), '?'))"), 'Investigation IN-list uses generated placeholders');

$functions = source('includes/functions.php');
check_contract($functions !== '', 'Permission helper source exists');
check_contract(str_contains($functions, 'role IN ($placeholders)') && str_contains($functions, '$pdo->prepare($sql)'), 'Permission role IN-list is placeholder-based and prepared');

$saleAlloc = source('lib/ruminant_sale_animal_allocation.php');
check_contract($saleAlloc !== '', 'Ruminant sale allocation source exists');
check_contract(str_contains($saleAlloc, 'id IN ($placeholders)') && str_contains($saleAlloc, '$pdo->prepare('), 'Ruminant sale allocation IN-list is placeholder-based and prepared');

$expenseAlloc = source('lib/ruminant_expense_allocation.php');
check_contract($expenseAlloc !== '', 'Ruminant expense allocation source exists');
check_contract(str_contains($expenseAlloc, 'id IN ($placeholders)') && str_contains($expenseAlloc, '$pdo->prepare('), 'Ruminant expense allocation IN-list is placeholder-based and prepared');

// Fail if request superglobals are interpolated directly inside PDO query/exec strings.
$scanTargets = [
    'api', 'admin', 'management', 'poultry', 'ruminant', 'includes', 'lib', 'inventory.php'
];
$unsafeDirect = [];
foreach ($scanTargets as $target) {
    $path = $root . '/' . $target;
    $files = [];
    if (is_file($path)) {
        $files[] = $path;
    } elseif (is_dir($path)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'php') $files[] = $file->getPathname();
        }
    }
    foreach ($files as $file) {
        $text = (string)file_get_contents($file);
        if (preg_match('/->(?:query|exec)\s*\(\s*["\'][^"\']*\$_(?:GET|POST|REQUEST)\b/s', $text)) {
            $unsafeDirect[] = substr($file, strlen($root) + 1);
        }
    }
}
check_contract($unsafeDirect === [], 'No request superglobal is directly interpolated into PDO query/exec SQL');
if ($unsafeDirect !== []) {
    echo '      ' . implode(', ', $unsafeDirect) . "\n";
}

echo "\n{$checks} checks, {$failures} failure(s).\n";
exit($failures === 0 ? 0 : 1);
