<?php

/**
 * Compatibility adapter for retired standalone Inventory Category routes.
 *
 * Category policy, validation, permissions and mutations are owned by the
 * canonical Inventory workspace. Old bookmarks remain usable, but these
 * compatibility routes must never implement their own category SQL.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';

requireLogin();

if (
    !isPlatformOwner()
    &&
    !hasRole('farm_admin')
) {
    header(
        'Location: '
        . BASE_URL
        . '/no_access.php'
    );

    exit();
}

$method =
    strtoupper(
        (string)(
            $_SERVER['REQUEST_METHOD']
            ?? 'GET'
        )
    );

/*
 * GET uses an ordinary compatibility redirect.
 * Any stale POST is converted to GET so a cached legacy form can never
 * execute a retired category mutation path.
 */
$status =
    $method === 'GET'
        ? 302
        : 303;

header(
    'Cache-Control: no-store'
);

header(
    'Location: '
    . BASE_URL
    . '/inventory.php?manage_categories=1',
    true,
    $status
);

exit();
