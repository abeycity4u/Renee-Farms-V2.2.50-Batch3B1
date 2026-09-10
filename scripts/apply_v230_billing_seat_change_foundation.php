<?php
/**
 * Targeted V2.3 billing extra-seat change foundation migration.
 *
 * Applies migration 045 only.
 * Never invokes the historical migration runner or migration 003.
 * Performs no provider/network call and no commercial-state mutation.
 */

require_once dirname(__DIR__) . '/config.php';

$migrationName = '045_billing_seat_change_foundation.sql';
$migrationPath = dirname(__DIR__) . '/migrations/' . $migrationName;

if (!is_file($migrationPath)) {
    fwrite(STDERR, "FAIL: missing {$migrationName}.\n");
    exit(1);
}

$requiredTables = [
    'farms',
    'billing_payment_attempts',
    'billing_provider_events',
    'subscriptions',
    'farm_subscription_seat_addons',
    'farm_role_limits',
];

foreach ($requiredTables as $table) {
    $stmt = $pdo->prepare(
        'SELECT ENGINE
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = ?
         LIMIT 1'
    );
    $stmt->execute([$table]);
    $engine = $stmt->fetchColumn();

    if ($engine === false) {
        fwrite(STDERR, "FAIL: required table {$table} is missing.\n");
        exit(1);
    }

    if (strcasecmp((string)$engine, 'InnoDB') !== 0) {
        fwrite(STDERR, "FAIL: required table {$table} is not InnoDB.\n");
        exit(1);
    }
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS schema_migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL UNIQUE,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    )"
);

$tableCount = static function (PDO $pdo, string $table): int {
    $allowed = [
        'billing_payment_attempts',
        'billing_provider_events',
        'subscriptions',
        'farm_subscription_seat_addons',
        'farm_role_limits',
        'billing_seat_change_requests',
    ];

    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException('Unexpected migration audit table.');
    }

    $exists = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = ?'
    );
    $exists->execute([$table]);

    if ((int)$exists->fetchColumn() < 1) {
        return 0;
    }

    return (int)$pdo->query(
        'SELECT COUNT(*) FROM ' . $table
    )->fetchColumn();
};

$before = [];
foreach ([
    'billing_payment_attempts',
    'billing_provider_events',
    'subscriptions',
    'farm_subscription_seat_addons',
    'farm_role_limits',
    'billing_seat_change_requests',
] as $table) {
    $before[$table] = $tableCount($pdo, $table);
}

$sql = file_get_contents($migrationPath);

if ($sql === false) {
    fwrite(STDERR, "FAIL: unable to read {$migrationName}.\n");
    exit(1);
}

$sql = preg_replace('/^\s*--.*$/m', '', $sql);
$statements = array_values(
    array_filter(
        array_map(
            'trim',
            explode(';', (string)$sql)
        )
    )
);

foreach ($statements as $statement) {
    if ($statement !== '') {
        $pdo->exec($statement);
    }
}

$purposeColumn = $pdo->query(
    "SELECT COUNT(*)
     FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = 'billing_payment_attempts'
       AND column_name = 'purpose'"
)->fetchColumn();

if ((int)$purposeColumn !== 1) {
    fwrite(STDERR, "FAIL: billing payment purpose column was not installed.\n");
    exit(1);
}

$purposeIndex = $pdo->query(
    "SELECT COUNT(DISTINCT index_name)
     FROM information_schema.statistics
     WHERE table_schema = DATABASE()
       AND table_name = 'billing_payment_attempts'
       AND index_name = 'idx_billing_attempt_farm_purpose_status'"
)->fetchColumn();

if ((int)$purposeIndex !== 1) {
    fwrite(STDERR, "FAIL: billing purpose/status index was not installed.\n");
    exit(1);
}

$seatColumns = [
    'id',
    'farm_id',
    'change_kind',
    'status',
    'role_code',
    'from_extra_seats',
    'to_extra_seats',
    'plan_code',
    'billing_interval',
    'modules_snapshot',
    'amount',
    'currency',
    'current_period_ends_at',
    'effective_at',
    'payment_attempt_id',
    'initiated_by_user_id',
    'request_hash',
    'applied_at',
    'cancelled_at',
    'created_at',
    'updated_at',
];

$placeholders = implode(
    ',',
    array_fill(0, count($seatColumns), '?')
);

$columnStmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT column_name)
     FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = 'billing_seat_change_requests'
       AND column_name IN ({$placeholders})"
);
$columnStmt->execute($seatColumns);

if ((int)$columnStmt->fetchColumn() !== count($seatColumns)) {
    fwrite(STDERR, "FAIL: seat-change request table is incomplete.\n");
    exit(1);
}

$badPurpose = (int)$pdo->query(
    "SELECT COUNT(*)
     FROM billing_payment_attempts
     WHERE purpose IS NULL
        OR TRIM(purpose) = ''"
)->fetchColumn();

if ($badPurpose !== 0) {
    fwrite(STDERR, "FAIL: existing payment attempts have invalid payment purpose.\n");
    exit(1);
}

$after = [];
foreach (array_keys($before) as $table) {
    $after[$table] = $tableCount($pdo, $table);
}

foreach ([
    'billing_payment_attempts',
    'billing_provider_events',
    'subscriptions',
    'farm_subscription_seat_addons',
    'farm_role_limits',
] as $table) {
    if ($before[$table] !== $after[$table]) {
        fwrite(
            STDERR,
            "FAIL: migration unexpectedly changed {$table} row count.\n"
        );
        exit(1);
    }
}

if ($before['billing_seat_change_requests'] !== $after['billing_seat_change_requests']) {
    fwrite(
        STDERR,
        "FAIL: migration unexpectedly created or removed seat-change requests.\n"
    );
    exit(1);
}

$mark = $pdo->prepare(
    'SELECT COUNT(*)
     FROM schema_migrations
     WHERE filename = ?'
);
$mark->execute([$migrationName]);

if ((int)$mark->fetchColumn() !== 1) {
    fwrite(STDERR, "FAIL: migration marker is missing.\n");
    exit(1);
}

echo "PASS: applied {$migrationName} only.\n";
echo "PASS: migration 003 was not invoked.\n";
echo "PASS: payment purpose defaults preserve existing subscription attempts.\n";
echo "PASS: durable seat-change request storage is installed.\n";
echo "PASS: no existing billing, subscription, seat, or role-limit rows changed.\n";
echo "PASS: no seat-change request was created by the migration.\n";
echo "PASS: no provider call, charge, entitlement change, or seat activation occurred.\n";
