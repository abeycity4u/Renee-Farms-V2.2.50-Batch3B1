<?php
/**
 * Dashboard overview visibility bridge.
 *
 * The dashboard is still a large legacy page whose livestock summary blocks are
 * rendered from farm scope. Until those blocks are migrated to page-local
 * permission checks, filter the completed dashboard response on the server so
 * users only receive livestock overview sections allowed by both subscription
 * entitlement and delegated permission. Platform Owner retains its owner-workspace
 * bypass; Farm Admin is still constrained by the tenant's subscribed modules.
 */

require_once __DIR__ . '/functions.php';

if (!function_exists('dashboard_overview_remove_div_containing')) {
function dashboard_overview_remove_div_containing(string $html, string $needle, string $requiredClass): string
{
    $needlePos = strpos($html, $needle);
    if ($needlePos === false) return $html;

    $searchPos = $needlePos;
    $start = false;
    while ($searchPos > 0) {
        $candidate = strrpos(substr($html, 0, $searchPos), '<div');
        if ($candidate === false) break;
        $tagEnd = strpos($html, '>', $candidate);
        if ($tagEnd === false || $tagEnd >= $needlePos) break;
        $tag = substr($html, $candidate, $tagEnd - $candidate + 1);
        if (str_contains($tag, $requiredClass)) {
            $start = $candidate;
            break;
        }
        $searchPos = $candidate;
    }
    if ($start === false) return $html;

    if (!preg_match_all('~<div\b|</div>~i', substr($html, $start), $matches, PREG_OFFSET_CAPTURE)) return $html;
    $depth = 0;
    foreach ($matches[0] as [$token, $offset]) {
        if (stripos($token, '<div') === 0) {
            $depth++;
            continue;
        }
        $depth--;
        if ($depth === 0) {
            $end = $start + $offset + strlen($token);
            return substr($html, 0, $start) . substr($html, $end);
        }
    }

    return $html;
}
}

if (!function_exists('dashboard_overview_filter_ticker')) {
function dashboard_overview_filter_ticker(string $html, bool $allowPoultry, bool $allowRuminant): string
{
    if (!$allowPoultry && !$allowRuminant) {
        return preg_replace(
            '~\s*<!-- Dashboard Statistics -->.*?(?=\s*<!-- (?:Management Intelligence(?: hidden by permission)?|Main Content Area) -->)~s',
            "\n        <!-- Dashboard Statistics hidden by overview permission -->\n        ",
            $html,
            1
        ) ?? $html;
    }

    $poultryMarker = 'Poultry Active Cycle Stock</span>';
    $ruminantMarker = 'Ruminant Active Cycle Stock</span>';

    if (!$allowPoultry) {
        $poultryText = strpos($html, $poultryMarker);
        $ruminantText = strpos($html, $ruminantMarker);
        if ($poultryText !== false && $ruminantText !== false && $poultryText < $ruminantText) {
            $start = strrpos(substr($html, 0, $poultryText), '<span class="livestock-ticker-title">');
            $end = strrpos(substr($html, 0, $ruminantText), '<span class="livestock-ticker-title">');
            if ($start !== false && $end !== false && $end > $start) {
                $html = substr($html, 0, $start) . substr($html, $end);
            }
        }
    }

    if (!$allowRuminant) {
        $ruminantText = strpos($html, $ruminantMarker);
        if ($ruminantText !== false) {
            $titleStart = strrpos(substr($html, 0, $ruminantText), '<span class="livestock-ticker-title">');
            if ($titleStart !== false) {
                $separator = strrpos(substr($html, 0, $titleStart), '<span class="livestock-pill">•</span>');
                $start = $separator !== false ? $separator : $titleStart;
                $end = strpos($html, '</div>', $ruminantText);
                if ($end !== false && $end > $start) {
                    $html = substr($html, 0, $start) . substr($html, $end);
                }
            }
        }
    }

    return $html;
}
}

if (!function_exists('dashboard_overview_filter_operational_surfaces')) {
function dashboard_overview_filter_operational_surfaces(string $html, bool $allowPoultry, bool $allowRuminant): string
{
    // Livestock Overview governs production overview surfaces only. Inventory
    // rows and stock filters are governed separately by Inventory View and by
    // the user's actual livestock role/farm scope in dashboard.php.
    if (!$allowPoultry) {
        $html = preg_replace('~\s*<a href="poultry/(?:layers_daily_record|broiler_daily_record)\.php" class="smart-action-card[^>]*>.*?</a>~s', '', $html) ?? $html;
    }

    if (!$allowRuminant) {
        $html = preg_replace('~\s*<a href="ruminant/ruminant_daily_record\.php" class="smart-action-card[^>]*>.*?</a>~s', '', $html) ?? $html;
    }

    return $html;
}
}

if (!isset($_SESSION['user_id'])) return;

$overviewPath = '/' . ltrim(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if (!($overviewPath === '/dashboard.php' || str_ends_with($overviewPath, '/dashboard.php'))) return;
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') return;

if (isPlatformOwner()) return;

$canViewPoultryOverview = user_can_access_entitled_module('poultry')
    && (hasRole('farm_admin') || hasPermission(getUserType(), 'poultry_overview'));
$canViewRuminantOverview = user_can_access_entitled_module('ruminant')
    && (hasRole('farm_admin') || hasPermission(getUserType(), 'ruminant_overview'));

ob_start(static function (string $html) use ($canViewPoultryOverview, $canViewRuminantOverview): string {
    $html = dashboard_overview_filter_ticker($html, $canViewPoultryOverview, $canViewRuminantOverview);
    $html = dashboard_overview_filter_operational_surfaces($html, $canViewPoultryOverview, $canViewRuminantOverview);

    if (!$canViewPoultryOverview) {
        $html = dashboard_overview_remove_div_containing($html, '<strong>Layers</strong>', 'production-tile');
        $html = dashboard_overview_remove_div_containing($html, '<strong>Broilers</strong>', 'production-tile');
    }
    if (!$canViewRuminantOverview) {
        $html = dashboard_overview_remove_div_containing($html, '<strong>Ruminant</strong>', 'production-tile');
    }

    if (!$canViewPoultryOverview && !$canViewRuminantOverview) {
        $html = preg_replace(
            '~\s*<!-- Latest Production Summary -->.*?(?=\s*</div>\s*</div>\s*<!-- Quick Stock Update Modal -->)~s',
            "\n                <!-- Latest Production hidden by overview permission -->\n                ",
            $html,
            1
        ) ?? $html;
    }

    return $html;
});
