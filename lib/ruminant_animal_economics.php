<?php
require_once __DIR__.'/ruminant_shared_cost_economics.php';
/**
 * V2.2.48 — Individual ruminant animal economics.
 *
 * This helper deliberately reports DIRECTLY attributable economics only:
 * purchase cost + explicit expense allocations versus explicit sale allocations.
 * Herd/cycle shared costs, feed consumed at herd level, and inventory usage that has
 * not been explicitly allocated to an animal are not silently divided across animals.
 */

function ruminant_animal_economics(PDO $pdo, int $farmId, int $animalId): array
{
    $animalStmt = $pdo->prepare('SELECT id, tag_no, species, status, purchase_date, purchase_cost FROM ruminant_animals WHERE id=? AND farm_id=? LIMIT 1');
    $animalStmt->execute([$animalId, $farmId]);
    $animal = $animalStmt->fetch(PDO::FETCH_ASSOC);
    if (!$animal) {
        throw new RuntimeException('Animal not found.');
    }

    $expenseStmt = $pdo->prepare("SELECT a.expense_id, a.allocation_method, a.allocation_percent, a.allocated_amount,
            e.expense_date, e.category, e.description, e.production_type, e.cycle_id,
            pc.cycle_code, pc.production_type AS cycle_production_type
        FROM ruminant_expense_animal_allocations a
        JOIN farm_expenses e ON e.id=a.expense_id AND e.farm_id=a.farm_id
        LEFT JOIN production_cycles pc ON pc.id=e.cycle_id AND pc.farm_id=e.farm_id
        WHERE a.farm_id=? AND a.animal_id=?
        ORDER BY e.expense_date ASC, a.expense_id ASC");
    $expenseStmt->execute([$farmId, $animalId]);
    $expenses = $expenseStmt->fetchAll(PDO::FETCH_ASSOC);

    $revenueStmt = $pdo->prepare("SELECT a.sale_id, a.allocation_method, a.allocation_percent, a.allocated_amount,
            s.sale_date, s.product_type, s.quantity, s.unit_of_measure, s.unit_price, s.customer_name,
            s.production_type, s.cycle_id, pc.cycle_code,
            xe.exit_outcome
        FROM ruminant_sale_animal_allocations a
        JOIN sales_records s ON s.id=a.sale_id AND s.farm_id=a.farm_id
        LEFT JOIN production_cycles pc ON pc.id=s.cycle_id AND pc.farm_id=s.farm_id
        LEFT JOIN ruminant_animal_exit_events xe ON xe.sale_id=a.sale_id AND xe.animal_id=a.animal_id AND xe.farm_id=a.farm_id
        WHERE a.farm_id=? AND a.animal_id=?
        ORDER BY s.sale_date ASC, a.sale_id ASC");
    $revenueStmt->execute([$farmId, $animalId]);
    $revenues = $revenueStmt->fetchAll(PDO::FETCH_ASSOC);

    $purchaseCost = round((float)$animal['purchase_cost'], 2);
    $directExpenseTotal = 0.0;
    foreach ($expenses as $row) {
        $directExpenseTotal += (float)$row['allocated_amount'];
    }
    $directExpenseTotal = round($directExpenseTotal, 2);

    $revenueTotal = 0.0;
    $exitRevenueTotal = 0.0;
    foreach ($revenues as $row) {
        $amount = (float)$row['allocated_amount'];
        $revenueTotal += $amount;
        if (!empty($row['exit_outcome'])) {
            $exitRevenueTotal += $amount;
        }
    }
    $revenueTotal = round($revenueTotal, 2);
    $exitRevenueTotal = round($exitRevenueTotal, 2);
    $postOrNonExitRevenue = round($revenueTotal - $exitRevenueTotal, 2);

    $directCostTotal = round($purchaseCost + $directExpenseTotal, 2);
    $directNet = round($revenueTotal - $directCostTotal, 2);
    $roi = $directCostTotal > 0 ? round(($directNet / $directCostTotal) * 100, 2) : null;

    $shared = ruminant_shared_cost_economics($pdo, $farmId, $animalId, (string)$animal['species']);
    $allocatedSharedCost = round((float)($shared['allocated_shared_cost'] ?? 0), 2);
    $fullyAllocatedCost = round($directCostTotal + $allocatedSharedCost, 2);
    $fullyAllocatedNet = round($revenueTotal - $fullyAllocatedCost, 2);
    $fullyAllocatedRoi = $fullyAllocatedCost > 0 ? round(($fullyAllocatedNet / $fullyAllocatedCost) * 100, 2) : null;

    return [
        'animal' => $animal,
        'purchase_cost' => $purchaseCost,
        'direct_expense_total' => $directExpenseTotal,
        'direct_cost_total' => $directCostTotal,
        'revenue_total' => $revenueTotal,
        'exit_revenue_total' => $exitRevenueTotal,
        'non_exit_revenue_total' => $postOrNonExitRevenue,
        'direct_net_position' => $directNet,
        'direct_roi_percent' => $roi,
        'allocated_shared_cost' => $allocatedSharedCost,
        'fully_allocated_cost_total' => $fullyAllocatedCost,
        'fully_allocated_net_position' => $fullyAllocatedNet,
        'fully_allocated_roi_percent' => $fullyAllocatedRoi,
        'shared_cost_rows' => $shared['shared_cost_rows'] ?? [],
        'uncovered_species_shared_cost' => (float)($shared['uncovered_species_shared_cost'] ?? 0),
        'shared_allocation_method' => $shared['method'] ?? 'Active headcount on each transaction date',
        'expenses' => $expenses,
        'revenues' => $revenues,
        'shared_costs_included' => true,
    ];
}
