<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/api_helpers.php');
require_once(__DIR__ . '/../includes/functions.php');
require_once(__DIR__ . '/../lib/daily_feed_sync.php');
require_once(__DIR__ . '/../lib/daily_population_sync.php');
require_once(__DIR__ . '/../lib/daily_record_revision_authority.php');
require_once(__DIR__ . '/../lib/daily_record_revision_cutover_gate.php');
require_once(__DIR__ . '/../lib/sales_allocation.php');
requireLogin();
require_http_method('POST');
require_csrf_token();
require_rate_limit('delete_record', 30, 60);

$type = $_POST['type'] ?? null;
$id = $_POST['id'] ?? null;
if (!$type || !$id) {
    send_json(['success' => false, 'error' => 'type and id are required'], 400);
}

$viewPermission = match ($type) {
    'layer' => 'poultry_daily_layer',
    'broiler' => 'poultry_daily_broiler',
    default => null,
};
$deletePermission = match ($type) {
    'layer' => 'poultry_daily_layer_delete',
    'broiler' => 'poultry_daily_broiler_delete',
    default => null,
};
if ($viewPermission === null || $deletePermission === null) {
    send_json(['success' => false, 'error' => 'Unsupported record type'], 400);
}

if (!isPlatformOwner() && !hasRole('farm_admin')) {
    if (
        !checkAccess('poultry')
        || !hasPermission(getUserType(), $viewPermission)
        || !hasPermission(getUserType(), $deletePermission)
    ) {
        send_json(['success' => false, 'error' => 'View and Delete permissions are required for this daily record.'], 403);
    }
}


/*
 * B12 FUTURE coordinated cutover only. This fuse is compile-time hard-off.
 * The existing privileged/delegated permission, CSRF, POST and rate-limit
 * checks above remain mandatory. Never synthesize expected_revision from a
 * fresh DELETE-time row lookup: it must be browser-loaded identity.
 */
if (daily_record_revision_cutover_enabled()) {
    require_once __DIR__ . '/../lib/daily_record_revision_delete_pipeline.php';
    try {
        $farmId = requireCurrentFarmId();
        $actorId = (int)($_SESSION['user_id'] ?? 0);
        $authorizeDelete = static function(
            string $module, string $view, string $operation
        ): bool {
            return isPlatformOwner() || hasRole('farm_admin') || (
                checkAccess($module) &&
                hasPermission(getUserType(), $view) &&
                hasPermission(getUserType(), $operation)
            );
        };
        $deleted = daily_record_revision_delete_transaction(
            $pdo, (string)$type, $farmId, $actorId, $_POST, $authorizeDelete
        );
        send_json([
            'success'=>true, 'message'=>'Record deleted successfully.',
            'record_id'=>$deleted['record_id'],
        ]);
    } catch (DailyRecordRevisionPermissionDenied $error) {
        send_json(['success'=>false, 'code'=>'forbidden',
            'error'=>'You do not have permission to delete this record.'], 403);
    } catch (DailyRecordRevisionConflict $error) {
        send_json(['success'=>false, 'code'=>'record_conflict',
            'error'=>'The record changed. Reload before deleting.'], 409);
    } catch (InvalidArgumentException $error) {
        send_json(['success'=>false, 'code'=>'invalid_delete_request',
            'error'=>'Invalid record selection. Reload before deleting.'], 422);
    } catch (Throwable $error) {
        log_app_error('delete_record_revision_failed', [
            'type' => (string)$type,
            'exception' => get_class($error),
        ]);
        send_json(['success'=>false, 'code'=>'delete_failed',
            'error'=>'Unable to delete the record. Try again later.'], 500);
    }
}

try {
    $farmId = requireCurrentFarmId();
    $pdo->beginTransaction();
    // Lock the exact farm-qualified source before reversing feed, stock or
    // mortality; no date-only probe or unprotected pretransaction read.
    $lockedRecord = daily_record_revision_lock_source_for_delete(
        $pdo, (string)$type, $farmId, (int)$id
    );
    $layerRecordDate = $type === 'layer'
        ? (string)$lockedRecord['record_date'] : null;
    $sourceType = $type === 'layer'
        ? 'daily_layer_record' : 'daily_broiler_record';
    $stmt = $pdo->prepare(
        $type === 'layer'
            ? 'DELETE FROM layer_daily_records WHERE id = ? AND farm_id = ?'
            : 'DELETE FROM broiler_daily_records WHERE id = ? AND farm_id = ?'
    );

    // Restore inventory and remove the canonical mortality projection
    // before deleting the durable Daily Record source. All operations share
    // this transaction, so a later failure rolls the complete deletion back.
    delete_daily_feed_usage($pdo, $farmId, (int)$id, $sourceType);

    daily_population_remove_mortality(
        $pdo,
        $farmId,
        $sourceType,
        (int)$id,
        isset($_SESSION['user_id'])
            ? (int)$_SESSION['user_id']
            : null
    );

    $stmt->execute([$id, $farmId]);
    if ($stmt->rowCount() !== 1) {
        $pdo->rollBack();
        send_json(['success' => false, 'error' => 'Record was not found or could not be deleted.'], 404);
    }
    if ($type === 'layer' && !empty($layerRecordDate)) {
        sales_rebuild_layer_egg_allocations($pdo, $farmId, (string)$layerRecordDate, (int)($_SESSION['user_id'] ?? 0));
    }
    $pdo->commit();
    $_SESSION['success'] = 'Record deleted successfully.';
    send_json(['success' => true, 'message' => 'Record deleted successfully']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    log_app_error('delete_record_failed', ['error' => safe_api_exception_message($e, 'The record could not be deleted.'), 'type' => $type, 'id' => $id]);
    send_json(['success' => false, 'error' => safe_api_exception_message($e, 'The record could not be deleted.')], 400);
}
?>
