<?php
require_once(dirname(__DIR__) . '/init.php');
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../includes/functions.php');
require_once(__DIR__ . '/../lib/poultry_cycle_lifecycle.php');
require_once(__DIR__ . '/../lib/poultry_cycle_acquisition.php');
require_once(__DIR__ . '/../lib/poultry_rearing_economics.php');
require_once(__DIR__ . '/../lib/poultry_production_entry_snapshots.php');
requireLogin();
requireBusinessReportAccess();
$farmId = requireCurrentFarmId();
if (!isPlatformOwner() && !hasRole('farm_admin') && !hasPermission(getUserType(), 'production_cycles')) {
    header('Location: ' . BASE_URL . '/no_access.php'); exit();
}

$cycleId=(int)($_GET['id']??($_POST['cycle_id']??0));
$flashError=''; $flashSuccess='';
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='approve_production_entry_basis') {
    try {
        $category=(string)($_POST['revision_category']??'');
        $reason=(string)($_POST['revision_reason']??'');
        poultry_production_entry_approve($pdo,$farmId,$cycleId,(int)($_SESSION['user_id']??0),$category,$reason);
        $_SESSION['poultry_cycle_flash']='Production-entry economic basis approved as a new immutable version.';
        header('Location: '.BASE_URL.'/management/poultry_cycle.php?id='.$cycleId.'#economic-basis'); exit();
    } catch(Throwable $e) { $flashError=$e->getMessage(); }
}
if (!empty($_SESSION['poultry_cycle_flash'])) { $flashSuccess=(string)$_SESSION['poultry_cycle_flash']; unset($_SESSION['poultry_cycle_flash']); }
$stmt=$pdo->prepare("SELECT * FROM production_cycles WHERE id=? AND farm_id=? AND farm_type='poultry' LIMIT 1");
$stmt->execute([$cycleId,$farmId]);
$cycle=$stmt->fetch(PDO::FETCH_ASSOC);
if (!$cycle || !in_array(strtolower((string)$cycle['production_type']),['layer','broiler'],true)) {
    http_response_code(404); exit('Poultry cycle not found.');
}
$history=poultry_lifecycle_history($pdo,$farmId,$cycleId);
$current=poultry_lifecycle_current_phase($pdo,$farmId,$cycleId);
$acqHistory=poultry_acquisition_history($pdo,$farmId,$cycleId);
$acqSummary=poultry_acquisition_summary($acqHistory);
$economics=strtolower((string)$cycle['production_type'])==='layer' ? poultry_rearing_economics($pdo,$farmId,$cycleId) : null;
$entryCandidate=strtolower((string)$cycle['production_type'])==='layer' ? poultry_production_entry_candidate($pdo,$farmId,$cycleId) : null;
$entrySnapshots=strtolower((string)$cycle['production_type'])==='layer' ? poultry_production_entry_snapshots($pdo,$farmId,$cycleId) : [];
$latestEntrySnapshot=$entrySnapshots[0]??null;

function moneyOrDash($value): string { return $value===null ? '-' : '₦'.number_format((float)$value,2); }
function cycleCurrentFlock(PDO $pdo,int $farmId,array $cycle): ?int {
    $table=strtolower((string)$cycle['production_type'])==='layer'?'layer_daily_records':'broiler_daily_records';
    $stmt=$pdo->prepare("SELECT opening_stock,mortality FROM {$table} WHERE farm_id=? AND cycle_id=? ORDER BY record_date DESC,id DESC LIMIT 1");
    $stmt->execute([$farmId,(int)$cycle['id']]); $row=$stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? max(0,(int)$row['opening_stock']-(int)$row['mortality']) : null;
}
$currentFlock=cycleCurrentFlock($pdo,$farmId,$cycle);
$type=strtolower((string)$cycle['production_type']);
$dailyUrl=$type==='layer'?'/poultry/layers_daily_record.php':'/poultry/broiler_daily_record.php';
$feedsUrl=$type==='layer'?'/poultry/layer_feeds.php':'/poultry/broiler_feeds.php';
$expensesUrl=$type==='layer'?'/poultry/layer_expenses.php':'/poultry/broiler_expenses.php';
?>
<!doctype html><html lang="en"><head>
<title>Poultry Cycle Workspace</title>
<?php include(dirname(__DIR__).'/navbar_head.php'); ?>
<style>
.workspace-stat{min-height:100%}.workspace-stat .value{font-size:1.25rem;font-weight:700}.explain-row{display:flex;justify-content:space-between;gap:18px;padding:.55rem 0;border-bottom:1px solid rgba(127,127,127,.16)}.explain-row:last-child{border-bottom:0}.explain-row .label{color:var(--bs-secondary-color)}.workspace-actions .btn{min-width:150px}.compact-table td,.compact-table th{vertical-align:middle}.section-anchor{scroll-margin-top:90px}
</style>
</head><body>
<?php include(dirname(__DIR__).'/navbar.php'); ?>
<div class="container-fluid px-3 px-lg-4 py-3">
  <?php if($flashSuccess): ?><div class="alert alert-success"><?php echo htmlspecialchars($flashSuccess); ?></div><?php endif; ?>
  <?php if($flashError): ?><div class="alert alert-danger"><?php echo htmlspecialchars($flashError); ?></div><?php endif; ?>
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
      <div class="text-muted small text-uppercase"><?php echo htmlspecialchars(strtoupper($type)); ?> · Poultry Cycle Workspace</div>
      <h3 class="mb-1"><?php echo htmlspecialchars($cycle['cycle_code']); ?></h3>
      <div class="text-muted">One place to understand this flock. Source records remain in their existing modules; this workspace does not duplicate accounting.</div>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="<?php echo BASE_URL; ?>/management/production_cycles.php">← Production Cycles</a>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card workspace-stat"><div class="card-body"><div class="text-muted small">Operational Status</div><div class="value text-uppercase"><?php echo htmlspecialchars($cycle['status']); ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card workspace-stat"><div class="card-body"><div class="text-muted small">Biological Phase</div><div class="value"><?php echo htmlspecialchars($current ? poultry_lifecycle_phase_label($type,$current['phase']) : 'Not defined'); ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card workspace-stat"><div class="card-body"><div class="text-muted small">Current Flock</div><div class="value"><?php echo $currentFlock===null?'-':number_format($currentFlock); ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card workspace-stat"><div class="card-body"><div class="text-muted small">Bird Cost Basis</div><div class="value"><?php echo moneyOrDash($cycle['bird_unit_cost']); ?></div><div class="small text-muted"><?php echo $cycle['bird_unit_cost']===null?'Not configured · ':''; ?>Used for mortality valuation only; separate from Production-Entry Economic Basis.</div></div></div></div>
  </div>

  <div class="card mb-3">
    <div class="card-header"><strong>Cycle Overview</strong></div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-4"><div class="text-muted small">Cycle Start</div><strong><?php echo htmlspecialchars($cycle['start_date']); ?></strong></div>
        <div class="col-md-4"><div class="text-muted small">Opening Headcount</div><strong><?php echo number_format((int)$cycle['opening_headcount']); ?></strong></div>
        <div class="col-md-4"><div class="text-muted small">Expected End</div><strong><?php echo htmlspecialchars($cycle['expected_end_date']??'-'); ?></strong></div>
      </div>
      <div class="workspace-actions d-flex flex-wrap gap-2 mt-3">
        <a class="btn btn-outline-primary btn-sm" href="#entry">Entry & Acquisition</a>
        <a class="btn btn-outline-primary btn-sm" href="#lifecycle">Lifecycle</a>
        <?php if($type==='layer'): ?><a class="btn btn-outline-primary btn-sm" href="#economics">Rearing Economics</a><?php endif; ?>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo BASE_URL.$dailyUrl; ?>">Daily Records</a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo BASE_URL.$feedsUrl; ?>">Feed Records</a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo BASE_URL; ?>/poultry/health.php">Health & Treatment</a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo BASE_URL.$expensesUrl; ?>">Expenses</a>
      </div>
    </div>
  </div>

  <div id="entry" class="card mb-3 section-anchor">
    <div class="card-header d-flex justify-content-between align-items-center"><strong>Entry & Acquisition</strong><span class="badge bg-info">Read only here</span></div>
    <div class="card-body">
      <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="text-muted small">Recorded Quantity</div><h5><?php echo number_format((int)$acqSummary['quantity']); ?></h5></div>
        <div class="col-md-4"><div class="text-muted small">Active Acquisition Cost</div><h5><?php echo moneyOrDash($acqSummary['total_cost']); ?></h5></div>
        <div class="col-md-4"><div class="text-muted small">Effective Cost / Bird</div><h5><?php echo moneyOrDash($acqSummary['effective_cost_per_bird']); ?></h5></div>
      </div>
      <div class="table-responsive"><table class="table table-sm compact-table mb-2"><thead><tr><th>Date</th><th>Entry</th><th>Age</th><th class="text-end">Qty</th><th class="text-end">Cost</th><th>Status</th><th>Source / Ref</th></tr></thead><tbody>
      <?php if(!$acqHistory): ?><tr><td colspan="7" class="text-center text-muted">No flock-entry history recorded.</td></tr><?php else: foreach($acqHistory as $r): ?>
      <tr><td><?php echo htmlspecialchars($r['acquisition_date']); ?></td><td><?php echo htmlspecialchars(poultry_acquisition_type_label($type,$r['acquisition_type'])); ?></td><td><?php echo (int)$r['age_days']; ?> days</td><td class="text-end"><?php echo number_format((int)$r['quantity']); ?></td><td class="text-end"><?php echo moneyOrDash($r['total_cost']); ?></td><td><?php echo empty($r['voided_at'])?'<span class="badge bg-success">Active</span>':'<span class="badge bg-secondary">Voided</span>'; ?></td><td><?php echo htmlspecialchars(trim(($r['source_name']??'').' '.($r['reference_no']??''))?:'-'); ?></td></tr>
      <?php endforeach; endif; ?></tbody></table></div>
      <a class="btn btn-outline-secondary btn-sm" href="<?php echo BASE_URL; ?>/management/production_cycles.php#poultry-entry-acquisition">Manage acquisition records on Production Cycles</a>
    </div>
  </div>

  <div id="lifecycle" class="card mb-3 section-anchor">
    <div class="card-header d-flex justify-content-between align-items-center"><strong>Biological Lifecycle</strong><span class="badge bg-info">Read only here</span></div>
    <div class="card-body">
      <div class="table-responsive"><table class="table table-sm compact-table mb-2"><thead><tr><th>Phase</th><th>Start</th><th>End</th><th>Notes</th></tr></thead><tbody>
      <?php if(!$history): ?><tr><td colspan="4" class="text-center text-muted">Lifecycle history is not yet defined. It is not inferred from bird age, eggs, feed, mortality, or cycle status.</td></tr><?php else: foreach($history as $r): ?>
      <tr><td><strong><?php echo htmlspecialchars(poultry_lifecycle_phase_label($type,$r['phase'])); ?></strong></td><td><?php echo htmlspecialchars($r['start_date']); ?></td><td><?php echo htmlspecialchars($r['end_date']??'Open'); ?></td><td><?php echo htmlspecialchars($r['notes']??'-'); ?></td></tr>
      <?php endforeach; endif; ?></tbody></table></div>
      <a class="btn btn-outline-secondary btn-sm" href="<?php echo BASE_URL; ?>/management/production_cycles.php#poultry-lifecycle-history">Manage lifecycle on Production Cycles</a>
    </div>
  </div>

  <?php if($type==='layer' && $economics!==null): ?>
  <div id="economics" class="card mb-3 section-anchor">
    <div class="card-header d-flex justify-content-between align-items-center"><strong>Layer Rearing & Production-Entry Economics</strong></div>
    <div class="card-body">
      <p class="text-muted mb-3"><?php echo htmlspecialchars($economics['message']); ?></p>
      <?php if(!$economics['available']): ?>
        <div class="alert alert-secondary mb-0">No economics are invented. Record a defensible lifecycle/acquisition history before rearing economics can be interpreted.</div>
      <?php elseif($economics['mode']==='pol'): ?>
        <div class="row g-3">
          <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">Entry Model</div><h5>Purchased Point-of-Lay</h5></div></div></div>
          <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">Production-entry Acquisition Basis</div><h5><?php echo moneyOrDash($economics['rearing_investment']); ?></h5></div></div></div>
          <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">Effective Acquisition Basis / Bird</div><h5><?php echo moneyOrDash($economics['investment_per_surviving_bird']); ?></h5></div></div></div>
        </div>
        <div class="mt-3"><strong>On-farm rearing investment:</strong> Not applicable. No on-farm rearing history is created for purchased POL birds.</div>
      <?php else: ?>
        <div class="row g-3 mb-3">
          <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Rearing Window</div><h6><?php echo htmlspecialchars($economics['rearing_phase']['start_date'].' → '.$economics['rearing_phase']['end_date']); ?></h6></div></div></div>
          <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Known Attributable Rearing Cost</div><h5><?php echo moneyOrDash($economics['known_attributable_rearing_cost']); ?></h5></div></div></div>
          <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Attributed Rearing Investment</div><h5><?php echo moneyOrDash($economics['rearing_investment']); ?></h5></div></div></div>
          <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Investment / Surviving Production Bird</div><h5><?php echo moneyOrDash($economics['investment_per_surviving_bird']); ?></h5></div></div></div>
        </div>
        <div class="explain-row"><span class="label">Bird acquisition basis</span><strong><?php echo moneyOrDash($economics['acquisition_cost']); ?></strong></div>
        <div class="explain-row"><span class="label">Feed actually consumed during Rearing</span><strong><?php echo moneyOrDash($economics['feed_consumed_cost']); ?></strong></div>
        <div class="explain-row"><span class="label">Medication/Vaccine, supplements & consumables USED</span><strong><?php echo moneyOrDash($economics['inventory_operating_cost']); ?></strong></div>
        <div class="explain-row"><span class="label">Direct non-feed expenses</span><strong><?php echo moneyOrDash($economics['direct_expenses']); ?></strong></div>
        <div class="explain-row"><span class="label">Explicit shared-expense allocations to this cycle</span><strong><?php echo moneyOrDash($economics['allocated_shared_expenses']); ?></strong></div>
        <div class="explain-row"><span class="label">Surviving flock at Production entry</span><strong><?php echo $economics['production_entry_headcount']===null?'-':number_format((int)$economics['production_entry_headcount']); ?></strong></div>
        <?php if($economics['production_entry_headcount_source']): ?><div class="small text-muted mt-2">Headcount source: <?php echo htmlspecialchars($economics['production_entry_headcount_source']); ?></div><?php endif; ?>
        <?php if((float)$economics['unallocated_shared_expense_pool']>0): ?><div class="alert alert-warning mt-3 mb-0"><strong>Unallocated shared Layer expense pool in this rearing window:</strong> <?php echo moneyOrDash($economics['unallocated_shared_expense_pool']); ?>. It is disclosed but not silently assigned to this cycle.</div><?php endif; ?>
      <?php endif; ?>
      <?php if(!empty($economics['warnings'])): ?>
        <?php $visibleWarnings=array_values(array_filter($economics['warnings'],static function($warning) use ($economics): bool {
          return !((float)$economics['unallocated_shared_expense_pool']>0 && str_starts_with((string)$warning,'Unallocated shared Layer expenses exist in the Rearing window.'));
        })); ?>
        <?php if($visibleWarnings): ?><div class="mt-3"><?php foreach($visibleWarnings as $w): ?><div class="alert alert-warning py-2 mb-2"><?php echo htmlspecialchars($w); ?></div><?php endforeach; ?></div><?php endif; ?>
      <?php endif; ?>
      <div class="small text-muted mt-3">This is a read layer over recorded source transactions. It does not alter monthly profitability, Bird Cost Basis, inventory, expenses, mortality records, or lifecycle history.</div>
    </div>
  </div>

  <div id="economic-basis" class="card mb-3 section-anchor">
    <div class="card-header d-flex justify-content-between align-items-center"><strong>Production-Entry Economic Basis</strong></div>
    <div class="card-body">
      <div class="small text-muted mb-3">Production-Entry Economic Basis is accumulated attributable rearing investment per surviving bird at production entry. It is a separate management-costing measure and does not replace Bird Cost Basis used for mortality valuation.</div>
      <?php if($latestEntrySnapshot): ?>
        <?php
          $changed=$entryCandidate && !empty($entryCandidate['ready']) && !hash_equals((string)$latestEntrySnapshot['source_fingerprint'],(string)$entryCandidate['source_fingerprint']);
          $investmentChanged=$entryCandidate && !empty($entryCandidate['ready']) && abs((float)$entryCandidate['attributed_investment']-(float)$latestEntrySnapshot['attributed_investment'])>0.0049;
          $flockChanged=$entryCandidate && !empty($entryCandidate['ready']) && (int)$entryCandidate['production_entry_headcount']!==(int)$latestEntrySnapshot['production_entry_headcount'];
        ?>
        <div class="row g-3 mb-3">
          <div class="col-md-3"><div class="text-muted small">Current Approved Version</div><h5>V<?php echo (int)$latestEntrySnapshot['version_no']; ?> · <?php echo htmlspecialchars(ucfirst($latestEntrySnapshot['snapshot_status'])); ?></h5></div>
          <div class="col-md-3"><div class="text-muted small">Approved Attributed Investment</div><h5><?php echo moneyOrDash($latestEntrySnapshot['attributed_investment']); ?></h5></div>
          <div class="col-md-3"><div class="text-muted small">Approved Production-entry Flock</div><h5><?php echo number_format((int)$latestEntrySnapshot['production_entry_headcount']); ?></h5></div>
          <div class="col-md-3"><div class="text-muted small">Approved Economic Basis / Entry Bird</div><h5><?php echo moneyOrDash($latestEntrySnapshot['investment_per_entry_bird']); ?></h5></div>
        </div>
        <?php if($changed): ?>
          <div class="alert alert-warning"><strong>Historical source economics changed after the latest approval.</strong>
            <?php if($investmentChanged): ?><div class="mt-2"><strong>Attributed investment changed:</strong> current <?php echo moneyOrDash($entryCandidate['attributed_investment']); ?> vs approved V<?php echo (int)$latestEntrySnapshot['version_no']; ?> <?php echo moneyOrDash($latestEntrySnapshot['attributed_investment']); ?>.</div><?php endif; ?>
            <?php if($flockChanged): ?><div class="mt-1"><strong>Production-entry flock changed:</strong> current <?php echo number_format((int)$entryCandidate['production_entry_headcount']); ?> vs approved V<?php echo (int)$latestEntrySnapshot['version_no']; ?> <?php echo number_format((int)$latestEntrySnapshot['production_entry_headcount']); ?>.</div><?php endif; ?>
            <?php if(!$investmentChanged && !$flockChanged): ?><div class="mt-2">Source records changed, but the approved attributed investment and production-entry flock remain numerically unchanged.</div><?php endif; ?>
            <div class="mt-1">Current economic basis / bird is <?php echo moneyOrDash($entryCandidate['investment_per_entry_bird']); ?>; approved V<?php echo (int)$latestEntrySnapshot['version_no']; ?> is <?php echo moneyOrDash($latestEntrySnapshot['investment_per_entry_bird']); ?>. Review the corrected source records before approving a revision.</div>
          </div>
        <?php elseif($entryCandidate && !empty($entryCandidate['ready'])): ?>
          <div class="alert alert-success">Current source-derived economics match the latest approved version.</div>
        <?php endif; ?>
      <?php else: ?>
        <div class="alert alert-secondary">No approved Production-Entry Economic Basis exists yet. Lifecycle and source accounting remain independent from this approval.</div>
      <?php endif; ?>

      <?php if($entryCandidate && !empty($entryCandidate['ready'])): ?>
        <div class="row g-3 mb-3">
          <div class="col-md-4"><div class="text-muted small">Current Source-Derived Attributed Investment</div><strong><?php echo moneyOrDash($entryCandidate['attributed_investment']); ?></strong></div>
          <div class="col-md-4"><div class="text-muted small">Current Production-entry Flock</div><strong><?php echo number_format((int)$entryCandidate['production_entry_headcount']); ?></strong></div>
          <div class="col-md-4"><div class="text-muted small">Current Source-Derived Economic Basis / Bird</div><strong><?php echo moneyOrDash($entryCandidate['investment_per_entry_bird']); ?></strong></div>
        </div>
        <?php $needsApproval=!$latestEntrySnapshot || !hash_equals((string)$latestEntrySnapshot['source_fingerprint'],(string)$entryCandidate['source_fingerprint']); ?>
        <?php if($needsApproval): ?>
        <form method="post" class="border rounded p-3 mb-3">
          <input type="hidden" name="action" value="approve_production_entry_basis">
          <input type="hidden" name="cycle_id" value="<?php echo (int)$cycleId; ?>">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Approval / Revision Category</label>
              <select class="form-select" name="revision_category" required>
                <?php if(!$latestEntrySnapshot): ?><option value="production_entry_confirmation">Production-entry confirmation</option><?php else: ?>
                <option value="">Select correction category</option>
                <option value="source_transaction_correction">Source transaction correction</option>
                <option value="missing_historical_transaction">Missing historical transaction</option>
                <option value="allocation_correction">Allocation correction</option>
                <option value="production_entry_headcount_correction">Production-entry headcount correction</option>
                <option value="administrative_correction">Administrative correction</option>
                <?php endif; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Revision Reason <?php echo $latestEntrySnapshot?'(required)':'(optional)'; ?></label>
              <input class="form-control" name="revision_reason" maxlength="500" <?php echo $latestEntrySnapshot?'required':''; ?> placeholder="Explain the correction; do not replace source accounting here">
            </div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-success w-100" type="submit"><?php echo $latestEntrySnapshot?'Approve Revised Basis':'Confirm Entry Basis'; ?></button></div>
          </div>
          <div class="small text-muted mt-2">Approval freezes this version only. Future source corrections are detected and require a new approved version; Bird Cost Basis is not changed.</div>
        </form>
        <?php endif; ?>
      <?php else: ?>
        <div class="alert alert-warning"><strong>Basis pending:</strong> <?php echo htmlspecialchars($entryCandidate['reason']??'Source-derived production-entry economics are not ready.'); ?></div>
      <?php endif; ?>

      <?php if($entrySnapshots): ?>
      <div class="table-responsive"><table class="table table-sm compact-table mb-0">
        <thead><tr><th>Version</th><th>Status</th><th>Entry Date</th><th class="text-end">Investment</th><th class="text-end">Entry Flock</th><th class="text-end">Basis/Bird</th><th>Category / Reason</th><th>Approved</th></tr></thead>
        <tbody><?php foreach($entrySnapshots as $snap): ?><tr>
          <td>V<?php echo (int)$snap['version_no']; ?></td><td><?php echo htmlspecialchars(ucfirst($snap['snapshot_status'])); ?></td>
          <td><?php echo htmlspecialchars($snap['production_entry_date']); ?></td><td class="text-end"><?php echo moneyOrDash($snap['attributed_investment']); ?></td>
          <td class="text-end"><?php echo number_format((int)$snap['production_entry_headcount']); ?></td><td class="text-end"><?php echo moneyOrDash($snap['investment_per_entry_bird']); ?></td>
          <td><?php echo htmlspecialchars(str_replace('_',' ',(string)$snap['revision_category'])); ?><?php echo !empty($snap['revision_reason'])?' · '.htmlspecialchars($snap['revision_reason']):''; ?></td>
          <td><?php echo htmlspecialchars($snap['approved_at']); ?><?php echo !empty($snap['approved_by_name'])?' · '.htmlspecialchars($snap['approved_by_name']):''; ?></td>
        </tr><?php endforeach; ?></tbody>
      </table></div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
</body></html>
