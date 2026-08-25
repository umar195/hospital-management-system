<?php
/** Sidebar navigation. $activeMenu decides the highlighted entry. */
$activeMenu = $activeMenu ?? '';
$logo = hospitalLogoUrl();

$nav = [
    ['label' => 'Main', 'items' => [
        ['key' => 'dashboard', 'url' => '/dashboard.php', 'icon' => 'speedometer2', 'text' => 'Dashboard'],
        ['key' => 'walkin', 'url' => '/modules/walkin/index.php', 'icon' => 'lightning-charge', 'text' => 'Walk-In Registration'],
        ['key' => 'queue', 'url' => '/modules/queue/index.php', 'icon' => 'people-fill', 'text' => 'Doctor Queue'],
    ]],
    ['label' => 'Patient Care', 'items' => [
        ['key' => 'patients', 'url' => '/modules/patients/index.php', 'icon' => 'people', 'text' => 'Patients'],
        ['key' => 'appointments', 'url' => '/modules/appointments/index.php', 'icon' => 'calendar-check', 'text' => 'Appointments'],
        ['key' => 'consultations', 'url' => '/modules/consultations/index.php', 'icon' => 'clipboard2-heart', 'text' => 'Consultations'],
        ['key' => 'followups', 'url' => '/modules/followups/index.php', 'icon' => 'arrow-repeat', 'text' => 'Follow-Ups'],
        ['key' => 'doctors', 'url' => '/modules/doctors/index.php', 'icon' => 'person-badge', 'text' => 'Doctors'],
        ['key' => 'prescriptions', 'url' => '/modules/prescriptions/index.php', 'icon' => 'capsule', 'text' => 'Prescriptions'],
    ]],
    ['label' => 'Laboratory', 'items' => [
        ['key' => 'orders', 'url' => '/modules/orders/index.php', 'icon' => 'clipboard2-pulse', 'text' => 'Test Orders'],
        ['key' => 'reports', 'url' => '/modules/reports/index.php', 'icon' => 'file-earmark-medical', 'text' => 'Lab Reports'],
        ['key' => 'tests', 'url' => '/modules/tests/index.php', 'icon' => 'droplet-half', 'text' => 'Tests'],
        ['key' => 'categories', 'url' => '/modules/tests/categories.php', 'icon' => 'diagram-3', 'text' => 'Test Categories'],
    ]],
    ['label' => 'Finance', 'items' => [
        ['key' => 'invoices', 'url' => '/modules/billing/invoices.php', 'icon' => 'receipt', 'text' => 'Invoices'],
        ['key' => 'payments', 'url' => '/modules/billing/payments.php', 'icon' => 'cash-coin', 'text' => 'Payments'],
        ['key' => 'expenses', 'url' => '/modules/expenses/index.php', 'icon' => 'wallet2', 'text' => 'Expenses'],
    ]],
    ['label' => 'Store', 'items' => [
        ['key' => 'inventory', 'url' => '/modules/inventory/index.php', 'icon' => 'box-seam', 'text' => 'Inventory'],
        ['key' => 'suppliers', 'url' => '/modules/suppliers/index.php', 'icon' => 'truck', 'text' => 'Suppliers'],
    ]],
    ['label' => 'System', 'items' => [
        ['key' => 'analytics', 'url' => '/modules/analytics/index.php', 'icon' => 'graph-up', 'text' => 'Analytics'],
        ['key' => 'backup', 'url' => '/modules/backup/index.php', 'icon' => 'database-check', 'text' => 'Backup & Restore'],
        ['key' => 'settings', 'url' => '/modules/settings/index.php', 'icon' => 'gear', 'text' => 'Settings'],
    ]],
];
?>
<aside class="sidebar no-print" id="appSidebar">
    <div class="sidebar-brand">
        <?php if ($logo): ?>
            <img src="<?= sanitize($logo) ?>" alt="Logo" class="sidebar-logo">
        <?php else: ?>
            <span class="sidebar-logo-placeholder"><i class="bi bi-hospital"></i></span>
        <?php endif; ?>
        <div class="sidebar-brand-text">
            <span class="brand-name"><?= sanitize(getSetting('hospital_name', 'City Care Hospital')) ?></span>
            <small><?= sanitize(getSetting('hospital_tagline', 'Hospital &amp; Diagnostic Center')) ?></small>
        </div>
    </div>
    <nav class="sidebar-nav">
        <?php foreach ($nav as $group): ?>
            <div class="nav-group-label"><?= sanitize($group['label']) ?></div>
            <ul class="nav flex-column">
                <?php foreach ($group['items'] as $item): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $activeMenu === $item['key'] ? 'active' : '' ?>" href="<?= BASE_URL . $item['url'] ?>">
                            <i class="bi bi-<?= $item['icon'] ?>"></i>
                            <span><?= sanitize($item['text']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
        <ul class="nav flex-column mt-2 mb-4">
            <li class="nav-item">
                <a class="nav-link text-danger-emphasis" href="<?= BASE_URL ?>/logout.php">
                    <i class="bi bi-box-arrow-right"></i><span>Logout</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>
<div class="sidebar-backdrop no-print" id="sidebarBackdrop"></div>
