<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$path = $root . '/.github/workflows/ga-e2e.yml';

$fail = 0;

if (!is_file($path)) {
    echo "E2E_WORKFLOW_FILE=FAIL" . PHP_EOL;
    exit(1);
}

$content = (string) file_get_contents($path);

$required = [
    'workflow_dispatch:',
    'environment: ga-staging',
    'actions/setup-node@v4',
    'node-version: "20"',
    'npx playwright install --with-deps chromium',
    'npm test',
    '${{ vars.E2E_BASE_URL }}',
    '${{ secrets.E2E_FARM_SLUG }}',
    '${{ secrets.E2E_USERNAME }}',
    '${{ secrets.E2E_PASSWORD }}',
    '${{ vars.E2E_FOREIGN_STOCK_ITEM_ID }}',
    'actions/upload-artifact@v4',
];

foreach ($required as $needle) {
    if (!str_contains($content, $needle)) {
        $fail++;
    }
}

$forbidden = [
    'E2E_DESTRUCTIVE_CREDENTIAL_TEST:',
    'E2E_RESET_URL:',
    'E2E_NEW_PASSWORD:',
    'E2E_DENIED_ROUTE:',
];

foreach ($forbidden as $needle) {
    if (str_contains($content, $needle)) {
        $fail++;
    }
}

echo "E2E_WORKFLOW_FILE=PASS" . PHP_EOL;
echo "E2E_ENVIRONMENT=ga-staging" . PHP_EOL;
echo "DESTRUCTIVE_PASSWORD_TEST=DISABLED" . PHP_EOL;
echo "E2E_WORKFLOW_CONTRACT=" . ($fail === 0 ? 'PASS' : 'FAIL') . PHP_EOL;

exit($fail === 0 ? 0 : 1);
