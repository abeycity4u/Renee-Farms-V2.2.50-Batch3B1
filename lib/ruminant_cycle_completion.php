<?php
/**
 * Renee Farms V3.0.1 — ruminant production-cycle completion orchestration.
 *
 * End Production is an economic/production-cycle boundary. It does not:
 * - sell, cull, slaughter, kill or transfer an animal;
 * - create a population exit;
 * - close or rewrite animal cycle memberships.
 *
 * Any membership extending beyond the selected end date must be resolved
 * first through its canonical animal lifecycle, transfer, or membership
 * correction workflow.
 */

require_once __DIR__ . '/production_cycle_service.php';
require_once __DIR__ . '/ruminant_cycle_membership.php';

if (!class_exists('RuminantCycleCompletionException')) {
    class RuminantCycleCompletionException extends RuntimeException {}
}

if (!function_exists('ruminant_cycle_end_production')) {
    function ruminant_cycle_end_production(
        PDO $pdo,
        int $farmId,
        int $cycleId,
        string $endDate,
        ?int $userId
    ): array {
        if ($farmId <= 0 || $cycleId <= 0) {
            throw new InvalidArgumentException(
                'Select a valid ruminant production cycle.'
            );
        }

        $endDate = trim($endDate);

        if (!production_cycle_valid_date($endDate)) {
            throw new InvalidArgumentException(
                'Enter a valid production end date.'
            );
        }

        production_cycle_assert_user_id(
            $userId
        );

        $startedTransaction =
            !$pdo->inTransaction();

        if ($startedTransaction) {
            $pdo->beginTransaction();
        }

        try {
            /*
             * Lock the cycle first. Membership assignment also locks this
             * cycle row, so a new membership cannot race canonical closure.
             */
            $cycle =
                production_cycle_get(
                    $pdo,
                    $farmId,
                    $cycleId,
                    true
                );

            if (
                strtolower(
                    (string)$cycle['farm_type']
                ) !== 'ruminant'
            ) {
                throw new RuminantCycleCompletionException(
                    'End Production is available here only for a ruminant cycle.'
                );
            }

            if (
                strtolower(
                    (string)$cycle['status']
                ) !== 'active'
            ) {
                throw new RuminantCycleCompletionException(
                    'Only an active ruminant production cycle can be ended.'
                );
            }

            if (
                $endDate
                < (string)$cycle['start_date']
            ) {
                throw new InvalidArgumentException(
                    'Production end date cannot be earlier than the cycle start date.'
                );
            }

            /*
             * Memberships are explicit economic attribution ranges.
             *
             * We intentionally do not close them here. An open or later-ending
             * membership may represent an animal that still needs a canonical
             * transfer or lifecycle exit, and its provenance must not be
             * fabricated by cycle completion.
             */
            $blockers =
                ruminant_cycle_completion_membership_blockers(
                    $pdo,
                    $farmId,
                    $cycleId,
                    $endDate,
                    true
                );

            if ($blockers) {
                $count =
                    count($blockers);

                throw new RuminantCycleCompletionException(
                    'End Production cannot continue because '
                    . $count
                    . ' animal cycle '
                    . (
                        $count === 1
                            ? 'membership extends'
                            : 'memberships extend'
                    )
                    . ' beyond the selected production end date. '
                    . 'Resolve the animal membership, transfer, or real lifecycle exit first. '
                    . 'No animal or membership was changed automatically.'
                );
            }

            /*
             * Positive population is valid here.
             *
             * Canonical closure records the ledger-derived live population as
             * closing_headcount. It does not manufacture physical exit events.
             */
            $closingHeadcount =
                production_cycle_close_v3(
                    $pdo,
                    $farmId,
                    $cycleId,
                    $endDate,
                    $userId
                );

            if ($startedTransaction) {
                $pdo->commit();
            }

            return [
                'cycle_id' =>
                    $cycleId,

                'end_date' =>
                    $endDate,

                'closing_headcount' =>
                    $closingHeadcount,

                'membership_blockers' =>
                    0,
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
