<?php
$root = dirname(__DIR__);
$checks = [];
function ck(string $name, bool $ok): void { global $checks; $checks[] = [$name, $ok]; echo ($ok ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL; }
function txt(string $path): string { $v = @file_get_contents($path); return $v === false ? '' : $v; }

$migration = txt($root . '/migrations/038_poultry_cycle_acquisition.sql');
$service = txt($root . '/lib/poultry_cycle_acquisition.php');
$page = txt($root . '/management/production_cycles.php');
$farms = txt($root . '/management/farms.php');
$financial = txt($root . '/includes/financial.php');

ck('migration creates canonical poultry acquisition table', str_contains($migration, 'CREATE TABLE IF NOT EXISTS poultry_cycle_acquisitions'));
ck('migration stores explicit actual entry age', str_contains($migration, 'age_days INT NOT NULL'));
ck('migration stores authoritative total acquisition cost', str_contains($migration, 'total_cost DECIMAL(14,2) NULL'));
ck('migration has no acquisition backfill', !preg_match('/INSERT\s+INTO\s+poultry_cycle_acquisitions\s+SELECT/i', $migration));
ck('cycle FK remains RESTRICT for auditable acquisition history', str_contains($migration, 'REFERENCES production_cycles(id) ON DELETE RESTRICT'));

ck('Layer supports explicit Point-of-Lay purchase', str_contains($service, "'purchased_point_of_lay' => 'Purchased Point-of-Lay'"));
$broilerBlockStart = strpos($service, "if (\$type === 'broiler')");
$broilerBlockEnd = $broilerBlockStart !== false ? strpos($service, 'return [];', $broilerBlockStart) : false;
$broilerBlock = ($broilerBlockStart !== false && $broilerBlockEnd !== false) ? substr($service, $broilerBlockStart, $broilerBlockEnd - $broilerBlockStart) : '';
ck('Broiler does not expose Point-of-Lay type', $broilerBlock !== '' && !str_contains($broilerBlock, 'purchased_point_of_lay'));
ck('purchased birds require actual total amount', str_contains($service, "if (\$acquisitionType !== 'internal_transfer' && \$totalCost === null)"));
ck('age must be explicit positive days without DOC assumption', str_contains($service, 'if ($ageDays < 1)'));
ck('acquisition cannot predate production cycle start', str_contains($service, "\$acquisitionDate < (string)\$cycle['start_date']"));
ck('acquisition respects first known lifecycle boundary', str_contains($service, '$acquisitionDate > $firstPhaseStart'));
ck('recording acquisition is audit logged', str_contains($service, "audit_log_event('poultry_cycle_acquisition_recorded'"));
ck('effective cost per bird is derived from total basis', str_contains($service, '$totalCost / $quantity'));

ck('Production Cycles exposes acquisition UI', str_contains($page, 'Poultry Entry / Acquisition') && str_contains($page, 'Record Flock Entry'));
ck('UI explicitly supports actual Broiler age examples', str_contains($page, '14 = 2 weeks') && str_contains($page, '28 = 4 weeks'));
ck('UI states acquisition does not alter core accounting/basis', str_contains($page, 'does not post Inventory or Expense transactions') && str_contains($page, 'does not alter period profitability or Bird Cost Basis'));
ck('UI marks legacy acquisition unknown instead of inferring', str_contains($page, 'No poultry acquisition history has been recorded') && str_contains($page, 'Not recorded'));
ck('POL option is Layer-only in UI', str_contains($page, 'Purchased Point-of-Lay (Layer only)') && str_contains($page, 'data-layer-only="1"'));
ck('POST is CSRF-protected through page-level request gate', str_contains($page, "verify_csrf_token(\$_POST['csrf_token'] ?? '')"));

$acqPos = strpos($farms, "'poultry_cycle_acquisitions'");
$cyclePos = strpos($farms, "'production_cycles'");
ck('tenant purge removes acquisition rows before production cycles', $acqPos !== false && $cyclePos !== false && $acqPos < $cyclePos);

ck('canonical profitability file is not wired to poultry acquisitions', !str_contains($financial, 'poultry_cycle_acquisitions'));
ck('Batch 2 does not reinterpret bird_unit_cost in acquisition service', !str_contains($service, 'bird_unit_cost'));

$failed = array_filter($checks, fn($c) => !$c[1]);
echo PHP_EOL . count($checks) . ' checks; ' . count($failed) . ' failed.' . PHP_EOL;
exit($failed ? 1 : 0);
