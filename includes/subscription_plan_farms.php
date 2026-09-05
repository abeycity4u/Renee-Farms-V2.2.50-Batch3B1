<?php
/**
 * Platform Farms bridge for V2.3 plan-driven included seats.
 *
 * This keeps management/farms.php stable while moving commercial seat policy into
 * the centralized subscription plan catalog. Poultry/Ruminant remain selectable
 * operational modules; basic Sales is a shared capability and is not sold as a
 * separate module in the Platform Farms form.
 */

$subscriptionPlanFarmPath = '/' . ltrim(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if (!($subscriptionPlanFarmPath === '/management/farms.php' || str_ends_with($subscriptionPlanFarmPath, '/management/farms.php'))) return;
if (!isset($_SESSION['user_id'])) return;

$subscriptionPlanFarmIsOwner = function_exists('isPlatformOwner') && isPlatformOwner();
if (!$subscriptionPlanFarmIsOwner) return;

// On create/update, the selected plan owns the included seat allowance. Do not
// trust editable role-limit fields from the browser. Sales is shared with any
// active livestock subscription, so only Poultry/Ruminant are persisted as the
// commercial module selection from this form.
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST'
    && (isset($_POST['create_farm']) || isset($_POST['update_farm']))) {
    $planCode = strtolower(trim((string)($_POST['plan'] ?? 'starter')));
    $submitted = is_array($_POST['modules'] ?? null) ? $_POST['modules'] : [];
    $livestockModules = array_values(array_intersect(
        ['poultry', 'ruminant'],
        array_map(static fn($module) => strtolower(trim((string)$module)), $submitted)
    ));
    $_POST['modules'] = $livestockModules;

    if (function_exists('subscription_plan_is_valid')
        && subscription_plan_is_valid($planCode)
        && function_exists('subscription_plan_included_role_limits')) {
        $_POST['role_limits'] = subscription_plan_included_role_limits($planCode, $livestockModules);
    }
}

$planCatalogForBrowser = function_exists('subscription_plan_catalog') ? subscription_plan_catalog() : [];
$planCatalogJson = json_encode($planCatalogForBrowser, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

ob_start(static function (string $html) use ($planCatalogJson): string {
    // Sales is now a shared core capability. Remove only its commercial module
    // checkbox; operational Sales visibility continues through entitlement logic.
    $html = preg_replace(
        '~<div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="modules\[\]" value="sales"[^>]*><label class="form-check-label">Sales</label></div>~i',
        '',
        $html,
        1
    ) ?? $html;

    $html = str_replace(
        'Disabling a module removes current operational access but preserves its historical farm records.',
        'Choose Poultry, Ruminant, or both. Basic Sales is included with any active livestock subscription. Disabling a livestock module removes current operational access but preserves its historical farm records.',
        $html
    );

    $html = str_replace(
        '<h3 class="h6 mb-1">User limits by role</h3><p class="form-text mt-0">Platform limit for how many login accounts this farm may create under each specialist role. Farm Admin is one protected account. Disabled modules force their specialist limit to 0 when saved.</p><div class="row g-2">',
        '<h3 class="h6 mb-1">Included Team Members</h3><p class="form-text mt-0">Seat allowances come from the selected plan. Only roles relevant to the subscribed modules are shown. Farm Admin is one protected account included separately.</p><div class="row g-2" id="planIncludedSeats">',
        $html
    );

    $html = str_replace('>Poultry users</label>', '>Poultry Manager</label>', $html);
    $html = str_replace('>Ruminant users</label>', '>Ruminant Manager</label>', $html);
    $html = str_replace('>Sales users</label>', '>Sales Rep</label>', $html);
    $html = str_replace('>Viewer users</label>', '>Viewer</label>', $html);

    $script = '<script id="subscription-plan-seat-ui">'
        . 'document.addEventListener("DOMContentLoaded",function(){'
        . 'const form=document.getElementById("farmAccountForm");if(!form)return;'
        . 'const catalog=' . ($planCatalogJson ?: '{}') . ';'
        . 'const plan=form.querySelector("select[name=plan]");'
        . 'const poultry=form.querySelector("input[name=\"modules[]\"][value=poultry]");'
        . 'const ruminant=form.querySelector("input[name=\"modules[]\"][value=ruminant]");'
        . 'const roles={poultry_manager:poultry,ruminant_manager:ruminant,sales_rep:null,viewer:null};'
        . 'function refresh(){'
        . 'const definition=catalog[(plan&&plan.value)||"starter"]||{};'
        . 'const limits=definition.included_role_limits||{};'
        . 'const hasLivestock=!!((poultry&&poultry.checked)||(ruminant&&ruminant.checked));'
        . 'Object.keys(roles).forEach(function(role){'
        . 'const input=form.querySelector("input[name=\"role_limits["+role+"]\"]");if(!input)return;'
        . 'const moduleBox=roles[role];const relevant=moduleBox?moduleBox.checked:hasLivestock;'
        . 'input.value=relevant?(parseInt(limits[role]||0,10)):0;input.readOnly=true;input.setAttribute("aria-readonly","true");'
        . 'const col=input.closest(".col-sm-6");if(col)col.style.display=relevant?"":"none";'
        . '});'
        . '}'
        . '[plan,poultry,ruminant].forEach(function(el){if(el)el.addEventListener("change",refresh);});refresh();'
        . '});</script>';

    if (stripos($html, '</body>') !== false) {
        $html = preg_replace('/<\/body>/i', $script . '</body>', $html, 1) ?? $html;
    }
    return $html;
});
