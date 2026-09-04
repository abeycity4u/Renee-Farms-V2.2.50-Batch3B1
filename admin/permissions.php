<?php require_once(__DIR__ . '/../init.php'); ?>
<?php
// permissions.php - Owner UI to manage tenant-scoped access and action permissions
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/permission_catalog.php';

ensurePermissionsTable($pdo);

if (!isPlatformOwner() && !hasRole('farm_admin')) {
    header('Location: ' . BASE_URL . '/no_access.php');
    exit();
}

// Permissions are tenant-scoped. Farm Admin edits only the current farm.
// Platform Owner may select a tenant explicitly without changing workspace/session.
$permissionFarmId = requireCurrentFarmId();
$permissionFarmName = farmBrandName();
$permissionFarms = [];
if (isPlatformOwner()) {
    $permissionFarms = $pdo->query("SELECT id, name FROM farms WHERE slug <> 'owner' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $requestedFarmId = filter_var($_GET['farm_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    if ($requestedFarmId > 0) {
        $farmStmt = $pdo->prepare("SELECT id, name FROM farms WHERE id = ? AND slug <> 'owner' LIMIT 1");
        $farmStmt->execute([$requestedFarmId]);
        if ($targetFarm = $farmStmt->fetch(PDO::FETCH_ASSOC)) {
            $permissionFarmId = (int)$targetFarm['id'];
            $permissionFarmName = $targetFarm['name'];
        }
    } elseif ($permissionFarms) {
        $permissionFarmId = (int)$permissionFarms[0]['id'];
        $permissionFarmName = $permissionFarms[0]['name'];
    }
}

$permissionGroups = permission_catalog();
$modules = permission_catalog_codes();

$enabledRoleStmt=$pdo->prepare('SELECT module_code FROM farm_modules WHERE farm_id=? AND is_enabled=1');
$enabledRoleStmt->execute([$permissionFarmId]);
$enabledModules=$enabledRoleStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
$roles=[];
if(in_array('poultry',$enabledModules,true)) $roles[]='poultry_manager';
if(in_array('ruminant',$enabledModules,true)) $roles[]='ruminant_manager';
if(in_array('sales',$enabledModules,true)) $roles[]='sales_rep';

$roleLabels = [
    'poultry_manager' => 'Poultry Manager',
    'ruminant_manager' => 'Ruminant Manager',
    'sales_rep' => 'Sales Representative'
];

$rolePlaceholders = rtrim(str_repeat('?,', count($roles)), ',');

// Load global defaults first (farm_id=0), then tenant overrides.
$permissions = [];
if ($roles) {
    $stmt = $pdo->prepare("SELECT farm_id,role,module,allowed FROM permissions WHERE farm_id IN (0, ?) AND role IN ($rolePlaceholders) ORDER BY farm_id ASC");
    $stmt->execute(array_merge([$permissionFarmId], $roles));
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $permissions[$row['role']][$row['module']] = $row['allowed'];
    }
}

$errorDetail = $_SESSION['permission_error_detail'] ?? null;
unset($_SESSION['permission_error_detail']);
?>
<!doctype html>
<html>
<head>
  <?php include __DIR__ . '/../navbar_head.php'; ?>
  <title>Access & Action Permissions</title>
  <style>
    .permissions-shell { max-width: 1320px; margin: 0 auto; }
    .permissions-card {
      border: 1px solid #e9ecef;
      border-radius: 14px;
      box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
      overflow: hidden;
    }
    .permissions-card .card-header {
      background: linear-gradient(120deg, #198754 0%, #157347 100%);
      color: #fff;
      border: 0;
      padding: 1rem 1.25rem;
    }
    .permissions-intro { color: #5c6770; margin-bottom: 0; font-size: 0.95rem; }
    .permission-group-title {
      background: #f8faf9;
      color: #1f5136;
      font-weight: 700;
      font-size: 0.88rem;
      letter-spacing: .02em;
      text-transform: uppercase;
    }
    .permission-module { min-width: 370px; }
    .permission-module strong { color: #1f2937; font-size: 0.95rem; }
    .permission-module small { color: #6b7280; line-height: 1.35; display: block; margin-top: .15rem; }
    .permission-action { font-size: .72rem; vertical-align: middle; }
    .permission-action-destructive { border-color: #dc3545 !important; color: #dc3545 !important; }
    .permission-check { transform: scale(1.2); cursor: pointer; }
    .sticky-actions {
      position: sticky;
      bottom: 0;
      background: #fff;
      border-top: 1px solid #e9ecef;
      padding: 1rem 1.25rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: .75rem;
    }
    .permission-tip { color: var(--app-text-muted, #5c6770); font-weight: 600; }
    .security-note { border-left: 4px solid #ffc107; }
    html[data-theme="dark"] .permissions-shell .permissions-intro,
    html[data-bs-theme="dark"] .permissions-shell .permissions-intro,
    html[data-theme="dark"] .permissions-shell .permission-module small,
    html[data-bs-theme="dark"] .permissions-shell .permission-module small,
    html[data-theme="dark"] .permission-tip,
    html[data-theme="dark"] .permissions-shell .permission-tip,
    html[data-bs-theme="dark"] .permissions-shell .permission-tip { color: #dbe5f3 !important; opacity: 1 !important; }
    html[data-theme="dark"] .permissions-shell .permission-group-title,
    html[data-bs-theme="dark"] .permissions-shell .permission-group-title { background: #172235 !important; color: #e8f2ff !important; }
    html[data-theme="dark"] .permissions-shell .permission-module strong,
    html[data-bs-theme="dark"] .permissions-shell .permission-module strong { color: #f8fafc !important; }
    html[data-theme="dark"] .permissions-shell .sticky-actions,
    html[data-bs-theme="dark"] .permissions-shell .sticky-actions { background: #111c2f !important; border-color: #334155 !important; }
    @media (max-width: 768px) {
      .permission-module { min-width: 290px; }
      .sticky-actions { flex-direction: column; align-items: stretch; }
      .sticky-actions button { width: 100%; }
    }
  </style>
</head>
<body>
  <?php include __DIR__ . '/../navbar.php'; ?>
  <div class="container py-4 permissions-shell">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
      <div>
        <h3 class="mb-1">Access & Action Permissions</h3>
        <p class="permissions-intro">Control what each operational role can view and which sensitive actions it may perform.</p>
      </div>
      <span class="badge text-bg-light border"><?php echo isPlatformOwner() ? 'Platform Owner • ' . htmlspecialchars($permissionFarmName) : 'Farm Admin Scoped Access'; ?></span>
    </div>

    <div class="alert alert-warning security-note py-2 mb-3">
      <strong>Security rule:</strong> viewing operational records does not automatically mean a user should be able to edit or delete them. Grant destructive actions only where they are operationally required.
    </div>

    <?php if (isset($_GET['updated'])): ?>
      <?php renderNotification('success', 'Permissions updated successfully!', 'Permissions updated successfully!'); ?>
    <?php elseif (isset($_GET['error'])): ?>
      <?php renderNotification('error', 'Unable to save permissions. Please try again or check the error log.', 'Unable to save permissions.'); ?>
      <?php if ($errorDetail): ?>
        <?php renderNotification('warning', $errorDetail, 'Additional details'); ?>
      <?php endif; ?>
    <?php endif; ?>

    <?php if (isPlatformOwner()): ?>
    <form method="get" class="card card-body mb-3 border-0 shadow-sm">
      <label class="form-label fw-semibold" for="permissionFarmSelect">Manage permissions for farm</label>
      <div class="d-flex gap-2 flex-wrap">
        <select class="form-select" style="max-width:420px" id="permissionFarmSelect" name="farm_id" onchange="this.form.submit()">
          <?php foreach ($permissionFarms as $farmOption): ?>
            <option value="<?= (int)$farmOption['id'] ?>" <?= (int)$farmOption['id'] === $permissionFarmId ? 'selected' : '' ?>><?= htmlspecialchars($farmOption['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="badge text-bg-light border align-self-center">Tenant-scoped settings</span>
      </div>
    </form>
    <?php endif; ?>

    <form method="post" action="permissions_save.php">
      <?= csrf_field() ?>
      <input type="hidden" name="farm_id" value="<?= (int)$permissionFarmId ?>">
      <div class="card permissions-card">
        <div class="card-header">
          <h5 class="mb-1">Role Permission Matrix</h5>
          <small>Checked means granted. Unchecked means blocked. Greyed-out combinations do not apply to that role.</small>
        </div>

        <div class="table-responsive">
          <table class="table table-hover table-bordered align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="permission-module">Permission</th>
                <?php foreach ($roles as $role): ?>
                  <th class="text-center"><?= htmlspecialchars($roleLabels[$role] ?? $role) ?></th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($permissionGroups as $groupName => $groupPermissions): ?>
                <tr class="permission-group-title">
                  <td colspan="<?= count($roles) + 1 ?>"><?= htmlspecialchars($groupName) ?></td>
                </tr>
                <?php foreach ($groupPermissions as $module => $meta):
                  $action = (string)($meta['action'] ?? 'Access');
                  $isDestructive = in_array(strtolower($action), ['delete','disable','cull','close'], true);
                ?>
                  <tr>
                    <td class="permission-module">
                      <div class="d-flex align-items-center flex-wrap gap-2">
                        <strong><?= htmlspecialchars((string)($meta['label'] ?? 'Permission')) ?></strong>
                        <span class="badge rounded-pill text-bg-light border permission-action <?= $isDestructive ? 'permission-action-destructive' : '' ?>"><?= htmlspecialchars($action) ?></span>
                      </div>
                      <small><?= htmlspecialchars((string)($meta['description'] ?? '')) ?></small>
                    </td>
                    <?php foreach ($roles as $role):
                      $applicable = permission_catalog_applicable($role,$module);
                      $checked = ($applicable && !empty($permissions[$role][$module]) && (int)$permissions[$role][$module] === 1) ? 'checked' : '';
                      $disabled = $applicable ? '' : 'disabled';
                    ?>
                      <td class="text-center">
                        <input
                          class="form-check-input permission-check"
                          type="checkbox"
                          name="perm[<?= htmlspecialchars($role) ?>][<?= htmlspecialchars($module) ?>]"
                          value="1"
                          <?= $checked ?> <?= $disabled ?>
                          aria-label="<?= htmlspecialchars($role) ?> permission for <?= htmlspecialchars((string)($meta['label'] ?? 'Permission')) ?> <?= htmlspecialchars($action) ?>"
                        />
                      </td>
                    <?php endforeach; ?>
                  </tr>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="sticky-actions">
          <small class="permission-tip">Tip: Keep Edit/Delete unchecked unless that role genuinely needs to alter historical farm records.</small>
          <button class="btn btn-success" type="submit">
            <i class="bi bi-check2-circle me-1"></i> Save Permissions
          </button>
        </div>
      </div>
    </form>
  </div>
</body>
</html>