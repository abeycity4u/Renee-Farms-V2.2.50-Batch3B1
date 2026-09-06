<?php
/**
 * V2.3 pre-billing UI feedback/dialog debt verifier.
 *
 * Read-only: scans first-party PHP/JavaScript source for native browser dialogs
 * and checks that the Ruminant validation paths use the shared session
 * notification flow rather than dumping plain validation responses.
 */

$root = dirname(__DIR__);
$failures = 0;
$checks = 0;

$check = static function (string $label, bool $ok) use (&$failures, &$checks): void {
    $checks++;
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    if (!$ok) $failures++;
};

$excludedSegments = [
    '.git',
    'vendor',
    'node_modules',
    'scripts',
    'migrations',
    'tests',
    'docs',
    'storage',
    'tmp',
    'backups',
];

$dialogPattern = '~(?<![A-Za-z0-9_$])(?:(?:window|globalThis)\s*\.\s*)?(alert|confirm|prompt)\s*\(~i';
$dialogMatches = [];
$scannedFiles = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile()) continue;

    $path = $fileInfo->getPathname();
    $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
    $segments = explode('/', $relative);

    $skip = false;
    foreach ($segments as $segment) {
        if (in_array($segment, $excludedSegments, true)) {
            $skip = true;
            break;
        }
    }
    if ($skip) continue;

    $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
    if (!in_array($extension, ['php', 'js'], true)) continue;

    $content = @file_get_contents($path);
    if ($content === false) continue;
    $scannedFiles++;

    foreach (preg_split('/\R/', $content) ?: [] as $index => $line) {
        $trimmed = ltrim($line);
        // Ignore pure comment/documentation lines; inline executable source is
        // still scanned so commented labels do not create false failures.
        if (preg_match('~^(?://|/\*|\*|#|<!--)~', $trimmed)) continue;
        if (!preg_match_all($dialogPattern, $line, $matches, PREG_OFFSET_CAPTURE)) continue;

        foreach ($matches[1] as [$kind]) {
            $dialogMatches[] = [
                'file' => $relative,
                'line' => $index + 1,
                'kind' => strtolower($kind),
                'source' => trim($line),
            ];
        }
    }
}

$check('first-party PHP/JavaScript files were scanned', $scannedFiles > 0);
$check('no native browser alert()/confirm()/prompt() calls remain in first-party application source', !$dialogMatches);

if ($dialogMatches) {
    echo PHP_EOL . 'Native browser dialog matches:' . PHP_EOL;
    foreach ($dialogMatches as $match) {
        echo '  ' . $match['file'] . ':' . $match['line'] . ' [' . $match['kind'] . '] ' . $match['source'] . PHP_EOL;
    }
}

$registryPath = $root . '/ruminant/animal_registry.php';
$profilePath = $root . '/ruminant/animal_view.php';
$navbarPath = $root . '/navbar.php';
$notificationsPath = $root . '/includes/notifications.php';

$registry = is_file($registryPath) ? (string)file_get_contents($registryPath) : '';
$profile = is_file($profilePath) ? (string)file_get_contents($profilePath) : '';
$navbar = is_file($navbarPath) ? (string)file_get_contents($navbarPath) : '';
$notifications = is_file($notificationsPath) ? (string)file_get_contents($notificationsPath) : '';

$check('Ruminant Registry no longer dumps Invalid animal data as a plain response',
    $registry !== '' && !str_contains($registry, "exit('Invalid animal data.')"));
$check('Ruminant Registry invalid animal form data uses session error feedback',
    str_contains($registry, "\$_SESSION['error'] = 'Enter a tag number and select a valid species, sex, and non-negative purchase cost.'")
    && str_contains($registry, "header('Location: animal_registry.php' . (\$id > 0 ? '?edit='.\$id : ''))"));

$check('Animal Profile invalid weight uses session notification redirect',
    str_contains($profile, "\$_SESSION['error'] = 'Enter a valid weighing date and weight.'")
    && str_contains($profile, "header('Location: animal_view.php?id='.\$animalId.'#weight-history')"));
$check('Animal Profile invalid health event uses session notification redirect',
    str_contains($profile, "\$_SESSION['error'] = 'Enter a valid health-event date and type.'")
    && str_contains($profile, "header('Location: animal_view.php?id='.\$animalId.'#health-history')"));
$check('Animal Profile invalid withdrawal date uses session notification redirect',
    str_contains($profile, "\$_SESSION['error'] = 'Enter a valid withdrawal date.'")
    && str_contains($profile, "header('Location: animal_view.php?id='.\$animalId.'#health-history')"));
$check('Animal Profile normal validation no longer returns HTTP 422 plain responses',
    !str_contains($profile, 'http_response_code(422)'));

// Guard against accidentally weakening the security/route failure boundaries
// while cleaning up only normal form validation feedback.
$check('Animal Profile keeps invalid-id 400 boundary',
    str_contains($profile, "http_response_code(400); exit('Invalid animal.')"));
$check('Animal Profile keeps authorization 403 boundary',
    str_contains($profile, "http_response_code(403); exit('Access denied.')"));
$check('Animal Profile keeps CSRF 419 boundary',
    str_contains($profile, "http_response_code(419); exit('Invalid request token.')"));
$check('Animal Profile keeps tenant-scoped not-found 404 boundary',
    str_contains($profile, "http_response_code(404); exit('Animal not found.')"));

$check('navbar renders the shared session notification surface',
    str_contains($navbar, 'renderSessionNotifications()'));
$check('shared notification renderer consumes session error and success messages',
    str_contains($notifications, "['error', \$_SESSION['error'] ?? null]")
    && str_contains($notifications, "['success', \$_SESSION['success'] ?? null]"));

$totalDialogs = count($dialogMatches);
echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;
echo 'Scanned ' . $scannedFiles . ' first-party PHP/JavaScript files; native dialog matches: ' . $totalDialogs . '.' . PHP_EOL;

if ($failures === 0) {
    echo 'PASS: V2.3 pre-billing UI dialog/validation feedback sweep is statically clean.' . PHP_EOL;
    exit(0);
}

echo 'FAIL: Review the reported UI dialog/feedback debt before billing work begins.' . PHP_EOL;
exit(1);
