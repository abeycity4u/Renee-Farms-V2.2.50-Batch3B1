<?php
require_once __DIR__.'/ruminant_cycle_membership.php';
require_once __DIR__.'/inventory_financial.php';
require_once __DIR__.'/stock_reporting.php';
require_once __DIR__.'/stock_consumption_economics.php';

/**
 * Analytical allocation of shared ruminant operating costs.
 *
 * Standard driver: equal active headcount on each source transaction date.
 * Across a reporting period this naturally becomes animal-days: an animal only
 * receives a share on dates it is explicitly a member of the relevant cycle.
 * No weight interpolation or hidden lifecycle inference is performed.
 */
function ruminant_shared_cost_share_for_target(float $poolAmount, array $eligibleAnimalIds, int $targetAnimalId): float
{
    $eligibleAnimalIds=array_values(array_unique(array_map('intval',$eligibleAnimalIds)));
    sort($eligibleAnimalIds,SORT_NUMERIC);
    $idx=array_search($targetAnimalId,$eligibleAnimalIds,true);
    if($idx===false || !$eligibleAnimalIds) return 0.0;
    $totalCents=(int)round(round($poolAmount,2)*100);
    if($totalCents<=0) return 0.0;
    $count=count($eligibleAnimalIds);
    $base=intdiv($totalCents,$count);
    $remainder=$totalCents-($base*$count);
    return ($base + ($idx < $remainder ? 1 : 0))/100;
}


function ruminant_shared_cost_stock_row_from_economics(
    array $row,
    string $species
): ?array {
    $species =
        strtolower(
            trim($species)
        );

    $mode =
        strtolower(
            trim(
                (string)(
                    $row[
                        'attribution_mode'
                    ]
                    ?? ''
                )
            )
        );

    if (
        !in_array(
            $mode,
            [
                'native_parent',
                'explicit_allocation',
                'unallocated_remainder',
            ],
            true
        )
    ) {
        throw new RuntimeException(
            'Consumed-stock economics returned an unsupported Ruminant attribution mode.'
        );
    }

    $amount =
        round(
            (float)(
                $row[
                    'economic_amount'
                ]
                ?? 0
            ),
            2
        );

    if ($amount <= 0) {
        return null;
    }

    $stockTransactionId =
        (int)(
            $row[
                'stock_transaction_id'
            ]
            ?? $row['id']
            ?? 0
        );

    if ($stockTransactionId < 1) {
        throw new RuntimeException(
            'Consumed-stock economics returned an invalid stock source identity.'
        );
    }

    $date =
        substr(
            (string)(
                $row[
                    'transaction_date'
                ]
                ?? ''
            ),
            0,
            10
        );

    if (
        preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $date
        ) !== 1
    ) {
        throw new RuntimeException(
            'Consumed-stock economics returned an invalid stock source date.'
        );
    }

    $classification =
        strtolower(
            trim(
                (string)(
                    $row[
                        'cost_classification'
                    ]
                    ?? $row[
                        'financial_classification'
                    ]
                    ?? ''
                )
            )
        );

    if ($classification === '') {
        throw new RuntimeException(
            'Consumed-stock economics returned no operating classification.'
        );
    }

    /*
     * Cycle boundary policy:
     *
     * explicit allocation:
     *     allocation target controls animal membership;
     *
     * native parent:
     *     preserve an already-direct cycle where present;
     *
     * unallocated remainder:
     *     stays species-wide and must never inherit an allocation cycle.
     */
    $cycleId = null;

    if ($mode === 'explicit_allocation') {
        $cycleId =
            (int)(
                $row[
                    'target_cycle_id'
                ]
                ?? 0
            );

        if ($cycleId < 1) {
            throw new RuntimeException(
                'Explicit consumed-stock allocation has no target Ruminant cycle.'
            );
        }

    } elseif ($mode === 'native_parent') {
        $nativeCycleId =
            (int)(
                $row[
                    'cycle_id'
                ]
                ?? 0
            );

        $cycleId =
            $nativeCycleId > 0
                ? $nativeCycleId
                : null;
    }

    $cycleCode = null;

    if ($cycleId !== null) {
        $cycleCode =
            $row[
                'target_cycle_code'
            ]
            ?? $row[
                'cycle_code'
            ]
            ?? null;
    }

    $label =
        trim(
            (string)(
                $row[
                    'item_name'
                ]
                ?? ''
            )
        );

    if ($label === '') {
        $label =
            'Inventory use #'
            . $stockTransactionId;
    }

    return [
        'source_id' =>
            $stockTransactionId,

        'source_date' =>
            $date,

        /*
         * Preserve the existing consumer/UI source-type contract.
         */
        'source_type' =>
            'inventory_use',

        'source_label' =>
            $label,

        'classification' =>
            $classification,

        'pool_amount' =>
            $amount,

        'cycle_id' =>
            $cycleId,

        'cycle_code' =>
            $cycleCode,

        'production_type' =>
            $species,

        /*
         * Supplemental audit fields. Existing consumers can ignore them.
         */
        'stock_attribution_mode' =>
            $mode,

        'stock_allocation_id' =>
            (
                (int)(
                    $row[
                        'allocation_id'
                    ]
                    ?? 0
                ) > 0
            )
                ? (int)$row[
                    'allocation_id'
                ]
                : null,
    ];
}

function ruminant_shared_cost_economics(PDO $pdo, int $farmId, int $animalId, string $species): array
{
    $species=strtolower(trim($species));
    $rows=[];

    // Shared manual/non-stock expenses at species or species-cycle level only.
    // Expense rows already explicitly allocated to animals are excluded entirely.
    $expenseSql="SELECT e.id source_id,e.expense_date source_date,'expense' source_type,
                       COALESCE(NULLIF(e.description,''),e.category) source_label,
                       e.category classification,(e.amount*e.unit) pool_amount,e.cycle_id,pc.cycle_code,
                       LOWER(COALESCE(pc.production_type,e.production_type,'')) production_type
                FROM farm_expenses e
                LEFT JOIN production_cycles pc ON pc.id=e.cycle_id AND pc.farm_id=e.farm_id
                WHERE e.farm_id=? AND e.farm_type='ruminant' AND e.category<>'feeds'
                  AND LOWER(COALESCE(pc.production_type,e.production_type,''))=?
                  AND NOT EXISTS (SELECT 1 FROM ruminant_expense_animal_allocations ra WHERE ra.farm_id=e.farm_id AND ra.expense_id=e.id)";
    $stmt=$pdo->prepare($expenseSql); $stmt->execute([$farmId,$species]);
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $rows[]=$r;

    // Explicit financial allocation of a pooled expense to a species cycle.
    $allocSql="SELECT e.id source_id,e.expense_date source_date,'allocated_expense' source_type,
                     COALESCE(NULLIF(e.description,''),e.category) source_label,
                     e.category classification,fa.allocated_amount pool_amount,fa.cycle_id,pc.cycle_code,
                     LOWER(pc.production_type) production_type
              FROM financial_allocations fa
              JOIN farm_expenses e ON e.id=fa.expense_id AND e.farm_id=fa.farm_id
              JOIN production_cycles pc ON pc.id=fa.cycle_id AND pc.farm_id=fa.farm_id
              WHERE fa.farm_id=? AND e.cycle_id IS NULL AND e.category<>'feeds'
                AND pc.farm_type='ruminant' AND LOWER(pc.production_type)=?
                AND NOT EXISTS (SELECT 1 FROM ruminant_expense_animal_allocations ra WHERE ra.farm_id=e.farm_id AND ra.expense_id=e.id)";
    $stmt=$pdo->prepare($allocSql); $stmt->execute([$farmId,$species]);
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $rows[]=$r;

    /*
     * Shared consumed inventory comes from the single canonical economics
     * reader instead of rebuilding effective-ledger / classification /
     * allocation policy here.
     *
     * Ruminant Shared Cost is a historical all-date analytical reader, so the
     * complete MySQL DATE range is requested. Animal membership continues to
     * be evaluated on each source transaction date below.
     *
     * Decomposition is required:
     * - native species parent + cycle allocations
     *      => explicit cycle pieces + species-wide remainder;
     * - broader farm/module parent
     *      => only explicit Ruminant/species attribution enters this reader;
     * - conservation remains owned by stock_consumption_economics.php.
     */
    $stockStartDate =
        '1000-01-01';

    $stockEndDate =
        '9999-12-31';

    $decomposeNativeStockAllocations =
        true;

    $stockEconomicRows =
        stock_consumption_economics_rows(
            $pdo,
            $farmId,
            $stockStartDate,
            $stockEndDate,
            'ruminant',
            $species,
            null,
            $decomposeNativeStockAllocations
        );

    foreach (
        $stockEconomicRows
        as $stockEconomicRow
    ) {
        $mappedStockRow =
            ruminant_shared_cost_stock_row_from_economics(
                $stockEconomicRow,
                $species
            );

        if ($mappedStockRow !== null) {
            $rows[] =
                $mappedStockRow;
        }
    }

    usort($rows,static function($a,$b){
        $d=strcmp((string)$a['source_date'],(string)$b['source_date']);
        if($d!==0)return $d;
        $t=strcmp((string)$a['source_type'],(string)$b['source_type']);
        return $t!==0?$t:((int)$a['source_id']<=> (int)$b['source_id']);
    });

    $allocated=[]; $total=0.0; $eligiblePool=0.0;
    foreach($rows as $r){
        $amount=round((float)$r['pool_amount'],2);
        if($amount<=0) continue;
        $eligible=ruminant_cycle_eligible_animal_ids($pdo,$farmId,$species,(string)$r['source_date'],!empty($r['cycle_id'])?(int)$r['cycle_id']:null);
        if(!$eligible) continue; // visible as uncovered below
        $eligiblePool+=$amount;
        $share=ruminant_shared_cost_share_for_target($amount,$eligible,$animalId);
        if($share<=0) continue;
        $r['eligible_animal_count']=count($eligible);
        $r['allocated_amount']=$share;
        $r['allocation_method']='Active headcount on transaction date';
        $allocated[]=$r;
        $total+=$share;
    }

    /*
     * Species/cost-centre allocation exceptions.
     *
     * These rows are NOT automatically an individual-animal membership
     * failure. A row may already be explicitly allocated to another cycle
     * of the same species whose own membership coverage is empty.
     *
     * Such amounts stay outside the target animal's economics and retain
     * their exact source/cycle provenance for correction at the owning
     * allocation workspace.
     */
    $speciesAllocationExceptionCost =
        0.0;

    $speciesAllocationExceptionRows =
        [];

    foreach($rows as $r){
        $amount=round((float)$r['pool_amount'],2);
        if($amount<=0) continue;

        $eligible=ruminant_cycle_eligible_animal_ids(
            $pdo,
            $farmId,
            $species,
            (string)$r['source_date'],
            !empty($r['cycle_id'])
                ? (int)$r['cycle_id']
                : null
        );

        if(!$eligible) {
            $speciesAllocationExceptionCost +=
                $amount;

            $r[
                'species_allocation_exception_amount'
            ] =
                $amount;

            /*
             * Compatibility alias retained for existing navigation/read
             * contracts. New callers should use the explicit exception key.
             */
            $r['uncovered_amount'] =
                $amount;

            $r[
                'species_allocation_exception_reason'
            ] =
                !empty($r['cycle_id'])
                    ? 'cycle_has_no_eligible_animals'
                    : 'species_scope_has_no_eligible_animals';

            $speciesAllocationExceptionRows[] =
                $r;
        }
    }

    $speciesAllocationExceptionCost =
        round(
            $speciesAllocationExceptionCost,
            2
        );

    return [
        'allocated_shared_cost'=>round($total,2),
        'shared_cost_rows'=>$allocated,

        'species_allocation_exception_cost'=>
            $speciesAllocationExceptionCost,

        'species_allocation_exception_rows'=>
            $speciesAllocationExceptionRows,

        /*
         * Compatibility aliases for older callers.
         */
        'uncovered_species_shared_cost'=>
            $speciesAllocationExceptionCost,

        'uncovered_shared_cost_rows'=>
            $speciesAllocationExceptionRows,

        'eligible_species_pool'=>round($eligiblePool,2),
        'method'=>'Active headcount on each transaction date',
    ];
}
