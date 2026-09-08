<?php
/**
 * Defense-in-depth tenant and password-policy guard for Team Users mutations.
 *
 * management/users.php already scopes user row updates/deletes by farm_id, but
 * role assignment is stored in user_roles without a farm_id column. Reject an
 * edit target that does not belong to the selected/current tenant before the
 * legacy page can touch role rows. Password validation is also enforced here so
 * Team Users cannot bypass the centralized minimum by posting directly.
 */

require_once __DIR__ . '/password_security.php';

if (!function_exists('user_management_password_guard')) {
function user_management_password_guard(): void
{
    $path = '/' . ltrim(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if (!str_ends_with($path, '/management/users.php')) return;
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;

    $isAdd = isset($_POST['add_user']);
    $isEdit = isset($_POST['edit_user']);
    if (!$isAdd && !$isEdit) return;

    $password = (string)($_POST['password'] ?? '');
    if ($isEdit && $password === '') return;

    $error = password_security_validate($password);
    if ($error === null) return;

    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['error'] = $error;
    }
    $target = defined('BASE_URL') ? BASE_URL . '/management/users.php' : '/management/users.php';
    if (isset($_POST['target_farm_id'])) {
        $farmId = filter_var($_POST['target_farm_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
        if ($farmId > 0) $target .= '?farm_id=' . $farmId;
    }
    header('Location: ' . $target, true, 303);
    exit();
}
}

if (!function_exists('user_management_tenant_guard')) {
function user_management_tenant_guard(PDO $pdo): void
{
    $path = '/' . ltrim(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if (!str_ends_with($path, '/management/users.php')) return;
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;
    if (!isset($_POST['edit_user'])) return;

    $targetUserId = filter_var(
        $_POST['user_id'] ?? 0,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    ) ?: 0;

    if ($targetUserId < 1) {
        http_response_code(404);
        exit('Team user not found.');
    }

    $farmId = requireCurrentFarmId();
    if (isPlatformOwner()) {
        $requestedFarmId = filter_var(
            $_POST['target_farm_id'] ?? 0,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        ) ?: 0;
        if ($requestedFarmId > 0) {
            $farmStmt = $pdo->prepare("SELECT id FROM farms WHERE id = ? AND slug <> 'owner' LIMIT 1");
            $farmStmt->execute([$requestedFarmId]);
            $resolvedFarmId = (int)($farmStmt->fetchColumn() ?: 0);
            if ($resolvedFarmId < 1) {
                http_response_code(404);
                exit('Tenant farm not found.');
            }
            $farmId = $resolvedFarmId;
        }
    }

    $targetStmt = $pdo->prepare('SELECT id FROM users WHERE id = ? AND farm_id = ? LIMIT 1');
    $targetStmt->execute([$targetUserId, $farmId]);
    if (!$targetStmt->fetchColumn()) {
        http_response_code(404);
        exit('Team user not found.');
    }
}
}

user_management_password_guard();

if (isset($pdo) && $pdo instanceof PDO) {
    user_management_tenant_guard($pdo);
}
