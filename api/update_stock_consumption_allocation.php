<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php

require_once __DIR__
    . '/../config.php';

require_once __DIR__
    . '/api_helpers.php';

requireLogin();

require_once __DIR__
    . '/../includes/functions.php';

require_once __DIR__
    . '/../includes/permission_catalog.php';

require_once __DIR__
    . '/../lib/stock_consumption_allocation_workspace.php';

require_once __DIR__
    . '/../lib/stock_consumption_allocation_persistence.php';

require_http_method('POST');
require_csrf_token();

require_rate_limit(
    'update_stock_consumption_allocation',
    60,
    60
);

$stockTransactionId =
    (int)(
        $_POST['stock_transaction_id']
        ?? 0
    );

$revisionReason =
    trim(
        (string)(
            $_POST['revision_reason']
            ?? ''
        )
    );

$rowsJson =
    (string)(
        $_POST['rows_json']
        ?? '[]'
    );

$desiredRows =
    json_decode(
        $rowsJson,
        true
    );

$decisionAction =
    strtolower(
        trim(
            (string)(
                $_POST['decision_action']
                ?? 'allocate'
            )
        )
    );

if ($stockTransactionId < 1) {
    send_json([
        'success' => false,
        'error' =>
            'Select a valid consumed-stock transaction.',
    ], 400);
}

if (!is_array($desiredRows)) {
    send_json([
        'success' => false,
        'error' =>
            'Consumed-stock allocation rows are invalid.',
    ], 400);
}

if (
    !in_array(
        $decisionAction,
        [
            'allocate',
            'retain_shared',
        ],
        true
    )
) {
    send_json([
        'success' => false,
        'error' =>
            'Consumed-stock shared-cost decision is invalid.',
    ], 400);
}

if (
    $decisionAction === 'retain_shared'
    && $desiredRows !== []
) {
    send_json([
        'success' => false,
        'error' =>
            'Clear cycle amounts before retaining this consumed stock cost as shared.',
    ], 422);
}

if (
    !stock_consumption_allocation_workspace_can_manage()
) {
    send_json([
        'success' => false,
        'error' =>
            'You do not have permission to allocate consumed stock costs.',
    ], 403);
}

$farmId =
    requireCurrentFarmId();

$actorUserId =
    (int)(
        $_SESSION['user_id']
        ?? 0
    );

if ($actorUserId < 1) {
    send_json([
        'success' => false,
        'error' =>
            'Your session cannot perform this stock allocation.',
    ], 403);
}

try {
    /*
     * The canonical persistence service owns all parent/source/cycle locks,
     * current-state validation, projection mutation and revision creation.
     *
     * This route owns only the outer transaction.
     */
    $pdo->beginTransaction();

    $result =
        $decisionAction === 'retain_shared'
            ? stock_consumption_allocation_persistence_retain_shared(
                $pdo,
                $farmId,
                $stockTransactionId,
                $actorUserId,
                $revisionReason !== ''
                    ? $revisionReason
                    : null
            )
            : stock_consumption_allocation_persistence_apply(
                $pdo,
                $farmId,
                $stockTransactionId,
                $desiredRows,
                $actorUserId,
                $revisionReason !== ''
                    ? $revisionReason
                    : null
            );

    $pdo->commit();

    if ($decisionAction === 'retain_shared') {
        $message =
            !empty($result['changed'])
                ? 'Consumed stock cost retained as shared successfully.'
                : 'This consumed stock cost is already retained as shared.';
    } else {
        $message =
            !empty($result['changed'])
                ? 'Consumed stock cost allocation saved successfully.'
                : 'No allocation changes were needed.';
    }

    $_SESSION['success'] =
        $message;

    send_json([
        'success' =>
            true,

        'message' =>
            $message,

        'changed' =>
            !empty(
                $result['changed']
            ),

        'action' =>
            $result['revision_action']
            ?? $result['action']
            ?? 'noop',

        'stock_transaction_id' =>
            $stockTransactionId,

        'allocated_amount' =>
            $result['allocated_amount']
            ?? '0.00',

        'remaining_amount' =>
            $result['unallocated_amount']
            ?? $result['remaining_amount']
            ?? '0.00',

        'revision_no' =>
            $result['revision_no']
            ?? null,
    ]);

} catch (InvalidArgumentException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    send_json([
        'success' => false,
        'error' =>
            $e->getMessage(),
    ], 422);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    log_app_error(
        'update_stock_consumption_allocation_database_failed',
        [
            'error' =>
                safe_api_exception_message(
                    $e,
                    'The consumed stock cost allocation could not be saved.'
                ),

            'stock_transaction_id' =>
                $stockTransactionId,
        ]
    );

    send_json([
        'success' => false,
        'error' =>
            'The consumed stock cost allocation could not be saved.',
    ], 500);

} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $status =
        $e->getMessage()
            === 'Stock consumption transaction not found.'
                ? 404
                : 409;

    send_json([
        'success' => false,
        'error' =>
            $e->getMessage(),
    ], $status);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    log_app_error(
        'update_stock_consumption_allocation_failed',
        [
            'error' =>
                safe_api_exception_message(
                    $e,
                    'The consumed stock cost allocation could not be saved.'
                ),

            'stock_transaction_id' =>
                $stockTransactionId,
        ]
    );

    send_json([
        'success' => false,
        'error' =>
            safe_api_exception_message(
                $e,
                'The consumed stock cost allocation could not be saved.'
            ),
    ], 500);
}
