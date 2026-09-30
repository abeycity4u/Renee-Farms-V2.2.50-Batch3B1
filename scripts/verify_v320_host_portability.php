<?php
declare(strict_types=1);

$root = dirname(__DIR__);

$required = [
    '.htaccess.example',
    'uploads/.gitkeep',

    'deployment/install_private_workers.sh',
    'deployment/show_cron_jobs.sh',

    'deployment/private-workers/README.md',
    'deployment/private-workers/v310-credential-worker/SOURCE_MAP.txt',

    'deployment/private-workers/v320-subscription-lifecycle/run_v320_subscription_lifecycle.php',
    'deployment/private-workers/v320-subscription-lifecycle/run_v320_subscription_lifecycle_production.sh',

    'deployment/private-workers/v320-subscription-reminder/run_v320_subscription_renewal_reminders.php',
    'deployment/private-workers/v320-subscription-reminder/run_v320_subscription_renewal_reminders_production.sh',

    'scripts/run_v310_account_credential_outbox.php',
    'scripts/run_v310_credential_worker_production.sh',
    'scripts/v310_private_cli_bridge.php',

    'scripts/run_v320_subscription_lifecycle.php',
    'scripts/run_v230_subscription_renewal_reminders.php',
];

$failures = [];

foreach ($required as $relative) {
    if (!is_file($root . '/' . $relative)) {
        $failures[] = 'missing:' . $relative;
    }
}

$portableScope = [
    'deployment/private-workers',
    'deployment/install_private_workers.sh',
    'deployment/show_cron_jobs.sh',
];

foreach ($portableScope as $relative) {
    $path = $root . '/' . $relative;

    if (is_dir($path)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $path,
                FilesystemIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            if (
                $contents !== false
                && strpos($contents, '/home/renee/') !== false
            ) {
                $failures[] =
                    'hardcoded-host-path:'
                    . substr(
                        $file->getPathname(),
                        strlen($root) + 1
                    );
            }
        }

        continue;
    }

    if (is_file($path)) {
        $contents = file_get_contents($path);

        if (
            $contents !== false
            && strpos($contents, '/home/renee/') !== false
        ) {
            $failures[] = 'hardcoded-host-path:' . $relative;
        }
    }
}

$htaccess = file_get_contents(
    $root . '/.htaccess.example'
);

if ($htaccess === false) {
    $failures[] = 'unreadable:.htaccess.example';
} else {
    $requiredKeys = [
        'DB_HOST',
        'DB_NAME',
        'DB_USER',
        'DB_PASS',

        'PLATFORM_SMTP_HOST',
        'PLATFORM_SMTP_USERNAME',
        'PLATFORM_SMTP_PASSWORD',

        'PLATFORM_MAIL_FROM_BILLING',
        'PLATFORM_MAIL_FROM_ONBOARDING',
        'PLATFORM_MAIL_FROM_SECURITY',
        'PLATFORM_MAIL_FROM_NOTIFICATIONS',
    ];

    foreach ($requiredKeys as $key) {
        if (
            preg_match(
                '/^\s*SetEnv\s+'
                . preg_quote($key, '/')
                . '\s+/m',
                $htaccess
            ) !== 1
        ) {
            $failures[] =
                'missing-htaccess-key:' . $key;
        }
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo 'FAIL: ' . $failure . PHP_EOL;
    }

    exit(1);
}

echo "PASS: Renee AgriSuite host portability contract verified."
    . PHP_EOL;

exit(0);
