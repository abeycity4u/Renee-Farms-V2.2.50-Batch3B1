<?php
/**
 * Focused C137 Reports session-lock regression contract.
 * Static only: no application bootstrap or database connections.
 */
$root = dirname(__DIR__);
$report = $root . '/management/reports.php';
$helper = $root . '/includes/readonly_session.php';

$errors = [];
$checks = 0;

$check = static function(string $label, bool $ok)
    use (&$errors, &$checks): void {
    $checks++;
    if (!$ok) $errors[] = $label;
};

$source = is_file($report) ? (string)file_get_contents($report) : '';
$shared = is_file($helper) ? (string)file_get_contents($helper) : '';

$check('Reports readable', $source !== '');
$check('Shared helper readable', $shared !== '');
$check('Shared helper loaded',
    str_contains($source, 'includes/readonly_session.php'));
$check('Shared helper closes active session',
    str_contains($shared, 'session_write_close();'));

$calls = static function(string $name) use ($source): array {
    preg_match_all(
        '/\b' . preg_quote($name, '/') . '\s*\(/',
        $source,
        $matches,
        PREG_OFFSET_CAPTURE
    );
    return array_map(
        static fn(array $m): int => $m[1],
        $matches[0]
    );
};

$release = $calls('release_readonly_session_lock');
$check('Exactly one release', count($release) === 1);

if (count($release) === 1) {
    $at = $release[0];

    foreach ([
        'requireLogin',
        'requireBusinessReportAccess',
        'requireCurrentFarmId',
        'getUserType',
        'getUserFarmType',
        'isPlatformOwner',
        'hasRole',
        'current_farm_is_sales_only',
        'normalizeFarmType'
    ] as $name) {
        $positions = $calls($name);
        $check(
            $name . ' precedes release',
            count($positions) >= 1 && max($positions) < $at
        );
    }

    // Only the initial entitlement resolution must precede release.
    // Later rendering may read the already available session context.
    $accessible = $calls('accessibleFarmTypes');
    $check(
        'Entitlement resolved before release',
        count($accessible) >= 1 && min($accessible) < $at
    );

    $date = strpos($source, "\$endDate = \$year . '-12-31';");
    $check(
        'Date scope resolved before release',
        $date !== false && $date < $at
    );

    foreach ([
        'farm_intelligence_monthly_series',
        'farm_intelligence_top_products',
        'farm_intelligence_expense_breakdown'
    ] as $name) {
        $positions = $calls($name);
        $check(
            $name . ' follows release',
            count($positions) >= 1 && min($positions) > $at
        );
    }

    $after = substr($source, $at);

    $check(
        'No direct post-release session assignment',
        !preg_match(
            '/\$_SESSION\s*\[[^\]]+\]\s*(?:=(?!=)|\+=|-=|\+\+|--)/',
            $after
        )
    );

    $check(
        'No second session lifecycle operation',
        !preg_match(
            '/\b(session_start|session_regenerate_id|session_unset|'.
            'session_destroy|session_write_close)\s*\(/',
            $after
        )
    );
}

$check(
    'No duplicated low-level closure',
    !str_contains($source, 'session_write_close(')
);

if ($errors) {
    foreach ($errors as $error) {
        echo 'FAIL: ' . $error . PHP_EOL;
    }
}

echo 'FOCUSED_CHECKS=' . $checks . PHP_EOL;
echo 'FOCUSED_FAILURES=' . count($errors) . PHP_EOL;
exit($errors ? 1 : 0);
