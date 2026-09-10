<?php
/**
 * Targeted V2.3 billing seat quote-snapshot migration runner.
 *
 * --preflight is read-only.
 * --apply is the only mode that executes migration 046.
 *
 * Never invokes the historical migration runner or migration 003.
 * Performs no provider call, payment creation, entitlement mutation,
 * subscription mutation, or seat activation.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "FAIL: CLI execution only.\n");
    exit(1);
}

$mode = $argv[1] ?? '';

if (!in_array($mode, ['--preflight', '--apply'], true)) {
    fwrite(
        STDERR,
        "Usage: php scripts/apply_v230_billing_seat_quote_snapshot.php --preflight|--apply\n"
    );
    exit(1);
}

$bootstrapMode = trim(
    (string)(
        getenv('RENEE_MIGRATION_DB_BOOTSTRAP')
        ?: 'config'
    )
);

if (!in_array(
    $bootstrapMode,
    ['config', 'env'],
    true
)) {
    fwrite(
        STDERR,
        "FAIL: unsupported migration database bootstrap mode.\n"
    );
    exit(1);
}

if ($bootstrapMode === 'env') {
    $requiredDbEnv = [
        'DB_HOST',
        'DB_USER',
        'DB_PASS',
        'DB_NAME',
    ];

    foreach ($requiredDbEnv as $name) {
        $value = getenv($name);

        if ($value === false || $value === '') {
            fwrite(
                STDERR,
                "FAIL: required migration database environment is incomplete.\n"
            );
            exit(1);
        }
    }

    try {
        $pdo = new PDO(
            'mysql:host='
            . getenv('DB_HOST')
            . ';dbname='
            . getenv('DB_NAME'),
            getenv('DB_USER'),
            getenv('DB_PASS')
        );

        $pdo->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );
    } catch (Throwable $e) {
        fwrite(
            STDERR,
            "FAIL: migration database environment connection failed.\n"
        );
        exit(1);
    }
} else {
    $bootstrapOverride = trim(
        (string)(getenv('RENEE_MIGRATION_CONFIG') ?: '')
    );

    $configPath = $bootstrapOverride !== ''
        ? $bootstrapOverride
        : dirname(__DIR__) . '/config.php';

    if (!is_file($configPath)
        || !is_readable($configPath)) {
        fwrite(
            STDERR,
            "FAIL: migration database bootstrap is unavailable.\n"
        );
        exit(1);
    }

    if (empty($_SERVER['DOCUMENT_ROOT'])) {
        $_SERVER['DOCUMENT_ROOT'] =
            dirname($configPath);
    }

    require_once $configPath;

    if (!isset($pdo)
        || !($pdo instanceof PDO)) {
        fwrite(
            STDERR,
            "FAIL: migration database bootstrap did not provide PDO.\n"
        );
        exit(1);
    }
}


$migrationName =
    '046_billing_seat_quote_snapshot.sql';

$migrationPath =
    dirname(__DIR__)
    . '/migrations/'
    . $migrationName;

if (!is_file($migrationPath)) {
    fwrite(
        STDERR,
        "FAIL: missing {$migrationName}.\n"
    );
    exit(1);
}

function v230_046_fail(string $message): void
{
    fwrite(
        STDERR,
        'FAIL: ' . $message . PHP_EOL
    );
    exit(1);
}

function v230_046_table_engine(
    PDO $pdo,
    string $table
): ?string {
    $stmt = $pdo->prepare(
        'SELECT ENGINE
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = ?
         LIMIT 1'
    );

    $stmt->execute([$table]);

    $engine = $stmt->fetchColumn();

    return $engine === false
        ? null
        : (string)$engine;
}

function v230_046_table_count(
    PDO $pdo,
    string $table
): int {
    $allowed = [
        'billing_payment_attempts',
        'billing_provider_events',
        'subscriptions',
        'farm_subscription_seat_addons',
        'farm_role_limits',
        'billing_seat_change_requests',
    ];

    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException(
            'Unexpected migration audit table.'
        );
    }

    return (int)$pdo->query(
        'SELECT COUNT(*) FROM ' . $table
    )->fetchColumn();
}

function v230_046_marker_exists(
    PDO $pdo,
    string $filename
): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM schema_migrations
         WHERE filename = ?'
    );

    $stmt->execute([$filename]);

    return (int)$stmt->fetchColumn() === 1;
}

function v230_046_fk(
    PDO $pdo,
    string $name
): ?array {
    $stmt = $pdo->prepare(
        'SELECT
             table_name,
             referenced_table_name,
             delete_rule
         FROM information_schema.referential_constraints
         WHERE constraint_schema = DATABASE()
           AND constraint_name = ?
         LIMIT 1'
    );

    $stmt->execute([$name]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function v230_046_column(
    PDO $pdo,
    string $column
): ?array {
    $stmt = $pdo->prepare(
        'SELECT
             column_name,
             data_type,
             column_type,
             is_nullable,
             character_maximum_length,
             numeric_precision,
             numeric_scale
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND column_name = ?
         LIMIT 1'
    );

    $stmt->execute([
        'billing_seat_change_requests',
        $column,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}


function v230_046_index_exists(
    PDO $pdo,
    string $name
): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(DISTINCT index_name)
         FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND index_name = ?'
    );

    $stmt->execute([
        'billing_seat_change_requests',
        $name,
    ]);

    return (int)$stmt->fetchColumn() === 1;
}

function v230_046_parent_id_column(
    PDO $pdo,
    string $table
): ?array {
    $allowed = [
        'subscriptions',
        'billing_payment_attempts',
    ];

    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException(
            'Unexpected migration parent table.'
        );
    }

    $stmt = $pdo->prepare(
        'SELECT
             data_type,
             column_type,
             is_nullable
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND column_name = ?
         LIMIT 1'
    );

    $stmt->execute([
        $table,
        'id',
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}


function v230_046_snapshot_column_matches(
    PDO $pdo,
    string $column
): bool {
    $row = v230_046_column(
        $pdo,
        $column
    );

    if ($row === null
        || strtoupper(
            (string)$row['is_nullable']
        ) !== 'YES') {
        return false;
    }

    $dataType = strtolower(
        (string)$row['data_type']
    );

    $columnType = strtolower(
        (string)$row['column_type']
    );

    if (in_array(
        $column,
        [
            'quoted_at',
            'lineage_start_at',
            'segment_start_at',
            'segment_end_at',
        ],
        true
    )) {
        return $dataType === 'datetime';
    }

    if ($column === 'pricing_version') {
        return $dataType === 'varchar'
            && (int)$row[
                'character_maximum_length'
            ] === 80;
    }

    if ($column === 'pricing_hash') {
        return $dataType === 'char'
            && (int)$row[
                'character_maximum_length'
            ] === 64;
    }

    if (in_array(
        $column,
        [
            'unit_amount',
            'partial_unit_amount',
            'per_seat_amount',
        ],
        true
    )) {
        return $dataType === 'decimal'
            && (int)$row['numeric_precision'] === 12
            && (int)$row['numeric_scale'] === 2;
    }

    if ($column === 'future_full_periods') {
        return $dataType === 'int'
            && strpos(
                $columnType,
                'unsigned'
            ) !== false;
    }

    if ($column === 'latest_paid_subscription_id') {
        return $dataType === 'int'
            && strpos(
                $columnType,
                'unsigned'
            ) === false;
    }

    if ($column === 'latest_paid_attempt_id') {
        return $dataType === 'bigint'
            && strpos(
                $columnType,
                'unsigned'
            ) !== false;
    }

    return false;
}

$requiredTables = [
    'schema_migrations',
    'billing_payment_attempts',
    'billing_provider_events',
    'subscriptions',
    'farm_subscription_seat_addons',
    'farm_role_limits',
    'billing_seat_change_requests',
];

foreach ($requiredTables as $table) {
    $engine = v230_046_table_engine(
        $pdo,
        $table
    );

    if ($engine === null) {
        v230_046_fail(
            "required table {$table} is missing."
        );
    }

    if (strcasecmp($engine, 'InnoDB') !== 0) {
        v230_046_fail(
            "required table {$table} is not InnoDB."
        );
    }
}

if (!v230_046_marker_exists(
    $pdo,
    '045_billing_seat_change_foundation.sql'
)) {
    v230_046_fail(
        'migration 045 must be applied before migration 046.'
    );
}

$baseColumns = [
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

foreach ($baseColumns as $column) {
    if (v230_046_column(
        $pdo,
        $column
    ) === null) {
        v230_046_fail(
            "migration 045 base column {$column} is missing."
        );
    }
}

$paymentFk = v230_046_fk(
    $pdo,
    'fk_billing_seat_change_payment_attempt'
);

if (!$paymentFk
    || (string)$paymentFk['table_name']
        !== 'billing_seat_change_requests'
    || (string)$paymentFk['referenced_table_name']
        !== 'billing_payment_attempts') {
    v230_046_fail(
        'migration 045 payment-attempt foreign key is missing or invalid.'
    );
}

$snapshotColumns = [
    'quoted_at',
    'lineage_start_at',
    'segment_start_at',
    'segment_end_at',
    'pricing_version',
    'pricing_hash',
    'unit_amount',
    'partial_unit_amount',
    'future_full_periods',
    'per_seat_amount',
    'latest_paid_subscription_id',
    'latest_paid_attempt_id',
];

$protectedTables = [
    'billing_payment_attempts',
    'billing_provider_events',
    'subscriptions',
    'farm_subscription_seat_addons',
    'farm_role_limits',
    'billing_seat_change_requests',
];

$before = [];

foreach ($protectedTables as $table) {
    $before[$table] =
        v230_046_table_count(
            $pdo,
            $table
        );
}

$alreadyApplied =
    v230_046_marker_exists(
        $pdo,
        $migrationName
    );

$preflightSchemaState = 'UNAVAILABLE';

if (!$alreadyApplied) {
    if ($before['billing_seat_change_requests'] !== 0) {
        v230_046_fail(
            'seat-change request storage is not empty before migration 046.'
        );
    }

    if (strtoupper(
        (string)$paymentFk['delete_rule']
    ) !== 'SET NULL') {
        v230_046_fail(
            'migration 045 payment-attempt foreign key must begin with ON DELETE SET NULL.'
        );
    }

    $subscriptionId =
        v230_046_parent_id_column(
            $pdo,
            'subscriptions'
        );

    if (!$subscriptionId
        || strtolower(
            (string)$subscriptionId['data_type']
        ) !== 'int'
        || stripos(
            (string)$subscriptionId['column_type'],
            'unsigned'
        ) !== false) {
        v230_046_fail(
            'subscriptions.id must be signed INT before migration 046.'
        );
    }

    $paymentAttemptId =
        v230_046_parent_id_column(
            $pdo,
            'billing_payment_attempts'
        );

    if (!$paymentAttemptId
        || strtolower(
            (string)$paymentAttemptId['data_type']
        ) !== 'bigint'
        || stripos(
            (string)$paymentAttemptId['column_type'],
            'unsigned'
        ) === false) {
        v230_046_fail(
            'billing_payment_attempts.id must be unsigned BIGINT before migration 046.'
        );
    }

    $presentSnapshotColumns = [];

    foreach ($snapshotColumns as $column) {
        if (v230_046_column(
            $pdo,
            $column
        ) !== null) {
            $presentSnapshotColumns[] =
                $column;
        }
    }

    $presentSnapshotCount =
        count($presentSnapshotColumns);

    if ($presentSnapshotCount === 0) {
        $preflightSchemaState =
            'CLEAN';
    } elseif (
        $presentSnapshotCount
        === count($snapshotColumns)
    ) {
        foreach ($snapshotColumns as $column) {
            if (!v230_046_snapshot_column_matches(
                $pdo,
                $column
            )) {
                v230_046_fail(
                    'migration 046 recovery column has unexpected definition: '
                    . $column
                    . '.'
                );
            }
        }

        $preflightSchemaState =
            'RECOVERABLE_COLUMNS_ONLY';
    } else {
        v230_046_fail(
            'migration 046 has an unsupported mixed snapshot-column state.'
        );
    }

    foreach ([
        'fk_billing_seat_change_latest_subscription',
        'fk_billing_seat_change_latest_attempt',
    ] as $fkName) {
        if (v230_046_fk(
            $pdo,
            $fkName
        ) !== null) {
            v230_046_fail(
                'migration 046 partial schema artifact exists: foreign key '
                . $fkName
                . '.'
            );
        }
    }

    foreach ([
        'idx_billing_seat_change_latest_paid_subscription',
        'idx_billing_seat_change_latest_paid_attempt',
    ] as $indexName) {
        if (v230_046_index_exists(
            $pdo,
            $indexName
        )) {
            v230_046_fail(
                'migration 046 partial schema artifact exists: index '
                . $indexName
                . '.'
            );
        }
    }
}

echo "MODE: {$mode}\n";
echo 'MIGRATION: ' . $migrationName . PHP_EOL;
echo 'MIGRATION_SHA256: '
    . hash_file('sha256', $migrationPath)
    . PHP_EOL;
echo '045_MARKER: PRESENT' . PHP_EOL;
echo '046_MARKER: '
    . ($alreadyApplied ? 'PRESENT' : 'ABSENT')
    . PHP_EOL;
echo 'CURRENT_PAYMENT_FK_DELETE_RULE: '
    . strtoupper(
        (string)$paymentFk['delete_rule']
    )
    . PHP_EOL;
echo 'PREFLIGHT_SCHEMA_STATE: '
    . $preflightSchemaState
    . PHP_EOL;

foreach ($before as $table => $count) {
    echo 'ROWS '
        . $table
        . ': '
        . $count
        . PHP_EOL;
}

if ($alreadyApplied) {
    v230_046_fail(
        'migration 046 is already marked as applied; refusing a second apply.'
    );
}

if ($mode === '--preflight') {
    echo "PASS: migration 046 preflight state is approved and read-only.\n";
    exit(0);
}

$sql = file_get_contents(
    $migrationPath
);

if ($sql === false) {
    v230_046_fail(
        "unable to read {$migrationName}."
    );
}

$sql = preg_replace(
    '/^\s*--.*$/m',
    '',
    $sql
);

$statements = array_values(
    array_filter(
        array_map(
            'trim',
            explode(';', (string)$sql)
        )
    )
);

try {
    foreach ($statements as $statement) {
        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }
} catch (Throwable $e) {
    fwrite(
        STDERR,
        "FAIL: migration 046 execution stopped: "
        . $e->getMessage()
        . PHP_EOL
    );

    fwrite(
        STDERR,
        "WARNING: MySQL DDL may have partially committed. "
        . "Do not improvise rollback; inspect schema state before retrying."
        . PHP_EOL
    );

    exit(1);
}

$columns = [];

foreach ($snapshotColumns as $column) {
    $row = v230_046_column(
        $pdo,
        $column
    );

    if ($row === null) {
        v230_046_fail(
            "snapshot column {$column} was not installed."
        );
    }

    if (strtoupper(
        (string)$row['is_nullable']
    ) !== 'YES') {
        v230_046_fail(
            "snapshot column {$column} must remain nullable."
        );
    }

    $columns[$column] = $row;
}

foreach ([
    'quoted_at',
    'lineage_start_at',
    'segment_start_at',
    'segment_end_at',
] as $column) {
    if (strtolower(
        (string)$columns[$column]['data_type']
    ) !== 'datetime') {
        v230_046_fail(
            "{$column} must be DATETIME."
        );
    }
}

if (strtolower(
    (string)$columns['pricing_version']['data_type']
) !== 'varchar'
    || (int)$columns['pricing_version']
        ['character_maximum_length'] !== 80) {
    v230_046_fail(
        'pricing_version must be VARCHAR(80).'
    );
}

if (strtolower(
    (string)$columns['pricing_hash']['data_type']
) !== 'char'
    || (int)$columns['pricing_hash']
        ['character_maximum_length'] !== 64) {
    v230_046_fail(
        'pricing_hash must be CHAR(64).'
    );
}

foreach ([
    'unit_amount',
    'partial_unit_amount',
    'per_seat_amount',
] as $column) {
    $row = $columns[$column];

    if (strtolower(
        (string)$row['data_type']
    ) !== 'decimal'
        || (int)$row['numeric_precision'] !== 12
        || (int)$row['numeric_scale'] !== 2) {
        v230_046_fail(
            "{$column} must be DECIMAL(12,2)."
        );
    }
}

if (strtolower(
    (string)$columns['future_full_periods']['data_type']
) !== 'int'
    || stripos(
        (string)$columns['future_full_periods']['column_type'],
        'unsigned'
    ) === false) {
    v230_046_fail(
        'future_full_periods must be unsigned INT.'
    );
}

if (strtolower(
    (string)$columns['latest_paid_subscription_id']['data_type']
) !== 'int'
    || stripos(
        (string)$columns['latest_paid_subscription_id']['column_type'],
        'unsigned'
    ) !== false) {
    v230_046_fail(
        'latest_paid_subscription_id must be signed INT.'
    );
}

if (strtolower(
    (string)$columns['latest_paid_attempt_id']['data_type']
) !== 'bigint'
    || stripos(
        (string)$columns['latest_paid_attempt_id']['column_type'],
        'unsigned'
    ) === false) {
    v230_046_fail(
        'latest_paid_attempt_id must be unsigned BIGINT.'
    );
}

$expectedFks = [
    'fk_billing_seat_change_payment_attempt' =>
        'billing_payment_attempts',
    'fk_billing_seat_change_latest_subscription' =>
        'subscriptions',
    'fk_billing_seat_change_latest_attempt' =>
        'billing_payment_attempts',
];

foreach ($expectedFks as $name => $parent) {
    $fk = v230_046_fk(
        $pdo,
        $name
    );

    if (!$fk
        || (string)$fk['table_name']
            !== 'billing_seat_change_requests'
        || (string)$fk['referenced_table_name']
            !== $parent
        || strtoupper(
            (string)$fk['delete_rule']
        ) !== 'RESTRICT') {
        v230_046_fail(
            "restrictive foreign key {$name} is invalid."
        );
    }
}

foreach ([
    'idx_billing_seat_change_latest_paid_subscription',
    'idx_billing_seat_change_latest_paid_attempt',
] as $index) {
    $stmt = $pdo->prepare(
        'SELECT COUNT(DISTINCT index_name)
         FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND index_name = ?'
    );

    $stmt->execute([
        'billing_seat_change_requests',
        $index,
    ]);

    if ((int)$stmt->fetchColumn() !== 1) {
        v230_046_fail(
            "required index {$index} is missing."
        );
    }
}

if (!v230_046_marker_exists(
    $pdo,
    $migrationName
)) {
    v230_046_fail(
        'migration 046 marker is missing after apply.'
    );
}

$after = [];

foreach ($protectedTables as $table) {
    $after[$table] =
        v230_046_table_count(
            $pdo,
            $table
        );

    if ($after[$table] !== $before[$table]) {
        v230_046_fail(
            "migration unexpectedly changed {$table} row count."
        );
    }
}

echo "PASS: applied migration 046 only.\n";
echo "PASS: migration 045 dependency remains satisfied.\n";
echo "PASS: authoritative quote snapshot schema is complete.\n";
echo "PASS: paid-lineage foreign keys are restrictive.\n";
echo "PASS: supporting lineage indexes are installed.\n";
echo "PASS: protected billing and seat-change row counts are unchanged.\n";
echo "PASS: no payment, provider, subscription, entitlement, or seat activation occurred.\n";
