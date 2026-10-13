<?php
declare(strict_types=1);

/**
 * B12 shared form-token presentation. Source cutover preparation only.
 *
 * All six inputs intentionally start EMPTY (pending), not CREATE. The
 * authorized exact-row/scope reader and shared modal controller must supply
 * genuine client-loaded identity before a future revision-aware submit.
 *
 * Including this helper in existing forms does not turn on B12 writes,
 * change a permission, connect to a DB, or load any JavaScript. Live save
 * handlers remain legacy until the separately approved coordinated cutover.
 */
require_once __DIR__ . '/daily_record_revision_authority.php';

if (!function_exists('daily_record_revision_pending_form_controls')) {
    function daily_record_revision_pending_form_controls(string $type): string
    {
        // The same closed type authority is used by all eventual writers.
        daily_record_revision_definition($type);
        $names = [
            'write_intent', 'record_id', 'expected_revision',
            'original_cycle_id', 'original_record_date', 'original_animal_type',
        ];
        $html = [];
        foreach ($names as $name) {
            // Names are fixed code literals, never obtained from request data.
            $html[] = '<input type="hidden" name="' . $name . '" value="">';
        }
        return implode("\n", $html);
    }
}
