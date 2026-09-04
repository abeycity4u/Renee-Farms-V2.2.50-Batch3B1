<?php
/**
 * Dashboard overview visibility bridge.
 *
 * The dashboard is still a large legacy page whose livestock summary blocks are
 * rendered from farm scope. Until those blocks are migrated to page-local
 * permission checks, filter the completed dashboard response on the server so
 * delegated users only receive livestock overview sections they were granted.
 * Farm Admin and Platform Owner retain their normal bypass.
 */

require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id'])) return;

$overviewPath = '/' . ltrim(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if (!($overviewPath === '/dashboard.php' || str_ends_with($overviewPath, '/dashboard.php'))) return;
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') return;

$overviewPrivileged = isPlatformOwner() || hasRole('farm_admin');
$canViewPoultryOverview = $overviewPrivileged || hasPermission(getUserType(), 'poultry_overview');
$canViewRuminantOverview = $overviewPrivileged || hasPermission(getUserType(), 'ruminant_overview');
$canViewAnyLivestockOverview = $canViewPoultryOverview || $canViewRuminantOverview;

if ($canViewAnyLivestockOverview) return;

ob_start(static function (string $html): string {
    // Remove the active-cycle livestock ticker between stable dashboard comments.
    $html = preg_replace(
        '~\s*<!-- Dashboard Statistics -->.*?(?=\s*<!-- Management Intelligence -->)~s',
        "\n        <!-- Dashboard Statistics hidden by overview permission -->\n        ",
        $html,
        1
    ) ?? $html;

    // Remove the Latest Production card while preserving the right-column and
    // main-row closing wrappers immediately before the stock-update modal.
    $html = preg_replace(
        '~\s*<!-- Latest Production Summary -->.*?(?=\s*</div>\s*</div>\s*<!-- Quick Stock Update Modal -->)~s',
        "\n                <!-- Latest Production hidden by overview permission -->\n                ",
        $html,
        1
    ) ?? $html;

    return $html;
});
