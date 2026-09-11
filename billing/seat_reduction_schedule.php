<?php
/**
 * V2.3 customer scheduled seat-reduction route.
 *
 * Contract:
 * - normal Farm Admin session only;
 * - POST + CSRF only;
 * - browser controls only role and positive removal quantity;
 * - tenant, current seats, future capacity, product and effective date are
 *   resolved server-side by billing_seat_reduction_schedule();
 * - no payment is created and current paid-term entitlement is not changed.
 */

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__)
    . '/includes/billing_tenant_actor.php';
require_once dirname(__DIR__)
    . '/includes/billing_seat_reduction_initiation.php';

$actor = billing_require_farm_admin_actor(
    $pdo,
    false
);

$farmId = (int)$actor['farm_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

require_valid_csrf_post();

$allowedKeys = [
    'csrf_token',
    'role_code',
    'quantity',
    'schedule_seat_reduction',
];

foreach (array_keys($_POST) as $key) {
    if (!in_array(
        (string)$key,
        $allowedKeys,
        true
    )) {
        http_response_code(422);
        exit('Invalid seat-reduction request.');
    }
}

if (!isset($_POST['schedule_seat_reduction'])) {
    http_response_code(422);
    exit('Invalid seat-reduction request.');
}

if (!$pdo instanceof PDO
    || !billing_seat_change_ready($pdo)) {
    $_SESSION['error'] =
        'Seat reduction is temporarily unavailable. Please try again later.';

    header(
        'Location: '
            . BASE_URL
            . '/billing/account.php',
        true,
        303
    );
    exit();
}

$roleCode =
    trim((string)($_POST['role_code'] ?? ''));

$quantity = filter_var(
    $_POST['quantity'] ?? null,
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'min_range' => 1,
            'max_range' => 500,
        ],
    ]
);

if ($quantity === false) {
    $_SESSION['error'] =
        'Choose a valid number of extra seats to remove.';

    header(
        'Location: '
            . BASE_URL
            . '/billing/account.php',
        true,
        303
    );
    exit();
}

try {
    $scheduled =
        billing_seat_reduction_schedule(
            $pdo,
            $farmId,
            $roleCode,
            (int)$quantity,
            (int)$actor['user_id']
        );

    $effectiveAt = trim((string)(
        $scheduled['effective_at']
            ?? ''
    ));

    $effectiveLabel = $effectiveAt;

    $effectiveTime = strtotime(
        $effectiveAt
    );

    if ($effectiveTime !== false) {
        $effectiveLabel =
            date(
                'd M Y',
                $effectiveTime
            );
    }

    $_SESSION['success'] =
        'Seat reduction scheduled for '
        . $effectiveLabel
        . '. Current paid-term seat limits stay available until then; no refund is created.';

    header(
        'Location: '
            . BASE_URL
            . '/billing/account.php',
        true,
        303
    );
    exit();
} catch (InvalidArgumentException $e) {
    $_SESSION['error'] =
        'Seat reduction could not be scheduled. Check the role and quantity, then try again.';

    header(
        'Location: '
            . BASE_URL
            . '/billing/account.php',
        true,
        303
    );
    exit();
} catch (Throwable $e) {
    error_log(
        'Scheduled seat reduction failed for farm '
        . $farmId
        . ': '
        . $e->getMessage()
    );

    $_SESSION['error'] =
        'Seat reduction is not available right now. No current seat allowance was changed.';

    header(
        'Location: '
            . BASE_URL
            . '/billing/account.php',
        true,
        303
    );
    exit();
}
