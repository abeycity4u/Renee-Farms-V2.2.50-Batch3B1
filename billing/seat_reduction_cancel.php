<?php
/**
 * V2.3 customer scheduled seat-reduction cancellation route.
 *
 * Contract:
 * - normal Farm Admin session only;
 * - POST + CSRF only;
 * - browser controls only the durable request id;
 * - authenticated tenant identity is server-derived;
 * - cancellation delegates entirely to the centralized foundation;
 * - no current entitlement, subscription or payment state is changed;
 * - no provider or network work is performed.
 */

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__)
    . '/includes/billing_tenant_actor.php';
require_once dirname(__DIR__)
    . '/includes/billing_seat_reduction_cancellation.php';

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
    'request_id',
    'cancel_seat_reduction',
];

foreach (array_keys($_POST) as $key) {
    if (!in_array(
        (string)$key,
        $allowedKeys,
        true
    )) {
        http_response_code(422);
        exit(
            'Invalid scheduled seat-reduction cancellation request.'
        );
    }
}

if (!isset($_POST['cancel_seat_reduction'])) {
    http_response_code(422);
    exit(
        'Invalid scheduled seat-reduction cancellation request.'
    );
}

$requestId = filter_var(
    $_POST['request_id'] ?? null,
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'min_range' => 1,
        ],
    ]
);

if ($requestId === false) {
    $_SESSION['error'] =
        'Scheduled seat reduction could not be identified.';

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
    $result =
        billing_seat_reduction_cancel_scheduled(
            $pdo,
            $farmId,
            (int)$requestId
        );

    if (($result['changed'] ?? false) === true) {
        $_SESSION['success'] =
            'Scheduled seat reduction cancelled. '
            . 'Current paid-term seat allowance remains unchanged.';
    } else {
        $_SESSION['success'] =
            'Scheduled seat reduction was already cancelled. '
            . 'Current paid-term seat allowance remains unchanged.';
    }

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
        'Scheduled seat reduction could not be cancelled. '
        . 'Refresh the billing page and try again.';

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
        'Scheduled seat reduction cancellation failed for farm '
        . $farmId
        . ': '
        . $e->getMessage()
    );

    $_SESSION['error'] =
        'Scheduled seat reduction cancellation is not available right now. '
        . 'No current seat allowance was changed.';

    header(
        'Location: '
            . BASE_URL
            . '/billing/account.php',
        true,
        303
    );

    exit();
}
