<?php

$root =
    dirname(__DIR__);

$stockPath =
    $root
    . '/lib/stock_service.php';

$persistencePath =
    $root
    . '/lib/stock_consumption_allocation_persistence.php';

$stock =
    file_get_contents(
        $stockPath
    );

$persistence =
    file_get_contents(
        $persistencePath
    );

if (
    !is_string($stock)
    ||
    !is_string($persistence)
) {
    echo "RESULT=FAIL\n";
    echo "FAIL=READ_FAILED\n";
    exit(1);
}

$failures = [];
$checks = 0;

$check =
    static function (
        string $name,
        bool $ok
    ) use (
        &$checks,
        &$failures
    ): void {
        $checks++;

        if (!$ok) {
            $failures[] =
                $name;
        }
    };

$check(
    'PERSISTENCE_REQUIRED',
    strpos(
        $stock,
        "require_once __DIR__ . '/stock_consumption_allocation_persistence.php';"
    ) !== false
);

$check(
    'HOOK_EXACTLY_ONCE',
    substr_count(
        $stock,
        'stock_consumption_allocation_persistence_before_source_reversal('
    ) === 1
);

$start =
    strpos(
        $stock,
        'function stock_reverse_transaction('
    );

$end =
    $start === false
        ? false
        : strpos(
            $stock,
            "\nfunction stock_expected_balance(",
            $start
        );

$body =
    (
        $start !== false
        &&
        $end !== false
    )
        ? substr(
            $stock,
            $start,
            $end - $start
        )
        : '';

$check(
    'REVERSAL_FUNCTION_FOUND',
    $body !== ''
);

$hookPos =
    strpos(
        $body,
        'stock_consumption_allocation_persistence_before_source_reversal('
    );

$itemMutationPos =
    strpos(
        $body,
        'UPDATE stock_items SET current_stock'
    );

$reversalInsertPos =
    strpos(
        $body,
        'INSERT INTO stock_transactions'
    );

$sourceReversePos =
    strpos(
        $body,
        'UPDATE stock_transactions'
    );

$check(
    'HOOK_BEFORE_STOCK_MUTATION',
    $hookPos !== false
    &&
    $itemMutationPos !== false
    &&
    $reversalInsertPos !== false
    &&
    $sourceReversePos !== false
    &&
    $hookPos < $itemMutationPos
    &&
    $hookPos < $reversalInsertPos
    &&
    $hookPos < $sourceReversePos
);

$check(
    'HOOK_PASSES_ACTOR_REASON',
    preg_match(
        '/stock_consumption_allocation_persistence_before_source_reversal\s*\(\s*\$pdo\s*,\s*\$farmId\s*,\s*\$transactionId\s*,\s*\$reason\s*,\s*\$userId\s*\)/s',
        $body
    ) === 1
);

$check(
    'STOCK_REVERSAL_STILL_CALLER_TRANSACTION',
    strpos(
        $body,
        'beginTransaction'
    ) === false
    &&
    strpos(
        $body,
        '->commit('
    ) === false
    &&
    strpos(
        $body,
        '->rollBack('
    ) === false
);

$reversalPersistenceStart =
    strpos(
        $persistence,
        'function stock_consumption_allocation_persistence_before_source_reversal('
    );

$reversalPersistence =
    $reversalPersistenceStart === false
        ? ''
        : substr(
            $persistence,
            $reversalPersistenceStart
        );

$check(
    'REVERSAL_DOES_NOT_REQUIRE_PARENT_ELIGIBILITY',
    $reversalPersistence !== ''
    &&
    strpos(
        $reversalPersistence,
        'stock_consumption_allocation_persistence_lock_parent('
    ) === false
    &&
    strpos(
        $reversalPersistence,
        'stock_consumption_source_resolver_resolve('
    ) === false
    &&
    strpos(
        $reversalPersistence,
        'stock_consumption_source_resolver_assert_cycle_consistency('
    ) === false
);

$check(
    'REVERSAL_LOCKS_STOCK_PARENT',
    strpos(
        $reversalPersistence,
        'stock_consumption_allocation_persistence_movement('
    ) !== false
    &&
    preg_match(
        '/stock_consumption_allocation_persistence_movement\s*\(.*?true\s*\)/s',
        $reversalPersistence
    ) === 1
);

$check(
    'REVERSAL_PRESERVES_PROVENANCE',
    strpos(
        $reversalPersistence,
        'stock_consumption_allocation_provenance_build('
    ) !== false
    &&
    strpos(
        $reversalPersistence,
        "'source_reversal'"
    ) !== false
);

$check(
    'NO_STOCK_HISTORY_REWRITE_BY_PERSISTENCE',
    preg_match(
        '/(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+stock_transactions/i',
        $persistence
    ) !== 1
);

/*
 * Existing stock service must retain append-only reversal semantics.
 */
$check(
    'ORIGINAL_REVERSAL_PAIR_PRESERVED',
    strpos(
        $body,
        'reversal_of_id'
    ) !== false
    &&
    strpos(
        $body,
        'is_reversed = 1'
    ) !== false
    &&
    strpos(
        $body,
        'INSERT INTO stock_transactions'
    ) !== false
);

/*
 * All previously discovered correction paths still converge on the
 * canonical reversal service. This is structural, not page-specific policy.
 */
$callers = [
    $root
        . '/lib/daily_feed_sync.php',

    $root
        . '/lib/manual_feed_transactions.php',
];

$callerCalls = 0;

foreach ($callers as $caller) {
    $callerText =
        file_get_contents(
            $caller
        );

    if (is_string($callerText)) {
        $callerCalls +=
            substr_count(
                $callerText,
                'stock_reverse_transaction('
            );
    }
}

$check(
    'KNOWN_CORRECTION_CALLERS_PRESERVED',
    $callerCalls === 4
);

$result =
    $failures
        ? 'FAIL'
        : 'PASS';

echo "RESULT={$result}\n";
echo "CHECK_COUNT={$checks}\n";

echo "CENTRAL_REVERSAL_WIRING="
    . ($failures ? 'SEE_FAILURES' : 'PASS')
    . "\n";

echo "SAME_TRANSACTION_CONTRACT="
    . ($failures ? 'SEE_FAILURES' : 'PASS')
    . "\n";

echo "GENERIC_REVERSAL_COMPATIBILITY="
    . ($failures ? 'SEE_FAILURES' : 'PASS')
    . "\n";

echo "ATTRIBUTION_DRIFT_CORRECTION="
    . ($failures ? 'SEE_FAILURES' : 'PASS')
    . "\n";

echo "APPEND_ONLY_STOCK_HISTORY="
    . ($failures ? 'SEE_FAILURES' : 'PASS')
    . "\n";

foreach ($failures as $failure) {
    echo "FAIL={$failure}\n";
}

exit(
    $result === 'PASS'
        ? 0
        : 1
);
