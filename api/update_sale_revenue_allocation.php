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
    . '/../lib/sale_revenue_allocation_workspace.php';

require_http_method('POST');
require_csrf_token();

require_rate_limit(
    'update_sale_revenue_allocation',
    60,
    60
);

$saleId =
    (int)(
        $_POST['sale_id']
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

if ($saleId < 1) {
    send_json([
        'success' =>
            false,

        'error' =>
            'Select a valid sale.',
    ], 400);
}

if (!is_array($desiredRows)) {
    send_json([
        'success' =>
            false,

        'error' =>
            'Shared revenue allocation rows are invalid.',
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
        'success' =>
            false,

        'error' =>
            'Your session cannot perform this allocation.',
    ], 403);
}

try {
    /*
     * Outer transaction belongs to the API coordinator.
     *
     * Permission is evaluated against the locked sale parent before the
     * canonical writer mutates the projection. The persistence service owns
     * validation, locking order, provenance, revision semantics and writes.
     */
    $pdo->beginTransaction();

    $parent =
        sale_revenue_allocation_persistence_parent(
            $pdo,
            $farmId,
            $saleId,
            true
        );

    if (
        !sale_revenue_allocation_workspace_can_access(
            $parent
        )
    ) {
        $pdo->rollBack();

        send_json([
            'success' =>
                false,

            'error' =>
                'You do not have permission to allocate this sale revenue.',
        ], 403);
    }

    $result =
        sale_revenue_allocation_persistence_apply(
            $pdo,
            $farmId,
            $saleId,
            $desiredRows,
            $actorUserId,
            $revisionReason !== ''
                ? $revisionReason
                : null
        );

    $pdo->commit();

    $message =
        !empty(
            $result['changed']
        )
            ? 'Shared revenue allocation saved successfully.'
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

        'sale_id' =>
            $saleId,

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
        'success' =>
            false,

        'error' =>
            $e->getMessage(),
    ], 422);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    log_app_error(
        'update_sale_revenue_allocation_database_failed',
        [
            'error' =>
                safe_api_exception_message(
                    $e,
                    'The shared revenue allocation could not be saved.'
                ),

            'sale_id' =>
                $saleId,
        ]
    );

    send_json([
        'success' =>
            false,

        'error' =>
            'The shared revenue allocation could not be saved.',
    ], 500);

} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $status =
        $e->getMessage()
            === 'Sale record not found.'
                ? 404
                : 409;

    send_json([
        'success' =>
            false,

        'error' =>
            $e->getMessage(),
    ], $status);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    log_app_error(
        'update_sale_revenue_allocation_failed',
        [
            'error' =>
                safe_api_exception_message(
                    $e,
                    'The shared revenue allocation could not be saved.'
                ),

            'sale_id' =>
                $saleId,
        ]
    );

    send_json([
        'success' =>
            false,

        'error' =>
            safe_api_exception_message(
                $e,
                'The shared revenue allocation could not be saved.'
            ),
    ], 500);
}
