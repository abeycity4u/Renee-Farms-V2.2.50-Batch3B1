<?php
$root = dirname(__DIR__);
$checks = [];
function ck(string $name, bool $ok): void { global $checks; $checks[] = [$name, $ok]; }

$migration = file_get_contents($root . '/migrations/037_poultry_cycle_phase_history.sql');
$service = file_get_contents($root . '/lib/poultry_cycle_lifecycle.php');
$page = file_get_contents($root . '/management/production_cycles.php');
$financial = file_get_contents($root . '/includes/financial.php');
$farmsPage = file_get_contents($root . '/management/farms.php');

require_once $root . '/lib/poultry_cycle_lifecycle.php';

ck('migration creates dated poultry phase history', str_contains($migration, 'CREATE TABLE IF NOT EXISTS production_cycle_phases'));
ck('phase history is tenant and cycle scoped', str_contains($migration, 'farm_id INT NOT NULL') && str_contains($migration, 'cycle_id INT NOT NULL'));
ck('same cycle cannot start two phases on same date', str_contains($migration, 'uniq_poultry_phase_cycle_start'));
ck('migration does not mutate production_cycles status', !preg_match('/UPDATE\s+production_cycles\s+SET\s+status/i', $migration));
ck('migration performs no historical phase backfill', !preg_match('/INSERT\s+INTO\s+production_cycle_phases\s+SELECT/i', $migration));

ck('layer lifecycle phases are explicit', poultry_lifecycle_allowed_phases('layer') === ['rearing' => 'Rearing', 'production' => 'Production']);
ck('broiler lifecycle phases are explicit', poultry_lifecycle_allowed_phases('broiler') === ['growing' => 'Growing / Rearing', 'harvest' => 'Harvest / Sale']);
ck('layer transition only rearing to production', poultry_lifecycle_next_phases('layer', 'rearing') === ['production' => 'Production'] && poultry_lifecycle_next_phases('layer', 'production') === []);
ck('broiler transition only growing to harvest', poultry_lifecycle_next_phases('broiler', 'growing') === ['harvest' => 'Harvest / Sale'] && poultry_lifecycle_next_phases('broiler', 'harvest') === []);
ck('missing lifecycle remains explicit unknown', poultry_lifecycle_phase_label('layer', null) === 'Lifecycle history not yet defined');

ck('service locks tenant poultry cycle before lifecycle write', str_contains($service, "farm_type = 'poultry'") && str_contains($service, 'FOR UPDATE'));
ck('service never reads daily operational tables to infer lifecycle', !preg_match('/FROM\s+(layer_daily_records|broiler_daily_records)|JOIN\s+(layer_daily_records|broiler_daily_records)/i', $service));
ck('transition appends new phase and closes prior date', str_contains($service, "modify('-1 day')") && str_contains($service, 'INSERT INTO production_cycle_phases'));
ck('terminal end cannot bypass a defined next phase', str_contains($service, 'Record a lifecycle transition instead of ending it directly.'));
ck('lifecycle writes are audit logged', str_contains($service, 'poultry_lifecycle_phase_started') && str_contains($service, 'poultry_lifecycle_phase_transitioned') && str_contains($service, 'poultry_lifecycle_phase_ended'));

ck('production-cycle page loads lifecycle service', str_contains($page, "lib/poultry_cycle_lifecycle.php"));
ck('production-cycle page requires migration visibly', str_contains($page, '037_poultry_cycle_phase_history.sql'));
ck('production-cycle page offers explicit initial phase', str_contains($page, 'set_initial_poultry_phase'));
ck('production-cycle page offers explicit transition', str_contains($page, 'transition_poultry_phase'));
ck('production-cycle page preserves operational status wording', str_contains($page, 'Biological lifecycle is recorded separately from the production cycle\'s operational status.'));
ck('lifecycle management writes remain CSRF protected', str_contains($page, "verify_csrf_token(\$_POST['csrf_token'] ?? '')"));
ck('transition/end use centralized confirmation architecture', str_contains($page, 'data-confirm="Record this lifecycle transition?"') && str_contains($page, 'data-confirm="End the selected current biological phase?"'));
ck('unexpected lifecycle errors are not exposed raw', str_contains($page, 'The lifecycle transition could not be saved. No lifecycle history was changed.'));
ck('tenant purge removes lifecycle rows before production cycles', strpos($farmsPage, "'production_cycle_phases'") !== false && strpos($farmsPage, "'production_cycle_phases'") < strpos($farmsPage, "'production_cycles'"));

ck('canonical profitability formula remains present', str_contains($financial, 'getProfitabilitySummary'));
ck('lifecycle service does not call profitability or change financial engine', !preg_match('/getProfitabilitySummary|profit|revenue|expense|stock_transactions/i', $service));

$bad = array_filter($checks, fn($x) => !$x[1]);
foreach ($checks as [$name, $ok]) {
    echo ($ok ? 'PASS' : 'FAIL') . " - {$name}\n";
}
exit($bad ? 1 : 0);
