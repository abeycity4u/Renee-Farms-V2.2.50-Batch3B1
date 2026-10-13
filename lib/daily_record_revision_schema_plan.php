<?php
declare(strict_types=1);

/**
 * B12 source-only three-table revision migration planner.
 *
 * IMPORTANT: There is intentionally NO PDO dependency, database connection,
 * SQL execution, migration-registration step, or application route import.
 * Call only with independently verified, read-only schema metadata from a
 * separately authorized ISOLATED environment. A fabricated fixture proves
 * this PHP contract only, never real tenant readiness.
 *
 * The ordinary scripts/run_migrations.php discovers migrations/*.sql and
 * ignores duplicate-column errors. It is NOT a safe executor for this
 * non-atomic three-table DDL sequence. Keep any draft outside migrations/.
 */

if (!function_exists('daily_record_revision_schema_tables')) {
    /** Closed, source-reviewed table and cycle FK identities. */
    function daily_record_revision_schema_tables(): array
    {
        return [
            'layer_daily_records' => 'fk_layer_cycle',
            'broiler_daily_records' => 'fk_broiler_cycle',
            'ruminant_daily_records' => 'fk_ruminant_cycle',
        ];
    }
}

if (!function_exists('daily_record_revision_schema_required_columns')) {
    function daily_record_revision_schema_required_columns(string $table): array
    {
        if (!array_key_exists($table, daily_record_revision_schema_tables())) {
            throw new InvalidArgumentException('Unsupported B12 schema table.');
        }
        $required = [
            'id' => ['int', 'NO'],
            'farm_id' => ['int', 'NO'],
            'cycle_id' => ['int', 'YES'],
            'record_date' => ['date', 'NO'],
        ];
        if ($table === 'ruminant_daily_records') {
            $required['animal_type'] = ['varchar(100)', 'NO'];
        }
        return $required;
    }
}

if (!function_exists('daily_record_revision_schema_column_matches')) {
    /** Information_schema.columns shape: column_type, is_nullable, column_default. */
    function daily_record_revision_schema_column_matches(
        array $column, string $expectedType, string $expectedNull
    ): bool {
        if (!isset($column['column_type'], $column['is_nullable']) ||
            !is_string($column['column_type']) || !is_string($column['is_nullable'])) {
            return false;
        }
        $type = strtolower(trim($column['column_type']));
        $nullability = strtoupper(trim($column['is_nullable']));
        if ($nullability !== $expectedNull) return false;
        if ($expectedType === 'int') {
            return preg_match('/^int(?:\\([0-9]+\\))?$/D', $type) === 1;
        }
        return $type === $expectedType;
    }
}

if (!function_exists('daily_record_revision_schema_revision_valid')) {
    function daily_record_revision_schema_revision_valid(array $column): bool
    {
        if (!isset($column['column_default']) ||
            !is_scalar($column['column_default'])) {
            return false;
        }
        $rawDefault = trim((string)$column['column_default']);
        if ($rawDefault !== '1' && $rawDefault !== "'1'") return false;
        if (!isset($column['column_type'], $column['is_nullable']) ||
            !is_string($column['column_type']) ||
            !is_string($column['is_nullable']) ||
            strtoupper(trim($column['is_nullable'])) !== 'NO') {
            return false;
        }
        return preg_match(
            '/^bigint(?:\\([0-9]+\\))? unsigned$/D',
            strtolower(trim($column['column_type']))
        ) === 1;
    }
}

if (!function_exists('daily_record_revision_schema_check_table')) {
    /**
     * @param array $metadata
     *   engine:string, columns:map keyed by column name, cycle_fk:array from
     *   already reviewed SHOW CREATE TABLE or equally authoritative audit.
     * No data or FK inference from a missing information_schema visibility row.
     *
     * @return 'absent'|'valid'
     */
    function daily_record_revision_schema_check_table(string $table, array $metadata): string
    {
        $fks = daily_record_revision_schema_tables();
        if (!isset($fks[$table])) {
            throw new InvalidArgumentException('Unrecognized Daily Record table.');
        }
        if (!isset($metadata['engine'], $metadata['columns'], $metadata['cycle_fk']) ||
            !is_string($metadata['engine']) ||
            !is_array($metadata['columns']) || !is_array($metadata['cycle_fk']) ||
            strcasecmp(trim($metadata['engine']), 'InnoDB') !== 0) {
            throw new DomainException('B12 table engine or metadata is not attested.');
        }
        foreach (daily_record_revision_schema_required_columns($table) as $name => $expected) {
            if (!isset($metadata['columns'][$name]) ||
                !is_array($metadata['columns'][$name]) ||
                !daily_record_revision_schema_column_matches(
                    $metadata['columns'][$name], $expected[0], $expected[1]
                )) {
                throw new DomainException('B12 identity schema differs from reviewed baseline: ' . $table);
            }
        }
        $fk = $metadata['cycle_fk'];
        if (($fk['constraint_name'] ?? null) !== $fks[$table] ||
            ($fk['column'] ?? null) !== 'cycle_id' ||
            ($fk['referenced_table'] ?? null) !== 'production_cycles' ||
            ($fk['referenced_column'] ?? null) !== 'id' ||
            strtoupper(trim((string)($fk['on_delete'] ?? ''))) !== 'SET NULL') {
            throw new DomainException('B12 cycle FK attestation differs from reviewed baseline: ' . $table);
        }
        if (!array_key_exists('record_revision', $metadata['columns'])) {
            return 'absent';
        }
        $revision = $metadata['columns']['record_revision'];
        if (!is_array($revision) || !daily_record_revision_schema_revision_valid($revision)) {
            throw new DomainException('Existing B12 revision column is incompatible: ' . $table);
        }
        return 'valid';
    }
}

if (!function_exists('daily_record_revision_schema_candidate_plan')) {
    /**
     * Source-only assessment. Does NOT attest physical DB identity, actual
     * legacy data, concurrent writers, rollout safety, or execution approval.
     * Does not produce a partially-applied retry script: manual recovery gate.
     *
     * @return array{status:string,columns:array,statements:array,executable:bool}
     */
    function daily_record_revision_schema_candidate_plan(array $schema): array
    {
        $tables = daily_record_revision_schema_tables();
        $supplied = array_keys($schema);
        $required = array_keys($tables);
        sort($supplied, SORT_STRING);
        sort($required, SORT_STRING);
        if ($supplied !== $required) {
            throw new DomainException('B12 requires metadata for exactly the three Daily Record tables.');
        }
        $states = [];
        foreach ($tables as $table => $_constraintName) {
            if (!is_array($schema[$table])) {
                throw new DomainException('Invalid B12 table metadata.');
            }
            $states[$table] = daily_record_revision_schema_check_table($table, $schema[$table]);
        }
        $present = count(array_filter(
            $states, static fn(string $status): bool => $status === 'valid'
        ));
        if ($present === count($tables)) {
            return [
                'status' => 'columns_already_valid_marker_not_verified',
                'columns' => $states,
                'statements' => [],
                'executable' => false,
            ];
        }
        if ($present !== 0) {
            return [
                'status' => 'partial_ddl_stop_manual_recovery',
                'columns' => $states,
                'statements' => [],
                'executable' => false,
            ];
        }
        $statements = [];
        foreach (array_keys($tables) as $table) {
            // Identifiers are solely from the closed literal table map.
            $statements[] = 'ALTER TABLE `' . $table
                . '` ADD COLUMN `record_revision` BIGINT UNSIGNED NOT NULL DEFAULT 1';
        }
        return [
            'status' => 'candidate_only_unapproved',
            'columns' => $states,
            'statements' => $statements,
            'executable' => false,
        ];
    }
}
