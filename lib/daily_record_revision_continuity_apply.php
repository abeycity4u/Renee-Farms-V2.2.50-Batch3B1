<?php
declare(strict_types=1);

/**
 * B12 staged revision-aware population continuity writer, NOT WIRED.
 *
 * The existing canonical continuity preview owns biological calculations and
 * chronological later-row FOR UPDATE locks. Each changed durable later row
 * is then updated by the shared revision authority: old-opening compare,
 * immutable tenant/cycle/species/date predicate and one revision increment.
 *
 * This service does NOT replace the active daily_population_continuity_apply().
 * It REQUIRES the three-table record_revision migration and a coordinated
 * replacement of ALL seven Daily Record mutation paths before activation.
 * Caller owns transaction; every failure must roll back original Daily Record,
 * stock, mortality, population, and Layer egg allocations together.
 */
require_once __DIR__ . '/daily_population_continuity.php';
require_once __DIR__ . '/daily_record_revision_continuity.php';

if (!function_exists('daily_record_revision_continuity_apply')) {
    /**
     * @return array Existing continuity preview including applied_count.
     */
    function daily_record_revision_continuity_apply(
        PDO $pdo,
        int $farmId,
        int $cycleId,
        string $type,
        string $recordDate,
        int $openingStock,
        int $mortality,
        ?string $animalType,
        int $sourceId
    ): array {
        daily_record_revision_require_transaction($pdo);
        $type = strtolower(trim($type));
        $definition = daily_record_revision_definition($type);
        if ($cycleId <= 0 || $sourceId <= 0) {
            throw new InvalidArgumentException('Revision continuity requires the saved cycle and source ID.');
        }
        $animalType = daily_record_revision_validate_scope(
            $farmId, $sourceId, $cycleId, $recordDate,
            (bool)$definition['animal_type'], $animalType
        );

        // This is the SAME canonical/legacy preview as the active writer,
        // requested with row locks. Never use an advisory browser preview.
        $plan = daily_population_continuity_preview(
            $pdo, $farmId, $cycleId, $type, $recordDate,
            $openingStock, $mortality, $animalType, true, $sourceId
        );
        if (!is_array($plan) || !isset($plan['record_type'], $plan['farm_id'],
            $plan['cycle_id'], $plan['record_date'], $plan['affected_count'],
            $plan['changes']) || !array_key_exists('animal_type', $plan) ||
            $plan['record_type'] !== $type ||
            $plan['farm_id'] !== $farmId || $plan['cycle_id'] !== $cycleId ||
            $plan['record_date'] !== $recordDate ||
            $plan['animal_type'] !== $animalType ||
            !is_array($plan['changes']) ||
            !is_int($plan['affected_count']) ||
            $plan['affected_count'] !== count($plan['changes'])) {
            throw new DailyRecordRevisionConflict('The locked continuity plan is inconsistent.');
        }

        // Preflight the complete change manifest before ANY UPDATE. Equal
        // openings are deliberately absent from preview changes and must
        // never increase a later row's revision.
        $seen = [];
        $lastDate = $recordDate;
        $lastId = 0;
        foreach ($plan['changes'] as $change) {
            if (!is_array($change) ||
                !isset($change['id'], $change['record_date'],
                    $change['old_opening_stock'], $change['new_opening_stock']) ||
                !is_int($change['id']) || $change['id'] <= 0 ||
                !is_string($change['record_date']) ||
                !is_int($change['old_opening_stock']) ||
                !is_int($change['new_opening_stock']) ||
                $change['old_opening_stock'] < 0 ||
                $change['new_opening_stock'] < 1 ||
                $change['old_opening_stock'] === $change['new_opening_stock'] ||
                $change['record_date'] <= $recordDate ||
                isset($seen[$change['id']])) {
                throw new DailyRecordRevisionConflict('Invalid locked continuity change manifest.');
            }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $change['record_date']);
            if ($date === false || $date->format('Y-m-d') !== $change['record_date'] ||
                $change['record_date'] < $lastDate ||
                ($change['record_date'] === $lastDate && $change['id'] <= $lastId)) {
                throw new DailyRecordRevisionConflict('Later Daily Record changes are out of order.');
            }
            if ($type === 'layer') {
                $rate = $change['new_laying_rate'] ?? null;
                if ((!is_int($rate) && !is_float($rate)) ||
                    !is_finite((float)$rate) || $rate < 0 || $rate > 100) {
                    throw new DailyRecordRevisionConflict('Invalid later Layer laying-rate projection.');
                }
            }
            $seen[$change['id']] = true;
            $lastDate = $change['record_date'];
            $lastId = $change['id'];
        }

        $applied = 0;
        foreach ($plan['changes'] as $change) {
            daily_record_revision_apply_continuity_change(
                $pdo, $type, $farmId, $cycleId, $animalType, $change
            );
            $applied++;
        }
        $plan['applied_count'] = $applied;
        return $plan;
    }
}
