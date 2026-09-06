<?php
/**
 * Dedicated Sales Representative dashboard workspace bridge.
 *
 * The legacy dashboard is livestock-first. A pure Sales Representative should
 * receive a useful Sales workspace without inheriting Poultry/Ruminant overview
 * data or unrelated management figures. Keep this as presentation filtering;
 * destination pages/APIs remain the authorization boundary.
 */

require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id'])) return;

$path = '/' . ltrim(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if (!($path === '/dashboard.php' || str_ends_with($path, '/dashboard.php'))) return;
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') return;

// Only shape the dashboard for a dedicated Sales Representative. Multi-role
// specialists retain the normal dashboard assembled for their other roles.
if (!hasRole('sales_rep') || hasRole('farm_admin') || hasRole('poultry_manager') || hasRole('ruminant_manager')) return;

$canViewSales = user_can_access_entitled_module('sales') && hasPermission(getUserType(), 'sales');
$canViewReceivables = $canViewSales && hasPermission(getUserType(), 'sales_receivables');
$canViewInventory = hasPermission(getUserType(), 'inventory');
$canViewExpenses = hasPermission(getUserType(), 'expenses');
$canViewProfitability = hasPermission(getUserType(), 'profitability');
$canViewIntelligence = hasPermission(getUserType(), 'farm_intelligence');

if (!function_exists('dashboard_sales_remove_div_containing')) {
function dashboard_sales_remove_div_containing(string $html, string $needle, string $requiredClass): string
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

    if (!preg_match_all('~<div\\b|</div>~i', substr($html, $start), $matches, PREG_OFFSET_CAPTURE)) return $html;
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

if (!function_exists('dashboard_sales_remove_card_containing')) {
function dashboard_sales_remove_card_containing(string $html, string $needle): string
{
    return dashboard_sales_remove_div_containing($html, $needle, 'card dashboard-card');
}
}

ob_start(static function (string $html) use (
    $canViewSales,
    $canViewReceivables,
    $canViewInventory,
    $canViewExpenses,
    $canViewProfitability,
    $canViewIntelligence
): string {
    // The hero metrics are legacy farm-wide figures. Keep each only when its
    // corresponding explicit View permission is granted.
    if (!$canViewExpenses) {
        $html = dashboard_sales_remove_div_containing($html, '<span>Total Operating Cost</span>', 'col-');
    }
    if (!$canViewProfitability) {
        $html = dashboard_sales_remove_div_containing($html, '<span>Net Profit (This Month)</span>', 'col-');
    }
    if (!$canViewIntelligence) {
        $html = preg_replace(
            '~\\s*<!-- Management Intelligence -->.*?(?=\\s*<!-- Main Content Area -->)~s',
            "\n        <!-- Management Intelligence hidden by permission -->\n        ",
            $html,
            1
        ) ?? $html;
    }

    if (!$canViewInventory) {
        // Inventory owns these hero figures.
        $html = dashboard_sales_remove_div_containing($html, '<span>Items in Stock</span>', 'col-');
        $html = dashboard_sales_remove_div_containing($html, '<span>Today\'s Activities</span>', 'col-');
        $html = dashboard_sales_remove_div_containing($html, '<span>Inventory Coverage</span>', 'col-');

        // The legacy left dashboard column is inventory-first. Remove the whole
        // column rather than deleting its cards individually and leaving a large
        // empty Bootstrap grid column behind.
        $html = dashboard_sales_remove_div_containing($html, 'Smart Stock Control', 'col-xl-8');

        // Safety-net any inventory cards that live in the right column.
        $html = dashboard_sales_remove_card_containing($html, 'Today\'s Transactions');
        $html = dashboard_sales_remove_card_containing($html, 'Critical Inventory Snapshot');
        $html = dashboard_sales_remove_card_containing($html, 'Low Stock Alerts');

        // With the inventory column gone, let the remaining Sales content use the
        // full dashboard width instead of staying pinned to a narrow side rail.
        $html = preg_replace('~<div class="col-xl-4">~', '<div class="col-12">', $html, 1) ?? $html;
    }

    if (!$canViewSales) {
        $html = dashboard_sales_remove_card_containing($html, 'Recent Sales');
    }

    // Give a dedicated Sales Representative a stable, useful dashboard entry
    // point even when there are no recent sales rows yet. Do not duplicate sales
    // calculations here; the canonical Sales/Receivables page owns those figures.
    if ($canViewSales) {
        $salesUrl = htmlspecialchars(rtrim(BASE_URL, '/') . '/management/sales_records.php', ENT_QUOTES, 'UTF-8');
        $receivableAction = $canViewReceivables
            ? '<a class="btn btn-outline-light btn-sm" href="' . $salesUrl . '"><i class="bi bi-wallet2 me-1"></i> Receivables</a>'
            : '';
        $receivableCopy = $canViewReceivables ? ', customer balances and debt payments.' : '.';

        $workspace = <<<HTML
        <div class="card dashboard-card mb-4" id="salesRepresentativeWorkspace">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <span class="section-eyebrow">Sales workspace</span>
                    <h5 class="mb-1"><i class="bi bi-cart-check text-success me-1"></i> Sales &amp; Customer Accounts</h5>
                    <div class="text-muted small">Record and review farm produce sales{$receivableCopy}</div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a class="btn btn-success btn-sm" href="{$salesUrl}"><i class="bi bi-graph-up-arrow me-1"></i> Open Sales</a>
                    {$receivableAction}
                </div>
            </div>
        </div>

HTML;

        $html = preg_replace(
            '~(\\s*<!-- Dashboard Statistics -->)~',
            $workspace . '$1',
            $html,
            1
        ) ?? $html;
    }

    return $html;
});
