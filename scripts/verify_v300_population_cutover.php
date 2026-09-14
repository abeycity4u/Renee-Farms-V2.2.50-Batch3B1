<?php
/**
 * V3.0 focused static/pure-contract verifier:
 * controlled legacy production population cutover.
 *
 * No database connection is opened and no migration is executed.
 */

$root = dirname(__DIR__);
$cycleServicePath = $root . '/lib/production_cycle_service.php';

$checks = 0;
$failures = 0;

$check = static function (
    bool $ok,
    string $message
) use (&$checks, &$failures): void {
    $checks++;

    if ($ok) {
        echo "PASS: {$message}\n";
        return;
    }

    $failures++;
    echo "FAIL: {$message}\n";
};

$source = is_file($cycleServicePath)
    ? file_get_contents($cycleServicePath)
    : false;

$check(
    $source !== false,
    'shared production cycle service exists and is readable'
);

if ($source === false) {
    echo "\nChecks: {$checks}\n";
    echo "Failures: {$failures}\n";
    echo "V3.0 POPULATION CUTOVER: FAILED\n";
    echo "DATABASE_CONNECTION_USED=NO\n";
    echo "DATABASE_WRITE_PERFORMED=NO\n";
    exit(1);
}

/**
 * Extract one named function body through PHP tokens.
 *
 * This avoids verifier dependence on indentation, line wrapping, or exact
 * whitespace.
 */
$extractFunctionBody = static function (
    string $php,
    string $functionName
): ?string {
    $tokens = token_get_all($php);
    $count = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if (!is_array($token) || $token[0] !== T_FUNCTION) {
            continue;
        }

        $name = null;
        $nameIndex = null;

        for ($j = $i + 1; $j < $count; $j++) {
            $candidate = $tokens[$j];

            if (
                is_array($candidate)
                && in_array(
                    $candidate[0],
                    [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT],
                    true
                )
            ) {
                continue;
            }

            if (
                defined('T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG')
                && is_array($candidate)
                && $candidate[0] === T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG
            ) {
                continue;
            }

            if (is_string($candidate) && $candidate === '&') {
                continue;
            }

            if (is_array($candidate) && $candidate[0] === T_STRING) {
                $name = $candidate[1];
                $nameIndex = $j;
            }

            break;
        }

        if ($name !== $functionName || $nameIndex === null) {
            continue;
        }

        $started = false;
        $depth = 0;
        $body = '';

        for ($j = $nameIndex + 1; $j < $count; $j++) {
            $part = $tokens[$j];
            $text = is_array($part) ? $part[1] : $part;

            if (!$started) {
                if ($text === '{') {
                    $started = true;
                    $depth = 1;
                }

                continue;
            }

            if ($text === '{') {
                $depth++;
                $body .= $text;
                continue;
            }

            if ($text === '}') {
                $depth--;

                if ($depth === 0) {
                    return $body;
                }

                $body .= $text;
                continue;
            }

            $body .= $text;
        }

        return null;
    }

    return null;
};

/**
 * Remove comments and normalize whitespace while leaving actual executable
 * tokens and string literals intact.
 */
$semanticCode = static function (string $fragment): string {
    $tokens = token_get_all("<?php\n" . $fragment);
    $result = '';

    foreach ($tokens as $token) {
        if (is_array($token)) {
            if (
                in_array(
                    $token[0],
                    [T_OPEN_TAG, T_COMMENT, T_DOC_COMMENT],
                    true
                )
            ) {
                continue;
            }

            if ($token[0] === T_WHITESPACE) {
                $result .= ' ';
                continue;
            }

            $result .= $token[1];
            continue;
        }

        $result .= $token;
    }

    return preg_replace('/\s+/', ' ', trim($result)) ?? '';
};

require_once $cycleServicePath;

$check(
    function_exists('production_cycle_cutover_population_v3'),
    'shared production cycle service exposes the legacy cutover operation'
);

$body = $extractFunctionBody(
    $source,
    'production_cycle_cutover_population_v3'
);

$check(
    $body !== null,
    'cutover function body is discoverable through PHP tokens'
);

if ($body === null) {
    echo "\nChecks: {$checks}\n";
    echo "Failures: {$failures}\n";
    echo "V3.0 POPULATION CUTOVER: FAILED\n";
    echo "DATABASE_CONNECTION_USED=NO\n";
    echo "DATABASE_WRITE_PERFORMED=NO\n";
    exit(1);
}

$code = $semanticCode($body);

$reflection = new ReflectionFunction(
    'production_cycle_cutover_population_v3'
);

$parameterNames = array_map(
    static function (ReflectionParameter $parameter): string {
        return $parameter->getName();
    },
    $reflection->getParameters()
);

$check(
    $parameterNames === [
        'pdo',
        'farmId',
        'cycleId',
        'baselineDate',
        'baselineQuantity',
        'notes',
        'userId',
    ],
    'cutover operation has one explicit tenant/cycle/date/quantity/user contract'
);

$returnType = $reflection->getReturnType();

$check(
    $returnType !== null
    && $returnType instanceof ReflectionNamedType
    && $returnType->getName() === 'int',
    'cutover returns the durable canonical baseline id'
);

$check(
    production_population_baseline_sources() === [
        'cycle_opening',
        'legacy_cutover',
    ],
    'canonical population service remains sole owner of approved baseline sources'
);

$baselineCallCount = preg_match_all(
    '/\bproduction_population_establish_baseline\s*\(/',
    $code
);

$check(
    $baselineCallCount === 1,
    'cutover delegates exactly once to the canonical baseline writer'
);

$check(
    strpos($code, "'legacy_cutover'") !== false,
    'cutover explicitly selects the canonical legacy_cutover source'
);

$check(
    strpos($code, 'production_cycle_assert_farm_id(') !== false
    && strpos($code, 'production_cycle_assert_user_id(') !== false,
    'cutover validates farm and actor through shared cycle helpers'
);

$check(
    strpos($code, 'production_cycle_valid_date(') !== false
    && strpos($code, 'production_cycle_nonnegative_int(') !== false,
    'cutover normalizes date and confirmed headcount through shared helpers'
);

$check(
    strpos($code, 'production_population_lock_baseline(') === false
    && strpos($code, 'production_population_lock_cycle(') === false,
    'cutover does not duplicate canonical baseline or cycle locking'
);

$check(
    preg_match(
        '/\bproduction_population_(?:baselines|movements)\b/i',
        $code
    ) !== 1,
    'cutover performs no direct population-table SQL ownership'
);

$check(
    preg_match(
        '/\b(?:SELECT|INSERT|UPDATE|DELETE)\b/i',
        $code
    ) !== 1,
    'cutover contains no direct SQL statements'
);

$check(
    strpos($code, 'beginTransaction(') === false
    && strpos($code, 'commit(') === false
    && strpos($code, 'rollBack(') === false,
    'cutover leaves transaction ownership to the canonical baseline service'
);

$check(
    strpos($code, 'audit_log_event(') === false,
    'cutover leaves baseline audit ownership to the canonical population service'
);

$legacyInferenceTerms = [
    'layer_daily_records',
    'broiler_daily_records',
    'ruminant_daily_records',
    'ruminant_animals',
    'sales_records',
    'opening_headcount',
    'closing_headcount',
];

$legacyInferenceFound = false;

foreach ($legacyInferenceTerms as $term) {
    if (stripos($code, $term) !== false) {
        $legacyInferenceFound = true;
        break;
    }
}

$check(
    !$legacyInferenceFound,
    'cutover does not infer confirmed population from legacy operational data'
);

$check(
    strpos(
        $code,
        "'Current live population'"
    ) !== false,
    'cutover labels the submitted quantity as current live population'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    echo "V3.0 POPULATION CUTOVER: FAILED\n";
    echo "DATABASE_CONNECTION_USED=NO\n";
    echo "DATABASE_WRITE_PERFORMED=NO\n";
    exit(1);
}

echo "V3.0 POPULATION CUTOVER: PASSED\n";
echo "DATABASE_CONNECTION_USED=NO\n";
echo "DATABASE_WRITE_PERFORMED=NO\n";
