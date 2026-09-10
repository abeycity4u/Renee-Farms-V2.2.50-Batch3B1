<?php
require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/includes/farm_contact_email.php';
require_once dirname(__DIR__) . '/includes/farm_onboarding_mail.php';
requireLogin();
requirePlatformOwner();

const PLATFORM_WORKSPACE_SLUG = 'owner';

function redirectFarms(): void { header('Location: ' . BASE_URL . '/management/farms.php'); exit(); }
function validFarmId($value): int { return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0; }
function validSubscriptionDate(string $value): bool {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}
function editableFarm(PDO $pdo, int $farmId): ?array {
    $stmt = $pdo->prepare("SELECT * FROM farms WHERE id = ? AND slug <> ? LIMIT 1");
    $stmt->execute([$farmId, PLATFORM_WORKSPACE_SLUG]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
function detectFarmLogoExtension(?array $file): ?string {
    if (empty($file['tmp_name'])) return null;
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 2 * 1024 * 1024) throw new RuntimeException('Logo upload must be an image smaller than 2 MB.');

    $mime = function_exists('finfo_open') ? (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) : null;
    $mimeExtensions = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $imageInfo = @getimagesize($file['tmp_name']);
    $imageType = $imageInfo[2] ?? null;
    $extensions = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    $extension = $extensions[$imageType] ?? ($mimeExtensions[$mime] ?? null);
    if ($extension === null) throw new RuntimeException('Logo must contain valid JPG, PNG, or WebP image data. Try re-exporting the image as JPG before uploading.');
    return $extension;
}
function saveFarmLogoUpload(?array $file, int $farmId, ?string $existing = null, ?string $validatedExtension = null): ?string {
    if (empty($file['tmp_name'])) return $existing;
    $extension = $validatedExtension ?? detectFarmLogoExtension($file);

    $directory = dirname(__DIR__) . '/uploads/farms';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) throw new RuntimeException('Unable to create the logo directory.');
    $filename = $farmId . '-' . bin2hex(random_bytes(12)) . '.' . $extension;
    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) throw new RuntimeException('Unable to save the logo.');
    return '/uploads/farms/' . $filename;
}
function ensureTenantRoles(PDO $pdo): void {
    $stmt = $pdo->prepare("INSERT INTO roles (code, name, is_platform_role) VALUES ('farm_admin', 'Admin / Farm Owner', 0) ON DUPLICATE KEY UPDATE name = VALUES(name), is_platform_role = 0");
    $stmt->execute();
}
function findFarmAdminId(PDO $pdo, int $farmId): int {
    $stmt = $pdo->prepare("SELECT u.id FROM users u LEFT JOIN user_roles ur ON ur.user_id = u.id LEFT JOIN roles r ON r.id = ur.role_id WHERE u.farm_id = ? AND (r.code = 'farm_admin' OR u.user_type = 'farm_admin') ORDER BY (r.code = 'farm_admin') DESC, u.id LIMIT 1");
    $stmt->execute([$farmId]);
    return (int)$stmt->fetchColumn();
}
function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}
function tableHasFarmId(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = 'farm_id'");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}
function saveRoleLimits(PDO $pdo, int $farmId, array $limits): void {
    if (!tableExists($pdo, 'farm_role_limits')) return;
    $stmt=$pdo->prepare('INSERT INTO farm_role_limits (farm_id,role_code,max_users) VALUES (?,?,?) ON DUPLICATE KEY UPDATE max_users=VALUES(max_users)');
    foreach ($limits as $role=>$max) $stmt->execute([$farmId,$role,(int)$max]);
}
function loadRoleLimits(PDO $pdo, int $farmId): array {
    $limits=['poultry_manager'=>1,'ruminant_manager'=>1,'sales_rep'=>1,'viewer'=>1];
    if ($farmId < 1 || !tableExists($pdo, 'farm_role_limits')) return $limits;
    $stmt=$pdo->prepare('SELECT role_code,max_users FROM farm_role_limits WHERE farm_id=?'); $stmt->execute([$farmId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) if (array_key_exists($row['role_code'],$limits)) $limits[$row['role_code']] = (int)$row['max_users'];
    return $limits;
}
function deleteFarmRows(PDO $pdo, string $table, int $farmId): void {
    if (!tableExists($pdo, $table) || !tableHasFarmId($pdo, $table)) return;
    $pdo->prepare("DELETE FROM {$table} WHERE farm_id = ?")->execute([$farmId]);
}
function deleteFarmData(PDO $pdo, int $farmId): void {
    // Tenant purge is intentionally explicit and ordered. V2.2 financial allocation
    // tables must be cleared before their parent sales/expense/cycle rows or old
    // RESTRICT foreign keys will reject the farm deletion.
    foreach (['sales_allocations', 'financial_allocations', 'poultry_cycle_acquisitions', 'production_cycle_phases', 'poultry_health_events', 'ruminant_animal_weights', 'ruminant_health_events'] as $table) {
        deleteFarmRows($pdo, $table, $farmId);
    }
    foreach (['customer_ledger_entries', 'stock_transactions', 'stock_batches', 'layer_daily_records', 'broiler_daily_records', 'ruminant_daily_records', 'ruminant_animals', 'farm_expenses', 'profit_loss_summary', 'sales_records', 'production_cycles', 'stock_items', 'inventory_categories', 'financial_settings', 'permissions', 'farm_subscription_seat_addons', 'farm_role_limits', 'v2_audit_log', 'farm_modules', 'billing_seat_change_requests', 'subscriptions'] as $table) {
        deleteFarmRows($pdo, $table, $farmId);
    }
    $pdo->prepare('DELETE ur FROM user_roles ur INNER JOIN users u ON u.id = ur.user_id WHERE u.farm_id = ?')->execute([$farmId]);
    deleteFarmRows($pdo, 'users', $farmId);
    $deleteFarm = $pdo->prepare('DELETE FROM farms WHERE id = ? AND slug <> ?');
    $deleteFarm->execute([$farmId, PLATFORM_WORKSPACE_SLUG]);
    if ($deleteFarm->rowCount() !== 1) throw new RuntimeException('Farm account could not be removed.');
}

$submittedModules = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { http_response_code(419); exit('Invalid request token.'); }
    $farmId = validFarmId($_POST['farm_id'] ?? null);
    $recordedByUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    if (isset($_POST['suspend_farm'], $_POST['farm_id']) || isset($_POST['reactivate_farm'], $_POST['farm_id'])) {
        if (!editableFarm($pdo, $farmId)) { $_SESSION['error'] = 'That farm account cannot be changed.'; redirectFarms(); }
        $status = isset($_POST['suspend_farm']) ? 'suspended' : 'active';
        $reason = $status === 'suspended' ? 'platform_owner_suspend' : 'platform_owner_reactivate';
        try {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE farms SET subscription_status = ? WHERE id = ?')->execute([$status, $farmId]);
            subscription_record_capture($pdo, $farmId, $reason, $recordedByUserId);
            $pdo->commit();
            $_SESSION['success'] = $status === 'suspended' ? 'Farm account suspended. All farm users are blocked from signing in.' : 'Farm account reactivated.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Farm subscription status change failed for farm ' . $farmId . ': ' . $e->getMessage());
            $_SESSION['error'] = 'Unable to change this farm subscription status. No subscription change was saved.';
        }
        redirectFarms();
    }
    if (isset($_POST['delete_farm'], $_POST['farm_id'])) {
        $farm = editableFarm($pdo, $farmId);
        if (!$farm) { $_SESSION['error'] = 'The platform workspace cannot be deleted.'; redirectFarms(); }
        $logoPath = $farm['logo_path'] ?? null;
        try { $pdo->beginTransaction(); deleteFarmData($pdo, $farmId); $pdo->commit();
            if ($logoPath && preg_match('#^/uploads/farms/[a-zA-Z0-9._-]+$#', $logoPath)) @unlink(dirname(__DIR__) . $logoPath);
            $_SESSION['success'] = 'Farm account and all data stored for it were permanently deleted.';
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log('Farm purge failed for farm ' . $farmId . ': ' . $e->getMessage()); $_SESSION['error'] = 'Unable to delete this farm and its data. No data was removed.'; }
        redirectFarms();
    }

    $name = trim($_POST['name'] ?? ''); $slug = strtolower(trim($_POST['slug'] ?? '')); $color = trim($_POST['primary_color'] ?? '#198754');
    $submittedModules = farm_entitlement_normalize_modules($_POST['modules'] ?? []);
    $seatAddOns = subscription_seat_normalize_addons(is_array($_POST['seat_addons'] ?? null) ? $_POST['seat_addons'] : []);
    $username = trim($_POST['owner_username'] ?? ''); $password = $_POST['owner_password'] ?? '';
    $rawOwnerEmail = trim($_POST['owner_email'] ?? '');
    $rawContactEmail = trim($_POST['contact_email'] ?? '');
    $emailPairError = null;
    try {
        $emailPair = farm_contact_email_pair($rawOwnerEmail, $rawContactEmail);
        $email = $emailPair['owner_email'];
        $contactEmail = $emailPair['contact_email'];
    } catch (InvalidArgumentException $emailError) {
        $email = strtolower($rawOwnerEmail);
        $contactEmail = strtolower($rawContactEmail);
        $emailPairError = $emailError->getMessage();
    }
    $startDate = trim($_POST['subscription_starts_at'] ?? ''); $endDate = trim($_POST['subscription_ends_at'] ?? '');
    $plan = strtolower(trim((string)($_POST['plan'] ?? 'starter'))); $status = $_POST['status'] ?? 'trial';
    $roleLimits = subscription_plan_is_valid($plan)
        ? subscription_plan_effective_role_limits($plan, $submittedModules, $seatAddOns)
        : normalize_role_limits_for_entitlements($_POST['role_limits'] ?? [], $submittedModules);
    $repairOwnerNeeded = isset($_POST['update_farm']) && $farmId > 0 && findFarmAdminId($pdo, $farmId) === 0;
    if ($name === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || $slug === PLATFORM_WORKSPACE_SLUG || !preg_match('/^#[0-9a-fA-F]{6}$/', $color) || $username === '') {
        $error = 'Enter the Farm Admin details and use a unique lowercase Farm Workspace ID.';
    } elseif (!$submittedModules) $error = 'Select Poultry, Ruminant, or both so the farm workspace has an active service entitlement.';
    elseif ($emailPairError !== null) $error = $emailPairError;
    elseif (!in_array($plan, ['starter', 'growth', 'pro'], true) || !in_array($status, ['trial', 'active', 'past_due', 'suspended'], true)) $error = 'Select a valid subscription plan and status.';
    elseif (($startDate !== '' && !validSubscriptionDate($startDate)) || ($endDate !== '' && !validSubscriptionDate($endDate))) $error = 'Subscription dates must be valid dates.';
    elseif ($startDate !== '' && $endDate !== '' && $endDate < $startDate) $error = 'Subscription end date cannot be before its start date.';
    elseif (isset($_POST['create_farm']) && ($passwordError = password_security_validate($password)) !== null) $error = $passwordError;
    elseif ($repairOwnerNeeded && ($passwordError = password_security_validate($password)) !== null) $error = 'This incomplete farm has no admin account. ' . $passwordError;
    elseif (isset($_POST['update_farm']) && $password !== '' && ($passwordError = password_security_validate($password)) !== null) $error = $passwordError;
    else try {
        if (isset($_POST['update_farm'])) {
            subscription_seat_assert_capacity($pdo, $farmId, $plan, $submittedModules, $seatAddOns);
        }
        $logoExtension = detectFarmLogoExtension($_FILES['logo'] ?? null);
        $createdFarmId = 0;
        $newLogoPath = null;
        $onboardingPayload = null;
        $pdo->beginTransaction();
        ensureTenantRoles($pdo);
        if (isset($_POST['create_farm'])) {
            $stmt = $pdo->prepare('INSERT INTO farms (name, slug, primary_color, contact_name, contact_email, subscription_plan, subscription_status, subscription_starts_at, subscription_ends_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, $slug, $color, trim($_POST['contact_name'] ?? ''), $contactEmail, $plan, $status, $startDate ? "$startDate 00:00:00" : null, $endDate ? "$endDate 23:59:59" : null]);
            $farmId = (int)$pdo->lastInsertId(); $createdFarmId = $farmId; $logoPath = saveFarmLogoUpload($_FILES['logo'] ?? null, $farmId, null, $logoExtension); $newLogoPath = $logoPath;
            if ($logoPath) $pdo->prepare('UPDATE farms SET logo_path = ? WHERE id = ?')->execute([$logoPath, $farmId]);
            $owner = $pdo->prepare('INSERT INTO users (farm_id, username, password, email, user_type, full_name) VALUES (?, ?, ?, ?, ?, ?)');
            $ownerName = trim($_POST['owner_name'] ?? $username);
            $owner->execute([$farmId, $username, password_security_hash($password), $email, 'farm_admin', $ownerName]); $ownerId = (int)$pdo->lastInsertId();
            sync_farm_entitlements($pdo, $farmId, $submittedModules);
            assign_protected_farm_admin_role($pdo, $farmId, $ownerId);
            saveRoleLimits($pdo, $farmId, $roleLimits);
            subscription_seat_save_addons($pdo, $farmId, $seatAddOns);
            subscription_record_capture($pdo, $farmId, 'tenant_created', $recordedByUserId);
            $onboardingPayload = [
                'contact_email' => $contactEmail,
                'farm_name' => $name,
                'workspace_id' => $slug,
                'username' => $username,
                'password' => $password,
                'recipient_name' => trim($_POST['contact_name'] ?? '') ?: $ownerName,
            ];
            $message = "Created {$name}.";
        } elseif (isset($_POST['update_farm'])) {
            $farm = editableFarm($pdo, $farmId); if (!$farm) throw new RuntimeException('That farm cannot be edited.');
            $ownerId = findFarmAdminId($pdo, $farmId);
            if (!$ownerId) {
                $ownerStmt = $pdo->prepare('INSERT INTO users (farm_id, username, password, email, user_type, full_name) VALUES (?, ?, ?, ?, ?, ?)');
                $ownerStmt->execute([$farmId, $username, password_security_hash($password), $email, 'farm_admin', trim($_POST['owner_name'] ?? $username)]);
                $ownerId = (int)$pdo->lastInsertId();
            }
            $logoPath = saveFarmLogoUpload($_FILES['logo'] ?? null, $farmId, $farm['logo_path'], $logoExtension); $newLogoPath = ($logoPath !== ($farm['logo_path'] ?? null)) ? $logoPath : null;
            $pdo->prepare('UPDATE farms SET name = ?, slug = ?, primary_color = ?, contact_name = ?, contact_email = ?, subscription_plan = ?, subscription_status = ?, subscription_starts_at = ?, subscription_ends_at = ?, logo_path = ? WHERE id = ?')->execute([$name, $slug, $color, trim($_POST['contact_name'] ?? ''), $contactEmail, $plan, $status, $startDate ? "$startDate 00:00:00" : null, $endDate ? "$endDate 23:59:59" : null, $logoPath, $farmId]);
            $ownerSql = 'UPDATE users SET username = ?, email = ?, full_name = ?, user_type = ?' . ($password !== '' ? ', password = ?' : '') . ' WHERE id = ? AND farm_id = ?'; $params = [$username, $email, trim($_POST['owner_name'] ?? $username), 'farm_admin']; if ($password !== '') $params[] = password_security_hash($password); $params[] = $ownerId; $params[] = $farmId; $pdo->prepare($ownerSql)->execute($params);
            sync_farm_entitlements($pdo, $farmId, $submittedModules);
            assign_protected_farm_admin_role($pdo, $farmId, $ownerId);
            saveRoleLimits($pdo, $farmId, $roleLimits);
            subscription_seat_save_addons($pdo, $farmId, $seatAddOns);
            subscription_record_capture($pdo, $farmId, 'platform_owner_update', $recordedByUserId);
            $message = "Updated {$name}.";
        } else throw new RuntimeException('Unknown farm action.');

        $pdo->commit();
        $_SESSION['success'] = $message;

        if ($createdFarmId > 0) {
            if ($contactEmail !== '' && $onboardingPayload !== null) {
                try {
                    $mailResult = farm_onboarding_send_credentials($onboardingPayload);
                    if (($mailResult['sent'] ?? false) === true) {
                        $_SESSION['success'] .= ' Sign-in details were sent to ' . $contactEmail . '.';
                    } else {
                        $_SESSION['warning'] = 'The farm was created, but the credential email could not be accepted by the mail service. Share the credentials securely and check the outbound mail configuration.';
                    }
                } catch (Throwable $mailError) {
                    error_log('Farm onboarding email failed for farm ' . $createdFarmId . ': ' . $mailError->getMessage());
                    $_SESSION['warning'] = 'The farm was created, but the credential email could not be sent. Share the credentials securely and check the outbound mail configuration.';
                }
            } else {
                $_SESSION['warning'] = 'The farm was created without a contact email, so no credential email was sent. The Farm Admin can add a billing contact email from Billing & Subscription after signing in.';
            }
        }

        redirectFarms();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (!empty($createdFarmId) && editableFarm($pdo, $createdFarmId)) {
            try { deleteFarmData($pdo, $createdFarmId); } catch (Throwable $cleanupError) { error_log('Incomplete farm cleanup failed: ' . $cleanupError->getMessage()); }
        }
        if (!empty($newLogoPath) && preg_match('#^/uploads/farms/[a-zA-Z0-9._-]+$#', $newLogoPath)) @unlink(dirname(__DIR__) . $newLogoPath);
        $error = $e instanceof RuntimeException ? $e->getMessage() : 'Unable to save this farm. Its workspace ID or owner username may already exist.';
    }
}

$editFarm = null; $editOwner = null; $editModules = [];
if (isset($_GET['edit'])) {
    $editFarm = editableFarm($pdo, validFarmId($_GET['edit']));
    if ($editFarm) {
        $ownerId = findFarmAdminId($pdo, (int)$editFarm['id']);
        if ($ownerId) { $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? AND farm_id = ?'); $stmt->execute([$ownerId, $editFarm['id']]); $editOwner = $stmt->fetch(PDO::FETCH_ASSOC); }
        $editModules = farm_entitlement_modules($pdo, (int)$editFarm['id']);
    }
}
$farms = $pdo->query("SELECT f.*, GROUP_CONCAT(CASE WHEN fm.is_enabled = 1 THEN fm.module_code END ORDER BY fm.module_code SEPARATOR ', ') AS modules, DATEDIFF(f.subscription_ends_at, CURDATE()) AS days_remaining FROM farms f LEFT JOIN farm_modules fm ON fm.farm_id = f.id WHERE f.slug <> 'owner' GROUP BY f.id ORDER BY f.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$form = $editFarm ?: ['name'=>'','slug'=>'','primary_color'=>'#198754','contact_name'=>'','contact_email'=>'','subscription_plan'=>'starter','subscription_status'=>'trial','subscription_starts_at'=>'','subscription_ends_at'=>''];
$owner = $editOwner ?: ['username'=>'','email'=>'','full_name'=>''];
$ownerNeedsRepair = $editFarm !== null && $editOwner === null;

if ($editFarm !== null) {
    try {
        $displayEmails = farm_contact_email_pair($owner['email'] ?? '', $form['contact_email'] ?? '');
        $owner['email'] = $displayEmails['owner_email'];
        $form['contact_email'] = $displayEmails['contact_email'];
    } catch (InvalidArgumentException $ignored) {
        // Preserve any legacy invalid value for explicit administrator correction.
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($error)) {
    $form = array_merge($form, [
        'name' => trim($_POST['name'] ?? ''),
        'slug' => strtolower(trim($_POST['slug'] ?? '')),
        'primary_color' => trim($_POST['primary_color'] ?? '#198754'),
        'contact_name' => trim($_POST['contact_name'] ?? ''),
        'contact_email' => isset($contactEmail) ? $contactEmail : trim($_POST['contact_email'] ?? ''),
        'subscription_plan' => $_POST['plan'] ?? 'starter',
        'subscription_status' => $_POST['status'] ?? 'trial',
        'subscription_starts_at' => trim($_POST['subscription_starts_at'] ?? ''),
        'subscription_ends_at' => trim($_POST['subscription_ends_at'] ?? ''),
    ]);
    $owner = array_merge($owner, [
        'username' => trim($_POST['owner_username'] ?? ''),
        'email' => isset($email) ? $email : trim($_POST['owner_email'] ?? ''),
        'full_name' => trim($_POST['owner_name'] ?? ''),
    ]);
    $editModules = $submittedModules ?? [];
}

$editRoleLimits = loadRoleLimits($pdo, (int)($editFarm['id'] ?? 0));
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($error) && isset($roleLimits)) $editRoleLimits = $roleLimits;
$moduleLabels = farm_entitlement_module_labels();
?>
<!doctype html><html lang="en"><head><?php include dirname(__DIR__) . '/navbar_head.php'; ?><title>Farms &amp; Tenants</title></head><body><?php include dirname(__DIR__) . '/navbar.php'; ?>
<main class="container py-4"><div class="d-flex justify-content-between app-responsive-toolbar"><h1 class="h3">Farms &amp; Tenants</h1><span class="badge bg-dark">Owner / Developer</span></div>
<?php if (!empty($error)): ?><?php renderNotification('error', $error, 'Farm action could not be completed.'); ?><?php endif; ?>
<?php if ($ownerNeedsRepair): ?><?php renderNotification('warning', 'This farm was only partially created and has no admin account. Complete the username, password, name, and subscribed modules below; saving will create and link its admin safely.', 'Farm setup needs attention.'); ?><?php endif; ?>
<div class="card my-3"><div class="card-body"><h2 class="h5"><?php echo $editFarm ? 'Edit Farm' : 'Add New Farm'; ?></h2><form method="post" enctype="multipart/form-data" class="row g-3" id="farmAccountForm" novalidate><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>"><?php if ($editFarm): ?><input type="hidden" name="farm_id" value="<?php echo (int)$editFarm['id']; ?>"><?php endif; ?>
<div class="col-md-4"><label class="form-label">Username</label><input class="form-control" name="owner_username" value="<?php echo htmlspecialchars($owner['username']); ?>" required></div><div class="col-md-4"><label class="form-label">Password<?php echo $editFarm && !$ownerNeedsRepair ? ' (leave blank to keep)' : ''; ?></label><input class="form-control" type="password" name="owner_password" <?php echo !$editFarm || $ownerNeedsRepair ? 'minlength="' . password_security_min_length() . '" required' : ''; ?>></div><div class="col-md-4"><label class="form-label">Farm Admin email</label><input class="form-control" type="email" name="owner_email" value="<?php echo htmlspecialchars($owner['email']); ?>"><div class="form-text">If only one email is supplied, the farm contact email can use the same address.</div></div>
<div class="col-md-6"><label class="form-label">Full Name</label><input class="form-control" name="owner_name" value="<?php echo htmlspecialchars($owner['full_name']); ?>" required></div><div class="col-md-6"><label class="form-label">Farm name</label><input class="form-control" name="name" value="<?php echo htmlspecialchars($form['name']); ?>" required></div><div class="col-md-6"><label class="form-label">Farm Workspace ID</label><input class="form-control" name="slug" value="<?php echo htmlspecialchars($form['slug']); ?>" pattern="[a-z0-9]+(-[a-z0-9]+)*" required></div><div class="col-md-6"><label class="form-label">Logo upload</label><input class="form-control" type="file" name="logo" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"><?php if ($editFarm && !empty($editFarm['logo_path'])): ?><div class="form-text">Current logo is saved and will be kept unless you choose a replacement.</div><img src="<?php echo BASE_URL . htmlspecialchars($editFarm['logo_path']); ?>" alt="Current farm logo" class="img-thumbnail mt-2 app-farm-logo-preview"><?php endif; ?></div>
<div class="col-md-4"><label class="form-label">Primary colour</label><input class="form-control form-control-color" type="color" name="primary_color" value="<?php echo htmlspecialchars($form['primary_color']); ?>"></div><div class="col-md-4"><label class="form-label">Contact name</label><input class="form-control" name="contact_name" value="<?php echo htmlspecialchars($form['contact_name']); ?>"></div><div class="col-md-4"><label class="form-label">Billing / contact email</label><input class="form-control" type="email" name="contact_email" value="<?php echo htmlspecialchars($form['contact_email']); ?>"><div class="form-text">Used for billing checkout. On new farms, sign-in credentials are emailed here when supplied.</div></div>
<div class="col-12"><div class="card border"><div class="card-body"><h3 class="h6 mb-1">Farm Admin</h3><p class="form-text mt-0 mb-0">Protected tenant administrator. Identity is always <strong>Farm Admin</strong>; operational access comes from the subscribed modules below, not specialist roles.</p></div></div></div>
<div class="col-md-12"><label class="form-label d-block">Subscribed Modules</label><?php foreach ($moduleLabels as $value => $label): ?><div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="modules[]" value="<?php echo htmlspecialchars($value); ?>" data-module-entitlement="1" <?php echo in_array($value, $editModules, true) ? 'checked' : ''; ?>><label class="form-check-label"><?php echo htmlspecialchars($label); ?></label></div><?php endforeach; ?><div class="form-text">Disabling a module removes current operational access but preserves its historical farm records.</div></div>
<div class="col-12"><div class="card border"><div class="card-body"><h3 class="h6 mb-1">User limits by role</h3><p class="form-text mt-0">Platform limit for how many login accounts this farm may create under each specialist role. Farm Admin is one protected account. Disabled modules force their specialist limit to 0 when saved.</p><div class="row g-2">
<?php foreach (['poultry_manager'=>'Poultry users','ruminant_manager'=>'Ruminant users','sales_rep'=>'Sales users','viewer'=>'Viewer users'] as $limitRole=>$limitLabel): ?><div class="col-sm-6 col-lg-3"><label class="form-label"><?php echo $limitLabel; ?></label><input class="form-control" type="number" min="0" max="500" name="role_limits[<?php echo $limitRole; ?>]" value="<?php echo (int)($editRoleLimits[$limitRole] ?? 1); ?>"></div><?php endforeach; ?>
</div></div></div></div>
<div class="col-md-3"><label class="form-label">Plan</label><select class="form-select" name="plan"><?php foreach (['starter'=>'Starter','growth'=>'Growth','pro'=>'Pro'] as $value=>$label): ?><option value="<?php echo $value; ?>" <?php echo $form['subscription_plan']===$value?'selected':''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></div><div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['trial'=>'Trial','active'=>'Active','past_due'=>'Past Due','suspended'=>'Suspended'] as $value=>$label): ?><option value="<?php echo $value; ?>" <?php echo $form['subscription_status']===$value?'selected':''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></div><div class="col-md-3"><label class="form-label">Starts Date</label><input class="form-control" type="date" name="subscription_starts_at" value="<?php echo htmlspecialchars($form['subscription_starts_at'] ? date('Y-m-d', strtotime($form['subscription_starts_at'])) : ''); ?>"></div><div class="col-md-3"><label class="form-label">End Date</label><input class="form-control" type="date" name="subscription_ends_at" value="<?php echo htmlspecialchars($form['subscription_ends_at'] ? date('Y-m-d', strtotime($form['subscription_ends_at'])) : ''); ?>"></div>
<div class="col-12"><button name="<?php echo $editFarm ? 'update_farm' : 'create_farm'; ?>" class="btn btn-success"><?php echo $editFarm ? 'Save farm changes' : 'Create farm and admin'; ?></button><?php if ($editFarm): ?><a class="btn btn-outline-secondary ms-2" href="<?php echo BASE_URL; ?>/management/farms.php">Cancel</a><?php endif; ?></div></form></div></div>
<div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Farm</th><th>Farm Workspace ID</th><th>Modules</th><th>Plan</th><th>Status</th><th>Subscription Ends</th><th>Actions</th></tr></thead><tbody><?php foreach ($farms as $farm): ?><tr><td><?php echo htmlspecialchars($farm['name']); ?></td><td><?php echo htmlspecialchars($farm['slug']); ?></td><td><?php echo htmlspecialchars($farm['modules'] ?: 'None'); ?></td><td><?php echo htmlspecialchars($farm['subscription_plan']); ?></td><td><?php echo htmlspecialchars($farm['subscription_status']); ?></td><td><?php echo htmlspecialchars($farm['subscription_ends_at'] ? date('d M Y', strtotime($farm['subscription_ends_at'])) : 'Not set'); ?></td><td><a class="btn btn-sm btn-outline-primary" href="?edit=<?php echo (int)$farm['id']; ?>">Edit</a> <form method="post" class="d-inline"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>"><input type="hidden" name="farm_id" value="<?php echo (int)$farm['id']; ?>"><?php if ($farm['subscription_status'] === 'suspended'): ?><button name="reactivate_farm" class="btn btn-sm btn-outline-success">Reactivate</button><?php else: ?><button type="button" name="suspend_farm" class="btn btn-sm btn-outline-warning" data-farm-action="suspend">Suspend</button><?php endif; ?><button type="button" name="delete_farm" class="btn btn-sm btn-outline-danger" data-farm-action="delete" data-farm-name="<?php echo app_attr($farm['name']); ?>">Delete</button></form></td></tr><?php endforeach; ?></tbody></table></div></div></main>
<script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/farms.js'); ?>"></script>

</body></html>
