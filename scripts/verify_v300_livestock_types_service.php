<?php

$servicePath = __DIR__ . '/../lib/livestock_types.php';

$checks = 0;
$failures = 0;

function verify_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;

    if ($condition) {
        echo "PASS: {$message}\n";
        return;
    }

    $failures++;
    echo "FAIL: {$message}\n";
}

function service_matches(string $source, string $pattern): bool
{
    return preg_match($pattern, $source) === 1;
}

verify_check(
    is_file($servicePath) && is_readable($servicePath),
    'shared livestock type service exists and is readable'
);

$source = is_readable($servicePath)
    ? (string)file_get_contents($servicePath)
    : '';

$normalized = preg_replace('/\s+/', ' ', strtolower($source));
$normalized = is_string($normalized) ? trim($normalized) : '';

require_once $servicePath;

verify_check(
    class_exists('LivestockTypeException'),
    'service exposes a dedicated livestock type domain exception'
);

verify_check(
    livestock_type_builtin_labels() === [
        'cattle' => 'Cattle',
        'goat' => 'Goat',
        'sheep' => 'Sheep',
    ],
    'built-in cattle goat and sheep remain canonical legacy identities'
);

verify_check(
    livestock_type_reserved_names() === [
        'cattle',
        'goat',
        'sheep',
        'other',
        'shared',
    ],
    'reserved platform livestock names are centralized'
);

$normalizedName = livestock_type_normalize_name("  New\tZealand   Rabbit  ");
verify_check(
    $normalizedName === 'New Zealand Rabbit',
    'custom livestock names are trimmed and whitespace-normalized centrally'
);

$reservedRejected = false;
try {
    livestock_type_normalize_name('  CATTLE  ');
} catch (InvalidArgumentException $e) {
    $reservedRejected = true;
}
verify_check(
    $reservedRejected,
    'reserved built-in names are rejected case-insensitively'
);

$longRejected = false;
try {
    livestock_type_normalize_name(str_repeat('x', 101));
} catch (InvalidArgumentException $e) {
    $longRejected = true;
}
verify_check(
    $longRejected,
    'custom livestock names enforce the database length contract'
);

verify_check(
    livestock_type_parse_choice('goat') === [
        'legacy_type' => 'goat',
        'livestock_type_id' => null,
    ]
    && livestock_type_parse_choice('other') === [
        'legacy_type' => 'other',
        'livestock_type_id' => null,
    ]
    && livestock_type_parse_choice('custom:27') === [
        'legacy_type' => 'other',
        'livestock_type_id' => 27,
    ],
    'form values map to the canonical legacy and custom identity pair'
);

$invalidChoiceRejected = false;
try {
    livestock_type_parse_choice('custom:0');
} catch (InvalidArgumentException $e) {
    $invalidChoiceRejected = true;
}
verify_check(
    $invalidChoiceRejected,
    'invalid custom form identities are rejected before database use'
);

verify_check(
    service_matches(
        $source,
        '/function\s+livestock_type_get\s*\(.*?WHERE\s+id\s*=\s*\?\s+AND\s+farm_id\s*=\s*\?/is'
    )
    && service_matches(
        $source,
        '/function\s+livestock_type_list\s*\(.*?WHERE\s+farm_id\s*=\s*\?/is'
    ),
    'all custom livestock reads are farm scoped'
);

verify_check(
    service_matches(
        $source,
        '/INSERT\s+INTO\s+livestock_types\s*\(\s*farm_id\s*,\s*name\s*,\s*is_active\s*,\s*created_by\s*\)/is'
    ),
    'create writes farm ownership name lifecycle and creator through one service'
);

verify_check(
    service_matches(
        $source,
        '/UPDATE\s+livestock_types\s+SET\s+name\s*=\s*\?\s+WHERE\s+id\s*=\s*\?\s+AND\s+farm_id\s*=\s*\?/is'
    ),
    'rename is farm scoped in the shared service'
);

verify_check(
    service_matches(
        $source,
        '/UPDATE\s+livestock_types\s+SET\s+is_active\s*=\s*\?\s+WHERE\s+id\s*=\s*\?\s+AND\s+farm_id\s*=\s*\?/is'
    )
    && !service_matches($source, '/DELETE\s+FROM\s+livestock_types/i'),
    'lifecycle uses activation and deactivation without hard deletion'
);

verify_check(
    service_matches(
        $source,
        "/sqlState\s*===\s*'23000'.*?driverCode\s*===\s*1062/is"
    )
    && substr_count(
        $source,
        'A livestock type with this name already exists for this farm.'
    ) >= 2,
    'duplicate custom names are translated into a shared friendly domain error'
);

verify_check(
    service_matches(
        $source,
        '/function\s+livestock_type_validate_link\s*\(.*?Built-in livestock types cannot carry a custom livestock type ID\./is'
    )
    && service_matches(
        $source,
        "/legacyType\s*!==\s*'other'/"
    ),
    'link validation preserves built-ins and confines custom IDs to other'
);

verify_check(
    service_matches(
        $source,
        '/livestock_type_get\s*\(\s*\$pdo\s*,\s*\$farmId\s*,\s*\$livestockTypeId\s*,\s*\$requireActiveCustom\s*\)/is'
    ),
    'custom link validation reuses same-farm authority and active-state policy'
);

verify_check(
    service_matches(
        $source,
        "/return\s+'Other';/i"
    )
    && service_matches(
        $source,
        '/livestock_type_validate_link\s*\(\s*\$pdo\s*,\s*\$farmId\s*,\s*\'other\'\s*,\s*\$livestockTypeId\s*,\s*false\s*\)/is'
    ),
    'display keeps generic Other and can resolve inactive historical custom labels'
);

verify_check(
    strpos($source, "'value' => 'custom:' . (int)\$type['id']") !== false
    && strpos($source, "'legacy_type' => 'other'") !== false,
    'UI choices expose stable custom identifiers without changing stored legacy keys'
);

verify_check(
    substr_count($source, "function_exists('audit_log_event')") >= 3
    && strpos($source, "'livestock_type_created'") !== false
    && strpos($source, "'livestock_type_renamed'") !== false
    && strpos($source, "'livestock_type_deactivated'") !== false,
    'custom type lifecycle changes retain shared audit hooks'
);

$withoutComments = preg_replace('/^\s*\/\/.*$/m', '', $source);
$withoutComments = is_string($withoutComments) ? $withoutComments : $source;
verify_check(
    !service_matches($withoutComments, '/\bALTER\s+TABLE\b/i')
    && !service_matches($withoutComments, '/\bCREATE\s+TABLE\b/i')
    && !service_matches($withoutComments, '/\bDROP\s+TABLE\b/i')
    && strpos($normalized, 'http://') === false
    && strpos($normalized, 'https://') === false
    && strpos($normalized, 'curl') === false,
    'service owns application policy only and performs no schema network or provider work'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    echo "V3.0 SHARED LIVESTOCK TYPE SERVICE: FAILED\n";
    exit(1);
}

echo "V3.0 SHARED LIVESTOCK TYPE SERVICE: PASSED\n";
exit(0);
