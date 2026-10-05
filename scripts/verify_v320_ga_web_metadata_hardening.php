<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$targets = [
    '.htaccess.example',
    'deployment/htaccess-hardening.conf',
];

$failures = [];

foreach ($targets as $relative) {
    $path = $root . '/' . $relative;

    if (!is_file($path)) {
        echo "FAIL: missing {$relative}" . PHP_EOL;
        $failures[] = $relative;
        continue;
    }

    $content = (string) file_get_contents($path);

    $required = [
        'BATCH_NOTES\.txt',
        'RELEASE_NOTES\.txt',
        'database_schema\.sql',
        'composer\.json',
        'composer\.lock',
        'Require all denied',
        'deployment|migrations|scripts|tests?|docs|\.github',
        '[F,L]',
    ];

    foreach ($required as $needle) {
        if (!str_contains($content, $needle)) {
            echo "FAIL: {$relative} missing {$needle}" . PHP_EOL;
            $failures[] = $relative . ':' . $needle;
        }
    }
}

if ($failures !== []) {
    echo 'V320_GA_WEB_METADATA_HARDENING=FAIL' . PHP_EOL;
    exit(1);
}

echo 'V320_GA_WEB_METADATA_HARDENING=PASS' . PHP_EOL;
exit(0);
