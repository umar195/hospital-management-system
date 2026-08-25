<?php
/** Administrator login. */
require_once __DIR__ . '/config/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('HMSSESSID');
    session_start();
}

if (!empty($_SESSION['user_id'])) {
    redirect(BASE_URL . '/dashboard.php');
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $pass     = (string)($_POST['pass'] ?? '');

    if (!verifyCsrf()) {
        $error = 'Security check failed. Please try again.';
    } elseif ($username === '' || $pass === '') {
        $error = 'Please enter both username and password.';
    } elseif (!empty($_SESSION['lockout_until']) && $_SESSION['lockout_until'] > time()) {
        $error = 'Too many failed attempts. Please wait ' . ($_SESSION['lockout_until'] - time()) . ' seconds.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($pass, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            unset($_SESSION['login_attempts'], $_SESSION['lockout_until']);
            logActivity('Login', 'User ' . $user['username'] . ' signed in', 'users', $user['id']);
            $target = $_SESSION['redirect_after_login'] ?? '';
            unset($_SESSION['redirect_after_login']);
            if ($target && strpos($target, 'login.php') === false && strpos($target, '//') !== 0) {
                header('Location: ' . $target);
                exit;
            }
            redirect(BASE_URL . '/dashboard.php');
        }

        $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
        if ($_SESSION['login_attempts'] >= 5) {
            $_SESSION['lockout_until'] = time() + 60;
            $_SESSION['login_attempts'] = 0;
        }
        $error = 'Invalid username or password.';
        logActivity('Failed login', 'Attempt for username ' . $username, 'users', null);
    }
}

$hospitalName = getSetting('hospital_name', 'City Care Hospital');
$logo = hospitalLogoUrl();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login &middot; <?= sanitize($hospitalName) ?></title>
    <link rel="stylesheet" href="<?= is_file(APP_PATH . '/assets/css/bootstrap.min.css') && filesize(APP_PATH . '/assets/css/bootstrap.min.css') > 1024 ? BASE_URL . '/assets/css/bootstrap.min.css' : 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' ?>">
    <link rel="stylesheet" href="<?= is_file(APP_PATH . '/assets/css/bootstrap-icons.css') && filesize(APP_PATH . '/assets/css/bootstrap-icons.css') > 1024 ? BASE_URL . '/assets/css/bootstrap-icons.css' : 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css' ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/custom.css?v=<?= APP_VERSION ?>">
</head>
<body class="login-page">
<div class="login-wrapper">
    <div class="login-card shadow-lg">
        <div class="login-brand">
            <?php if ($logo): ?>
                <img src="<?= sanitize($logo) ?>" alt="Logo">
            <?php else: ?>
                <div class="login-logo"><i class="bi bi-hospital"></i></div>
            <?php endif; ?>
            <h1 class="h4 mb-1"><?= sanitize($hospitalName) ?></h1>
            <p class="text-muted small mb-0"><?= sanitize(getSetting('hospital_tagline', 'Hospital & Diagnostic Center')) ?></p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><i class="bi bi-exclamation-octagon me-2"></i><?= sanitize($error) ?></div>
        <?php endif; ?>
        <?= renderFlash() ?>

        <form method="post" autocomplete="off">
            <?= csrfField() ?>
            <div class="mb-3">
                <label class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" name="username" class="form-control" value="<?= sanitize($username) ?>" autofocus required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" name="pass" id="loginPass" class="form-control" required>
                    <button class="btn btn-outline-secondary" type="button" id="togglePass" tabindex="-1"><i class="bi bi-eye"></i></button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2"><i class="bi bi-box-arrow-in-right me-2"></i>Sign in</button>
        </form>

        <div class="login-footer">
            <small class="text-muted">Default credentials: <code>admin</code> / <code>admin123</code></small>
            <div class="mt-2"><a href="<?= BASE_URL ?>/install.php" class="small text-decoration-none"><i class="bi bi-tools me-1"></i>Run installer</a></div>
        </div>
    </div>
    <p class="text-center text-white-50 small mt-3 mb-0"><?= APP_NAME ?> v<?= APP_VERSION ?></p>
</div>
<script src="<?= is_file(APP_PATH . '/assets/js/bootstrap.bundle.min.js') && filesize(APP_PATH . '/assets/js/bootstrap.bundle.min.js') > 1024 ? BASE_URL . '/assets/js/bootstrap.bundle.min.js' : 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js' ?>"></script>
<script>
document.getElementById('togglePass').addEventListener('click', function () {
    var input = document.getElementById('loginPass');
    var isText = input.type === 'text';
    input.type = isText ? 'password' : 'text';
    this.innerHTML = isText ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
});
</script>
</body>
</html>
