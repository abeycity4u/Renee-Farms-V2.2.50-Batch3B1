<?php
require_once __DIR__.'/../init.php';
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/audit_helpers.php';
require_once __DIR__.'/../lib/ruminant_lifecycle_service.php';
require_once __DIR__.'/../lib/ruminant_lifecycle_integrity.php';
requireLogin();
ensureAllowed('ruminant_daily');
$farmId=requireCurrentFarmId();
$canManage=isPlatformOwner()||hasRole('farm_admin')||hasRole('ruminant_manager');

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!verify_csrf_token($_POST['csrf_token']??'')){ http_response_code(419); exit('Invalid request token.'); }
    if(!$canManage){ http_response_code(403); exit('Access denied.'); }
    if(($_POST['action']??'')==='repair_membership'){
        $animalId=(int)($_POST['animal_id']??0);
        $membershipId=(int)($_POST['membership_id']??0);
        try{
            $result=ruminant_lifecycle_repair_open_membership($pdo,$farmId,$animalId,$membershipId);
            audit_log_event('update','ruminant_cycle_membership',$membershipId,[
                'reason'=>'exit_membership_integrity_repair',
                'animal_id'=>$animalId,
                'tag_no'=>$result['tag_no'],
                'cycle_code'=>$result['cycle_code'],
                'exit_event_id'=>$result['exit_event_id'],
                'end_date'=>$result['exit_date'],
            ]);
            $_SESSION['success']='Membership closed at the recorded lifecycle exit date. Historical boundary repaired.';
        }catch(Throwable $e){ $_SESSION['error']=$e->getMessage(); }
        header('Location: ruminant_membership_integrity.php'); exit();
    }
}

$sql="SELECT m.id membership_id,m.animal_id,m.cycle_id,m.start_date,m.end_date,
            a.tag_no,a.species,a.status,pc.cycle_code,
            xe.id exit_event_id,xe.exit_date,xe.exit_outcome
     FROM ruminant_animal_cycle_memberships m
     JOIN ruminant_animals a ON a.id=m.animal_id AND a.farm_id=m.farm_id
     JOIN production_cycles pc ON pc.id=m.cycle_id AND pc.farm_id=m.farm_id
     LEFT JOIN ruminant_animal_exit_events xe ON xe.id=(
        SELECT x2.id FROM ruminant_animal_exit_events x2
        WHERE x2.farm_id=m.farm_id AND x2.animal_id=m.animal_id
        ORDER BY x2.exit_date DESC,x2.id DESC LIMIT 1
     )
     WHERE m.farm_id=? AND a.status<>'active' AND m.end_date IS NULL
     ORDER BY xe.exit_date,a.species,a.tag_no,m.start_date";
$stmt=$pdo->prepare($sql); $stmt->execute([$farmId]); $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html lang="en"><head><?php include __DIR__.'/../navbar_head.php'; ?><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ruminant Membership Integrity</title></head>
<body class="management-page"><?php include __DIR__.'/../navbar.php'; ?>
<main class="container-fluid mt-4 poultry-shell">
<div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
  <div><h3 class="mb-1"><i class="bi bi-shield-check"></i> Ruminant Membership Integrity</h3><div class="text-muted">Review exited animals whose production-cycle membership still has no end date. Repairs use the animal's recorded lifecycle exit date; Farm Intelligence never changes history silently.</div></div>
  <a class="btn btn-outline-secondary" href="intelligence.php">← Farm Intelligence</a>
</div>
<?php if(!empty($_SESSION['success'])):?><div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div><?php endif; ?>
<?php if(!empty($_SESSION['error'])):?><div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?>
<div class="card poultry-panel"><div class="card-header d-flex justify-content-between align-items-center"><strong>Open memberships for exited animals</strong><span class="badge text-bg-<?php echo $rows?'warning':'success'; ?>"><?php echo count($rows); ?></span></div>
<div class="card-body">
<?php if(!$rows): ?>
  <div class="alert alert-success mb-0"><strong>No stale exit memberships detected.</strong> Current lifecycle and membership history are aligned.</div>
<?php else: ?>
  <div class="alert alert-info small">A repair closes only the displayed open membership at the animal's existing dated exit event. It does not create an exit, change an outcome, or alter revenue.</div>
  <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Animal</th><th>Status</th><th>Cycle</th><th>Membership</th><th>Recorded Exit</th><th>Outcome</th><th>Integrity</th><th>Action</th></tr></thead><tbody>
  <?php foreach($rows as $r): $repairable=!empty($r['exit_event_id']) && (string)$r['start_date'] <= (string)$r['exit_date']; ?>
    <tr>
      <td><strong><?php echo htmlspecialchars($r['tag_no']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars(ucfirst($r['species'])); ?></small></td>
      <td><span class="badge text-bg-secondary"><?php echo htmlspecialchars(ucfirst($r['status'])); ?></span></td>
      <td><?php echo htmlspecialchars($r['cycle_code']); ?></td>
      <td><?php echo date('d/m/Y',strtotime($r['start_date'])); ?> → <strong>Open</strong></td>
      <td><?php echo $r['exit_date']?date('d/m/Y',strtotime($r['exit_date'])):'No dated exit event'; ?></td>
      <td><?php echo $r['exit_outcome']?htmlspecialchars(ruminant_exit_outcome_display((string)$r['exit_outcome'])):'--'; ?></td>
      <td><?php if($repairable): ?><span class="badge text-bg-warning">Proposed end: <?php echo date('d/m/Y',strtotime($r['exit_date'])); ?></span><?php elseif(empty($r['exit_event_id'])): ?><span class="badge text-bg-danger">Manual review required</span><div class="small text-muted mt-1">No lifecycle exit event exists.</div><?php else: ?><span class="badge text-bg-danger">Date conflict</span><div class="small text-muted mt-1">Membership starts after exit date.</div><?php endif; ?></td>
      <td class="text-nowrap"><a class="btn btn-sm btn-outline-secondary" href="../ruminant/animal_view.php?id=<?php echo (int)$r['animal_id']; ?>">Profile</a>
      <?php if($canManage && $repairable): ?><form method="post" class="d-inline" data-confirm="This will close the displayed production-cycle membership on the animal's already-recorded exit date. It will not change the sale, exit outcome, or revenue. The repair is audit logged." data-confirm-title="Close membership at exit date?" data-confirm-button="Close Membership" data-confirm-tone="warning"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(),ENT_QUOTES); ?>"><input type="hidden" name="action" value="repair_membership"><input type="hidden" name="animal_id" value="<?php echo (int)$r['animal_id']; ?>"><input type="hidden" name="membership_id" value="<?php echo (int)$r['membership_id']; ?>"><button class="btn btn-sm btn-warning">Close at Exit Date</button></form><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php endif; ?>
</div></div>
</main></body></html>
