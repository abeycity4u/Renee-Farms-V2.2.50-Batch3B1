<?php
/**
 * V2.3 Gap D Phase 3B Foundation 2 verifier.
 *
 * Proves that every future paid seat-top-up application durably links its
 * seat-change request to the exact immutable subscriptions history row created
 * by that application.
 *
 * Read-only/static. No provider calls and no commercial mutations.
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__
    . '/../includes/billing_seat_change_request.php';
require_once __DIR__
    . '/../includes/billing_seat_topup_application.php';

$root = dirname(__DIR__);

$requestPath =
    $root . '/includes/billing_seat_change_request.php';

$applicationPath =
    $root . '/includes/billing_seat_topup_application.php';

$requestSource =
    (string)file_get_contents($requestPath);

$applicationSource =
    (string)file_get_contents($applicationPath);

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
    in_array(
        'applied_subscription_record_id',
        billing_seat_change_required_columns(),
        true
    ),
    'seat-change readiness requires applied history column'
);

$check(
    billing_seat_change_migration_ready($pdo),
    'seat-change migration readiness includes migration 051'
);

$check(
    billing_seat_change_foreign_keys_ready($pdo),
    'seat-change foreign-key readiness includes applied history RESTRICT link'
);

$check(
    billing_seat_change_table_ready($pdo),
    'seat-change table readiness accepts deployed history-link schema'
);

$check(
    billing_seat_change_ready($pdo),
    'shared seat-change service is ready after migration 051'
);

$check(
    billing_seat_topup_application_ready($pdo),
    'paid seat-top-up application is transactionally ready'
);

$check(
    strpos(
        $requestSource,
        "'051_billing_seat_application_history_link.sql'"
    ) !== false
    && strpos(
        $requestSource,
        "=== 3"
    ) !== false,
    'shared readiness explicitly requires migration 051'
);

$check(
    strpos(
        $requestSource,
        "'fk_billing_seat_change_applied_subscription'"
    ) !== false
    && strpos(
        $requestSource,
        "'applied_subscription_record_id' =>"
    ) !== false,
    'shared request service exposes mutable applied-history workflow state'
);

$check(
    strpos(
        $applicationSource,
        "function billing_seat_topup_applied_history_link_id"
    ) !== false,
    'application has centralized same-tenant applied-history validator'
);

$check(
    strpos(
        $applicationSource,
        "FROM subscriptions"
    ) !== false
    && strpos(
        $applicationSource,
        "AND farm_id = ?"
    ) !== false,
    'applied history link is revalidated against the same tenant'
);

$check(
    preg_match(
        "/SET\\s+status\\s*=\\s*'applied'\\s*,\\s*"
        . "applied_subscription_record_id\\s*=\\s*\\?\\s*,\\s*"
        . "applied_at\\s*=\\s*CURRENT_TIMESTAMP/is",
        $applicationSource
    ) === 1,
    'request applied transition stores exact history identity atomically'
);

$check(
    strpos(
        $applicationSource,
        'AND applied_subscription_record_id IS NULL'
    ) !== false,
    'applied transition fails closed if a history link already exists'
);

$check(
    strpos(
        $applicationSource,
        '$linkedHistoryRecordId'
    ) !== false
    && strpos(
        $applicationSource,
        '!== $historyRecordId'
    ) !== false,
    'post-application reload proves persisted link equals captured history'
);

$idempotentPos =
    strpos(
        $applicationSource,
        "=== 'applied'"
    );

$helperCallPos =
    strpos(
        $applicationSource,
        'billing_seat_topup_applied_history_link_id(',
        $idempotentPos === false
            ? 0
            : $idempotentPos
    );

$check(
    $idempotentPos !== false
    && $helperCallPos !== false,
    'idempotent applied re-entry requires durable history linkage'
);

$check(
    substr_count(
        $applicationSource,
        "'history_record_id' =>"
    ) >= 2,
    'new and idempotent application results both expose history identity'
);

$check(
    stripos(
        $applicationSource,
        'curl_'
    ) === false,
    'application contains no provider/network call'
);

$forbiddenPaymentDml =
    preg_match(
        '/\\b(?:UPDATE|DELETE\\s+FROM|INSERT\\s+INTO)\\s+'
        . 'billing_payment_attempts\\b/i',
        $applicationSource
    );

$check(
    $forbiddenPaymentDml === 0,
    'history-link wiring does not mutate payment-attempt audit facts'
);

$migrationRecordedStmt =
    $pdo->prepare(
        "SELECT COUNT(*)
         FROM schema_migrations
         WHERE filename = ?"
    );

$migrationRecordedStmt->execute([
    '051_billing_seat_application_history_link.sql',
]);

$check(
    (int)$migrationRecordedStmt->fetchColumn()
        === 1,
    'migration 051 remains recorded exactly once'
);

$fkStmt =
    $pdo->prepare(
        "SELECT
             kcu.column_name,
             kcu.referenced_table_name,
             kcu.referenced_column_name,
             rc.delete_rule
         FROM information_schema.referential_constraints rc
         INNER JOIN information_schema.key_column_usage kcu
           ON kcu.constraint_schema =
              rc.constraint_schema
          AND kcu.constraint_name =
              rc.constraint_name
          AND kcu.table_name =
              rc.table_name
         WHERE rc.constraint_schema = DATABASE()
           AND rc.table_name =
               'billing_seat_change_requests'
           AND rc.constraint_name =
               'fk_billing_seat_change_applied_subscription'
         LIMIT 1"
    );

$fkStmt->execute();

$fk =
    $fkStmt->fetch(PDO::FETCH_ASSOC)
    ?: null;

$check(
    is_array($fk)
    && (string)$fk['column_name']
        === 'applied_subscription_record_id'
    && (string)$fk['referenced_table_name']
        === 'subscriptions'
    && (string)$fk['referenced_column_name']
        === 'id'
    && strtoupper(
        (string)$fk['delete_rule']
    ) === 'RESTRICT',
    'live applied-history FK is exact and RESTRICT'
);

echo 'CHECKS='
    . $checks
    . PHP_EOL;

echo 'FAILURES='
    . $failures
    . PHP_EOL;

echo $failures === 0
    ? 'V2.3 SEAT APPLICATION HISTORY LINK: PASSED'
    : 'V2.3 SEAT APPLICATION HISTORY LINK: FAILED';

echo PHP_EOL;

exit($failures === 0 ? 0 : 1);
