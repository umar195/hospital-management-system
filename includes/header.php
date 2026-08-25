<?php
/**
 * Common HTML head + top bar.
 *
 * Pages may define before including this file:
 *   $pageTitle    string  - browser + page heading
 *   $pageSubtitle string  - small text under the heading
 *   $pageActions  string  - HTML for buttons shown on the right of the heading
 *   $activeMenu   string  - sidebar highlight key
 */
if (!defined('APP_PATH')) {
    require_once dirname(__DIR__) . '/config/config.php';
}

$pageTitle    = $pageTitle ?? 'Dashboard';
$pageSubtitle = $pageSubtitle ?? '';
$pageActions  = $pageActions ?? '';
$activeMenu   = $activeMenu ?? '';
$hospitalName = getSetting('hospital_name', 'City Care Hospital');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= sanitize($hospitalName) ?> - <?= APP_NAME ?>">
    <title><?= sanitize($pageTitle) ?> &middot; <?= sanitize($hospitalName) ?></title>
    <!-- Bootstrap 5. For full offline use download bootstrap.min.css into assets/css/ (see README). -->
    <link rel="stylesheet" href="<?= asset_url('assets/css/bootstrap.min.css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('assets/css/bootstrap-icons.css', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css') ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/custom.css?v=<?= APP_VERSION ?>">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='6' fill='%230E7490'/><path d='M13 6h6v7h7v6h-7v7h-6v-7H6v-6h7z' fill='white'/></svg>">
    <script>window.BASE_URL = <?= json_encode(BASE_URL) ?>;</script>
</head>
<body>
<div class="app-wrapper">
    <?php require INC_PATH . '/sidebar.php'; ?>
    <div class="app-main">
        <header class="topbar no-print">
            <button class="btn btn-light btn-sm d-lg-none" type="button" id="sidebarToggle" aria-label="Toggle navigation">
                <i class="bi bi-list"></i>
            </button>
            <div class="topbar-title">
                <span class="fw-semibold"><?= sanitize($hospitalName) ?></span>
                <small class="text-muted d-none d-md-inline ms-2"><?= date('l, d M Y') ?></small>
            </div>
            <form class="topbar-search d-none d-md-block" action="<?= BASE_URL ?>/modules/patients/index.php" method="get" role="search">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                    <input type="search" name="q" class="form-control border-start-0" placeholder="Search patients by name, phone or ID">
                </div>
            </form>
            <div class="dropdown ms-auto">
                <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-circle me-1"></i><?= sanitize($_SESSION['full_name'] ?? 'Administrator') ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/modules/settings/index.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/modules/backup/index.php"><i class="bi bi-database me-2"></i>Backup</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>
        </header>
        <main class="app-content">
            <div class="page-head no-print">
                <div>
                    <h1 class="page-title"><?= sanitize($pageTitle) ?></h1>
                    <?php if ($pageSubtitle !== ''): ?>
                        <p class="page-subtitle"><?= sanitize($pageSubtitle) ?></p>
                    <?php endif; ?>
                </div>
                <?php if ($pageActions !== ''): ?>
                    <div class="page-actions"><?= $pageActions ?></div>
                <?php endif; ?>
            </div>
            <?= renderFlash() ?>
