<?php
declare(strict_types=1);

/**
 * B12 staged source persistence for authorized CREATE/EDIT, NOT WIRED.
 *
 * The caller first invokes daily_record_revision_prepare_save() with trusted
 * tenant, permissions and CSRF, inside one transaction. It must then call
 * this service BEFORE existing continuity, feed, mortality and Layer egg-sale
 * allocation writers, committing only when every step succeeds. This helper
 * neither starts/commits a transaction nor changes business calculations.
 *
 * Requires the separately approved record_revision migration and seven-writer
 * cutover. Never deploy/activate this ahead of browser-loaded revision tokens.
 */
require_once __DIR__ . '/daily_record_revision_authority.php';

if (!function_exists('daily_record_revision_persist_prepared')) {
    /**
     * @param array $prepared Exact return from daily_record_revision_prepare_save().
     * @param array $fields Caller business-validated persisted values.
     * @return array{record_id:int,record_revision:int,mode:string}
     */
    function daily_record_revision_persist_prepared(
        PDO $pdo, array $prepared, int $farmId, int $actorId, array $fields
    ): array {
        daily_record_revision_require_transaction($pdo);
        if ($farmId < 1 || $actorId < 1 || !isset($prepared['contract']) ||
            !is_array($prepared['contract']) || !$fields) {
            throw new InvalidArgumentException('Invalid Daily Record write context.');
        }
        $intent = $prepared['contract'];
        if (!isset($intent['type'], $intent['mode'], $intent['scope']) ||
            !is_string($intent['type']) || !is_string($intent['mode']) ||
            !is_array($intent['scope']) ||
            !array_key_exists('cycle_id', $intent['scope']) ||
            !array_key_exists('record_date', $intent['scope']) ||
            !array_key_exists('animal_type', $intent['scope'])) {
            throw new InvalidArgumentException('Invalid Daily Record preflight contract.');
        }
        $type = strtolower(trim($intent['type']));
        $def = daily_record_revision_definition($type);
        $scope = $intent['scope'];
        $cycleId = $scope['cycle_id'];
        $date = $scope['record_date'];
        $species = $scope['animal_type'];
        if (($cycleId !== null && !is_int($cycleId)) ||
            !is_string($date) || ($species !== null && !is_string($species))) {
            throw new InvalidArgumentException('Invalid Daily Record persisted scope.');
        }
        $allowed = array_fill_keys($def['fields'], true);
        foreach ($fields as $column => $_value) {
            if (!is_string($column) || !isset($allowed[$column])) {
                throw new InvalidArgumentException('Unsupported Daily Record field.');
            }
        }
        foreach (['opening_stock', 'mortality'] as $required) {
            if (!array_key_exists($required, $fields)) {
                throw new InvalidArgumentException('Missing validated Daily Record field.');
            }
        }
        if ($type === 'layer' && !array_key_exists('egg_production', $fields)) {
            throw new InvalidArgumentException('Missing validated Layer egg production.');
        }
        if ($type !== 'ruminant' && !array_key_exists('birds_age', $fields)) {
            throw new InvalidArgumentException('Missing validated poultry bird age.');
        }

        if ($intent['mode'] === 'edit') {
            if (!isset($intent['record_id'], $intent['expected_revision']) ||
                !is_int($intent['record_id']) || !is_int($intent['expected_revision']) ||
                !is_array($prepared['locked_row'] ?? null) ||
                ($prepared['create_scope'] ?? null) !== null) {
                throw new DailyRecordRevisionConflict('Incomplete locked EDIT preflight.');
            }
            $recordId = $intent['record_id'];
            $expectedRevision = $intent['expected_revision'];
            if ((int)($prepared['locked_row']['id'] ?? 0) !== $recordId ||
                (int)($prepared['locked_row']['farm_id'] ?? 0) !== $farmId) {
                throw new DailyRecordRevisionConflict('EDIT lock identity does not match.');
            }
            $next = daily_record_revision_write(
                $pdo, $type, $farmId, $recordId, $cycleId,
                $date, $species, $expectedRevision, $fields
            );
            return ['record_id' => $recordId, 'record_revision' => $next, 'mode' => 'edit'];
        }
        if ($intent['mode'] !== 'create' ||
            ($intent['record_id'] ?? null) !== null ||
            ($intent['expected_revision'] ?? null) !== null ||
            ($prepared['locked_row'] ?? null) !== null ||
            !is_array($prepared['create_scope'] ?? null)) {
            throw new DailyRecordRevisionConflict('Incomplete locked CREATE preflight.');
        }
        $lock = $prepared['create_scope'];
        $normalizedSpecies = daily_record_revision_validate_scope(
            $farmId, 1, $cycleId, $date, (bool)$def['animal_type'], $species
        );
        if (($lock['status'] ?? null) !== 'locked_absent' ||
            ($lock['farm_id'] ?? null) !== $farmId ||
            ($lock['cycle_id'] ?? null) !== $cycleId ||
            ($lock['record_date'] ?? null) !== $date ||
            ($lock['animal_type'] ?? null) !== $normalizedSpecies) {
            throw new DailyRecordRevisionConflict('CREATE scope reservation no longer matches.');
        }
        $columns = ['farm_id', 'cycle_id', 'record_date'];
        $values = [$farmId, $cycleId, $date];
        if ($def['animal_type']) {
            $columns[] = 'animal_type';
            $values[] = $normalizedSpecies;
        }
        foreach ($fields as $column => $value) {
            $columns[] = $column;
            $values[] = $value;
        }
        $columns[] = 'user_id';
        $values[] = $actorId;
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = 'INSERT INTO ' . $def['table'] . ' (' . implode(', ', $columns)
            . ') VALUES (' . $placeholders . ')';
        $stmt = $pdo->prepare($sql);
        if ($stmt === false || !$stmt->execute($values) || $stmt->rowCount() !== 1) {
            throw new DailyRecordRevisionConflict('The Daily Record could not be created.');
        }
        // Schema default is authoritative; do not invent a revision in SQL.
        $recordId = daily_record_revision_parse_record_id($pdo->lastInsertId());
        return ['record_id' => $recordId, 'record_revision' => 1, 'mode' => 'create'];
    }
}
