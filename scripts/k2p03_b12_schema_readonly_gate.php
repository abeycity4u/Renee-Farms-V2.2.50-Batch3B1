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
