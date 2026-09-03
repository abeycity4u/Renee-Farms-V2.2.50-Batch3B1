<?php
$root = dirname(__DIR__);
$checks = [];
function check_it($condition, $message) { global $checks; $checks[] = [$condition, $message]; echo ($condition ? "PASS" : "FAIL") . " - {$message}\n"; }
$migration = file_get_contents($root . '/migrations/028_ruminant_expense_animal_allocations.sql');
$helper = file_get_contents($root . '/lib/ruminant_expense_allocation.php');
$page = file_get_contents($root . '/ruminant/ruminant_expenses.php');
$api = file_get_contents($root . '/api/update_expense.php');
check_it(strpos($migration, 'CREATE TABLE IF NOT EXISTS ruminant_expense_animal_allocations') !== false, 'Animal expense allocation table exists');
check_it(strpos($migration, 'UNIQUE KEY uniq_ruminant_expense_animal (expense_id, animal_id)') !== false, 'Duplicate animal allocation is prevented per expense');
check_it(strpos($migration, 'FOREIGN KEY (expense_id) REFERENCES farm_expenses(id) ON DELETE CASCADE') !== false, 'Expense remains source of truth with cascade cleanup');
check_it(strpos($migration, 'FOREIGN KEY (animal_id) REFERENCES ruminant_animals(id) ON DELETE CASCADE') !== false, 'Allocations reference registered animals');
check_it(strpos($helper, "['equal', 'custom']") !== false, 'Equal and custom allocation methods are supported');
check_it(strpos($helper, "Choose a specific ruminant production type") !== false, 'Individual allocation cannot remain species-shared');
check_it(strpos($helper, "Selected animals must match the expense production type") !== false, 'Selected animals are species validated');
check_it(strpos($helper, 'Custom animal allocations must add up exactly') !== false, 'Custom allocation must reconcile to expense total');
check_it(strpos($helper, 'DELETE FROM ruminant_expense_animal_allocations') !== false, 'Edit replaces allocation rows without duplicating the expense');
check_it(strpos($page, 'Shared cost — no individual animal allocation') !== false, 'Ruminant expense UI supports shared cost without individual animal allocation');
check_it(strpos($page, 'Selected animals — Equal split') !== false, 'Ruminant expense UI supports equal split');
check_it(strpos($page, 'Selected animals — Custom split') !== false, 'Ruminant expense UI supports custom split');
check_it(strpos($page, 'Animal Attribution') !== false, 'Expense history exposes animal attribution transparently');
check_it(strpos($page, 'ruminant_expense_save_animal_allocations') !== false, 'New expense saves animal allocations');
check_it(strpos($api, 'ruminant_expense_save_animal_allocations') !== false, 'Edited expense saves animal allocations');
check_it(strpos($api, '$pdo->beginTransaction()') !== false && strpos($api, '$pdo->commit()') !== false, 'Expense edit and allocation update are atomic');
check_it(strpos($api, '$isLegacyMedicationEdit') !== false, 'Manual medication remains legacy-edit only');
$failed = count(array_filter($checks, fn($c) => !$c[0]));
echo "\n" . count($checks) . " checks, {$failed} failed.\n";
exit($failed ? 1 : 0);
