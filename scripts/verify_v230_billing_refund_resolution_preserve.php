<?php
/**
 * V2.3 Gap D Phase 3A verifier.
 *
 * Proves explicit preserve-entitlement resolution of a captured
 * post-application refund review.
 *
 * Contract:
 * - provider refund fact is already durable;
 * - commercial application lineage is revalidated under locks;
 * - preserve action changes only refund-resolution audit metadata;
 * - no tenant entitlement/payment/provider state is changed.
 *
 * Static/database-free/provider-network-free.
 */

$root = dirname(__DIR__);

$servicePath =
    $root . '/includes/billing_refund_resolution.php';

if (!is_file($servicePath)) {
    fwrite(
        STDERR,
        "FAIL: refund-resolution service is missing.\n"
    );
    exit(1);
}

require_once $servicePath;

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

$helperMarker =
    'function billing_refund_resolution_assert_locked_lineage';

$resolverMarker =
    'function billing_refund_resolution_resolve_preserve';

$helperStart =
    strpos($service, $helperMarker);

$resolverStart =
    strpos($service, $resolverMarker);

$check(
    function_exists(
        'billing_refund_resolution_assert_locked_lineage'
    ),
    'shared locked-lineage validator exists'
);

$check(
    function_exists(
        'billing_refund_resolution_resolve_preserve'
    ),
    'shared preserve-entitlement resolver exists'
);

$helper =
    $helperStart === false
        ? ''
        : substr(
            $service,
            $helperStart,
            $resolverStart !== false
                && $resolverStart > $helperStart
                ? $resolverStart - $helperStart
                : null
        );

$resolver =
    $resolverStart === false
        ? ''
        : substr($service, $resolverStart);

$check(
    strpos(
        $resolver,
        'Refund preservation requires an active database transaction.'
    ) !== false
    && strpos(
        $resolver,
        'billing_refund_resolution_ready'
    ) !== false,
    'preserve resolution requires caller transaction and ready storage'
);

$paymentLock =
    strpos(
        $resolver,
        'billing_audit_attempt_by_id'
    );

$resolutionLock =
    strpos(
        $resolver,
        'billing_refund_resolution_by_payment'
    );

$lineageCall =
    strpos(
        $resolver,
        'billing_refund_resolution_assert_locked_lineage'
    );

$check(
    $paymentLock !== false
    && $resolutionLock !== false
    && $lineageCall !== false
    && $paymentLock
        < $resolutionLock
    && $resolutionLock
        < $lineageCall,
    'resolver locks payment attempt before resolution before purpose context'
);

$check(
    strpos(
        $helper,
        "\$paymentStatus !== 'refunded'"
    ) !== false
    && strpos(
        $helper,
        "'verified_at'"
    ) !== false
    && strpos(
        $helper,
        "'paid_at'"
    ) !== false,
    'lineage requires refunded provider fact with verification and paid evidence'
);

$check(
    strpos(
        $helper,
        "'payment_attempt_id'"
    ) !== false
    && strpos(
        $helper,
        "'farm_id'"
    ) !== false
    && strpos(
        $helper,
        "'purpose'"
    ) !== false,
    'resolution identity is rebound to exact payment farm and purpose'
);

$check(
    strpos(
        $helper,
        'applied_subscription_record_id'
    ) !== false
    && strpos(
        $helper,
        'FROM subscriptions'
    ) !== false
    && strpos(
        $helper,
        'AND farm_id = ?'
    ) !== false,
    'subscription preservation validates exact same-tenant applied history'
);

$check(
    strpos(
        $helper,
        'billing_seat_change_request_by_payment'
    ) !== false
    && strpos(
        $helper,
        "'change_kind'"
    ) !== false
    && strpos(
        $helper,
        "'payment_attempt_id'"
    ) !== false
    && strpos(
        $helper,
        "'farm_id'"
    ) !== false,
    'seat preservation validates exact durable add request lineage'
);

$check(
    strpos(
        $helper,
        "\$requestState['status'] !== 'applied'"
    ) !== false
    && strpos(
        $helper,
        "'applied_at'"
    ) !== false,
    'seat refund resolution requires already-applied request evidence'
);

$check(
    strpos(
        $resolver,
        "=== 'resolved'"
    ) !== false
    && strpos(
        $resolver,
        "'already_preserved'"
    ) !== false,
    'repeat preserve resolution is idempotent'
);

$check(
    strpos(
        $resolver,
        'different commercial action'
    ) !== false,
    'already-resolved different action fails closed'
);

$check(
    substr_count(
        $resolver,
        'UPDATE billing_refund_resolutions'
    ) === 1,
    'preserve resolver has exactly one refund-resolution update'
);

$check(
    strpos(
        $resolver,
        "status = 'resolved'"
    ) !== false
    && strpos(
        $resolver,
        "'preserve_entitlement'"
    ) !== false
    && strpos(
        $resolver,
        'resolved_at = CURRENT_TIMESTAMP'
    ) !== false
    && strpos(
        $resolver,
        'resolved_by_user_id = ?'
    ) !== false
    && strpos(
        $resolver,
        'resolution_reason = ?'
    ) !== false,
    'resolution write records explicit action actor reason and timestamp'
);

$check(
    strpos(
        $resolver,
        "AND status = 'pending_review'"
    ) !== false
    && strpos(
        $resolver,
        '$update->rowCount() !== 1'
    ) !== false,
    'pending review transitions exactly once'
);

$check(
    strpos(
        $resolver,
        'billing_refund_resolution_row_contract'
    ) !== false
    && strpos(
        $resolver,
        'Preserved refund resolution failed its post-write audit contract.'
    ) !== false,
    'resolved row is reloaded and contract-validated after mutation'
);

$protectedDml =
    '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+'
    . '(?:farms|farm_modules|farm_role_limits|'
    . 'farm_subscription_seat_addons|subscriptions|'
    . 'billing_payment_attempts|'
    . 'billing_seat_change_requests)\b/i';

$check(
    !preg_match(
        $protectedDml,
        $helper . "\n" . $resolver
    ),
    'preserve workflow performs no payment or entitlement mutation'
);

$providerOps =
    '/\bcurl_(?:init|exec)|'
    . 'billing_provider_(?:initialize|verify|charge)/i';

$check(
    !preg_match(
        $providerOps,
        $helper . "\n" . $resolver
    ),
    'preserve workflow performs no provider or network call'
);

$check(
    strpos(
        $helper,
        'refund_verified_at'
    ) === false,
    'repeat verification timestamp is not required to equal first captured refund time'
);

$check(
    substr_count(
        $service,
        'function billing_refund_resolution_resolve_preserve'
    ) === 1
    && substr_count(
        $service,
        'function billing_refund_resolution_assert_locked_lineage'
    ) === 1,
    'Phase 3A shared functions are defined exactly once'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    echo "V2.3 REFUND PRESERVE RESOLUTION: FAILED\n";
    exit(1);
}

echo "V2.3 REFUND PRESERVE RESOLUTION: PASSED\n";
