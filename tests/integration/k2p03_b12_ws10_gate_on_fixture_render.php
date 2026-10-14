<?php
declare(strict_types=1);

/**
 * WS10 real-PHP gate-ON *fragment rendering* simulation.
 *
 * Executes unchanged copies of the shared renderer/asset plan and exact
 * extracted PHP page footer/config/delete fragments under a TEST-ONLY
 * gate shim in a disposable directory. The repository gate remains literal
 * return false. No PHP application/bootstrap, HTTP, DB, migrations or live
 * page include. The runner must provide a temporary writable /tmp only.
 */
$root = dirname(__DIR__, 2);
$passes = 0;
$fixtureDir = null;
$originalGate = $root . '/lib/daily_record_revision_cutover_gate.php';
$gateShaBefore = hash_file('sha256', $originalGate);

function ws10_check(bool $ok, string $reason): void {
    global $passes;
    if (!$ok) {
        throw new RuntimeException('FAIL: ' . $reason);
    }
    ++$passes;
}
function ws10_file(string $path): string {
    global $root;
    $content = file_get_contents($root . '/' . $path);
    if (!is_string($content)) {
        throw new RuntimeException('Cannot read source ' . $path);
    }
    return $content;
}
function ws10_fixture_file(string $directory, string $relative, string $data): string {
    $target = $directory . '/' . $relative;
    $parent = dirname($target);
    if (!is_dir($parent) && !mkdir($parent, 0700, true) && !is_dir($parent)) {
        throw new RuntimeException('Fixture directory unavailable.');
    }
    if (file_put_contents($target, $data, LOCK_EX) !== strlen($data)) {
        throw new RuntimeException('Fixture source was not completely written.');
    }
    return $target;
}
function ws10_cleanup(?string $base): void {
    if ($base === null || !str_starts_with($base, sys_get_temp_dir() . '/b12-ws10-')) {
        return;
    }
    if (!is_dir($base) || is_link($base)) return;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $item) {
        if ($item->isDir() && !$item->isLink()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($base);
}
function ws10_fragment(
    string $page, string $needle, string $beginTag, string $endTag
): string {
    $at = strpos($page, $needle);
    if ($at === false) {
        throw new RuntimeException('Required page fragment missing: ' . $needle);
    }
    $start = strrpos(substr($page, 0, $at + 1), $beginTag);
    $end = strpos($page, $endTag, $at);
    if ($start === false || $end === false || $start > $at) {
        throw new RuntimeException('Unsafe page-fragment boundary.');
    }
    return substr($page, $start, $end + strlen($endTag) - $start);
}
function ws10_include(string $file, int $cycleId): string {
    $selectedCycleId = $cycleId;
    ob_start();
    try {
        include $file;
        return (string)ob_get_clean();
    } catch (Throwable $error) {
        ob_end_clean();
        throw $error;
    }
}
function ws10_script_urls(string $html): array {
    preg_match_all('~<script src="([^"]+)"></script>~', $html, $matches);
    return $matches[1];
}
// Real application code calls a common versioned_asset() function. Only
// the fixture replaces its infrastructure-dependent version lookup.
function versioned_asset(string $asset): string {
    if (!preg_match('~^/assets/js/[a-z-]+\.js$~D', $asset)) {
        throw new LogicException('Unexpected script path in fixture.');
    }
    return $asset . '?v=ws10';
}
define('BASE_URL', 'https://b12-ws10.invalid');

try {
    ws10_check(
        is_string($gateShaBefore) &&
        preg_match(
            '~function\s+daily_record_revision_cutover_enabled\s*\(\s*\)\s*:\s*bool\s*\{\s*return\s+false\s*;\s*\}~',
            ws10_file('lib/daily_record_revision_cutover_gate.php')
        ) === 1,
        'committed source cutover is literally OFF'
    );

    $fixtureDir = sys_get_temp_dir() . '/b12-ws10-' . bin2hex(random_bytes(10));
    if (!mkdir($fixtureDir, 0700) || is_link($fixtureDir)) {
        throw new RuntimeException('Isolated fixture directory unavailable.');
    }
    ws10_fixture_file($fixtureDir, 'lib/daily_record_revision_cutover_gate.php',
        "<?php\ndeclare(strict_types=1);\n"
        . "function daily_record_revision_cutover_enabled(): bool { return true; }\n"
    );
    foreach ([
        'lib/daily_record_revision_page_asset_plan.php',
        'lib/daily_record_revision_page_switch.php'
    ] as $path) {
        ws10_fixture_file($fixtureDir, $path, ws10_file($path));
    }
    require $fixtureDir . '/lib/daily_record_revision_page_switch.php';
    ws10_check(daily_record_revision_cutover_enabled() === true,
        'only isolated fixture process has simulated ON authority');

    $rendered = [];
    $types = [
        'layer' => ['poultry/layers_daily_record.php', 'layers-daily-record.js'],
        'broiler' => ['poultry/broiler_daily_record.php', 'broiler-daily-record.js'],
        'ruminant' => ['ruminant/ruminant_daily_record.php', 'ruminant-daily-record.js']
    ];
    foreach ($types as $type => [$path, $legacy]) {
        $page = ws10_file($path);

        // Evaluate the EXACT config marker PHP tag, not a duplicate
        // hand-authored implementation.
        $markerSource = ws10_fragment(
            $page,
            "daily_record_revision_page_future_markers('" . $type . "', (int)\$selectedCycleId)",
            '<?php',
            '?>'
        );
        ws10_check(
            str_contains($markerSource, 'daily_record_revision_page_switch.php') &&
            str_contains($markerSource, "daily_record_revision_page_future_markers('" . $type . "'"),
            $type . ' marker source uses the original page helper'
        );
        $markerFile = ws10_fixture_file(
            $fixtureDir, dirname($path) . '/ws10-config.php', $markerSource
        );
        $exactMarkers = ws10_include($markerFile, 17);
        $allMarkers = ws10_include($markerFile, 0);
        ws10_check(
            str_contains($exactMarkers, 'data-b12-revision-page="' . $type . '"') &&
            str_contains($exactMarkers, 'data-b12-legacy-retired="1"') &&
            str_contains($exactMarkers, 'data-b12-delete-ownership="replacement"') &&
            trim($allMarkers) === '',
            $type . ' exact scope receives ownership; All Cycles does not'
        );

        // Evaluate each page's EXACT on/off footer including its legacy
        // script ELSE; in the isolated ON process the old owner must vanish.
        $footerStart = strpos($page, "<?php\n// B12 source-only switch:");
        $footerEnd = $footerStart === false ? false :
            strpos($page, '<?php endif; ?>', $footerStart);
        ws10_check(
            $footerStart !== false && $footerEnd !== false &&
            $footerStart < $footerEnd,
            $type . ' original page footer has guarded switch'
        );
        $footer = substr(
            $page,
            $footerStart,
            $footerEnd + strlen('<?php endif; ?>') - $footerStart
        );
        ws10_check(
            str_contains($footer, "daily_record_revision_page_future_script_tags('" . $type . "'") &&
            str_contains($footer, "versioned_asset('/assets/js/" . $legacy . "')"),
            $type . ' original page footer includes exclusive script branches'
        );
        $footerFile = ws10_fixture_file(
            $fixtureDir, dirname($path) . '/ws10-footer.php', $footer
        );
        $exact = ws10_include($footerFile, 17);
        $all = ws10_include($footerFile, 0);
        $rendered[$type] = [
            'exact' => ['markers' => $exactMarkers, 'html' => $exact],
            'all' => ['markers' => $allMarkers, 'html' => $all],
        ];

        $plan = daily_record_revision_page_asset_plan($type);
        $expected = array_map(
            static fn(string $file): string =>
                BASE_URL . '/' . $file . '?v=ws10',
            $plan['future_assets']
        );
        $urls = ws10_script_urls($exact);
        ws10_check(
            $urls === $expected &&
            count($urls) === count(array_unique($urls)) &&
            str_ends_with(end($urls), '/daily-record-revision-page-bootstrap.js?v=ws10'),
            $type . ' exact-cycle scripts render in manifest dependency order'
        );
        ws10_check(
            !str_contains($exact, $legacy) &&
            substr_count($exact, '<script>') === 1 &&
            str_contains($exact, 'ReneeDailyRecordRevisionPageBootstrap') &&
            str_contains($exact, '.create("' . $type . '",') &&
            str_contains($exact, '}).bind();'),
            $type . ' exact-cycle has one initializer and no legacy script'
        );
        ws10_check(
            ws10_script_urls($all) === [
                BASE_URL . '/assets/js/daily-record-revision-readonly-nav.js?v=ws10'
            ] &&
            !str_contains($all, $legacy) &&
            !str_contains($all, 'ReneeDailyRecordRevisionPageBootstrap') &&
            str_contains($all, 'ReneeDailyRecordRevisionReadOnlyNav') &&
            str_contains($all, '.create("' . $type . '",') &&
            substr_count($all, '<script>') === 1,
            $type . ' All Cycles only binds its read-only navigation'
        );
    }

    $ruminantSource = ws10_file('ruminant/ruminant_daily_record.php');
    $ruminantStart = strpos(
        $ruminantSource,
        '<form method="POST" class="d-inline" <?php if (daily_record_revision_cutover_enabled()): ?>'
    );
    $ruminantEnd = $ruminantStart === false ? false :
        strpos($ruminantSource, '><input type="hidden" name="csrf_token"',
            $ruminantStart);
    ws10_check(
        $ruminantStart !== false && $ruminantEnd !== false,
        'Ruminant DELETE form ownership source fragment found'
    );
    $formFragment = substr(
        $ruminantSource, $ruminantStart, $ruminantEnd + 1 - $ruminantStart
    );
    $formFile = ws10_fixture_file(
        $fixtureDir, 'ruminant/ws10-delete-form.php', $formFragment
    );
    $formHtml = ws10_include($formFile, 17);
    ws10_check(
        str_contains($formHtml, 'data-b12-revision-delete="ruminant"') &&
        !str_contains($formHtml, 'data-confirm=') &&
        substr_count($formHtml, '<form') === 1,
        'simulated ON Ruminant DELETE form exclusively uses AJAX ownership'
    );

    foreach (['layer', 'broiler', 'ruminant'] as $type) {
        ws10_check(
            daily_record_revision_page_future_markers($type, -1) === '' &&
            count(ws10_script_urls(
                daily_record_revision_page_future_script_tags($type, -1)
            )) === 1,
            $type . ' nonpositive selected cycle never becomes a revision editor'
        );
    }
    ws10_check(
        hash_file('sha256', $originalGate) === $gateShaBefore,
        'original hard-OFF gate bytes unchanged throughout simulated ON'
    );
    if (($argv[1] ?? '') === '--json') {
        // Structured samples are consumed only by the network-disabled
        // fake-DOM Node VM. They come from actual PHP fragment execution.
        echo json_encode($rendered, JSON_THROW_ON_ERROR) . "\n";
        return;
    }
    echo "B12_WS10_ISOLATED_GATE_ON_RENDER=PASS\n";
    echo "PASS_COUNT={$passes}\n";
    echo "SIMULATION_GATE=ON_IN_TEMP_ONLY\n";
    echo "ORIGINAL_GATE=HARD_OFF_UNMODIFIED\n";
    echo "PHP_APP_BOOTSTRAP=NOT_RUN\n";
    echo "DATABASE_CONNECTIONS=0\n";
    echo "AUTHENTICATED_PAGE_RENDER=NOT_TESTED\n";
} catch (Throwable $error) {
    fwrite(STDERR, "B12_WS10_ISOLATED_GATE_ON_RENDER=FAIL " .
        $error->getMessage() . "\n");
    exit(1);
} finally {
    ws10_cleanup($fixtureDir);
}
