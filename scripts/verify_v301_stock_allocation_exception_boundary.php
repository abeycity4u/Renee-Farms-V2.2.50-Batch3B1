<?php

$root =
    dirname(__DIR__);

$workspacePath =
    $root
    . '/lib/stock_consumption_allocation_workspace.php';

$resolverPath =
    $root
    . '/lib/stock_consumption_source_resolver.php';

$apiPath =
    $root
    . '/api/get_stock_history.php';

$workspace =
    file_get_contents(
        $workspacePath
    );

$resolver =
    file_get_contents(
        $resolverPath
    );

$api =
    file_get_contents(
        $apiPath
    );

if (
    $workspace === false
    ||
    $resolver === false
    ||
    $api === false
) {
    echo "RESULT=FAIL\n";
    echo "CHECK_COUNT=0\n";
    echo "FAILED=SOURCE_LOAD\n";
    exit(1);
}

$checks = [];

function f5_check(
    array &$checks,
    string $name,
    bool $passed
): void {
    $checks[$name] =
        $passed;
}

$eligibilityStart =
    strpos(
        $workspace,
        'function stock_consumption_allocation_workspace_eligibility('
    );

$eligibilityEnd =
    strpos(
        $workspace,
        "if (!function_exists(\n"
        . "    'stock_consumption_allocation_workspace_action_state'"
    );

$eligibility =
    (
        $eligibilityStart !== false
        &&
        $eligibilityEnd !== false
        &&
        $eligibilityEnd > $eligibilityStart
    )
        ? substr(
            $workspace,
            $eligibilityStart,
            $eligibilityEnd
                - $eligibilityStart
        )
        : '';

f5_check(
    $checks,
    'ELIGIBILITY_FUNCTION_FOUND',
    $eligibility !== ''
);

f5_check(
    $checks,
    'PDO_EXCEPTION_IS_RUNTIME_EXCEPTION',
    is_subclass_of(
        'PDOException',
        'RuntimeException'
    )
);

$pdoCatchMatched =
    preg_match(
        '/catch\s*\(\s*PDOException\s+\$e\s*\)/s',
        $eligibility,
        $pdoCatchMatch,
        PREG_OFFSET_CAPTURE
    ) === 1;

$runtimeCatchMatched =
    preg_match(
        '/catch\s*\(\s*InvalidArgumentException\s*\|\s*RuntimeException\s+\$e\s*\)/s',
        $eligibility,
        $runtimeCatchMatch,
        PREG_OFFSET_CAPTURE
    ) === 1;

$pdoCatchPosition =
    $pdoCatchMatched
        ? (int)$pdoCatchMatch[0][1]
        : false;

$runtimeCatchPosition =
    $runtimeCatchMatched
        ? (int)$runtimeCatchMatch[0][1]
        : false;

f5_check(
    $checks,
    'EXPLICIT_PDO_EXCEPTION_CATCH_PRESENT',
    $pdoCatchMatched
);

f5_check(
    $checks,
    'PDO_CATCH_PRECEDES_RUNTIME_CATCH',
    $pdoCatchMatched
    &&
    $runtimeCatchMatched
    &&
    $pdoCatchPosition
        < $runtimeCatchPosition
);

$pdoCatchTail =
    (
        $pdoCatchMatched
        &&
        $runtimeCatchMatched
        &&
        $runtimeCatchPosition
            > $pdoCatchPosition
    )
        ? substr(
            $eligibility,
            $pdoCatchPosition,
            $runtimeCatchPosition
                - $pdoCatchPosition
        )
        : '';

f5_check(
    $checks,
    'PDO_EXCEPTION_IS_RETHROWN',
    preg_match(
        '/\bthrow\s+\$e\s*;/s',
        $pdoCatchTail
    ) === 1
);

f5_check(
    $checks,
    'BUSINESS_RUNTIME_CATCH_REMAINS',
    $runtimeCatchMatched
);

f5_check(
    $checks,
    'BUSINESS_EXCEPTION_STILL_RETURNS_REASON',
    strpos(
        $eligibility,
        "'reason'"
    ) !== false
    &&
    strpos(
        $eligibility,
        '$e->getMessage()'
    ) !== false
);

f5_check(
    $checks,
    'ELIGIBILITY_STILL_USES_CANONICAL_RESOLVER',
    strpos(
        $eligibility,
        'stock_consumption_source_resolver_resolve('
    ) !== false
);

f5_check(
    $checks,
    'RESOLVER_REMAINS_QUERY_CAPABLE',
    strpos(
        $resolver,
        '$pdo->prepare('
    ) !== false
);

f5_check(
    $checks,
    'API_STILL_SURFACES_BUSINESS_ELIGIBILITY_REASON',
    strpos(
        $api,
        "\$allocationAction['reason']"
    ) !== false
);

f5_check(
    $checks,
    'API_SAFE_OUTER_HANDLER_REMAINS',
    strpos(
        $api,
        'catch (Throwable $e)'
    ) !== false
    &&
    strpos(
        $api,
        'safe_api_exception_message'
    ) !== false
);

f5_check(
    $checks,
    'ELIGIBILITY_HAS_NO_MUTATION_SQL',
    preg_match(
        '/\b(?:INSERT\s+INTO|UPDATE\s+[A-Za-z_]|DELETE\s+FROM)\b/i',
        $eligibility
    ) !== 1
);

$verifierSource =
    file_get_contents(
        __FILE__
    );

$verifierExecutableSource = '';

if ($verifierSource !== false) {
    foreach(
        token_get_all(
            $verifierSource
        )
        as $token
    ) {
        if (is_array($token)) {
            if (
                in_array(
                    $token[0],
                    [
                        T_CONSTANT_ENCAPSED_STRING,
                        T_ENCAPSED_AND_WHITESPACE,
                        T_COMMENT,
                        T_DOC_COMMENT,
                    ],
                    true
                )
            ) {
                continue;
            }

            $verifierExecutableSource .=
                $token[1];

            continue;
        }

        $verifierExecutableSource .=
            $token;
    }
}

f5_check(
    $checks,
    'VERIFIER_SOURCE_ONLY',
    $verifierSource !== false
    &&
    preg_match(
        '/\bnew\s+\\\\?PDO\s*\(/i',
        $verifierExecutableSource
    ) !== 1
    &&
    preg_match(
        '/->\s*prepare\s*\(/i',
        $verifierExecutableSource
    ) !== 1
    &&
    preg_match(
        '/->\s*execute\s*\(/i',
        $verifierExecutableSource
    ) !== 1
);

$failed = [];

foreach (
    $checks
    as $name => $passed
) {
    if (!$passed) {
        $failed[] =
            $name;
    }
}

echo 'RESULT='
    . (
        $failed
            ? 'FAIL'
            : 'PASS'
    )
    . PHP_EOL;

echo 'CHECK_COUNT='
    . count($checks)
    . PHP_EOL;

foreach (
    $checks
    as $name => $passed
) {
    echo $name
        . '='
        . (
            $passed
                ? 'PASS'
                : 'FAIL'
        )
        . PHP_EOL;
}

echo "DATABASE_CONNECTION=NONE\n";
echo "DATABASE_WRITE=NONE\n";

if ($failed) {
    echo 'FAILED='
        . implode(
            ',',
            $failed
        )
        . PHP_EOL;

    exit(1);
}

exit(0);
