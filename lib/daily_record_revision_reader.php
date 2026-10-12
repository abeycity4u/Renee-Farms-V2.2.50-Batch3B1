<?php
declare(strict_types=1);

/**
 * B12: authoritative Daily Record source reader (SOURCE ONLY; NOT WIRED).
 *
 * Caller must already authorize login, module, tenant and read/edit permission.
 * Caller supplies farmId from requireCurrentFarmId(), NEVER from request input.
 * The source DB must first have three authoritative record_revision columns.
 * This does not grant permissions, start transactions, mutate records or
 * establish CREATE uniqueness/serialize concurrent INSERTs.
 *
 * Date-only reads are NEVER edit identities. An exact ID is mandatory for
 * editor loads; an explicit-cycle scope probe has distinct 0/1/>1 results.
 */
require_once __DIR__ . '/daily_record_revision_form_tokens.php';

if (!class_exists('DailyRecordRevisionNotFound', false)) {
    final class DailyRecordRevisionNotFound extends RuntimeException {}
}

if (!function_exists('daily_record_revision_reader_cycle')) {
    function daily_record_revision_reader_cycle(mixed $rawCycle): ?int
    {
        // The route must require that the cycle_id parameter was supplied.
        // Explicit empty represents a legacy NULL cycle, never an all-cycle view.
        if ($rawCycle === null || $rawCycle === '') {
            return null;
        }
        return daily_record_revision_parse_record_id($rawCycle);
    }
}

if (!function_exists('daily_record_revision_reader_scope')) {
    /** @return array{cycle_id:?int,record_date:string,animal_type:?string} */
    function daily_record_revision_reader_scope(
        string $type, int $farmId, mixed $rawCycle,
        string $recordDate, ?string $animalType
    ): array {
        $definition = daily_record_revision_definition($type);
        $cycleId = daily_record_revision_reader_cycle($rawCycle);
        $species = daily_record_revision_validate_scope(
            $farmId, 1, $cycleId, $recordDate,
            (bool)$definition['animal_type'], $animalType
        );
        if ($species !== null && !in_array(
            $species, ['cattle', 'goat', 'sheep', 'other'], true
        )) {
            throw new DailyRecordRevisionConflict(
                'The Daily Record species is not supported. Reload the selection.'
            );
        }
        return [
            'cycle_id' => $cycleId,
            'record_date' => $recordDate,
            'animal_type' => $species,
        ];
    }
}

if (!function_exists('daily_record_revision_reader_exact_plan')) {
    /**
     * Strict immutable ID plus server-authorized tenant. Explicit date/cycle/
     * species are asserted AFTER loading. A tenant-qualified cycle LEFT JOIN
     * exposes invalid cross-tenant or orphan links for fail-closed handling.
     */
    function daily_record_revision_reader_exact_plan(
        string $type, int $farmId, mixed $rawRecordId,
        mixed $rawCycle, string $recordDate, ?string $animalType
    ): array {
        $definition = daily_record_revision_definition($type);
        $recordId = daily_record_revision_parse_record_id($rawRecordId);
        $scope = daily_record_revision_reader_scope(
            $type, $farmId, $rawCycle, $recordDate, $animalType
        );
        return [
            'sql' => 'SELECT d.*, c.id AS b12_tenant_cycle_verified'
                . ' FROM ' . $definition['table'] . ' d'
                . ' LEFT JOIN production_cycles c'
                . ' ON c.id = d.cycle_id AND c.farm_id = d.farm_id'
                . ' WHERE d.id = ? AND d.farm_id = ? LIMIT 2',
            'params' => [$recordId, $farmId],
            'record_id' => $recordId,
            'scope' => $scope,
        ];
    }
}

if (!function_exists('daily_record_revision_reader_scope_plan')) {
    /**
     * Scope lookup only: never use the result as an editable row or to change
     * submitted CREATE into EDIT. Zero means absence at one instant, NOT a
     * concurrency-safe authorization to INSERT. Two means ambiguity.
     */
    function daily_record_revision_reader_scope_plan(
        string $type, int $farmId, mixed $rawCycle,
        string $recordDate, ?string $animalType
    ): array {
        $definition = daily_record_revision_definition($type);
        $scope = daily_record_revision_reader_scope(
            $type, $farmId, $rawCycle, $recordDate, $animalType
        );
        $sql = 'SELECT d.id FROM ' . $definition['table'] . ' d'
            . ' WHERE d.farm_id = ? AND d.record_date = ?';
        $params = [$farmId, $scope['record_date']];
        if ($scope['cycle_id'] === null) {
            $sql .= ' AND d.cycle_id IS NULL';
        } else {
            $sql .= ' AND d.cycle_id = ?';
            $params[] = $scope['cycle_id'];
        }
        if ($definition['animal_type']) {
            $sql .= ' AND LOWER(TRIM(d.animal_type)) = ?';
            $params[] = $scope['animal_type'];
        }
        return ['sql' => $sql . ' LIMIT 2', 'params' => $params];
    }
}

if (!function_exists('daily_record_revision_reader_scope_cardinality')) {
    /**
     * @return array{status:string,record_id:?int}
     */
    function daily_record_revision_reader_scope_cardinality(array $rows): array
    {
        if (count($rows) > 1) {
            throw new DailyRecordRevisionConflict(
                'Multiple Daily Records match the selection. Choose a specific record.'
            );
        }
        if (count($rows) === 0) {
            return ['status' => 'absent', 'record_id' => null];
        }
        if (!is_array($rows[0]) || !array_key_exists('id', $rows[0])) {
            throw new DailyRecordRevisionConflict('Invalid Daily Record lookup result.');
        }
        return [
            'status' => 'unique',
            'record_id' => daily_record_revision_parse_record_id($rows[0]['id']),
        ];
    }
}

if (!function_exists('daily_record_revision_reader_resolve_exact')) {
    /**
     * Verify immutable source and linked-cycle tenant integrity BEFORE
     * generating untrusted client edit tokens. Reject missing/multiple rows,
     * including sources with absent revision columns. Never infer revision.
     */
    function daily_record_revision_reader_resolve_exact(
        string $type, int $farmId, array $plan, array $rows
    ): array {
        if (count($rows) === 0) {
            throw new DailyRecordRevisionNotFound(
                'The Daily Record is no longer available.'
            );
        }
        if (count($rows) !== 1 || !is_array($rows[0])) {
            throw new DailyRecordRevisionConflict(
                'The Daily Record is ambiguous. Reload the selection.'
            );
        }
        $row = $rows[0];
        if (!array_key_exists('b12_tenant_cycle_verified', $row)) {
            throw new DailyRecordRevisionConflict(
                'The Daily Record cycle integrity cannot be verified.'
            );
        }
        $tokens = daily_record_revision_form_fields($type, $farmId, $row);
        if ((int)$tokens['record_id'] !== $plan['record_id']) {
            throw new DailyRecordRevisionConflict('The Daily Record identity changed.');
        }
        $scope = $plan['scope'];
        if (
            $tokens['original_cycle_id'] !== ($scope['cycle_id'] === null ? '' : (string)$scope['cycle_id']) ||
            $tokens['original_record_date'] !== $scope['record_date'] ||
            $tokens['original_animal_type'] !== ($scope['animal_type'] ?? '')
        ) {
            throw new DailyRecordRevisionConflict(
                'The Daily Record selection changed. Reload before editing.'
            );
        }
        if ($scope['cycle_id'] === null) {
            if ($row['b12_tenant_cycle_verified'] !== null) {
                throw new DailyRecordRevisionConflict('Invalid legacy Daily Record cycle.');
            }
        } elseif (
            daily_record_revision_parse_record_id($row['b12_tenant_cycle_verified'])
                !== $scope['cycle_id']
        ) {
            throw new DailyRecordRevisionConflict(
                'The Daily Record cycle belongs to another farm or is unavailable.'
            );
        }
        unset($row['b12_tenant_cycle_verified']);
        return ['record' => $row, 'form_tokens' => $tokens];
    }
}

if (!function_exists('daily_record_revision_reader_read_exact')) {
    /**
     * Requires caller-side login, current farm, module/read permission and
     * explicit record_id + cycle_id presence. Returns data only after the
     * 3-table migration; never used by current api/get_record.php.
     */
    function daily_record_revision_reader_read_exact(
        PDO $pdo, string $type, int $farmId, mixed $rawRecordId,
        mixed $rawCycle, string $recordDate, ?string $animalType
    ): array {
        $plan = daily_record_revision_reader_exact_plan(
            $type, $farmId, $rawRecordId, $rawCycle, $recordDate, $animalType
        );
        $stmt = $pdo->prepare($plan['sql']);
        if ($stmt === false) {
            throw new RuntimeException('The Daily Record could not be loaded.');
        }
        $stmt->execute($plan['params']);
        return daily_record_revision_reader_resolve_exact(
            $type, $farmId, $plan, $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }
}

if (!function_exists('daily_record_revision_reader_check_scope')) {
    /** A scope probe is NEVER a unique CREATE reservation or edit token. */
    function daily_record_revision_reader_check_scope(
        PDO $pdo, string $type, int $farmId, mixed $rawCycle,
        string $recordDate, ?string $animalType
    ): array {
        $plan = daily_record_revision_reader_scope_plan(
            $type, $farmId, $rawCycle, $recordDate, $animalType
        );
        $stmt = $pdo->prepare($plan['sql']);
        if ($stmt === false) {
            throw new RuntimeException('The Daily Record selection could not be checked.');
        }
        $stmt->execute($plan['params']);
        return daily_record_revision_reader_scope_cardinality(
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }
}
