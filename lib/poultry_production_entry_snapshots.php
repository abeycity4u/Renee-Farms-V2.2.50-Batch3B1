<?php
require_once __DIR__ . '/poultry_rearing_economics.php';
require_once dirname(__DIR__) . '/includes/audit_helpers.php';

/**
 * V2.2.50 Batch 3B — approved Production-Entry Economic Basis.
 *
 * Source records remain canonical. Approved snapshots are immutable application
 * records: a later correction creates a new version; it never overwrites V1.
 */
if (!function_exists('poultry_production_entry_candidate')) {
function poultry_production_entry_candidate(PDO $pdo, int $farmId, int $cycleId): array
{
    $e = poultry_rearing_economics($pdo, $farmId, $cycleId);
    $candidate = [
        'ready'=>false, 'mode'=>$e['mode'] ?? 'not_available', 'reason'=>'',
        'production_entry_date'=>null, 'rearing_start_date'=>null, 'rearing_end_date'=>null,
        'acquisition_basis'=>0.0, 'feed_consumed_cost'=>0.0, 'operating_inventory_cost'=>0.0,
        'direct_expenses'=>0.0, 'explicit_shared_allocations'=>0.0,
        'attributed_investment'=>null, 'production_entry_headcount'=>null,
        'investment_per_entry_bird'=>null, 'unallocated_shared_cost_pool'=>0.0,
        'source_fingerprint'=>null, 'economics'=>$e,
    ];
    if (empty($e['available'])) {
        $candidate['reason'] = $e['message'] ?? 'Production-entry economics are not available.';
        return $candidate;
    }

    if (($e['mode'] ?? '') === 'pol') {
        if (empty($e['production_phase']['start_date'])) {
            $candidate['reason']='Production lifecycle phase must be recorded before a Point-of-Lay production-entry basis can be approved.';
            return $candidate;
        }
        if ($e['rearing_investment'] === null || empty($e['production_entry_headcount'])) {
            $candidate['reason']='Point-of-Lay acquisition cost and quantity must be complete before approval.';
            return $candidate;
        }
        $candidate['production_entry_date']=(string)$e['production_phase']['start_date'];
        $candidate['acquisition_basis']=round((float)$e['acquisition_cost'],2);
        $candidate['attributed_investment']=round((float)$e['rearing_investment'],2);
        $candidate['production_entry_headcount']=(int)$e['production_entry_headcount'];
        $candidate['investment_per_entry_bird']=round((float)$e['investment_per_surviving_bird'],2);
    } else {
        if ($e['rearing_investment'] === null) {
            $candidate['reason']='Attributed rearing investment is incomplete. Resolve missing acquisition or uncosted source records before approval.';
            return $candidate;
        }
        if (empty($e['production_entry_headcount']) || empty($e['production_phase']['start_date'])) {
            $candidate['reason']='A reconciled production-entry flock boundary is required before approval.';
            return $candidate;
        }
        $candidate['production_entry_date']=(string)$e['production_phase']['start_date'];
        $candidate['rearing_start_date']=(string)$e['rearing_phase']['start_date'];
        $candidate['rearing_end_date']=(string)$e['rearing_phase']['end_date'];
        $candidate['acquisition_basis']=round((float)$e['acquisition_cost'],2);
        $candidate['feed_consumed_cost']=round((float)$e['feed_consumed_cost'],2);
        $candidate['operating_inventory_cost']=round((float)$e['inventory_operating_cost'],2);
        $candidate['direct_expenses']=round((float)$e['direct_expenses'],2);
        $candidate['explicit_shared_allocations']=round((float)$e['allocated_shared_expenses'],2);
        $candidate['attributed_investment']=round((float)$e['rearing_investment'],2);
        $candidate['production_entry_headcount']=(int)$e['production_entry_headcount'];
        $candidate['investment_per_entry_bird']=round((float)$e['investment_per_surviving_bird'],2);
        $candidate['unallocated_shared_cost_pool']=round((float)$e['unallocated_shared_expense_pool'],2);
    }

    // Fingerprint the source-derived economic facts, not presentation text.
    $fingerprintFacts=[
        'mode'=>$candidate['mode'],
        'production_entry_date'=>$candidate['production_entry_date'],
        'rearing_start_date'=>$candidate['rearing_start_date'],
        'rearing_end_date'=>$candidate['rearing_end_date'],
        'acquisition_basis'=>$candidate['acquisition_basis'],
        'feed_consumed_cost'=>$candidate['feed_consumed_cost'],
        'operating_inventory_cost'=>$candidate['operating_inventory_cost'],
        'direct_expenses'=>$candidate['direct_expenses'],
        'explicit_shared_allocations'=>$candidate['explicit_shared_allocations'],
        'attributed_investment'=>$candidate['attributed_investment'],
        'production_entry_headcount'=>$candidate['production_entry_headcount'],
        'investment_per_entry_bird'=>$candidate['investment_per_entry_bird'],
        'unallocated_shared_cost_pool'=>$candidate['unallocated_shared_cost_pool'],
    ];
    $candidate['source_fingerprint']=hash('sha256', json_encode($fingerprintFacts, JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION));
    $candidate['ready']=true;
    return $candidate;
}
}

if (!function_exists('poultry_production_entry_snapshots')) {
function poultry_production_entry_snapshots(PDO $pdo,int $farmId,int $cycleId): array
{
    $s=$pdo->prepare("SELECT s.*,u.username approved_by_name
        FROM poultry_production_entry_snapshots s
        LEFT JOIN users u ON u.id=s.approved_by
        WHERE s.farm_id=? AND s.cycle_id=? ORDER BY s.version_no DESC");
    $s->execute([$farmId,$cycleId]);
    return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
}

if (!function_exists('poultry_production_entry_approve')) {
function poultry_production_entry_approve(PDO $pdo,int $farmId,int $cycleId,int $userId,string $category,string $reason=''): int
{
    $allowed=[
        'production_entry_confirmation','source_transaction_correction','missing_historical_transaction',
        'allocation_correction','production_entry_headcount_correction','administrative_correction'
    ];
    if (!in_array($category,$allowed,true)) throw new InvalidArgumentException('Select a valid revision category.');
    $reason=trim($reason);
    $candidate=poultry_production_entry_candidate($pdo,$farmId,$cycleId);
    if (empty($candidate['ready'])) throw new RuntimeException($candidate['reason'] ?: 'Production-entry economic basis is not ready for approval.');

    $pdo->beginTransaction();
    try {
        $lock=$pdo->prepare("SELECT * FROM poultry_production_entry_snapshots WHERE farm_id=? AND cycle_id=? ORDER BY version_no DESC LIMIT 1 FOR UPDATE");
        $lock->execute([$farmId,$cycleId]);
        $previous=$lock->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($previous && hash_equals((string)$previous['source_fingerprint'],(string)$candidate['source_fingerprint'])) {
            throw new RuntimeException('The source-derived economic basis has not changed since the latest approved version.');
        }
        if (!$previous && $category!=='production_entry_confirmation') {
            throw new RuntimeException('The first approved basis must use Production-entry confirmation.');
        }
        if ($previous && $category==='production_entry_confirmation') {
            throw new RuntimeException('A later version must identify the correction category.');
        }
        if ($previous && $reason==='') {
            throw new RuntimeException('A revision reason is required for a corrected economic basis.');
        }

        $version=$previous ? ((int)$previous['version_no']+1) : 1;
        $status=$previous ? 'revised' : 'original';
        $model=$candidate['mode']==='pol'?'purchased_point_of_lay':'farm_reared';
        $sql="INSERT INTO poultry_production_entry_snapshots
          (farm_id,cycle_id,version_no,snapshot_status,entry_model,production_entry_date,rearing_start_date,rearing_end_date,
           acquisition_basis,feed_consumed_cost,operating_inventory_cost,direct_expenses,explicit_shared_allocations,
           attributed_investment,production_entry_headcount,investment_per_entry_bird,unallocated_shared_cost_pool,
           source_fingerprint,revision_category,revision_reason,previous_snapshot_id,approved_by)
          VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $st=$pdo->prepare($sql);
        $st->execute([
            $farmId,$cycleId,$version,$status,$model,$candidate['production_entry_date'],
            $candidate['rearing_start_date'],$candidate['rearing_end_date'],$candidate['acquisition_basis'],
            $candidate['feed_consumed_cost'],$candidate['operating_inventory_cost'],$candidate['direct_expenses'],
            $candidate['explicit_shared_allocations'],$candidate['attributed_investment'],$candidate['production_entry_headcount'],
            $candidate['investment_per_entry_bird'],$candidate['unallocated_shared_cost_pool'],$candidate['source_fingerprint'],
            $category,$reason!==''?$reason:null,$previous?(int)$previous['id']:null,$userId?:null
        ]);
        $id=(int)$pdo->lastInsertId();
        audit_log_event('poultry_production_entry_basis_approved','poultry_production_entry_snapshot',$id,[
            'cycle_id'=>$cycleId,'version'=>$version,'status'=>$status,'source_fingerprint'=>$candidate['source_fingerprint'],
            'attributed_investment'=>$candidate['attributed_investment'],'production_entry_headcount'=>$candidate['production_entry_headcount'],
            'investment_per_entry_bird'=>$candidate['investment_per_entry_bird'],'revision_category'=>$category
        ]);
        $pdo->commit();
        return $id;
    } catch(Throwable $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
}
