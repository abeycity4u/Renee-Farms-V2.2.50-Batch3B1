<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/api_helpers.php');
requireLogin();
require_once(__DIR__ . '/../includes/functions.php');
require_once(__DIR__ . '/../includes/permission_catalog.php');
require_once(__DIR__ . '/../lib/attribution.php');
require_once(__DIR__ . '/../lib/ruminant_expense_allocation.php');
require_http_method('POST');
require_csrf_token();
require_rate_limit('update_expense', 60, 60);

$requiredFields = ['expense_id', 'expense_date', 'farm_type', 'category', 'amount', 'unit'];

foreach ($requiredFields as $field) {
    if (empty($_POST[$field])) {
        send_json(['success' => false, 'error' => 'Missing required field: ' . $field], 400);
    }
}

$expenseId = $_POST['expense_id'];
$expenseDate = $_POST['expense_date'];
$farmType = $_POST['farm_type'];
if (!in_array($farmType, array_unique(array_merge(allowedFarmTypes(), ['general'])), true)) {
    send_json(['success' => false, 'error' => 'That farm type is not enabled for this farm.'], 422);
}
$poultryCategory = $_POST['poultry_category'] ?? null;
$category = $_POST['category'];
$amount = $_POST['amount'];
$unit = $_POST['unit'];
$description = $_POST['description'] ?? '';

try {
    $farmId=requireCurrentFarmId();
    $existingStmt=$pdo->prepare("SELECT farm_type,production_type,poultry_category,cycle_id,category FROM farm_expenses WHERE id=? AND farm_id=? LIMIT 1");
    $existingStmt->execute([$expenseId,$farmId]);
    $existing=$existingStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    if (!$existing) {
        send_json(['success' => false, 'error' => 'Expense record not found.'], 404);
    }
    $existingPermission = permission_catalog_expense_action_code($existing, 'edit');
    if (!isPlatformOwner() && !hasRole('farm_admin') && (!$existingPermission || !hasPermission(getUserType(), $existingPermission))) {
        send_json(['success' => false, 'error' => 'You do not have permission to edit this expense record.'], 403);
    }
    $allowedManualCategories = ['salary','logistic','fuel','misc'];
    $isLegacyFeedEdit = (($existing['category'] ?? '') === 'feeds' && $category === 'feeds');
    $isLegacyMedicationEdit = (($existing['category'] ?? '') === 'medication' && $category === 'medication');
    if (!in_array($category, $allowedManualCategories, true) && !$isLegacyFeedEdit && !$isLegacyMedicationEdit) {
        send_json(['success' => false, 'error' => 'Stock purchases such as feed and medication are recorded through Inventory. Choose a non-stock expense category.'], 422);
    }
    $requestedProduction=$_POST['production_type'] ?? ($existing['production_type'] ?? null);
    if ($farmType==='poultry' && in_array((string)$poultryCategory,['layer','broiler'],true)) $requestedProduction=$poultryCategory;
    $productionType=attribution_normalize_production_type($farmType,$requestedProduction);
    $targetPermission = permission_catalog_expense_action_code([
        'farm_type' => $farmType,
        'production_type' => $productionType,
        'poultry_category' => $poultryCategory,
    ], 'edit');
    if (!isPlatformOwner() && !hasRole('farm_admin') && (!$targetPermission || !hasPermission(getUserType(), $targetPermission))) {
        send_json(['success' => false, 'error' => 'You do not have permission to move or edit an expense in the requested area.'], 403);
    }
    $cycleId=(int)($_POST['cycle_id'] ?? ($existing['cycle_id'] ?? 0));
    if ($cycleId>0) attribution_validate_cycle($pdo,$farmId,$cycleId,$farmType,$productionType);
    $scope=attribution_scope($cycleId>0?$cycleId:null,$farmType,$productionType);
    $animalAllocation = null;
    if ($farmType === 'ruminant') {
        $animalAllocation = ruminant_expense_build_animal_allocations($pdo, $farmId, $productionType, round(((float)$amount) * ((float)$unit), 2), $_POST);
    }

    $pdo->beginTransaction();
    $stmt = $pdo->prepare("UPDATE farm_expenses
                           SET expense_date=?, farm_type=?, production_type=?, attribution_scope=?, cycle_id=?, poultry_category=?, category=?, amount=?, unit=?, description=?
                           WHERE id=? AND farm_id=?");
    $stmt->execute([$expenseDate,$farmType,$productionType,$scope,$cycleId>0?$cycleId:null,$poultryCategory,$category,$amount,$unit,$description,$expenseId,$farmId]);
    if ($farmType === 'ruminant') {
        ruminant_expense_save_animal_allocations($pdo, $farmId, (int)$expenseId, $animalAllocation, (int)($_SESSION['user_id'] ?? 0));
    }
    $pdo->commit();

    $_SESSION['success'] = 'Expense updated successfully.'; send_json(['success' => true, 'message' => 'Expense updated successfully']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    log_app_error('update_expense_failed', ['error' => safe_api_exception_message($e, 'The expense could not be updated.'), 'expense_id' => $expenseId ?? null]);
    send_json(['success' => false, 'error' => safe_api_exception_message($e, 'The expense could not be updated.')], 500);
}
?>
