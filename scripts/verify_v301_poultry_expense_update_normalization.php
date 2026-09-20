<?php

declare(strict_types=1);

$root =
    dirname(
        __DIR__
    );

$path =
    $root
    . '/api/update_expense.php';

$src =
    is_file($path)
        ? (string)file_get_contents($path)
        : '';

$checks = 0;
$failed = 0;

function poultry_expense_update_normalization_check(
    string $name,
    bool $ok
): void {
    global $checks, $failed;

    $checks++;

    echo
        $name
        . '='
        . (
            $ok
                ? 'PASS'
                : 'FAIL'
        )
        . PHP_EOL;

    if (!$ok) {
        $failed++;
    }
}


poultry_expense_update_normalization_check(
    'OLD_POULTRY_CATEGORY_OVERRIDE_RETIRED',
    strpos(
        $src,
        "\$requestedProduction=\$poultryCategory"
    ) === false
);


poultry_expense_update_normalization_check(
    'PRODUCTION_TYPE_IS_PRIMARY_REQUEST',
    strpos(
        $src,
        "\$_POST['production_type']"
    ) !== false
    &&
    strpos(
        $src,
        "\$existing['production_type']"
    ) !== false
);


poultry_expense_update_normalization_check(
    'LEGACY_CATEGORY_IS_FALLBACK_ONLY',
    strpos(
        $src,
        '$legacyPoultryCategory'
    ) !== false
    &&
    strpos(
        $src,
        "!in_array(\n                \$requestedProduction"
    ) !== false
);


poultry_expense_update_normalization_check(
    'POULTRY_CANONICAL_TYPES_INCLUDE_SHARED',
    preg_match(
        "/'layer'\\s*,[\\s\\S]{0,100}'broiler'\\s*,[\\s\\S]{0,100}'shared'/",
        $src
    ) === 1
);


poultry_expense_update_normalization_check(
    'POULTRY_CATEGORY_DERIVED_FROM_CANONICAL_PRODUCTION',
    strpos(
        $src,
        "\$poultryCategory ="
    ) !== false
    &&
    strpos(
        $src,
        "? \$productionType"
    ) !== false
    &&
    strpos(
        $src,
        ': null;'
    ) !== false
);


$sharedProductionPos =
    strpos(
        $src,
        "\$productionType === 'shared'"
    );

$sharedWindow =
    $sharedProductionPos === false
        ? ''
        : substr(
            $src,
            $sharedProductionPos,
            700
        );

$sharedZeroPos =
    $sharedProductionPos === false
        ? false
        : strpos(
            $src,
            '$cycleId =',
            $sharedProductionPos
        );

$cycleValidationPos =
    $sharedZeroPos === false
        ? false
        : strpos(
            $src,
            'attribution_validate_cycle(',
            $sharedZeroPos
        );

$attributionScopePos =
    $sharedZeroPos === false
        ? false
        : strpos(
            $src,
            'attribution_scope(',
            $sharedZeroPos
        );


poultry_expense_update_normalization_check(
    'SHARED_FORCES_ZERO_CYCLE',
    $sharedProductionPos !== false
    &&
    preg_match(
        '/\$cycleId\s*=\s*0\s*;/',
        $sharedWindow
    ) === 1
    &&
    $sharedZeroPos !== false
    &&
    $cycleValidationPos !== false
    &&
    $attributionScopePos !== false
    &&
    $sharedProductionPos < $sharedZeroPos
    &&
    $sharedZeroPos < $cycleValidationPos
    &&
    $sharedZeroPos < $attributionScopePos
);


poultry_expense_update_normalization_check(
    'CANONICAL_TARGET_SCOPE_USES_DERIVED_FIELDS',
    strpos(
        $src,
        "'production_type' =>\n            \$productionType"
    ) !== false
    &&
    strpos(
        $src,
        "'poultry_category' =>\n            \$poultryCategory"
    ) !== false
);


poultry_expense_update_normalization_check(
    'EXISTING_AND_TARGET_PERMISSION_CHECKS_PRESERVED',
    substr_count(
        $src,
        'permission_catalog_expense_operational_can('
    ) >= 2
);


poultry_expense_update_normalization_check(
    'CYCLE_VALIDATION_USES_CANONICAL_PRODUCTION',
    preg_match(
        '/attribution_validate_cycle\s*\(\s*\$pdo\s*,\s*\$farmId\s*,\s*\$cycleId\s*,\s*\$farmType\s*,\s*\$productionType\s*\)/s',
        $src
    ) === 1
);


poultry_expense_update_normalization_check(
    'ATTRIBUTION_SCOPE_USES_CANONICAL_PRODUCTION',
    preg_match(
        '/attribution_scope\s*\([\s\S]{0,180}\$farmType\s*,\s*\$productionType\s*\)/',
        $src
    ) === 1
);


poultry_expense_update_normalization_check(
    'INTEGRITY_GUARD_REMAINS_BEFORE_UPDATE_SQL',
    ($integrityPos =
        strpos(
            $src,
            'financial_allocation_integrity_assert_parent_update('
        )
    ) !== false
    &&
    ($updatePos =
        strpos(
            $src,
            'UPDATE farm_expenses'
        )
    ) !== false
    &&
    $integrityPos < $updatePos
);


poultry_expense_update_normalization_check(
    'REVISION_WRITER_REMAINS_AROUND_UPDATE',
    strpos(
        $src,
        'expense_revision_service_prepare_existing_mutation('
    ) !== false
    &&
    strpos(
        $src,
        'expense_revision_service_record_updated('
    ) !== false
);


poultry_expense_update_normalization_check(
    'UPDATE_WRITES_CANONICAL_SCOPE_COLUMNS',
    strpos(
        $src,
        'production_type=?, attribution_scope=?, cycle_id=?, poultry_category=?'
    ) !== false
);


poultry_expense_update_normalization_check(
    'NO_NEW_SCHEMA_DDL',
    preg_match(
        '/\b(?:ALTER|CREATE|DROP)\s+TABLE\b/i',
        $src
    ) !== 1
);


echo
    'CHECK_COUNT='
    . $checks
    . PHP_EOL;

echo
    'FAILED_COUNT='
    . $failed
    . PHP_EOL;

echo
    'RESULT='
    . (
        $failed === 0
            ? 'PASS'
            : 'FAIL'
    )
    . PHP_EOL;

exit(
    $failed === 0
        ? 0
        : 1
);
