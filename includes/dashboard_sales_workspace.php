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
$canAddSale = $canViewSales && hasPermission(getUserType(), 'sales_add');
$canViewReceivables = $canViewSales && hasPermission(getUserType(), 'sales_receivables');
$canRecordPayment = $canViewReceivables && hasPermission(getUserType(), 'sales_payment');
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
    $canAddSale,
    $canViewReceivables,
    $canRecordPayment,
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
        // Inventory owns these hero figures and dashboard panels. Remove the
        // panels, but keep the Bootstrap left column itself because it becomes the
        // Sales workspace column for a dedicated Sales Representative.
        $html = dashboard_sales_remove_div_containing($html, '<span>Items in Stock</span>', 'col-');
        $html = dashboard_sales_remove_div_containing($html, '<span>Today\'s Activities</span>', 'col-');
        $html = dashboard_sales_remove_div_containing($html, '<span>Inventory Coverage</span>', 'col-');
        $html = dashboard_sales_remove_card_containing($html, 'Smart Stock Control');
        $html = dashboard_sales_remove_card_containing($html, 'Today\'s Transactions');
        $html = dashboard_sales_remove_card_containing($html, 'Critical Inventory Snapshot');
        $html = dashboard_sales_remove_card_containing($html, 'Low Stock Alerts');
    }

    if (!$canViewSales) {
        $html = dashboard_sales_remove_card_containing($html, 'Recent Sales');
        return $html;
    }

    $salesUrl = htmlspecialchars(rtrim(BASE_URL, '/') . '/management/sales_records.php', ENT_QUOTES, 'UTF-8');
    $addSaleUrl = htmlspecialchars(rtrim(BASE_URL, '/') . '/management/sales_records.php?dashboard_action=add_sale', ENT_QUOTES, 'UTF-8');
    $receivablesUrl = htmlspecialchars(rtrim(BASE_URL, '/') . '/management/sales_records.php#customer-debt-management', ENT_QUOTES, 'UTF-8');
    $recordPaymentUrl = htmlspecialchars(rtrim(BASE_URL, '/') . '/management/sales_records.php?dashboard_action=record_payment#customer-debt-management', ENT_QUOTES, 'UTF-8');

    $actions = '<a class="btn btn-outline-success btn-sm" href="' . $salesUrl . '"><i class="bi bi-graph-up-arrow me-1"></i> Sales Report</a>';
    if ($canAddSale) {
        $actions .= '<a class="btn btn-success btn-sm" href="' . $addSaleUrl . '"><i class="bi bi-plus-circle me-1"></i> Add Sale</a>';
    }
    if ($canViewReceivables) {
        $actions .= '<a class="btn btn-outline-primary btn-sm" href="' . $receivablesUrl . '"><i class="bi bi-wallet2 me-1"></i> Customer Debt Management</a>';
    }
    if ($canRecordPayment) {
        $actions .= '<a class="btn btn-primary btn-sm" href="' . $recordPaymentUrl . '"><i class="bi bi-cash-coin me-1"></i> Record Payment</a>';
    }

    $workspace = <<<HTML
                <div class="card dashboard-card ops-card mb-4" id="salesRepresentativeWorkspace">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <span class="section-eyebrow">Sales workspace</span>
                            <h5 class="mb-0"><i class="bi bi-cart-check text-success me-1"></i> Sales &amp; Customer Accounts</h5>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">Record and review farm produce sales and use only the customer-account actions granted to this role.</p>
                        <div class="d-flex gap-2 flex-wrap">{$actions}</div>
                    </div>
                </div>

HTML;

    // Place Sales into the existing left 8-column dashboard region. This preserves
    // the legacy 8/4 grid even when Inventory is disabled, so Recent Sales remains
    // a balanced right-side panel instead of the dashboard collapsing.
    $html = preg_replace(
        '~(\s*<!-- Left Column: Stock & Quick Actions -->\s*<div class="col-xl-8">)~',
        '$1' . "\n" . $workspace,
        $html,
        1
    ) ?? $html;

    return $html;
});
