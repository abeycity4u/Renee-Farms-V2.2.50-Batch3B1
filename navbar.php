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

$subscriptionNotice = null;
if (!isPlatformOwner() && hasRole('farm_admin')) {
    $farm = currentFarm();
    if (!empty($farm['subscription_ends_at'])) {
        $daysLeft = (int) floor((strtotime($farm['subscription_ends_at']) - strtotime(date('Y-m-d'))) / 86400);
        if ($daysLeft >= 0 && $daysLeft <= 14) {
            $subscriptionNotice = 'Subscription ends in ' . $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') . '. Please contact the platform owner to renew.';
        }
    }
}

$navHas = static function (string $permission): bool {
    return isPlatformOwner() || hasRole('farm_admin') || hasPermission(getUserType(), $permission);
};

$canViewInventory = $navHas('inventory');

$canViewLayerDaily = $navHas('poultry_daily_layer');
$canViewBroilerDaily = $navHas('poultry_daily_broiler');
$canViewPoultryFeeds = $navHas('poultry_feeds');
$canViewPoultryHealth = $navHas('poultry_health');
$canViewLayerExpenses = $navHas('poultry_layer_expenses');
$canViewBroilerExpenses = $navHas('poultry_broiler_expenses');
$showPoultryMenu = $canViewLayerDaily || $canViewBroilerDaily || $canViewPoultryFeeds || $canViewPoultryHealth || $canViewLayerExpenses || $canViewBroilerExpenses;

$canViewRuminantDaily = $navHas('ruminant_daily');
$canViewRuminantAnimals = $navHas('ruminant_animals');
$canViewRuminantFeeds = $navHas('ruminant_feeds');
$canViewRuminantExpenses = $navHas('ruminant_expenses');
$showRuminantMenu = $canViewRuminantDaily || $canViewRuminantAnimals || $canViewRuminantFeeds || $canViewRuminantExpenses;

$canViewSales = $navHas('sales');
$canViewExpenseReport = $navHas('expenses');
$canViewReports = $navHas('reports');
$canViewFarmIntelligence = $navHas('farm_intelligence');
$canViewProfitability = $navHas('profitability');
$canViewProductionCycles = $navHas('production_cycles');
$canManageUsers = $navHas('users');
$showManagementMenu = $canViewSales || $canViewExpenseReport || $canViewReports || $canViewFarmIntelligence || $canViewProfitability || $canViewProductionCycles || $canManageUsers || isPlatformOwner();
?>

<div id="appNotifications" class="app-notifications" aria-live="polite" aria-atomic="false">
  <?php renderSessionNotifications(); ?>
</div>

<?php if ($subscriptionNotice): ?><div class="alert alert-warning rounded-0 mb-0 text-center no-print"><?php echo htmlspecialchars($subscriptionNotice); ?></div><?php endif; ?>
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
            <?php if ($canManageUsers || isPlatformOwner()): ?>
            <li><hr class="dropdown-divider"></li>
            <li><h6 class="dropdown-header">Administration <span class="dropdown-section-badge">Secure</span></h6></li>
            <?php endif; ?>
            <?php if ($canManageUsers): ?>
            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/management/users.php"><i class="bi bi-people menu-icon me-2"></i> Users</a></li>
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
<script>
document.addEventListener('DOMContentLoaded', function () {
  const navDebugEnabled = (() => {
    const urlDebug = new URLSearchParams(window.location.search).get('nav_debug');
    if (urlDebug === '1') localStorage.setItem('nav_debug', '1');
    if (urlDebug === '0') localStorage.removeItem('nav_debug');
    return localStorage.getItem('nav_debug') === '1';
  })();

  const navDebug = (...args) => {
    if (!navDebugEnabled) return;
    console.log('[NavbarDebug]', ...args);
  };

  const toggles = document.querySelectorAll('.navbar [data-nav-dropdown-toggle="dropdown"]');
  navDebug('DOMContentLoaded', {
    page: window.location.pathname,
    toggleCount: toggles.length,
    bootstrapPresent: !!window.bootstrap,
    dropdownPluginPresent: !!(window.bootstrap && window.bootstrap.Dropdown),
    popperPresent: !!window.Popper,
    popperCreatePopperPresent: !!(window.Popper && typeof window.Popper.createPopper === 'function')
  });

  if (!toggles.length) return;

  // Always use a local navbar dropdown controller so Popper/Bootstrap mismatch cannot break navigation.
  navDebug('Using local navbar dropdown controller');

  const closeAll = () => {
    navDebug('Fallback closeAll');
    toggles.forEach((toggle) => {
      toggle.setAttribute('aria-expanded', 'false');
      const menu = toggle.parentElement?.querySelector('.dropdown-menu');
      if (menu) menu.classList.remove('show');
    });
  };

  toggles.forEach((toggle) => {
    toggle.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation();
      const menu = this.parentElement?.querySelector('.dropdown-menu');
      if (!menu) return;

      const isOpen = menu.classList.contains('show');
      navDebug('Fallback toggle click', {
        id: this.id || '(no-id)',
        wasOpen: isOpen
      });
      closeAll();
      if (!isOpen) {
        menu.classList.add('show');
        this.setAttribute('aria-expanded', 'true');
        navDebug('Fallback menu opened', {
          id: this.id || '(no-id)'
        });
      }
    });
  });

  document.addEventListener('click', (event) => {
    navDebug('Document click closes menus', {
      target: event.target?.tagName || '(unknown)'
    });
    closeAll();
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeAll();
    }
  });

  toggles.forEach((toggle) => {
    const menu = toggle.parentElement?.querySelector('.dropdown-menu');
    if (!menu) return;
    const items = () => Array.from(menu.querySelectorAll('.dropdown-item'));

    toggle.addEventListener('keydown', (event) => {
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        closeAll();
        menu.classList.add('show');
        toggle.setAttribute('aria-expanded', 'true');
        items()[0]?.focus();
      }
    });

    menu.addEventListener('keydown', (event) => {
      const list = items();
      if (!list.length) return;
      const currentIndex = list.indexOf(document.activeElement);

      if (event.key === 'ArrowDown') {
        event.preventDefault();
        const nextIndex = currentIndex < 0 ? 0 : (currentIndex + 1) % list.length;
        list[nextIndex].focus();
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        const prevIndex = currentIndex <= 0 ? list.length - 1 : currentIndex - 1;
        list[prevIndex].focus();
      } else if (event.key === 'Home') {
        event.preventDefault();
        list[0]?.focus();
      } else if (event.key === 'End') {
        event.preventDefault();
        list[list.length - 1]?.focus();
      } else if (event.key === 'Escape') {
        event.preventDefault();
        closeAll();
        toggle.focus();
      }
    });
  });

  const currentPath = window.location.pathname.replace(/\/+$/, '');
  const allLinks = document.querySelectorAll('.navbar .nav-link[href], .navbar .dropdown-item[href]');
  allLinks.forEach((link) => {
    const href = link.getAttribute('href');
    if (!href || href.startsWith('#')) return;
    const linkPath = new URL(href, window.location.origin).pathname.replace(/\/+$/, '');
    if (linkPath && linkPath === currentPath) {
      link.classList.add('active');
      const parentDropdown = link.closest('.dropdown');
      if (parentDropdown) {
        const toggle = parentDropdown.querySelector('.nav-link.dropdown-toggle');
        if (toggle) toggle.classList.add('active');
      }
    }
  });

  const navbar = document.getElementById('appNavbar');
  if (navbar) {
    const COMPACT_ENTER_Y = 52;
    const COMPACT_EXIT_Y = 20;

    const setCompactState = () => {
      const canCompact = window.innerWidth >= 992;
      if (!canCompact) {
        navbar.classList.remove('is-compact');
        return;
      }

      const currentlyCompact = navbar.classList.contains('is-compact');
      if (!currentlyCompact && window.scrollY > COMPACT_ENTER_Y) {
        navbar.classList.add('is-compact');
      } else if (currentlyCompact && window.scrollY < COMPACT_EXIT_Y) {
        navbar.classList.remove('is-compact');
      }
    };

    setCompactState();
    window.addEventListener('scroll', setCompactState, { passive: true });
    window.addEventListener('resize', setCompactState);
  }
});
</script>

<script>document.addEventListener('DOMContentLoaded',function(){const menu=document.getElementById('themeToggle');const quick=document.getElementById('themeQuickToggle');const apply=(n)=>{document.documentElement.setAttribute('data-theme',n);document.documentElement.setAttribute('data-bs-theme',n);try{localStorage.setItem('farm-theme',n);}catch(e){}paint();};const paint=()=>{const d=document.documentElement.getAttribute('data-theme')==='dark';if(menu){const i=menu.querySelector('i'),t=menu.querySelector('span');if(i)i.className='bi '+(d?'bi-sun':'bi-moon-stars')+' menu-icon me-2';if(t)t.textContent=d?'Light mode':'Dark mode';}if(quick){const i=quick.querySelector('i');if(i)i.className='bi '+(d?'bi-sun':'bi-moon-stars');quick.setAttribute('aria-label',d?'Switch to light mode':'Switch to dark mode');quick.setAttribute('title',d?'Switch to light mode':'Dark mode');}};const toggle=()=>apply(document.documentElement.getAttribute('data-theme')==='dark'?'light':'dark');paint();if(menu)menu.addEventListener('click',toggle);if(quick)quick.addEventListener('click',toggle);});</script>
