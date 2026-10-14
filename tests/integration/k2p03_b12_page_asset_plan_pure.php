<?php
declare(strict_types=1);
/**
 * Source-only B12 three-page asset plan regression.
 * No application bootstrap, database, migrations, network or page writes.
 */
$root = dirname(__DIR__, 2);
require_once $root . '/lib/daily_record_revision_page_asset_plan.php';

$pass = 0;
function b12_asset_check(bool $ok, string $name): void
{
    global $pass;
    if (!$ok) {
        throw new RuntimeException('FAIL: ' . $name);
    }
    ++$pass;
}

try {
    b12_asset_check(
        daily_record_revision_cutover_enabled() === false,
        'cutover stays hard OFF'
    );

    $expected = [
        'modal', 'transport', 'submit', 'editor', 'workspace',
        'page-lifecycle', 'page-fields', 'delete',
        'page-delete', 'page-adapters'
    ];
    $base = 'assets/js/daily-record-revision-';
    foreach ([
        'layer' => ['poultry/layers_daily_record.php', 'layers-daily-record.js', 'layerDailyConfig'],
        'broiler' => ['poultry/broiler_daily_record.php', 'broiler-daily-record.js', 'broilerDailyConfig'],
        'ruminant' => ['ruminant/ruminant_daily_record.php', 'ruminant-daily-record.js', 'ruminantDailyConfig'],
    ] as $type => [$page, $legacy, $config]) {
        $plan = daily_record_revision_page_asset_plan($type);
        b12_asset_check($plan['type'] === $type &&
            $plan['page'] === $page &&
            $plan['legacy_controller'] === 'assets/js/' . $legacy &&
            $plan['config_id'] === $config,
            $type . ' identifies its exact existing page/config/legacy owner');
        b12_asset_check(
            $plan['activation_state'] === 'NOT_IMPLEMENTED' &&
            daily_record_revision_page_candidate_assets_if_enabled($type) === [],
            $type . ' does not return active scripts while gate OFF'
        );
        $scripts = $plan['future_assets'];
        b12_asset_check(count($scripts) === count(array_unique($scripts)),
            $type . ' has no duplicate future script');
        $order = $expected;
        if ($type === 'ruminant') {
            array_splice($order, 8, 0, 'ruminant-delete');
        }
        $order[] = $type . '-page';
        $requiredScripts = array_map(
            static fn(string $name): string => $base . $name . '.js',
            $order
        );
        b12_asset_check(
            $scripts === $requiredScripts,
            $type . ' future browser assets follow explicit dependency order'
        );
        foreach ($scripts as $script) {
            b12_asset_check(
                is_file($root . '/' . $script),
                $type . ' referenced B12 asset exists: ' . $script
            );
        }
        b12_asset_check(
            in_array('[data-open-record-modal]', $plan['required_page_elements'], true) &&
            in_array('.add-record-btn', $plan['required_page_elements'], true) &&
            in_array('.edit-record-btn', $plan['required_page_elements'], true),
            $type . ' must preserve top add/calendar add/calendar edit handlers'
        );
        $html = file_get_contents($root . '/' . $page);
        b12_asset_check(is_string($html) &&
            str_contains($html, 'id="' . $config . '"') &&
            str_contains($html, 'data-open-record-modal') &&
            str_contains($html, 'class="btn btn-sm btn-outline-primary add-record-btn"') &&
            str_contains($html, 'class="btn btn-sm btn-outline-primary edit-record-btn"'),
            $type . ' legacy page still renders documented controls'
        );
        b12_asset_check(
            str_contains($html, "versioned_asset('/assets/js/" . $legacy . "')") &&
            !str_contains($html, 'daily-record-revision-page-adapters.js') &&
            !str_contains($html, 'daily-record-revision-page-delete.js') &&
            !str_contains($html, 'daily-record-revision-page-asset-plan.js') &&
            !str_contains($html, 'data-b12-revision-page='),
            $type . ' live page still uses legacy controller with no B12 page script'
        );
    }

    try {
        daily_record_revision_page_asset_plan('unsupported');
        throw new RuntimeException('FAIL: unknown species was accepted');
    } catch (InvalidArgumentException $expectedError) {
        ++$pass;
    }
    b12_asset_check(
        !str_contains((string)file_get_contents($root . '/lib/daily_record_revision_page_asset_plan.php'), 'echo ') &&
        !str_contains((string)file_get_contents($root . '/lib/daily_record_revision_page_asset_plan.php'), '<script'),
        'manifest cannot itself output any HTML script'
    );

    echo "B12_PAGE_ASSET_PLAN_PURE=PASS\nPASS_COUNT=$pass\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'B12_PAGE_ASSET_PLAN_PURE=FAIL ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
