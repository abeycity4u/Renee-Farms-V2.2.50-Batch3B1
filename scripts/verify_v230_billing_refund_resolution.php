<?php
/**
 * Focused verifier for V2.3 post-application
 * refund-resolution foundation.
 *
 * Read-only, database-free and provider-network-free.
 */

$root = dirname(__DIR__);

$migrationPath =
    $root
    . '/migrations/050_billing_refund_resolution.sql';

$servicePath =
    $root
    . '/includes/billing_refund_resolution.php';

if (!is_file($migrationPath)
    || !is_file($servicePath)) {
    fwrite(
        STDERR,
        "FAIL: refund-resolution foundation is incomplete.\n"
    );
    exit(1);
}

require_once $servicePath;

$migration =
    (string)file_get_contents($migrationPath);

$service =
    (string)file_get_contents($servicePath);

$checks = 0;
$failures = 0;

$check = static function (
    bool $ok,
    string $message
) use (&$checks, &$failures): void {
    $checks++;

    echo ($ok ? 'PASS: ' : 'FAIL: ')
        . $message
        . PHP_EOL;

    if (!$ok) {
        $failures++;
    }
};

$check(
    strpos(
        $migration,
        'CREATE TABLE IF NOT EXISTS billing_refund_resolutions'
    ) !== false
    && strpos(
        $migration,
        "VALUES ('050_billing_refund_resolution.sql')"
    ) !== false,
    'migration owns durable refund-resolution storage and registration'
);

$check(
    strpos(
        $migration,
        'UNIQUE KEY uniq_billing_refund_payment_attempt'
    ) !== false,
    'one durable refund-resolution row is allowed per payment attempt'
);

foreach ([
    'fk_billing_refund_farm',
    'fk_billing_refund_payment_attempt',
    'fk_billing_refund_subscription',
    'fk_billing_refund_seat_request',
] as $constraint) {
    $check(
        strpos($migration, $constraint) !== false,
        "migration defines {$constraint}"
    );
}

$check(
    substr_count(
        $migration,
        'ON DELETE RESTRICT'
    ) === 4,
    'refund-resolution commercial evidence is retention protected'
);

$check(
    billing_refund_resolution_statuses()
        === [
            'pending_review',
            'resolved',
        ],
    'refund-resolution workflow has explicit pending and resolved states'
);

$check(
    billing_refund_resolution_actions()
        === [
            'preserve_entitlement',
            'reverse_entitlement',
        ],
    'commercial resolution requires explicit preserve or reverse action'
);

$required =
    billing_refund_resolution_required_columns();

$check(
    in_array(
        'refund_verified_at',
        $required,
        true
    )
    && in_array(
        'applied_subscription_record_id',
        $required,
        true
    )
    && in_array(
        'seat_change_request_id',
        $required,
        true
    ),
    'resolution preserves refund verification and exact application lineage'
);

$pendingSubscription = [
    'id' => 1,
    'farm_id' => 4,
    'payment_attempt_id' => 50,
    'purpose' => 'subscription',
    'status' => 'pending_review',
    'resolution_action' => null,
    'refund_verified_at' =>
        '2026-09-13 12:00:00',
    'applied_subscription_record_id' => 20,
    'seat_change_request_id' => null,
    'resolved_at' => null,
    'resolved_by_user_id' => null,
    'resolution_reason' => null,
];

$pendingSeat = [
    'id' => 2,
    'farm_id' => 4,
    'payment_attempt_id' => 51,
    'purpose' => 'seat_topup',
    'status' => 'pending_review',
    'resolution_action' => null,
    'refund_verified_at' =>
        '2026-09-13 12:05:00',
    'applied_subscription_record_id' => null,
    'seat_change_request_id' => 9,
    'resolved_at' => null,
    'resolved_by_user_id' => null,
    'resolution_reason' => null,
];

$resolved = $pendingSeat;
$resolved['status'] = 'resolved';
$resolved['resolution_action'] =
    'preserve_entitlement';
$resolved['resolved_at'] =
    '2026-09-13 12:10:00';
$resolved['resolved_by_user_id'] = 1;
$resolved['resolution_reason'] =
    'Support-approved commercial preservation.';

try {
    $subscriptionContract =
        billing_refund_resolution_row_contract(
            $pendingSubscription
        );

    $check(
        $subscriptionContract['purpose']
            === 'subscription'
        && $subscriptionContract[
            'applied_subscription_record_id'
        ] === 20
        && $subscriptionContract[
            'seat_change_request_id'
        ] === null,
        'subscription refund binds only to applied subscription history'
    );
} catch (Throwable $e) {
    $check(
        false,
        'subscription refund binds only to applied subscription history'
    );
}

try {
    $seatContract =
        billing_refund_resolution_row_contract(
            $pendingSeat
        );

    $check(
        $seatContract['purpose']
            === 'seat_topup'
        && $seatContract[
            'seat_change_request_id'
        ] === 9
        && $seatContract[
            'applied_subscription_record_id'
        ] === null,
        'seat-top-up refund binds only to applied seat request'
    );
} catch (Throwable $e) {
    $check(
        false,
        'seat-top-up refund binds only to applied seat request'
    );
}

try {
    $resolvedContract =
        billing_refund_resolution_row_contract(
            $resolved
        );

    $check(
        $resolvedContract['status']
            === 'resolved'
        && $resolvedContract[
            'resolution_action'
        ] === 'preserve_entitlement'
        && $resolvedContract[
            'resolved_by_user_id'
        ] === 1,
        'resolved refund requires explicit action and audit evidence'
    );
} catch (Throwable $e) {
    $check(
        false,
        'resolved refund requires explicit action and audit evidence'
    );
}

$badPending = $pendingSeat;
$badPending['resolution_action'] =
    'reverse_entitlement';

try {
    billing_refund_resolution_row_contract(
        $badPending
    );

    $check(
        false,
        'pending review rejects premature resolution metadata'
    );
} catch (Throwable $e) {
    $check(
        true,
        'pending review rejects premature resolution metadata'
    );
}

$badSubscription = $pendingSubscription;
$badSubscription[
    'seat_change_request_id'
] = 9;

try {
    billing_refund_resolution_row_contract(
        $badSubscription
    );

    $check(
        false,
        'refund application lineage fails closed on mixed purpose references'
    );
} catch (Throwable $e) {
    $check(
        true,
        'refund application lineage fails closed on mixed purpose references'
    );
}

$check(
    strpos(
        $service,
        'billing_refund_resolution_foreign_keys_ready'
    ) !== false
    && strpos(
        $service,
        'billing_refund_resolution_unique_key_ready'
    ) !== false
    && strpos(
        $service,
        'billing_refund_resolution_migration_ready'
    ) !== false,
    'storage readiness verifies foreign keys, uniqueness and migration'
);

$uniqueStart = strpos(
    $service,
    'function billing_refund_resolution_unique_key_ready'
);

$uniqueEnd = strpos(
    $service,
    'function billing_refund_resolution_migration_ready'
);

$uniqueSection =
    $uniqueStart !== false
    && $uniqueEnd !== false
    && $uniqueEnd > $uniqueStart
        ? substr(
            $service,
            $uniqueStart,
            $uniqueEnd - $uniqueStart
        )
        : '';

$check(
    strpos(
        $uniqueSection,
        'column_name'
    ) !== false
    && strpos(
        $uniqueSection,
        'seq_in_index'
    ) !== false
    && strpos(
        $uniqueSection,
        "'payment_attempt_id'"
    ) !== false,
    'unique-key readiness verifies the exact payment-attempt index column'
);

$reason160 = $resolved;
$reason160['resolution_reason'] =
    str_repeat('x', 160);

try {
    $reason160Contract =
        billing_refund_resolution_row_contract(
            $reason160
        );

    $check(
        strlen(
            (string)$reason160Contract[
                'resolution_reason'
            ]
        ) === 160,
        '160-character resolution reason is accepted'
    );
} catch (Throwable $e) {
    $check(
        false,
        '160-character resolution reason is accepted'
    );
}

$reason161 = $resolved;
$reason161['resolution_reason'] =
    str_repeat('x', 161);

try {
    billing_refund_resolution_row_contract(
        $reason161
    );

    $check(
        false,
        'resolution reason longer than storage capacity fails closed'
    );
} catch (Throwable $e) {
    $check(
        true,
        'resolution reason longer than storage capacity fails closed'
    );
}

$protectedDml =
    '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+'
    . '(?:farms|farm_modules|farm_role_limits|'
    . 'farm_subscription_seat_addons|subscriptions|'
    . 'billing_payment_attempts|'
    . 'billing_seat_change_requests)\b/i';

$check(
    !preg_match(
        $protectedDml,
        $service
    ),
    'foundation service performs no payment or commercial-state mutation'
);

$providerCall =
    '/\bcurl_(?:init|exec)|'
    . 'billing_provider_(?:initialize|verify|charge)/i';

$check(
    !preg_match(
        $providerCall,
        $service
    ),
    'foundation service performs no provider or network call'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    echo "V2.3 REFUND RESOLUTION FOUNDATION: FAILED\n";
    exit(1);
}

echo "V2.3 REFUND RESOLUTION FOUNDATION: PASSED\n";
