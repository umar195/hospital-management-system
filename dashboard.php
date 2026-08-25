<?php
/** Main dashboard with KPIs, quick actions and recent activity. */
require_once __DIR__ . '/config/session.php';

$today = date('Y-m-d');

$stats = [
    'today_patients'    => (int)fetchValue('SELECT COUNT(*) FROM patients WHERE registration_date = ?', [$today]),
    'total_patients'    => (int)fetchValue('SELECT COUNT(*) FROM patients'),
    'today_walkins'     => (int)fetchValue("SELECT COUNT(*) FROM visits WHERE DATE(visit_date) = ? AND visit_type = 'Walk-In'", [$today]),
    'today_appointments'=> (int)fetchValue('SELECT COUNT(*) FROM appointments WHERE appointment_date = ?', [$today]),
    'today_orders'      => (int)fetchValue('SELECT COUNT(*) FROM test_orders WHERE DATE(order_date) = ?', [$today]),
    'today_tests'       => (int)fetchValue('SELECT COUNT(*) FROM test_order_items i JOIN test_orders o ON o.id = i.order_id WHERE DATE(o.order_date) = ?', [$today]),
    'pending_tests'     => (int)fetchValue("SELECT COUNT(*) FROM test_order_items WHERE status NOT IN ('Completed','Delivered')"),
    'completed_reports' => (int)fetchValue('SELECT COUNT(*) FROM reports'),
    'today_revenue'     => (float)fetchValue('SELECT COALESCE(SUM(amount),0) FROM payments WHERE DATE(payment_date) = ?', [$today]),
    'month_revenue'     => (float)fetchValue('SELECT COALESCE(SUM(amount),0) FROM payments WHERE YEAR(payment_date)=YEAR(CURDATE()) AND MONTH(payment_date)=MONTH(CURDATE())'),
    'pending_payments'  => (float)fetchValue('SELECT COALESCE(SUM(remaining_amount),0) FROM test_orders'),
    'month_expenses'    => (float)fetchValue('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE YEAR(expense_date)=YEAR(CURDATE()) AND MONTH(expense_date)=MONTH(CURDATE())'),
];

$recentPatients = fetchAll('SELECT id, patient_id, full_name, gender, age, phone, registration_date
                            FROM patients ORDER BY id DESC LIMIT 8');

$recentOrders = fetchAll('SELECT o.id, o.order_number, o.order_date, o.net_amount, o.remaining_amount,
                                 p.full_name, p.patient_id,
                                 (SELECT COUNT(*) FROM test_order_items i WHERE i.order_id = o.id) AS item_count,
                                 (SELECT COUNT(*) FROM test_order_items i WHERE i.order_id = o.id AND i.status IN ("Completed","Delivered")) AS done_count
                          FROM test_orders o JOIN patients p ON p.id = o.patient_id
                          ORDER BY o.id DESC LIMIT 8');

$upcomingAppointments = fetchAll('SELECT a.*, p.full_name, p.patient_id, d.name AS doctor_name
                                  FROM appointments a
                                  JOIN patients p ON p.id = a.patient_id
                                  LEFT JOIN doctors d ON d.id = a.doctor_id
                                  WHERE a.appointment_date >= CURDATE() AND a.status IN ("Scheduled","Arrived")
                                  ORDER BY a.appointment_date, a.appointment_time LIMIT 6');

$lowStock = fetchAll('SELECT id, item_code, name, quantity, minimum_stock FROM inventory_items
                      WHERE quantity <= minimum_stock ORDER BY quantity ASC LIMIT 5');

// Revenue for the last 7 days (chart)
$chartLabels = [];
$chartRevenue = [];
$chartOrders = [];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i day"));
    $chartLabels[] = date('D d', strtotime($day));
    $chartRevenue[] = (float)fetchValue('SELECT COALESCE(SUM(amount),0) FROM payments WHERE DATE(payment_date) = ?', [$day]);
    $chartOrders[] = (int)fetchValue('SELECT COUNT(*) FROM test_orders WHERE DATE(order_date) = ?', [$day]);
}

$pageTitle = 'Dashboard';
$pageSubtitle = 'Overview for ' . date('d M Y');
$activeMenu = 'dashboard';
$useCharts = true;
$pageActions = '<a href="' . BASE_URL . '/modules/walkin/index.php" class="btn btn-primary"><i class="bi bi-lightning-charge me-1"></i>New Walk-In</a> '
    . '<a href="' . BASE_URL . '/modules/patients/add.php" class="btn btn-outline-primary"><i class="bi bi-person-plus me-1"></i>Register Patient</a>';

require_once INC_PATH . '/header.php';

$cards = [
    ['label' => "Today's Patients", 'value' => $stats['today_patients'], 'icon' => 'person-plus', 'color' => 'primary', 'link' => '/modules/patients/index.php'],
    ['label' => "Today's Walk-Ins", 'value' => $stats['today_walkins'], 'icon' => 'lightning-charge', 'color' => 'info', 'link' => '/modules/walkin/index.php'],
    ['label' => "Today's Appointments", 'value' => $stats['today_appointments'], 'icon' => 'calendar-check', 'color' => 'warning', 'link' => '/modules/appointments/index.php'],
    ['label' => "Today's Lab Tests", 'value' => $stats['today_tests'], 'icon' => 'clipboard2-pulse', 'color' => 'success', 'link' => '/modules/orders/index.php'],
];
$cards2 = [
    ['label' => 'Pending Tests', 'value' => $stats['pending_tests'], 'icon' => 'hourglass-split', 'color' => 'danger', 'link' => '/modules/orders/index.php?status=pending'],
    ['label' => 'Reports Generated', 'value' => $stats['completed_reports'], 'icon' => 'file-earmark-medical', 'color' => 'success', 'link' => '/modules/reports/index.php'],
    ['label' => 'Total Patients', 'value' => $stats['total_patients'], 'icon' => 'people', 'color' => 'primary', 'link' => '/modules/patients/index.php'],
    ['label' => 'Pending Dues', 'value' => formatCurrency($stats['pending_payments']), 'icon' => 'exclamation-circle', 'color' => 'warning', 'link' => '/modules/billing/invoices.php?status=Pending'],
];
?>

<div class="row g-3">
    <?php foreach ($cards as $card): ?>
        <div class="col-6 col-xl-3">
            <a class="stat-card stat-<?= $card['color'] ?>" href="<?= BASE_URL . $card['link'] ?>">
                <div class="stat-icon"><i class="bi bi-<?= $card['icon'] ?>"></i></div>
                <div>
                    <div class="stat-value"><?= is_numeric($card['value']) ? number_format($card['value']) : $card['value'] ?></div>
                    <div class="stat-label"><?= sanitize($card['label']) ?></div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mt-1">
    <div class="col-md-4">
        <div class="card revenue-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Today's Collection</div>
                        <div class="h3 mb-0 text-success"><?= formatCurrency($stats['today_revenue']) ?></div>
                    </div>
                    <span class="badge bg-success-subtle text-success"><i class="bi bi-cash-coin"></i></span>
                </div>
                <hr>
                <div class="d-flex justify-content-between small">
                    <span class="text-muted">This month</span>
                    <strong><?= formatCurrency($stats['month_revenue']) ?></strong>
                </div>
                <div class="d-flex justify-content-between small mt-1">
                    <span class="text-muted">Month expenses</span>
                    <strong class="text-danger"><?= formatCurrency($stats['month_expenses']) ?></strong>
                </div>
                <div class="d-flex justify-content-between small mt-1">
                    <span class="text-muted">Net this month</span>
                    <strong class="<?= ($stats['month_revenue'] - $stats['month_expenses']) >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= formatCurrency($stats['month_revenue'] - $stats['month_expenses']) ?>
                    </strong>
                </div>
                <a href="<?= BASE_URL ?>/modules/analytics/index.php" class="btn btn-sm btn-outline-primary w-100 mt-3">
                    <i class="bi bi-graph-up me-1"></i>View analytics
                </a>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-bar-chart me-2"></i>Last 7 days</span>
                <small class="text-muted">Collections &amp; test orders</small>
            </div>
            <div class="card-body">
                <canvas id="weekChart" height="110"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <?php foreach ($cards2 as $card): ?>
        <div class="col-6 col-xl-3">
            <a class="stat-card stat-outline stat-<?= $card['color'] ?>" href="<?= BASE_URL . $card['link'] ?>">
                <div class="stat-icon"><i class="bi bi-<?= $card['icon'] ?>"></i></div>
                <div>
                    <div class="stat-value"><?= is_numeric($card['value']) ? number_format($card['value']) : $card['value'] ?></div>
                    <div class="stat-label"><?= sanitize($card['label']) ?></div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="card mt-3">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-grid-3x3-gap me-2"></i>Quick Actions</div>
    <div class="card-body">
        <div class="row g-2 quick-actions">
            <?php
            $actions = [
                ['Walk-In Patient', 'lightning-charge', '/modules/walkin/index.php', 'primary'],
                ['New Patient', 'person-plus', '/modules/patients/add.php', 'success'],
                ['New Test Order', 'clipboard2-plus', '/modules/orders/add.php', 'info'],
                ['Enter Results', 'pencil-square', '/modules/orders/index.php?status=pending', 'warning'],
                ['New Appointment', 'calendar-plus', '/modules/appointments/add.php', 'secondary'],
                ['Add Payment', 'cash-coin', '/modules/billing/add_payment.php', 'success'],
                ['Add Expense', 'wallet2', '/modules/expenses/add.php', 'danger'],
                ['Prescription', 'capsule', '/modules/prescriptions/add.php', 'primary'],
            ];
            foreach ($actions as [$label, $icon, $url, $color]): ?>
                <div class="col-6 col-md-3">
                    <a href="<?= BASE_URL . $url ?>" class="quick-action btn btn-outline-<?= $color ?> w-100">
                        <i class="bi bi-<?= $icon ?>"></i>
                        <span><?= sanitize($label) ?></span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-people me-2"></i>Recent Patients</span>
                <a href="<?= BASE_URL ?>/modules/patients/index.php" class="btn btn-sm btn-link">View all</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead><tr><th>ID</th><th>Name</th><th>Gender/Age</th><th>Phone</th><th></th></tr></thead>
                    <tbody>
                    <?php if (!$recentPatients): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No patients registered yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($recentPatients as $p): ?>
                        <tr>
                            <td><span class="badge bg-light text-dark"><?= sanitize($p['patient_id']) ?></span></td>
                            <td><?= sanitize($p['full_name']) ?></td>
                            <td class="small text-muted"><?= sanitize($p['gender']) ?><?= $p['age'] !== null ? ' / ' . (int)$p['age'] . 'y' : '' ?></td>
                            <td class="small"><?= sanitize($p['phone']) ?></td>
                            <td class="text-end">
                                <a href="<?= BASE_URL ?>/modules/patients/view.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-primary py-0"><i class="bi bi-eye"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-clipboard2-pulse me-2"></i>Recent Test Orders</span>
                <a href="<?= BASE_URL ?>/modules/orders/index.php" class="btn btn-sm btn-link">View all</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead><tr><th>Order #</th><th>Patient</th><th>Tests</th><th>Amount</th><th></th></tr></thead>
                    <tbody>
                    <?php if (!$recentOrders): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No test orders yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($recentOrders as $o): ?>
                        <tr>
                            <td class="small"><?= sanitize($o['order_number']) ?></td>
                            <td class="small"><?= sanitize($o['full_name']) ?></td>
                            <td class="small"><?= (int)$o['done_count'] ?>/<?= (int)$o['item_count'] ?> done</td>
                            <td class="small"><?= formatCurrency($o['net_amount']) ?></td>
                            <td class="text-end">
                                <a href="<?= BASE_URL ?>/modules/orders/view.php?id=<?= (int)$o['id'] ?>" class="btn btn-sm btn-outline-primary py-0"><i class="bi bi-eye"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1 mb-2">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-calendar-check me-2"></i>Upcoming Appointments</span>
                <a href="<?= BASE_URL ?>/modules/appointments/index.php" class="btn btn-sm btn-link">View all</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Date</th><th>Time</th><th>Patient</th><th>Doctor</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if (!$upcomingAppointments): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No upcoming appointments.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($upcomingAppointments as $a): ?>
                        <tr>
                            <td class="small"><?= formatDate($a['appointment_date']) ?></td>
                            <td class="small"><?= formatTime($a['appointment_time']) ?></td>
                            <td class="small"><?= sanitize($a['full_name']) ?></td>
                            <td class="small"><?= sanitize($a['doctor_name'] ?: '-') ?></td>
                            <td><?= statusBadge($a['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-exclamation-triangle me-2"></i>Low Stock Alerts</span>
                <a href="<?= BASE_URL ?>/modules/inventory/index.php" class="btn btn-sm btn-link">Inventory</a>
            </div>
            <ul class="list-group list-group-flush">
                <?php if (!$lowStock): ?>
                    <li class="list-group-item text-center text-muted py-4">All stock levels are healthy.</li>
                <?php endif; ?>
                <?php foreach ($lowStock as $item): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold small"><?= sanitize($item['name']) ?></div>
                            <small class="text-muted"><?= sanitize($item['item_code']) ?></small>
                        </div>
                        <span class="badge bg-danger"><?= (int)$item['quantity'] ?> / min <?= (int)$item['minimum_stock'] ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<?php
$pageScripts = '<script>
document.addEventListener("DOMContentLoaded", function () {
    var ctx = document.getElementById("weekChart");
    if (!ctx || typeof Chart === "undefined") { return; }
    new Chart(ctx, {
        type: "bar",
        data: {
            labels: ' . json_encode($chartLabels) . ',
            datasets: [
                { label: "Collection", data: ' . json_encode($chartRevenue) . ', backgroundColor: "#2563EB", borderRadius: 4, yAxisID: "y" },
                { label: "Orders", type: "line", data: ' . json_encode($chartOrders) . ', borderColor: "#16A34A", backgroundColor: "#16A34A", tension: .35, yAxisID: "y1" }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: "bottom" } },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } },
                y1: { beginAtZero: true, position: "right", grid: { drawOnChartArea: false }, ticks: { precision: 0 } }
            }
        }
    });
});
</script>';
require_once INC_PATH . '/footer.php';
