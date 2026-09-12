<?php
/**
 * Static contract verifier for tenant purge compatibility with durable
 * V2.3 billing/payment history.
 */

$root = dirname(__DIR__);

$farmsPath = $root . '/management/farms.php';
$foundationPath = $root . '/includes/billing_payment_foundation.php';
$migration046Path = $root . '/migrations/046_billing_seat_quote_snapshot.sql';
$migration048Path = $root . '/migrations/048_billing_tenant_retention_integrity.sql';
$runner048Path = $root . '/scripts/apply_v230_billing_tenant_retention_integrity.php';

$checks = 0;
$failures = 0;

$assert = static function (bool $condition, string $message) use (&$checks, &$failures): void {
    $checks++;

    if ($condition) {
        echo 'PASS: ' . $message . PHP_EOL;
        return;
    }

    $failures++;
    echo 'FAIL: ' . $message . PHP_EOL;
};

$assert(is_file($farmsPath), 'management/farms.php exists.');
$assert(is_file($foundationPath), 'billing payment foundation exists.');
$assert(is_file($migration046Path), 'migration 046 exists.');
$assert(is_file($migration048Path), 'migration 048 exists.');
$assert(is_file($runner048Path), 'targeted migration 048 runner exists.');

$farms = is_file($farmsPath) ? file_get_contents($farmsPath) : '';
$foundation = is_file($foundationPath) ? file_get_contents($foundationPath) : '';
$migration046 = is_file($migration046Path) ? file_get_contents($migration046Path) : '';
$migration048 = is_file($migration048Path) ? file_get_contents($migration048Path) : '';
$runner048 = is_file($runner048Path) ? file_get_contents($runner048Path) : '';

$functionStart = strpos($farms, 'function deleteFarmData(');
$functionEnd = $functionStart === false
    ? false
    : strpos($farms, "\n}\n", $functionStart);

$purge = (
    $functionStart !== false
    && $functionEnd !== false
)
    ? substr(
        $farms,
        $functionStart,
        ($functionEnd - $functionStart) + 3
    )
    : '';

$assert(
    $functionStart !== false && $functionEnd !== false,
    'tenant purge remains centralized in deleteFarmData().'
);

$assert(
    strpos($farms, 'function farmHasBillingPaymentHistory(') !== false
    && strpos(
        $farms,
        'SELECT 1 FROM billing_payment_attempts WHERE farm_id = ? LIMIT 1'
    ) !== false,
    'farm deletion has a centralized billing-history detector.'
);

$historyGuardPos = strpos($purge, 'farmHasBillingPaymentHistory($pdo, $farmId)');
$firstPurgePos = strpos($purge, 'foreach (');

$assert(
    $historyGuardPos !== false
    && $firstPurgePos !== false
    && $historyGuardPos < $firstPurgePos,
    'billing history blocks tenant purge before tenant rows are deleted.'
);

$assert(
    substr_count(
        $farms,
        'farmHasBillingPaymentHistory($pdo, $farmId)'
    ) >= 2,
    'manual farm deletion and centralized purge both enforce billing-history retention.'
);

$assert(
    strpos(
        $farms,
        'Suspend the farm instead to preserve the financial audit trail.'
    ) !== false,
    'platform owner receives an explicit safe alternative when deletion is blocked.'
);

$requestPos = strpos(
    $purge,
    "'billing_seat_change_requests'"
);

$subscriptionPos = strpos(
    $purge,
    "'subscriptions'"
);

$assert(
    $requestPos !== false,
    'tenant purge explicitly includes billing_seat_change_requests.'
);

$assert(
    $subscriptionPos !== false,
    'tenant purge explicitly includes subscriptions.'
);

$assert(
    $requestPos !== false
    && $subscriptionPos !== false
    && $requestPos < $subscriptionPos,
    'seat-change requests remain ordered before subscriptions for non-billed cleanup.'
);

$assert(
    strpos($purge, "'billing_payment_attempts'") === false
    && stripos($purge, 'DELETE FROM billing_payment_attempts') === false,
    'tenant purge never explicitly deletes durable payment attempts.'
);

$assert(
    strpos(
        $farms,
        "function deleteFarmRows(PDO \$pdo, string \$table, int \$farmId): void"
    ) !== false
    && strpos(
        $farms,
        "!tableExists(\$pdo, \$table)"
    ) !== false,
    'central purge helper remains schema-compatible when optional tables are absent.'
);

$assert(
    strpos(
        $migration046,
        'fk_billing_seat_change_payment_attempt'
    ) !== false
    && strpos(
        $migration046,
        'ON DELETE RESTRICT'
    ) !== false,
    'migration 046 continues protecting durable payment identity.'
);

$assert(
    strpos(
        $migration048,
        'DROP FOREIGN KEY fk_billing_attempt_farm'
    ) !== false
    && strpos(
        $migration048,
        'fk_billing_attempt_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE RESTRICT'
    ) !== false,
    'migration 048 replaces destructive farm cascade semantics with RESTRICT.'
);

$assert(
    stripos($migration048, 'DELETE FROM billing_payment_attempts') === false
    && stripos($migration048, 'UPDATE billing_payment_attempts') === false
    && stripos($migration048, 'DELETE FROM billing_provider_events') === false
    && stripos($migration048, 'UPDATE billing_provider_events') === false,
    'migration 048 changes FK semantics without rewriting billing audit rows.'
);

$assert(
    strpos($foundation, 'SELECT table_name, referenced_table_name, delete_rule') !== false
    && strpos($foundation, "['RESTRICT', 'NO ACTION']") !== false,
    'billing foundation readiness now verifies safe payment-attempt farm delete semantics.'
);

$assert(
    substr_count(
        $farms,
        'DELETE FROM farms WHERE id = ? AND slug <> ?'
    ) === 1,
    'farm deletion remains routed through one explicit centralized statement.'
);

$assert(
    strpos($runner048, "'048_billing_tenant_retention_integrity.sql'") !== false,
    'targeted retention runner names migration 048 explicitly.'
);

$assert(
    strpos($runner048, 'run_migrations.php') === false
    && strpos($runner048, '003_multi_tenant_saas.sql') === false,
    'targeted retention runner never invokes historical migration paths.'
);

$assert(
    strpos($runner048, '$beforeAttempts') !== false
    && strpos($runner048, '$afterAttempts') !== false
    && strpos($runner048, '$beforeEvents') !== false
    && strpos($runner048, '$afterEvents') !== false,
    'targeted retention runner proves billing audit row counts are preserved.'
);

$assert(
    strpos($runner048, 'orphaned billing payment attempts') !== false
    && strpos($runner048, "['RESTRICT', 'NO ACTION']") !== false,
    'targeted retention runner fails closed on orphan data and unsafe final FK semantics.'
);

$assert(
    stripos($runner048, 'DELETE FROM billing_payment_attempts') === false
    && stripos($runner048, 'UPDATE billing_payment_attempts') === false
    && stripos($runner048, 'DELETE FROM billing_provider_events') === false
    && stripos($runner048, 'UPDATE billing_provider_events') === false,
    'targeted retention runner does not rewrite billing audit rows.'
);

echo 'Checks: ' . $checks . PHP_EOL;
echo 'Failures: ' . $failures . PHP_EOL;

if ($failures > 0) {
    exit(1);
}

echo 'PASS: V2.3 tenant purge preserves durable billing/payment history.' . PHP_EOL;
