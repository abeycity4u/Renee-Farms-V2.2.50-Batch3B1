<?php

declare(strict_types=1);

/**
 * V3.1 durable account credential delivery outbox.
 *
 * Responsibilities:
 * - persist one neutral credential-delivery job;
 * - claim one available job under a database row lock;
 * - recover abandoned processing leases;
 * - bound delivery attempts and retry delay;
 * - discard null-user jobs without credential delivery;
 * - delegate actual activation/reset mail to the shared credential
 *   delivery service;
 * - persist only non-secret outcome classifications.
 *
 * Non-responsibilities:
 * - account lookup;
 * - public enumeration policy;
 * - HTTP routing;
 * - CSRF or guest rate limiting;
 * - password/token mutation internals;
 * - raw mail content or transport exception persistence.
 */

require_once __DIR__ . '/account_credential_delivery.php';

const ACCOUNT_CREDENTIAL_OUTBOX_MAX_ATTEMPTS = 5;
const ACCOUNT_CREDENTIAL_OUTBOX_LEASE_SECONDS = 300;

if (!function_exists('account_credential_outbox_storage_ready')) {
    function account_credential_outbox_storage_ready(
        PDO $pdo
    ): bool {
        $stmt = $pdo->query(
            "SELECT
                (
                    SELECT COUNT(*)
                    FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME =
                          'account_credential_delivery_outbox'
                ) AS outbox_ready,
                (
                    SELECT COUNT(*)
                    FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'users'
                      AND COLUMN_NAME = 'credential_state'
                ) AS state_ready,
                (
                    SELECT COUNT(*)
                    FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME =
                          'account_credential_tokens'
                ) AS token_ready"
        );

        $row =
            $stmt->fetch(PDO::FETCH_ASSOC)
            ?: [];

        return (int)($row['outbox_ready'] ?? 0) === 1
            && (int)($row['state_ready'] ?? 0) === 1
            && (int)($row['token_ready'] ?? 0) === 1;
    }
}

if (!function_exists('account_credential_outbox_assert_ready')) {
    function account_credential_outbox_assert_ready(
        PDO $pdo
    ): void {
        if (!account_credential_outbox_storage_ready($pdo)) {
            throw new RuntimeException(
                'Account credential outbox storage is not ready.'
            );
        }
    }
}

if (!function_exists('account_credential_outbox_error_code')) {
    function account_credential_outbox_error_code(
        string $value,
        string $fallback = 'delivery_error'
    ): string {
        $value =
            strtolower(
                trim($value)
            );

        $value =
            preg_replace(
                '/[^a-z0-9_]+/',
                '_',
                $value
            ) ?? '';

        $value =
            trim(
                $value,
                '_'
            );

        if ($value === '') {
            $value = $fallback;
        }

        return substr(
            $value,
            0,
            80
        );
    }
}

if (!function_exists('account_credential_outbox_retry_delay_seconds')) {
    function account_credential_outbox_retry_delay_seconds(
        int $attemptCount
    ): int {
        if ($attemptCount <= 1) {
            return 60;
        }

        if ($attemptCount === 2) {
            return 300;
        }

        if ($attemptCount === 3) {
            return 900;
        }

        return 3600;
    }
}

if (!function_exists('account_credential_outbox_enqueue')) {
    function account_credential_outbox_enqueue(
        PDO $pdo,
        ?int $userId,
        string $purpose
    ): int {
        account_credential_outbox_assert_ready(
            $pdo
        );

        $purpose =
            account_credential_normalize_purpose(
                $purpose
            );

        if ($userId !== null && $userId < 1) {
            $userId = null;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO account_credential_delivery_outbox
                (
                    user_id,
                    purpose,
                    status,
                    attempt_count,
                    available_at
                )
             VALUES (
                    ?,
                    ?,
                    'pending',
                    0,
                    NOW()
             )"
        );

        $stmt->execute([
            $userId,
            $purpose,
        ]);

        $id =
            (int)$pdo->lastInsertId();

        if ($id < 1) {
            throw new RuntimeException(
                'Credential outbox enqueue did not return a durable job identity.'
            );
        }

        return $id;
    }
}

if (!function_exists('account_credential_outbox_claim_next')) {
    function account_credential_outbox_claim_next(
        PDO $pdo
    ): ?array {
        account_credential_outbox_assert_ready(
            $pdo
        );

        if ($pdo->inTransaction()) {
            throw new RuntimeException(
                'Credential outbox claim must start outside a database transaction.'
            );
        }

        $leaseSeconds =
            ACCOUNT_CREDENTIAL_OUTBOX_LEASE_SECONDS;

        $pdo->beginTransaction();

        try {
            /*
             * A processing row whose updated_at is older than the lease may
             * have been abandoned by a terminated worker. Reclaiming under
             * FOR UPDATE gives every claim a fresh processing lease.
             */
            $sql =
                "SELECT
                    id,
                    user_id,
                    purpose,
                    status,
                    attempt_count,
                    available_at,
                    updated_at
                 FROM account_credential_delivery_outbox
                 WHERE
                    (
                        status = 'pending'
                        AND available_at <= NOW()
                    )
                    OR
                    (
                        status = 'processing'
                        AND updated_at <= DATE_SUB(
                            NOW(),
                            INTERVAL "
                            . (int)$leaseSeconds
                            . " SECOND
                        )
                    )
                 ORDER BY
                    CASE
                        WHEN status = 'processing' THEN 0
                        ELSE 1
                    END,
                    available_at ASC,
                    id ASC
                 LIMIT 1
                 FOR UPDATE";

            $stmt =
                $pdo->query(
                    $sql
                );

            $job =
                $stmt->fetch(PDO::FETCH_ASSOC)
                ?: null;

            if ($job === null) {
                $pdo->commit();
                return null;
            }

            $jobId =
                (int)($job['id'] ?? 0);

            if ($jobId < 1) {
                throw new RuntimeException(
                    'Credential outbox selected an invalid job identity.'
                );
            }

            $recoveredLease =
                (string)($job['status'] ?? '')
                === 'processing';

            $attemptCountBeforeClaim =
                (int)($job['attempt_count'] ?? 0);

            /*
             * A stale processing lease that already consumed the final
             * allowed delivery attempt must be recovered only for terminal
             * classification. It must never increment to attempt six or
             * enter credential delivery again.
             */
            $attemptLimitReached =
                $recoveredLease
                && $attemptCountBeforeClaim
                    >= ACCOUNT_CREDENTIAL_OUTBOX_MAX_ATTEMPTS;

            if ($attemptLimitReached) {
                $update = $pdo->prepare(
                    "UPDATE account_credential_delivery_outbox
                     SET
                        status = 'processing',
                        processed_at = NULL,
                        last_error_code =
                            'attempt_limit_recovered',
                        updated_at = NOW()
                     WHERE id = ?
                       AND status = 'processing'"
                );

                $update->execute([
                    $jobId,
                ]);
            } else {
                $update = $pdo->prepare(
                    "UPDATE account_credential_delivery_outbox
                     SET
                        status = 'processing',
                        attempt_count = attempt_count + 1,
                        processed_at = NULL,
                        last_error_code = ?
                     WHERE id = ?"
                );

                $update->execute([
                    $recoveredLease
                        ? 'lease_recovered'
                        : null,
                    $jobId,
                ]);
            }

            if ($update->rowCount() !== 1) {
                throw new RuntimeException(
                    'Credential outbox claim could not persist processing ownership.'
                );
            }

            $pdo->commit();

            $job['status'] =
                'processing';

            $job['attempt_count'] =
                $attemptLimitReached
                    ? $attemptCountBeforeClaim
                    : $attemptCountBeforeClaim + 1;

            $job['lease_recovered'] =
                $recoveredLease;

            $job['attempt_limit_reached'] =
                $attemptLimitReached;

            return $job;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }
}

if (!function_exists('account_credential_outbox_mark_terminal')) {
    function account_credential_outbox_mark_terminal(
        PDO $pdo,
        int $jobId,
        string $status,
        ?string $errorCode = null
    ): void {
        if ($jobId < 1) {
            throw new InvalidArgumentException(
                'A valid credential outbox job is required.'
            );
        }

        if (!in_array(
            $status,
            [
                'sent',
                'discarded',
                'failed',
            ],
            true
        )) {
            throw new InvalidArgumentException(
                'Credential outbox terminal status is invalid.'
            );
        }

        $errorCode =
            $errorCode === null
                ? null
                : account_credential_outbox_error_code(
                    $errorCode
                );

        $stmt = $pdo->prepare(
            "UPDATE account_credential_delivery_outbox
             SET
                status = ?,
                processed_at = NOW(),
                last_error_code = ?
             WHERE id = ?
               AND status = 'processing'"
        );

        $stmt->execute([
            $status,
            $errorCode,
            $jobId,
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException(
                'Credential outbox terminal transition lost processing ownership.'
            );
        }
    }
}

if (!function_exists('account_credential_outbox_retry_or_fail')) {
    function account_credential_outbox_retry_or_fail(
        PDO $pdo,
        int $jobId,
        int $attemptCount,
        string $errorCode
    ): string {
        if ($jobId < 1) {
            throw new InvalidArgumentException(
                'A valid credential outbox job is required.'
            );
        }

        $errorCode =
            account_credential_outbox_error_code(
                $errorCode
            );

        if (
            $attemptCount
            >= ACCOUNT_CREDENTIAL_OUTBOX_MAX_ATTEMPTS
        ) {
            account_credential_outbox_mark_terminal(
                $pdo,
                $jobId,
                'failed',
                $errorCode
            );

            return 'failed';
        }

        $delaySeconds =
            account_credential_outbox_retry_delay_seconds(
                $attemptCount
            );

        $stmt = $pdo->prepare(
            "UPDATE account_credential_delivery_outbox
             SET
                status = 'pending',
                available_at = DATE_ADD(
                    NOW(),
                    INTERVAL ? SECOND
                ),
                processed_at = NULL,
                last_error_code = ?
             WHERE id = ?
               AND status = 'processing'"
        );

        $stmt->execute([
            $delaySeconds,
            $errorCode,
            $jobId,
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException(
                'Credential outbox retry transition lost processing ownership.'
            );
        }

        return 'retry';
    }
}

if (!function_exists('account_credential_outbox_process_one')) {
    function account_credential_outbox_process_one(
        PDO $pdo
    ): array {
        $job =
            account_credential_outbox_claim_next(
                $pdo
            );

        if ($job === null) {
            return [
                'job_found' => false,
                'outcome' => 'none',
            ];
        }

        $jobId =
            (int)($job['id'] ?? 0);

        $userId =
            isset($job['user_id'])
                && $job['user_id'] !== null
                    ? (int)$job['user_id']
                    : null;

        $purpose =
            (string)($job['purpose'] ?? '');

        $attemptCount =
            (int)($job['attempt_count'] ?? 0);

        if ($userId === null || $userId < 1) {
            account_credential_outbox_mark_terminal(
                $pdo,
                $jobId,
                'discarded',
                'no_eligible_account'
            );

            return [
                'job_found' => true,
                'outcome' => 'discarded',
                'job_id' => $jobId,
                'attempt_count' => $attemptCount,
            ];
        }

        /*
         * A recovered stale lease at the attempt ceiling represents an
         * ambiguous final delivery attempt. Do not send again. Preserve the
         * five-attempt ceiling and terminate with a non-secret operator code.
         */
        if (
            ($job['attempt_limit_reached'] ?? false)
            === true
        ) {
            account_credential_outbox_mark_terminal(
                $pdo,
                $jobId,
                'failed',
                'attempt_limit_recovered'
            );

            return [
                'job_found' => true,
                'outcome' => 'failed',
                'job_id' => $jobId,
                'attempt_count' => $attemptCount,
            ];
        }

        try {
            $delivery =
                account_credential_send(
                    $pdo,
                    $userId,
                    $purpose
                );
        } catch (Throwable $deliveryError) {
            /*
             * Never log username, workspace, email, token, URL or exception
             * message. The durable job id and exception class are sufficient
             * for operator correlation without account enumeration data.
             */
            error_log(
                'Credential outbox job '
                . $jobId
                . ' delivery exception ['
                . get_class($deliveryError)
                . '].'
            );

            $outcome =
                account_credential_outbox_retry_or_fail(
                    $pdo,
                    $jobId,
                    $attemptCount,
                    'delivery_exception'
                );

            return [
                'job_found' => true,
                'outcome' => $outcome,
                'job_id' => $jobId,
                'attempt_count' => $attemptCount,
            ];
        }

        if (($delivery['sent'] ?? false) === true) {
            /*
             * Mail transport has already accepted the message at this point.
             * If this DB finalization itself fails, leave the row processing;
             * the lease recovery path may later retry. Credential-token
             * supersession makes a later retry safe, though delivery is
             * intentionally at-least-once rather than exactly-once.
             */
            account_credential_outbox_mark_terminal(
                $pdo,
                $jobId,
                'sent'
            );

            return [
                'job_found' => true,
                'outcome' => 'sent',
                'job_id' => $jobId,
                'attempt_count' => $attemptCount,
            ];
        }

        $outcome =
            account_credential_outbox_retry_or_fail(
                $pdo,
                $jobId,
                $attemptCount,
                'mail_not_sent'
            );

        return [
            'job_found' => true,
            'outcome' => $outcome,
            'job_id' => $jobId,
            'attempt_count' => $attemptCount,
        ];
    }
}
