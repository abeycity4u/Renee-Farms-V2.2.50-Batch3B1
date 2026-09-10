<?php
/**
 * Static contract verifier for tenant purge compatibility with the
 * V2.3 billing seat-change foreign-key model.
 */

$root = dirname(__DIR__);

$farmsPath = $root . '/management/farms.php';
$migrationPath = $root . '/migrations/046_billing_seat_quote_snapshot.sql';

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
$assert(is_file($migrationPath), 'migration 046 exists.');

$farms = is_file($farmsPath) ? file_get_contents($farmsPath) : '';
$migration = is_file($migrationPath) ? file_get_contents($migrationPath) : '';

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
    'seat-change requests are deleted before subscriptions.'
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
        $migration,
        'fk_billing_seat_change_payment_attempt'
    ) !== false
    && strpos(
        $migration,
        'ON DELETE RESTRICT'
    ) !== false,
    'migration 046 protects durable payment identity with RESTRICT.'
);

$assert(
    strpos(
        $migration,
        'fk_billing_seat_change_latest_subscription'
    ) !== false,
    'migration 046 retains authoritative paid-subscription lineage.'
);

$assert(
    strpos(
        $migration,
        'fk_billing_seat_change_latest_attempt'
    ) !== false,
    'migration 046 retains authoritative paid-attempt lineage.'
);

$assert(
    substr_count(
        $farms,
        'DELETE FROM farms WHERE id = ? AND slug <> ?'
    ) === 1,
    'farm deletion remains routed through one explicit centralized statement.'
);

echo 'Checks: ' . $checks . PHP_EOL;
echo 'Failures: ' . $failures . PHP_EOL;

if ($failures > 0) {
    exit(1);
}

echo 'PASS: V2.3 tenant purge remains compatible with durable billing seat-change lineage.' . PHP_EOL;
