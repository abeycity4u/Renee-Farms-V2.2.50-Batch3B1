<?php
/**
 * Platform Owner read-only commercial subscription history.
 *
 * Adds the canonical subscriptions audit trail to the dedicated selected-tenant
 * support view. This never changes session farm identity and exposes no mutation
 * controls or billing-provider actions.
 */

$platformSubscriptionHistoryPath = '/' . ltrim(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if (!($platformSubscriptionHistoryPath === '/management/platform_tenant_view.php'
    || str_ends_with($platformSubscriptionHistoryPath, '/management/platform_tenant_view.php'))) return;
if (!isset($_SESSION['user_id'])) return;
if (!function_exists('isPlatformOwner') || !isPlatformOwner()) return;
if (!isset($pdo) || !($pdo instanceof PDO)) return;

require_once __DIR__ . '/platform_owner_tenant_view.php';

$subscriptionHistoryTenant = platform_owner_tenant_from_request($pdo, 'farm_id');
$subscriptionHistoryTenantId = $subscriptionHistoryTenant ? (int)$subscriptionHistoryTenant['id'] : 0;
$subscriptionHistoryRows = [];
$subscriptionHistoryUsers = [];
$subscriptionHistoryReady = function_exists('subscription_record_table_ready')
    && subscription_record_table_ready($pdo);

if ($subscriptionHistoryTenantId > 0 && $subscriptionHistoryReady) {
    $subscriptionHistoryRows = function_exists('subscription_record_history')
        ? subscription_record_history($pdo, $subscriptionHistoryTenantId, 30)
        : [];

    $userIds = [];
    foreach ($subscriptionHistoryRows as $row) {
        $userId = (int)($row['recorded_by_user_id'] ?? 0);
        if ($userId > 0) $userIds[$userId] = $userId;
    }

    if ($userIds) {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $pdo->prepare(
            "SELECT id, username, full_name
             FROM users
             WHERE id IN ({$placeholders})"
        );
        $stmt->execute(array_values($userIds));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $user) {
            $subscriptionHistoryUsers[(int)$user['id']] = $user;
        }
    }
}

$subscriptionHistoryReasonLabel = static function ($reason): string {
    $reason = strtolower(trim((string)$reason));
    $labels = [
        'foundation_backfill' => 'Commercial baseline',
        'tenant_created' => 'Tenant created',
        'platform_owner_update' => 'Subscription updated',
        'platform_owner_suspend' => 'Farm suspended',
        'platform_owner_reactivate' => 'Farm reactivated',
    ];
    if (isset($labels[$reason])) return $labels[$reason];
    if ($reason === '') return 'Subscription snapshot';
    return ucwords(str_replace(['_', '-', '.'], ' ', $reason));
};

$subscriptionHistoryModules = static function ($json): array {
    $decoded = json_decode((string)$json, true);
    if (!is_array($decoded)) return [];
    $modules = array_values(array_intersect(['poultry', 'ruminant'], array_map('strtolower', $decoded)));
    sort($modules, SORT_STRING);
    return $modules;
};

$subscriptionHistoryExtras = static function ($json): array {
    $decoded = json_decode((string)$json, true);
    if (!is_array($decoded)) return [];
    $labels = [
        'poultry_manager' => 'Poultry',
        'ruminant_manager' => 'Ruminant',
        'sales_rep' => 'Sales',
        'viewer' => 'Viewer',
    ];
    $extras = [];
    foreach ($labels as $role => $label) {
        $count = max(0, (int)($decoded[$role] ?? 0));
        if ($count > 0) $extras[] = $label . ' +' . $count;
    }
    return $extras;
};

$subscriptionHistoryStatusClass = static function ($status): string {
    return match (strtolower((string)$status)) {
        'active' => 'success',
        'trial' => 'info',
        'past_due' => 'warning',
        'suspended' => 'danger',
        'cancelled' => 'secondary',
        default => 'light',
    };
};

$subscriptionHistorySection = '';
if ($subscriptionHistoryTenantId > 0) {
    ob_start();
    ?>
    <div id="platformTenantSubscriptionHistory" class="card tenant-view-card mt-3">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <div class="fw-semibold"><i class="bi bi-clock-history"></i> Subscription History</div>
                <div class="tenant-view-meta">Auditable commercial snapshots for this tenant.</div>
            </div>
            <span class="badge text-bg-light border">Read only · latest 30 changes</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm tenant-view-table mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Change</th>
                        <th>Plan</th>
                        <th>Status</th>
                        <th>Modules</th>
                        <th>Extra Seats</th>
                        <th>Recorded By</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$subscriptionHistoryReady): ?>
                    <tr><td colspan="7" class="text-center text-muted py-3">Commercial subscription history storage is not installed.</td></tr>
                <?php elseif (!$subscriptionHistoryRows): ?>
                    <tr><td colspan="7" class="text-center text-muted py-3">No commercial subscription history has been recorded for this tenant.</td></tr>
                <?php endif; ?>
                <?php foreach ($subscriptionHistoryRows as $row):
                    $modules = $subscriptionHistoryModules($row['modules_snapshot'] ?? '[]');
                    $extras = $subscriptionHistoryExtras($row['seat_addons_snapshot'] ?? '{}');
                    $recordedById = (int)($row['recorded_by_user_id'] ?? 0);
                    $recordedBy = $subscriptionHistoryUsers[$recordedById] ?? null;
                    $recordedByLabel = 'System';
                    if ($recordedBy) {
                        $recordedByLabel = trim((string)($recordedBy['full_name'] ?? ''));
                        if ($recordedByLabel === '') $recordedByLabel = (string)($recordedBy['username'] ?? ('User #' . $recordedById));
                    } elseif ($recordedById > 0) {
                        $recordedByLabel = 'User #' . $recordedById;
                    }
                    $status = strtolower((string)($row['status'] ?? ''));
                ?>
                    <tr>
                        <td class="text-nowrap"><?php echo htmlspecialchars((string)($row['created_at'] ?? '—')); ?></td>
                        <td class="fw-semibold"><?php echo htmlspecialchars($subscriptionHistoryReasonLabel($row['change_reason'] ?? '')); ?></td>
                        <td class="text-capitalize"><?php echo htmlspecialchars((string)($row['plan_code'] ?? '—')); ?></td>
                        <td><span class="badge text-bg-<?php echo htmlspecialchars($subscriptionHistoryStatusClass($status)); ?> text-capitalize"><?php echo htmlspecialchars(str_replace('_', ' ', $status ?: 'unknown')); ?></span></td>
                        <td>
                            <?php if (!$modules): ?><span class="text-muted">None</span><?php endif; ?>
                            <?php foreach ($modules as $module): ?><span class="badge text-bg-primary text-capitalize me-1"><?php echo htmlspecialchars($module); ?></span><?php endforeach; ?>
                        </td>
                        <td><?php echo htmlspecialchars($extras ? implode(' · ', $extras) : 'None'); ?></td>
                        <td><?php echo htmlspecialchars($recordedByLabel); ?><?php if ($recordedById > 0): ?><div class="tenant-view-meta">User #<?php echo $recordedById; ?></div><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    $subscriptionHistorySection = (string)ob_get_clean();
}

ob_start(static function (string $html) use ($subscriptionHistoryTenantId, $subscriptionHistorySection): string {
    if ($subscriptionHistoryTenantId < 1
        || $subscriptionHistorySection === ''
        || stripos($html, 'id="platformTenantSubscriptionHistory"') !== false) {
        return $html;
    }

    $needle = '<div class="alert alert-secondary mt-3 mb-0">';
    $position = strpos($html, $needle);
    if ($position === false) return $html;

    return substr($html, 0, $position) . $subscriptionHistorySection . substr($html, $position);
});
