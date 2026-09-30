<?php

declare(strict_types=1);

$home =
    rtrim(
        (string) (
            getenv('HOME')
            ?: ''
        ),
        '/'
    );

$appRoot =
    (string) (
        getenv('RENEE_APP_ROOT')
        ?: (
            $home !== ''
                ? $home . '/public_html'
                : ''
        )
    );

$privateRoot =
    (string) (
        getenv('RENEE_PRIVATE_ROOT')
        ?: (
            $home !== ''
                ? $home . '/renee-private'
                : ''
        )
    );

$authoritySource =
    (string) (
        getenv('RENEE_ENV_SOURCE')
        ?: (
            $appRoot !== ''
                ? rtrim($appRoot, '/') . '/.htaccess'
                : ''
        )
    );

if (
    $appRoot === ''
    || $privateRoot === ''
    || $authoritySource === ''
) {
    fwrite(
        STDERR,
        "FAIL: portable worker path authority is incomplete.\n"
    );

    exit(1);
}


if (PHP_SAPI !== 'cli') {
    exit(1);
}

$bridgePath =
    $privateRoot . '/'
    . 'v310-credential-worker/'
    . 'v310_private_cli_bridge.php';

$appRoot =
    $appRoot;

$authoritySource =
    $authoritySource;

require_once $bridgePath;

try {
    v310_private_cli_bridge_import(
        $authoritySource
    );
} catch (Throwable $e) {
    fwrite(
        STDERR,
        'FAIL: private subscription reminder authority import failed ['
        . get_class($e)
        . '].'
        . PHP_EOL
    );

    exit(1);
}

$_SERVER['DOCUMENT_ROOT'] =
    $appRoot;

$send =
    in_array(
        '--send',
        $argv ?? [],
        true
    );

/*
 * Sending is deliberately fail-closed.
 * Dry-run remains available without SEND execution.
 */
if (
    $send
    && !v310_private_cli_send_authority_ready()
) {
    fwrite(
        STDERR,
        "FAIL: subscription reminder SEND authority is incomplete.\n"
    );

    exit(1);
}

require
    $appRoot
    . '/scripts/run_v230_subscription_renewal_reminders.php';
