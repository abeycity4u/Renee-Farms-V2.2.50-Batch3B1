<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$launcherPath =
    $root
    . '/scripts/run_v310_credential_worker_production.sh';

$workerPath =
    $root
    . '/scripts/run_v310_account_credential_outbox.php';

if (
    !is_file($launcherPath)
    || !is_file($workerPath)
) {
    echo "FAIL: production launcher source contract is incomplete.\n";
    exit(1);
}

$launcher =
    (string)file_get_contents(
        $launcherPath
    );

$checks = [];

$check = static function (
    string $label,
    bool $ok
) use (&$checks): void {
    $checks[] = $ok;

    echo ($ok ? 'PASS: ' : 'FAIL: ')
        . $label
        . PHP_EOL;
};

$check(
    'Launcher uses restrictive process umask',
    str_contains(
        $launcher,
        'umask 077'
    )
);

$check(
    'Launcher targets the shared credential worker',
    str_contains(
        $launcher,
        'run_v310_account_credential_outbox.php'
    )
);

$check(
    'Launcher defaults to dry-run',
    str_contains(
        $launcher,
        'MODE="dry-run"'
    )
);

$check(
    'Launcher requires explicit send switch',
    str_contains(
        $launcher,
        '"${1:-}" = "--send"'
    )
);

$check(
    'Production send is bounded to 25 jobs',
    str_contains(
        $launcher,
        'MAX_JOBS=25'
    )
    && str_contains(
        $launcher,
        '--max="$MAX_JOBS"'
    )
);

$check(
    'Launcher requires explicit application root path',
    str_contains(
        $launcher,
        'RENEE_CREDENTIAL_APP_ROOT'
    )
);

$check(
    'Launcher requires explicit authority-source path',
    str_contains(
        $launcher,
        'RENEE_CREDENTIAL_ENV_SOURCE'
    )
);

$check(
    'Launcher uses private local execution lock',
    str_contains(
        $launcher,
        'production-launcher.lock'
    )
    && str_contains(
        $launcher,
        'flock -n 9'
    )
);

$check(
    'Launcher bounds current log at one MiB',
    str_contains(
        $launcher,
        'MAX_LOG_BYTES=1048576'
    )
);

$check(
    'Launcher keeps one rotated log generation',
    str_contains(
        $launcher,
        'worker.log.1'
    )
    && str_contains(
        $launcher,
        'rm -f "$ROTATED_LOG"'
    )
);

$check(
    'Launcher keeps current log private',
    str_contains(
        $launcher,
        'chmod 600 "$LOG_FILE"'
    )
);

$check(
    'Launcher captures worker stdout and stderr privately',
    str_contains(
        $launcher,
        '>>"$LOG_FILE" 2>&1'
    )
);

$secretAssignment =
    preg_match(
        '/(?:DB_PASS|PLATFORM_SMTP_PASSWORD)\s*=/',
        $launcher
    ) === 1;

$check(
    'Launcher contains no database or SMTP secret assignment',
    !$secretAssignment
);

$check(
    'Launcher never embeds raw mail authority names as values',
    !str_contains(
        $launcher,
        'PLATFORM_SMTP_PASSWORD='
    )
);

$pass =
    !in_array(
        false,
        $checks,
        true
    );

echo $pass
    ? "PRODUCTION_LAUNCHER_CONTRACT=PASS\n"
    : "PRODUCTION_LAUNCHER_CONTRACT=FAIL\n";

exit($pass ? 0 : 1);
