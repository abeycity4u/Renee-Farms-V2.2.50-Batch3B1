<?php
/**
 * V2.3 Stage 2G transactional-integrity repair verifier.
 * Database-free and network-free.
 */

$root = dirname(__DIR__);
$paths = [
    'migration040' => $root . '/migrations/040_subscription_seat_addons.sql',
    'migration044' => $root . '/migrations/044_commercial_application_transactional_integrity.sql',
    'runner' => $root . '/scripts/apply_v230_commercial_application_transactional_integrity.php',
    'application' => $root . '/includes/billing_subscription_application.php',
];
$source = [];
foreach ($paths as $key => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "FAIL: missing {$path}\n");
        exit(1);
    }
    $content = file_get_contents($path);
    if ($content === false) {
        fwrite(STDERR, "FAIL: unable to read {$path}\n");
        exit(1);
    }
    $source[$key] = $content;
}

$checks = 0;
$failures = 0;
$check = static function (bool $ok, string $message) use (&$checks, &$failures): void {
    $checks++;
    if ($ok) {
        echo "PASS: {$message}\n";
        return;
    }
    $failures++;
    echo "FAIL: {$message}\n";
};

$m040 = $source['migration040'];
$m044 = $source['migration044'];
$runner = $source['runner'];
$app = $source['application'];

$check(strpos($m040, ') ENGINE=InnoDB;') !== false,
    'fresh farm_subscription_seat_addons installs explicitly use InnoDB');
$check(strpos($m040, 'fk_farm_subscription_seat_addons_farm') !== false,
    'fresh seat-addon migration still declares its tenant foreign key');

$check(substr_count($m044, 'ENGINE=InnoDB') === 2,
    'migration 044 converts exactly two legacy commercial tables to InnoDB');
$check(strpos($m044, 'ALTER TABLE farm_role_limits ENGINE=InnoDB') !== false,
    'migration 044 converts farm_role_limits to InnoDB');
$check(strpos($m044, 'ALTER TABLE farm_subscription_seat_addons ENGINE=InnoDB') !== false,
    'migration 044 converts farm_subscription_seat_addons to InnoDB');
$check(strpos($m044, 'fk_farm_role_limits_farm') !== false
    && strpos($m044, 'FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE') !== false,
    'migration 044 restores farm_role_limits tenant integrity');
$check(strpos($m044, 'fk_farm_subscription_seat_addons_farm') !== false,
    'migration 044 restores farm_subscription_seat_addons tenant integrity');
$check(strpos($m044, "VALUES ('044_commercial_application_transactional_integrity.sql')") !== false,
    'migration 044 records only its own schema migration identity');
$check(strpos($m044, '003_multi_tenant_saas.sql') === false,
    'migration 044 does not invoke or reference historical migration 003');

$forbiddenCommercialDml = '/\b(?:UPDATE|DELETE\s+FROM|INSERT\s+INTO)\s+(?:farms|farm_modules|farm_role_limits|farm_subscription_seat_addons|subscriptions|billing_payment_attempts|billing_provider_events)\b/i';
$check(!preg_match($forbiddenCommercialDml, $m044),
    'migration 044 performs no tenant commercial-value or billing-row DML');
$check(strpos($m044, 'ALTER TABLE farms') === false
    && strpos($m044, 'ALTER TABLE subscriptions') === false
    && strpos($m044, 'ALTER TABLE billing_payment_attempts') === false,
    'migration 044 leaves already-transactional parent/audit tables untouched');

$check(strpos($runner, '$migrationName = \'044_commercial_application_transactional_integrity.sql\';') !== false,
    'targeted runner names migration 044 explicitly');
$check(strpos($runner, 'migration 003') !== false
    && strpos($runner, 'migrations/003') === false,
    'targeted runner documents but never loads migration 003');
$check(strpos($runner, 'LEFT JOIN farms f ON f.id = child.farm_id') !== false,
    'targeted runner refuses orphan tenant rows before conversion');
$check(strpos($runner, '$beforeRoleLimits') !== false
    && strpos($runner, '$beforeSeatAddons') !== false
    && strpos($runner, '$afterRoleLimits') !== false
    && strpos($runner, '$afterSeatAddons') !== false,
    'targeted runner captures before/after row counts for both repaired tables');
$check(strpos($runner, 'migration 044 unexpectedly changed commercial row counts') !== false,
    'targeted runner fails closed if migration 044 changes either row count');
$check(strpos($runner, "['farms', 'subscriptions', 'billing_payment_attempts']") !== false,
    'targeted runner requires the existing parent/audit tables to remain InnoDB before repair');
$check(strpos($runner, 'fk_farm_role_limits_farm') !== false
    && strpos($runner, 'fk_farm_subscription_seat_addons_farm') !== false,
    'targeted runner verifies both restored foreign keys');
$check(strpos($runner, 'billing_subscription_application_ready($pdo)') !== false,
    'targeted runner verifies Stage 2G application readiness after repair');
$check(strpos($runner, 'DELETE FROM') === false
    && strpos($runner, 'UPDATE farms') === false
    && strpos($runner, 'UPDATE farm_modules') === false
    && strpos($runner, 'UPDATE subscriptions') === false,
    'targeted runner contains no destructive tenant/commercial cleanup path');
$check(strpos($runner, 'curl_') === false,
    'transactional repair makes no provider or network call');
$check(strpos($app, "'farm_role_limits',") !== false
    && strpos($app, "'farm_subscription_seat_addons',") !== false
    && strpos($app, 'strcasecmp($engine, \'InnoDB\')') !== false,
    'Stage 2G application continues to fail closed unless repaired tables are transactional');

echo "\n{$checks} checks, {$failures} failure(s).\n";
if ($failures > 0) {
    fwrite(STDERR, "FAIL: V2.3 Stage 2G transactional-integrity repair contract is not closed.\n");
    exit(1);
}

echo "PASS: V2.3 Stage 2G transactional repair is two-table-scoped, orphan-safe, row-preserving and migration-003-independent.\n";
