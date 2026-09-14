<?php
/**
 * Focused V3 Sales population-effect contract verifier.
 *
 * Safety:
 * - no database connection;
 * - no database writes;
 * - no fixture creation;
 * - pure runtime validation + semantic source/migration checks only.
 */

$root = dirname(__DIR__);
$servicePath = $root . '/lib/sale_population_effects.php';
$migration057Path = $root . '/migrations/057_sale_population_effect_foundation.sql';
$migration058Path = $root . '/migrations/058_sale_population_effect_lifecycle.sql';
$salesPagePath = $root . '/management/sales_records.php';
$deleteSalePath = $root . '/api/delete_sale.php';
$salesJsPath = $root . '/assets/js/management-sales-records.js';

$service = file_get_contents($servicePath);
$m057 = file_get_contents($migration057Path);
$m058 = file_get_contents($migration058Path);
$salesPage = file_get_contents($salesPagePath);
$deleteSaleApi = file_get_contents($deleteSalePath);
$salesJs = file_get_contents($salesJsPath);

if (
    $service === false
    || $m057 === false
    || $m058 === false
    || $salesPage === false
    || $deleteSaleApi === false
    || $salesJs === false
) {
    fwrite(STDERR, "VERIFY_SETUP_FAILED\n");
    exit(2);
}

$passes = 0;
$failures = 0;

function verify_true($condition, string $label): void
{
    global $passes, $failures;

    if ($condition) {
        $passes++;
        echo "[PASS] {$label}\n";
        return;
    }

    $failures++;
    echo "[FAIL] {$label}\n";
}

function verify_throws(callable $fn, string $label): void
{
    try {
        $fn();
        verify_true(false, $label);
    } catch (Throwable $e) {
        verify_true(true, $label);
    }
}

require_once $servicePath;

/* Pure input validation. */
verify_true(
    sale_population_effect_positive_int(1, 'Population') === 1,
    'positive integer headcount accepted'
);

verify_true(
    sale_population_effect_positive_int('12', 'Population') === 12,
    'numeric-string whole headcount accepted'
);

verify_true(
    sale_population_effect_positive_int('001', 'Population') === 1,
    'zero-padded whole headcount normalized'
);

verify_throws(
    function (): void {
        sale_population_effect_positive_int(0, 'Population');
    },
    'zero headcount rejected'
);

verify_throws(
    function (): void {
        sale_population_effect_positive_int(-1, 'Population');
    },
    'negative headcount rejected'
);

verify_throws(
    function (): void {
        sale_population_effect_positive_int('2.5', 'Population');
    },
    'fractional headcount rejected'
);

verify_throws(
    function (): void {
        sale_population_effect_positive_int('', 'Population');
    },
    'blank headcount rejected'
);

$normalized = sale_population_effect_normalize_rows([
    ['cycle_id' => '3', 'population_quantity' => '5'],
    ['cycle_id' => '1', 'population_quantity' => '2'],
]);

verify_true(
    array_keys($normalized) === [1, 3]
    && $normalized[1] === 2
    && $normalized[3] === 5,
    'cycle allocations normalize and sort deterministically'
);

verify_true(
    sale_population_effect_normalize_rows([]) === [],
    'empty allocation set remains explicit financial-only input'
);

verify_throws(
    function (): void {
        sale_population_effect_normalize_rows([
            ['cycle_id' => 4, 'population_quantity' => 2],
            ['cycle_id' => 4, 'population_quantity' => 3],
        ]);
    },
    'duplicate source cycle rejected'
);

verify_throws(
    function (): void {
        sale_population_effect_normalize_rows([
            ['cycle_id' => 4, 'population_quantity' => '1.5'],
        ]);
    },
    'fractional allocation quantity rejected'
);

/* Explicit Sales form adapter. */
verify_true(
    sale_population_effect_rows_from_post([]) === [],
    'missing stock-effect mode remains financial-only'
);

verify_true(
    sale_population_effect_rows_from_post([
        'population_effect_mode' => 'financial_only',
        'quantity' => '500',
        'unit_of_measure' => 'Head',
        'population_cycle_ids' => ['12'],
        'population_quantities' => ['50'],
    ]) === [],
    'financial-only mode ignores stale physical rows and never infers from financial fields'
);

$postRows = sale_population_effect_rows_from_post([
    'population_effect_mode' => 'remove_live_population',
    'population_cycle_ids' => ['9', '4'],
    'population_quantities' => ['25', '10'],
]);

verify_true(
    $postRows === [
        ['cycle_id' => 4, 'population_quantity' => 10],
        ['cycle_id' => 9, 'population_quantity' => 25],
    ],
    'explicit physical rows normalize deterministically'
);

verify_throws(
    function (): void {
        sale_population_effect_rows_from_post([
            'population_effect_mode' => 'remove_live_population',
            'population_cycle_ids' => [],
            'population_quantities' => [],
        ]);
    },
    'live-population mode requires an explicit source row'
);

verify_throws(
    function (): void {
        sale_population_effect_rows_from_post([
            'population_effect_mode' => 'remove_live_population',
            'population_cycle_ids' => ['2'],
            'population_quantities' => ['1.5'],
        ]);
    },
    'form adapter rejects fractional physical headcount'
);

verify_throws(
    function (): void {
        sale_population_effect_rows_from_post([
            'population_effect_mode' => 'remove_live_population',
            'population_cycle_ids' => ['2', '2'],
            'population_quantities' => ['1', '1'],
        ]);
    },
    'form adapter rejects duplicate source cycles'
);

verify_throws(
    function (): void {
        sale_population_effect_rows_from_post([
            'population_effect_mode' => 'automatic',
        ]);
    },
    'implicit or unknown population-effect mode is rejected'
);

verify_true(
    strpos($service, "spe.is_active=1") !== false
    && strpos($service, 'sale_population_effect_rows_for_sales') !== false,
    'current-effect reader exposes active durable rows through one shared query'
);

/* Shared-service ownership and projection boundaries. */
verify_true(
    preg_match(
        '/production_population_projection_sync\s*\(/',
        $service
    ) === 1,
    'canonical population projection synchronizer is used'
);

verify_true(
    preg_match(
        '/(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+production_population_movements/i',
        $service
    ) !== 1,
    'service contains no direct population-ledger DML'
);

verify_true(
    preg_match(
        '/DELETE\s+FROM\s+sale_population_effects/i',
        $service
    ) !== 1,
    'durable sale population source rows are never deleted'
);

verify_true(
    preg_match(
        "/['\"]movement_type['\"]\s*=>\s*['\"]sale['\"]/",
        $service
    ) === 1,
    'physical projection uses canonical sale movement type'
);

verify_true(
    preg_match(
        "/production_population_projection_sync\s*\(.*?['\"]sale['\"]/s",
        $service
    ) === 1,
    'projection source type is sale'
);

verify_true(
    strpos($service, 'unit_of_measure') === false
    && strpos($service, 'product_type') === false,
    'product and sales UOM cannot imply population removal'
);

verify_true(
    strpos($service, 'ruminant_animal_exit_events') === false
    && strpos($service, 'ruminant_sale_exit') === false,
    'aggregate Sales population effect never queries or derives tagged-ruminant exits'
);

verify_true(
    strpos(
        $service,
        'may legitimately coexist in one sale'
    ) !== false
    && strpos(
        $service,
        'those remain lifecycle-owned'
    ) !== false,
    'mixed tagged and aggregate ruminant sale ownership is explicit'
);

verify_true(
    strpos($service, '$saleCycleId') !== false
    && strpos($service, 'array_key_exists($saleCycleId, $desired)') !== false,
    'cycle-attributed sale is constrained to its selected cycle'
);

verify_true(
    strpos($service, 'production_population_assert_date_in_cycle') !== false,
    'sale population effect date is validated against source cycle'
);

verify_true(
    preg_match(
        '/SET\s+is_active\s*=\s*0/i',
        $service
    ) === 1,
    'removed effect is soft-deactivated'
);

verify_true(
    preg_match(
        '/is_active\s*=\s*1/i',
        $service
    ) === 1,
    'inactive durable effect can be reactivated'
);

verify_true(
    preg_match(
        "/['\"]status['\"]\s*=>\s*\\\$desired\s*\?\s*['\"]synchronized['\"]\s*:\s*['\"]financial_only['\"]/",
        $service
    ) === 1,
    'financial-only state is explicit'
);

/* Caller transaction ownership must be preserved. */
verify_true(
    preg_match(
        '/\$startedTransaction\s*=\s*!\s*\$pdo->inTransaction\s*\(\s*\)/',
        $service
    ) === 1,
    'service detects caller-owned transaction'
);

verify_true(
    preg_match(
        '/if\s*\(\s*\$startedTransaction\s*\)\s*\{\s*\$pdo->beginTransaction\s*\(/s',
        $service
    ) === 1,
    'service begins transaction only when it owns one'
);

verify_true(
    preg_match(
        '/if\s*\(\s*\$startedTransaction\s*\)\s*\{\s*\$pdo->commit\s*\(/s',
        $service
    ) === 1,
    'service commits only its own transaction'
);

verify_true(
    preg_match(
        '/\$startedTransaction\s*&&\s*\$pdo->inTransaction\s*\(\s*\)/',
        $service
    ) === 1,
    'service rollback is limited to its own transaction'
);

/* Delete policy must include active and inactive history. */
$guardStart = strpos(
    $service,
    'function sale_population_effect_assert_deletable'
);
$guardEnd = $guardStart === false
    ? false
    : strpos(
        $service,
        "if (!function_exists('sale_population_effect_clear'))",
        $guardStart
    );

$deleteGuard = (
    $guardStart !== false
    && $guardEnd !== false
    && $guardEnd > $guardStart
)
    ? substr($service, $guardStart, $guardEnd - $guardStart)
    : '';

verify_true(
    $deleteGuard !== ''
    && strpos($deleteGuard, 'sale_population_effects') !== false,
    'hard-delete guard checks durable population history'
);

verify_true(
    $deleteGuard !== ''
    && strpos($deleteGuard, 'is_active') === false,
    'inactive history also blocks destructive sale deletion'
);

/* Thin Sales route integration. */
verify_true(
    strpos($salesPage, 'lib/sale_population_effects.php') !== false
    && strpos($deleteSaleApi, 'lib/sale_population_effects.php') !== false,
    'Sales writers and delete endpoint load the shared population service'
);

verify_true(
    preg_match_all(
        '/sale_population_effect_rows_from_post\s*\(\s*\$_POST\s*\)/',
        $salesPage
    ) === 2,
    'add and edit routes both use the shared explicit POST adapter'
);

verify_true(
    preg_match_all(
        '/sale_population_effect_sync\s*\(/',
        $salesPage
    ) === 2,
    'add and edit routes both synchronize through the shared population service'
);

verify_true(
    preg_match(
        '/(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+sale_population_effects/i',
        $salesPage
    ) !== 1,
    'Sales page contains no direct sale-population-effect DML'
);

$receivableGuardPos = strpos(
    $deleteSaleApi,
    'receivable_assert_sale_deletable'
);
$populationGuardPos = strpos(
    $deleteSaleApi,
    'sale_population_effect_assert_deletable'
);
$ruminantReversePos = strpos(
    $deleteSaleApi,
    'ruminant_sale_reverse_exit_events'
);
$hardDeletePos = strpos(
    $deleteSaleApi,
    'DELETE FROM sales_records'
);

verify_true(
    $receivableGuardPos !== false
    && $populationGuardPos !== false
    && $ruminantReversePos !== false
    && $hardDeletePos !== false
    && $receivableGuardPos < $populationGuardPos
    && $populationGuardPos < $ruminantReversePos
    && $populationGuardPos < $hardDeletePos,
    'population history blocks deletion before destructive sale cleanup'
);

verify_true(
    preg_match(
        '/catch\s*\(\s*SalePopulationEffectException\s+\$e\s*\)/',
        $deleteSaleApi
    ) === 1
    && strpos($deleteSaleApi, '$e->getMessage()') !== false
    && strpos($deleteSaleApi, '409') !== false,
    'population delete policy returns its friendly conflict response'
);

verify_true(
    preg_match(
        '/(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+sale_population_effects/i',
        $deleteSaleApi
    ) !== 1,
    'delete endpoint contains no direct sale-population-effect DML'
);

/* Explicit Sales population presentation and browser contract. */
verify_true(
    substr_count(
        $salesPage,
        'sale_population_effect_rows_for_sales('
    ) === 1,
    'Sales page reads current physical effects through the shared reader'
);

verify_true(
    strpos($salesPage, 'data-sale-population-effect-map') !== false
    && strpos($salesPage, '<th>Population Effect</th>') !== false,
    'Sales presentation exposes current explicit population effects'
);

verify_true(
    strpos(
        $salesPage,
        'name="population_effect_mode"'
    ) !== false
    && strpos(
        $salesPage,
        'value="financial_only" selected'
    ) !== false
    && strpos(
        $salesPage,
        'value="remove_live_population"'
    ) !== false,
    'Sales modal component requires an explicit physical-effect mode'
);

verify_true(
    strpos(
        $salesPage,
        'Aggregate/group headcount only'
    ) !== false
    && strpos(
        $salesPage,
        'Animal Registry'
    ) !== false,
    'mixed ruminant UI separates aggregate headcount from tagged lifecycle exits'
);

verify_true(
    strpos($salesJs, 'salePopulationEffectMap') !== false
    && strpos(
        $salesJs,
        'function refreshSalePopulationEffect'
    ) !== false
    && strpos(
        $salesJs,
        'function loadEditSalePopulationEffect'
    ) !== false,
    'Sales browser behavior centrally loads and refreshes population effects'
);

verify_true(
    strpos(
        $salesJs,
        "name: 'population_cycle_ids[]'"
    ) !== false
    && strpos(
        $salesJs,
        "name: 'population_quantities[]'"
    ) !== false,
    'browser submits explicit source-cycle and whole-headcount rows'
);

$populationUiStart = strpos(
    $salesJs,
    'function salePopulationSelectors'
);
$populationUiEnd = $populationUiStart === false
    ? false
    : strpos(
        $salesJs,
        '$(document).ready',
        $populationUiStart
    );

$populationUi = (
    $populationUiStart !== false
    && $populationUiEnd !== false
    && $populationUiEnd > $populationUiStart
)
    ? substr(
        $salesJs,
        $populationUiStart,
        $populationUiEnd - $populationUiStart
    )
    : '';

verify_true(
    $populationUi !== ''
    && strpos($populationUi, "'#addQuantity'") === false
    && strpos($populationUi, '"#addQuantity"') === false
    && strpos($populationUi, "'#editSaleQuantity'") === false
    && strpos($populationUi, '"#editSaleQuantity"') === false
    && strpos($populationUi, "'#addUnitPreset'") === false
    && strpos($populationUi, '"#addUnitPreset"') === false
    && strpos($populationUi, "'#editSaleUnitPreset'") === false
    && strpos($populationUi, '"#editSaleUnitPreset"') === false
    && strpos($populationUi, "'#addProductType'") === false
    && strpos($populationUi, '"#addProductType"') === false
    && strpos($populationUi, "'#editSaleProduct'") === false
    && strpos($populationUi, '"#editSaleProduct"') === false,
    'population UI never infers headcount from financial quantity, UOM, or product'
);

verify_true(
    $populationUi !== ''
    && strpos($populationUi, 'directCycle > 0') !== false
    && strpos(
        $populationUi,
        'Number(cycle.id) === directCycle'
    ) !== false,
    'cycle-attributed sale UI locks population source to its selected sale cycle'
);

verify_true(
    strpos(
        $salesPage,
        'PopulationEffectExplanation'
    ) !== false
    && strpos(
        $salesJs,
        'salePopulationEffectExplanations'
    ) !== false,
    'Sales population explanation is shared across add and edit modes'
);

verify_true(
    strpos(
        $salesJs,
        'This option updates only the financial sale record.'
    ) !== false
    && strpos(
        $salesJs,
        'This option records a physical population removal.'
    ) !== false
    && strpos(
        $salesJs,
        'whole headcount you explicitly enter'
    ) !== false,
    'population mode explanation describes the selected operational effect'
);

verify_true(
    strpos(
        $salesJs,
        'text-nowrap px-3 sale-population-remove-row'
    ) !== false
    && strpos(
        $salesJs,
        "const actionColumn = $('<div>', {class: 'col-md-3'});"
    ) !== false,
    'population row Remove action stays readable on one line'
);

/* Migration 057 foundational guarantees. */
verify_true(
    preg_match(
        '/UNIQUE\s+KEY\s+uniq_sale_population_effect_cycle\s*\(\s*farm_id\s*,\s*sale_id\s*,\s*cycle_id\s*\)/is',
        $m057
    ) === 1,
    'one durable sale population source exists per sale and cycle'
);

verify_true(
    preg_match(
        '/FOREIGN\s+KEY\s*\(\s*farm_id\s*,\s*sale_id\s*\)\s*REFERENCES\s+sales_records\s*\(\s*farm_id\s*,\s*id\s*\)\s*ON\s+DELETE\s+RESTRICT/is',
        $m057
    ) === 1,
    'sale population source uses tenant-safe restricted sale FK'
);

verify_true(
    preg_match(
        '/FOREIGN\s+KEY\s*\(\s*farm_id\s*,\s*cycle_id\s*\)\s*REFERENCES\s+production_cycles\s*\(\s*farm_id\s*,\s*id\s*\)/is',
        $m057
    ) === 1,
    'sale population source uses tenant-safe cycle FK'
);

verify_true(
    preg_match(
        '/CHECK\s*\(\s*population_quantity\s*>\s*0\s*\)/i',
        $m057
    ) === 1,
    'database requires positive physical headcount'
);

verify_true(
    preg_match(
        '/INSERT\s+INTO\s+sale_population_effects/i',
        $m057
    ) !== 1,
    'Migration 057 performs no historical population backfill'
);

/* Migration 058 durable lifecycle guarantees. */
verify_true(
    preg_match(
        '/ADD\s+COLUMN\s+is_active\s+TINYINT\s*\(\s*1\s*\)\s+NOT\s+NULL\s+DEFAULT\s+1/is',
        $m058
    ) === 1,
    'Migration 058 adds active lifecycle state'
);

verify_true(
    preg_match(
        '/CHECK\s*\(\s*is_active\s+IN\s*\(\s*0\s*,\s*1\s*\)\s*\)/i',
        $m058
    ) === 1,
    'Migration 058 constrains active lifecycle state'
);

verify_true(
    strpos(
        $m058,
        '058_sale_population_effect_lifecycle.sql'
    ) !== false,
    'Migration 058 records its schema checkpoint'
);

verify_true(
    preg_match(
        '/(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+sale_population_effects/i',
        $m058
    ) !== 1,
    'Migration 058 does not mutate historical effect rows'
);

echo "\n=== RESULT ===\n";
echo "PASS={$passes}\n";
echo "FAIL={$failures}\n";
echo "DATABASE_CONNECTION_USED=NO\n";
echo "DATABASE_WRITE_PERFORMED=NO\n";

exit($failures === 0 ? 0 : 1);
