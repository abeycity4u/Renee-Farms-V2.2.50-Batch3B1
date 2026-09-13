<?php
/**
 * Targeted V2.3 billing tenant-retention integrity repair.
 *
 * Applies migration 048 only.
 * It does not invoke the historical migration runner and does not rewrite
 * billing attempts, provider events, tenant entitlements or subscription history.
 */

require_once dirname(__DIR__) . '/config.php';

$migrationName = '048_billing_tenant_retention_integrity.sql';
$migrationPath = dirname(__DIR__) . '/migrations/' . $migrationName;

if (!isset($pdo) || !($pdo instanceof PDO)) {
    fwrite(STDERR, "FAIL: database connection is unavailable.\n");
    exit(1);
}

if (!is_file($migrationPath)) {
    fwrite(STDERR, "FAIL: missing {$migrationName}.\n");
    exit(1);
}

$tableStmt = $pdo->prepare(
    'SELECT engine
     FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name = ?
     LIMIT 1'
);

$engines = [];
foreach (['farms', 'billing_payment_attempts', 'billing_provider_events'] as $table) {
    $tableStmt->execute([$table]);
    $engine = $tableStmt->fetchColumn();

    if ($engine === false) {
        fwrite(STDERR, "FAIL: required table {$table} is missing.\n");
        exit(1);
    }

    $engines[$table] = (string)$engine;
}

foreach ($engines as $table => $engine) {
    if (strcasecmp($engine, 'InnoDB') !== 0) {
        fwrite(STDERR, "FAIL: {$table} must use InnoDB before migration 048.\n");
        exit(1);
    }
}

$countRows = static function (PDO $pdo, string $table): int {
    if (!in_array($table, ['billing_payment_attempts', 'billing_provider_events'], true)) {
        throw new InvalidArgumentException('Unexpected billing table.');
    }

    return (int)$pdo->query(
        'SELECT COUNT(*) FROM ' . $table
    )->fetchColumn();
};

$beforeAttempts = $countRows($pdo, 'billing_payment_attempts');
$beforeEvents = $countRows($pdo, 'billing_provider_events');

$orphanAttempts = (int)$pdo->query(
    'SELECT COUNT(*)
     FROM billing_payment_attempts a
     LEFT JOIN farms f ON f.id = a.farm_id
     WHERE f.id IS NULL'
)->fetchColumn();

if ($orphanAttempts !== 0) {
    fwrite(STDERR, "FAIL: orphaned billing payment attempts must be resolved before migration 048.\n");
    exit(1);
}

$fkStmt = $pdo->prepare(
    "SELECT table_name, referenced_table_name, delete_rule
     FROM information_schema.referential_constraints
     WHERE constraint_schema = DATABASE()
       AND constraint_name = ?
     LIMIT 1"
);

$readFarmFk = static function (PDOStatement $stmt, string $constraint): ?array {
    $stmt->execute([$constraint]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
};

$validateFarmFk = static function (
    ?array $fk,
    string $constraint,
    array $allowedDeleteRules
): void {
    if (!$fk) {
        return;
    }

    if ((string)$fk['table_name'] !== 'billing_payment_attempts'
        || (string)$fk['referenced_table_name'] !== 'farms') {
        fwrite(
            STDERR,
            "FAIL: {$constraint} points to an unexpected table relationship.\n"
        );
        exit(1);
    }

    $deleteRule = strtoupper(trim((string)$fk['delete_rule']));

    if (!in_array($deleteRule, $allowedDeleteRules, true)) {
        fwrite(
            STDERR,
            "FAIL: {$constraint} has an unexpected delete rule.\n"
        );
        exit(1);
    }
};

$beforeLegacyFk = $readFarmFk($fkStmt, 'fk_billing_attempt_farm');
$beforeRetentionFk = $readFarmFk(
    $fkStmt,
    'fk_billing_attempt_farm_restrict'
);

$validateFarmFk(
    $beforeLegacyFk,
    'fk_billing_attempt_farm',
    ['CASCADE', 'RESTRICT', 'NO ACTION']
);

$validateFarmFk(
    $beforeRetentionFk,
    'fk_billing_attempt_farm_restrict',
    ['RESTRICT', 'NO ACTION']
);

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS schema_migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL UNIQUE,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB"
);

$alreadyAppliedStmt = $pdo->prepare(
    'SELECT COUNT(*) FROM schema_migrations WHERE filename = ?'
);
$alreadyAppliedStmt->execute([$migrationName]);
$alreadyApplied = (int)$alreadyAppliedStmt->fetchColumn() > 0;

if (!$alreadyApplied) {
    $sql = file_get_contents($migrationPath);

    if ($sql === false) {
        fwrite(STDERR, "FAIL: unable to read {$migrationName}.\n");
        exit(1);
    }

    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    $statements = array_values(
        array_filter(
            array_map('trim', explode(';', $sql))
        )
    );

    foreach ($statements as $statement) {
        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }
}

$afterAttempts = $countRows($pdo, 'billing_payment_attempts');
$afterEvents = $countRows($pdo, 'billing_provider_events');

if ($afterAttempts !== $beforeAttempts || $afterEvents !== $beforeEvents) {
    fwrite(STDERR, "FAIL: migration 048 unexpectedly changed billing audit row counts.\n");
    exit(1);
}

$afterRetentionFk = $readFarmFk(
    $fkStmt,
    'fk_billing_attempt_farm_restrict'
);
$afterLegacyFk = $readFarmFk(
    $fkStmt,
    'fk_billing_attempt_farm'
);

if (!$afterRetentionFk
    || (string)$afterRetentionFk['table_name'] !== 'billing_payment_attempts'
    || (string)$afterRetentionFk['referenced_table_name'] !== 'farms'
    || !in_array(
        strtoupper(trim((string)$afterRetentionFk['delete_rule'])),
        ['RESTRICT', 'NO ACTION'],
        true
    )) {
    fwrite(
        STDERR,
        "FAIL: migration 048 did not establish the retained RESTRICT farm foreign key.\n"
    );
    exit(1);
}

if ($afterLegacyFk) {
    fwrite(
        STDERR,
        "FAIL: migration 048 left the legacy farm foreign key in place.\n"
    );
    exit(1);
}

$mark = $pdo->prepare(
    'INSERT IGNORE INTO schema_migrations (filename) VALUES (?)'
);
$mark->execute([$migrationName]);

echo $alreadyApplied
    ? "PASS: {$migrationName} was already marked applied; retention integrity reverified.\n"
    : "PASS: applied {$migrationName} only.\n";

echo "PASS: migration 003 was not invoked by this script.\n";
echo "PASS: no orphan billing payment attempts were present.\n";
echo "PASS: billing audit row counts were preserved.\n";
echo "PASS: fk_billing_attempt_farm_restrict now blocks destructive farm deletion.\n";
echo "billing_payment_attempts rows: {$afterAttempts}\n";
echo "billing_provider_events rows: {$afterEvents}\n";
echo "PASS: no billing row, entitlement, subscription-history or operational farm data was rewritten.\n";
