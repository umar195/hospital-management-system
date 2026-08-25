<?php
/**
 * Session bootstrap + authentication guard.
 * Every protected page starts with:  require_once __DIR__ . '/../../config/session.php';
 */
require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('HMSSESSID');
    session_start();
}

if (empty($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
    redirect(BASE_URL . '/login.php');
}

// Redirect to the one-click upgrade page when the database schema is older
// than this release expects (existing data is always preserved).
if (!defined('SKIP_UPGRADE_CHECK') && $pdo && (int)getSetting('schema_version', 1) < SCHEMA_VERSION) {
    redirect(BASE_URL . '/upgrade.php');
}

$currentUser = [
    'id'        => $_SESSION['user_id'],
    'username'  => $_SESSION['username'] ?? '',
    'full_name' => $_SESSION['full_name'] ?? '',
];
