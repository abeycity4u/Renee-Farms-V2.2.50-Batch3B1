<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../lib/farm_intelligence.php');
requireLogin();
requireBusinessReportAccess();
$farmId = requireCurrentFarmId();
$userFarmType = getUserFarmType();
$canChooseFarmType = isPlatformOwner() || hasRole('farm_admin', 'sales_rep');
$requestedFarmType = $canChooseFarmType ? ($_GET['farm_type'] ?? null) : $userFarmType;
if ($requestedFarmType === 'both' && count(accessibleFarmTypes()) === 2) $requestedFarmType = 'all';
$salesOnlyScope = enabledFarmTypes() === [] && farmHasModule('sales') && (isPlatformOwner() || hasRole('farm_admin', 'sales_rep', 'viewer'));
$farmType = $salesOnlyScope ? 'all' : normalizeFarmType($requestedFarmType, true, false, $canChooseFarmType);
if ($farmType === 'general') $farmType = 'all';
$today = date('Y-m-d');
$intelligence = farm_intelligence_explainable_signals($pdo, $farmId, $farmType, $today);
$signals = $intelligence['signals'];
$grouped = [];
foreach ($signals as $signal) $grouped[$signal['category']][] = $signal;
$current = $intelligence['current_period']['financial'];
$prior = $intelligence['comparison_period']['financial'];
$pageTitle = 'Farm Intelligence';
function intel_money($value): string { return '₦'.number_format((float)$value, 2); }
function intel_href(string $path): string { return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include(__DIR__ . '/../navbar_head.php'); ?>
    <title>Farm Intelligence - Renee Farms</title>
    <style>
        .intel-shell{max-width:1440px;margin:0 auto}
        .intel-hero{border-left:3px solid var(--bs-primary)}
        .intel-hero .card-body{padding:.8rem 1rem}
        .intel-stat{border:1px solid var(--bs-border-color);border-radius:.5rem;padding:.5rem .65rem;height:100%}
        .intel-stat small{display:block;line-height:1.1}
        .intel-category-anchor{scroll-margin-top:80px}
        .intel-category{border:1px solid var(--bs-border-color);border-radius:.65rem;overflow:hidden;background:var(--bs-body-bg)}
        .intel-category-head{padding:.55rem .75rem;background:var(--bs-tertiary-bg);border-bottom:1px solid var(--bs-border-color)}
        .intel-row{padding:.6rem .75rem;border-bottom:1px solid var(--bs-border-color)}
        .intel-row:last-child{border-bottom:0}
        .intel-row-icon{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex:0 0 30px}
        .intel-measure{font-weight:700;white-space:nowrap}
        .intel-reason{line-height:1.3}
        .intel-action .btn{white-space:normal}
        @media (min-width:992px){.intel-action{min-width:135px;text-align:right}}
    </style>
</head>
<body>
<?php include(__DIR__ . '/../navbar.php'); ?>
<div class="container-fluid py-3">
<div class="intel-shell">
    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-2">
        <div>
            <h3 class="mb-0"><i class="bi bi-lightbulb"></i> Farm Intelligence</h3>
            <div class="small text-muted">Management signals from recorded financial, inventory, production and animal data.</div>
        </div>
        <a class="btn btn-sm btn-outline-secondary" href="<?php echo BASE_URL; ?>/dashboard.php"><i class="bi bi-arrow-left"></i> Dashboard</a>
    </div>

    <?php if ($canChooseFarmType && !$salesOnlyScope): ?>
    <form class="d-flex align-items-end gap-2 mb-2 app-responsive-form" method="get">
        <div style="min-width:280px;max-width:420px;flex:1"><label class="form-label small mb-1">Farm scope</label><select class="form-select form-select-sm" name="farm_type">
            <option value="all" <?php echo $farmType==='all'?'selected':''; ?>>All accessible farm activity</option>
            <?php if (in_array('poultry', accessibleFarmTypes(), true)): ?><option value="poultry" <?php echo $farmType==='poultry'?'selected':''; ?>>Poultry</option><?php endif; ?>
            <?php if (in_array('ruminant', accessibleFarmTypes(), true)): ?><option value="ruminant" <?php echo $farmType==='ruminant'?'selected':''; ?>>Ruminant</option><?php endif; ?>
        </select></div><button class="btn btn-sm btn-primary">Apply</button>
    </form>
    <?php endif; ?>

    <div class="card intel-hero mb-3"><div class="card-body">
        <div class="row g-2 align-items-center">
            <div class="col-lg-4">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="small text-muted fw-semibold text-uppercase">Management attention</span>
                    <strong class="text-<?php echo htmlspecialchars($intelligence['status_class']); ?>"><?php echo htmlspecialchars($intelligence['status']); ?></strong>
                    <?php if ($intelligence['counts']['danger']): ?><span class="badge bg-danger"><?php echo (int)$intelligence['counts']['danger']; ?> danger</span><?php endif; ?>
                    <?php if ($intelligence['counts']['warning']): ?><span class="badge bg-warning text-dark"><?php echo (int)$intelligence['counts']['warning']; ?> warning<?php echo $intelligence['counts']['warning']===1?'':'s'; ?></span><?php endif; ?>
                    <?php if ($intelligence['counts']['info']): ?><span class="badge bg-info text-dark"><?php echo (int)$intelligence['counts']['info']; ?> info</span><?php endif; ?>
                </div>
                <div class="small text-muted mt-1">Each insight shows what was detected, why it matters and where to review it.</div>
            </div>
            <div class="col-lg-8"><div class="row g-2">
                <div class="col-6 col-md-3"><div class="intel-stat"><small class="text-muted">Revenue</small><strong><?php echo intel_money($current['revenue']); ?></strong></div></div>
                <div class="col-6 col-md-3"><div class="intel-stat"><small class="text-muted">Feed Consumed</small><strong><?php echo intel_money($current['feed_consumption_cost']); ?></strong></div></div>
                <div class="col-6 col-md-3"><div class="intel-stat"><small class="text-muted">Other Op. Cost</small><strong><?php echo intel_money($current['non_feed_expenses']); ?></strong></div></div>
                <div class="col-6 col-md-3"><div class="intel-stat"><small class="text-muted">Operating P/L</small><strong class="text-<?php echo (float)$current['profit']<0?'danger':'success'; ?>"><?php echo intel_money($current['profit']); ?></strong></div></div>
            </div><div class="small text-muted mt-1"><?php echo htmlspecialchars($intelligence['current_period']['label']); ?> · comparison: <?php echo htmlspecialchars($intelligence['comparison_period']['label']); ?></div></div>
        </div>
    </div></div>

    <?php if (!$signals): ?><div class="alert alert-info py-2">No intelligence signals are available from the current recorded data.</div><?php endif; ?>

    <div class="row g-3">
    <?php foreach ($grouped as $category=>$categorySignals): ?>
    <section class="col-xl-6 intel-category-anchor" id="category-<?php echo htmlspecialchars(strtolower(str_replace(' ','-',$category))); ?>">
        <div class="intel-category h-100">
            <div class="intel-category-head d-flex justify-content-between align-items-center"><strong><?php echo htmlspecialchars($category); ?></strong><span class="badge bg-secondary"><?php echo count($categorySignals); ?></span></div>
            <?php foreach ($categorySignals as $signal): ?>
            <div class="intel-row">
                <div class="d-flex gap-2 align-items-start">
                    <span class="intel-row-icon bg-<?php echo htmlspecialchars($signal['severity']); ?>-subtle text-<?php echo htmlspecialchars($signal['severity']); ?>"><i class="bi <?php echo htmlspecialchars($signal['icon']); ?>"></i></span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex justify-content-between gap-2 flex-wrap"><strong><?php echo htmlspecialchars($signal['title']); ?></strong><span class="d-flex gap-1 flex-wrap align-items-center"><span class="badge bg-<?php echo htmlspecialchars($signal['severity']); ?><?php echo in_array($signal['severity'],['warning','info'],true)?' text-dark':''; ?>"><?php echo ucfirst(htmlspecialchars($signal['severity'])); ?></span><?php if(!empty($signal['followup_status'])): $fuResolved=$signal['followup_status']==='resolved'; $fuNew=!empty($signal['followup_new_evidence']); ?><span class="badge <?php echo $fuNew?'bg-danger-subtle text-danger-emphasis':($fuResolved?'bg-success-subtle text-success-emphasis':'bg-warning-subtle text-warning-emphasis'); ?>"><i class="bi <?php echo $fuNew?'bi-arrow-repeat':($fuResolved?'bi-check-circle':'bi-clock-history'); ?>"></i> <?php echo $fuNew?'New activity since review':($fuResolved?'Previously resolved':'Follow-up open'); ?></span><?php endif; ?></span></div>
                        <div class="d-flex gap-2 flex-wrap align-items-baseline mt-1"><span class="intel-measure"><?php echo htmlspecialchars($signal['measured_value']); ?></span><span class="small text-muted"><i class="bi bi-calendar3"></i> <?php echo htmlspecialchars($signal['period_label']); ?></span></div>
                        <?php if(!empty($signal['followup_status'])):?><div class="small mt-1"><span class="text-muted">Management follow-through:</span> <strong><?php echo htmlspecialchars($signal['followup_status']==='resolved'?'Resolved':'Open'); ?></strong><?php if(!empty($signal['followup_as_of'])):?> · <?php echo htmlspecialchars(date('M j, Y',strtotime($signal['followup_as_of']))); ?><?php endif;?><?php if(!empty($signal['followup_outcome'])):?> · <?php echo htmlspecialchars(investigation_followup_outcomes()[$signal['followup_outcome']]??ucwords(str_replace('_',' ',$signal['followup_outcome']))); ?><?php endif;?><?php if(!empty($signal['followup_new_evidence'])):?><span class="text-danger-emphasis"> · new source record <?php echo htmlspecialchars(date('M j, Y',strtotime($signal['followup_evidence_date']))); ?></span><?php elseif(empty($signal['followup_exact_as_of'])):?><span class="text-muted"> · previous review context</span><?php endif;?></div><?php endif;?>
                        <div class="small text-muted intel-reason mt-1"><?php echo htmlspecialchars($signal['reason']); ?></div>
                    </div>
                    <div class="intel-action"><a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars(intel_href($signal['action_url'])); ?>"><?php echo htmlspecialchars($signal['action_label']); ?> <i class="bi bi-arrow-right"></i></a></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endforeach; ?>
    </div>
</div>
</div>
</body>
</html>
