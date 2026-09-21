<?php

$root =
    dirname(__DIR__);

$pagePath =
    $root
    . '/management/stock_consumption_allocation.php';

$workspacePath =
    $root
    . '/lib/stock_consumption_allocation_workspace.php';

$persistencePath =
    $root
    . '/lib/stock_consumption_allocation_persistence.php';

$fail = 0;

$check =
    static function (
        string $name,
        bool $passed
    ) use (&$fail): void {
        echo $name
            . '='
            . (
                $passed
                    ? 'PASS'
                    : 'FAIL'
            )
            . PHP_EOL;

        if (!$passed) {
            $fail = 1;
        }
    };

$page =
    is_file($pagePath)
        ? file_get_contents(
            $pagePath
        )
        : false;

$workspace =
    is_file($workspacePath)
        ? file_get_contents(
            $workspacePath
        )
        : false;

$persistence =
    is_file($persistencePath)
        ? file_get_contents(
            $persistencePath
        )
        : false;

$check(
    'H4_PAGE_READABLE',
    is_string($page)
);

$check(
    'H4_WORKSPACE_READABLE',
    is_string($workspace)
);

$check(
    'H4_PERSISTENCE_READABLE',
    is_string($persistence)
);

if (
    !is_string($page)
    ||
    !is_string($workspace)
    ||
    !is_string($persistence)
) {
    echo "DATABASE_REQUIRED=NO\n";
    echo "BUSINESS_ROW_MUTATION=NO\n";
    echo "H4_STOCK_PUBLIC_REFERENCE_VERIFIER=FAIL\n";
    exit(1);
}

$referenceBlock = <<<'HTML'
                <div class="mb-3">
                    <div class="small text-muted">
                        Stock Reference
                    </div>
                    <div class="fw-semibold">
                        <code><?php echo $escape(
                            $movement['public_reference']
                            ?? '—'
                        ); ?></code>
                    </div>
                </div>
HTML;

$check(
    'H4_CANONICAL_WORKSPACE_USED',
    strpos(
        $page,
        'stock_consumption_allocation_workspace_snapshot'
    ) !== false
);

$check(
    'H4_STOCK_REFERENCE_LABEL_ONCE',
    substr_count(
        $page,
        'Stock Reference'
    ) === 1
);

$check(
    'H4_PUBLIC_REFERENCE_BLOCK_PRESENT',
    strpos(
        $page,
        $referenceBlock
    ) !== false
);

$check(
    'H4_PUBLIC_REFERENCE_ESCAPED',
    strpos(
        $referenceBlock,
        '$escape('
    ) !== false
);

$check(
    'H4_REFERENCE_RENDERED_AS_CODE',
    strpos(
        $referenceBlock,
        '<code>'
    ) !== false
    &&
    strpos(
        $referenceBlock,
        '</code>'
    ) !== false
);

$check(
    'H4_REFERENCE_FROM_MOVEMENT',
    strpos(
        $referenceBlock,
        "\$movement['public_reference']"
    ) !== false
);

$check(
    'H4_REFERENCE_HAS_SAFE_FALLBACK',
    strpos(
        $referenceBlock,
        "?? '—'"
    ) !== false
);

$check(
    'H4_PAGE_REMAINS_SQL_FREE',
    strpos(
        $page,
        '$pdo->prepare'
    ) === false
    &&
    strpos(
        $page,
        '$pdo->query'
    ) === false
    &&
    strpos(
        $page,
        '$pdo->exec'
    ) === false
);

$check(
    'H4_WORKSPACE_RETURNS_CANONICAL_MOVEMENT',
    strpos(
        $workspace,
        "'movement'"
    ) !== false
    &&
    strpos(
        $workspace,
        '$movement'
    ) !== false
);

$check(
    'H4_CANONICAL_MOVEMENT_READS_STOCK_TRANSACTION',
    strpos(
        $persistence,
        'SELECT *'
    ) !== false
    &&
    strpos(
        $persistence,
        'FROM stock_transactions'
    ) !== false
);

$check(
    'H4_INTERNAL_TRANSACTION_ID_STILL_ROUTING_ONLY',
    strpos(
        $page,
        'data-stock-transaction-id='
    ) !== false
    &&
    strpos(
        $page,
        '?stock_transaction_id='
    ) === false
);

echo "DATABASE_REQUIRED=NO\n";
echo "BUSINESS_ROW_MUTATION=NO\n";
echo "SQL_CHANGE_REQUIRED=NO\n";
echo "SERVICE_CHANGE_REQUIRED=NO\n";
echo "WORKSPACE_CHANGE_REQUIRED=NO\n";
echo "API_CHANGE_REQUIRED=NO\n";

echo 'H4_STOCK_PUBLIC_REFERENCE_VERIFIER='
    . (
        $fail === 0
            ? 'PASS'
            : 'FAIL'
    )
    . PHP_EOL;

exit($fail);
