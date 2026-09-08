<?php
/**
 * V2.3 dashboard performance profiler.
 *
 * Read-only CLI diagnostic. It times the major shared dashboard read-model
 * stages without mutating farm, payment, subscription, session, or audit data.
 *
 * Usage:
 *   php scripts/profile_v230_dashboard.php --farm-name="Farm A LLC" --scope=all
 *   php scripts/profile_v230_dashboard.php --farm-id=3 --scope=all
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

ini_set('display_errors', '1');
error_reporting(E_ALL);

try {
    $root = dirname(__DIR__);
    require_once $root . '/config.php';
    require_once $root . '/includes/financial.php';
    require_once $root . '/includes/dashboard_livestock_snapshot.php';
    require_once $root . '/lib/farm_intelligence.php';

    $options = getopt('', ['farm-id::', 'farm-name::', 'scope::']);
    $farmId = isset($options['farm-id']) ? (int)$options['farm-id'] : 0;
    $farmName = trim((string)($options['farm-name'] ?? ''));
    $scope = strtolower(trim((string)($options['scope'] ?? 'all')));
    if (!in_array($scope, ['all', 'poultry', 'ruminant'], true)) {
        fwrite(STDERR, "Invalid --scope. Use all, poultry, or ruminant.\n");
        exit(1);
    }

    if ($farmId <= 0 && $farmName === '') {
        fwrite(STDERR, "Provide --farm-id=<id> or --farm-name=\"Exact Farm Name\".\n");
        exit(1);
    }

    if ($farmId <= 0) {
        $stmt = $pdo->prepare('SELECT id, name FROM farms WHERE name = ? LIMIT 2');
        $stmt->execute([$farmName]);
        $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($matches) !== 1) {
            fwrite(STDERR, count($matches) === 0
                ? "No exact farm matched that name.\n"
                : "Farm name is not unique; use --farm-id instead.\n");
            exit(1);
        }
        $farmId = (int)$matches[0]['id'];
        $farmName = (string)$matches[0]['name'];
    } else {
        $stmt = $pdo->prepare('SELECT name FROM farms WHERE id = ? LIMIT 1');
        $stmt->execute([$farmId]);
        $resolved = $stmt->fetchColumn();
        if ($resolved === false) {
            fwrite(STDERR, "Farm id not found.\n");
            exit(1);
        }
        $farmName = (string)$resolved;
    }

    $today = date('Y-m-d');
    $monthStart = date('Y-m-01');
    $monthEnd = date('Y-m-t');

    $results = [];
    $time = static function (string $label, callable $fn) use (&$results) {
        $start = hrtime(true);
        $value = $fn();
        $elapsedMs = (hrtime(true) - $start) / 1_000_000;
        $results[] = ['label' => $label, 'ms' => $elapsedMs];
        return $value;
    };

    $time('baseline SELECT 1', static function () use ($pdo) {
        return $pdo->query('SELECT 1')->fetchColumn();
    });

    $time('dashboard stock items', static function () use ($pdo, $farmId, $scope) {
        if ($scope === 'all') {
            $stmt = $pdo->prepare("SELECT * FROM stock_items WHERE farm_id = ? AND farm_type IN ('poultry','ruminant','both') AND is_active = 1 ORDER BY current_stock ASC");
            $stmt->execute([$farmId]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM stock_items WHERE farm_id = ? AND farm_type IN (?, 'both') AND is_active = 1 ORDER BY current_stock ASC");
            $stmt->execute([$farmId, $scope]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    });

    $time('livestock snapshot', static function () use ($pdo, $farmId, $scope) {
        $access = $scope === 'all' ? 'both' : $scope;
        return dashboard_livestock_snapshot($pdo, $farmId, $access);
    });

    $time('canonical profitability current month', static function () use ($pdo, $farmId, $monthStart, $monthEnd, $scope) {
        return farm_intelligence_summary($pdo, $farmId, $monthStart, $monthEnd, $scope);
    });

    $time('explainable intelligence full pass', static function () use ($pdo, $farmId, $scope, $today) {
        return farm_intelligence_explainable_signals($pdo, $farmId, $scope, $today);
    });

    $time('canonical profitability repeat', static function () use ($pdo, $farmId, $monthStart, $monthEnd, $scope) {
        return farm_intelligence_summary($pdo, $farmId, $monthStart, $monthEnd, $scope);
    });

    usort($results, static fn(array $a, array $b): int => $b['ms'] <=> $a['ms']);
    $totalMs = array_sum(array_column($results, 'ms'));

    printf("Dashboard profiler for %s (farm_id=%d, scope=%s)\n", $farmName, $farmId, $scope);
    printf("Date: %s\n\n", $today);
    foreach ($results as $row) {
        printf("%8.2f ms  %s\n", $row['ms'], $row['label']);
    }
    printf("\n%8.2f ms  timed stages total\n", $totalMs);
    echo "Read-only diagnostic completed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Dashboard profiler failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
