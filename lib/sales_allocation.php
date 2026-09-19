<?php
/**
 * Revenue allocation helpers.
 *
 * A pooled sale remains one immutable source sale. Allocation rows are only
 * reporting ownership: they never duplicate or rewrite the source revenue.
 */
require_once __DIR__ . '/layer_egg_inventory.php';
require_once __DIR__ . '/sale_revenue_allocation_provenance.php';


if (!class_exists(
    'SaleRevenueAllocationLifecycleException'
)) {
    class SaleRevenueAllocationLifecycleException
        extends RuntimeException
    {
    }
}


function sales_manual_revenue_allocation_require_transaction(
    PDO $pdo
): void {
    if (!$pdo->inTransaction()) {
        throw new SaleRevenueAllocationLifecycleException(
            'Manual shared revenue lifecycle protection requires an active transaction.'
        );
    }
}


function sales_lock_sale_for_manual_revenue_lifecycle(
    PDO $pdo,
    int $farmId,
    int $saleId
): array {
    sales_manual_revenue_allocation_require_transaction(
        $pdo
    );

    $stmt =
        $pdo->prepare(
            "SELECT *
             FROM sales_records
             WHERE farm_id=?
               AND id=?
             LIMIT 1
             FOR UPDATE"
        );

    $stmt->execute([
        $farmId,
        $saleId,
    ]);

    $sale =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$sale) {
        throw new SaleRevenueAllocationLifecycleException(
            'Sale record was not found for revenue-allocation lifecycle protection.'
        );
    }

    return $sale;
}


function sales_manual_revenue_allocation_state(
    PDO $pdo,
    int $farmId,
    int $saleId,
    bool $forUpdate = false
): array {
    if (
        $farmId < 1
        ||
        $saleId < 1
    ) {
        throw new InvalidArgumentException(
            'Sale revenue lifecycle identity is invalid.'
        );
    }

    if ($forUpdate) {
        sales_manual_revenue_allocation_require_transaction(
            $pdo
        );
    }

    $projectionSql =
        "SELECT
             id,
             allocation_basis
         FROM sales_allocations
         WHERE farm_id=?
           AND sale_id=?
         ORDER BY id";

    if ($forUpdate) {
        $projectionSql .=
            " FOR UPDATE";
    }

    $projectionStmt =
        $pdo->prepare(
            $projectionSql
        );

    $projectionStmt->execute([
        $farmId,
        $saleId,
    ]);

    $projectionRows =
        $projectionStmt->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];

    $manualRows = 0;
    $otherRows = 0;

    foreach ($projectionRows as $row) {
        $basis =
            strtolower(
                trim(
                    (string)(
                        $row['allocation_basis']
                        ?? ''
                    )
                )
            );

        if ($basis === 'manual_shared_revenue') {
            $manualRows++;
        } else {
            $otherRows++;
        }
    }

    /*
     * Individual-animal revenue allocation is a separate mutually-exclusive
     * authority. Lock it before revision rows, matching the canonical writer's
     * parent -> projection -> animal -> revision lock order.
     */
    $animalSql =
        "SELECT id
         FROM ruminant_sale_animal_allocations
         WHERE farm_id=?
           AND sale_id=?
         ORDER BY id";

    if ($forUpdate) {
        $animalSql .=
            " FOR UPDATE";
    }

    $animalStmt =
        $pdo->prepare(
            $animalSql
        );

    $animalStmt->execute([
        $farmId,
        $saleId,
    ]);

    $animalRows =
        $animalStmt->fetchAll(
            PDO::FETCH_COLUMN
        ) ?: [];

    $revisionSql =
        "SELECT
             id,
             revision_no,
             revision_action
         FROM sales_allocation_revisions
         WHERE farm_id=?
           AND sale_id=?
         ORDER BY id";

    if ($forUpdate) {
        $revisionSql .=
            " FOR UPDATE";
    }

    $revisionStmt =
        $pdo->prepare(
            $revisionSql
        );

    $revisionStmt->execute([
        $farmId,
        $saleId,
    ]);

    $revisionRows =
        $revisionStmt->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];

    $revisionCount =
        count(
            $revisionRows
        );

    return [
        'manual_row_count' =>
            $manualRows,

        'other_row_count' =>
            $otherRows,

        'animal_row_count' =>
            count(
                $animalRows
            ),

        'revision_count' =>
            $revisionCount,

        'protected' =>
            $manualRows > 0
            ||
            $revisionCount > 0,

        'unprovenanced_manual_projection' =>
            $manualRows > 0
            &&
            $revisionCount === 0,
    ];
}


function sales_manual_revenue_allocation_assert_consistent(
    array $state
): void {
    if (
        !empty(
            $state['unprovenanced_manual_projection']
        )
    ) {
        throw new SaleRevenueAllocationLifecycleException(
            'Manual shared revenue allocation exists without immutable revision provenance.'
        );
    }

    if (
        !empty(
            $state['protected']
        )
        &&
        (int)(
            $state['other_row_count']
            ?? 0
        ) > 0
    ) {
        throw new SaleRevenueAllocationLifecycleException(
            'This sale has conflicting revenue allocation ownership.'
        );
    }

    if (
        !empty(
            $state['protected']
        )
        &&
        (int)(
            $state['animal_row_count']
            ?? 0
        ) > 0
    ) {
        throw new SaleRevenueAllocationLifecycleException(
            'Manual cycle revenue allocation cannot overlap individual-animal revenue allocation.'
        );
    }
}


function sales_assert_manual_revenue_edit_allowed(
    PDO $pdo,
    int $farmId,
    int $saleId,
    array $proposedSale,
    int $proposedAnimalAllocationCount = 0
): array {
    $current =
        sales_lock_sale_for_manual_revenue_lifecycle(
            $pdo,
            $farmId,
            $saleId
        );

    $state =
        sales_manual_revenue_allocation_state(
            $pdo,
            $farmId,
            $saleId,
            true
        );

    sales_manual_revenue_allocation_assert_consistent(
        $state
    );

    if (empty($state['protected'])) {
        return $current;
    }

    if ($proposedAnimalAllocationCount > 0) {
        throw new SaleRevenueAllocationLifecycleException(
            'A sale with manual shared revenue allocation history cannot be changed to individual-animal revenue allocation.'
        );
    }

    $currentManifest =
        sale_revenue_allocation_provenance_parent(
            $current
        );

    $proposedManifest =
        sale_revenue_allocation_provenance_parent(
            array_merge(
                $current,
                $proposedSale
            )
        );

    $criticalFields = [
        'sale_date',
        'farm_type',
        'production_type',
        'attribution_scope',
        'cycle_id',
        'product_type',
        'quantity',
        'unit_of_measure',
        'unit_price',
        'total_amount',
    ];

    $changed = [];

    foreach ($criticalFields as $field) {
        if (
            (
                $currentManifest[$field]
                ?? null
            )
            !==
            (
                $proposedManifest[$field]
                ?? null
            )
        ) {
            $changed[] =
                $field;
        }
    }

    if ($changed) {
        throw new SaleRevenueAllocationLifecycleException(
            'This sale has manual shared revenue allocation history. Allocation-critical sale fields cannot be edited from Sales Records.'
        );
    }

    return $current;
}


function sales_assert_manual_revenue_delete_allowed(
    PDO $pdo,
    int $farmId,
    int $saleId
): void {
    sales_lock_sale_for_manual_revenue_lifecycle(
        $pdo,
        $farmId,
        $saleId
    );

    $state =
        sales_manual_revenue_allocation_state(
            $pdo,
            $farmId,
            $saleId,
            true
        );

    sales_manual_revenue_allocation_assert_consistent(
        $state
    );

    if (!empty($state['protected'])) {
        throw new SaleRevenueAllocationLifecycleException(
            'Sales with manual shared revenue allocation history cannot be deleted.'
        );
    }
}


function sales_allocation_status(PDO $pdo, int $farmId, int $saleId, float $saleTotal): array
{
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(allocated_amount),0) FROM sales_allocations WHERE farm_id=? AND sale_id=?");
    $stmt->execute([$farmId, $saleId]);
    $allocated = (float)$stmt->fetchColumn();
    $epsilon = 0.01;
    if ($allocated <= $epsilon) $status = 'unallocated';
    elseif ($allocated + $epsilon < $saleTotal) $status = 'partial';
    else $status = 'allocated';
    return ['status'=>$status,'allocated_amount'=>$allocated,'unallocated_amount'=>max(0,$saleTotal-$allocated)];
}

function sales_clear_allocations(PDO $pdo, int $farmId, int $saleId): void
{
    $state =
        sales_manual_revenue_allocation_state(
            $pdo,
            $farmId,
            $saleId,
            $pdo->inTransaction()
        );

    sales_manual_revenue_allocation_assert_consistent(
        $state
    );

    if (!empty($state['protected'])) {
        throw new SaleRevenueAllocationLifecycleException(
            'Manual shared revenue allocation history protects this sale from automatic allocation clearing.'
        );
    }

    $stmt = $pdo->prepare("DELETE FROM sales_allocations WHERE farm_id=? AND sale_id=?");
    $stmt->execute([$farmId,$saleId]);
}

/**
 * Auto-allocate a pooled Layer egg sale from the UNSOLD egg pool, not merely
 * production recorded on the sale date. This correctly handles delayed and
 * partial sales while excluding rearing/non-producing cycles naturally.
 */
function sales_auto_allocate_layer_egg(PDO $pdo, int $farmId, int $saleId, string $saleDate, float $saleTotal, ?int $userId=null): array
{
    $saleStmt = $pdo->prepare("SELECT quantity,unit_of_measure FROM sales_records WHERE id=? AND farm_id=? LIMIT 1");
    $saleStmt->execute([$saleId,$farmId]);
    $saleRow = $saleStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $rawSaleQty = (float)($saleRow['quantity'] ?? 0);
    $saleQty = layer_egg_sale_quantity_crates($rawSaleQty, $saleRow['unit_of_measure'] ?? null);
    sales_clear_allocations($pdo,$farmId,$saleId);

    if ($saleQty === null) {
        sales_clear_allocations($pdo,$farmId,$saleId);
        return ['status'=>'unallocated','allocated_amount'=>0.0,'cycles'=>0,'reason'=>'This Layer egg sale uses a unit that cannot be safely converted to crates. Use Crate/Tray, Piece, or Dozen for automatic egg-pool allocation.'];
    }
    if ($saleTotal <= 0 || $saleQty <= 0) {
        return ['status'=>'unallocated','allocated_amount'=>0.0,'cycles'=>0,'reason'=>'Sale quantity or total is zero.'];
    }

    $pool = layer_egg_pool_before_sale($pdo,$farmId,$saleDate,$saleId);
    $eligible = array_values(array_filter($pool, static fn($row)=>(float)$row['available_crates'] > 0.0001));
    $totals = layer_egg_pool_totals($eligible);
    $available = (float)$totals['available_crates'];
    if ($available <= 0.0001) {
        return ['status'=>'unallocated','allocated_amount'=>0.0,'cycles'=>0,'reason'=>'No unsold Layer egg production is available for this sale date.','available_quantity'=>0.0,'sale_quantity'=>$saleQty];
    }
    if ($saleQty > $available + 0.01) {
        return ['status'=>'unallocated','allocated_amount'=>0.0,'cycles'=>count($eligible),'reason'=>sprintf('Recorded unsold egg stock is %.2f crates, below the %.2f crates sold.',$available,$saleQty),'available_quantity'=>$available,'sale_quantity'=>$saleQty];
    }

    $insert = $pdo->prepare("INSERT INTO sales_allocations
        (farm_id,sale_id,cycle_id,allocation_percent,allocated_quantity,allocation_unit,allocated_amount,allocation_basis,notes,created_by)
        VALUES (?,?,?,?,?,'crate',?,'layer_unsold_egg_pool',?,?)");
    $remainingAmount = round($saleTotal,2);
    $remainingQty = round($saleQty,4);
    $last = count($eligible)-1;
    foreach ($eligible as $i=>$row) {
        $share = (float)$row['available_crates'] / $available;
        $qty = $i===$last ? $remainingQty : round($saleQty*$share,4);
        $amount = $i===$last ? $remainingAmount : round($saleTotal*$share,2);
        $remainingQty = round($remainingQty-$qty,4);
        $remainingAmount = round($remainingAmount-$amount,2);
        $insert->execute([
            $farmId,$saleId,(int)$row['cycle_id'],round($share*100,4),$qty,$amount,
            'Auto-allocated from each Layer cycle\'s unsold egg pool as of '.$saleDate,
            $userId ?: null,
        ]);
    }
    return ['status'=>'allocated','allocated_amount'=>round($saleTotal,2),'allocated_quantity'=>round($saleQty,4),'cycles'=>count($eligible),'reason'=>'Allocated from accumulated unsold Layer egg production.','available_before'=>$available];
}

function sales_refresh_automatic_allocation(PDO $pdo, int $farmId, int $saleId, ?int $userId=null): array
{
    $sql = "SELECT id,sale_date,farm_type,production_type,cycle_id,product_type,total_amount FROM sales_records WHERE id=? AND farm_id=? LIMIT 1";

    if ($pdo->inTransaction()) {
        $sql .= " FOR UPDATE";
    }

    $stmt=$pdo->prepare($sql);
    $stmt->execute([$saleId,$farmId]);
    $sale=$stmt->fetch(PDO::FETCH_ASSOC);

    if(!$sale) {
        throw new RuntimeException(
            'Sale was not found for allocation.'
        );
    }

    $state =
        sales_manual_revenue_allocation_state(
            $pdo,
            $farmId,
            $saleId,
            $pdo->inTransaction()
        );

    sales_manual_revenue_allocation_assert_consistent(
        $state
    );

    $isDirect =
        !empty(
            $sale['cycle_id']
        );

    $isAutomaticLayerEgg =
        $sale['farm_type'] === 'poultry'
        &&
        strtolower(
            (string)$sale['production_type']
        ) === 'layer'
        &&
        layer_egg_is_sale_product(
            $sale['product_type']
            ?? null
        );

    if (!empty($state['protected'])) {
        if (
            $isDirect
            ||
            $isAutomaticLayerEgg
        ) {
            throw new SaleRevenueAllocationLifecycleException(
                'Manual shared revenue allocation history prevents changing this sale to direct or automatic allocation authority.'
            );
        }

        $status =
            sales_allocation_status(
                $pdo,
                $farmId,
                $saleId,
                (float)$sale['total_amount']
            );

        $status['cycles'] =
            (int)$state['manual_row_count'];

        $status['allocation_basis'] =
            'manual_shared_revenue';

        $status['reason'] =
            'Manual shared revenue allocation was preserved; automatic refresh was skipped.';

        return $status;
    }

    if($isDirect) {
        sales_clear_allocations($pdo,$farmId,$saleId);

        return [
            'status'=>'direct',
            'allocated_amount'=>(float)$sale['total_amount'],
            'cycles'=>1,
            'reason'=>'Sale is directly assigned to a cycle.'
        ];
    }

    if($isAutomaticLayerEgg) {
        return sales_auto_allocate_layer_egg(
            $pdo,
            $farmId,
            $saleId,
            (string)$sale['sale_date'],
            (float)$sale['total_amount'],
            $userId
        );
    }

    sales_clear_allocations($pdo,$farmId,$saleId);

    return [
        'status'=>'unallocated',
        'allocated_amount'=>0.0,
        'cycles'=>0,
        'reason'=>'This shared sale has no automatic allocation basis.'
    ];
}

/**
 * Rebuild pooled Layer egg allocations chronologically from a date. Call this
 * whenever a Layer Daily Record or Layer egg sale on/after that date changes.
 */
function sales_rebuild_layer_egg_allocations(PDO $pdo, int $farmId, string $fromDate='1000-01-01', ?int $userId=null): array
{
    $stmt=$pdo->prepare("SELECT id,product_type FROM sales_records
                         WHERE farm_id=? AND farm_type='poultry' AND production_type='layer'
                           AND cycle_id IS NULL AND sale_date>=?
                         ORDER BY sale_date,id");
    $stmt->execute([$farmId,$fromDate]);
    $rebuilt=$allocated=$unallocated=0;
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if(!layer_egg_is_sale_product($row['product_type']??null)) continue;
        $result=sales_refresh_automatic_allocation($pdo,$farmId,(int)$row['id'],$userId);
        $rebuilt++;
        if(($result['status']??'')==='allocated') $allocated++; else $unallocated++;
    }
    return ['rebuilt'=>$rebuilt,'allocated'=>$allocated,'unallocated'=>$unallocated];
}
