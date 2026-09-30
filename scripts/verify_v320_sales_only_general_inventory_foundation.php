<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$failures = [];

function contract_pass(
    bool $condition,
    string $message
): void {
    global $failures;

    if ($condition) {
        echo "PASS: {$message}\n";
        return;
    }

    echo "FAIL: {$message}\n";
    $failures[] = $message;
}

function contract_file(
    string $root,
    string $relative
): string {
    $path =
        $root
        . DIRECTORY_SEPARATOR
        . $relative;

    if (!is_file($path)) {
        throw new RuntimeException(
            "Missing source file: {$relative}"
        );
    }

    $content =
        file_get_contents($path);

    if ($content === false) {
        throw new RuntimeException(
            "Unable to read source file: {$relative}"
        );
    }

    return $content;
}

function contract_table_block(
    string $schema,
    string $table
): string {
    $startMarker =
        "CREATE TABLE `{$table}` (";

    $start =
        strpos(
            $schema,
            $startMarker
        );

    if ($start === false) {
        return '';
    }

    $end =
        strpos(
            $schema,
            ";\n",
            $start
        );

    if ($end === false) {
        return '';
    }

    return
        substr(
            $schema,
            $start,
            ($end - $start) + 2
        );
}

try {
    $migration =
        contract_file(
            $root,
            'migrations/089_sales_only_general_inventory.sql'
        );

    $schema =
        contract_file(
            $root,
            'database_schema.sql'
        );

    $config =
        contract_file(
            $root,
            'config.php'
        );

    $inventory =
        contract_file(
            $root,
            'inventory.php'
        );

    $financial =
        contract_file(
            $root,
            'lib/inventory_financial.php'
        );

    $attribution =
        contract_file(
            $root,
            'lib/stock_movement_attribution.php'
        );

    contract_pass(
        substr_count(
            $migration,
            "ENUM('poultry','ruminant','both','general')"
        ) === 3,
        'migration 089 expands exactly three Inventory farm-type enums'
    );

    foreach (
        [
            'inventory_categories',
            'stock_items',
            'stock_transactions',
        ]
        as $table
    ) {
        $block =
            contract_table_block(
                $schema,
                $table
            );

        contract_pass(
            $block !== ''
            && str_contains(
                $block,
                "enum('poultry','ruminant','both','general')"
            ),
            "{$table} fresh schema supports General inventory"
        );
    }

    contract_pass(
        str_contains(
            $config,
            'function allowedInventoryFarmTypes'
        ),
        'central Inventory farm-type entitlement helper exists'
    );

    contract_pass(
        str_contains(
            $config,
            "current_farm_has_entitlement('sales')"
        )
        && str_contains(
            $config,
            "\$types[] = 'general';"
        ),
        'General Inventory is tied to Sales entitlement'
    );

    contract_pass(
        !str_contains(
            $inventory,
            'allowedFarmTypes()'
        )
        && substr_count(
            $inventory,
            'allowedInventoryFarmTypes()'
        ) >= 5,
        'Inventory category/item validation uses shared Inventory scope'
    );

    contract_pass(
        str_contains(
            $inventory,
            'allowedInventoryFarmTypes(false)'
        ),
        'Sales-only Farm Admin receives a neutral Inventory page scope'
    );

    contract_pass(
        str_contains(
            $financial,
            "if (\$farmType === 'general')"
        )
        && str_contains(
            $financial,
            "return ['general' => 'General / Sales'];"
        ),
        'General Inventory owns canonical General production attribution'
    );

    contract_pass(
        str_contains(
            $attribution,
            "\$movementFarmType === 'general'"
        )
        && str_contains(
            $attribution,
            "\$requestedProduction =\n            'general';"
        ),
        'canonical stock movement resolver already supports General stock'
    );

    contract_pass(
        !preg_match(
            "/\\b(INSERT|UPDATE|DELETE)\\b.*\\b(farms|users|sales_records)\\b/is",
            $migration
        ),
        'migration 089 contains no tenant business-data rewrite'
    );

} catch (Throwable $e) {
    fwrite(
        STDERR,
        'FAIL: '
        . $e->getMessage()
        . PHP_EOL
    );

    exit(1);
}

if ($failures) {
    echo
        'GENERAL_INVENTORY_FOUNDATION=FAIL'
        . PHP_EOL;

    exit(1);
}

echo
    'GENERAL_INVENTORY_FOUNDATION=PASS'
    . PHP_EOL;
