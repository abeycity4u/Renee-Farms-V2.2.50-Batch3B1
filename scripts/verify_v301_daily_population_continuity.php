<?php

$root = dirname(__DIR__);
$servicePath =
    $root . '/lib/daily_population_continuity.php';

if (!is_file($servicePath)) {
    fwrite(
        STDERR,
        "FAIL - shared continuity service is missing\n"
    );
    exit(1);
}

require_once $servicePath;

$checks = [];

function continuity_check(&$checks, $ok, $label)
{
    $checks[] = [(bool)$ok, $label];
}

/* Public contract */
continuity_check(
    $checks,
    function_exists('daily_population_continuity_preview'),
    'shared read-only preview exists'
);

continuity_check(
    $checks,
    function_exists('daily_population_continuity_apply'),
    'shared transactional apply exists'
);

$types = daily_population_continuity_types();

continuity_check(
    $checks,
    isset($types['layer'])
        && $types['layer']['table']
            === 'layer_daily_records'
        && $types['layer']['source_type']
            === 'daily_layer_record',
    'Layer uses canonical shared mapping'
);

continuity_check(
    $checks,
    isset($types['broiler'])
        && $types['broiler']['table']
            === 'broiler_daily_records'
        && $types['broiler']['source_type']
            === 'daily_broiler_record',
    'Broiler uses canonical shared mapping'
);

continuity_check(
    $checks,
    isset($types['ruminant'])
        && $types['ruminant']['table']
            === 'ruminant_daily_records'
        && $types['ruminant']['source_type']
            === 'daily_ruminant_record'
        && $types['ruminant']['animal_type_required']
            === true,
    'Ruminant mapping preserves animal-type isolation'
);

/*
 * Behavioral preview tests use a tiny fake PDO-compatible harness rather
 * than depending on a live farm database.
 */
final class ContinuityFakeStatement
{
    private $rows;
    private $query;

    public function __construct(array $rows, string $query)
    {
        $this->rows = $rows;
        $this->query = $query;
    }

    public function execute($params = null)
    {
        return true;
    }

    public function fetchAll($mode = null)
    {
        return $this->rows;
    }

    public function rowCount()
    {
        return stripos(ltrim($this->query), 'UPDATE ') === 0
            ? 1
            : 0;
    }
}

final class ContinuityFakePDO extends PDO
{
    private $rows;
    private $transaction = false;
    public $preparedSql = [];

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function getAttribute($attribute)
    {
        if ($attribute === PDO::ATTR_DRIVER_NAME) {
            return 'fake';
        }

        return null;
    }

    public function prepare(
        $query,
        $options = []
    ) {
        $this->preparedSql[] = $query;

        return new ContinuityFakeStatement(
            $this->rows,
            $query
        );
    }

    public function inTransaction()
    {
        return $this->transaction;
    }

    public function setFakeTransaction($value)
    {
        $this->transaction = (bool)$value;
    }
}

/* Layer: +6 birds flows forward, mortality stays unchanged. */
$layerPdo = new ContinuityFakePDO([
    [
        'id' => 100,
        'record_date' => '2026-09-15',
        'opening_stock' => 490,
        'mortality' => 2,
        'egg_production' => 400,
        'laying_rate' => 81.63,
    ],
]);

$layerPlan = daily_population_continuity_preview(
    $layerPdo,
    4,
    55,
    'layer',
    '2026-09-14',
    498,
    2
);

continuity_check(
    $checks,
    $layerPlan['affected_count'] === 1
        && $layerPlan['changes'][0][
            'old_opening_stock'
        ] === 490
        && $layerPlan['changes'][0][
            'new_opening_stock'
        ] === 496
        && $layerPlan['changes'][0][
            'mortality'
        ] === 2
        && $layerPlan['changes'][0][
            'new_closing_stock'
        ] === 494,
    'Layer historical correction propagates opening only'
);

continuity_check(
    $checks,
    abs(
        $layerPlan['changes'][0][
            'new_laying_rate'
        ] - round((400 / 496) * 100, 2)
    ) < 0.001,
    'Layer laying rate follows corrected opening'
);

/* Invalid later mortality must block propagation. */
$mortalityBlocked = false;

try {
    $pdo = new ContinuityFakePDO([
        [
            'id' => 200,
            'record_date' => '2026-09-15',
            'opening_stock' => 10,
            'mortality' => 9,
        ],
    ]);

    daily_population_continuity_preview(
        $pdo,
        1,
        2,
        'broiler',
        '2026-09-14',
        10,
        5
    );
} catch (DailyPopulationContinuityException $e) {
    $mortalityBlocked = true;
}

continuity_check(
    $checks,
    $mortalityBlocked,
    'downstream mortality cannot exceed corrected opening'
);

/* Invalid Layer egg fact must block propagation. */
$eggBlocked = false;

try {
    $pdo = new ContinuityFakePDO([
        [
            'id' => 300,
            'record_date' => '2026-09-15',
            'opening_stock' => 20,
            'mortality' => 0,
            'egg_production' => 18,
            'laying_rate' => 90.0,
        ],
    ]);

    daily_population_continuity_preview(
        $pdo,
        1,
        2,
        'layer',
        '2026-09-14',
        20,
        5
    );
} catch (DailyPopulationContinuityException $e) {
    $eggBlocked = true;
}

continuity_check(
    $checks,
    $eggBlocked,
    'Layer egg fact cannot exceed corrected opening'
);

/* Ruminant SQL must retain animal_type scope. */
$ruminantPdo = new ContinuityFakePDO([]);

daily_population_continuity_preview(
    $ruminantPdo,
    1,
    2,
    'ruminant',
    '2026-09-14',
    20,
    1,
    'goat'
);

$ruminantSql = implode(
    "\n",
    $ruminantPdo->preparedSql
);

continuity_check(
    $checks,
    strpos(
        $ruminantSql,
        'LOWER(animal_type) = ?'
    ) !== false,
    'Ruminant query is scoped by animal type'
);


/* Actual apply path: later Broiler opening changes, mortality does not. */
$applyPdo = new ContinuityFakePDO([
    [
        'id' => 401,
        'record_date' => '2026-09-15',
        'opening_stock' => 7,
        'mortality' => 1,
    ],
]);

$applyPdo->setFakeTransaction(true);

$applyPlan = daily_population_continuity_apply(
    $applyPdo,
    1,
    2,
    'broiler',
    '2026-09-14',
    10,
    2
);

$applySql = implode(
    "\n",
    $applyPdo->preparedSql
);

continuity_check(
    $checks,
    $applyPlan['affected_count'] === 1
        && $applyPlan['applied_count'] === 1
        && $applyPlan['changes'][0]['new_opening_stock'] === 8
        && $applyPlan['changes'][0]['mortality'] === 1,
    'shared apply propagates corrected opening and preserves later mortality'
);

continuity_check(
    $checks,
    strpos(
        $applySql,
        'UPDATE broiler_daily_records SET opening_stock = ?'
    ) !== false,
    'shared apply writes only Broiler opening stock'
);

/* Apply is deliberately unavailable outside a caller transaction. */
$transactionRequired = false;

try {
    $pdo = new ContinuityFakePDO([]);

    daily_population_continuity_apply(
        $pdo,
        1,
        2,
        'broiler',
        '2026-09-14',
        20,
        1
    );
} catch (DailyPopulationContinuityException $e) {
    $transactionRequired =
        strpos(
            $e->getMessage(),
            'active transaction'
        ) !== false;
}

continuity_check(
    $checks,
    $transactionRequired,
    'apply requires caller-owned transaction'
);

/* Static safety contract: no canonical ledger ownership here. */
$source = file_get_contents($servicePath);

continuity_check(
    $checks,
    strpos(
        $source,
        'production_population_movements'
    ) === false
        && strpos(
            $source,
            'daily_population_sync_mortality('
        ) === false,
    'continuity service does not write canonical population ledger'
);

continuity_check(
    $checks,
    strpos(
        $source,
        'SET mortality'
    ) === false
        && strpos(
            $source,
            'mortality = ?'
        ) === false,
    'continuity service never rewrites downstream mortality'
);


/* Route integration contract */
$routeFiles = [
    'layer' => $root . '/poultry/layers_daily_record.php',
    'broiler' => $root . '/poultry/broiler_daily_record.php',
    'ruminant' => $root . '/ruminant/ruminant_daily_record.php',
];

$routeSources = [];

foreach ($routeFiles as $type => $path) {
    $routeSources[$type] = is_file($path)
        ? file_get_contents($path)
        : '';

    continuity_check(
        $checks,
        strpos(
            $routeSources[$type],
            "daily_population_continuity.php"
        ) !== false,
        ucfirst($type) . ' route loads shared continuity service'
    );

    continuity_check(
        $checks,
        strpos(
            $routeSources[$type],
            'daily_population_continuity_apply('
        ) !== false,
        ucfirst($type) . ' route calls shared continuity service'
    );

    continuity_check(
        $checks,
        strpos(
            $routeSources[$type],
            "Opening stock must match the previous day's closing"
        ) !== false,
        ucfirst($type) . ' keeps previous-record continuity guard'
    );
}

continuity_check(
    $checks,
    strpos(
        $routeSources['layer'],
        'This change would break flock continuity'
    ) === false,
    'Layer obsolete next-record dead-end guard removed'
);

continuity_check(
    $checks,
    strpos(
        $routeSources['broiler'],
        'This change would break flock continuity'
    ) === false,
    'Broiler obsolete next-record dead-end guard removed'
);

continuity_check(
    $checks,
    strpos(
        $routeSources['ruminant'],
        'This change would break herd continuity'
    ) === false,
    'Ruminant obsolete next-record dead-end guard removed'
);

foreach (['layer', 'broiler', 'ruminant'] as $type) {
    $applyPos = strpos(
        $routeSources[$type],
        'daily_population_continuity_apply('
    );

    $feedPos = strpos(
        $routeSources[$type],
        'sync_daily_feed_usage('
    );

    continuity_check(
        $checks,
        $applyPos !== false
            && $feedPos !== false
            && $applyPos < $feedPos,
        ucfirst($type)
            . ' locks/applies Daily Record continuity before feed sync'
    );
}

continuity_check(
    $checks,
    preg_match(
        "/daily_population_continuity_apply\\s*\\("
        . ".*?'ruminant'"
        . ".*?\\$animalType"
        . ".*?\\)/s",
        $routeSources['ruminant']
    ) === 1,
    'Ruminant adapter passes animal type to shared service'
);

continuity_check(
    $checks,
    strpos(
        $routeSources['layer'],
        "'layer'"
    ) !== false
        && strpos(
            $routeSources['broiler'],
            "'broiler'"
        ) !== false,
    'Poultry adapters identify their production types'
);


foreach (['layer', 'broiler', 'ruminant'] as $type) {
    continuity_check(
        $checks,
        strpos(
            $routeSources[$type],
            '$continuityPlan = daily_population_continuity_apply('
        ) !== false,
        ucfirst($type) . ' retains shared continuity result'
    );

    continuity_check(
        $checks,
        strpos(
            $routeSources[$type],
            "Population continuity updated across"
        ) !== false
            && strpos(
                $routeSources[$type],
                "affected_count"
            ) !== false,
        ucfirst($type) . ' surfaces propagated-record count'
    );
}

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
