<?php
/**
 * Hospital Management System - Core configuration
 *
 * Database credentials may be overridden by config/db_config.php which is
 * generated automatically by install.php. Edit that file (or the defaults
 * below) if your MySQL user/password is different.
 */

// ---------------------------------------------------------------------------
// Paths
// ---------------------------------------------------------------------------
define('APP_PATH', dirname(__DIR__));
define('INC_PATH', APP_PATH . '/includes');
define('MODULES_PATH', APP_PATH . '/modules');
define('UPLOADS_PATH', APP_PATH . '/uploads');
define('BACKUP_PATH', UPLOADS_PATH . '/backups');

// ---------------------------------------------------------------------------
// Database credentials
// ---------------------------------------------------------------------------
if (is_file(__DIR__ . '/db_config.php')) {
    require_once __DIR__ . '/db_config.php';
}
if (!defined('DB_HOST')) { define('DB_HOST', 'localhost'); }
if (!defined('DB_USER')) { define('DB_USER', 'root'); }
if (!defined('DB_PASS')) { define('DB_PASS', ''); }
if (!defined('DB_NAME')) { define('DB_NAME', 'hospital_db'); }

define('APP_NAME', 'Hospital Management System');
define('APP_VERSION', '2.0.0');
define('SCHEMA_VERSION', 2);

// ---------------------------------------------------------------------------
// Base URL (auto detected so the app works from any folder in htdocs)
// ---------------------------------------------------------------------------
if (!defined('BASE_URL')) {
    $hmsScheme = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
    $hmsHost   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $hmsDocRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $hmsAppReal = realpath(APP_PATH);
    $hmsRel = '';
    if ($hmsDocRoot && $hmsAppReal) {
        $hmsDocRoot = rtrim(str_replace('\\', '/', $hmsDocRoot), '/');
        $hmsAppReal = rtrim(str_replace('\\', '/', $hmsAppReal), '/');
        if ($hmsDocRoot !== '' && strpos($hmsAppReal, $hmsDocRoot) === 0) {
            $hmsRel = substr($hmsAppReal, strlen($hmsDocRoot));
        }
    }
    define('BASE_URL', $hmsScheme . '://' . $hmsHost . rtrim($hmsRel, '/'));
}

// ---------------------------------------------------------------------------
// Defaults
// ---------------------------------------------------------------------------
date_default_timezone_set('Asia/Karachi');
mb_internal_encoding('UTF-8');

// ---------------------------------------------------------------------------
// Database connection
// ---------------------------------------------------------------------------
$pdo = null;
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    if (!defined('INSTALL_MODE')) {
        header('Location: ' . BASE_URL . '/install.php');
        exit;
    }
    $pdo = null;
    $GLOBALS['db_error'] = $e->getMessage();
}

require_once INC_PATH . '/functions.php';
