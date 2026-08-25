<?php
/** Hospital settings: branding, report/invoice defaults, formats and admin account. */
require_once __DIR__ . '/../../config/session.php';

$fields = [
    'hospital_name'      => ['Hospital name', 'text'],
    'hospital_tagline'   => ['Tagline', 'text'],
    'hospital_address'   => ['Address', 'textarea'],
    'hospital_phone'     => ['Phone', 'text'],
    'hospital_email'     => ['Email', 'text'],
    'hospital_website'   => ['Website', 'text'],
    'currency_symbol'    => ['Currency symbol', 'text'],
    'country_code'       => ['WhatsApp country code (digits only)', 'text'],
    'date_format'        => ['Date format', 'text'],
    'time_format'        => ['Time format', 'text'],
    'patient_prefix'     => ['Patient ID prefix', 'text'],
    'invoice_prefix'     => ['Invoice prefix', 'text'],
    'order_prefix'       => ['Order prefix', 'text'],
    'report_prefix'      => ['Report prefix', 'text'],
    'report_header'      => ['Report title', 'text'],
    'authorized_by'      => ['Reports authorized by', 'text'],
    'report_footer'      => ['Report footer', 'textarea'],
    'report_disclaimer'  => ['Report disclaimer', 'textarea'],
    'invoice_footer'     => ['Invoice footer', 'textarea'],
    'default_discount'   => ['Default discount', 'number'],
];

$logoError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(BASE_URL . '/modules/settings/index.php');
    $action = post('action', 'general');

    if ($action === 'general') {
        $required = [
            'currency_symbol' => 'Rs.', 'date_format' => 'd M Y', 'time_format' => 'h:i A',
            'patient_prefix' => 'PAT', 'invoice_prefix' => 'INV', 'order_prefix' => 'ORD', 'report_prefix' => 'RPT',
        ];
        foreach ($fields as $key => $meta) {
            if (!array_key_exists($key, $_POST)) {
                continue;
            }
            $value = trim((string)post($key));
            if ($value === '' && isset($required[$key])) {
                $value = $required[$key];
            }
            setSetting($key, $value);
        }

        if (!empty($_FILES['logo']['tmp_name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
            $info = @getimagesize($_FILES['logo']['tmp_name']);
            $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
            if (!$info || !isset($allowed[$info[2]])) {
                $logoError = 'The logo must be a JPG, PNG, GIF or WEBP image.';
            } elseif ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
                $logoError = 'The logo must be smaller than 2 MB.';
            } else {
                $dir = UPLOADS_PATH . '/logos';
                if (!is_dir($dir)) {
                    @mkdir($dir, 0777, true);
                }
                $name = 'logo-' . date('YmdHis') . '.' . $allowed[$info[2]];
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $dir . '/' . $name)) {
                    $old = getSetting('hospital_logo', '');
                    if ($old && is_file($dir . '/' . $old)) {
                        @unlink($dir . '/' . $old);
                    }
                    setSetting('hospital_logo', $name);
                } else {
                    $logoError = 'Could not save the uploaded logo. Check permissions on uploads/logos.';
                }
            }
        }

        logActivity('Settings updated', 'Hospital settings saved', 'settings', null);
        flash($logoError ? 'warning' : 'success', $logoError ?: 'Settings saved successfully.');
        redirect(BASE_URL . '/modules/settings/index.php');
    }

    if ($action === 'remove_logo') {
        $old = getSetting('hospital_logo', '');
        if ($old && is_file(UPLOADS_PATH . '/logos/' . $old)) {
            @unlink(UPLOADS_PATH . '/logos/' . $old);
        }
        setSetting('hospital_logo', '');
        flash('success', 'Logo removed.');
        redirect(BASE_URL . '/modules/settings/index.php');
    }

    if ($action === 'account') {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $user = fetchOne('SELECT * FROM users WHERE id = ?', [$userId]);
        $current = (string)post('current_password');
        $new = (string)post('new_password');
        $confirm = (string)post('confirm_password');
        $fullName = trim((string)post('full_name'));
        $email = trim((string)post('email'));

        if (!$user || !password_verify($current, $user['password'])) {
            flash('danger', 'Your current password is incorrect.');
        } elseif ($new !== '' && strlen($new) < 6) {
            flash('danger', 'The new password must be at least 6 characters long.');
        } elseif ($new !== '' && $new !== $confirm) {
            flash('danger', 'The new passwords do not match.');
        } else {
            if ($new !== '') {
                $stmt = $pdo->prepare('UPDATE users SET full_name = ?, email = ?, password = ? WHERE id = ?');
                $stmt->execute([$fullName ?: $user['full_name'], $email ?: null, password_hash($new, PASSWORD_DEFAULT), $userId]);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET full_name = ?, email = ? WHERE id = ?');
                $stmt->execute([$fullName ?: $user['full_name'], $email ?: null, $userId]);
            }
            $_SESSION['full_name'] = $fullName ?: $user['full_name'];
            logActivity('Account updated', 'Admin profile updated', 'users', $userId);
            flash('success', 'Account updated.');
        }
        redirect(BASE_URL . '/modules/settings/index.php#account');
    }
}

$values = [];
foreach ($fields as $key => $meta) {
    $values[$key] = getSetting($key, '');
}
$user = fetchOne('SELECT * FROM users WHERE id = ?', [(int)($_SESSION['user_id'] ?? 0)]);
$logs = fetchAll('SELECT * FROM activity_logs ORDER BY id DESC LIMIT 15');

$pageTitle = 'Settings';
$pageSubtitle = 'Branding, report defaults and administrator account';
$activeMenu = 'settings';
require_once INC_PATH . '/header.php';
?>

<div class="row g-3">
    <div class="col-lg-8">
        <form method="post" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="general">

            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-hospital me-2"></i>Hospital profile</div>
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Hospital name <span class="text-danger">*</span></label>
                        <input type="text" name="hospital_name" class="form-control" required value="<?= sanitize($values['hospital_name']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tagline</label>
                        <input type="text" name="hospital_tagline" class="form-control" value="<?= sanitize($values['hospital_tagline']) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <textarea name="hospital_address" class="form-control" rows="2"><?= sanitize($values['hospital_address']) ?></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Phone</label>
                        <input type="text" name="hospital_phone" class="form-control" value="<?= sanitize($values['hospital_phone']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Email</label>
                        <input type="text" name="hospital_email" class="form-control" value="<?= sanitize($values['hospital_email']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Website</label>
                        <input type="text" name="hospital_website" class="form-control" value="<?= sanitize($values['hospital_website']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Logo (JPG/PNG/GIF/WEBP, max 2 MB)</label>
                        <input type="file" name="logo" class="form-control" accept="image/*">
                    </div>
                    <div class="col-md-6 d-flex align-items-end gap-2">
                        <?php if (hospitalLogoUrl()): ?>
                            <img src="<?= hospitalLogoUrl() ?>" alt="Logo" style="height:52px" class="border rounded p-1 bg-white">
                            <button type="submit" form="removeLogoForm" class="btn btn-sm btn-outline-danger"
                                    data-confirm="Remove the current logo?"><i class="bi bi-trash me-1"></i>Remove logo</button>
                        <?php else: ?>
                            <span class="text-muted small">No logo uploaded yet.</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-sliders me-2"></i>Regional & numbering</div>
                <div class="card-body row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Currency symbol</label>
                        <input type="text" name="currency_symbol" class="form-control" value="<?= sanitize($values['currency_symbol']) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">WhatsApp country code</label>
                        <input type="text" name="country_code" class="form-control" value="<?= sanitize($values['country_code']) ?>">
                        <div class="form-text">Digits only, e.g. 92 for Pakistan.</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date format</label>
                        <input type="text" name="date_format" class="form-control" value="<?= sanitize($values['date_format']) ?>">
                        <div class="form-text">PHP format, e.g. <code>d M Y</code>.</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Time format</label>
                        <input type="text" name="time_format" class="form-control" value="<?= sanitize($values['time_format']) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Patient ID prefix</label>
                        <input type="text" name="patient_prefix" class="form-control" value="<?= sanitize($values['patient_prefix']) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Invoice prefix</label>
                        <input type="text" name="invoice_prefix" class="form-control" value="<?= sanitize($values['invoice_prefix']) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Order prefix</label>
                        <input type="text" name="order_prefix" class="form-control" value="<?= sanitize($values['order_prefix']) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Report prefix</label>
                        <input type="text" name="report_prefix" class="form-control" value="<?= sanitize($values['report_prefix']) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Default discount</label>
                        <input type="number" step="0.01" min="0" name="default_discount" class="form-control" value="<?= sanitize($values['default_discount']) ?>">
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-file-earmark-medical me-2"></i>Reports & invoices</div>
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Report title</label>
                        <input type="text" name="report_header" class="form-control" value="<?= sanitize($values['report_header']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Reports authorized by</label>
                        <input type="text" name="authorized_by" class="form-control" value="<?= sanitize($values['authorized_by']) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Report footer</label>
                        <textarea name="report_footer" class="form-control" rows="2"><?= sanitize($values['report_footer']) ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Report disclaimer</label>
                        <textarea name="report_disclaimer" class="form-control" rows="3"><?= sanitize($values['report_disclaimer']) ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Invoice footer</label>
                        <textarea name="invoice_footer" class="form-control" rows="2"><?= sanitize($values['invoice_footer']) ?></textarea>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Save settings</button>
                </div>
            </div>
        </form>

        <div class="card mb-3" id="account">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-person-gear me-2"></i>Administrator account</div>
            <form method="post" class="card-body row g-3">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="account">
                <div class="col-md-6">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control" value="<?= sanitize($user['username'] ?? '') ?>" disabled>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Full name</label>
                    <input type="text" name="full_name" class="form-control" value="<?= sanitize($user['full_name'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= sanitize($user['email'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Current password <span class="text-danger">*</span></label>
                    <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                </div>
                <div class="col-md-6">
                    <label class="form-label">New password</label>
                    <input type="password" name="new_password" class="form-control" autocomplete="new-password" placeholder="Leave blank to keep current">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Confirm new password</label>
                    <input type="password" name="confirm_password" class="form-control" autocomplete="new-password">
                </div>
                <div class="col-12">
                    <button class="btn btn-primary"><i class="bi bi-shield-lock me-1"></i>Update account</button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-2"></i>System</div>
            <div class="card-body small">
                <div class="d-flex justify-content-between mb-1"><span>Application</span><strong><?= APP_NAME ?></strong></div>
                <div class="d-flex justify-content-between mb-1"><span>Version</span><strong><?= APP_VERSION ?></strong></div>
                <div class="d-flex justify-content-between mb-1"><span>PHP</span><strong><?= PHP_VERSION ?></strong></div>
                <div class="d-flex justify-content-between mb-1"><span>Database</span><strong><?= sanitize(DB_NAME) ?></strong></div>
                <div class="d-flex justify-content-between"><span>Base URL</span><strong class="text-truncate ms-2"><?= sanitize(BASE_URL) ?></strong></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history me-2"></i>Recent activity</div>
            <ul class="list-group list-group-flush">
                <?php if (!$logs): ?>
                    <li class="list-group-item small text-muted">No activity recorded yet.</li>
                <?php endif; ?>
                <?php foreach ($logs as $log): ?>
                    <li class="list-group-item small">
                        <strong><?= sanitize($log['action']) ?></strong><br>
                        <span class="text-muted"><?= sanitize($log['description']) ?></span><br>
                        <span class="text-muted"><?= formatDateTime($log['created_at']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<form method="post" id="removeLogoForm" class="d-none">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="remove_logo">
</form>

<?php require_once INC_PATH . '/footer.php'; ?>
