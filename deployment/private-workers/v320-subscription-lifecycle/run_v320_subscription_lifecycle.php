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

$authoritySource =
    $authoritySource;

$appRoot =
    $appRoot;

require_once $bridgePath;

try {
    v310_private_cli_bridge_import(
        $authoritySource
    );
} catch (Throwable $e) {
    fwrite(
        STDERR,
        'FAIL: lifecycle authority import failed ['
        . get_class($e)
        . '].'
        . PHP_EOL
    );

    exit(1);
}

if (
    !v310_private_cli_database_authority_ready()
) {
    fwrite(
        STDERR,
        "FAIL: lifecycle database authority is incomplete.\n"
    );

    exit(1);
}

$_SERVER['DOCUMENT_ROOT'] =
    $appRoot;

/*
 * Config is loaded only after private authority import.
 */
require_once
    $appRoot
    . '/config.php';

require_once
    $appRoot
    . '/scripts/run_v320_subscription_lifecycle.php';

try {
    $result =
        subscription_lifecycle_worker_run(
            $pdo
        );
} catch (Throwable $e) {
    fwrite(
        STDERR,
        'FAIL: subscription lifecycle worker failed ['
        . get_class($e)
        . '].'
        . PHP_EOL
    );

    exit(1);
}

echo "PASS: subscription lifecycle worker completed.\n";
echo "CHECKED="
    . (int)$result['checked']
    . PHP_EOL;
echo "TRANSITIONED="
    . (int)$result['transitioned']
    . PHP_EOL;
echo "FAILED="
    . (int)$result['failed']
    . PHP_EOL;

exit(
    (int)$result['failed'] === 0
        ? 0
        : 1
);
