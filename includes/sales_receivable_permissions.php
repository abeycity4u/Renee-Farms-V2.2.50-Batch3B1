<?php
/**
 * V2.3 compatibility bridge for Customer Debt Management actions and visibility.
 *
 * The legacy Sales Records page still carries older role checks around debt
 * mutations and does not render CSRF tokens itself. Keep this bridge small and
 * explicit: hide the debt workspace when Sales Receivables View is OFF, inject
 * CSRF tokens into Sales-page POST forms, and enforce CSRF plus the exact
 * receivables/action permission before delegated debt mutations proceed.
 *
 * Platform Owner/Farm Admin retain their intended permission bypass, but their
 * debt mutations are still protected by CSRF.
 */

$receivablePath = '/' . ltrim(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$isSalesRecordsPage = $receivablePath === '/management/sales_records.php'
    || str_ends_with($receivablePath, '/management/sales_records.php');

if (!$isSalesRecordsPage) {
    return;
}

$receivableMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$receivablePrivileged = isPlatformOwner() || hasRole('farm_admin');

if ($receivableMethod === 'GET') {
    $canViewReceivables = $receivablePrivileged || hasPermission(getUserType(), 'sales_receivables');
    if (!$canViewReceivables) {
        // Do not allow an unauthorized customer query-string selection to drive
        // customer-specific ledger branches later in the legacy Sales page.
        $_GET['customer'] = '';
    }

    $csrfMarkup = '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8')
        . '">';

    ob_start(static function (string $html) use ($canViewReceivables, $csrfMarkup): string {
        // Add a CSRF token to each Sales-page POST form. This is server-rendered,
        // so protection does not depend on JavaScript being available.
        $html = preg_replace_callback(
            '#<form\b([^>]*)method\s*=\s*(["\'])POST\2([^>]*)>#i',
            static function (array $match) use ($csrfMarkup): string {
                return $match[0] . $csrfMarkup;
            },
            $html
        ) ?? $html;

        if ($canViewReceivables) {
            return $html;
        }

        $salesTableMarker = '<!-- Sales Table -->';
        $debtHeading = 'Customer Debt Management';
        $cardStartNeedle = '<div class="card border-secondary mb-4">';

        $headingPos = strpos($html, $debtHeading);
        $salesTablePos = strpos($html, $salesTableMarker);
        if ($headingPos !== false && $salesTablePos !== false && $headingPos < $salesTablePos) {
            $cardStart = strrpos(substr($html, 0, $headingPos), $cardStartNeedle);
            if ($cardStart !== false) {
                $html = substr($html, 0, $cardStart) . substr($html, $salesTablePos);
            }
        }

        // If the database feature probe rendered its legacy fallback instead of
        // the debt card, hide that fallback from users who simply lack permission.
        $html = preg_replace(
            '#<div class="alert alert-warning">\s*Debt management tables are not available yet\.\s*Run migrations to enable customer credit tracking\.\s*</div>#i',
            '',
            $html
        ) ?? $html;

        return $html;
    });

    return;
}

if ($receivableMethod !== 'POST') {
    return;
}

$receivableAction = null;
if (isset($_POST['record_payment'])) {
    $receivableAction = 'payment';
} elseif (isset($_POST['update_ledger_entry'])) {
    $receivableAction = 'edit';
} elseif (isset($_POST['delete_ledger_entry'])) {
    $receivableAction = 'delete';
}
if ($receivableAction === null) {
    return;
}

require_valid_csrf_post();

if (!$receivablePrivileged) {
    if (!hasPermission(getUserType(), 'sales_receivables')) {
        http_response_code(403);
        exit('Sales receivables view access required.');
    }

    $requiredPermission = match ($receivableAction) {
        'payment' => 'sales_payment',
        'edit' => 'sales_receivables_edit',
        'delete' => 'sales_receivables_delete',
    };
    if (!hasPermission(getUserType(), $requiredPermission)) {
        http_response_code(403);
        exit('You do not have permission to perform this receivables action.');
    }
}

// Payment recording already has correct tenant/customer/sale scoping in the
// legacy Sales page. After authorization + CSRF, let that proven branch execute.
if ($receivableAction === 'payment' || $receivablePrivileged) {
    return;
}

$tenantFarmId = requireCurrentFarmId();
$reportMode = (string)($_GET['report_mode'] ?? 'monthly');
$month = (string)($_GET['month'] ?? date('Y-m'));
$year = (string)($_GET['year'] ?? date('Y'));
$farmType = (string)($_GET['farm_type'] ?? 'all');
$selectedCustomer = trim((string)($_GET['customer'] ?? ''));

$redirect = static function (string $customer) use ($reportMode, $month, $year, $farmType): void {
    header('Location: sales_records.php?' . http_build_query([
        'report_mode' => $reportMode,
        'month' => $month,
        'year' => $year,
        'farm_type' => $farmType,
        'customer' => $customer,
    ]));
    exit();
};

if ($receivableAction === 'edit') {
    $ledgerId = (int)($_POST['ledger_id'] ?? 0);
    $customerName = trim((string)($_POST['ledger_customer_name'] ?? ''));
    $entryDate = (string)($_POST['ledger_entry_date'] ?? date('Y-m-d'));
    $amount = (float)($_POST['ledger_amount'] ?? 0);
    $notes = trim((string)($_POST['ledger_notes'] ?? ''));

    if ($ledgerId <= 0 || $customerName === '' || $amount == 0.0) {
        $_SESSION['error'] = 'Ledger update requires valid customer, amount, and entry id.';
        $redirect($selectedCustomer);
    }

    $existsStmt = $pdo->prepare('SELECT customer_name FROM customer_ledger_entries WHERE id = ? AND farm_id = ? LIMIT 1');
    $existsStmt->execute([$ledgerId, $tenantFarmId]);
    if ($existsStmt->fetchColumn() === false) {
        $_SESSION['error'] = 'Ledger entry not found.';
        $redirect($selectedCustomer);
    }

    $stmt = $pdo->prepare('UPDATE customer_ledger_entries
        SET customer_name = ?, entry_date = ?, amount = ?, notes = ?
        WHERE id = ? AND farm_id = ?');
    $stmt->execute([$customerName, $entryDate, $amount, $notes, $ledgerId, $tenantFarmId]);

    $_SESSION['success'] = 'Ledger entry updated successfully.';
    $redirect($customerName);
}

$ledgerId = (int)($_POST['ledger_id'] ?? 0);
if ($ledgerId <= 0) {
    $_SESSION['error'] = 'Invalid ledger entry selected.';
    $redirect($selectedCustomer);
}

$getCustomerStmt = $pdo->prepare('SELECT customer_name FROM customer_ledger_entries WHERE id = ? AND farm_id = ?');
$getCustomerStmt->execute([$ledgerId, $tenantFarmId]);
$existingCustomer = $getCustomerStmt->fetchColumn();
if ($existingCustomer === false) {
    $_SESSION['error'] = 'Ledger entry not found.';
    $redirect($selectedCustomer);
}
$customerName = (string)$existingCustomer;

$deleteStmt = $pdo->prepare('DELETE FROM customer_ledger_entries WHERE id = ? AND farm_id = ?');
$deleteStmt->execute([$ledgerId, $tenantFarmId]);
if ($deleteStmt->rowCount() !== 1) {
    $_SESSION['error'] = 'Ledger entry not found.';
    $redirect($selectedCustomer);
}

$_SESSION['success'] = 'Ledger entry deleted successfully.';
$redirect($customerName);
