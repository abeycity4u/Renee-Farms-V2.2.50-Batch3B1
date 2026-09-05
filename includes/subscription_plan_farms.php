<?php
/**
 * Platform Farms bridge for V2.3 plan-driven included seats and seat add-ons.
 *
 * This keeps management/farms.php stable while moving commercial seat policy into
 * the centralized subscription plan catalog. Poultry/Ruminant remain selectable
 * operational modules; basic Sales is a shared capability and is not sold as a
 * separate module in the Platform Farms form.
 *
 * farm_role_limits remains the persisted effective seat allowance. Add-ons are
 * derived as effective limit minus the selected plan's included allowance, so this
 * batch needs no new database table or migration.
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

// On create/update, derive effective role limits from the selected plan plus only
// explicitly submitted non-negative seat add-ons. Do not trust browser-supplied
// role_limits totals. Sales is shared with any active livestock subscription, so
// only Poultry/Ruminant are persisted as commercial module selections here. Saving
// an older farm therefore retires any legacy standalone Sales entitlement row.
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST'
    && (isset($_POST['create_farm']) || isset($_POST['update_farm']))) {
    $planCode = strtolower(trim((string)($_POST['plan'] ?? 'starter')));
    $submitted = is_array($_POST['modules'] ?? null) ? $_POST['modules'] : [];
    $livestockModules = array_values(array_intersect(
        ['poultry', 'ruminant'],
        array_map(static fn($module) => strtolower(trim((string)$module)), $submitted)
    ));
    $_POST['modules'] = $livestockModules;

    $seatAddOns = [];
    $submittedAddOns = is_array($_POST['seat_addons'] ?? null) ? $_POST['seat_addons'] : [];
    foreach (['poultry_manager', 'ruminant_manager', 'sales_rep', 'viewer'] as $role) {
        $value = filter_var(
            $submittedAddOns[$role] ?? 0,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0, 'max_range' => 500]]
        );
        $seatAddOns[$role] = $value === false ? 0 : (int)$value;
    }

    if (function_exists('subscription_plan_is_valid')
        && subscription_plan_is_valid($planCode)
        && function_exists('subscription_plan_effective_role_limits')) {
        $_POST['role_limits'] = subscription_plan_effective_role_limits($planCode, $livestockModules, $seatAddOns);
    }
}

$planCatalogForBrowser = function_exists('subscription_plan_catalog') ? subscription_plan_catalog() : [];
$planCatalogJson = json_encode($planCatalogForBrowser, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

ob_start(static function (string $html) use ($planCatalogJson): string {
    // Sales is now a shared core capability. Remove only its commercial module
    // checkbox; operational Sales visibility continues through entitlement logic.
    $html = preg_replace(
        '~<div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="modules\\[\\]" value="sales"[^>]*><label class="form-check-label">Sales</label></div>~i',
        '',
        $html,
        1
    ) ?? $html;

    $html = str_replace(
        'Select at least one subscribed module (Poultry, Ruminant, or Sales) so the farm workspace has an active service entitlement.',
        'Select Poultry, Ruminant, or both so the farm workspace has an active service entitlement.',
        $html
    );

    // Existing tenants can still carry a legacy stored "sales" row. Keep the DB
    // compatible, but present only commercial livestock modules in the farm list.
    $html = preg_replace_callback(
        '~<td>((?:poultry|ruminant|sales)(?:, (?:poultry|ruminant|sales))*)</td>~i',
        static function (array $match): string {
            $modules = array_values(array_filter(
                array_map('strtolower', explode(', ', $match[1])),
                static fn(string $module): bool => in_array($module, ['poultry', 'ruminant'], true)
            ));
            $label = $modules ? implode(', ', array_map('ucfirst', $modules)) : 'None';
            return '<td>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</td>';
        },
        $html
    ) ?? $html;

    $html = str_replace(
        'Disabling a module removes current operational access but preserves its historical farm records.',
        'Choose Poultry, Ruminant, or both. Sales access is included with any active livestock subscription. Disabling a livestock module removes current operational access but preserves its historical farm records.',
        $html
    );

    $html = str_replace(
        '<h3 class="h6 mb-1">User limits by role</h3><p class="form-text mt-0">Platform limit for how many login accounts this farm may create under each specialist role. Farm Admin is one protected account. Disabled modules force their specialist limit to 0 when saved.</p><div class="row g-2">',
        '<h3 class="h6 mb-1">Included Team Members &amp; Extra Seats</h3><p class="form-text mt-0">The selected plan supplies the included seats. Add extra paid seats only where needed. Only roles relevant to the subscribed modules are shown. Farm Admin is one protected account included separately.</p><div class="row g-2" id="planIncludedSeats">',
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
        . 'const originalTotals={};const extras={};const includedDisplays={};const totalDisplays={};'
        . 'Object.keys(roles).forEach(function(role){'
        . 'const total=form.querySelector("input[name=\"role_limits["+role+"]\"]");if(!total)return;'
        . 'originalTotals[role]=parseInt(total.value||0,10)||0;'
        . 'total.readOnly=true;total.setAttribute("aria-readonly","true");total.setAttribute("tabindex","-1");total.style.display="none";'
        . 'const ownLabel=total.previousElementSibling;if(ownLabel&&ownLabel.tagName==="LABEL")ownLabel.textContent=(ownLabel.textContent||"").replace(/ \(.*\)$/g,"");'
        . 'const summary=document.createElement("div");summary.className="border rounded p-2 mb-2 bg-body-tertiary";'
        . 'const includedLine=document.createElement("div");includedLine.className="small";includedLine.innerHTML="Included seats: <strong>0</strong>";'
        . 'const totalLine=document.createElement("div");totalLine.className="small mt-1";totalLine.innerHTML="Total seats: <strong>0</strong>";'
        . 'summary.appendChild(includedLine);summary.appendChild(totalLine);total.insertAdjacentElement("afterend",summary);'
        . 'includedDisplays[role]=includedLine.querySelector("strong");totalDisplays[role]=totalLine.querySelector("strong");'
        . 'const wrap=document.createElement("div");wrap.className="mt-2";'
        . 'const label=document.createElement("label");label.className="form-label small mb-1";label.textContent="Extra seats";'
        . 'const addon=document.createElement("input");addon.className="form-control form-control-sm";addon.type="number";addon.min="0";addon.max="500";addon.name="seat_addons["+role+"]";addon.value="0";addon.setAttribute("data-seat-addon",role);'
        . 'wrap.appendChild(label);wrap.appendChild(addon);summary.insertAdjacentElement("afterend",wrap);extras[role]=addon;'
        . '});'
        . 'let initialized=false;'
        . 'function refresh(preserveExtras){'
        . 'const definition=catalog[(plan&&plan.value)||"starter"]||{};const limits=definition.included_role_limits||{};'
        . 'const hasLivestock=!!((poultry&&poultry.checked)||(ruminant&&ruminant.checked));'
        . 'Object.keys(roles).forEach(function(role){'
        . 'const total=form.querySelector("input[name=\"role_limits["+role+"]\"]");const addon=extras[role];if(!total||!addon)return;'
        . 'const moduleBox=roles[role];const relevant=moduleBox?moduleBox.checked:hasLivestock;const included=relevant?(parseInt(limits[role]||0,10)||0):0;'
        . 'if(!initialized){addon.value=relevant?Math.max(0,(originalTotals[role]||0)-included):0;}'
        . 'else if(!preserveExtras||!relevant){addon.value=relevant?(parseInt(addon.value||0,10)||0):0;}'
        . 'const extra=Math.max(0,parseInt(addon.value||0,10)||0);const effective=included+extra;total.value=effective;'
        . 'if(includedDisplays[role])includedDisplays[role].textContent=String(included);if(totalDisplays[role])totalDisplays[role].textContent=String(effective);'
        . 'const col=total.closest(".col-sm-6");if(col)col.style.display=relevant?"":"none";'
        . '});initialized=true;'
        . '}'
        . 'Object.keys(extras).forEach(function(role){extras[role].addEventListener("input",function(){refresh(true);});});'
        . 'if(plan)plan.addEventListener("change",function(){refresh(true);});'
        . '[poultry,ruminant].forEach(function(el){if(el)el.addEventListener("change",function(){refresh(true);});});refresh(false);'
        . '});</script>';

    if (stripos($html, '</body>') !== false) {
        $html = preg_replace('/<\\/body>/i', $script . '</body>', $html, 1) ?? $html;
    }
    return $html;
});
