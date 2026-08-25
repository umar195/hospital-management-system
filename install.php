<?php
/**
 * Hospital Management System - installation wizard.
 *
 * 1. Checks the server requirements
 * 2. Tests / creates the database
 * 3. Imports database/hospital.sql (schema + default data)
 * 4. Optionally imports database/demo_data.sql
 * 5. Creates the administrator account and writes config/db_config.php
 */
define('INSTALL_MODE', true);
require_once __DIR__ . '/config/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('HMSSESSID');
    session_start();
}

$alreadyInstalled = false;
if ($pdo instanceof PDO) {
    try {
        $alreadyInstalled = (bool)$pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn();
    } catch (Exception $e) {
        $alreadyInstalled = false;
    }
}

/** Split a .sql dump into individual statements. */
function splitSqlStatements($sql)
{
    $statements = [];
    $buffer = '';
    foreach (preg_split("/\r\n|\n|\r/", $sql) as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || strpos($trimmed, '--') === 0 || strpos($trimmed, '#') === 0) {
            continue;
        }
        $buffer .= $line . "\n";
        if (substr($trimmed, -1) === ';') {
            $statements[] = trim($buffer);
            $buffer = '';
        }
    }
    if (trim($buffer) !== '') {
        $statements[] = trim($buffer);
    }
    return $statements;
}

function runSqlFile(PDO $db, $file, array &$log)
{
    if (!is_file($file)) {
        throw new RuntimeException('SQL file not found: ' . $file);
    }
    $statements = splitSqlStatements(file_get_contents($file));
    $count = 0;
    foreach ($statements as $statement) {
        if (preg_match('/^\s*(CREATE\s+DATABASE|USE)\b/i', $statement)) {
            continue;
        }
        $db->exec($statement);
        $count++;
    }
    $log[] = basename($file) . ': executed ' . $count . ' statements.';
}

$errors  = [];
$log     = [];
$success = false;

$form = [
    'db_host'       => $_POST['db_host']       ?? DB_HOST,
    'db_user'       => $_POST['db_user']       ?? DB_USER,
    'db_pass'       => $_POST['db_pass']       ?? DB_PASS,
    'db_name'       => $_POST['db_name']       ?? DB_NAME,
    'admin_user'    => $_POST['admin_user']    ?? 'admin',
    'admin_name'    => $_POST['admin_name']    ?? 'System Administrator',
    'admin_email'   => $_POST['admin_email']   ?? 'admin@hospital.local',
    'admin_pass'    => '',
    'hospital_name' => $_POST['hospital_name'] ?? 'City Care Hospital',
    'demo'          => isset($_POST['demo']),
    'fresh'         => isset($_POST['fresh']),
];

// ---------------------------------------------------------------------------
// Requirement checks
// ---------------------------------------------------------------------------
$requirements = [
    'PHP 7.4 or newer'                => version_compare(PHP_VERSION, '7.4.0', '>='),
    'PDO extension'                   => extension_loaded('pdo'),
    'PDO MySQL driver'                => extension_loaded('pdo_mysql'),
    'mbstring extension'              => extension_loaded('mbstring'),
    'config/ directory writable'      => is_writable(__DIR__ . '/config'),
    'uploads/ directory writable'     => is_writable(__DIR__ . '/uploads'),
];
$requirementsOk = !in_array(false, $requirements, true);

// ---------------------------------------------------------------------------
// Handle installation
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $errors[] = 'Security check failed. Please reload the page and try again.';
    }
    $adminPass  = (string)($_POST['admin_pass'] ?? '');
    $adminPass2 = (string)($_POST['admin_pass2'] ?? '');
    if (strlen($adminPass) < 6) {
        $errors[] = 'The administrator password must be at least 6 characters long.';
    }
    if ($adminPass !== $adminPass2) {
        $errors[] = 'The two administrator passwords do not match.';
    }
    if ($form['admin_user'] === '') {
        $errors[] = 'Administrator username is required.';
    }
    if (!preg_match('/^[A-Za-z0-9_]+$/', $form['db_name'])) {
        $errors[] = 'Database name may only contain letters, numbers and underscores.';
    }
    if (!$requirementsOk) {
        $errors[] = 'Please fix the failed server requirements before installing.';
    }

    if (!$errors) {
        try {
            $dsn = 'mysql:host=' . $form['db_host'] . ';charset=utf8mb4';
            $db = new PDO($dsn, $form['db_user'], $form['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $log[] = 'Connected to MySQL server at ' . $form['db_host'] . '.';

            $db->exec('CREATE DATABASE IF NOT EXISTS `' . $form['db_name'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $db->exec('USE `' . $form['db_name'] . '`');
            $log[] = 'Database `' . $form['db_name'] . '` is ready.';

            if ($form['fresh']) {
                $db->exec('SET FOREIGN_KEY_CHECKS = 0');
                $tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
                foreach ($tables as $table) {
                    $db->exec('DROP TABLE IF EXISTS `' . $table . '`');
                }
                $db->exec('SET FOREIGN_KEY_CHECKS = 1');
                if ($tables) {
                    $log[] = 'Dropped ' . count($tables) . ' existing table(s).';
                }
            }

            runSqlFile($db, __DIR__ . '/database/hospital.sql', $log);

            if ($form['demo']) {
                runSqlFile($db, __DIR__ . '/database/demo_data.sql', $log);
            }

            // Administrator account
            $hash = password_hash($adminPass, PASSWORD_DEFAULT);
            $stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->execute([$form['admin_user']]);
            $userId = $stmt->fetchColumn();
            if ($userId) {
                $stmt = $db->prepare('UPDATE users SET password = ?, full_name = ?, email = ? WHERE id = ?');
                $stmt->execute([$hash, $form['admin_name'], $form['admin_email'], $userId]);
            } else {
                $stmt = $db->prepare('INSERT INTO users (username, password, full_name, email) VALUES (?,?,?,?)');
                $stmt->execute([$form['admin_user'], $hash, $form['admin_name'], $form['admin_email']]);
            }
            // Remove the factory default account when a different one was created
            if ($form['admin_user'] !== 'admin') {
                $db->prepare('DELETE FROM users WHERE username = ?')->execute(['admin']);
            }
            $log[] = 'Administrator account "' . $form['admin_user'] . '" is ready.';

            $stmt = $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?,?)
                                  ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            $stmt->execute(['hospital_name', $form['hospital_name']]);

            // Write database credentials
            $configContent = "<?php\n"
                . "// Generated by install.php on " . date('Y-m-d H:i:s') . "\n"
                . "define('DB_HOST', " . var_export($form['db_host'], true) . ");\n"
                . "define('DB_USER', " . var_export($form['db_user'], true) . ");\n"
                . "define('DB_PASS', " . var_export($form['db_pass'], true) . ");\n"
                . "define('DB_NAME', " . var_export($form['db_name'], true) . ");\n";
            if (@file_put_contents(__DIR__ . '/config/db_config.php', $configContent) === false) {
                $errors[] = 'Could not write config/db_config.php. Please create it manually with your database credentials.';
            } else {
                $log[] = 'Saved database credentials to config/db_config.php.';
            }

            foreach ([UPLOADS_PATH . '/logos', UPLOADS_PATH . '/reports', BACKUP_PATH] as $dir) {
                if (!is_dir($dir)) {
                    @mkdir($dir, 0775, true);
                }
            }

            if (!$errors) {
                $success = true;
                $_SESSION['install_done'] = true;
            }
        } catch (Throwable $e) {
            $errors[] = 'Installation failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Install &middot; <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body class="install-page">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="text-center text-white mb-4">
                <div class="install-logo"><i class="bi bi-hospital"></i></div>
                <h1 class="h3 mt-3"><?= APP_NAME ?></h1>
                <p class="mb-0 opacity-75">Installation wizard &middot; version <?= APP_VERSION ?></p>
            </div>

            <?php if ($success): ?>
                <div class="card shadow-sm">
                    <div class="card-body p-4 text-center">
                        <div class="display-5 text-success mb-2"><i class="bi bi-check-circle-fill"></i></div>
                        <h2 class="h4">Installation completed</h2>
                        <p class="text-muted">Your Hospital Management System is ready to use.</p>
                        <ul class="list-group list-group-flush text-start my-4">
                            <?php foreach ($log as $line): ?>
                                <li class="list-group-item small"><i class="bi bi-check2 text-success me-2"></i><?= sanitize($line) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="alert alert-warning text-start">
                            <strong>Security tip:</strong> delete or rename <code>install.php</code> now that the installation is finished.
                        </div>
                        <a href="login.php" class="btn btn-primary btn-lg"><i class="bi bi-box-arrow-in-right me-2"></i>Go to login</a>
                    </div>
                </div>
            <?php else: ?>
                <?php if ($alreadyInstalled): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>An existing installation was detected in <code><?= sanitize(DB_NAME) ?></code>.
                        Running the wizard again will refresh the schema and administrator account.
                        <a href="login.php" class="alert-link ms-1">Go to login instead</a>.
                    </div>
                <?php endif; ?>
                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-danger"><i class="bi bi-exclamation-octagon me-2"></i><?= sanitize($error) ?></div>
                <?php endforeach; ?>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-list-check me-2"></i>Step 1 &middot; Server requirements</div>
                    <div class="card-body">
                        <div class="row g-2">
                            <?php foreach ($requirements as $label => $ok): ?>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-<?= $ok ? 'check-circle-fill text-success' : 'x-circle-fill text-danger' ?>"></i>
                                        <span><?= sanitize($label) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <form method="post" class="card shadow-sm">
                    <?= csrfField() ?>
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-database me-2"></i>Step 2 &middot; Database connection</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">MySQL host</label>
                                <input type="text" name="db_host" class="form-control" value="<?= sanitize($form['db_host']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Database name</label>
                                <input type="text" name="db_name" class="form-control" value="<?= sanitize($form['db_name']) ?>" required>
                                <div class="form-text">Created automatically if it does not exist.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">MySQL user</label>
                                <input type="text" name="db_user" class="form-control" value="<?= sanitize($form['db_user']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">MySQL password</label>
                                <input type="password" name="db_pass" class="form-control" value="<?= sanitize($form['db_pass']) ?>">
                                <div class="form-text">Usually empty on XAMPP / Laragon.</div>
                            </div>
                        </div>
                    </div>

                    <div class="card-header bg-white fw-semibold"><i class="bi bi-person-gear me-2"></i>Step 3 &middot; Administrator account</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Username</label>
                                <input type="text" name="admin_user" class="form-control" value="<?= sanitize($form['admin_user']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Full name</label>
                                <input type="text" name="admin_name" class="form-control" value="<?= sanitize($form['admin_name']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Password</label>
                                <input type="password" name="admin_pass" class="form-control" minlength="6" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Confirm password</label>
                                <input type="password" name="admin_pass2" class="form-control" minlength="6" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" name="admin_email" class="form-control" value="<?= sanitize($form['admin_email']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Hospital name</label>
                                <input type="text" name="hospital_name" class="form-control" value="<?= sanitize($form['hospital_name']) ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="card-header bg-white fw-semibold"><i class="bi bi-gear me-2"></i>Step 4 &middot; Options</div>
                    <div class="card-body">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="fresh" id="fresh" value="1" <?= $form['fresh'] || !$alreadyInstalled ? 'checked' : '' ?>>
                            <label class="form-check-label" for="fresh">Fresh install &mdash; drop existing tables first (all current data will be lost)</label>
                        </div>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="demo" id="demo" value="1" <?= $form['demo'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="demo">Import demo data (sample patients, doctors, orders, invoices)</label>
                        </div>
                    </div>

                    <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                        <span class="text-muted small">The default test catalogue and settings are always installed.</span>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-download me-2"></i>Install now</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
