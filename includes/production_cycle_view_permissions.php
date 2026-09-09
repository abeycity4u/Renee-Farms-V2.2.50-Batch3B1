<?php
/**
 * Keep delegated Production Cycles access genuinely read-only.
 *
 * The legacy Production Cycles page already blocks every POST for non-admins.
 * Poultry Cycle Workspace historically allowed a delegated viewer to submit a
 * Production-Entry Economic Basis approval, so enforce the same admin-only
 * mutation boundary here and remove management controls from delegated views.
 */

if (!function_exists('production_cycle_view_permissions_path')) {
function production_cycle_view_permissions_path(): string
{
    return '/' . ltrim(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/');
}
}

if (!function_exists('production_cycle_view_permissions_privileged')) {
function production_cycle_view_permissions_privileged(): bool
{
    return isPlatformOwner() || hasRole('farm_admin');
}
}

if (!function_exists('production_cycle_view_permissions_filter')) {
function production_cycle_view_permissions_filter(string $html): string
{
    // This helper only buffers the two Production Cycle routes for a delegated
    // non-admin viewer, so hiding POST forms here is safely route-scoped.
    $styleAsset = BASE_URL . '/assets/css/prepaint-production-cycle-readonly.css';
    if (function_exists('versioned_asset')) {
        $styleAsset = BASE_URL . versioned_asset('/assets/css/prepaint-production-cycle-readonly.css');
    }
    $style = '<link rel="stylesheet" href="'
        . htmlspecialchars(
            $styleAsset,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        )
        . '">';
    $asset = BASE_URL . '/assets/js/production-cycle-view-permissions.js';

    if (function_exists('versioned_asset')) {
        $asset = BASE_URL . versioned_asset(
            '/assets/js/production-cycle-view-permissions.js'
        );
    }

    $script = '<script src="'
        . htmlspecialchars(
            $asset,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        )
        . '"></script>';
    if (stripos($html, '</head>') !== false) $html = preg_replace('/<\/head>/i', $style . '</head>', $html, 1) ?? $html;
    if (stripos($html, '</body>') !== false) $html = preg_replace('/<\/body>/i', $script . '</body>', $html, 1) ?? $html;
    return $html;
}
}

if (!isset($_SESSION['user_id'])) return;
$path = production_cycle_view_permissions_path();
$isProductionCycles = str_ends_with($path, '/management/production_cycles.php');
$isPoultryWorkspace = str_ends_with($path, '/management/poultry_cycle.php');
if (!$isProductionCycles && !$isPoultryWorkspace) return;
if (production_cycle_view_permissions_privileged()) return;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    http_response_code(403);
    exit('Production-cycle management access required.');
}

ob_start('production_cycle_view_permissions_filter');
