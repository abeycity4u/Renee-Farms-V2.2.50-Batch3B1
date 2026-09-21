<?php

declare(strict_types=1);

/**
 * V3.0.1 — Daily Record population type configuration verifier.
 *
 * Source-only.
 * No database connection.
 * No database writes.
 */

$root = dirname(__DIR__);
$path = $root . '/lib/daily_population_continuity.php';
$source = is_file($path) ? (string)file_get_contents($path) : '';

$checks = 0;
$failures = 0;

$check = static function (string $label, bool $ok) use (&$checks, &$failures): void {
    $checks++;
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
};

$check(
    'Daily population continuity source is readable',
    $source !== ''
);

$types = [];
if ($source !== '') {
    require_once $path;
    $types = daily_population_continuity_types();
}

$check(
    'Layer type has canonical poultry/layer identity',
    isset($types['layer'])
    && ($types['layer']['farm_type'] ?? null) === 'poultry'
    && ($types['layer']['production_type'] ?? null) === 'layer'
);

$check(
    'Broiler type has canonical poultry/broiler identity',
    isset($types['broiler'])
    && ($types['broiler']['farm_type'] ?? null) === 'poultry'
    && ($types['broiler']['production_type'] ?? null) === 'broiler'
);

$check(
    'Ruminant type keeps canonical ruminant identity',
    isset($types['ruminant'])
    && ($types['ruminant']['farm_type'] ?? null) === 'ruminant'
    && array_key_exists('production_type', $types['ruminant'])
    && $types['ruminant']['production_type'] === null
);

$layerMatch = [];
$broilerMatch = [];

$layerMatched = preg_match(
    "/'layer'\s*=>\s*\[(.*?)\],\s*'broiler'/s",
    $source,
    $layerMatch
) === 1;

$broilerMatched = preg_match(
    "/'broiler'\s*=>\s*\[(.*?)\],\s*'ruminant'/s",
    $source,
    $broilerMatch
) === 1;

$check(
    'Layer type map contains one production_type assignment',
    $layerMatched
    && substr_count($layerMatch[1], "'production_type'") === 1
);

$check(
    'Broiler type map contains one production_type assignment',
    $broilerMatched
    && substr_count($broilerMatch[1], "'production_type'") === 1
);

$check(
    'Layer and Broiler both use poultry farm type exactly once',
    $layerMatched
    && $broilerMatched
    && substr_count($layerMatch[1], "'farm_type'") === 1
    && substr_count($broilerMatch[1], "'farm_type'") === 1
);

$check(
    'Shared current-stock reader still uses canonical type configuration',
    str_contains($source, "\$config['farm_type']")
    && str_contains($source, "\$config['production_type']")
    && str_contains($source, 'production_population_state(')
);

echo PHP_EOL . 'CHECK_COUNT=' . $checks . PHP_EOL;
echo 'FAILED_COUNT=' . $failures . PHP_EOL;
echo 'DATABASE_CONNECTION_USED=NO' . PHP_EOL;
echo 'DATABASE_WRITE_PERFORMED=NO' . PHP_EOL;
echo 'RESULT=' . ($failures === 0 ? 'PASS' : 'FAIL') . PHP_EOL;

exit($failures === 0 ? 0 : 1);
