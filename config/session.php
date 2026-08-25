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

$currentUser = [
    'id'        => $_SESSION['user_id'],
    'username'  => $_SESSION['username'] ?? '',
    'full_name' => $_SESSION['full_name'] ?? '',
];
