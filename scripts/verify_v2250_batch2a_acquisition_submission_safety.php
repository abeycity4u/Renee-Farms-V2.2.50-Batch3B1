<?php
$root = dirname(__DIR__);
function t(string $p): string { $v = @file_get_contents($p); return $v === false ? '' : $v; }
function ck(string $label, bool $ok): void { echo ($ok ? "PASS" : "FAIL") . ": {$label}\n"; if (!$ok) $GLOBALS['fail'] = true; }
$GLOBALS['fail'] = false;
$migration = t($root . '/migrations/039_poultry_acquisition_submission_corrections.sql');
$service = t($root . '/lib/poultry_cycle_acquisition.php');
$page = t($root . '/management/production_cycles.php');
$financial = t($root . '/includes/financial.php');

ck('migration adds request token', str_contains($migration, 'request_token VARCHAR(64) NULL'));
ck('migration makes request token tenant-unique', str_contains($migration, 'UNIQUE KEY uniq_poultry_acquisition_request (farm_id, request_token)'));
ck('migration adds auditable void metadata', str_contains($migration, 'voided_at DATETIME NULL') && str_contains($migration, 'voided_by INT NULL') && str_contains($migration, 'void_reason VARCHAR(255) NULL'));
ck('service checks request token before insert', str_contains($service, "SELECT id FROM poultry_cycle_acquisitions WHERE farm_id = ? AND request_token = ?"));
ck('insert persists request token', str_contains($service, 'notes, request_token, created_by'));
ck('void workflow updates rather than deletes', str_contains($service, 'SET voided_at = NOW(), voided_by = ?, void_reason = ?') && !preg_match('/DELETE\s+FROM\s+poultry_cycle_acquisitions/i', $service));
ck('void workflow is audit logged', str_contains($service, "audit_log_event('poultry_cycle_acquisition_voided'"));
ck('summary excludes voided rows', str_contains($service, "return empty(\$row['voided_at']);"));
ck('page uses PRG after acquisition save', str_contains($page, "header('Location: ' . BASE_URL . '/management/production_cycles.php#poultry-entry-acquisition')"));
ck('form posts request token', str_contains($page, 'name="request_token"'));
ck('pricing UI separates unit and total', str_contains($page, 'Unit Purchase Price (₦ / bird)') && str_contains($page, 'Total Bird Acquisition Amount (₦)'));
ck('total amount warning is explicit', str_contains($page, 'This is the total amount for all birds, not the unit price.'));
ck('server can derive total from unit price', str_contains($page, '$totalCost = round(((float)$unitPrice) * ((int)$quantity), 2);'));
ck('correction UI is void not destructive delete', str_contains($page, 'Void Erroneous Entry') && str_contains($page, 'void_poultry_acquisition'));
ck('voided history remains visible', str_contains($page, '<span class="badge bg-secondary">Voided</span>'));
ck('canonical profitability remains acquisition-independent', !str_contains($financial, 'poultry_cycle_acquisitions'));

if ($GLOBALS['fail']) { exit(1); }
echo "Batch 2A acquisition submission safety verification passed.\n";
