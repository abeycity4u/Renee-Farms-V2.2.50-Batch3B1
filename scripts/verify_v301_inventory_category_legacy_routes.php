<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$checks = 0;
$failed = 0;

function route_check(
    string $label,
    bool $condition
): void {
    global $checks, $failed;

    $checks++;

    echo
        $label
        . '='
        . (
            $condition
                ? 'PASS'
                : 'FAIL'
        )
        . PHP_EOL;

    if (!$condition) {
        $failed++;
    }
}

function route_source(
    string $path
): string {
    return is_file($path)
        ? (string)file_get_contents($path)
        : '';
}


$routes = [
    'add' =>
        $root
        . '/inventory/add_category.php',

    'list' =>
        $root
        . '/inventory/category_list.php',

    'delete' =>
        $root
        . '/inventory/delete_category.php',
];

$adapter =
    route_source(
        $root
        . '/includes/inventory_category_legacy_redirect.php'
    );

$inventory =
    route_source(
        $root
        . '/inventory.php'
    );

$inventoryJs =
    route_source(
        $root
        . '/assets/js/inventory.js'
    );


foreach (
    $routes
    as $key => $path
) {
    $source =
        route_source($path);

    route_check(
        strtoupper($key)
        . '_ROUTE_EXISTS',
        $source !== ''
    );

    route_check(
        strtoupper($key)
        . '_ROUTE_DELEGATES_SHARED_ADAPTER',
        strpos(
            $source,
            'inventory_category_legacy_redirect.php'
        ) !== false
    );

    route_check(
        strtoupper($key)
        . '_ROUTE_HAS_NO_CATEGORY_SQL',
        stripos(
            $source,
            'inventory_categories'
        ) === false
        &&
        stripos(
            $source,
            'INSERT INTO'
        ) === false
        &&
        stripos(
            $source,
            'UPDATE '
        ) === false
        &&
        stripos(
            $source,
            'DELETE FROM'
        ) === false
    );

    route_check(
        strtoupper($key)
        . '_ROUTE_HAS_NO_STANDALONE_FORM',
        stripos(
            $source,
            '<form'
        ) === false
    );
}


route_check(
    'SHARED_ADAPTER_REQUIRES_LOGIN',
    strpos(
        $adapter,
        'requireLogin();'
    ) !== false
);

route_check(
    'SHARED_ADAPTER_PRESERVES_ADMIN_BOUNDARY',
    strpos(
        $adapter,
        'isPlatformOwner()'
    ) !== false
    &&
    strpos(
        $adapter,
        "hasRole('farm_admin')"
    ) !== false
);

route_check(
    'SHARED_ADAPTER_REDIRECTS_CANONICAL_INVENTORY',
    strpos(
        $adapter,
        '/inventory.php?manage_categories=1'
    ) !== false
);

route_check(
    'SHARED_ADAPTER_CONVERTS_STALE_POST_TO_GET',
    strpos(
        $adapter,
        "$method === 'GET'"
    ) !== false
    &&
    strpos(
        $adapter,
        ': 303'
    ) !== false
);

route_check(
    'SHARED_ADAPTER_HAS_NO_CATEGORY_MUTATION_SQL',
    stripos(
        $adapter,
        'INSERT INTO'
    ) === false
    &&
    stripos(
        $adapter,
        'UPDATE '
    ) === false
    &&
    stripos(
        $adapter,
        'DELETE FROM'
    ) === false
);


route_check(
    'CANONICAL_CATEGORY_WORKSPACE_EXISTS',
    strpos(
        $inventory,
        'id="addCategoryModal"'
    ) !== false
    &&
    strpos(
        $inventory,
        'Manage Inventory Categories'
    ) !== false
);

route_check(
    'CANONICAL_CATEGORY_WORKSPACE_HAS_FINANCIAL_TYPE',
    strpos(
        $inventory,
        'name="category_financial_type"'
    ) !== false
    &&
    strpos(
        $inventory,
        'inventory_financial_classifications()'
    ) !== false
);

route_check(
    'CANONICAL_CATEGORY_WORKSPACE_HAS_RESPONSIVE_TABLE',
    strpos(
        $inventory,
        'table-responsive app-scroll-max-320'
    ) !== false
);

route_check(
    'CANONICAL_CATEGORY_WORKSPACE_OWNS_DELETE',
    strpos(
        $inventory,
        'name="delete_category"'
    ) !== false
);


route_check(
    'LEGACY_DEEPLINK_OPENS_CANONICAL_MODAL',
    strpos(
        $inventoryJs,
        'openRequestedCategoryWorkspace'
    ) !== false
    &&
    strpos(
        $inventoryJs,
        "params.get('manage_categories')"
    ) !== false
    &&
    strpos(
        $inventoryJs,
        "'addCategoryModal'"
    ) !== false
    &&
    strpos(
        $inventoryJs,
        'getOrCreateInstance'
    ) !== false
);


/*
 * Product source outside the three compatibility wrappers must not link back
 * into the retired standalone category pages.
 */
$legacyNames = [
    'add_category.php',
    'category_list.php',
    'delete_category.php',
];

$unexpectedProductLinks = [];

$iterator =
    new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $root,
            FilesystemIterator::SKIP_DOTS
        )
    );

foreach ($iterator as $file) {
    if (
        !$file->isFile()
    ) {
        continue;
    }

    $extension =
        strtolower(
            $file->getExtension()
        );

    if (
        !in_array(
            $extension,
            ['php', 'js'],
            true
        )
    ) {
        continue;
    }

    $path =
        $file->getPathname();

    $relative =
        str_replace(
            $root . DIRECTORY_SEPARATOR,
            '',
            $path
        );

    if (
        str_starts_with(
            $relative,
            'vendor/'
        )
        ||
        str_starts_with(
            $relative,
            'scripts/'
        )
        ||
        in_array(
            $relative,
            [
                'inventory/add_category.php',
                'inventory/category_list.php',
                'inventory/delete_category.php',
            ],
            true
        )
    ) {
        continue;
    }

    $source =
        route_source($path);

    foreach ($legacyNames as $legacyName) {
        if (
            strpos(
                $source,
                $legacyName
            ) !== false
        ) {
            $unexpectedProductLinks[] =
                $relative
                . ':'
                . $legacyName;
        }
    }
}

route_check(
    'NO_PRODUCT_LINKS_TO_RETIRED_CATEGORY_ROUTES',
    $unexpectedProductLinks === []
);

if ($unexpectedProductLinks) {
    echo
        'UNEXPECTED_PRODUCT_LINKS='
        . implode(
            ',',
            $unexpectedProductLinks
        )
        . PHP_EOL;
}


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
