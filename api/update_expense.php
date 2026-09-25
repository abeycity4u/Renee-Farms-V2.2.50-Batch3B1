<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/api_helpers.php');
requireLogin();
require_once(__DIR__ . '/../includes/functions.php');
require_once(__DIR__ . '/../includes/permission_catalog.php');
require_once(__DIR__ . '/../lib/attribution.php');
require_once(__DIR__ . '/../lib/financial_allocation_integrity.php');
require_once(__DIR__ . '../lib/slaughter_expense_integrity.php');
require_once(__DIR__ . '/../lib/ruminant_expense_allocation.php');
require_once(__DIR__ . '/../lib/expense_revision_service.php');
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
$revisionReason =
    $_POST['revision_reason']
    ?? null;

$permissionScope = trim((string)($_POST['permission_scope'] ?? 'operational'));
if (!in_array($permissionScope, ['operational', 'expense_report'], true)) {
    send_json(['success' => false, 'error' => 'Invalid expense permission scope.'], 400);
}
$expensePrivileged = isPlatformOwner() || hasRole('farm_admin');

try {
    $farmId=requireCurrentFarmId();
    $existingStmt=$pdo->prepare("SELECT farm_type,production_type,poultry_category,cycle_id,category FROM farm_expenses WHERE id=? AND farm_id=? LIMIT 1");
    $existingStmt->execute([$expenseId,$farmId]);
    $existing=$existingStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    if (!$existing) {
        send_json(['success' => false, 'error' => 'Expense record not found.'], 404);
    }
    if (!$expensePrivileged) {
        if ($permissionScope === 'expense_report') {
            if (!hasPermission(getUserType(), 'expenses')
                || !hasPermission(getUserType(), 'expenses_edit')
                || !permission_catalog_expense_report_row_accessible($existing)) {
                send_json(['success' => false, 'error' => 'You do not have permission to edit this Expense Report record.'], 403);
            }
        } elseif (
            !permission_catalog_expense_operational_can(
                $existing,
                'edit'
            )
        ) {
            send_json(['success' => false, 'error' => 'You do not have permission to edit this expense record.'], 403);
        }
    }
    /*
     * Canonical category authority owns both normal edits and historical
     * Inventory-owned category preservation.
     */
    $category =
        expense_category_normalize_for_update(
            $category,
            $existing['category'] ?? ''
        );

    $requestedProduction =
        $_POST['production_type']
        ?? ($existing['production_type'] ?? null);

    if ($farmType === 'poultry') {
        $requestedProduction =
            strtolower(
                trim(
                    (string)$requestedProduction
                )
            );

        $legacyPoultryCategory =
            strtolower(
                trim(
                    (string)$poultryCategory
                )
            );

        /*
         * production_type is the canonical Poultry ownership field.
         *
         * poultry_category is retained only as a compatibility fallback for
         * historical Layer/Broiler callers that do not provide a usable
         * production_type. It must never override an explicit Shared target.
         */
        if (
            !in_array(
                $requestedProduction,
                [
                    'layer',
                    'broiler',
                    'shared',
                ],
                true
            )
            &&
            in_array(
                $legacyPoultryCategory,
                [
                    'layer',
                    'broiler',
                ],
                true
            )
        ) {
            $requestedProduction =
                $legacyPoultryCategory;
        }
    }

    $productionType =
        attribution_normalize_production_type(
            $farmType,
            $requestedProduction
        );

    if ($farmType === 'poultry') {
        /*
         * Keep the legacy poultry_category column synchronized with canonical
         * production ownership. Shared has no Layer/Broiler category.
         */
        $poultryCategory =
            in_array(
                $productionType,
                [
                    'layer',
                    'broiler',
                ],
                true
            )
                ? $productionType
                : null;
    }
    $targetExpenseScope = [
        'farm_type' =>
            $farmType,

        'production_type' =>
            $productionType,

        'poultry_category' =>
            $poultryCategory,
    ];

    if (!$expensePrivileged) {
        if ($permissionScope === 'expense_report') {
            if (!permission_catalog_expense_report_row_accessible($targetExpenseScope)) {
                send_json(['success' => false, 'error' => 'You do not have permission to move this Expense Report record into the requested area.'], 403);
            }
        } elseif (
            !permission_catalog_expense_operational_can(
                $targetExpenseScope,
                'edit'
            )
        ) {
            send_json(['success' => false, 'error' => 'You do not have permission to move or edit an expense in the requested area.'], 403);
        }
    }
    $cycleId =
        (int)(
            $_POST['cycle_id']
            ?? ($existing['cycle_id'] ?? 0)
        );

    /*
     * Poultry Shared is a Poultry-wide parent. It cannot carry a specific
     * Layer or Broiler cycle, even if a stale client submits one.
     */
    if (
        $farmType === 'poultry'
        &&
        $productionType === 'shared'
    ) {
        $cycleId =
            0;
    }

    if ($cycleId > 0) {
        attribution_validate_cycle(
            $pdo,
            $farmId,
            $cycleId,
            $farmType,
            $productionType
        );
    }

    $scope =
        attribution_scope(
            $cycleId > 0
                ? $cycleId
                : null,
            $farmType,
            $productionType
        );
    $animalAllocation = null;
    if ($farmType === 'ruminant') {
        $animalAllocation = ruminant_expense_build_animal_allocations($pdo, $farmId, $productionType, round(((float)$amount) * ((float)$unit), 2), $_POST);
    }

    $pdo->beginTransaction();

    financial_allocation_integrity_assert_parent_update(
        $pdo,
        $farmId,
        (int)$expenseId,
        [
            'id' =>
                (int)$expenseId,

            'farm_id' =>
                $farmId,

            'expense_date' =>
                $expenseDate,

            'farm_type' =>
                $farmType,

            'production_type' =>
                $productionType,

            'attribution_scope' =>
                $scope,

            'cycle_id' =>
                $cycleId > 0
                    ? $cycleId
                    : null,

            'poultry_category' =>
                $poultryCategory,

            'category' =>
                $category,

            'amount' =>
                $amount,

            'unit' =>
                $unit,

            'description' =>
                $description,
        ],
        $animalAllocation
            ?? []
    );

    slaughter_expense_integrity_assert_mutable(
        $pdo,
        $farmId,
        (int)$expenseId
    );

    expense_revision_service_prepare_existing_mutation(
        $pdo,
        $farmId,
        (int)$expenseId,
        (int)($_SESSION['user_id'] ?? 0)
    );

    $stmt = $pdo->prepare("UPDATE farm_expenses
                           SET expense_date=?, farm_type=?, production_type=?, attribution_scope=?, cycle_id=?, poultry_category=?, category=?, amount=?, unit=?, description=?
                           WHERE id=? AND farm_id=?");
    $stmt->execute([$expenseDate,$farmType,$productionType,$scope,$cycleId>0?$cycleId:null,$poultryCategory,$category,$amount,$unit,$description,$expenseId,$farmId]);
    if ($farmType === 'ruminant') {
        ruminant_expense_save_animal_allocations(
            $pdo,
            $farmId,
            (int)$expenseId,
            $animalAllocation,
            (int)($_SESSION['user_id'] ?? 0)
        );

    } elseif (($existing['farm_type'] ?? '') === 'ruminant') {
        /*
         * An expense moved away from Ruminant must not retain stale
         * animal-allocation rows.
         */
        ruminant_expense_save_animal_allocations(
            $pdo,
            $farmId,
            (int)$expenseId,
            [
                'mode' => 'herd',
                'rows' => [],
            ],
            (int)($_SESSION['user_id'] ?? 0)
        );
    }

    expense_revision_service_record_updated(
        $pdo,
        $farmId,
        (int)$expenseId,
        (int)($_SESSION['user_id'] ?? 0),
        $revisionReason
    );

    $pdo->commit();

    $_SESSION['success'] = 'Expense updated successfully.'; send_json(['success' => true, 'message' => 'Expense updated successfully']);
} catch (InvalidArgumentException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    send_json([
        'success' => false,
        'error' => $e->getMessage(),
    ], 422);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    log_app_error('update_expense_failed', ['error' => safe_api_exception_message($e, 'The expense could not be updated.'), 'expense_id' => $expenseId ?? null]);
    send_json(['success' => false, 'error' => safe_api_exception_message($e, 'The expense could not be updated.')], 500);
}
?>
