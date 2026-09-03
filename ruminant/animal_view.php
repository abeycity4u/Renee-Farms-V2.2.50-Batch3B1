<?php
require_once(dirname(__DIR__) . '/init.php');
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../includes/functions.php');
require_once(__DIR__ . '/../includes/audit_helpers.php');
require_once(__DIR__ . '/../lib/ruminant_animal_economics.php');
require_once(__DIR__ . '/../lib/ruminant_cycle_membership.php');
require_once(__DIR__ . '/../lib/ruminant_lifecycle_integrity.php');
requireLogin();
ensureAllowed('ruminant_daily');
$farmId = requireCurrentFarmId();
$animalId = (int)($_GET['id'] ?? 0);
if ($animalId < 1) { http_response_code(400); exit('Invalid animal.'); }

$canManage = isPlatformOwner() || hasRole('farm_admin') || hasRole('ruminant_manager');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canManage) { http_response_code(403); exit('Access denied.'); }
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { http_response_code(419); exit('Invalid request token.'); }

    $action = $_POST['action'] ?? '';
    $check = $pdo->prepare('SELECT id, tag_no, species, status FROM ruminant_animals WHERE id=? AND farm_id=? LIMIT 1');
    $check->execute([$animalId, $farmId]);
    $target = $check->fetch(PDO::FETCH_ASSOC);
    if (!$target) { http_response_code(404); exit('Animal not found.'); }

    if ($action === 'add_weight') {
        $date = $_POST['weight_date'] ?? '';
        $weight = (float)($_POST['weight_kg'] ?? 0);
        $notes = trim($_POST['weight_notes'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $weight <= 0 || $weight > 10000) {
            http_response_code(422); exit('Enter a valid weighing date and weight.');
        }
        $stmt = $pdo->prepare('INSERT INTO ruminant_animal_weights (farm_id, animal_id, weight_date, weight_kg, notes, recorded_by) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$farmId, $animalId, $date, $weight, $notes ?: null, $_SESSION['user_id'] ?? null]);
        audit_log_event('create', 'ruminant_animal_weight', $pdo->lastInsertId(), ['animal_id'=>$animalId,'tag_no'=>$target['tag_no'],'weight_kg'=>$weight,'weight_date'=>$date]);
        header('Location: animal_view.php?id='.$animalId.'#weight-history'); exit();
    }

    if ($action === 'add_cycle_membership') {
        $cycleId = (int)($_POST['cycle_id'] ?? 0);
        $startDate = trim((string)($_POST['membership_start_date'] ?? ''));
        $endDateRaw = trim((string)($_POST['membership_end_date'] ?? ''));
        $endDate = $endDateRaw !== '' ? $endDateRaw : null;
        $notes = trim((string)($_POST['membership_notes'] ?? ''));
        try {
            $membershipId = ruminant_cycle_membership_add($pdo,$farmId,$animalId,$cycleId,$startDate,$endDate,$notes,$_SESSION['user_id'] ?? null);
            audit_log_event('create','ruminant_cycle_membership',$membershipId,['animal_id'=>$animalId,'tag_no'=>$target['tag_no'],'cycle_id'=>$cycleId,'start_date'=>$startDate,'end_date'=>$endDate]);
            $_SESSION['success']='Production cycle membership added.';
        } catch (Throwable $e) {
            $_SESSION['error']=$e->getMessage();
        }
        header('Location: animal_view.php?id='.$animalId.'#cycle-membership'); exit();
    }

    if ($action === 'close_cycle_membership') {
        $membershipId=(int)($_POST['membership_id']??0); $endDate=trim((string)($_POST['membership_end_date']??''));
        try { ruminant_cycle_membership_close($pdo,$farmId,$animalId,$membershipId,$endDate); audit_log_event('update','ruminant_cycle_membership',$membershipId,['animal_id'=>$animalId,'tag_no'=>$target['tag_no'],'end_date'=>$endDate]); $_SESSION['success']='Production cycle membership closed on the effective date.'; }
        catch(Throwable $e){ $_SESSION['error']=$e->getMessage(); }
        header('Location: animal_view.php?id='.$animalId.'#cycle-membership'); exit();
    }

    if ($action === 'delete_cycle_membership') {
        $membershipId = (int)($_POST['membership_id'] ?? 0);
        try {
            ruminant_cycle_membership_delete($pdo,$farmId,$animalId,$membershipId);
            audit_log_event('delete','ruminant_cycle_membership',$membershipId,['animal_id'=>$animalId,'tag_no'=>$target['tag_no']]);
            $_SESSION['success']='Production cycle membership removed.';
        } catch (Throwable $e) {
            $_SESSION['error']=$e->getMessage();
        }
        header('Location: animal_view.php?id='.$animalId.'#cycle-membership'); exit();
    }

    if ($action === 'add_health') {
        $date = $_POST['event_date'] ?? '';
        $type = $_POST['event_type'] ?? 'other';
        $description = trim($_POST['description'] ?? '');
        $medicine = trim($_POST['medicine'] ?? '');
        $dosage = trim($_POST['dosage'] ?? '');
        $withdrawal = $_POST['withdrawal_until'] ?? '';
        $types = ['vaccination','treatment','diagnosis','vet_visit','deworming','other'];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !in_array($type, $types, true)) {
            http_response_code(422); exit('Enter a valid health-event date and type.');
        }
        if ($withdrawal !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $withdrawal)) {
            http_response_code(422); exit('Enter a valid withdrawal date.');
        }
        $stmt = $pdo->prepare('INSERT INTO ruminant_health_events (farm_id, animal_id, event_date, event_type, description, medicine, dosage, withdrawal_until, recorded_by) VALUES (?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$farmId, $animalId, $date, $type, $description ?: null, $medicine ?: null, $dosage ?: null, $withdrawal ?: null, $_SESSION['user_id'] ?? null]);
        audit_log_event('create', 'ruminant_health_event', $pdo->lastInsertId(), ['animal_id'=>$animalId,'tag_no'=>$target['tag_no'],'event_type'=>$type,'event_date'=>$date]);
        header('Location: animal_view.php?id='.$animalId.'#health-history'); exit();
    }
}

$stmt = $pdo->prepare('SELECT a.*, u.full_name AS created_by_name FROM ruminant_animals a LEFT JOIN users u ON u.id=a.created_by WHERE a.id=? AND a.farm_id=? LIMIT 1');
$stmt->execute([$animalId, $farmId]);
$animal = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$animal) { http_response_code(404); exit('Animal not found.'); }

$weightsStmt = $pdo->prepare('SELECT w.*, u.full_name AS recorded_by_name FROM ruminant_animal_weights w LEFT JOIN users u ON u.id=w.recorded_by WHERE w.animal_id=? AND w.farm_id=? ORDER BY w.weight_date DESC, w.id DESC');
$weightsStmt->execute([$animalId, $farmId]);
$weights = $weightsStmt->fetchAll(PDO::FETCH_ASSOC);

$healthStmt = $pdo->prepare('SELECT h.*, u.full_name AS recorded_by_name FROM ruminant_health_events h LEFT JOIN users u ON u.id=h.recorded_by WHERE h.animal_id=? AND h.farm_id=? ORDER BY h.event_date DESC, h.id DESC');
$healthStmt->execute([$animalId, $farmId]);
$healthEvents = $healthStmt->fetchAll(PDO::FETCH_ASSOC);

$exitStmt = $pdo->prepare("SELECT e.*, s.product_type, s.customer_name, rsa.allocated_amount, u.full_name AS recorded_by_name
    FROM ruminant_animal_exit_events e
    LEFT JOIN sales_records s ON s.id=e.sale_id AND s.farm_id=e.farm_id
    LEFT JOIN ruminant_sale_animal_allocations rsa ON rsa.sale_id=e.sale_id AND rsa.animal_id=e.animal_id AND rsa.farm_id=e.farm_id
    LEFT JOIN users u ON u.id=e.recorded_by AND u.farm_id=e.farm_id
    WHERE e.animal_id=? AND e.farm_id=?
    ORDER BY e.exit_date DESC,e.id DESC");
$exitStmt->execute([$animalId,$farmId]);
$exitEvents = $exitStmt->fetchAll(PDO::FETCH_ASSOC);

$memberships = ruminant_cycle_memberships_for_animal($pdo,$farmId,$animalId);
$cycleOptionStmt=$pdo->prepare("SELECT id,cycle_code,production_type,status,start_date,close_date FROM production_cycles WHERE farm_id=? AND farm_type='ruminant' AND LOWER(production_type)=? ORDER BY start_date DESC,id DESC");
$cycleOptionStmt->execute([$farmId,strtolower((string)$animal['species'])]);
$membershipCycleOptions=$cycleOptionStmt->fetchAll(PDO::FETCH_ASSOC);

$economics = ruminant_animal_economics($pdo, $farmId, $animalId);

$latestWeight = $weights[0]['weight_kg'] ?? null;
$previousWeight = $weights[1]['weight_kg'] ?? null;
$weightChange = ($latestWeight !== null && $previousWeight !== null) ? (float)$latestWeight - (float)$previousWeight : null;
$today = app_today();
?>
<!doctype html>
<html lang="en">
<head>
<?php include(__DIR__ . '/../navbar_head.php'); ?>
<title><?php echo htmlspecialchars($animal['tag_no']); ?> — Animal Profile</title>
</head>
<body class="ruminant-page">
<?php include(__DIR__ . '/../navbar.php'); ?>
<main class="container-fluid mt-4 poultry-shell">
  <div class="d-flex justify-content-between align-items-center mb-3 app-responsive-toolbar">
    <div>
      <div class="text-muted small">Ruminant Animal Registry</div>
      <h3 class="mb-0"><i class="bi bi-person-badge"></i> <?php echo htmlspecialchars($animal['tag_no']); ?></h3>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary" href="animal_registry.php"><i class="bi bi-arrow-left"></i> Back</a>
      <?php if ($canManage): ?><a class="btn btn-primary" href="animal_registry.php?edit=<?php echo (int)$animal['id']; ?>"><i class="bi bi-pencil"></i> Edit</a><?php endif; ?>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-5">
      <div class="card poultry-panel h-100">
        <div class="card-header poultry-hero d-flex justify-content-between align-items-center"><strong>Animal Profile</strong><span class="badge text-bg-<?php echo $animal['status']==='active'?'success':'secondary'; ?>"><?php echo ucfirst($animal['status']); ?></span></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-6"><small class="text-muted">Tag No.</small><div class="fw-semibold"><?php echo htmlspecialchars($animal['tag_no']); ?></div></div>
            <div class="col-6"><small class="text-muted">Species</small><div class="fw-semibold"><?php echo ucfirst($animal['species']); ?></div></div>
            <div class="col-6"><small class="text-muted">Breed</small><div><?php echo htmlspecialchars($animal['breed'] ?: '—'); ?></div></div>
            <div class="col-6"><small class="text-muted">Sex</small><div><?php echo ucfirst($animal['sex']); ?></div></div>
            <div class="col-6"><small class="text-muted">Birth Date</small><div><?php echo $animal['birth_date'] ? date('d/m/Y', strtotime($animal['birth_date'])) : '—'; ?></div></div>
            <div class="col-6"><small class="text-muted">Source</small><div><?php echo htmlspecialchars($animal['source'] ?: '—'); ?></div></div>
            <div class="col-6"><small class="text-muted">Purchase Date</small><div><?php echo $animal['purchase_date'] ? date('d/m/Y', strtotime($animal['purchase_date'])) : '—'; ?></div></div>
            <div class="col-6"><small class="text-muted">Purchase Cost</small><div>₦<?php echo number_format((float)$animal['purchase_cost'], 2); ?></div></div>
            <div class="col-12"><small class="text-muted">Notes</small><div><?php echo nl2br(htmlspecialchars($animal['notes'] ?: 'No notes.')); ?></div></div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><small class="text-muted">Current Weight</small><h4 class="mb-0"><?php echo $latestWeight !== null ? number_format((float)$latestWeight, 2).' kg' : '—'; ?></h4><?php if ($canManage): ?><button type="button" class="btn btn-sm btn-outline-primary mt-2" data-bs-toggle="modal" data-bs-target="#weightModal">+ Record Weight</button><?php endif; ?></div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><small class="text-muted">Weight Records</small><h4 class="mb-0"><?php echo count($weights); ?></h4></div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><small class="text-muted">Health Events</small><h4 class="mb-0"><?php echo count($healthEvents); ?></h4><?php if ($canManage): ?><button type="button" class="btn btn-sm btn-outline-primary mt-2" data-bs-toggle="modal" data-bs-target="#healthModal">+ Add Health</button><?php endif; ?></div></div></div>
      </div>

      <div class="card poultry-panel mb-3" id="animal-economics">
        <div class="card-header poultry-hero d-flex justify-content-between align-items-center">
          <strong><i class="bi bi-graph-up-arrow"></i> Individual Animal Economics</strong>
          <span class="badge text-bg-light">Direct attribution</span>
        </div>
        <div class="card-body">
          <div class="alert alert-info py-2 small mb-3">
            This view uses only costs and revenue explicitly attributable to this animal. Shared herd/cycle costs, herd-level feed consumption, and unallocated inventory usage are not silently divided across animals.
          </div>
          <div class="row g-3">
            <div class="col-6 col-xl-4"><div class="border rounded p-3 h-100"><small class="text-muted">Purchase Cost</small><div class="fs-5 fw-semibold">₦<?php echo number_format((float)$economics['purchase_cost'],2); ?></div></div></div>
            <div class="col-6 col-xl-4"><div class="border rounded p-3 h-100"><small class="text-muted">Direct Allocated Expenses</small><div class="fs-5 fw-semibold">₦<?php echo number_format((float)$economics['direct_expense_total'],2); ?></div></div></div>
            <div class="col-6 col-xl-4"><div class="border rounded p-3 h-100"><small class="text-muted">Direct Cost Basis</small><div class="fs-5 fw-semibold">₦<?php echo number_format((float)$economics['direct_cost_total'],2); ?></div></div></div>
            <div class="col-6 col-xl-4"><div class="border rounded p-3 h-100"><small class="text-muted">Attributed Revenue</small><div class="fs-5 fw-semibold">₦<?php echo number_format((float)$economics['revenue_total'],2); ?></div></div></div>
            <div class="col-6 col-xl-4"><div class="border rounded p-3 h-100"><small class="text-muted">Exit-linked Revenue</small><div class="fs-5 fw-semibold">₦<?php echo number_format((float)$economics['exit_revenue_total'],2); ?></div><small class="text-muted">Included in attributed revenue; not added twice.</small></div></div>
            <div class="col-6 col-xl-4"><div class="border rounded p-3 h-100"><small class="text-muted">Direct Net Position</small><div class="fs-5 fw-semibold <?php echo $economics['direct_net_position'] >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo $economics['direct_net_position'] >= 0 ? '' : '−'; ?>₦<?php echo number_format(abs((float)$economics['direct_net_position']),2); ?></div><small class="text-muted"><?php echo $economics['direct_roi_percent'] === null ? 'ROI N/A' : 'Direct ROI '.number_format((float)$economics['direct_roi_percent'],2).'%'; ?></small></div></div>
          </div>
          <div class="small text-muted mt-3"><strong>Formula:</strong> Attributed Revenue − Purchase Cost − Direct Allocated Expenses. This is a directly attributable economic position, not a fully allocated herd-profit figure.</div>
        </div>
      </div>

      <div class="card poultry-panel mb-3" id="fully-allocated-economics">
        <div class="card-header poultry-hero d-flex justify-content-between align-items-center">
          <strong><i class="bi bi-diagram-3"></i> Fully Allocated Animal Economics</strong>
          <span class="badge text-bg-light">Shared-cost allocation</span>
        </div>
        <div class="card-body">
          <div class="alert alert-secondary py-2 small mb-3">
            Shared species/cycle operating costs are allocated by <strong>active headcount on each transaction date</strong>. Across a period this behaves like animal-days. The platform uses explicit production-cycle membership and does not estimate weight-based consumption or silently assign cross-species Shared Ruminant costs.
          </div>
          <?php if (!$memberships): ?>
            <div class="alert alert-warning py-2 small"><strong>Cycle membership required.</strong> Direct economics remains valid, but shared costs cannot be assigned to this animal until its production-cycle membership dates are recorded.</div>
          <?php endif; ?>
          <?php if (($economics['uncovered_species_shared_cost'] ?? 0) > 0): ?>
            <div class="alert alert-warning py-2 small"><strong>Incomplete shared-cost coverage:</strong> ₦<?php echo number_format((float)$economics['uncovered_species_shared_cost'],2); ?> of <?php echo htmlspecialchars(ucfirst((string)$animal['species'])); ?> shared operating cost falls on dates with no eligible cycle membership. It remains unallocated instead of being guessed.</div>
          <?php endif; ?>
          <div class="row g-3">
            <div class="col-6 col-xl-4"><div class="border rounded p-3 h-100"><small class="text-muted">Direct Cost Basis</small><div class="fs-5 fw-semibold">₦<?php echo number_format((float)$economics['direct_cost_total'],2); ?></div></div></div>
            <div class="col-6 col-xl-4"><div class="border rounded p-3 h-100"><small class="text-muted">Allocated Shared Cost</small><div class="fs-5 fw-semibold">₦<?php echo number_format((float)$economics['allocated_shared_cost'],2); ?></div></div></div>
            <div class="col-6 col-xl-4"><div class="border rounded p-3 h-100"><small class="text-muted">Fully Allocated Cost Basis</small><div class="fs-5 fw-semibold">₦<?php echo number_format((float)$economics['fully_allocated_cost_total'],2); ?></div></div></div>
            <div class="col-6 col-xl-4"><div class="border rounded p-3 h-100"><small class="text-muted">Attributed Revenue</small><div class="fs-5 fw-semibold">₦<?php echo number_format((float)$economics['revenue_total'],2); ?></div></div></div>
            <div class="col-6 col-xl-4"><div class="border rounded p-3 h-100"><small class="text-muted">Fully Allocated Net Position</small><div class="fs-5 fw-semibold <?php echo $economics['fully_allocated_net_position']>=0?'text-success':'text-danger'; ?>"><?php echo $economics['fully_allocated_net_position']>=0?'':'−'; ?>₦<?php echo number_format(abs((float)$economics['fully_allocated_net_position']),2); ?></div><small class="text-muted"><?php echo $economics['fully_allocated_roi_percent']===null?'ROI N/A':'Fully allocated ROI '.number_format((float)$economics['fully_allocated_roi_percent'],2).'%'; ?></small></div></div>
            <div class="col-6 col-xl-4"><div class="border rounded p-3 h-100"><small class="text-muted">Allocation Driver</small><div class="fw-semibold">Active headcount</div><small class="text-muted">Transaction-date / animal-days</small></div></div>
          </div>
          <div class="small text-muted mt-3"><strong>Formula:</strong> Attributed Revenue − Purchase Cost − Direct Allocated Expenses − Allocated Shared Operating Cost. Shared Ruminant cross-species pools remain outside this figure until assigned to a species/cost centre.</div>
        </div>
      </div>

      <div class="card poultry-panel mb-3" id="shared-cost-history">
        <div class="card-header"><strong><i class="bi bi-people"></i> Allocated Shared Cost History</strong></div>
        <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Date</th><th>Source</th><th>Cycle</th><th>Pool Cost</th><th>Eligible Animals</th><th class="text-end">This Animal</th></tr></thead><tbody>
        <?php foreach (($economics['shared_cost_rows'] ?? []) as $c): ?>
          <tr><td><?php echo date('d/m/Y',strtotime((string)$c['source_date'])); ?></td><td><?php echo htmlspecialchars((string)$c['source_label']); ?><div class="small text-muted"><?php echo htmlspecialchars(ucfirst(str_replace('_',' ',(string)$c['source_type']))); ?> · <?php echo htmlspecialchars(ucfirst(str_replace('_',' ',(string)$c['classification']))); ?></div></td><td><?php echo htmlspecialchars((string)($c['cycle_code'] ?: 'Shared between species cycles')); ?></td><td>₦<?php echo number_format((float)$c['pool_amount'],2); ?></td><td><?php echo (int)$c['eligible_animal_count']; ?><div class="small text-muted">Active headcount</div></td><td class="text-end fw-semibold">₦<?php echo number_format((float)$c['allocated_amount'],2); ?></td></tr>
        <?php endforeach; if (empty($economics['shared_cost_rows'])): ?><tr><td colspan="6" class="text-center text-muted py-3">No eligible shared species/cycle operating cost has been allocated to this animal yet.</td></tr><?php endif; ?>
        </tbody></table></div>
      </div>

      <div class="card poultry-panel mb-3" id="cycle-membership">
        <div class="card-header d-flex justify-content-between align-items-center"><strong><i class="bi bi-calendar-range"></i> Production Cycle Membership</strong><?php if($canManage): ?><button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#membershipModal">+ Assign Cycle</button><?php endif; ?></div>
        <div class="card-body py-2"><div class="small text-muted">Membership history is the audit basis for shared-cost allocation. Date ranges cannot overlap and the cycle species must match this animal.</div></div>
        <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Cycle</th><th>Start</th><th>End</th><th>Status</th><th>Notes</th><?php if($canManage): ?><th class="text-end">Action</th><?php endif; ?></tr></thead><tbody>
        <?php foreach($memberships as $m): ?><tr><td><?php echo htmlspecialchars($m['cycle_code']); ?></td><td><?php echo date('d/m/Y',strtotime($m['start_date'])); ?></td><td><?php echo $m['end_date']?date('d/m/Y',strtotime($m['end_date'])):'Open'; ?></td><td><?php echo htmlspecialchars(ucfirst((string)$m['cycle_status'])); ?></td><td><?php echo htmlspecialchars($m['notes'] ?: '—'); ?></td><?php if($canManage): ?><td class="text-end"><?php if(!$m['end_date']): ?><button type="button" class="btn btn-sm btn-outline-primary" onclick="closeMembership(<?php echo (int)$m['id']; ?>,'<?php echo htmlspecialchars($m['cycle_code'],ENT_QUOTES); ?>')">Close</button> <?php endif; ?><form method="post" class="d-inline" data-confirm="Remove this production-cycle membership? Removal is blocked when financial activity exists in its date range." data-confirm-title="Remove membership?" data-confirm-button="Remove"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(),ENT_QUOTES); ?>"><input type="hidden" name="action" value="delete_cycle_membership"><input type="hidden" name="membership_id" value="<?php echo (int)$m['id']; ?>"><button class="btn btn-sm btn-outline-danger">Remove</button></form></td><?php endif; ?></tr><?php endforeach; if(!$memberships): ?><tr><td colspan="<?php echo $canManage?6:5; ?>" class="text-center text-muted py-3">No production-cycle membership recorded.</td></tr><?php endif; ?>
        </tbody></table></div>
      </div>

      <div class="card poultry-panel mb-3" id="animal-expense-economics">
        <div class="card-header"><strong><i class="bi bi-receipt"></i> Direct Cost History</strong></div>
        <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Date</th><th>Expense</th><th>Production / Cycle</th><th>Method</th><th class="text-end">Allocated Cost</th></tr></thead><tbody>
        <?php foreach ($economics['expenses'] as $e): ?>
          <tr>
            <td><?php echo date('d/m/Y', strtotime($e['expense_date'])); ?></td>
            <td><?php echo htmlspecialchars(ucfirst(str_replace('_',' ',(string)$e['category']))); ?><?php echo $e['description'] ? '<div class="small text-muted">'.htmlspecialchars($e['description']).'</div>' : ''; ?></td>
            <td><?php echo htmlspecialchars(ucfirst((string)($e['production_type'] ?: $animal['species']))); ?><div class="small text-muted"><?php echo htmlspecialchars($e['cycle_code'] ?: 'No specific cycle'); ?></div></td>
            <td><?php echo htmlspecialchars(ucfirst((string)$e['allocation_method'])); ?></td>
            <td class="text-end">₦<?php echo number_format((float)$e['allocated_amount'],2); ?></td>
          </tr>
        <?php endforeach; if (!$economics['expenses']): ?><tr><td colspan="5" class="text-center text-muted py-3">No expense has been directly allocated to this animal.</td></tr><?php endif; ?>
        </tbody></table></div>
      </div>

      <div class="card poultry-panel mb-3" id="animal-revenue-economics">
        <div class="card-header"><strong><i class="bi bi-cash-stack"></i> Attributed Revenue History</strong></div>
        <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Date</th><th>Sale</th><th>Quantity</th><th>Outcome</th><th>Customer</th><th class="text-end">Attributed Revenue</th></tr></thead><tbody>
        <?php foreach ($economics['revenues'] as $r): ?>
          <tr>
            <td><?php echo date('d/m/Y', strtotime($r['sale_date'])); ?></td>
            <td>#<?php echo (int)$r['sale_id']; ?> · <?php echo htmlspecialchars($r['product_type'] ?: 'Sale'); ?></td>
            <td><?php echo number_format((float)$r['quantity'],2); ?> <?php echo htmlspecialchars($r['unit_of_measure'] ?: 'unit not specified'); ?></td>
            <td><?php echo $r['exit_outcome'] === 'sold_live' ? 'Sold live' : ($r['exit_outcome'] === 'culled_slaughtered' ? 'Culled / slaughtered' : 'Revenue only'); ?></td>
            <td><?php echo htmlspecialchars($r['customer_name'] ?: '—'); ?></td>
            <td class="text-end">₦<?php echo number_format((float)$r['allocated_amount'],2); ?></td>
          </tr>
        <?php endforeach; if (!$economics['revenues']): ?><tr><td colspan="6" class="text-center text-muted py-3">No sale revenue has been attributed to this animal.</td></tr><?php endif; ?>
        </tbody></table></div>
      </div>

      <div class="card poultry-panel mb-3" id="weight-history">
        <div class="card-header d-flex justify-content-between align-items-center"><strong><i class="bi bi-speedometer2"></i> Weight History</strong><?php if ($canManage): ?><button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#weightModal">+ Add Weight</button><?php endif; ?><?php if ($weightChange !== null): ?><span class="ms-auto me-2 <?php echo $weightChange >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo ($weightChange >= 0 ? '+' : '').number_format($weightChange,2); ?> kg since previous record</span><?php endif; ?></div>
        <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Date</th><th>Weight</th><th>Change</th><th>Notes</th></tr></thead><tbody>
        <?php foreach ($weights as $i => $w): $olderWeight = $weights[$i + 1]['weight_kg'] ?? null; $change = $olderWeight !== null ? (float)$w['weight_kg'] - (float)$olderWeight : null; ?>
          <tr><td><?php echo date('d/m/Y', strtotime($w['weight_date'])); ?></td><td><?php echo number_format((float)$w['weight_kg'],2); ?> kg</td><td><?php echo $change === null ? '—' : (($change >= 0 ? '+' : '').number_format($change,2).' kg'); ?></td><td><?php echo htmlspecialchars($w['notes'] ?: '—'); ?></td></tr>
        <?php endforeach; if (!$weights): ?><tr><td colspan="4" class="text-center text-muted py-3">No weight records yet.</td></tr><?php endif; ?></tbody></table></div>
      </div>

      <div class="card poultry-panel" id="health-history">
        <div class="card-header d-flex justify-content-between align-items-center"><strong><i class="bi bi-heart-pulse"></i> Health & Treatment History</strong><?php if ($canManage): ?><button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#healthModal">+ Add Health Record</button><?php endif; ?></div>
        <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Date</th><th>Type</th><th>Description</th><th>Medicine</th><th>Withdrawal Until</th></tr></thead><tbody>
        <?php foreach ($healthEvents as $h): ?><tr><td><?php echo date('d/m/Y', strtotime($h['event_date'])); ?></td><td><?php echo ucfirst(str_replace('_',' ',$h['event_type'])); ?></td><td><?php echo nl2br(htmlspecialchars($h['description'] ?: '—')); ?></td><td><?php echo htmlspecialchars($h['medicine'] ?: '—'); ?><?php echo $h['dosage'] ? ' ('.htmlspecialchars($h['dosage']).')' : ''; ?></td><td><?php echo $h['withdrawal_until'] ? date('d/m/Y', strtotime($h['withdrawal_until'])) : '—'; ?></td></tr><?php endforeach; if (!$healthEvents): ?><tr><td colspan="5" class="text-center text-muted py-3">No health records yet.</td></tr><?php endif; ?></tbody></table></div>
      </div>

      <div class="card poultry-panel mt-3" id="exit-history">
        <div class="card-header"><strong><i class="bi bi-box-arrow-right"></i> Animal Exit History</strong></div>
        <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Date</th><th>Outcome</th><th>Sale</th><th>Revenue</th><th>Customer</th><th>Recorded By</th></tr></thead><tbody>
        <?php foreach ($exitEvents as $e): ?>
          <tr>
            <td><?php echo date('d/m/Y', strtotime($e['exit_date'])); ?></td>
            <td><?php echo htmlspecialchars(ruminant_exit_outcome_display((string)$e['exit_outcome'])); ?></td>
            <td><?php echo $e['sale_id'] ? '#'.(int)$e['sale_id'].' · '.htmlspecialchars($e['product_type'] ?: 'Sale') : '—'; ?></td>
            <td><?php echo $e['allocated_amount'] !== null ? '₦'.number_format((float)$e['allocated_amount'],2) : '—'; ?></td>
            <td><?php echo htmlspecialchars($e['customer_name'] ?: '—'); ?></td>
            <td><?php echo htmlspecialchars($e['recorded_by_name'] ?: '—'); ?></td>
          </tr>
        <?php endforeach; if (!$exitEvents): ?><tr><td colspan="6" class="text-center text-muted py-3">No sale-linked exit event recorded.</td></tr><?php endif; ?></tbody></table></div>
      </div>
    </div>
  </div>
</main>
<?php if ($canManage): ?>
<div class="modal fade" id="weightModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content"><form method="post">
    <div class="modal-header"><h5 class="modal-title">Record Weight</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>"><input type="hidden" name="action" value="add_weight">
      <div class="mb-3"><label class="form-label">Date *</label><input type="date" class="form-control" name="weight_date" value="<?php echo $today; ?>" max="<?php echo $today; ?>" required></div>
      <div class="mb-3"><label class="form-label">Weight (kg) *</label><input type="number" class="form-control" name="weight_kg" min="0.01" max="10000" step="0.01" required></div>
      <div><label class="form-label">Notes</label><textarea class="form-control" name="weight_notes" rows="3" maxlength="255" placeholder="e.g. Routine weighing"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save Weight</button></div>
  </form></div></div>
</div>
<div class="modal fade" id="closeMembershipModal"><div class="modal-dialog"><div class="modal-content"><form method="post"><div class="modal-header"><h5 class="modal-title">Close Cycle Membership</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(),ENT_QUOTES); ?>"><input type="hidden" name="action" value="close_cycle_membership"><input type="hidden" name="membership_id" id="close_membership_id"><div class="mb-3"><label class="form-label">Cycle</label><input class="form-control" id="close_membership_cycle" disabled></div><div><label class="form-label">Effective End Date *</label><input type="date" class="form-control" name="membership_end_date" value="<?php echo htmlspecialchars(app_today(),ENT_QUOTES); ?>" required><div class="form-text">The animal remains eligible for shared costs on this date and is excluded from later dates.</div></div></div><div class="modal-footer"><button class="btn btn-primary">Close Membership</button></div></form></div></div></div><div class="modal fade" id="membershipModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content"><form method="post">
    <div class="modal-header"><h5 class="modal-title">Assign Production Cycle</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(),ENT_QUOTES); ?>"><input type="hidden" name="action" value="add_cycle_membership">
      <div class="mb-3"><label class="form-label">Production Cycle *</label><select class="form-select" name="cycle_id" required><option value="">Choose cycle</option><?php foreach($membershipCycleOptions as $c): ?><option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['cycle_code'].' — '.ucfirst($c['status'])); ?></option><?php endforeach; ?></select><div class="form-text">Only <?php echo htmlspecialchars(ucfirst((string)$animal['species'])); ?> cycles are shown.</div></div>
      <div class="row g-3"><div class="col-md-6"><label class="form-label">Membership Start *</label><input type="date" class="form-control" name="membership_start_date" value="<?php echo htmlspecialchars((string)($animal['purchase_date'] ?: $today)); ?>" required></div><div class="col-md-6"><label class="form-label">Membership End</label><input type="date" class="form-control" name="membership_end_date"><div class="form-text">Leave blank while animal remains in this cycle.</div></div></div>
      <div class="mt-3"><label class="form-label">Notes</label><input class="form-control" name="membership_notes" maxlength="255" placeholder="Optional transfer or assignment note"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save Membership</button></div>
  </form></div></div>
</div>
<div class="modal fade" id="healthModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg"><div class="modal-content"><form method="post">
    <div class="modal-header"><h5 class="modal-title">Add Health / Treatment Record</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>"><input type="hidden" name="action" value="add_health">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Date *</label><input type="date" class="form-control" name="event_date" value="<?php echo $today; ?>" max="<?php echo $today; ?>" required></div>
        <div class="col-md-6"><label class="form-label">Type *</label><select class="form-select" name="event_type" required><?php foreach(['vaccination','treatment','diagnosis','vet_visit','deworming','other'] as $type): ?><option value="<?php echo $type; ?>"><?php echo ucfirst(str_replace('_',' ',$type)); ?></option><?php endforeach; ?></select></div>
        <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3" placeholder="What happened or what was treated?"></textarea></div>
        <div class="col-md-6"><label class="form-label">Medicine</label><input class="form-control" name="medicine" maxlength="150" placeholder="Medicine/vaccine name"></div>
        <div class="col-md-6"><label class="form-label">Dosage</label><input class="form-control" name="dosage" maxlength="100" placeholder="e.g. 10 ml"></div>
        <div class="col-md-6"><label class="form-label">Withdrawal Until</label><input type="date" class="form-control" name="withdrawal_until" min="<?php echo $today; ?>"></div>
      </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save Health Record</button></div>
  </form></div></div>
</div>
<?php endif; ?>
<script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js'); ?>"></script>
<script>function closeMembership(id,cycle){document.getElementById('close_membership_id').value=id;document.getElementById('close_membership_cycle').value=cycle;bootstrap.Modal.getOrCreateInstance(document.getElementById('closeMembershipModal')).show();}</script>
</body>
</html>
