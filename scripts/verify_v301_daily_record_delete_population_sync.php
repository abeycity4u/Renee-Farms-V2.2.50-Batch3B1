<?php

$root = dirname(__DIR__);

$route = file_get_contents(
    $root . '/api/delete_record.php'
);

$helper = file_get_contents(
    $root . '/lib/daily_population_sync.php'
);

$checks = [];

function chk(&$checks, $ok, $label)
{
    $checks[] = [$ok, $label];
}

chk(
    $checks,
    $route !== false,
    'shared Daily Record delete endpoint exists'
);

chk(
    $checks,
    strpos(
        $route,
        "daily_population_sync.php"
    ) !== false,
    'delete endpoint loads canonical Daily mortality synchronizer'
);

$removePos = strpos(
    $route,
    'daily_population_remove_mortality('
);

$deletePos = strpos(
    $route,
    '$stmt->execute([$id, $farmId]);'
);

chk(
    $checks,
    $removePos !== false,
    'delete endpoint removes mortality projection'
);

chk(
    $checks,
    $removePos !== false
        && $deletePos !== false
        && $removePos < $deletePos,
    'mortality projection is removed before durable source deletion'
);

chk(
    $checks,
    strpos(
        $route,
        "'daily_layer_record'"
    ) !== false
        && strpos(
            $route,
            "'daily_broiler_record'"
        ) !== false,
    'shared deletion covers Layer and Broiler source types'
);

chk(
    $checks,
    strpos(
        $route,
        'delete_daily_feed_usage('
    ) !== false,
    'existing feed reversal remains intact'
);

chk(
    $checks,
    strpos(
        $route,
        'production_population_movements'
    ) === false,
    'delete route does not duplicate canonical population SQL'
);

chk(
    $checks,
    $helper !== false
        && strpos(
            $helper,
            'function daily_population_remove_mortality'
        ) !== false
        && strpos(
            $helper,
            'production_population_projection_sync('
        ) !== false,
    'shared helper delegates removal to canonical projection service'
);

chk(
    $checks,
    strpos($route, '$pdo->beginTransaction();')
        !== false
        && strpos($route, '$pdo->commit();')
        !== false,
    'feed reversal, population reversal, and source delete remain transactional'
);

$failed = array_filter(
    $checks,
    function ($row) {
        return !$row[0];
    }
);

foreach ($checks as $row) {
    echo ($row[0] ? 'PASS' : 'FAIL')
        . ' - '
        . $row[1]
        . PHP_EOL;
}

echo PHP_EOL;

if ($failed) {
    echo 'FAILED: '
        . count($failed)
        . '/'
        . count($checks)
        . PHP_EOL;
    exit(1);
}

echo 'ALL CHECKS PASSED: '
    . count($checks)
    . '/'
    . count($checks)
    . PHP_EOL;
