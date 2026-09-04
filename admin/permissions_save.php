<?php require_once(__DIR__ . '/../init.php'); ?>
<?php
// permissions_save.php - save tenant-scoped access and action permissions
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/permission_catalog.php';

ensurePermissionsTable($pdo);

if (!isPlatformOwner() && !hasRole('farm_admin')) {
    header('Location: ' . BASE_URL . '/no_access.php'); exit();
}

require_valid_csrf_post();

$permissionFarmId = requireCurrentFarmId();
if (isPlatformOwner()) {
    $requestedFarmId = filter_var($_POST['farm_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    $farmStmt = $pdo->prepare("SELECT id FROM farms WHERE id = ? AND slug <> 'owner' LIMIT 1");
    $farmStmt->execute([$requestedFarmId]);
    $permissionFarmId = (int)($farmStmt->fetchColumn() ?: 0);
    if ($permissionFarmId < 1) {
        $_SESSION['permission_error_detail'] = 'Choose a valid tenant farm.';
        header('Location: permissions.php?error=1&farm_id=' . $permissionFarmId);
        exit();
    }
}

$enabledRoleStmt=$pdo->prepare('SELECT module_code FROM farm_modules WHERE farm_id=? AND is_enabled=1');
$enabledRoleStmt->execute([$permissionFarmId]);
$enabledModules=$enabledRoleStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
$roles=[];
if(in_array('poultry',$enabledModules,true)) $roles[]='poultry_manager';
if(in_array('ruminant',$enabledModules,true)) $roles[]='ruminant_manager';
if(in_array('sales',$enabledModules,true)) $roles[]='sales_rep';

$modules = permission_catalog_codes();
$incoming = isset($_POST['perm']) && is_array($_POST['perm']) ? $_POST['perm'] : [];

try {
    $pdo->beginTransaction();

    foreach ($roles as $role) {
        foreach ($modules as $module) {
            $allowed = (permission_catalog_applicable($role,$module)
                && isset($incoming[$role][$module])
                && (string)$incoming[$role][$module] === '1') ? 1 : 0;
            $stmt = $pdo->prepare("INSERT INTO permissions (farm_id,role,module,allowed) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE allowed = VALUES(allowed)");
            $stmt->execute([$permissionFarmId, $role, $module, $allowed]);
        }
    }

    $pdo->commit();
    $_SESSION['success'] = 'Permissions updated successfully!';
    header('Location: permissions.php?farm_id=' . $permissionFarmId);
    exit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $message = 'Permission save failed: ' . $e->getMessage();
    error_log($message);

    $_SESSION['permission_error_detail'] = 'The permission update could not be completed. No raw database details are shown for security.';

    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }
    $logEntry = sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $message);
    @file_put_contents($logDir . '/permissions_error.log', $logEntry, FILE_APPEND);
    header('Location: permissions.php?error=1&farm_id=' . $permissionFarmId);
    exit();
}
