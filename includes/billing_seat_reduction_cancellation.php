<?php
/**
 * V2.3 scheduled seat-reduction cancellation foundation.
 *
 * Contract:
 * - caller supplies the authenticated tenant farm id and durable request id;
 * - this service owns its database transaction;
 * - tenant farm is locked before the scheduled reduction row so cancellation
 *   serializes with paid-renewal seat application;
 * - only a tenant-bound scheduled remove request may be cancelled;
 * - an already-cancelled request is returned idempotently;
 * - applied/failed/add/foreign-tenant requests fail closed;
 * - cancellation changes only durable seat-change workflow state;
 * - current paid-term entitlement, subscriptions and payment attempts are not
 *   changed;
 * - no payment-provider or network work is performed.
 */

require_once __DIR__
    . '/billing_seat_change_request.php';

if (!function_exists(
    'billing_seat_reduction_cancel_scheduled'
)) {
    function billing_seat_reduction_cancel_scheduled(
        PDO $pdo,
        int $farmId,
        int $requestId
    ): array {
        if ($farmId < 1) {
            throw new InvalidArgumentException(
                'A valid tenant farm is required for seat-reduction cancellation.'
            );
        }

        if ($requestId < 1) {
            throw new InvalidArgumentException(
                'A valid scheduled seat-reduction request is required.'
            );
        }

        if (!billing_seat_change_ready($pdo)) {
            throw new RuntimeException(
                'Seat-change storage is not ready for scheduled reduction cancellation.'
            );
        }

        if ($pdo->inTransaction()) {
            throw new RuntimeException(
                'Scheduled seat-reduction cancellation must own its database transaction.'
            );
        }

        $pdo->beginTransaction();

        try {
            /*
             * Match renewal application lock order:
             * tenant farm first, then durable seat-change request.
             *
             * This prevents cancellation racing a paid renewal that is
             * consuming the same scheduled reduction.
             */
            $farmLock = $pdo->prepare(
                "SELECT id
                 FROM farms
                 WHERE id = ?
                   AND slug <> 'owner'
                 LIMIT 1
                 FOR UPDATE"
            );

            $farmLock->execute([
                $farmId,
            ]);

            if (!$farmLock->fetchColumn()) {
                throw new RuntimeException(
                    'Tenant farm could not be locked for scheduled seat-reduction cancellation.'
                );
            }

            $requestRow =
                billing_seat_change_request_by_id(
                    $pdo,
                    $requestId,
                    true
                );

            if (!$requestRow) {
                throw new InvalidArgumentException(
                    'Scheduled seat-reduction request could not be found.'
                );
            }

            $requestState =
                billing_seat_change_row_contract(
                    $requestRow
                );

            $contract =
                is_array(
                    $requestState['contract']
                        ?? null
                )
                    ? $requestState['contract']
                    : [];

            if ((int)(
                $contract['farm_id']
                    ?? 0
            ) !== $farmId) {
                throw new RuntimeException(
                    'Scheduled seat-reduction request does not belong to the authenticated tenant.'
                );
            }

            if (($contract['change_kind'] ?? '')
                !== 'remove') {
                throw new InvalidArgumentException(
                    'Only a scheduled seat reduction may be cancelled through this workflow.'
                );
            }

            if (($requestState['status'] ?? '')
                === 'cancelled') {
                if (trim((string)(
                    $requestState['cancelled_at']
                        ?? ''
                )) === '') {
                    throw new RuntimeException(
                        'Cancelled seat-reduction request is missing its cancellation timestamp.'
                    );
                }

                if (trim((string)(
                    $requestState['applied_at']
                        ?? ''
                )) !== '') {
                    throw new RuntimeException(
                        'Cancelled seat-reduction request cannot also be applied.'
                    );
                }

                $pdo->commit();

                return [
                    'changed' => false,
                    'idempotent' => true,
                    'request' => $requestState,
                ];
            }

            if (($requestState['status'] ?? '')
                !== 'scheduled') {
                throw new RuntimeException(
                    'Only a currently scheduled seat reduction can be cancelled.'
                );
            }

            if (trim((string)(
                $requestState['applied_at']
                    ?? ''
            )) !== ''
                || trim((string)(
                    $requestState['cancelled_at']
                        ?? ''
                )) !== '') {
                throw new RuntimeException(
                    'Scheduled seat-reduction workflow metadata is inconsistent.'
                );
            }

            $update = $pdo->prepare(
                "UPDATE billing_seat_change_requests
                 SET status = 'cancelled',
                     cancelled_at = CURRENT_TIMESTAMP
                 WHERE id = ?
                   AND farm_id = ?
                   AND change_kind = 'remove'
                   AND status = 'scheduled'
                   AND applied_at IS NULL
                   AND cancelled_at IS NULL"
            );

            $update->execute([
                $requestId,
                $farmId,
            ]);

            if ($update->rowCount() !== 1) {
                throw new RuntimeException(
                    'Scheduled seat reduction could not be cancelled exactly once.'
                );
            }

            $cancelledRow =
                billing_seat_change_request_by_id(
                    $pdo,
                    $requestId,
                    false
                );

            if (!$cancelledRow) {
                throw new RuntimeException(
                    'Cancelled seat-reduction request could not be reloaded.'
                );
            }

            $cancelledState =
                billing_seat_change_row_contract(
                    $cancelledRow
                );

            $cancelledContract =
                is_array(
                    $cancelledState['contract']
                        ?? null
                )
                    ? $cancelledState['contract']
                    : [];

            if (($cancelledState['status'] ?? '')
                    !== 'cancelled'
                || trim((string)(
                    $cancelledState['cancelled_at']
                        ?? ''
                )) === ''
                || trim((string)(
                    $cancelledState['applied_at']
                        ?? ''
                )) !== ''
                || ($cancelledContract[
                    'change_kind'
                ] ?? '') !== 'remove'
                || (int)(
                    $cancelledContract['farm_id']
                        ?? 0
                ) !== $farmId) {
                throw new RuntimeException(
                    'Cancelled seat reduction failed post-update verification.'
                );
            }

            $pdo->commit();

            return [
                'changed' => true,
                'idempotent' => false,
                'request' => $cancelledState,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }
}
?>
