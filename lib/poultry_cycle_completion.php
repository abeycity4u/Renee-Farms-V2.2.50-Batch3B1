<?php
/**
 * Renee Farms V3.0 — poultry production-cycle completion orchestration.
 *
 * One farmer-facing End Production action:
 * - closes the production cycle through the canonical V3 cycle service;
 * - ends any still-open biological stage on the same date;
 * - preserves a single transaction across both operations.
 *
 * This helper owns no lifecycle, population, or production-cycle SQL.
 */

require_once __DIR__ . '/production_cycle_service.php';
require_once __DIR__ . '/poultry_cycle_lifecycle.php';

if (!function_exists('poultry_cycle_end_production')) {
    function poultry_cycle_end_production(
        PDO $pdo,
        int $farmId,
        int $cycleId,
        string $endDate,
        ?int $userId
    ): array {
        if ($farmId <= 0 || $cycleId <= 0) {
            throw new InvalidArgumentException(
                'Select a valid poultry production cycle.'
            );
        }

        $endDate = trim($endDate);

        if (!production_cycle_valid_date($endDate)) {
            throw new InvalidArgumentException(
                'Enter a valid production end date.'
            );
        }

        production_cycle_assert_user_id($userId);

        $startedTransaction = !$pdo->inTransaction();

        if ($startedTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $cycle = production_cycle_get(
                $pdo,
                $farmId,
                $cycleId,
                true
            );

            if (
                strtolower((string)$cycle['farm_type'])
                !== 'poultry'
            ) {
                throw new ProductionCycleException(
                    'End Production is available here only for a poultry cycle.'
                );
            }

            $productionType = strtolower(
                (string)$cycle['production_type']
            );

            if (
                !in_array(
                    $productionType,
                    ['layer', 'broiler'],
                    true
                )
            ) {
                throw new ProductionCycleException(
                    'This poultry production type is not supported.'
                );
            }

            if (
                strtolower((string)$cycle['status'])
                !== 'active'
            ) {
                throw new ProductionCycleException(
                    'Only an active production cycle can be ended.'
                );
            }

            if ($endDate < (string)$cycle['start_date']) {
                throw new InvalidArgumentException(
                    'Production end date cannot be earlier than the cycle start date.'
                );
            }

            $currentPhase = poultry_lifecycle_current_phase(
                $pdo,
                $farmId,
                $cycleId
            );

            /*
             * Let the canonical V3 close service own population eligibility,
             * future-movement validation, and closing-headcount calculation.
             *
             * Because it participates in this caller transaction, any later
             * lifecycle failure also rolls the cycle closure back.
             */
            $closingHeadcount = production_cycle_close_v3(
                $pdo,
                $farmId,
                $cycleId,
                $endDate,
                $userId
            );

            $endedPhase = null;

            if ($currentPhase !== null) {
                /*
                 * cycleClosing=true means this is not an ordinary biological
                 * transition. A flock may legitimately end during Rearing or
                 * Growing without inventing a Production/Harvest transition.
                 */
                poultry_lifecycle_end_current_phase(
                    $pdo,
                    $farmId,
                    $cycleId,
                    $endDate,
                    $userId,
                    true
                );

                $endedPhase =
                    (string)$currentPhase['phase'];
            }

            if ($startedTransaction) {
                $pdo->commit();
            }

            return [
                'cycle_id' => $cycleId,
                'end_date' => $endDate,
                'ended_phase' => $endedPhase,
                'closing_headcount' => $closingHeadcount,
            ];
        } catch (Throwable $error) {
            if (
                $startedTransaction
                && $pdo->inTransaction()
            ) {
                $pdo->rollBack();
            }

            throw $error;
        }
    }
}
