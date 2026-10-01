<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php

require_once __DIR__
    . '/../config.php';

require_once __DIR__
    . '/api_helpers.php';

require_once __DIR__
    . '/../includes/functions.php';

require_once __DIR__
    . '/../includes/permission_catalog.php';

require_once __DIR__
    . '/../lib/general_expense_entry.php';

requireLogin();
require_http_method('POST');
require_csrf_token();
require_rate_limit(
    'create_general_expense',
    30,
    60
);

$farmId =
    requireCurrentFarmId();

if (!current_farm_is_sales_only()) {
    send_json(
        [
            'success' => false,
            'error' =>
                'General operating-expense entry is available from the Sales-only workspace.',
        ],
        403
    );
}

$privileged =
    isPlatformOwner()
    ||
    hasRole('farm_admin');

if (
    !$privileged
    &&
    (
        !hasPermission(
            getUserType(),
            'expenses'
        )
        ||
        !hasPermission(
            getUserType(),
            'expenses_add'
        )
    )
) {
    send_json(
        [
            'success' => false,
            'error' =>
                'You do not have permission to record General expenses.',
        ],
        403
    );
}

$actorUserId =
    (int)(
        $_SESSION['user_id']
        ?? 0
    );

if ($actorUserId < 1) {
    send_json(
        [
            'success' => false,
            'error' =>
                'A valid signed-in user is required.',
        ],
        401
    );
}

try {
    $pdo->beginTransaction();

    $created =
        general_expense_entry_create(
            $pdo,
            $farmId,
            $actorUserId,
            [
                'expense_date' =>
                    $_POST['expense_date']
                    ?? '',

                'category' =>
                    $_POST['category']
                    ?? '',

                'amount' =>
                    $_POST['amount']
                    ?? null,

                'unit' =>
                    $_POST['unit']
                    ?? 1,

                'description' =>
                    $_POST['description']
                    ?? '',
            ]
        );

    $pdo->commit();

    send_json(
        [
            'success' => true,
            'message' =>
                'Expense recorded successfully.',
            'expense' =>
                $created,
        ]
    );

} catch (InvalidArgumentException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    send_json(
        [
            'success' => false,
            'error' =>
                $e->getMessage(),
        ],
        422
    );

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    log_app_error(
        'create_general_expense_failed',
        [
            'farm_id' =>
                $farmId,

            'user_id' =>
                $actorUserId,

            'error' =>
                $e->getMessage(),
        ]
    );

    send_json(
        [
            'success' => false,
            'error' =>
                'Unable to record the expense.',
        ],
        500
    );
}
