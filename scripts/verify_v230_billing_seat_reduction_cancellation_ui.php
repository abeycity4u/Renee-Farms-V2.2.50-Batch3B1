<?php
/**
 * Focused static verifier for customer scheduled-reduction cancellation UI.
 *
 * Database-free and provider-network-free.
 */

$root = dirname(__DIR__);

$routePath =
    $root . '/billing/seat_reduction_cancel.php';

$accountPath =
    $root . '/billing/account.php';

$overviewPath =
    $root . '/includes/billing_account_overview.php';

foreach (
    [
        $routePath,
        $accountPath,
        $overviewPath,
    ] as $path
) {
    if (!is_file($path)) {
        fwrite(
            STDERR,
            "FAIL: missing {$path}\n"
        );
        exit(1);
    }
}

$route =
    (string)file_get_contents(
        $routePath
    );

$account =
    (string)file_get_contents(
        $accountPath
    );

$overview =
    (string)file_get_contents(
        $overviewPath
    );

$routeCompact =
    preg_replace('/\s+/', '', $route);

$checks = 0;
$failures = 0;

$check = static function (
    bool $ok,
    string $message
) use (&$checks, &$failures): void {
    $checks++;

    if ($ok) {
        echo "PASS: {$message}\n";
        return;
    }

    $failures++;

    echo "FAIL: {$message}\n";
};

$check(
    strpos(
        $route,
        'billing_require_farm_admin_actor('
    ) !== false
    && strpos(
        $route,
        '$pdo,'
    ) !== false
    && strpos(
        $route,
        'false'
    ) !== false,
    'cancellation route requires the normal Farm Admin actor'
);

$check(
    strpos(
        $route,
        "REQUEST_METHOD"
    ) !== false
    && strpos(
        $route,
        "!== 'POST'"
    ) !== false
    && strpos(
        $route,
        'require_valid_csrf_post();'
    ) !== false,
    'cancellation route is POST-only and CSRF protected'
);

$check(
    strpos(
        $route,
        "'request_id'"
    ) !== false
    && strpos(
        $route,
        "'cancel_seat_reduction'"
    ) !== false
    && strpos(
        $route,
        "\$_POST['farm_id']"
    ) === false
    && strpos(
        $route,
        "\$_POST['role_code']"
    ) === false
    && strpos(
        $route,
        "\$_POST['amount']"
    ) === false,
    'browser cancellation request controls only the durable request identity'
);

$check(
    strpos(
        $routeCompact,
        'billing_seat_reduction_cancel_scheduled($pdo,$farmId,(int)$requestId)'
    ) !== false,
    'route delegates mutation to the centralized cancellation foundation'
);

$routeForbidden = [
    'UPDATE billing_seat_change_requests',
    'UPDATE farms',
    'UPDATE subscriptions',
    'UPDATE farm_subscription_seat_addons',
    'INSERT INTO billing_payment_attempts',
    'billing_provider_',
    'curl_',
];

$routeHasForbidden = false;

foreach ($routeForbidden as $needle) {
    if (stripos(
        $route,
        $needle
    ) !== false) {
        $routeHasForbidden = true;
        break;
    }
}

$check(
    !$routeHasForbidden,
    'customer cancellation route performs no direct billing DML or provider work'
);

$check(
    strpos(
        $overview,
        'function billing_account_scheduled_reductions('
    ) !== false
    && strpos(
        $overview,
        "'scheduled_request_ids'"
    ) !== false,
    'account read model resolves scheduled reductions from authoritative renewal request ids'
);

$check(
    strpos(
        $overview,
        'billing_seat_change_request_by_id('
    ) !== false
    && strpos(
        $overview,
        'billing_seat_change_row_contract('
    ) !== false,
    'account read model reloads and integrity-validates each durable request'
);

$check(
    strpos(
        $overview,
        "'request_id' =>"
    ) !== false
    && strpos(
        $overview,
        "'from_extra_seats' =>"
    ) !== false
    && strpos(
        $overview,
        "'to_extra_seats' =>"
    ) !== false
    && strpos(
        $overview,
        "'effective_at' =>"
    ) !== false,
    'account read model exposes only the scheduled-reduction facts needed by UI'
);

$check(
    strpos(
        $account,
        "\$overview['scheduled_reductions']"
    ) !== false,
    'account page consumes centralized scheduled-reduction display data'
);

$check(
    strpos(
        $account,
        "/billing/seat_reduction_cancel.php"
    ) !== false
    && strpos(
        $account,
        'name="request_id"'
    ) !== false
    && strpos(
        $account,
        'name="cancel_seat_reduction"'
    ) !== false,
    'scheduled reduction UI posts durable request identity to the dedicated cancellation route'
);

$check(
    strpos(
        $account,
        '<?= csrf_field() ?>'
    ) !== false
    && strpos(
        $account,
        'Cancel reduction'
    ) !== false,
    'scheduled reduction cancellation control includes CSRF and explicit customer wording'
);

$check(
    strpos(
        $route,
        'Current paid-term seat allowance remains unchanged.'
    ) !== false
    && strpos(
        $route,
        'No current seat allowance was changed.'
    ) !== false,
    'route communicates that cancellation never mutates current paid-term seats'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    echo "V2.3 SCHEDULED REDUCTION CANCELLATION UI: FAILED\n";
    exit(1);
}

echo "V2.3 SCHEDULED REDUCTION CANCELLATION UI: PASSED\n";
?>
