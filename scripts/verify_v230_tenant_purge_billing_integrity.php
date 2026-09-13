<?php
/**
 * Static contract verifier for tenant purge compatibility with durable
 * V2.3 billing/payment and commercial subscription history.
 */

$root = dirname(__DIR__);

$farmsPath = $root . '/management/farms.php';
$foundationPath = $root . '/includes/billing_payment_foundation.php';
$migration046Path = $root . '/migrations/046_billing_seat_quote_snapshot.sql';
$migration048Path = $root . '/migrations/048_billing_tenant_retention_integrity.sql';
$runner048Path = $root . '/scripts/apply_v230_billing_tenant_retention_integrity.php';
$migration049Path = $root . '/migrations/049_subscription_history_retention_integrity.sql';
$runner049Path = $root . '/scripts/apply_v230_subscription_history_retention_integrity.php';

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
$assert(is_file($migration049Path), 'migration 049 exists.');
$assert(is_file($runner049Path), 'targeted migration 049 runner exists.');

$farms = is_file($farmsPath) ? file_get_contents($farmsPath) : '';
$foundation = is_file($foundationPath) ? file_get_contents($foundationPath) : '';
$migration046 = is_file($migration046Path) ? file_get_contents($migration046Path) : '';
$migration048 = is_file($migration048Path) ? file_get_contents($migration048Path) : '';
$runner048 = is_file($runner048Path) ? file_get_contents($runner048Path) : '';
$migration049 = is_file($migration049Path) ? file_get_contents($migration049Path) : '';
$runner049 = is_file($runner049Path) ? file_get_contents($runner049Path) : '';

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
    'farm deletion retains the centralized billing-history detector.'
);

$assert(
    strpos($farms, 'function farmHasSubscriptionHistory(') !== false
    && strpos(
        $farms,
        'SELECT 1 FROM subscriptions WHERE farm_id = ? LIMIT 1'
    ) !== false
    && strpos(
        $farms,
        'function farmHasProtectedCommercialHistory('
    ) !== false,
    'farm deletion also detects immutable subscription history.'
);

$historyGuardPos = strpos(
    $purge,
    'farmHasProtectedCommercialHistory($pdo, $farmId)'
);
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
        'farmHasProtectedCommercialHistory($pdo, $farmId)'
    ) >= 2,
    'manual farm deletion and centralized purge both enforce commercial-history retention.'
);

$assert(
    strpos(
        $farms,
        'Suspend the farm instead to preserve the commercial audit trail.'
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
    $subscriptionPos === false
    && stripos($purge, 'DELETE FROM subscriptions') === false,
    'tenant purge never explicitly deletes immutable subscription history.'
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

$retainedFkAdd = strpos(
    $migration048,
    'ALTER TABLE billing_payment_attempts ADD CONSTRAINT fk_billing_attempt_farm_restrict FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE RESTRICT'
);
$legacyFkDrop = strpos(
    $migration048,
    'ALTER TABLE billing_payment_attempts DROP FOREIGN KEY fk_billing_attempt_farm'
);

$assert(
    $retainedFkAdd !== false,
    'migration 048 establishes a new permanent RESTRICT farm foreign key.'
);

$assert(
    $legacyFkDrop !== false,
    'migration 048 removes the legacy farm foreign key only after replacement protection is available.'
);

$assert(
    $retainedFkAdd !== false
    && $legacyFkDrop !== false
    && $retainedFkAdd < $legacyFkDrop,
    'migration 048 establishes RESTRICT protection before dropping the legacy CASCADE key.'
);

$assert(
    strpos(
        $migration048,
        'constraint_name = \'fk_billing_attempt_farm_restrict\''
    ) !== false
    && strpos(
        $migration048,
        "UPPER(delete_rule) IN ('RESTRICT', 'NO ACTION')"
    ) !== false,
    'legacy FK removal is gated on the retained safe farm relationship.'
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
    && strpos($foundation, "'fk_billing_attempt_farm_restrict' => [") !== false
    && strpos($foundation, "['RESTRICT', 'NO ACTION']") !== false,
    'billing foundation readiness requires the retained safe payment-attempt farm foreign key.'
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
    && strpos($runner048, "'fk_billing_attempt_farm_restrict'") !== false
    && strpos($runner048, 'legacy farm foreign key in place') !== false
    && strpos($runner048, "['RESTRICT', 'NO ACTION']") !== false,
    'targeted retention runner fails closed on orphan data, missing retained protection, and legacy FK residue.'
);

$assert(
    stripos($runner048, 'DELETE FROM billing_payment_attempts') === false
    && stripos($runner048, 'UPDATE billing_payment_attempts') === false
    && stripos($runner048, 'DELETE FROM billing_provider_events') === false
    && stripos($runner048, 'UPDATE billing_provider_events') === false,
    'targeted retention runner does not rewrite billing audit rows.'
);


$subscriptionRetainedFkAdd = strpos(
    $migration049,
    'ALTER TABLE subscriptions ADD CONSTRAINT fk_subscription_farm_restrict FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE RESTRICT'
);
$subscriptionLegacyFkDrop = strpos(
    $migration049,
    'ALTER TABLE subscriptions DROP FOREIGN KEY fk_subscription_farm'
);

$assert(
    $subscriptionRetainedFkAdd !== false,
    'migration 049 establishes a permanent RESTRICT subscription-history farm foreign key.'
);

$assert(
    $subscriptionLegacyFkDrop !== false,
    'migration 049 removes the legacy subscription-history farm foreign key.'
);

$assert(
    $subscriptionRetainedFkAdd !== false
    && $subscriptionLegacyFkDrop !== false
    && $subscriptionRetainedFkAdd < $subscriptionLegacyFkDrop,
    'migration 049 establishes RESTRICT protection before dropping the legacy CASCADE key.'
);

$assert(
    strpos(
        $migration049,
        "constraint_name = 'fk_subscription_farm_restrict'"
    ) !== false
    && strpos(
        $migration049,
        "UPPER(delete_rule) IN ('RESTRICT', 'NO ACTION')"
    ) !== false,
    'legacy subscription FK removal is gated on retained safe protection.'
);

$assert(
    stripos($migration049, 'DELETE FROM subscriptions') === false
    && stripos($migration049, 'UPDATE subscriptions') === false
    && stripos($migration049, 'INSERT INTO subscriptions') === false,
    'migration 049 changes FK semantics without rewriting subscription-history rows.'
);

$assert(
    strpos(
        $runner049,
        "'049_subscription_history_retention_integrity.sql'"
    ) !== false,
    'targeted subscription-history retention runner names migration 049 explicitly.'
);

$assert(
    strpos($runner049, 'run_migrations.php') === false
    && strpos($runner049, '003_multi_tenant_saas.sql') === false
    && strpos($runner049, '048_billing_tenant_retention_integrity.sql') === false,
    'targeted migration 049 runner never invokes historical migration paths.'
);

$assert(
    strpos($runner049, '$beforeSubscriptions') !== false
    && strpos($runner049, '$afterSubscriptions') !== false
    && strpos($runner049, '$beforeAttempts') !== false
    && strpos($runner049, '$afterAttempts') !== false
    && strpos($runner049, '$beforeEvents') !== false
    && strpos($runner049, '$afterEvents') !== false,
    'targeted migration 049 runner proves commercial audit row counts are preserved.'
);

$assert(
    strpos($runner049, 'orphaned subscription history') !== false
    || strpos($runner049, 'orphaned subscription-history') !== false,
    'targeted migration 049 runner fails closed on orphan subscription history.'
);

$assert(
    strpos($runner049, "'fk_subscription_farm_restrict'") !== false
    && strpos($runner049, "'fk_subscription_farm'") !== false
    && strpos($runner049, "['RESTRICT', 'NO ACTION']") !== false,
    'targeted migration 049 runner verifies retained protection and legacy FK removal.'
);

$assert(
    stripos($runner049, 'DELETE FROM subscriptions') === false
    && stripos($runner049, 'UPDATE subscriptions') === false
    && stripos($runner049, 'INSERT INTO subscriptions') === false,
    'targeted migration 049 runner does not rewrite subscription-history rows.'
);

echo 'Checks: ' . $checks . PHP_EOL;
echo 'Failures: ' . $failures . PHP_EOL;

if ($failures > 0) {
    exit(1);
}

echo 'PASS: V2.3 tenant purge preserves durable billing/payment and commercial subscription history.' . PHP_EOL;
