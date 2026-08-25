<?php
/** Entry point - send the visitor to the dashboard or the login page. */
if (!is_file(__DIR__ . '/config/config.php')) {
    header('Location: install.php');
    exit;
}
require_once __DIR__ . '/config/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('HMSSESSID');
    session_start();
}

if (!empty($_SESSION['user_id'])) {
    redirect(BASE_URL . '/dashboard.php');
}
redirect(BASE_URL . '/login.php');
