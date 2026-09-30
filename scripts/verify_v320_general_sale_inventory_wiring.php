<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$failures = [];

function check_contract(
    bool $ok,
    string $label
): void {
    global $failures;

    echo
        ($ok ? 'PASS: ' : 'FAIL: ')
        . $label
        . PHP_EOL;

    if (!$ok) {
        $failures[] =
            $label;
    }
}

function source_file(
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

try {
    $sales =
        source_file(
            $root,
            'management/sales_records.php'
        );

    $delete =
        source_file(
            $root,
            'api/delete_sale.php'
        );

    $service =
        source_file(
            $root,
            'lib/general_sale_inventory.php'
        );

    $js =
        source_file(
            $root,
            'assets/js/management-sales-records.js'
        );

    check_contract(
        substr_count(
            $service,
            "require_once __DIR__ . '/sales_units.php';"
        ) === 1,
        'General sale service loads Sales units exactly once'
    );

    check_contract(
        substr_count(
            $service,
            'function general_sale_inventory_selection_from_post'
        ) === 1,
        'General sale POST authority exists exactly once'
    );

    check_contract(
        substr_count(
            $sales,
            'general_sale_inventory_selection_from_post('
        ) === 2,
        'create and edit resolve General Inventory through shared service'
    );

    check_contract(
        substr_count(
            $sales,
            'general_sale_inventory_sync('
        ) === 2,
        'create and edit synchronize General stock exactly once each'
    );

    check_contract(
        substr_count(
            $sales,
            'value="general_inventory"'
        ) === 2,
        'Add and Edit expose General Inventory stock source'
    );

    check_contract(
        substr_count(
            $sales,
            'id="addGeneralInventoryItem"'
        ) === 1
        &&
        substr_count(
            $sales,
            'id="editGeneralInventoryItem"'
        ) === 1,
        'Sales forms expose exact General Inventory item selectors'
    );

    check_contract(
        substr_count(
            $sales,
            'data-stock-item='
        ) === 1,
        'edit action carries durable stock item identity'
    );

    check_contract(
        substr_count(
            $sales,
            'data-general-sale-inventory-items='
        ) === 1,
        'browser receives General Inventory catalog through CSP-safe config'
    );

    check_contract(
        substr_count(
            $delete,
            'general_sale_inventory_reverse_for_delete('
        ) === 1,
        'delete restores General Inventory exactly once'
    );

    check_contract(
        substr_count(
            $delete,
            'general_sale_inventory.php'
        ) === 1,
        'delete endpoint loads shared General Inventory service'
    );

    check_contract(
        str_contains(
            $js,
            'function setGeneralInventorySaleMode('
        )
        &&
        str_contains(
            $js,
            'function refreshGeneralInventorySale('
        ),
        'Sales browser supports General Inventory mode'
    );

    check_contract(
        substr_count(
            $js,
            "source === 'general_inventory'"
        ) === 2,
        'Add and Edit stock-source handlers activate General Inventory'
    );

    check_contract(
        str_contains(
            $js,
            'data.stockItem'
        ),
        'Edit modal restores durable stock item selection'
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
        'GENERAL_SALE_INVENTORY_WIRING=FAIL'
        . PHP_EOL;

    exit(1);
}

echo
    'GENERAL_SALE_INVENTORY_WIRING=PASS'
    . PHP_EOL;
