<?php
declare(strict_types=1);

/**
 * B12 source-only post-DELETE continuity coordination. NOT WIRED.
 *
 * The authorized DELETE transaction owns its original source FOR UPDATE lock.
 * Before feed/population reversals, lock later tenant/cycle/species rows in
 * chronological order and establish whether a canonical population baseline
 * exists for the deleted date. After the canonical mortality reversal, rebase
 * changed later rows through the SAME revision-aware continuity writer used
 * by SAVE, while the source and later-row locks are still held.
 *
 * A legacy/pre-baseline DELETE with later rows cannot synthesize an opening
 * stock from the deleted source. Reject it before any reversal until its
 * historical cohort policy is separately approved. Explicit NULL-cycle
 * source DELETEs preserve their existing exact-ID/revision contract; their
 * cross-record continuity is NOT certified by this helper.
 *
 * The caller must roll back the entire transaction on ANY throwable.
 * MariaDB lock ordering and cross-writer concurrency remain uncertified;
 * this is no substitute for the coordinated seven-writer cutover.
 */
require_once __DIR__ . '/daily_record_revision_continuity_apply.php';

if (!function_exists('daily_record_revision_delete_continuity_lock')) {
    /**
     * @return array{type:string,farm_id:int,record_id:int,cycle_id:?int,
     *   record_date:string,animal_type:?string,needs_rebase:bool}
     */
    function daily_record_revision_delete_continuity_lock(
        PDO $pdo, string $type, int $farmId, int $recordId,
        ?int $cycleId, string $recordDate, ?string $animalType
    ): array {
        daily_record_revision_require_transaction($pdo);
        $type = strtolower(trim($type));
        $def = daily_record_revision_definition($type);
        $animalType = daily_record_revision_validate_scope(
            $farmId, $recordId, $cycleId, $recordDate,
            (bool)$def['animal_type'], $animalType
        );
        if ($def['animal_type'] && !in_array(
            $animalType, ['cattle', 'goat', 'sheep', 'other'], true
        )) {
            throw new DailyRecordRevisionConflict('Unsupported Ruminant DELETE species.');
        }

        $plan = [
            'type' => $type,
            'farm_id' => $farmId,
            'record_id' => $recordId,
            'cycle_id' => $cycleId,
            'record_date' => $recordDate,
            'animal_type' => $animalType,
            'needs_rebase' => false,
        ];
        if ($cycleId === null) {
            // No trusted cohort: retain explicit legacy exact-ID deletion,
            // but do not pretend to certify downstream NULL-cycle continuity.
            return $plan;
        }

        // This read takes chronological later-row locks BEFORE reversing
        // feed or mortality, preserving the SAVE side-effect order as far
        // as the staged DELETE contract permits.
        $later = daily_population_continuity_later_rows(
            $pdo, $farmId, $cycleId, $type, $recordDate,
            $animalType, true
        );
        if (!is_array($later)) {
            throw new DailyRecordRevisionConflict('Later Daily Record scope is unavailable.');
        }
        if (!$later) {
            return $plan;
        }

        // Deletion is not a SAVE with mortality=0 in a legacy chain: its
        // deleted source opening_stock is not an approved cohort baseline.
        $opening = daily_population_continuity_expected_opening(
            $pdo, $farmId, $cycleId, $type, $recordDate, $animalType
        );
        if (!is_array($opening) ||
            ($opening['tracking_status'] ?? null) !== 'canonical' ||
            !isset($opening['opening_stock']) ||
            (int)$opening['opening_stock'] < 1) {
            throw new DailyRecordRevisionConflict(
                'Historical DELETE continuity needs an approved canonical population baseline.'
            );
        }
        $plan['needs_rebase'] = true;
        return $plan;
    }
}

if (!function_exists('daily_record_revision_delete_continuity_apply')) {
    /**
     * Call ONLY after the deleted source's mortality projection is reversed,
     * with all original row and chronological later-row locks still held.
     * Recompute from the resulting canonical population, not the deleted
     * row's opening or a browser-provided value.
     *
     * @return int Number of later rows whose revisions were incremented.
     */
    function daily_record_revision_delete_continuity_apply(
        PDO $pdo, array $plan
    ): int {
        daily_record_revision_require_transaction($pdo);
        foreach (['type','farm_id','record_id','cycle_id',
                  'record_date','animal_type','needs_rebase'] as $key) {
            if (!array_key_exists($key, $plan)) {
                throw new DailyRecordRevisionConflict('Incomplete DELETE continuity contract.');
            }
        }
        if (!is_bool($plan['needs_rebase'])) {
            throw new DailyRecordRevisionConflict('Invalid DELETE continuity intent.');
        }
        $type = $plan['type'];
        $farmId = $plan['farm_id'];
        $recordId = $plan['record_id'];
        $cycleId = $plan['cycle_id'];
        $recordDate = $plan['record_date'];
        $animalType = $plan['animal_type'];
        if (!is_string($type) || !is_int($farmId) || !is_int($recordId) ||
            ($cycleId !== null && !is_int($cycleId)) ||
            !is_string($recordDate) ||
            ($animalType !== null && !is_string($animalType))) {
            throw new DailyRecordRevisionConflict('Invalid DELETE continuity identity.');
        }
        $def = daily_record_revision_definition($type);
        daily_record_revision_validate_scope(
            $farmId, $recordId, $cycleId, $recordDate,
            (bool)$def['animal_type'], $animalType
        );
        if (!$plan['needs_rebase']) {
            return 0;
        }
        if ($cycleId === null || $cycleId <= 0) {
            throw new DailyRecordRevisionConflict('DELETE rebase requires exact cycle.');
        }
        $opening = daily_population_continuity_expected_opening(
            $pdo, $farmId, $cycleId, $type, $recordDate, $animalType
        );
        if (!is_array($opening) ||
            ($opening['tracking_status'] ?? null) !== 'canonical' ||
            !isset($opening['opening_stock']) ||
            (int)$opening['opening_stock'] < 1) {
            throw new DailyRecordRevisionConflict(
                'Canonical population changed during DELETE. Roll back and retry.'
            );
        }

        // The mortality projection was removed in this SAME transaction.
        // The canonical preview now observes zero target mortality and the
        // post-DELETE movement balance; later rows receive guarded revision
        // bumps only if their opening_stock actually changes.
        $result = daily_record_revision_continuity_apply(
            $pdo, $farmId, $cycleId, $type, $recordDate,
            (int)$opening['opening_stock'], 0, $animalType, $recordId
        );
        if (!is_array($result) || !isset($result['applied_count']) ||
            !is_int($result['applied_count']) ||
            $result['applied_count'] < 0) {
            throw new DailyRecordRevisionConflict('Invalid DELETE continuity result.');
        }
        return $result['applied_count'];
    }
}
