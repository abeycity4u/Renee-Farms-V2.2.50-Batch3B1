<?php
declare(strict_types=1);

/**
 * B12 — deliberately fail-closed CLI runner for the already-reviewed
 * aggregate/schema-only SQL inventory. NOT a migration.
 *
 * Default / --lint-only: NO database connection, no DB queries.
 * --run: requires a separately approved NEW disposable isolated clone, a
 * SELECT-only user, verified identity/provenance, and explicit env guards.
 * Never run against production, staging, AWS Nodes A/B/C, D12 fixtures or
 * any pre-existing protected database.
 *
 * This script cannot independently certify an environment as disposable.
 * Human authorization and out-of-band instance/clone verification are
 * prerequisites, not supplied by setting an environment variable.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

function b12_schema_audit_abort(string $reason): never
{
    fwrite(STDERR, 'B12_SCHEMA_GATE=BLOCKED; REASON=' . $reason . PHP_EOL);
    exit(1);
}

/**
 * Section 05 must report the three actual cycle foreign keys. A SELECT may
 * succeed but return zero visible metadata rows under limited DB privileges;
 * that is NOT a successful schema audit.
 */
function b12_schema_audit_has_cycle_fk_rules(array $rows): bool
{
    $expected = [
        'layer_daily_records' => 0,
        'broiler_daily_records' => 0,
        'ruminant_daily_records' => 0,
    ];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            return false;
        }
        $table = (string)($row['table_name'] ?? '');
        if (!array_key_exists($table, $expected)
            || ($row['referenced_table_name'] ?? null) !== 'production_cycles') {
            continue;
        }
        if (strtoupper(trim((string)($row['delete_rule'] ?? ''))) !== 'SET NULL') {
            return false;
        }
        $expected[$table]++;
    }
    foreach ($expected as $count) {
        if ($count !== 1) {
            return false;
        }
    }
    return true;
}

/**
 * For SELECT-only roles, MariaDB may hide REFERENTIAL_CONSTRAINTS but
 * expose the same table's read-only SHOW CREATE TABLE metadata.
 * The table and FK names are a closed map; none come from HTTP input.
 */
function b12_schema_audit_cycle_fk_ddl_matches(string $table, string $ddl): bool
{
    $constraints = [
        'layer_daily_records' => 'fk_layer_cycle',
        'broiler_daily_records' => 'fk_broiler_cycle',
        'ruminant_daily_records' => 'fk_ruminant_cycle',
    ];
    if (!isset($constraints[$table]) || $ddl === '') {
        return false;
    }
    $name = preg_quote($constraints[$table], '/');
    $pattern = '/\bCONSTRAINT\s+\x60?' . $name
        . '\x60?\s+FOREIGN\s+KEY\s*\(\s*\x60?cycle_id\x60?\s*\)'
        . '\s+REFERENCES\s+\x60?production_cycles\x60?\s*\(\s*\x60?id\x60?\s*\)'
        . '\s+ON\s+DELETE\s+SET\s+NULL\b/i';
    return preg_match($pattern, $ddl) === 1;
}

function b12_schema_audit_cycle_fk_ddl_attest(PDO $pdo): bool
{
    foreach (['layer_daily_records', 'broiler_daily_records',
              'ruminant_daily_records'] as $table) {
        $stmt = $pdo->query('SHOW CREATE TABLE ' . chr(96) . $table . chr(96));
        $row = $stmt === false ? false : $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)
            || !is_string($row['Create Table'] ?? null)
            || !b12_schema_audit_cycle_fk_ddl_matches(
                $table, $row['Create Table']
            )) {
            return false;
        }
    }
    return true;
}

$mode = $argv[1] ?? '--lint-only';
if (!in_array($mode, ['--lint-only', '--run'], true) || count($argv) > 2) {
    b12_schema_audit_abort('USE_EXPLICIT_LINT_ONLY_OR_RUN');
}

$path = dirname(__DIR__) . '/docs/C137_GC24_K2_P03_B12_READONLY_SCHEMA_INVENTORY.sql';
$raw = file_get_contents($path);
if (!is_string($raw) || $raw === '') {
    b12_schema_audit_abort('PINNED_SQL_MISSING');
}
if (!hash_equals(
    'fe9e80cfb836f593b146cf9adf269a15a78ab9a192e5292fc02376d02becbfd7',
    hash('sha256', $raw)
)) {
    b12_schema_audit_abort('PINNED_SQL_CHECKSUM_CHANGED');
}

// The audited inventory uses only standalone '--' comments and SELECT.
// Fail closed if that contract changes (e.g., executable block comments).
if (str_contains($raw, '/*') || str_contains($raw, '#')) {
    b12_schema_audit_abort('UNREVIEWED_SQL_COMMENT_OR_DIRECTIVE');
}
$withoutComments = preg_replace('/^\\s*--[^\\r\\n]*(?:\\r?\\n|$)/m', '', $raw);
if (!is_string($withoutComments)) {
    b12_schema_audit_abort('SQL_COMMENT_PARSER_FAILED');
}
$statements = array_values(array_filter(
    array_map('trim', explode(';', $withoutComments)),
    static fn(string $statement): bool => $statement !== ''
));
if (count($statements) !== 11) {
    b12_schema_audit_abort('UNEXPECTED_SECTION_COUNT');
}

$labels = [
    '01_DATABASE_IDENTITY',
    '02_REQUIRED_TABLES',
    '03_IDENTITY_COLUMNS',
    '04_UNIQUE_AND_RELEVANT_INDEXES',
    '05_FOREIGN_KEY_DELETE_RULES',
    '06_ROW_AND_CYCLE_COUNTS',
    '07_NULL_CYCLE_DUPLICATES',
    '08_UNSCOPED_UPDATE_AMBIGUITY',
    '09_CYCLE_TENANT_INTEGRITY',
    '10_TRIGGER_COUNTS',
    '11_RUMINANT_SPECIES_DATA_SHAPE',
];
foreach ($statements as $i => $sql) {
    if (!preg_match('/^SELECT\\s+/i', $sql) ||
        !str_contains($sql, "'" . $labels[$i] . "'") ||
        preg_match('/\\b(INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP|CREATE|TRUNCATE|GRANT|REVOKE|CALL|EXECUTE|DO|SET|LOCK|UNLOCK|LOAD|INTO\\s+OUTFILE)\\b/i', $sql)) {
        b12_schema_audit_abort('UNREVIEWED_STATEMENT_' . ($i + 1));
    }
}

if ($mode === '--lint-only') {
    $cycleFkFixture = [
        ['table_name' => 'layer_daily_records',
         'referenced_table_name' => 'production_cycles', 'delete_rule' => 'SET NULL'],
        ['table_name' => 'broiler_daily_records',
         'referenced_table_name' => 'production_cycles', 'delete_rule' => 'SET NULL'],
        ['table_name' => 'ruminant_daily_records',
         'referenced_table_name' => 'production_cycles', 'delete_rule' => 'SET NULL'],
    ];
    if (!b12_schema_audit_has_cycle_fk_rules($cycleFkFixture) ||
        b12_schema_audit_has_cycle_fk_rules([]) ||
        b12_schema_audit_has_cycle_fk_rules(array_slice($cycleFkFixture, 0, 2))) {
        b12_schema_audit_abort('CYCLE_FK_RESULT_VALIDATOR_FAILED');
    }
    $wrongDelete = $cycleFkFixture;
    $wrongDelete[0]['delete_rule'] = 'CASCADE';
    if (b12_schema_audit_has_cycle_fk_rules($wrongDelete)) {
        b12_schema_audit_abort('CYCLE_FK_WRONG_DELETE_RULE_ACCEPTED');
    }
    $validDdl = 'CREATE TABLE layer_daily_records ('
        . ' CONSTRAINT fk_layer_cycle FOREIGN KEY (cycle_id)'
        . ' REFERENCES production_cycles (id) ON DELETE SET NULL)';
    if (!b12_schema_audit_cycle_fk_ddl_matches(
        'layer_daily_records', $validDdl
    ) || b12_schema_audit_cycle_fk_ddl_matches(
        'broiler_daily_records', $validDdl
    ) || b12_schema_audit_cycle_fk_ddl_matches(
        'layer_daily_records',
        str_replace('ON DELETE SET NULL', 'ON DELETE CASCADE', $validDdl)
    ) || b12_schema_audit_cycle_fk_ddl_matches(
        'layer_daily_records',
        str_replace('FOREIGN KEY (cycle_id)', 'FOREIGN KEY (farm_id)', $validDdl)
    )) {
        b12_schema_audit_abort('CYCLE_FK_DDL_PARSER_CONTRACT_FAILED');
    }
    echo "CYCLE_FK_DDL_PARSER_CONTRACT=PASS\n";
    echo "CYCLE_FK_RESULT_FAIL_CLOSED=PASS\n";
    echo "B12_SCHEMA_SOURCE_GATE=PASS\n";
    echo "READONLY_SELECT_SECTIONS=11\n";
    echo "DATABASE_CONNECTIONS=0\n";
    echo "DATABASE_QUERIES=0\n";
    echo "SOURCE_SQL_SHA256=" . hash('sha256', $raw) . "\n";
    exit(0);
}

// These switches NEVER replace the required separately documented approval,
// verified isolated database identity, read-only grant and clone provenance.
if (getenv('B12_SCHEMA_APPROVED_RUN') !== 'YES_ONE_DISPOSABLE_READONLY_CLONE' ||
    getenv('B12_SCHEMA_PROVENANCE_VERIFIED') !== 'YES' ||
    getenv('B12_SCHEMA_READONLY_GRANTS_VERIFIED') !== 'YES') {
    b12_schema_audit_abort('SEPARATE_APPROVAL_AND_IDENTITY_REQUIRED');
}
$host = getenv('B12_SCHEMA_HOST') ?: '';
$db = getenv('B12_SCHEMA_DATABASE') ?: '';
$user = getenv('B12_SCHEMA_USER') ?: '';
$secretFile = getenv('B12_SCHEMA_PASSWORD_FILE') ?: '';
if ($host !== '127.0.0.1' ||
    !preg_match('/^renee_b12_disposable_[a-z0-9_]{3,48}$/D', $db) ||
    $user === '' || strcasecmp($user, 'root') === 0 ||
    !is_file($secretFile) || !is_readable($secretFile) ||
    !extension_loaded('pdo_mysql')) {
    b12_schema_audit_abort('NON_DISPOSABLE_TARGET_OR_CREDENTIALS');
}
$password = rtrim((string)file_get_contents($secretFile), "\r\n");
if ($password === '') {
    b12_schema_audit_abort('EMPTY_DATABASE_SECRET');
}

try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;dbname=' . $db . ';charset=utf8mb4',
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    unset($password);
    if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== $db) {
        b12_schema_audit_abort('DATABASE_IDENTITY_MISMATCH');
    }
    echo "B12_SCHEMA_DATABASE_IDENTITY=PASS\n";
    echo "B12_SCHEMA_SELECT_SECTIONS=11\n";
    foreach ($statements as $i => $sql) {
        $rows = $pdo->query($sql)->fetchAll();
        if (count($rows) > 300) {
            b12_schema_audit_abort('METADATA_RESULT_TOO_LARGE');
        }
        if ($labels[$i] === '05_FOREIGN_KEY_DELETE_RULES') {
            echo 'FK_REFERENCE_METADATA_ROWS=' . count($rows) . PHP_EOL;
            if (b12_schema_audit_has_cycle_fk_rules($rows)) {
                echo "CYCLE_FK_ATTESTATION=REFERENTIAL_CONSTRAINTS_PASS\n";
            } elseif (b12_schema_audit_cycle_fk_ddl_attest($pdo)) {
                echo "CYCLE_FK_ATTESTATION=READONLY_SHOW_CREATE_TABLE_PASS\n";
            } else {
                b12_schema_audit_abort('CYCLE_FK_METADATA_MISSING_OR_INCOMPATIBLE');
            }
        }
        echo 'SECTION=' . $labels[$i] . PHP_EOL;
        foreach ($rows as $row) {
            // Inventory contains schema metadata and aggregate counts only.
            echo json_encode($row, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
        }
    }
    echo "B12_SCHEMA_PREFLIGHT=COMPLETE_READONLY\n";
} catch (Throwable $error) {
    // Do not echo SQL, connection strings, credentials or raw PDO errors.
    b12_schema_audit_abort('CONNECTION_OR_INVENTORY_ERROR_' . get_class($error));
}
