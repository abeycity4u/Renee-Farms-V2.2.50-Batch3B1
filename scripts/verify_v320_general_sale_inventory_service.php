<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$failures = [];

function pass_contract(
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

function read_contract_file(
    string $root,
    string $relative
): string {
    $path =
        $root
        . DIRECTORY_SEPARATOR
        . $relative;

    if (!is_file($path)) {
        throw new RuntimeException(
            "Missing {$relative}"
        );
    }

    $content =
        file_get_contents(
            $path
        );

    if ($content === false) {
        throw new RuntimeException(
            "Unable to read {$relative}"
        );
    }

    return $content;
}

function schema_table_block(
    string $schema,
    string $table
): string {
    $start =
        strpos(
            $schema,
            "CREATE TABLE `{$table}` ("
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
    $schema =
        read_contract_file(
            $root,
            'database_schema.sql'
        );

    $migration =
        read_contract_file(
            $root,
            'migrations/089_sales_only_general_inventory.sql'
        );

    $service =
        read_contract_file(
            $root,
            'lib/general_sale_inventory.php'
        );

    $manifest =
        read_contract_file(
            $root,
            'database_baseline_migrations.txt'
        );

    $salesBlock =
        schema_table_block(
            $schema,
            'sales_records'
        );

    pass_contract(
        str_contains(
            $salesBlock,
            '`stock_item_id` int(11) DEFAULT NULL'
        ),
        'fresh Sales schema contains nullable stock item link'
    );

    pass_contract(
        str_contains(
            $salesBlock,
            'KEY `idx_sales_stock_item` (`farm_id`,`stock_item_id`)'
        ),
        'fresh Sales schema indexes tenant-scoped stock item link'
    );

    $stockItemsBlock =
        schema_table_block(
            $schema,
            'stock_items'
        );

    pass_contract(
        str_contains(
            $stockItemsBlock,
            'UNIQUE KEY `uniq_stock_item_farm_identity` (`farm_id`,`id`)'
        ),
        'fresh Inventory schema exposes tenant-scoped stock identity'
    );

    pass_contract(
        str_contains(
            $salesBlock,
            'CONSTRAINT `fk_sales_stock_item`'
        )
        && str_contains(
            $salesBlock,
            'FOREIGN KEY (`farm_id`,`stock_item_id`)'
        )
        && str_contains(
            $salesBlock,
            'REFERENCES `stock_items` (`farm_id`,`id`) ON DELETE RESTRICT'
        ),
        'fresh Sales schema enforces tenant-scoped Inventory provenance'
    );

    pass_contract(
        str_contains(
            $migration,
            'ADD COLUMN stock_item_id INT NULL'
        )
        && str_contains(
            $migration,
            'ADD UNIQUE KEY uniq_stock_item_farm_identity'
        )
        && str_contains(
            $migration,
            'ADD CONSTRAINT fk_sales_stock_item'
        )
        && str_contains(
            $migration,
            'FOREIGN KEY ('
        )
        && str_contains(
            $migration,
            'farm_id,'
        )
        && str_contains(
            $migration,
            'stock_item_id'
        ),
        'migration 089 creates tenant-scoped General-sale stock item link'
    );

    pass_contract(
        !str_contains(
            $manifest,
            '089_sales_only_general_inventory.sql'
        ),
        'migration 089 remains outside frozen 001-088 fresh baseline manifest'
    );

    foreach (
        [
            'general_sale_inventory_available_items',
            'general_sale_inventory_item',
            'general_sale_inventory_active_movement',
            'general_sale_inventory_sync',
            'general_sale_inventory_reverse_for_delete',
        ]
        as $function
    ) {
        pass_contract(
            str_contains(
                $service,
                "function {$function}("
            ),
            "shared {$function} service exists"
        );
    }

    pass_contract(
        str_contains(
            $service,
            'stock_apply_movement('
        ),
        'General sale consumes stock through canonical stock service'
    );

    pass_contract(
        str_contains(
            $service,
            'stock_reverse_transaction('
        ),
        'General sale corrections restore stock append-only'
    );

    pass_contract(
        str_contains(
            $service,
            "general_sale_inventory_source_type()"
        )
        && str_contains(
            $service,
            "return 'general_sale';"
        ),
        'General sale stock movement has durable canonical source identity'
    );

    pass_contract(
        str_contains(
            $service,
            "si.farm_type='general'"
        )
        && str_contains(
            $service,
            "si.feed_category='general'"
        )
        && str_contains(
            $service,
            "'operational'"
        ),
        'General sale item catalog excludes livestock/feed/source-controlled stock'
    );

    pass_contract(
        str_contains(
            $service,
            'record_reference_persistence_require_transaction'
        ),
        'General sale stock lifecycle requires caller-owned transaction'
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
        'GENERAL_SALE_INVENTORY_SERVICE=FAIL'
        . PHP_EOL;

    exit(1);
}

echo
    'GENERAL_SALE_INVENTORY_SERVICE=PASS'
    . PHP_EOL;
