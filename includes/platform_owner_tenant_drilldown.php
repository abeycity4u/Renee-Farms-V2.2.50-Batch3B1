<?php
/**
 * Platform Owner tenant-view read-only drill-down.
 *
 * This bridge extends management/platform_tenant_view.php without changing the
 * Platform Owner's session farm identity or exposing tenant mutation controls.
 * Batch 1 adds tenant-scoped Production Cycles and Team Users detail only.
 */

$platformTenantDrilldownPath = '/' . ltrim(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if (!($platformTenantDrilldownPath === '/management/platform_tenant_view.php'
    || str_ends_with($platformTenantDrilldownPath, '/management/platform_tenant_view.php'))) return;
if (!isset($_SESSION['user_id'])) return;
if (!function_exists('isPlatformOwner') || !isPlatformOwner()) return;
if (!isset($pdo) || !($pdo instanceof PDO)) return;

require_once __DIR__ . '/platform_owner_tenant_view.php';

$drilldownTenant = platform_owner_tenant_from_request($pdo, 'farm_id');
$drilldownTenantId = $drilldownTenant ? (int)$drilldownTenant['id'] : 0;
$drilldownModules = $drilldownTenantId > 0 ? platform_owner_tenant_modules($pdo, $drilldownTenantId) : [];
$drilldownLivestockModules = array_values(array_intersect(['poultry', 'ruminant'], $drilldownModules));
$drilldownCycles = [];
$drilldownUsers = [];

if ($drilldownTenantId > 0) {
    $stmt = $pdo->prepare(
        "SELECT id, cycle_code, farm_type, production_type, status, start_date,
                expected_end_date, opening_headcount
         FROM production_cycles
         WHERE farm_id = ?
         ORDER BY start_date DESC, id DESC
         LIMIT 12"
    );
    $stmt->execute([$drilldownTenantId]);
    $drilldownCycles = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->prepare(
        "SELECT u.id, u.full_name, u.username, u.user_type,
                GROUP_CONCAT(DISTINCT r.code ORDER BY r.code SEPARATOR ', ') AS role_codes
         FROM users u
         LEFT JOIN user_roles ur ON ur.user_id = u.id
         LEFT JOIN roles r ON r.id = ur.role_id
         WHERE u.farm_id = ?
         GROUP BY u.id, u.full_name, u.username, u.user_type
         ORDER BY CASE WHEN u.user_type = 'farm_admin' THEN 0 ELSE 1 END,
                  u.full_name, u.id
         LIMIT 20"
    );
    $stmt->execute([$drilldownTenantId]);
    $drilldownUsers = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$drilldownRoleLabel = static function (string $role): string {
    return [
        'farm_admin' => 'Farm Admin',
        'poultry_manager' => 'Poultry Manager',
        'ruminant_manager' => 'Ruminant Manager',
        'sales_rep' => 'Sales Representative',
        'viewer' => 'Viewer',
    ][$role] ?? ucwords(str_replace('_', ' ', $role));
};

ob_start(static function (string $html) use (
    $drilldownTenantId,
    $drilldownLivestockModules,
    $drilldownCycles,
    $drilldownUsers,
    $drilldownRoleLabel
): string {
    if ($drilldownTenantId < 1 || stripos($html, 'id="platformTenantDrilldown"') !== false) {
        return $html;
    }

    ob_start();
    ?>
    <div id="platformTenantDrilldown" class="row g-3 mt-1">
        <div class="col-xl-7">
            <div class="card tenant-view-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="fw-semibold"><i class="bi bi-arrow-repeat"></i> Production Cycles</div>
                    <span class="badge text-bg-light border">Read only · latest 12</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm tenant-view-table mb-0">
                        <thead><tr><th>Cycle</th><th>Type</th><th>Status</th><th>Start</th><th>Opening</th></tr></thead>
                        <tbody>
                        <?php if (!$drilldownCycles): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">No production cycles.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($drilldownCycles as $cycle):
                            $farmType = strtolower((string)($cycle['farm_type'] ?? ''));
                            $currentlyEntitled = in_array($farmType, $drilldownLivestockModules, true);
                        ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars((string)$cycle['cycle_code']); ?></div>
                                    <?php if (in_array($farmType, ['poultry', 'ruminant'], true) && !$currentlyEntitled): ?>
                                        <span class="badge text-bg-secondary">Historical · module inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-capitalize"><?php echo htmlspecialchars($farmType); ?> · <?php echo htmlspecialchars((string)$cycle['production_type']); ?></td>
                                <td><span class="badge text-bg-<?php echo $cycle['status'] === 'active' ? 'success' : 'secondary'; ?> text-capitalize"><?php echo htmlspecialchars((string)$cycle['status']); ?></span></td>
                                <td><?php echo htmlspecialchars((string)$cycle['start_date']); ?></td>
                                <td><?php echo number_format((int)$cycle['opening_headcount']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card tenant-view-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="fw-semibold"><i class="bi bi-people"></i> Team Users</div>
                    <span class="badge text-bg-light border">Read only · latest 20</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm tenant-view-table mb-0">
                        <thead><tr><th>User</th><th>Role</th></tr></thead>
                        <tbody>
                        <?php if (!$drilldownUsers): ?>
                            <tr><td colspan="2" class="text-center text-muted py-3">No tenant users.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($drilldownUsers as $user):
                            $roles = array_values(array_filter(array_map('trim', explode(',', (string)($user['role_codes'] ?? '')))));
                            if (!$roles) $roles = [(string)$user['user_type']];
                        ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars((string)$user['full_name']); ?></div>
                                    <div class="tenant-view-meta">@<?php echo htmlspecialchars((string)$user['username']); ?></div>
                                </td>
                                <td>
                                    <?php foreach ($roles as $role): ?>
                                        <span class="badge text-bg-light border me-1 mb-1"><?php echo htmlspecialchars($drilldownRoleLabel($role)); ?></span>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php
    $section = (string)ob_get_clean();

    $needle = '<div class="alert alert-secondary mt-3 mb-0">';
    $position = strpos($html, $needle);
    if ($position === false) {
        return $html;
    }

    return substr($html, 0, $position) . $section . substr($html, $position);
});
