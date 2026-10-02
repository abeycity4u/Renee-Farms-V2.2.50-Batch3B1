<?php

declare(strict_types=1);

/**
 * Source-side GA reachability audit.
 *
 * Maps potentially sensitive legacy/runtime helpers without executing the app.
 * It prints paths and classifications only; never request values, environment
 * values, source snippets, tokens or credentials.
 */

$root = dirname(__DIR__);
$files = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'php') {
        continue;
    }

    $path = $fileInfo->getPathname();
    $relative = ltrim(str_replace('\\', '/', substr($path, strlen($root))), '/');

    if (
        str_starts_with($relative, 'vendor/')
        || str_starts_with($relative, '.git/')
    ) {
        continue;
    }

    $content = @file_get_contents($path);
    if (!is_string($content)) {
        continue;
    }

    $files[$relative] = $content;
}

$migrationCallers = [];
$apiExceptionHelperCallers = [];
$rawThrowableResponseCandidates = [];

foreach ($files as $relative => $content) {
    $isScript = str_starts_with($relative, 'scripts/');

    if (
        $relative !== 'includes/functions.php'
        && !$isScript
        && str_contains($content, 'runSchemaMigrations(')
    ) {
        $migrationCallers[] = $relative;
    }

    if (
        $relative !== 'api/api_helpers.php'
        && !$isScript
        && str_contains($content, 'safe_api_exception_message(')
    ) {
        $apiExceptionHelperCallers[] = $relative;
    }

    if (!$isScript) {
        $responseShaped =
            str_contains($content, 'send_json(')
            || str_contains($content, 'json_encode(')
            || str_contains($content, 'echo ')
            || str_contains($content, 'print ');

        if ($responseShaped && str_contains($content, '->getMessage()')) {
            $rawThrowableResponseCandidates[] = $relative;
        }
    }
}

$migrationCallers = array_values(array_unique($migrationCallers));
$apiExceptionHelperCallers = array_values(array_unique($apiExceptionHelperCallers));
$rawThrowableResponseCandidates = array_values(array_unique($rawThrowableResponseCandidates));
sort($migrationCallers);
sort($apiExceptionHelperCallers);
sort($rawThrowableResponseCandidates);

echo 'SOURCE_FILES=' . count($files) . PHP_EOL;

echo 'RUNTIME_SCHEMA_MIGRATION_CALLERS=' . count($migrationCallers) . PHP_EOL;
foreach ($migrationCallers as $file) {
    echo 'MIGRATION_CALLER=' . $file . PHP_EOL;
}

echo 'SAFE_API_EXCEPTION_HELPER_CALLERS=' . count($apiExceptionHelperCallers) . PHP_EOL;
foreach ($apiExceptionHelperCallers as $file) {
    echo 'SAFE_API_EXCEPTION_CALLER=' . $file . PHP_EOL;
}

echo 'RAW_THROWABLE_RESPONSE_CANDIDATES=' . count($rawThrowableResponseCandidates) . PHP_EOL;
foreach ($rawThrowableResponseCandidates as $file) {
    echo 'THROWABLE_RESPONSE_CANDIDATE=' . $file . PHP_EOL;
}

if ($migrationCallers) {
    echo "GA_RUNTIME_MIGRATION_REACHABILITY=REVIEW\n";
} else {
    echo "GA_RUNTIME_MIGRATION_REACHABILITY=PASS_NO_CALLERS\n";
}

echo "GA_REACHABILITY_AUDIT=PASS\n";
exit(0);
