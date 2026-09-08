<?php require_once(__DIR__ . '/init.php'); ?>
<?php
// navbar.php - Main Navigation (permission-aware)
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}

if (!isLoggedIn()) {
    return;
}

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/notifications.php';
require_once __DIR__ . '/includes/subscription_renewal_notice.php';

$subscriptionNotice = null;
if (!isPlatformOwner() && hasRole('farm_admin')) {
    $subscriptionNotice = subscription_renewal_notice(currentFarm());
}

$navHas = static function (string $permission): bool {
    return isPlatformOwner() || hasRole('farm_admin') || hasPermission(getUserType(), $permission);
};

$poultryEntitled = isPlatformOwner() || user_can_access_entitled_module('poultry');
$ruminantEntitled = isPlatformOwner() || user_can_access_entitled_module('ruminant');
$salesEntitled = isPlatformOwner() || user_can_access_entitled_module('sales');

// A Sales Representative does not become a Poultry/Ruminant operational manager,
// but Farm Admin may delegate the expense record surfaces within a subscribed farm
// module. Keep this exception narrow to expense navigation only.
$poultryExpenseEntitled = $poultryEntitled
    || (hasRole('sales_rep') && current_farm_has_entitlement('poultry'));
$ruminantExpenseEntitled = $ruminantEntitled
    || (hasRole('sales_rep') && current_farm_has_entitlement('ruminant'));

$canViewInventory = $navHas('inventory');

$canViewLayerDaily = $poultryEntitled && $navHas('poultry_daily_layer');
$canViewBroilerDaily = $poultryEntitled && $navHas('poultry_daily_broiler');
$canViewPoultryFeeds = $poultryEntitled && $navHas('poultry_feeds');
$canViewPoultryHealth = $poultryEntitled && $navHas('poultry_health');
$canViewLayerExpenses = $poultryExpenseEntitled && $navHas('poultry_layer_expenses');
$canViewBroilerExpenses = $poultryExpenseEntitled && $navHas('poultry_broiler_expenses');
$showPoultryMenu = $canViewLayerDaily || $canViewBroilerDaily || $canViewPoultryFeeds || $canViewPoultryHealth || $canViewLayerExpenses || $canViewBroilerExpenses;

$canViewRuminantDaily = $ruminantEntitled && $navHas('ruminant_daily');
$canViewRuminantAnimals = $ruminantEntitled && $navHas('ruminant_animals');
$canViewRuminantFeeds = $ruminantEntitled && $navHas('ruminant_feeds');
$canViewRuminantExpenses = $ruminantExpenseEntitled && $navHas('ruminant_expenses');
$showRuminantMenu = $canViewRuminantDaily || $canViewRuminantAnimals || $canViewRuminantFeeds || $canViewRuminantExpenses;

$canViewSales = $salesEntitled && $navHas('sales');
$canViewExpenseReport = $navHas('expenses');
$canViewReports = $navHas('reports');
$canViewFarmIntelligence = $navHas('farm_intelligence');
$canViewProfitability = $navHas('profitability');
$canViewProductionCycles = $navHas('production_cycles');
$canManageUsers = $navHas('users');
$canManagePermissions = isPlatformOwner() || hasRole('farm_admin');
$showManagementMenu = $canViewSales || $canViewExpenseReport || $canViewReports || $canViewFarmIntelligence || $canViewProfitability || $canViewProductionCycles || $canManageUsers || $canManagePermissions || isPlatformOwner();
?>

<div id="appNotifications" class="app-notifications" aria-live="polite" aria-atomic="false">
  <?php renderSessionNotifications(); ?>
</div>

<?php if ($subscriptionNotice): ?>
<?php $subscriptionNoticeSeverity = ($subscriptionNotice['severity'] ?? 'warning') === 'danger' ? 'danger' : 'warning'; ?>
<div class="alert alert-<?php echo $subscriptionNoticeSeverity; ?> rounded-0 mb-0 text-center no-print d-flex flex-wrap align-items-center justify-content-center gap-2" role="status">
  <span><?php echo htmlspecialchars((string)$subscriptionNotice['message'], ENT_QUOTES, 'UTF-8'); ?></span>
  <a class="btn btn-sm btn-dark fw-semibold" href="<?php echo htmlspecialchars(BASE_URL . (string)$subscriptionNotice['action_path'], ENT_QUOTES, 'UTF-8'); ?>">
    <?php echo htmlspecialchars((string)$subscriptionNotice['action_label'], ENT_QUOTES, 'UTF-8'); ?>
  </a>
</div>
<?php endif; ?>
<nav id="appNavbar" class="navbar navbar-expand-lg navbar-dark bg-success shadow-sm no-print">
  <div class="container-fluid">
    <a class="navbar-brand farm-brand" href="<?php echo BASE_URL; ?>/dashboard.php">
      <span class="brand-logo-badge">
        <img src="<?php echo htmlspecialchars(farmLogoUrl(), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars(farmBrandName(), ENT_QUOTES, 'UTF-8'); ?> logo" height="30" class="brand-logo-img">
      </span>
      <span class="brand-text"><?php echo htmlspecialchars(farmBrandName(), ENT_QUOTES, 'UTF-8'); ?></span>
    </a>
    <button class="navbar-toggler collapsed" type="button" data-app-navbar-toggle aria-controls="navbarMenu" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarMenu">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0 main-nav-spacing">

        <li class="nav-item">
          <a class="nav-link" href="<?php echo BASE_URL; ?>/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
        </li>

        <?php if ($canViewInventory): ?>
        <li class="nav-item">
          <a class="nav-link" href="<?php echo BASE_URL; ?>/inventory.php">
            <i class="bi bi-box-seam"></i> Inventory
          </a>
        </li>
        <?php endif; ?>

        <?php if ($showPoultryMenu): ?>
        <!-- Poultry Dropdown: visible when at least one child page is permitted -->
        <li class="nav-item dropdown">
          <button class="nav-link dropdown-toggle btn btn-link" type="button" id="poultryMenu" data-nav-dropdown-toggle="dropdown" aria-expanded="false"> <i class="bi bi-egg"></i> Poultry</button>
          <ul class="dropdown-menu">
            <?php if ($canViewLayerDaily): ?>
            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/poultry/layers_daily_record.php"><i class="bi bi-journal-check menu-icon me-2"></i> Layer Daily Record</a></li>
            <?php endif; ?>
            <?php if ($canViewBroilerDaily): ?>
            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/poultry/broiler_daily_record.php"><i class="bi bi-journal-text menu-icon me-2"></i> Broiler Daily Record</a></li>
            <?php endif; ?>
            <?php if ($canViewPoultryFeeds): ?>
            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/poultry/layer_feeds.php"><i class="bi bi-basket menu-icon me-2"></i> Layer Feeds</a></li>
            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/poultry/broiler_feeds.php"><i class="bi bi-basket2 menu-icon me-2"></i> Broiler Feeds</a></li>
            <?php endif; ?>
            <?php if ($canViewPoultryHealth): ?>
            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/poultry/health.php"><i class="bi bi-heart-pulse menu-icon me-2"></i> Health &amp; Treatment</a></li>
            <?php endif; ?>
            <?php if ($canViewLayerExpenses): ?>
            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/poultry/layer_expenses.php"><i class="bi bi-cash menu-icon me-2"></i> Layer Expenses</a></li>
            <?php endif; ?>
            <?php if ($canViewBroilerExpenses): ?>
            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/poultry/broiler_expenses.php"><i class="bi bi-cash-stack menu-icon me-2"></i> Broiler Expenses</a></li>
            <?php endif; ?>
          </ul>
        </li>
        <?php endif; ?>

        <?php if ($showRuminantMenu): ?>
        <!-- Ruminant Dropdown: visible when at least one child page is permitted -->
        <li class="nav-item dropdown">
          <button class="nav-link dropdown-toggle btn btn-link" type="button" id="ruminantMenu" data-nav-dropdown-toggle="dropdown" aria-expanded="false"> <i class="bi bi-cow"></i> Ruminants</button>
          <ul class="dropdown-menu">
            <?php if ($canViewRuminantDaily): ?>
            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/ruminant/ruminant_daily_record.php"><i class="bi bi-journal-richtext menu-icon me-2"></i> Daily Record</a></li>
            <?php endif; ?>
            <?php if ($canViewRuminantAnimals): ?>
            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/ruminant/animal_registry.php"><i class="bi bi-tags menu-icon me-2"></i> Animal Registry</a></li>
            <?php endif; ?>
            <?php if ($canViewRuminantFeeds): ?>
            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/ruminant/ruminant_feeds_record.php"><i class="bi bi-basket3 menu-icon me-2"></i> Ruminant Feeds</a></li>
            <?php endif; ?>
            <?php if ($canViewRuminantExpenses): ?>
            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/ruminant/ruminant_expenses.php"><i class="bi bi-receipt menu-icon me-2"></i> Expenses</a></li>
            <?php endif; ?>
          </ul>
        </li>
        <?php endif; ?>

        <?php if ($showManagementMenu): ?>
        <!-- Management Dropdown -->
        <li class="nav-item dropdown">
          <button class="nav-link dropdown-toggle btn btn-link" type="button" id="manageMenu" data-nav-dropdown-toggle="dropdown" aria-expanded="false"> <i class="bi bi-briefcase"></i> Management</button>
          <ul class="dropdown-menu">
            <?php if ($canViewSales || $canViewExpenseReport || $canViewReports || $canViewFarmIntelligence || $canViewProfitability || $canViewProductionCycles): ?>
            <li><h6 class="dropdown-header">Reports <span class="dropdown-section-badge">Live</span></h6></li>
            <?php endif; ?>
            <?php if ($canViewSales): ?>
            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/management/sales_records.php"><i class="bi bi-graph-up menu-icon me-2"></i> Sales Report</a></li>
            <?php endif; ?>
            <?php if ($canViewExpenseReport): ?>
            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/management/expenses.php"><i class="bi bi-cash-stack menu-icon me-2"></i> Expense Report</a></li>
            <?php endif; ?>
            <?php if ($canViewReports): ?>
            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/management/poultry_ruminant_report.php"><i class="bi bi-clipboard-data menu-icon me-2"></i> Poultry & Ruminant Report</a></li>
            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/management/reports.php"><i class="bi bi-bar-chart-line menu-icon me-2"></i> Analytics Dashboard</a></li>
            <?php endif; ?>
            <?php if ($canViewFarmIntelligence): ?>
            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/management/intelligence.php"><i class="bi bi-lightbulb menu-icon me-2"></i> Farm Intelligence</a></li>
            <?php endif; ?>
            <?php if ($canViewProfitability): ?>
            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/management/profitability.php"><i class="bi bi-cash-stack menu-icon me-2"></i> Profitability</a></li>
            <?php endif; ?>
            <?php if ($canViewProductionCycles): ?>
            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/management/production_cycles.php"><i class="bi bi-arrow-repeat menu-icon me-2"></i> Production Cycles</a></li>
            <?php endif; ?>
            <?php if ($canManageUsers || $canManagePermissions || isPlatformOwner()): ?>
            <li><hr class="dropdown-divider"></li>
            <li><h6 class="dropdown-header">Administration <span class="dropdown-section-badge">Secure</span></h6></li>
            <?php endif; ?>
            <?php if ($canManageUsers): ?>
            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/management/users.php"><i class="bi bi-people menu-icon me-2"></i> Users</a></li>
            <?php endif; ?>
            <?php if ($canManagePermissions): ?>
            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/permissions.php"><i class="bi bi-shield-lock menu-icon me-2"></i> Permissions</a></li>
            <?php endif; ?>
            <?php if (isPlatformOwner()): ?>
            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/management/farms.php"><i class="bi bi-buildings menu-icon me-2"></i> Platform Farms</a></li>
            <?php endif; ?>
          </ul>
        </li>
        <?php endif; ?>

      </ul>

      <ul class="navbar-nav ms-auto align-items-lg-center">
        <li class="nav-item me-lg-1">
          <button type="button" class="nav-link btn btn-link theme-quick-toggle" id="themeQuickToggle" title="Switch color theme" aria-label="Switch color theme"><i class="bi bi-moon-stars"></i></button>
        </li>
        <li class="nav-item dropdown">
          <button class="nav-link dropdown-toggle btn btn-link" type="button" data-nav-dropdown-toggle="dropdown" aria-expanded="false"><i class="bi bi-person-circle"></i> Account</button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><button type="button" class="dropdown-item" id="themeToggle"><i class="bi bi-moon-stars menu-icon me-2"></i><span>Dark mode</span></button></li>
            <!-- <li><a class="dropdown-item" href="#"><i class="bi bi-person"></i> Profile</a></li> -->
            <!-- <li><a class="dropdown-item" href="#"><i class="bi bi-gear"></i> Settings</a></li> -->
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/logout.php"><i class="bi bi-box-arrow-right menu-icon me-2"></i> Logout</a></li>
          </ul>
        </li>
      </ul>

    </div>
  </div>
</nav>
<script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/navbar.js'); ?>"></script>