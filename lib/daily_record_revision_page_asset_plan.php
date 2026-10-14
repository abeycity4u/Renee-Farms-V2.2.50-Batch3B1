<?php
declare(strict_types=1);

/**
 * C137 B12 prospective Daily Record page-asset manifest — SOURCE ONLY.
 *
 * This file is NOT included by any live page. Merely requiring it never
 * echoes HTML, adds scripts, binds events, changes permissions or queries DB.
 * Only a separately certified future page switch may consume the plan.
 *
 * The cutover gate is hard OFF. The guarded accessor returns an empty asset
 * list while OFF; it is not approval to activate the gate or render scripts.
 */
require_once __DIR__ . '/daily_record_revision_cutover_gate.php';

function daily_record_revision_page_asset_plan(string $type): array
{
    $pages = [
        'layer' => [
            'page' => 'poultry/layers_daily_record.php',
            'legacy_controller' => 'assets/js/layers-daily-record.js',
            'config_id' => 'layerDailyConfig',
            'entry' => 'daily-record-revision-layer-page.js',
        ],
        'broiler' => [
            'page' => 'poultry/broiler_daily_record.php',
            'legacy_controller' => 'assets/js/broiler-daily-record.js',
            'config_id' => 'broilerDailyConfig',
            'entry' => 'daily-record-revision-broiler-page.js',
        ],
        'ruminant' => [
            'page' => 'ruminant/ruminant_daily_record.php',
            'legacy_controller' => 'assets/js/ruminant-daily-record.js',
            'config_id' => 'ruminantDailyConfig',
            'entry' => 'daily-record-revision-ruminant-page.js',
        ],
    ];

    if (!isset($pages[$type])) {
        throw new InvalidArgumentException('Unsupported Daily Record page type.');
    }

    // Dependency order comes from direct global imports, not alphabetical.
    // No entry module auto-initializes. A separate future authorized
    // browser bootstrap and removal of legacy page listeners are required.
    $shared = [
        'daily-record-revision-modal.js',
        'daily-record-revision-transport.js',
        'daily-record-revision-submit.js',
        'daily-record-revision-editor.js',
        'daily-record-revision-workspace.js',
        'daily-record-revision-page-lifecycle.js',
        'daily-record-revision-page-fields.js',
        'daily-record-revision-delete.js',
    ];
    if ($type === 'ruminant') {
        $shared[] = 'daily-record-revision-ruminant-delete.js';
    }
    $shared[] = 'daily-record-revision-page-delete.js';
    $shared[] = 'daily-record-revision-page-adapters.js';
    $shared[] = $pages[$type]['entry'];

    return [
        'type' => $type,
        'page' => $pages[$type]['page'],
        'legacy_controller' => $pages[$type]['legacy_controller'],
        'config_id' => $pages[$type]['config_id'],
        'future_assets' => array_map(
            static fn (string $name): string => 'assets/js/' . $name,
            $shared
        ),
        'required_page_elements' => [
            '#recordModal', '#recordForm', '#modalTitle',
            '#selectedDate', '#recordDate', '#openingStock',
            '[name="save_record"]',
            '[data-open-record-modal]', '.add-record-btn',
            '.edit-record-btn',
        ],
        'required_config_fields' => [
            'selectedCycleId', 'canEditOpening',
        ],
        'exclusive_delete' => $type === 'ruminant'
            ? 'Remove all legacy DELETE data-confirm and use only the revision-marked AJAX forms.'
            : 'Retire the legacy global DELETE handler and preserve original-row revision tokens.',
        'activation_state' => 'NOT_IMPLEMENTED',
    ];
}

/**
 * Candidate assets for future integration. Returns [] under the hard-OFF
 * gate and is NEVER called by an active Daily Record page today.
 *
 * If a future authority changes the gate, this still only returns paths;
 * it does not print script tags, boot clients or bypass certification.
 */
function daily_record_revision_page_candidate_assets_if_enabled(string $type): array
{
    if (!daily_record_revision_cutover_enabled()) {
        return [];
    }
    return daily_record_revision_page_asset_plan($type)['future_assets'];
}
