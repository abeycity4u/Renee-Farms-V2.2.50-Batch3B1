<?php
$root = dirname(__DIR__);
$failures = 0;
$checks = 0;

$check = function (bool $ok, string $label) use (&$failures, &$checks): void {
    $checks++;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
};

$behaviors = file_get_contents($root . '/assets/js/app-behaviors.js');
$registry = file_get_contents($root . '/ruminant/animal_registry.php');

$check(
    str_contains($behaviors, "[data-ruminant-animal-new]"),
    'Shared behavior recognizes new ruminant animal contract'
);

$check(
    str_contains($behaviors, 'window.newAnimal();'),
    'Shared behavior delegates new animal action to page-owned function'
);

$check(
    str_contains($behaviors, "[data-ruminant-animal-edit]"),
    'Shared behavior recognizes edit ruminant animal contract'
);

$check(
    str_contains($behaviors, 'dataset.ruminantAnimal'),
    'Shared behavior reads structured ruminant animal payload'
);

$check(
    str_contains($behaviors, 'window.editAnimal(animalData);'),
    'Shared behavior delegates edit action to page-owned function'
);

$check(
    str_contains($behaviors, "[data-ruminant-animal-exit]"),
    'Shared behavior recognizes ruminant animal exit contract'
);

$check(
    str_contains($behaviors, 'dataset.ruminantAnimalExit'),
    'Shared behavior reads structured ruminant exit payload'
);

$check(
    str_contains($behaviors, 'window.exitAnimal(exitData);'),
    'Shared behavior delegates exit action to page-owned function'
);

$check(
    !preg_match('/onclick=.*(?:newAnimal|editAnimal|exitAnimal)/', $registry),
    'Ruminant Animal Registry no longer uses inline animal action handlers'
);

$check(
    substr_count($registry, 'data-ruminant-animal-new') === 1,
    'Ruminant Animal Registry has exactly one new-animal contract'
);

$check(
    substr_count($registry, 'data-ruminant-animal-edit') === 1,
    'Ruminant Animal Registry has exactly one edit-animal contract'
);

$check(
    substr_count($registry, 'data-ruminant-animal=') === 1,
    'Ruminant Animal Registry has exactly one structured edit payload'
);

$check(
    substr_count($registry, 'data-ruminant-animal-exit=') === 1,
    'Ruminant Animal Registry has exactly one structured exit payload'
);

$check(
    str_contains(
        $registry,
        'app_attr(json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE))'
    ),
    'Ruminant edit payload is safely HTML-escaped'
);

$check(
    str_contains(
        $registry,
        'app_attr(json_encode(["id" => (int)$a["id"], "tag_no" => $a["tag_no"]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE))'
    ),
    'Ruminant exit payload is safely HTML-escaped'
);

$check(
    str_contains($registry, 'function newAnimal()'),
    'Ruminant Animal Registry retains page-owned newAnimal implementation'
);

$check(
    str_contains($registry, 'function editAnimal(a)'),
    'Ruminant Animal Registry retains page-owned editAnimal implementation'
);

$check(
    str_contains($registry, 'function exitAnimal(a)'),
    'Ruminant Animal Registry retains page-owned exitAnimal implementation'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;
exit($failures === 0 ? 0 : 1);
