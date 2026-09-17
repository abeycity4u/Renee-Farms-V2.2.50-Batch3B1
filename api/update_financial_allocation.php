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
    . '/../lib/financial_allocation_workspace.php';

require_once __DIR__
    . '/../lib/financial_allocation_persistence.php';

require_http_method('POST');
require_csrf_token();
require_rate_limit(
    'update_financial_allocation',
    60,
    60
);

$expenseId =
    (int)(
        $_POST['expense_id']
        ?? 0
    );

$permissionScope =
    financial_allocation_workspace_scope(
        $_POST['permission_scope']
        ?? 'operational'
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

if ($expenseId < 1) {
    send_json([
        'success' => false,
        'error' =>
            'Select a valid expense.',
    ], 400);
}

if (!is_array($desiredRows)) {
    send_json([
        'success' => false,
        'error' =>
            'Financial allocation rows are invalid.',
    ], 400);
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
            'Your session cannot perform this allocation.',
    ], 403);
}

try {
    /*
     * Permission is evaluated against the same locked parent that the writer
     * will mutate. This prevents a concurrent attribution change from turning
     * a previously-authorized expense into an unauthorized write target.
     */
    $pdo->beginTransaction();

    $parent =
        financial_allocation_service_parent(
            $pdo,
            $farmId,
            $expenseId,
            true
        );

    if (
        !financial_allocation_workspace_can_access(
            $parent,
            $permissionScope
        )
    ) {
        $pdo->rollBack();

        send_json([
            'success' => false,
            'error' =>
                'You do not have permission to allocate this expense.',
        ], 403);
    }

    $result =
        financial_allocation_persistence_apply(
            $pdo,
            $farmId,
            $expenseId,
            $desiredRows,
            $actorUserId,
            $revisionReason !== ''
                ? $revisionReason
                : null
        );

    $pdo->commit();

    $message =
        !empty($result['changed'])
            ? 'Shared cost allocation saved successfully.'
            : 'No allocation changes were needed.';

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
            $result['action']
            ?? 'noop',

        'expense_id' =>
            $expenseId,

        'allocated_amount' =>
            $result[
                'allocated_amount'
            ],

        'remaining_amount' =>
            $result[
                'remaining_amount'
            ],

        'revision_no' =>
            $result[
                'revision_no'
            ]
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

} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $status =
        $e->getMessage()
            === 'Expense record not found.'
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
        'update_financial_allocation_failed',
        [
            'error' =>
                safe_api_exception_message(
                    $e,
                    'The shared cost allocation could not be saved.'
                ),

            'expense_id' =>
                $expenseId,
        ]
    );

    send_json([
        'success' => false,
        'error' =>
            safe_api_exception_message(
                $e,
                'The shared cost allocation could not be saved.'
            ),
    ], 500);
}
