<?php
declare(strict_types=1);

/**
 * B12 WS08B — one dormant, gate-conditional page rendering authority.
 *
 * No DB reads, no permission decisions, no side-effect SQL, and no bootstrap
 * when included. Every public renderer consults the literal hard-OFF B12
 * gate; a future separately authorized cutover must migrate all seven
 * writers, revision DDL, and exact AJAX clients together.
 *
 * The ALL-CYCLES (cycle=0) view gets only a dedicated read-only navigation
 * owner. It must not receive an editor, DELETE binder or legacy controller.
 * Real authenticated navigation still needs separate browser certification.
 */
require_once __DIR__ . '/daily_record_revision_page_asset_plan.php';

if (!function_exists('daily_record_revision_page_future_markers')) {
    function daily_record_revision_page_future_markers(string $type, int $cycleId): string
    {
        // Always validate the closed type, even under a disabled cutover.
        daily_record_revision_page_asset_plan($type);
        if (!daily_record_revision_cutover_enabled() || $cycleId <= 0) {
            return '';
        }
        return ' data-b12-revision-page="' . $type . '"'
            . ' data-b12-legacy-retired="1"'
            . ' data-b12-delete-ownership="replacement"';
    }
}

if (!function_exists('daily_record_revision_page_ruminant_delete_marker')) {
    function daily_record_revision_page_ruminant_delete_marker(): string
    {
        // The outer page template independently enforces DELETE permission
        // and CSRF; this is only opt-in browser form ownership metadata.
        return daily_record_revision_cutover_enabled()
            ? ' data-b12-revision-delete="ruminant"' : '';
    }
}

if (!function_exists('daily_record_revision_page_future_script_tags')) {
    function daily_record_revision_page_future_script_tags(
        string $type, int $cycleId
    ): string {
        $plan = daily_record_revision_page_asset_plan($type);
        if (!daily_record_revision_cutover_enabled()) {
            return '';
        }
        if (!defined('BASE_URL') || !function_exists('versioned_asset')) {
            throw new LogicException('B12 page asset authority is not available.');
        }
        if ($cycleId <= 0) {
            // The All Cycles view is navigable but NOT editable. It must
            // never silently revive a legacy writer or omit all navigation.
            $source = rtrim((string)BASE_URL, '/')
                . versioned_asset('/assets/js/daily-record-revision-readonly-nav.js');
            $url = htmlspecialchars($source, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            return '<script src="' . $url . '"></script>' . "\n"
                . '<script>(function(){'
                . 'const m=window.ReneeDailyRecordRevisionReadOnlyNav;'
                . 'if(!m||typeof m.create!=="function")'
                . '{throw new Error("B12 All Cycles navigation unavailable.");}'
                . 'm.create(' . json_encode($type, JSON_THROW_ON_ERROR)
                . ',{document:document,location:window.location}).bind();'
                . '})();</script>';
        }
        $scripts = [];
        foreach ($plan['future_assets'] as $asset) {
            if (!is_string($asset) ||
                !preg_match('~^assets/js/daily-record-revision-[a-z-]+\.js$~D', $asset)) {
                throw new LogicException('Unsafe B12 page asset path.');
            }
            $uri = rtrim((string)BASE_URL, '/')
                . versioned_asset('/' . $asset);
            $scripts[] = '<script src="' . htmlspecialchars(
                $uri, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'
            ) . '"></script>';
        }

        // The module is inert until the last, explicit one-time bind.
        // Strict client preflight rejects missing ownership markers, absent
        // CSRF/Bootstrap/confirmation APIs, partial markup and old listeners.
        // main.js continues to provide the existing appToday() date provider.
        $script = '(function(){'
            . 'const b=window.ReneeDailyRecordRevisionPageBootstrap;'
            . 'if(!b||typeof b.create!=="function"||typeof window.appToday!=="function")'
            . '{throw new Error("B12 page bootstrap unavailable.");}'
            . 'b.create(' . json_encode($type, JSON_THROW_ON_ERROR) . ',{'
            . 'document:document,'
            . 'fetchImpl:window.fetch.bind(window),'
            . 'FormDataImpl:window.FormData,'
            . 'URLSearchParamsImpl:window.URLSearchParams,'
            . 'currentDate:()=>window.appToday(),'
            . 'bootstrap:window.bootstrap,'
            . 'AppConfirm:window.AppConfirm,'
            . 'AppNotify:window.AppNotify,'
            . 'populationUI:window.DailyPopulationMovementUI,'
            . 'location:window.location'
            . '}).bind();'
            . '})();';
        $scripts[] = '<script>' . $script . '</script>';
        return implode("\n", $scripts);
    }
}
