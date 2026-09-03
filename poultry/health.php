<?php
require_once(dirname(__DIR__) . '/init.php');
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../includes/functions.php');
require_once(__DIR__ . '/../includes/audit_helpers.php');
require_once(__DIR__ . '/../lib/poultry_health.php');
requireLogin();
ensureAllowed('poultry_health');
$farmId = requireCurrentFarmId();
$canManage = isPlatformOwner() || hasRole('farm_admin') || hasRole('poultry_manager');

$eventTypes = poultry_health_event_types();
$productionFilter = strtolower(trim((string)($_GET['production_type'] ?? '')));
if (!in_array($productionFilter, ['layer','broiler'], true)) $productionFilter = '';
$cycleFilter = (int)($_GET['cycle_id'] ?? 0);

$cycleStmt = $pdo->prepare("SELECT id, cycle_code, production_type, start_date, expected_end_date, status
    FROM production_cycles WHERE farm_id=? AND farm_type='poultry'
    ORDER BY FIELD(status,'active','planned','closed'), start_date DESC, id DESC");
$cycleStmt->execute([$farmId]);
$cycles = $cycleStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$stockStmt = $pdo->prepare("SELECT si.id, si.item_name, si.unit,
       COALESCE(ic.financial_type, si.financial_classification, 'other_stock') AS financial_type
    FROM stock_items si
    JOIN inventory_categories ic ON ic.id=si.category_id AND ic.farm_id=si.farm_id
    WHERE si.farm_id=? AND si.is_active=1 AND si.farm_type IN ('poultry','both')
      AND COALESCE(ic.financial_type, si.financial_classification, 'other_stock') IN ('medication_vaccine','supplement')
    ORDER BY si.item_name");
$stockStmt->execute([$farmId]);
$stockItems = $stockStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canManage) { http_response_code(403); exit('Access denied.'); }
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { http_response_code(419); exit('Invalid request token.'); }
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'save_event') {
            $id = (int)($_POST['event_id'] ?? 0);
            $productionType = strtolower(trim((string)($_POST['production_type'] ?? '')));
            $cycleId = (int)($_POST['cycle_id'] ?? 0);
            $eventDate = trim((string)($_POST['event_date'] ?? ''));
            $eventType = trim((string)($_POST['event_type'] ?? ''));
            $productName = trim((string)($_POST['product_name'] ?? ''));
            $dosage = trim((string)($_POST['dosage'] ?? ''));
            $reason = trim((string)($_POST['reason_symptoms'] ?? ''));
            $notes = trim((string)($_POST['notes'] ?? ''));
            $stockItemId = (int)($_POST['stock_item_id'] ?? 0);
            $stockItemId = $stockItemId > 0 ? $stockItemId : null;

            if (!in_array($productionType, ['layer','broiler'], true)) throw new InvalidArgumentException('Choose Layer or Broiler.');
            $dateObj = DateTime::createFromFormat('Y-m-d', $eventDate);
            if (!$dateObj || $dateObj->format('Y-m-d') !== $eventDate) throw new InvalidArgumentException('Enter a valid event date.');
            if (!isset($eventTypes[$eventType])) throw new InvalidArgumentException('Choose a valid event type.');
            poultry_health_validate_cycle($pdo, $farmId, $cycleId, $productionType, $eventDate);
            $linkedItem = poultry_health_validate_stock_item($pdo, $farmId, $stockItemId);
            if ($linkedItem && $productName === '') $productName = (string)$linkedItem['item_name'];
            if (strlen($productName) > 150 || strlen($dosage) > 120) throw new InvalidArgumentException('Product or dosage text is too long.');

            if ($id > 0) {
                $check = $pdo->prepare('SELECT id FROM poultry_health_events WHERE id=? AND farm_id=? LIMIT 1');
                $check->execute([$id, $farmId]);
                if (!$check->fetchColumn()) throw new RuntimeException('Health event not found.');
                $stmt = $pdo->prepare("UPDATE poultry_health_events SET cycle_id=?, production_type=?, event_date=?, event_type=?, product_name=?, dosage=?, reason_symptoms=?, notes=?, stock_item_id=? WHERE id=? AND farm_id=?");
                $stmt->execute([$cycleId,$productionType,$eventDate,$eventType,$productName ?: null,$dosage ?: null,$reason ?: null,$notes ?: null,$stockItemId,$id,$farmId]);
                audit_log_event('update','poultry_health_event',$id,['cycle_id'=>$cycleId,'production_type'=>$productionType,'event_date'=>$eventDate,'event_type'=>$eventType]);
                $_SESSION['success'] = 'Poultry health event updated.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO poultry_health_events (farm_id,cycle_id,production_type,event_date,event_type,product_name,dosage,reason_symptoms,notes,stock_item_id,recorded_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$farmId,$cycleId,$productionType,$eventDate,$eventType,$productName ?: null,$dosage ?: null,$reason ?: null,$notes ?: null,$stockItemId,$_SESSION['user_id'] ?? null]);
                $id = (int)$pdo->lastInsertId();
                audit_log_event('create','poultry_health_event',$id,['cycle_id'=>$cycleId,'production_type'=>$productionType,'event_date'=>$eventDate,'event_type'=>$eventType]);
                $_SESSION['success'] = 'Poultry health event recorded.';
            }
        } elseif ($action === 'delete_event') {
            $id = (int)($_POST['event_id'] ?? 0);
            $stmt = $pdo->prepare('DELETE FROM poultry_health_events WHERE id=? AND farm_id=?');
            $stmt->execute([$id, $farmId]);
            if ($stmt->rowCount() < 1) throw new RuntimeException('Health event not found.');
            audit_log_event('delete','poultry_health_event',$id,[]);
            $_SESSION['success'] = 'Poultry health event deleted.';
        }
    } catch (Throwable $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    header('Location: health.php' . ($productionFilter ? '?production_type='.urlencode($productionFilter) : ''));
    exit();
}

$events = poultry_health_list($pdo, $farmId, $productionFilter ?: null, $cycleFilter > 0 ? $cycleFilter : null);
require_once(__DIR__ . '/../navbar_head.php');
require_once(__DIR__ . '/../navbar.php');
?>
<div class="container-fluid py-3" style="max-width:1500px">
  <div class="d-flex justify-content-between align-items-start mb-3 gap-3 app-responsive-toolbar">
    <div>
      <h3 class="mb-1"><i class="bi bi-heart-pulse"></i> Poultry Health & Treatment</h3>
      <div class="text-muted">Structured flock health history for Layer and Broiler cycles. Daily Record medication notes remain available as quick notes.</div>
    </div>
    <?php if ($canManage): ?><button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#eventModal" onclick="newHealthEvent()"><i class="bi bi-plus-lg"></i> Record Health Event</button><?php endif; ?>
  </div>

  <?php if (!empty($_SESSION['success'])): ?><div class="alert alert-success py-2"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div><?php endif; ?>
  <?php if (!empty($_SESSION['error'])): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?>

  <div class="card mb-3"><div class="card-body py-2">
    <form class="row g-2 align-items-end" method="get">
      <div class="col-md-3"><label class="form-label mb-1">Production Type</label><select class="form-select" name="production_type"><option value="">Layer + Broiler</option><option value="layer" <?php echo $productionFilter==='layer'?'selected':''; ?>>Layer</option><option value="broiler" <?php echo $productionFilter==='broiler'?'selected':''; ?>>Broiler</option></select></div>
      <div class="col-md-5"><label class="form-label mb-1">Production Cycle</label><select class="form-select" name="cycle_id"><option value="0">All cycles</option><?php foreach ($cycles as $c): ?><option value="<?php echo (int)$c['id']; ?>" <?php echo $cycleFilter===(int)$c['id']?'selected':''; ?>><?php echo htmlspecialchars(ucfirst($c['production_type']).' · '.$c['cycle_code'].' · '.$c['status']); ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><button class="btn btn-primary w-100">Apply</button></div>
      <div class="col-md-2"><a class="btn btn-outline-secondary w-100" href="health.php">Reset</a></div>
    </form>
  </div></div>

  <div class="card"><div class="card-header d-flex justify-content-between align-items-center"><strong>Health & Treatment History</strong><span class="badge bg-secondary"><?php echo count($events); ?> records</span></div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
      <thead><tr><th>Date</th><th>Cycle</th><th>Type</th><th>Event</th><th>Product / Medicine / Vaccine</th><th>Dosage</th><th>Reason / Symptoms</th><th>Linked Inventory</th><th>Recorded By</th><?php if ($canManage): ?><th class="text-end">Actions</th><?php endif; ?></tr></thead>
      <tbody><?php if (!$events): ?><tr><td colspan="10" class="text-center text-muted py-4">No structured poultry health events recorded yet.</td></tr><?php endif; ?>
      <?php foreach ($events as $e): ?><tr>
        <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($e['event_date']))); ?></td>
        <td><div class="fw-semibold"><?php echo htmlspecialchars($e['cycle_code'] ?: '—'); ?></div><small class="text-muted"><?php echo htmlspecialchars(ucfirst($e['production_type'])); ?></small></td>
        <td><?php echo htmlspecialchars(poultry_health_event_type_label((string)$e['event_type'])); ?></td>
        <td><?php echo nl2br(htmlspecialchars((string)($e['notes'] ?: '—'))); ?></td>
        <td><?php echo htmlspecialchars((string)($e['product_name'] ?: '—')); ?></td>
        <td><?php echo htmlspecialchars((string)($e['dosage'] ?: '—')); ?></td>
        <td style="max-width:300px"><?php echo nl2br(htmlspecialchars((string)($e['reason_symptoms'] ?: '—'))); ?></td>
        <td><?php echo htmlspecialchars((string)($e['linked_item_name'] ?: '—')); ?></td>
        <td><?php echo htmlspecialchars((string)($e['recorded_by_name'] ?: '—')); ?></td>
        <?php if ($canManage): ?><td class="text-end text-nowrap">
          <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#eventModal" onclick='editHealthEvent(<?php echo json_encode($e, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE); ?>)'><i class="bi bi-pencil"></i></button>
          <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDeleteEvent(<?php echo (int)$e['id']; ?>)"><i class="bi bi-trash"></i></button>
        </td><?php endif; ?>
      </tr><?php endforeach; ?></tbody>
    </table></div>
  </div>

  <div class="alert alert-info mt-3 mb-0 py-2"><strong>Inventory link:</strong> this is a reference to the medicine, vaccine or supplement used. It does not deduct stock by itself; continue recording physical stock usage through Inventory so stock and cost history remain auditable.</div>
</div>

<?php if ($canManage): ?>
<div class="modal fade" id="eventModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
<form method="post" id="eventForm"><div class="modal-header"><h5 class="modal-title" id="eventModalTitle">Record Poultry Health Event</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>"><input type="hidden" name="action" value="save_event"><input type="hidden" name="event_id" id="event_id" value="0">
<div class="row g-3">
  <div class="col-md-4"><label class="form-label">Production Type *</label><select name="production_type" id="production_type" class="form-select" required onchange="filterCycleOptions()"><option value="layer">Layer</option><option value="broiler">Broiler</option></select></div>
  <div class="col-md-8"><label class="form-label">Production Cycle *</label><select name="cycle_id" id="cycle_id" class="form-select" required><?php foreach ($cycles as $c): ?><option data-production-type="<?php echo htmlspecialchars($c['production_type']); ?>" value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['cycle_code'].' · '.ucfirst($c['production_type']).' · '.$c['status']); ?></option><?php endforeach; ?></select></div>
  <div class="col-md-4"><label class="form-label">Event Date *</label><input type="date" name="event_date" id="event_date" class="form-control" value="<?php echo htmlspecialchars(function_exists('app_today') ? app_today() : date('Y-m-d')); ?>" required></div>
  <div class="col-md-4"><label class="form-label">Event Type *</label><select name="event_type" id="event_type" class="form-select" required><?php foreach ($eventTypes as $k=>$v): ?><option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($v); ?></option><?php endforeach; ?></select></div>
  <div class="col-md-4"><label class="form-label">Dosage</label><input type="text" name="dosage" id="dosage" maxlength="120" class="form-control" placeholder="e.g. 1 ml / bird"></div>
  <div class="col-md-6"><label class="form-label">Product / Medicine / Vaccine</label><input type="text" name="product_name" id="product_name" maxlength="150" class="form-control"></div>
  <div class="col-md-6"><label class="form-label">Linked Inventory Item <span class="text-muted">(optional)</span></label><select name="stock_item_id" id="stock_item_id" class="form-select"><option value="0">No linked inventory item</option><?php foreach ($stockItems as $i): ?><option value="<?php echo (int)$i['id']; ?>"><?php echo htmlspecialchars($i['item_name'].' · '.$i['unit']); ?></option><?php endforeach; ?></select><small class="text-muted">Reference only; no stock is deducted here.</small></div>
  <div class="col-md-12"><label class="form-label">Reason / Symptoms</label><textarea name="reason_symptoms" id="reason_symptoms" class="form-control" rows="2" placeholder="Why was this event recorded? What signs were observed?"></textarea></div>
  <div class="col-md-12"><label class="form-label">Notes</label><textarea name="notes" id="notes" class="form-control" rows="2" placeholder="Treatment details, response, vet instructions, batch/lot notes, etc."></textarea></div>
</div></div>
<div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Save Health Event</button></div></form>
</div></div></div>
<script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js'); ?>"></script>
<form method="post" id="deleteEventForm" class="d-none"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>"><input type="hidden" name="action" value="delete_event"><input type="hidden" name="event_id" id="delete_event_id"></form>
<script>
function filterCycleOptions(){
  const type=document.getElementById('production_type').value;
  const select=document.getElementById('cycle_id');
  let first=null;
  [...select.options].forEach(o=>{ const show=o.dataset.productionType===type; o.hidden=!show; o.disabled=!show; if(show && first===null) first=o; });
  if(select.selectedOptions.length===0 || select.selectedOptions[0].disabled){ if(first) first.selected=true; }
}
function newHealthEvent(){
  document.getElementById('eventForm').reset(); document.getElementById('event_id').value='0'; document.getElementById('eventModalTitle').textContent='Record Poultry Health Event';
  document.getElementById('event_date').value='<?php echo htmlspecialchars(function_exists('app_today') ? app_today() : date('Y-m-d')); ?>';
  filterCycleOptions();
}
function editHealthEvent(e){
  document.getElementById('eventModalTitle').textContent='Edit Poultry Health Event';
  document.getElementById('event_id').value=e.id||0; document.getElementById('production_type').value=e.production_type||'layer'; filterCycleOptions();
  document.getElementById('cycle_id').value=e.cycle_id||''; document.getElementById('event_date').value=e.event_date||''; document.getElementById('event_type').value=e.event_type||'other';
  document.getElementById('product_name').value=e.product_name||''; document.getElementById('dosage').value=e.dosage||''; document.getElementById('reason_symptoms').value=e.reason_symptoms||''; document.getElementById('notes').value=e.notes||''; document.getElementById('stock_item_id').value=e.stock_item_id||'0';
}
async function confirmDeleteEvent(id){
  const confirmed = await AppConfirm.ask('Delete this poultry health event? This removes the structured clinical/history record only; linked Inventory transactions are not changed.', {title:'Delete health event?', confirmText:'Delete', danger:true});
  if(confirmed){ document.getElementById('delete_event_id').value=id; document.getElementById('deleteEventForm').submit(); }
}
document.addEventListener('DOMContentLoaded',filterCycleOptions);
</script>
<?php endif; ?>
</body>
</html>
