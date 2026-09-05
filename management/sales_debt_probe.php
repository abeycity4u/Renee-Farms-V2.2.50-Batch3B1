<?php
require_once(dirname(__DIR__) . '/init.php');
require_once(dirname(__DIR__) . '/config.php');

requireLogin();

if (!isPlatformOwner() && !hasRole('farm_admin')) {
    http_response_code(403);
    exit('Farm Admin or Platform Owner access required.');
}

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');

function debt_probe_result(string $name, callable $test): array
{
    try {
        $value = $test();
        return [
            'test' => $name,
            'status' => 'PASS',
            'detail' => is_scalar($value) || $value === null ? $value : 'ok',
        ];
    } catch (Throwable $e) {
        $sqlState = $e instanceof PDOException ? (string)$e->getCode() : '';
        $driverCode = null;
        if ($e instanceof PDOException && isset($e->errorInfo[1])) {
            $driverCode = (int)$e->errorInfo[1];
        }

        $category = 'query_failed';
        if ($sqlState === '42S02' || $driverCode === 1146) {
            $category = 'table_missing';
        } elseif ($sqlState === '42S22' || $driverCode === 1054) {
            $category = 'column_missing';
        } elseif (in_array($driverCode, [1044, 1045, 1142, 1143], true)) {
            $category = 'database_privilege';
        }

        // Full diagnostic stays in the private server error log.
        error_log('[SALES_DEBT_PROBE] ' . get_class($e) . ' code=' . $sqlState . ' driver=' . (string)$driverCode . ' message=' . $e->getMessage());

        return [
            'test' => $name,
            'status' => 'FAIL',
            'category' => $category,
            'sqlstate' => $sqlState !== '' ? $sqlState : null,
            'driver_code' => $driverCode,
        ];
    }
}

$farmId = getCurrentFarmId();

$results = [];
$results[] = debt_probe_result('database_connection', function () use ($pdo) {
    return (int)$pdo->query('SELECT 1')->fetchColumn();
});
$results[] = debt_probe_result('ledger_table_basic_read', function () use ($pdo) {
    $stmt = $pdo->query('SELECT 1 FROM customer_ledger_entries LIMIT 1');
    return $stmt->fetchColumn() === false ? 'table readable; no rows required' : 'table readable';
});
$results[] = debt_probe_result('ledger_tenant_column_read', function () use ($pdo, $farmId) {
    if ($farmId < 1) {
        return 'skipped: no active farm in session';
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM customer_ledger_entries WHERE farm_id = ?');
    $stmt->execute([$farmId]);
    return 'rows for active farm: ' . (int)$stmt->fetchColumn();
});

echo "Renee Farms — Sales Debt Read-Only Probe\n";
echo "=======================================\n";
echo "No database writes or migrations are performed.\n\n";
foreach ($results as $result) {
    echo $result['test'] . ': ' . $result['status'] . "\n";
    if (array_key_exists('detail', $result)) {
        echo '  detail: ' . (string)$result['detail'] . "\n";
    }
    if (($result['status'] ?? '') === 'FAIL') {
        echo '  category: ' . ($result['category'] ?? 'query_failed') . "\n";
        echo '  sqlstate: ' . (($result['sqlstate'] ?? null) ?: 'n/a') . "\n";
        echo '  driver_code: ' . (($result['driver_code'] ?? null) !== null ? (string)$result['driver_code'] : 'n/a') . "\n";
    }
}

echo "\nWhen finished, remove this temporary probe from the branch and deployment.\n";
