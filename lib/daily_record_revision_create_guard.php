<?php
declare(strict_types=1);

/**
 * B12 SOURCE-ONLY; NOT WIRED. Serialize one tenant's participating CREATE
 * writers on the existing farms row before checking authoritative scope.
 *
 * CRITICAL: this lock does not protect against legacy writers that ignore it;
 * neither a 0-row existence probe nor a nullable UNIQUE index is a global
 * uniqueness guarantee. Activate only after all seven writers have migrated,
 * legacy data policy is audited, and disposable multi-worker tests pass.
 *
 * Caller: require login/module/permission/CSRF, validate business inputs, own
 * the PDO transaction, initialize validated farmId from the server session,
 * and call with an explicitly selected cycle (null means legacy NULL).
 */
require_once __DIR__ . '/daily_record_revision_parent_lock.php';

if (!function_exists('daily_record_revision_create_lock_scope')) {
    function daily_record_revision_create_lock_scope(
        PDO $pdo, string $type, int $farmId, ?int $cycleId,
        string $recordDate, ?string $animalType
    ): array {
        if (!$pdo->inTransaction()) {
            throw new LogicException('CREATE serialization requires caller transaction.');
        }
        if ($farmId < 1) {
            throw new InvalidArgumentException('Invalid current tenant identity.');
        }
        // Canonical shared reader owns species/date/type validation and the
        // NULL-cycle scope query, not this service or an individual page.
        if (!function_exists('daily_record_revision_reader_scope_plan') ||
            !function_exists('daily_record_revision_reader_check_scope')) {
            throw new LogicException('Canonical Daily Record scope reader not loaded.');
        }
        daily_record_revision_reader_scope_plan($type, $farmId, $cycleId, $recordDate, $animalType);

        // Central parent locking policy is shared with EDIT and DELETE.
        // Preserve farm -> cycle -> CREATE scope ordering.
        daily_record_revision_lock_parents($pdo, $farmId, $cycleId);

        // Probe AFTER the tenant lock using a CURRENT locking read. A plain
        // SELECT can reuse a stale REPEATABLE READ snapshot from earlier in
        // this transaction and miss a concurrent CREATE that just committed.
        // Every participating writer must use this lock order. A found row
        // is never permission to silently reclassify CREATE as EDIT.
        $existing = daily_record_revision_reader_check_scope(
            $pdo, $type, $farmId, $cycleId, $recordDate, $animalType, true
        );
        if (($existing['status'] ?? null) !== 'absent' ||
            !array_key_exists('record_id', $existing) || $existing['record_id'] !== null) {
            throw new DailyRecordRevisionConflict(
                'A Daily Record already exists or is ambiguous. Reload the selection.'
            );
        }
        return [
            'status' => 'locked_absent',
            'farm_id' => $farmId,
            'cycle_id' => $cycleId,
            'record_date' => $recordDate,
            'animal_type' => $animalType,
        ];
    }
}
