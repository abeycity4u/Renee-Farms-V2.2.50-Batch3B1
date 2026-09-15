<?php
/**
 * V3 poultry cycle onboarding orchestration.
 *
 * This helper does not own production-cycle creation SQL, acquisition SQL,
 * lifecycle SQL, economics, or Manage Cycle calculations.
 *
 * It coordinates the two already-canonical poultry facts required when a NEW
 * poultry cycle is deliberately initialized:
 *   1. flock entry / acquisition;
 *   2. explicit starting biological phase.
 *
 * If the caller already owns a transaction, that transaction is preserved.
 * Otherwise this helper wraps both facts atomically.
 */

require_once __DIR__ . '/poultry_cycle_acquisition.php';
require_once __DIR__ . '/poultry_cycle_lifecycle.php';

if (!function_exists('poultry_cycle_onboarding_record_initial')) {
    function poultry_cycle_onboarding_record_initial(
        PDO $pdo,
        int $farmId,
        int $cycleId,
        array $input,
        ?int $userId
    ): array {
        $productionType = strtolower(
            trim((string)($input['production_type'] ?? ''))
        );
        $startDate = trim((string)($input['start_date'] ?? ''));
        $quantity = (int)($input['quantity'] ?? 0);
        $ageDays = (int)($input['age_days'] ?? 0);
        $acquisitionType = strtolower(
            trim((string)($input['acquisition_type'] ?? ''))
        );
        $initialPhase = strtolower(
            trim((string)($input['initial_phase'] ?? ''))
        );
        $sourceName = trim((string)($input['source_name'] ?? ''));
        $referenceNo = trim((string)($input['reference_no'] ?? ''));
        $requestToken = trim((string)($input['request_token'] ?? ''));

        $totalCost = $input['total_cost'] ?? null;
        if ($totalCost !== null) {
            if (!is_numeric($totalCost) || (float)$totalCost < 0) {
                throw new InvalidArgumentException(
                    'Enter a valid total bird acquisition amount.'
                );
            }
            $totalCost = (float)$totalCost;
        }

        $allowedAcquisitions =
            poultry_acquisition_allowed_types($productionType);

        if (!isset($allowedAcquisitions[$acquisitionType])) {
            throw new InvalidArgumentException(
                'Select a valid flock entry type for this poultry cycle.'
            );
        }

        $allowedPhases =
            poultry_lifecycle_allowed_phases($productionType);

        if (!isset($allowedPhases[$initialPhase])) {
            throw new InvalidArgumentException(
                'Select a valid starting biological stage for this poultry cycle.'
            );
        }

        if ($quantity <= 0) {
            throw new InvalidArgumentException(
                'Starting flock size must be at least 1 bird for a poultry cycle.'
            );
        }

        if ($ageDays < 1) {
            throw new InvalidArgumentException(
                'Start age must be at least 1 day.'
            );
        }

        if (
            $acquisitionType === 'purchased_point_of_lay'
            && $initialPhase !== 'production'
        ) {
            throw new InvalidArgumentException(
                'Purchased Point-of-Lay birds must start in the Production stage. '
                . 'The platform will not create artificial Rearing history for purchased POL birds.'
            );
        }

        $startedTransaction = !$pdo->inTransaction();

        if ($startedTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $acquisitionId = poultry_acquisition_record(
                $pdo,
                $farmId,
                $cycleId,
                $acquisitionType,
                $startDate,
                $quantity,
                $ageDays,
                $totalCost,
                $sourceName,
                $referenceNo,
                null,
                $userId,
                $requestToken
            );

            $phaseId = poultry_lifecycle_record_initial_phase(
                $pdo,
                $farmId,
                $cycleId,
                $initialPhase,
                $startDate,
                null,
                $userId
            );

            if ($startedTransaction) {
                $pdo->commit();
            }

            return [
                'acquisition_id' => $acquisitionId,
                'phase_id' => $phaseId,
            ];
        } catch (Throwable $error) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $error;
        }
    }
}
