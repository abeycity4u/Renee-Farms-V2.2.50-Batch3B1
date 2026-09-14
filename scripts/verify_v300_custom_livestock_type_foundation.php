<?php

$migrationPath = __DIR__ . '/../migrations/055_custom_livestock_type_foundation.sql';

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

function sql_matches(string $sql, string $pattern): bool
{
    return preg_match($pattern, $sql) === 1;
}

verify_check(
    is_file($migrationPath) && is_readable($migrationPath),
    'migration 055 exists and is readable'
);

$sql = is_readable($migrationPath)
    ? (string)file_get_contents($migrationPath)
    : '';

$normalized = preg_replace('/\s+/', ' ', strtolower($sql));
$normalized = is_string($normalized) ? trim($normalized) : '';

verify_check(
    sql_matches($sql, '/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+livestock_types\s*\(/i'),
    'migration creates the farm livestock type authority'
);

verify_check(
    sql_matches(
        $sql,
        '/UNIQUE\s+KEY\s+uniq_livestock_type_farm_name\s*\(\s*farm_id\s*,\s*name\s*\)/i'
    ),
    'custom livestock type names are unique inside each farm'
);

verify_check(
    sql_matches(
        $sql,
        '/UNIQUE\s+KEY\s+uniq_livestock_type_farm_identity\s*\(\s*farm_id\s*,\s*id\s*\)/i'
    ),
    'custom livestock identities expose a same-farm composite key'
);

verify_check(
    sql_matches(
        $sql,
        '/CHAR_LENGTH\s*\(\s*name\s*\)\s*=\s*CHAR_LENGTH\s*\(\s*TRIM\s*\(\s*name\s*\)\s*\)/i'
    )
    && sql_matches($sql, '/CHAR_LENGTH\s*\(\s*name\s*\)\s*>\s*0/i')
    && sql_matches(
        $sql,
        "/LOWER\s*\(\s*name\s*\)\s+NOT\s+IN\s*\(\s*'cattle'\s*,\s*'goat'\s*,\s*'sheep'\s*,\s*'other'\s*,\s*'shared'\s*\)/i"
    ),
    'custom livestock names are normalized, non-empty, and cannot shadow reserved built-ins'
);

verify_check(
    sql_matches(
        $sql,
        '/CONSTRAINT\s+chk_livestock_type_active\s+CHECK\s*\(\s*is_active\s+IN\s*\(\s*0\s*,\s*1\s*\)\s*\)/i'
    ),
    'custom livestock active state is constrained to a boolean contract'
);

verify_check(
    sql_matches(
        $sql,
        '/CONSTRAINT\s+fk_livestock_types_farm\s+FOREIGN\s+KEY\s*\(\s*farm_id\s*\)\s+REFERENCES\s+farms\s*\(\s*id\s*\)\s+ON\s+DELETE\s+RESTRICT/is'
    )
    && sql_matches(
        $sql,
        '/CONSTRAINT\s+fk_livestock_types_creator\s+FOREIGN\s+KEY\s*\(\s*created_by\s*\)\s+REFERENCES\s+users\s*\(\s*id\s*\)\s+ON\s+DELETE\s+SET\s+NULL/is'
    ),
    'custom livestock types retain tenant ownership and creator audit boundaries'
);

verify_check(
    sql_matches(
        $sql,
        '/ALTER\s+TABLE\s+production_cycles.*?ADD\s+COLUMN\s+livestock_type_id\s+INT\s+NULL/is'
    ),
    'production cycles gain an optional custom livestock type link'
);

verify_check(
    sql_matches(
        $sql,
        '/CONSTRAINT\s+fk_production_cycles_livestock_type\s+FOREIGN\s+KEY\s*\(\s*farm_id\s*,\s*livestock_type_id\s*\)\s+REFERENCES\s+livestock_types\s*\(\s*farm_id\s*,\s*id\s*\)\s+ON\s+DELETE\s+RESTRICT/is'
    ),
    'production cycle custom type links cannot cross farm boundaries'
);

verify_check(
    sql_matches(
        $sql,
        "/CONSTRAINT\s+chk_production_cycles_livestock_type_scope\s+CHECK\s*\(\s*livestock_type_id\s+IS\s+NULL\s+OR\s*\(\s*farm_type\s*=\s*'ruminant'\s+AND\s+production_type\s*=\s*'other'\s*\)\s*\)/is"
    ),
    'production cycle custom types are limited to the ruminant other compatibility bucket'
);

verify_check(
    sql_matches(
        $sql,
        '/ALTER\s+TABLE\s+ruminant_animals.*?ADD\s+COLUMN\s+livestock_type_id\s+INT\s+NULL/is'
    ),
    'ruminant registry rows gain an optional custom livestock type link'
);

verify_check(
    sql_matches(
        $sql,
        "/ADD\s+COLUMN\s+tag_type_scope_key\s+INT\s+AS\s*\(\s*CASE\s+WHEN\s+species\s*=\s*'cattle'\s+THEN\s+-1\s+WHEN\s+species\s*=\s*'goat'\s+THEN\s+-2\s+WHEN\s+species\s*=\s*'sheep'\s+THEN\s+-3\s+ELSE\s+COALESCE\s*\(\s*livestock_type_id\s*,\s*0\s*\)\s+END\s*\)\s+PERSISTENT/is"
    ),
    'tag scope preserves built-ins, generic other, and distinct custom livestock identities'
);

verify_check(
    sql_matches(
        $sql,
        '/DROP\s+INDEX\s+uniq_ruminant_farm_species_tag/i'
    ),
    'legacy species-only tag uniqueness is explicitly retired'
);

verify_check(
    sql_matches(
        $sql,
        '/ADD\s+UNIQUE\s+KEY\s+uniq_ruminant_farm_type_tag\s*\(\s*farm_id\s*,\s*tag_type_scope_key\s*,\s*tag_no\s*\)/i'
    ),
    'ruminant tags become unique per actual livestock type inside a farm'
);

verify_check(
    sql_matches(
        $sql,
        '/CONSTRAINT\s+fk_ruminant_animals_livestock_type\s+FOREIGN\s+KEY\s*\(\s*farm_id\s*,\s*livestock_type_id\s*\)\s+REFERENCES\s+livestock_types\s*\(\s*farm_id\s*,\s*id\s*\)\s+ON\s+DELETE\s+RESTRICT/is'
    ),
    'ruminant registry custom type links cannot cross farm boundaries'
);

verify_check(
    sql_matches(
        $sql,
        "/CONSTRAINT\s+chk_ruminant_animals_livestock_type_scope\s+CHECK\s*\(\s*livestock_type_id\s+IS\s+NULL\s+OR\s+species\s*=\s*'other'\s*\)/is"
    ),
    'registry custom types are limited to the legacy other compatibility bucket'
);

$withoutComments = preg_replace('/^\s*--.*$/m', '', $sql);
$withoutComments = is_string($withoutComments) ? $withoutComments : '';

verify_check(
    !sql_matches($withoutComments, '/\bUPDATE\s+(production_cycles|ruminant_animals)\b/i')
    && !sql_matches($withoutComments, '/\bINSERT\s+INTO\s+(production_cycles|ruminant_animals)\b/i')
    && !sql_matches($withoutComments, '/\bDELETE\s+FROM\s+(production_cycles|ruminant_animals)\b/i'),
    'migration performs no historical livestock or cycle data backfill'
);

verify_check(
    sql_matches(
        $sql,
        "/INSERT\s+INTO\s+schema_migrations\s*\(\s*filename\s*\)\s*VALUES\s*\(\s*'055_custom_livestock_type_foundation\.sql'\s*\)/is"
    ),
    'migration records the correct schema checkpoint'
);

verify_check(
    !sql_matches($withoutComments, '/\bCREATE\s+TRIGGER\b/i')
    && !sql_matches($withoutComments, '/\bDROP\s+TRIGGER\b/i')
    && strpos($normalized, 'http://') === false
    && strpos($normalized, 'https://') === false
    && strpos($normalized, 'curl') === false,
    'custom livestock type foundation uses no triggers or network/provider behavior'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    echo "V3.0 CUSTOM LIVESTOCK TYPE FOUNDATION: FAILED\n";
    exit(1);
}

echo "V3.0 CUSTOM LIVESTOCK TYPE FOUNDATION: PASSED\n";
exit(0);
