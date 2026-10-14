<?php
declare(strict_types=1);

/**
 * B12 staged, SOURCE-ONLY common transaction lock hierarchy.
 *
 * Every participating Daily Record CREATE, EDIT and DELETE writer must
 * acquire farm -> selected production cycle BEFORE locking the source row,
 * date/species scope, later-row continuity, feed, or population projection.
 * The farm lock serializes participating writers within one tenant. Other
 * existing writers remain independent until the separately authorized
 * coordinated cutover and cross-module concurrency certification.
 *
 * The tenant and selected cycle IDs must come from validated server scope
 * and original immutable browser identity; locks DO NOT confer permissions.
 * An explicitly reviewed NULL-cycle DELETE locks the farm only. Interactive
 * SAVE already rejects a NULL-cycle before entering its write pipeline.
 */
if (!function_exists('daily_record_revision_lock_parents')) {
    function daily_record_revision_lock_parents(
        PDO $pdo, int $farmId, ?int $cycleId
    ): void {
        if (!$pdo->inTransaction()) {
            throw new LogicException('Daily Record parent locks require caller transaction.');
        }
        if ($farmId <= 0 || ($cycleId !== null && $cycleId <= 0)) {
            throw new InvalidArgumentException('Invalid Daily Record parent scope.');
        }

        $farm = $pdo->prepare(
            'SELECT id FROM farms WHERE id = ? LIMIT 1 FOR UPDATE'
        );
        if ($farm === false || !$farm->execute([$farmId]) ||
            (string)$farm->fetchColumn() !== (string)$farmId) {
            throw new DailyRecordRevisionConflict(
                'The selected Daily Record farm is unavailable.'
            );
        }
        if ($cycleId === null) {
            return;
        }

        $cycle = $pdo->prepare(
            'SELECT id FROM production_cycles WHERE id = ? AND farm_id = ? LIMIT 1 FOR UPDATE'
        );
        if ($cycle === false || !$cycle->execute([$cycleId, $farmId]) ||
            (string)$cycle->fetchColumn() !== (string)$cycleId) {
            throw new DailyRecordRevisionConflict(
                'The selected Daily Record production cycle is unavailable.'
            );
        }
    }
}
