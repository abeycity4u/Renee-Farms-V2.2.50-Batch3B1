<?php
/**
 * V2.3 Gap D Phase 3B Foundation 3 verifier.
 *
 * Proves the shared compensating-history primitive:
 * - accepts one explicit same-tenant immutable history source;
 * - validates the source's own snapshot hash;
 * - carries billing/provider metadata from that explicit source;
 * - refuses to append unless current runtime state exactly matches
 *   the chosen historical source snapshot;
 * - never uses the latest history row as compensation metadata;
 * - performs no provider/network operation.
 *
 * Read-only verifier. It never calls the append function.
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__
    . '/../includes/subscription_seat_policy.php';
require_once __DIR__
    . '/../includes/subscription_record.php';

$root =
    dirname(__DIR__);

$servicePath =
    $root
    . '/includes/subscription_record.php';

$source =
    (string)file_get_contents(
        $servicePath
    );

$checks = 0;
$failures = 0;

$check = static function (
    bool $ok,
    string $message
) use (&$checks, &$failures): void {
    $checks++;

    echo ($ok ? 'PASS' : 'FAIL')
        . ': '
        . $message
        . PHP_EOL;

    if (!$ok) {
        $failures++;
    }
};

$check(
    function_exists(
        'subscription_record_history_source_contract'
    ),
    'explicit immutable history-source contract exists'
);

$check(
    function_exists(
        'subscription_record_append_from_history_source'
    ),
    'explicit compensating-history append primitive exists'
);

$check(
    function_exists(
        'subscription_record_normalize_reason'
    )
    && function_exists(
        'subscription_record_normalize_actor'
    ),
    'record reason and actor normalization are shared centrally'
);

$reflection =
    new ReflectionFunction(
        'subscription_record_append_from_history_source'
    );

$lines =
    file(
        $servicePath
    );

$appendSource =
    implode(
        '',
        array_slice(
            $lines,
            $reflection->getStartLine() - 1,
            $reflection->getEndLine()
                - $reflection->getStartLine()
                + 1
        )
    );

$check(
    strpos(
        $appendSource,
        '$pdo->inTransaction()'
    ) !== false,
    'compensating append requires caller transaction'
);

$check(
    strpos(
        $appendSource,
        'subscription_record_history_source_contract('
    ) !== false,
    'compensating append resolves one explicit history source'
);

$check(
    strpos(
        $appendSource,
        'subscription_record_build_snapshot('
    ) !== false,
    'compensating append rebuilds current runtime snapshot'
);

$check(
    strpos(
        $appendSource,
        'hash_equals('
    ) !== false
    && strpos(
        $appendSource,
        "'snapshot_hash'"
    ) !== false,
    'runtime state must hash-match the chosen source'
);

$check(
    strpos(
        $appendSource,
        'subscription_record_latest('
    ) === false
    && strpos(
        $appendSource,
        '$latest'
    ) === false,
    'compensating append never sources metadata from latest history'
);

$check(
    strpos(
        $appendSource,
        '$source[\'billing_interval\']'
    ) !== false
    && strpos(
        $appendSource,
        '$source[\'amount\']'
    ) !== false
    && strpos(
        $appendSource,
        '$source[\'currency\']'
    ) !== false,
    'billing interval amount and currency come from explicit source'
);

$check(
    strpos(
        $appendSource,
        "'provider_subscription_id'"
    ) !== false
    && strpos(
        $appendSource,
        "'current_period_ends_at'"
    ) !== false,
    'provider lineage and paid-period metadata come from explicit source'
);

$check(
    preg_match(
        '/INSERT\s+INTO\s+subscriptions/i',
        $appendSource
    ) === 1,
    'compensating primitive appends immutable subscription history'
);

$check(
    preg_match(
        '/\bUPDATE\s+subscriptions\b/i',
        $appendSource
    ) === 0
    && preg_match(
        '/\bDELETE\s+FROM\s+subscriptions\b/i',
        $appendSource
    ) === 0,
    'compensating primitive never rewrites or deletes history'
);

$check(
    stripos(
        $appendSource,
        'curl_'
    ) === false
    && stripos(
        $appendSource,
        'paystack'
    ) === false
    && stripos(
        $appendSource,
        'http://'
    ) === false
    && stripos(
        $appendSource,
        'https://'
    ) === false,
    'compensating primitive performs no provider/network operation'
);

/*
 * Validate every currently known predecessor source through the new
 * centralized source contract.
 */
$rows =
    $pdo->query(
        "SELECT
             a.id AS attempt_id,
             a.farm_id,
             a.applied_subscription_record_id AS applied_id,
             (
                 SELECT MAX(prev.id)
                 FROM subscriptions prev
                 WHERE prev.farm_id =
                       a.farm_id
                   AND prev.id <
                       a.applied_subscription_record_id
             ) AS predecessor_id
         FROM billing_payment_attempts a
         WHERE a.purpose = 'subscription'
           AND a.applied_subscription_record_id
               IS NOT NULL
         ORDER BY a.id"
    )->fetchAll(PDO::FETCH_ASSOC);

$validSources = 0;

foreach ($rows as $row) {
    $predecessorId =
        (int)$row['predecessor_id'];

    try {
        $contract =
            subscription_record_history_source_contract(
                $pdo,
                (int)$row['farm_id'],
                $predecessorId
            );

        $ok =
            (int)$contract['id']
                === $predecessorId
            && (int)$contract['farm_id']
                === (int)$row['farm_id']
            && preg_match(
                '/^[a-f0-9]{64}$/',
                (string)$contract[
                    'snapshot_hash'
                ]
            ) === 1
            && in_array(
                (string)$contract[
                    'billing_interval'
                ],
                [
                    'monthly',
                    'annual',
                ],
                true
            )
            && preg_match(
                '/^[A-Z]{3}$/',
                (string)$contract[
                    'currency'
                ]
            ) === 1;

        echo 'SOURCE_ATTEMPT_'
            . (int)$row['attempt_id']
            . '='
            . ($ok ? 'PASS' : 'FAIL')
            . PHP_EOL;

        if ($ok) {
            $validSources++;
        }
    } catch (Throwable $e) {
        echo 'SOURCE_ATTEMPT_'
            . (int)$row['attempt_id']
            . '=FAIL'
            . PHP_EOL;
    }
}

$check(
    count($rows) === 7
    && $validSources === 7,
    'all seven known applied-subscription predecessors satisfy centralized source contract'
);

/*
 * Current production must still have no live refund reversal case.
 */
$totalRefunds =
    (int)$pdo->query(
        "SELECT COUNT(*)
         FROM billing_refund_resolutions"
    )->fetchColumn();

$pendingRefunds =
    (int)$pdo->query(
        "SELECT COUNT(*)
         FROM billing_refund_resolutions
         WHERE status = 'pending_review'"
    )->fetchColumn();

$check(
    $totalRefunds === 0
    && $pendingRefunds === 0,
    'production still has no refund-resolution row for mutating reversal QA'
);

/*
 * Static safety: Foundation 3 must not modify refund resolution,
 * payment facts, farms, modules, add-ons or effective limits.
 */
$forbiddenDml =
    preg_match(
        '/\b(?:UPDATE|DELETE\s+FROM|INSERT\s+INTO)\s+'
        . '(?:billing_refund_resolutions'
        . '|billing_payment_attempts'
        . '|farms'
        . '|farm_modules'
        . '|farm_subscription_seat_addons'
        . '|farm_role_limits)\b/i',
        $appendSource
    );

$check(
    $forbiddenDml === 0,
    'Foundation 3 primitive mutates only append-only subscriptions history'
);

echo 'CHECKS='
    . $checks
    . PHP_EOL;

echo 'FAILURES='
    . $failures
    . PHP_EOL;

echo $failures === 0
    ? 'V2.3 SUBSCRIPTION COMPENSATING HISTORY: PASSED'
    : 'V2.3 SUBSCRIPTION COMPENSATING HISTORY: FAILED';

echo PHP_EOL;

exit(
    $failures === 0
        ? 0
        : 1
);
