<?php
declare(strict_types=1);

/**
 * B12 — shared browser-form revision identity presentation (SOURCE ONLY).
 *
 * Deliberately NOT imported by live routes until the three Daily Record
 * record_revision columns, transactional CAS writers, and browser handlers
 * are introduced in one controlled, separately verified integration.
 *
 * Render from an actual tenant-scoped, durable record loaded at page/modal
 * time. Do not generate edit tokens from a POST-time read, from a date-only
 * existence probe, or from a display-only population activity placeholder.
 *
 * These fields are UNTRUSTED client intent, not access-control evidence.
 * The POST handler must independently authorize the tenant/role/CSRF, then
 * lock the exact persisted id and compare the original immutable identity
 * and revision before updating any feed, stock, population, or sale effects.
 */
require_once __DIR__ . '/daily_record_revision_authority.php';

if (!function_exists('daily_record_revision_form_fields')) {
    /**
     * @param ?array<string,mixed> $row Null means an explicitly new record.
     * @return array{write_intent:string,record_id:string,expected_revision:string,original_cycle_id:string,original_record_date:string,original_animal_type:string}
     */
    function daily_record_revision_form_fields(
        string $type,
        int $farmId,
        ?array $row
    ): array {
        $definition = daily_record_revision_definition($type);
        if ($farmId < 1) {
            throw new InvalidArgumentException('A valid farm is required.');
        }
        if ($row === null) {
            return [
                'write_intent' => 'create',
                'record_id' => '',
                'expected_revision' => '',
                'original_cycle_id' => '',
                'original_record_date' => '',
                'original_animal_type' => '',
            ];
        }

        // The continuity display model may synthesize id=0 movement-only rows.
        // Neither those nor cross-farm snapshots can authorize an EDIT form.
        if (!empty($row['is_population_activity_only'])) {
            throw new DailyRecordRevisionConflict(
                'Population activity is not an editable Daily Record.'
            );
        }
        foreach (['id', 'farm_id', 'cycle_id', 'record_date', 'record_revision'] as $column) {
            if (!array_key_exists($column, $row)) {
                throw new DailyRecordRevisionConflict(
                    'The Daily Record identity is incomplete. Reload it before saving.'
                );
            }
        }
        if ($definition['animal_type'] && !array_key_exists('animal_type', $row)) {
            throw new DailyRecordRevisionConflict(
                'The Daily Record species is missing. Reload it before saving.'
            );
        }

        $recordId = daily_record_revision_parse_record_id($row['id']);
        $rowFarmId = daily_record_revision_parse_record_id($row['farm_id']);
        if ($rowFarmId !== $farmId) {
            throw new DailyRecordRevisionConflict(
                'The Daily Record does not belong to this farm.'
            );
        }
        $cycleId = $row['cycle_id'] === null
            ? null
            : daily_record_revision_parse_record_id($row['cycle_id']);
        if (!is_string($row['record_date'])) {
            throw new DailyRecordRevisionConflict(
                'The Daily Record date is invalid. Reload it before saving.'
            );
        }
        $date = $row['record_date'];
        $species = daily_record_revision_validate_scope(
            $farmId,
            $recordId,
            $cycleId,
            $date,
            (bool)$definition['animal_type'],
            $definition['animal_type'] ? $row['animal_type'] : null
        );
        $intent = daily_record_revision_parse_write_intent(
            'edit',
            $recordId,
            $row['record_revision']
        );

        return [
            'write_intent' => $intent['mode'],
            'record_id' => (string)$intent['record_id'],
            'expected_revision' => (string)$intent['expected_revision'],
            'original_cycle_id' => $cycleId === null ? '' : (string)$cycleId,
            'original_record_date' => $date,
            'original_animal_type' => $species ?? '',
        ];
    }
}
