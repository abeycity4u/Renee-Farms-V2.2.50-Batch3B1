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
$reports = file_get_contents($root . '/management/reports.php');
$sales = file_get_contents($root . '/management/sales_records.php');
$users = file_get_contents($root . '/management/users.php');
$animalView = file_get_contents($root . '/ruminant/animal_view.php');

$check(
    str_contains($behaviors, "[data-report-export-excel]"),
    'Shared behavior recognizes report Excel export contract'
);

$check(
    str_contains($behaviors, 'window.exportToExcel();'),
    'Shared behavior delegates report Excel export'
);

$check(
    str_contains($behaviors, "[data-sale-delete-id]"),
    'Shared behavior recognizes sale delete contract'
);

$check(
    str_contains($behaviors, 'dataset.saleDeleteId'),
    'Shared behavior reads sale delete id'
);

$check(
    str_contains($behaviors, 'window.deleteSale(saleId);'),
    'Shared behavior delegates sale deletion'
);

$check(
    str_contains($behaviors, "[data-user-delete]"),
    'Shared behavior recognizes user delete contract'
);

$check(
    str_contains($behaviors, 'dataset.username'),
    'Shared behavior reads username'
);

$check(
    str_contains($behaviors, 'window.confirmUserDeletion(form, username);'),
    'Shared behavior delegates user deletion confirmation'
);

$check(
    str_contains($behaviors, "[data-membership-close-id]"),
    'Shared behavior recognizes membership close contract'
);

$check(
    str_contains($behaviors, 'dataset.membershipCloseId'),
    'Shared behavior reads membership id'
);

$check(
    str_contains($behaviors, 'dataset.cycleCode'),
    'Shared behavior reads cycle code'
);

$check(
    str_contains($behaviors, 'window.closeMembership(membershipId, cycleCode);'),
    'Shared behavior delegates membership close action'
);

$check(
    !str_contains($reports, 'onclick="exportToExcel()"'),
    'Reports no longer uses inline Excel export handler'
);

$check(
    str_contains($reports, 'data-report-export-excel'),
    'Reports uses centralized Excel export contract'
);

$check(
    str_contains($reports, 'function exportToExcel()'),
    'Reports retains page-owned exportToExcel implementation'
);

$check(
    !preg_match('/onclick\s*=\s*"deleteSale\(/', $sales),
    'Sales Records no longer uses inline deleteSale handler'
);

$check(
    str_contains($sales, 'data-sale-delete-id='),
    'Sales Records uses centralized sale delete contract'
);

$check(
    str_contains($sales, 'function deleteSale(saleId)'),
    'Sales Records retains page-owned deleteSale implementation'
);

$check(
    !preg_match('/onclick\s*=\s*"confirmUserDeletion\(/', $users),
    'Users no longer uses inline delete confirmation handler'
);

$check(
    str_contains($users, 'data-user-delete'),
    'Users uses centralized delete contract'
);

$check(
    str_contains($users, 'data-username="<?php echo app_attr($user[\'username\']); ?>"'),
    'Users safely HTML-escapes username data attribute'
);

$check(
    !str_contains($users, 'data-username="<?php echo app_attr($user[\'username\']); ?>""'),
    'Users delete data attribute has no duplicate closing quote'
);

$check(
    str_contains($users, 'function confirmUserDeletion(form, username)'),
    'Users retains page-owned deletion confirmation'
);

$check(
    !preg_match('/onclick\s*=\s*"closeMembership\(/', $animalView),
    'Animal View no longer uses inline membership close handler'
);

$check(
    str_contains($animalView, 'data-membership-close-id='),
    'Animal View uses centralized membership close contract'
);

$check(
    str_contains($animalView, 'data-cycle-code="<?php echo app_attr($m[\'cycle_code\']); ?>"'),
    'Animal View safely HTML-escapes cycle code'
);

$check(
    str_contains($animalView, 'function closeMembership(id,cycle)'),
    'Animal View retains page-owned closeMembership implementation'
);

/*
 * Global audited PHP inline-event-handler contract.
 * Ignore Git metadata, vendor code, and HTML comments.
 */
$handlerPattern = '/\s(onclick|onchange|oninput|onblur|onfocus|onsubmit|onerror|onload|onkeydown|onkeyup)\s*=/i';
$remaining = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    $relative = ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);

    if (
        str_contains($relative, '.git' . DIRECTORY_SEPARATOR) ||
        str_contains($relative, 'vendor' . DIRECTORY_SEPARATOR)
    ) {
        continue;
    }

    $text = file_get_contents($path);
    $active = preg_replace('/<!--.*?-->/s', '', $text);

    if (preg_match($handlerPattern, $active)) {
        $remaining[] = $relative;
    }
}

$check(
    count($remaining) === 0,
    'Audited PHP surfaces contain zero active inline event handlers'
);

if ($remaining) {
    echo 'Remaining handler files:' . PHP_EOL;
    foreach ($remaining as $file) {
        echo ' - ' . $file . PHP_EOL;
    }
}

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;
exit($failures === 0 ? 0 : 1);
