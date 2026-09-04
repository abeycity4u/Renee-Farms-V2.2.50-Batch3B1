<?php
require_once(dirname(__DIR__) . '/init.php');
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../includes/functions.php');
require_once(__DIR__ . '/../includes/financial.php');
require_once(__DIR__ . '/../lib/stock_reporting.php');
require_once(__DIR__ . '/../lib/stock_costing.php');
require_once(__DIR__ . '/../lib/attribution.php');
requireLogin();
requireBusinessReportAccess();
if (!isPlatformOwner() && !hasRole('farm_admin') && !hasPermission(getUserType(), 'profitability')) {
    header('Location: ' . BASE_URL . '/no_access.php');
    exit();
}

$farmId = requireCurrentFarmId();
$access = getUserFarmType();
$canChoose = isPlatformOwner() || hasRole('farm_admin','sales_rep');
$farmType = $canChoose ? ($_GET['farm_type'] ?? 'all') : ($access === 'all' ? 'all' : $access);
if (!in_array($farmType, ['all','poultry','ruminant','general'], true)) $farmType = 'all';

$productionType = strtolower(trim((string)($_GET['production_type'] ?? 'all')));
$productionOptions = $farmType === 'all' ? [] : attribution_production_types($farmType);
if ($productionType !== 'all' && !isset($productionOptions[$productionType])) $productionType = 'all';

$period = ($_GET['period'] ?? 'monthly') === 'daily' ? 'daily' : 'monthly';
$selectedDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '') ? $_GET['date'] : date('Y-m-d');
$month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
if ($period === 'daily') {
    $start = $selectedDate;
    $end = $selectedDate;
    $periodLabel = date('j M Y', strtotime($selectedDate));
} else {
    $start = $month . '-01';
    $end = date('Y-m-t', strtotime($start));
    $periodLabel = date('F Y', strtotime($start));
}

$cycleId = (int)($_GET['cycle_id'] ?? 0);
$cyclesStmt = $pdo->prepare("SELECT id,cycle_code,farm_type,production_type,status FROM production_cycles WHERE farm_id=? ORDER BY start_date DESC,id DESC");
$cyclesStmt->execute([$farmId]);
$cycles = $cyclesStmt->fetchAll(PDO::FETCH_ASSOC);
$visibleCycles = array_values(array_filter($cycles, static function(array $cycle) use ($farmType, $productionType): bool {
    if ($farmType !== 'all' && strtolower((string)$cycle['farm_type']) !== $farmType) return false;
    if ($productionType !== 'all' && strtolower((string)$cycle['production_type']) !== $productionType) return false;
    return true;
}));
$validCycleIds = array_map('intval', array_column($visibleCycles,'id'));
if ($cycleId && !in_array($cycleId, $validCycleIds, true)) $cycleId = 0;

$summary = getProfitabilitySummary($pdo, $farmId, $start, $end, $farmType, $cycleId ?: null, $productionType === 'all' ? null : $productionType);

$poultryEconomics = ['available' => false];
if ($farmType === 'poultry' && in_array($productionType, ['layer', 'broiler'], true)) {
    $poultryEconomics = getPoultryUnitEconomics(
        $pdo,
        $farmId,
        $start,
        $end,
        $productionType,
        $summary,
        $cycleId ?: null
    );
}

// Mortality value is kept separate from period profitability. Layer/Broiler
// birds are productive biological assets; mortality records the number/value
// of productive birds lost without creating or implying another cash/operating
// expense in the selected period.
$summary['mortality_value'] = !empty($poultryEconomics['available'])
    ? (float)$poultryEconomics['mortality_cost']
    : 0.0;

// Cycle-level profitability deliberately excludes pooled revenue until it is
// allocated. Surface the amount still waiting for allocation so a zero/low
// result is explainable rather than silently implying no sales occurred.
$unallocatedRevenue = 0.0;
if ($cycleId > 0 && $farmType !== 'all') {
    $unallocatedRevenue = getUnallocatedSalesRevenue($pdo, $farmId, $start, $end, $farmType, $productionType === 'all' ? null : $productionType);
}

$periodLabel = htmlspecialchars($periodLabel);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include(__DIR__ . '/../navbar_head.php'); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profitability - Renee Farms</title>
</head>
<body>
<?php include(__DIR__ . '/../navbar.php'); ?>
<div class="container-fluid mt-4">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h4 class="mb-0"><i class="bi bi-graph-up-arrow"></i> Profitability</h4>
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <select class="form-select" id="farmTypeFilter" style="width:150px">
                    <?php if ($canChoose): ?>
                        <option value="all" <?= $farmType==='all'?'selected':'' ?>>All Farms</option>
                        <?php foreach (accessibleFarmTypes() as $type): ?><option value="<?= htmlspecialchars($type) ?>" <?= $farmType===$type?'selected':'' ?>><?= htmlspecialchars(ucfirst($type)) ?></option><?php endforeach; ?>
                        <option value="general" <?= $farmType==='general'?'selected':'' ?>>General</option>
                    <?php else: ?>
                        <option value="<?= htmlspecialchars($farmType) ?>" selected><?= htmlspecialchars(ucfirst($farmType)) ?></option>
                    <?php endif; ?>
                </select>
                <select class="form-select" id="productionTypeFilter" style="width:190px">
                    <option value="all">All Production Types</option>
                    <?php foreach ($productionOptions as $value=>$label): ?><option value="<?= htmlspecialchars($value) ?>" <?= $productionType===$value?'selected':'' ?>><?= htmlspecialchars($label) ?></option><?php endforeach; ?>
                </select>
                <select class="form-select" id="cycleFilter" style="width:220px">
                    <option value="0">All cycles</option>
                    <?php foreach ($visibleCycles as $cycle): ?><option value="<?= (int)$cycle['id'] ?>" <?= $cycleId===(int)$cycle['id']?'selected':'' ?>><?= htmlspecialchars($cycle['cycle_code']) ?></option><?php endforeach; ?>
                </select>
                <select class="form-select" id="periodFilter" style="width:130px">
                    <option value="monthly" <?= $period==='monthly'?'selected':'' ?>>Monthly</option>
                    <option value="daily" <?= $period==='daily'?'selected':'' ?>>Daily</option>
                </select>
                <input type="month" class="form-control" id="monthFilter" value="<?= htmlspecialchars($month) ?>" style="width:160px;<?= $period==='daily'?'display:none;':'' ?>">
                <input type="date" class="form-control" id="dateFilter" value="<?= htmlspecialchars($selectedDate) ?>" style="width:160px;<?= $period==='monthly'?'display:none;':'' ?>">
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-4">
                <div class="col-md-3"><div class="card h-100"><div class="card-body"><small class="text-muted">Sales Revenue</small><h3 class="mb-0 text-success">₦<?= number_format($summary['revenue'],2) ?></h3></div></div></div>
                <div class="col-md-3"><div class="card h-100"><div class="card-body"><small class="text-muted">Feed Consumed Cost</small><h3 class="mb-0 text-danger">₦<?= number_format($summary['feed_cost'],2) ?></h3></div></div></div>
                <div class="col-md-3"><div class="card h-100"><div class="card-body"><small class="text-muted">Other Operating Expenses</small><h3 class="mb-0 text-danger">₦<?= number_format($summary['other_expenses'],2) ?></h3></div></div></div>
                <div class="col-md-3"><div class="card h-100"><div class="card-body"><small class="text-muted">Profit / Loss</small><h3 class="mb-0 <?= $summary['profit']>=0?'text-success':'text-danger' ?>">₦<?= number_format($summary['profit'],2) ?></h3></div></div></div>
            </div>

            <?php if ($cycleId > 0 && $unallocatedRevenue > 0): ?>
            <div class="alert alert-warning">
                <strong>Unallocated sales revenue:</strong> ₦<?= number_format($unallocatedRevenue,2) ?> is currently pooled/shared and is not attributed to this cycle, so it is excluded from cycle-level profit until allocated.
            </div>
            <?php endif; ?>

            <?php if (!empty($poultryEconomics['available'])): ?>
            <div class="row g-3 mb-4">
                <div class="col-md-4"><div class="card h-100"><div class="card-body"><small class="text-muted">Current Birds</small><h4 class="mb-0"><?= number_format((float)$poultryEconomics['closing_birds'],0) ?></h4></div></div></div>
                <div class="col-md-4"><div class="card h-100"><div class="card-body"><small class="text-muted">Mortality Value (separate)</small><h4 class="mb-0 text-warning">₦<?= number_format((float)$poultryEconomics['mortality_cost'],2) ?></h4></div></div></div>
                <div class="col-md-4"><div class="card h-100"><div class="card-body"><small class="text-muted">Bird Cost Basis</small><h4 class="mb-0">₦<?= number_format((float)$poultryEconomics['bird_unit_cost'],2) ?></h4></div></div></div>
            </div>
            <?php endif; ?>

            <div class="alert alert-info mb-0">
                <strong>Formula:</strong> Profit/Loss = Sales Revenue − Feed Consumed Cost − Other Operating Expenses. Inventory purchases are recognized when consumed, not when purchased. Mortality value is shown separately and does not change period operating profit.
            </div>
        </div>
    </div>
</div>
<script>
(function(){
  const controls=['farmTypeFilter','productionTypeFilter','cycleFilter','periodFilter','monthFilter','dateFilter'];
  controls.forEach(id=>{const el=document.getElementById(id);if(el)el.addEventListener('change',applyFilters);});
  function applyFilters(){
    const p=new URLSearchParams();
    const farm=document.getElementById('farmTypeFilter'); if(farm)p.set('farm_type',farm.value);
    const prod=document.getElementById('productionTypeFilter'); if(prod)p.set('production_type',prod.value);
    const cycle=document.getElementById('cycleFilter'); if(cycle)p.set('cycle_id',cycle.value);
    const period=document.getElementById('periodFilter'); if(period)p.set('period',period.value);
    const month=document.getElementById('monthFilter'); if(month && period.value==='monthly')p.set('month',month.value);
    const date=document.getElementById('dateFilter'); if(date && period.value==='daily')p.set('date',date.value);
    window.location='profitability.php?'+p.toString();
  }
})();
</script>
</body>
</html>