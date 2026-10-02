<?php
/**
 * Platform Farms bridge for V2.3 plan-driven included seats and seat add-ons.
 *
 * This keeps management/farms.php stable while moving commercial seat policy into
 * centralized helpers. Standalone Sales is a commercial product, while livestock
 * products receive shared Sales capability without another priced Sales product.
 *
 * farm_role_limits remains the persisted effective runtime seat allowance.
 * Purchased extra seats are persisted separately by subscription_seat_policy.php.
 * Existing farms without durable add-on rows are read through the compatibility
 * fallback until their first save under the new model.
 *
 * Legacy farm_modules rows may still contain "sales" until that farm is saved.
 * Keep those rows readable for backwards compatibility, but never expose Sales as
 * a separately purchasable module in the commercial Platform Farms interface.
 */

$subscriptionPlanFarmPath = '/' . ltrim(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if (!($subscriptionPlanFarmPath === '/management/farms.php' || str_ends_with($subscriptionPlanFarmPath, '/management/farms.php'))) return;
if (!isset($_SESSION['user_id'])) return;

$subscriptionPlanFarmIsOwner = function_exists('isPlatformOwner') && isPlatformOwner();
if (!$subscriptionPlanFarmIsOwner) return;

$seatAddOnsForBrowser = function_exists('subscription_seat_normalize_addons')
    ? subscription_seat_normalize_addons([])
    : ['poultry_manager' => 0, 'ruminant_manager' => 0, 'sales_rep' => 0, 'viewer' => 0];

// On create/update, derive effective role limits from the selected plan plus only
// explicitly submitted non-negative seat add-ons. Do not trust browser-supplied
// role_limits totals. If an older/stale client omits seat_addons during an update,
// preserve the tenant's existing durable extras instead of silently resetting them.
// Standalone Sales is a commercial product. Livestock products receive shared
// Sales capability without requiring another priced Sales product.
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST'
    && (isset($_POST['create_farm']) || isset($_POST['update_farm']))) {
    $planCode = strtolower(trim((string)($_POST['plan'] ?? 'starter')));
    $submitted = is_array($_POST['modules'] ?? null) ? $_POST['modules'] : [];
    $commercialModules =
        function_exists('farm_entitlement_normalize_modules')
            ? farm_entitlement_normalize_modules(
                $submitted
            )
            : array_values(
                array_unique(
                    array_filter(
                        array_map(
                            static fn ($module): string =>
                                strtolower(
                                    trim(
                                        (string)$module
                                    )
                                ),
                            $submitted
                        ),
                        static fn (string $module): bool =>
                            in_array(
                                $module,
                                [
                                    'poultry',
                                    'ruminant',
                                    'sales',
                                ],
                                true
                            )
                    )
                )
            );

    $_POST['modules'] =
        $commercialModules;

    $submittedAddOns = is_array($_POST['seat_addons'] ?? null) ? $_POST['seat_addons'] : null;
    if ($submittedAddOns === null
        && isset($_POST['update_farm'])
        && function_exists('subscription_seat_load_addons')
        && isset($pdo) && $pdo instanceof PDO) {
        $existingFarmId = filter_var($_POST['farm_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
        if ($existingFarmId > 0) {
            $existingFarmStmt = $pdo->prepare("SELECT subscription_plan FROM farms WHERE id = ? AND slug <> 'owner' LIMIT 1");
            $existingFarmStmt->execute([$existingFarmId]);
            $existingPlan = $existingFarmStmt->fetchColumn();
            if ($existingPlan !== false) {
                $existingModules = function_exists('farm_entitlement_modules')
                    ? farm_entitlement_modules($pdo, $existingFarmId)
                    : [];
                $submittedAddOns = subscription_seat_load_addons(
                    $pdo,
                    $existingFarmId,
                    (string)$existingPlan,
                    $existingModules
                );
            }
        }
    }
    if (!is_array($submittedAddOns)) $submittedAddOns = [];

    if (function_exists('subscription_seat_normalize_addons')) {
        $seatAddOns = subscription_seat_normalize_addons($submittedAddOns);
    } else {
        $seatAddOns = [];
        foreach (['poultry_manager', 'ruminant_manager', 'sales_rep', 'viewer'] as $role) {
            $value = filter_var(
                $submittedAddOns[$role] ?? 0,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 0, 'max_range' => 500]]
            );
            $seatAddOns[$role] = $value === false ? 0 : (int)$value;
        }
    }
    $_POST['seat_addons'] = $seatAddOns;
    $seatAddOnsForBrowser = $seatAddOns;

    if (function_exists('subscription_plan_is_valid')
        && subscription_plan_is_valid($planCode)
        && function_exists('subscription_plan_effective_role_limits')) {
        $_POST['role_limits'] = subscription_plan_effective_role_limits($planCode, $commercialModules, $seatAddOns);
    }
} elseif (isset($_GET['edit']) && function_exists('subscription_seat_load_addons')) {
    $editFarmId = filter_var($_GET['edit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    if ($editFarmId > 0 && isset($pdo) && $pdo instanceof PDO) {
        $farmStmt = $pdo->prepare("SELECT id, subscription_plan FROM farms WHERE id = ? AND slug <> 'owner' LIMIT 1");
        $farmStmt->execute([$editFarmId]);
        $farmForSeats = $farmStmt->fetch(PDO::FETCH_ASSOC);
        if ($farmForSeats) {
            $modulesForSeats = function_exists('farm_entitlement_modules')
                ? farm_entitlement_modules($pdo, $editFarmId)
                : [];
            $seatAddOnsForBrowser = subscription_seat_load_addons(
                $pdo,
                $editFarmId,
                (string)($farmForSeats['subscription_plan'] ?? 'starter'),
                $modulesForSeats
            );
        }
    }
}

$planCatalogForBrowser = function_exists('subscription_plan_catalog') ? subscription_plan_catalog() : [];
$planCatalogJson = json_encode($planCatalogForBrowser, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$seatAddOnsJson = json_encode($seatAddOnsForBrowser, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

ob_start(static function (string $html) use ($planCatalogJson, $seatAddOnsJson): string {
    $html = str_replace(
        'Select at least one subscribed module (Poultry, Ruminant, or Sales) so the farm workspace has an active service entitlement.',
        'Select Poultry, Ruminant, or both so the farm workspace has an active service entitlement.',
        $html
    );

    $html = str_replace(
        'Disabling a module removes current operational access but preserves its historical farm records.',
        'Choose Poultry, Ruminant, or both. Sales access is included with any active livestock subscription. Disabling a livestock module removes current operational access but preserves its historical farm records and purchased extra-seat allowance.',
        $html
    );

    $html = str_replace(
        '<h3 class="h6 mb-1">User limits by role</h3><p class="form-text mt-0">Platform limit for how many login accounts this farm may create under each specialist role. Farm Admin is one protected account. Disabled modules force their specialist limit to 0 when saved.</p><div class="row g-2">',
        '<h3 class="h6 mb-1">Included Team Members &amp; Extra Seats</h3><p class="form-text mt-0">The selected plan supplies the included seats. Purchased extra seats are preserved across plan changes. A downgrade or seat reduction is blocked if assigned users would exceed the new total. Farm Admin is one protected account included separately.</p><div class="row g-2" id="planIncludedSeats">',
        $html
    );

    $html = str_replace('>Poultry users</label>', '>Poultry Manager</label>', $html);
    $html = str_replace('>Ruminant users</label>', '>Ruminant Manager</label>', $html);
    $html = str_replace('>Sales users</label>', '>Sales Rep</label>', $html);
    $html = str_replace('>Viewer users</label>', '>Viewer</label>', $html);

    $catalogConfig = htmlspecialchars(
        $planCatalogJson ?: '{}',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $seatAddOnsConfig = htmlspecialchars(
        $seatAddOnsJson ?: '{}',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $asset = BASE_URL . '/assets/js/subscription-plan-farms.js';

    if (function_exists('versioned_asset')) {
        $asset = BASE_URL . versioned_asset(
            '/assets/js/subscription-plan-farms.js'
        );
    }

    $script = '<div id="subscriptionPlanSeatUiConfig" hidden'
        . ' data-plan-catalog="' . $catalogConfig . '"'
        . ' data-seat-addons="' . $seatAddOnsConfig . '"'
        . '></div>'
        . '<script src="'
        . htmlspecialchars(
            $asset,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        )
        . '"></script>';

    if (stripos($html, '</body>') !== false) {
        $html = preg_replace('/<\\/body>/i', $script . '</body>', $html, 1) ?? $html;
    }
    return $html;
});
