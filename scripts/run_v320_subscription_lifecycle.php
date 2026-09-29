<?php

declare(strict_types=1);

/**
 * Scheduled subscription lifecycle worker.
 *
 * Thin CLI caller only:
 * - discovers current trial/active farms with an end timestamp;
 * - delegates every expiry decision and mutation to the canonical
 *   subscription_lifecycle_refresh_farm() authority;
 * - does not duplicate lifecycle SQL or subscription-history logic.
 */

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/subscription_lifecycle.php';

if (!function_exists('subscription_lifecycle_worker_run')) {
    function subscription_lifecycle_worker_run(
        PDO $pdo,
        ?DateTimeImmutable $now = null
    ): array {
        $now =
            $now
            ?? new DateTimeImmutable('now');

        $stmt = $pdo->query(
            "SELECT id
             FROM farms
             WHERE subscription_status IN ('trial', 'active')
               AND subscription_ends_at IS NOT NULL
             ORDER BY id"
        );

        $farmIds =
            array_map(
                'intval',
                $stmt->fetchAll(PDO::FETCH_COLUMN)
            );

        $checked = 0;
        $transitioned = 0;
        $failed = 0;

        foreach ($farmIds as $farmId) {
            if ($farmId < 1) {
                continue;
            }

            $checked++;

            try {
                $before =
                    subscription_lifecycle_farm(
                        $pdo,
                        $farmId,
                        false
                    );

                if (!$before) {
                    continue;
                }

                $beforeStatus =
                    strtolower(
                        trim(
                            (string)(
                                $before['subscription_status']
                                ?? ''
                            )
                        )
                    );

                $after =
                    subscription_lifecycle_refresh_farm(
                        $pdo,
                        $farmId,
                        $now
                    );

                if (!$after) {
                    continue;
                }

                $afterStatus =
                    strtolower(
                        trim(
                            (string)(
                                $after['subscription_status']
                                ?? ''
                            )
                        )
                    );

                if (
                    in_array(
                        $beforeStatus,
                        ['trial', 'active'],
                        true
                    )
                    && $afterStatus === 'past_due'
                ) {
                    $transitioned++;
                }

            } catch (Throwable $e) {
                $failed++;

                error_log(
                    'Scheduled subscription lifecycle refresh failed for farm '
                    . $farmId
                    . ': '
                    . $e->getMessage()
                );
            }
        }

        return [
            'checked' => $checked,
            'transitioned' => $transitioned,
            'failed' => $failed,
        ];
    }
}

/*
 * Execute only when this file itself is the CLI entrypoint.
 * Including it from a verifier/test harness defines the worker function
 * without starting a production lifecycle pass.
 */
$isDirectCliExecution =
    PHP_SAPI === 'cli'
    && isset($_SERVER['SCRIPT_FILENAME'])
    && realpath(
        (string)$_SERVER['SCRIPT_FILENAME']
    ) === __FILE__;

if (!$isDirectCliExecution) {
    return;
}

try {
    $result =
        subscription_lifecycle_worker_run(
            $pdo
        );
} catch (Throwable $e) {
    fwrite(
        STDERR,
        'FAIL: unable to run subscription lifecycle worker: '
        . $e->getMessage()
        . PHP_EOL
    );

    exit(1);
}

echo "PASS: subscription lifecycle worker completed.\n";
echo "CHECKED=" . (int)$result['checked'] . PHP_EOL;
echo "TRANSITIONED=" . (int)$result['transitioned'] . PHP_EOL;
echo "FAILED=" . (int)$result['failed'] . PHP_EOL;

exit(
    (int)$result['failed'] === 0
        ? 0
        : 1
);
