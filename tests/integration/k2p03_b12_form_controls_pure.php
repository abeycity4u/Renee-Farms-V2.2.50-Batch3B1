<?php
declare(strict_types=1);

/** B12 offline form-token HTML verifier; no runtime/app/DB/CI. */
require_once dirname(__DIR__, 2) . '/lib/daily_record_revision_form_controls.php';

$checks = 0;
function check_form(bool $ok, string $name): void
{
    global $checks;
    if (!$ok) throw new RuntimeException('B12_FORM_FAIL: ' . $name);
    ++$checks;
}
$expected = [
    'write_intent', 'record_id', 'expected_revision',
    'original_cycle_id', 'original_record_date', 'original_animal_type',
];
foreach (['layer', 'broiler', 'ruminant'] as $type) {
    $html = daily_record_revision_pending_form_controls($type);
    $matched = preg_match_all('/<input type="hidden" name="([a-z_]+)" value="">/', $html, $m);
    check_form($matched === 6, $type . ' has six blank hidden inputs');
    check_form($m[1] === $expected, $type . ' has exact shared token names');
    check_form(!str_contains($html, 'name="write_intent" value="create"'), $type . ' never preauthorizes CREATE');
    check_form(substr_count($html, 'value=""') === 6, $type . ' all pending tokens blank');
}
try {
    daily_record_revision_pending_form_controls('table_from_request');
    throw new RuntimeException('Unsupported record type was accepted');
} catch (InvalidArgumentException $expectedError) {
    check_form(true, 'closed record type gate');
}
echo "B12_FORM_CONTROLS_PURE=PASS\nPASS_COUNT={$checks}\n";
