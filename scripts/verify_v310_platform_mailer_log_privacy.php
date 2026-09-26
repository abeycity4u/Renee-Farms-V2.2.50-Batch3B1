<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$path =
    $root . '/includes/platform_mailer.php';

if (!is_file($path)) {
    echo "FAIL: platform mailer source is missing.\n";
    exit(1);
}

$source =
    (string)file_get_contents($path);

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

preg_match_all(
    '/error_log\s*\((.*?)\);/s',
    $source,
    $matches
);

$errorLogs =
    $matches[1] ?? [];

$errorLogText =
    implode(
        "\n",
        $errorLogs
    );

$check(
    'Mailer retains a generic transport failure log',
    str_contains(
        $source,
        'Platform mail transport rejected an outbound message via '
    )
);

$check(
    'Mailer failure log no longer includes recipient variable',
    !str_contains(
        $errorLogText,
        '$to'
    )
);

$check(
    'Mailer logs do not include message body',
    !str_contains(
        $errorLogText,
        '$body'
    )
);

$check(
    'Mailer logs do not include SMTP password variable',
    !str_contains(
        $errorLogText,
        '$password'
    )
);

$check(
    'Mailer logs do not reference SMTP password authority name',
    !str_contains(
        $errorLogText,
        'PLATFORM_SMTP_PASSWORD'
    )
);

$check(
    'Mailer logs do not include SMTP username variable',
    !str_contains(
        $errorLogText,
        '$username'
    )
);

$check(
    'Recipient-bearing legacy failure text is absent',
    !str_contains(
        $source,
        'Platform mail transport rejected an outbound message for '
    )
);

$check(
    'SMTP transport implementation remains present',
    str_contains(
        $source,
        'function platform_smtp_send('
    )
);

$check(
    'PHP mail transport implementation remains present',
    str_contains(
        $source,
        'function platform_php_mail_send('
    )
);

$check(
    'Central mail send API remains present',
    str_contains(
        $source,
        'function platform_mail_send('
    )
);

$check(
    'Central transport still returns mail result',
    str_contains(
        $source,
        "return \$result;"
    )
);

$pass =
    !in_array(
        false,
        $checks,
        true
    );

echo $pass
    ? "PLATFORM_MAILER_LOG_PRIVACY_CONTRACT=PASS\n"
    : "PLATFORM_MAILER_LOG_PRIVACY_CONTRACT=FAIL\n";

exit($pass ? 0 : 1);
