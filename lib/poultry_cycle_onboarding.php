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

if (!function_exists('poultry_cycle_onboarding_correct_initial_acquisition')) {
    /**
     * Replace one active initial acquisition fact without deleting history.
     *
     * The erroneous active row is voided with the farmer's correction reason,
     * then a corrected active row is recorded with the same entry identity
     * facts and the corrected quantity / total acquisition cost.
     */
    function poultry_cycle_onboarding_correct_initial_acquisition(
        PDO $pdo,
        int $farmId,
        int $cycleId,
        int $quantity,
        ?float $totalCost,
        string $reason,
        ?string $requestToken,
        ?int $userId
    ): array {
        if ($quantity <= 0) {
            throw new InvalidArgumentException(
                'Opening headcount must be at least 1 bird for a poultry cycle.'
            );
        }

        if ($totalCost !== null && $totalCost < 0) {
            throw new InvalidArgumentException(
                'Total acquisition cost cannot be negative.'
            );
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException(
                'Enter a correction reason for the opening or acquisition change.'
            );
        }

        $reasonLength = function_exists('mb_strlen')
            ? mb_strlen($reason)
            : strlen($reason);

        if ($reasonLength < 4) {
            throw new InvalidArgumentException(
                'Correction reason must briefly explain the change.'
            );
        }

        if ($reasonLength > 255) {
            throw new InvalidArgumentException(
                'Correction reason must be 255 characters or fewer.'
            );
        }

        $startedTransaction = !$pdo->inTransaction();

        if ($startedTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $history = poultry_acquisition_history(
                $pdo,
                $farmId,
                $cycleId
            );

            $active = array_values(
                array_filter(
                    $history,
                    static function (array $row): bool {
                        return empty($row['voided_at']);
                    }
                )
            );

            if (count($active) !== 1) {
                throw new PoultryAcquisitionException(
                    'This cycle must have exactly one active initial acquisition entry before Opening Headcount or Total Acquisition Cost can be corrected here.'
                );
            }

            $current = $active[0];

            $currentCost =
                $current['total_cost'] === null
                || $current['total_cost'] === ''
                    ? null
                    : (float)$current['total_cost'];

            $costSame =
                (
                    $currentCost === null
                    && $totalCost === null
                )
                || (
                    $currentCost !== null
                    && $totalCost !== null
                    && abs($currentCost - $totalCost) < 0.005
                );

            if (
                (int)$current['quantity'] === $quantity
                && $costSame
            ) {
                if ($startedTransaction) {
                    $pdo->commit();
                }

                return [
                    'changed' => false,
                    'previous_acquisition_id' =>
                        (int)$current['id'],
                    'acquisition_id' =>
                        (int)$current['id'],
                ];
            }

            if (
                (string)$current['acquisition_type'] !== 'internal_transfer'
                && $totalCost === null
            ) {
                throw new InvalidArgumentException(
                    'Enter the actual total amount paid for purchased birds.'
                );
            }

            poultry_acquisition_void(
                $pdo,
                $farmId,
                (int)$current['id'],
                $reason,
                $userId
            );

            $newId = poultry_acquisition_record(
                $pdo,
                $farmId,
                $cycleId,
                (string)$current['acquisition_type'],
                (string)$current['acquisition_date'],
                $quantity,
                (int)$current['age_days'],
                $totalCost,
                isset($current['source_name'])
                    ? (string)$current['source_name']
                    : null,
                isset($current['reference_no'])
                    ? (string)$current['reference_no']
                    : null,
                isset($current['notes'])
                    ? (string)$current['notes']
                    : null,
                $userId,
                $requestToken
            );

            if (function_exists('audit_log_event')) {
                audit_log_event(
                    'poultry_cycle_initial_acquisition_corrected',
                    'poultry_cycle_acquisition',
                    $newId,
                    [
                        'cycle_id' =>
                            $cycleId,
                        'previous_acquisition_id' =>
                            (int)$current['id'],
                        'acquisition_id' =>
                            $newId,
                        'previous_quantity' =>
                            (int)$current['quantity'],
                        'quantity' =>
                            $quantity,
                        'previous_total_cost' =>
                            $currentCost,
                        'total_cost' =>
                            $totalCost,
                        'correction_reason' =>
                            $reason,
                    ]
                );
            }

            if ($startedTransaction) {
                $pdo->commit();
            }

            return [
                'changed' => true,
                'previous_acquisition_id' =>
                    (int)$current['id'],
                'acquisition_id' =>
                    $newId,
            ];

        } catch (Throwable $error) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $error;
        }
    }
}
