<?php
/** Destroy the session and return to the login screen. */
require_once __DIR__ . '/config/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('HMSSESSID');
    session_start();
}

if (!empty($_SESSION['username'])) {
    logActivity('Logout', 'User ' . $_SESSION['username'] . ' signed out', 'users', $_SESSION['user_id'] ?? null);
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

session_name('HMSSESSID');
session_start();
flash('success', 'You have been signed out successfully.');
redirect(BASE_URL . '/login.php');
