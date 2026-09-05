<?php
/**
 * Keep Team Users role pickers commercially clean.
 *
 * management/users.php remains the backend authority for role/module validation.
 * This bridge only removes disabled specialist roles from the Add/Edit User
 * modals, so tenants see roles relevant to their active subscription instead of
 * grey 0/0 options.
 */

$teamUserRoleVisibilityPath = '/' . ltrim(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if (!($teamUserRoleVisibilityPath === '/management/users.php' || str_ends_with($teamUserRoleVisibilityPath, '/management/users.php'))) return;
if (!isset($_SESSION['user_id'])) return;

ob_start(static function (string $html): string {
    return preg_replace(
        '~<div class="form-check"><input(?=[^>]*\bname="roles\[\]")(?=[^>]*\bid="(?:add|edit)-)(?=[^>]*\bdisabled\b)[^>]*><label[^>]*>.*?</label></div>~is',
        '',
        $html
    ) ?? $html;
});
