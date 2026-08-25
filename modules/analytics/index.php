<?php
/** Analytics dashboard: revenue, expenses, workload and top tests. */
require_once __DIR__ . '/../../config/session.php';

$from = get('from', date('Y-m-01'));
$to   = get('to', date('Y-m-d'));
if (!strtotime($from)) {
    $from = date('Y-m-01');
}
if (!strtotime($to)) {
    $to = date('Y-m-d');
}
$range = [$from, $to];

$revenue      = (float)fetchValue('SELECT COALESCE(SUM(amount),0) FROM payments WHERE DATE(payment_date) BETWEEN ? AND ?', $range);
$billed       = (float)fetchValue('SELECT COALESCE(SUM(net_amount),0) FROM test_orders WHERE DATE(order_date) BETWEEN ? AND ?', $range);
$outstanding  = (float)fetchValue('SELECT COALESCE(SUM(remaining_amount),0) FROM test_orders WHERE DATE(order_date) BETWEEN ? AND ?', $range);
$expenses     = (float)fetchValue('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date BETWEEN ? AND ?', $range);
$newPatients  = (int)fetchValue('SELECT COUNT(*) FROM patients WHERE registration_date BETWEEN ? AND ?', $range);
$visits       = (int)fetchValue('SELECT COUNT(*) FROM visits WHERE DATE(visit_date) BETWEEN ? AND ?', $range);
$orders       = (int)fetchValue('SELECT COUNT(*) FROM test_orders WHERE DATE(order_date) BETWEEN ? AND ?', $range);
$testsDone    = (int)fetchValue('SELECT COUNT(*) FROM test_order_items i JOIN test_orders o ON o.id = i.order_id
                                 WHERE DATE(o.order_date) BETWEEN ? AND ?', $range);
$reportsMade  = (int)fetchValue('SELECT COUNT(*) FROM reports WHERE DATE(generated_at) BETWEEN ? AND ?', $range);
$profit       = $revenue - $expenses;

$dailyRevenue = fetchAll('SELECT DATE(payment_date) AS day, COALESCE(SUM(amount),0) AS total
    FROM payments WHERE DATE(payment_date) BETWEEN ? AND ? GROUP BY DATE(payment_date) ORDER BY day', $range);
$dailyExpense = fetchAll('SELECT expense_date AS day, COALESCE(SUM(amount),0) AS total
    FROM expenses WHERE expense_date BETWEEN ? AND ? GROUP BY expense_date ORDER BY day', $range);

$expenseMap = [];
foreach ($dailyExpense as $row) {
    $expenseMap[$row['day']] = (float)$row['total'];
}
$labels = [];
$revenueSeries = [];
$expenseSeries = [];
foreach ($dailyRevenue as $row) {
    $labels[$row['day']] = true;
}
foreach ($expenseMap as $day => $value) {
    $labels[$day] = true;
}
$labels = array_keys($labels);
sort($labels);
$revenueMap = [];
foreach ($dailyRevenue as $row) {
    $revenueMap[$row['day']] = (float)$row['total'];
}
foreach ($labels as $day) {
    $revenueSeries[] = round($revenueMap[$day] ?? 0, 2);
    $expenseSeries[] = round($expenseMap[$day] ?? 0, 2);
}

$topTests = fetchAll('SELECT t.name, COUNT(*) AS times, COALESCE(SUM(i.price),0) AS revenue
    FROM test_order_items i JOIN tests t ON t.id = i.test_id JOIN test_orders o ON o.id = i.order_id
    WHERE DATE(o.order_date) BETWEEN ? AND ? GROUP BY t.id, t.name ORDER BY times DESC LIMIT 10', $range);

$expenseByCategory = fetchAll('SELECT category, COALESCE(SUM(amount),0) AS total FROM expenses
    WHERE expense_date BETWEEN ? AND ? GROUP BY category ORDER BY total DESC', $range);

$genderSplit = fetchAll('SELECT gender, COUNT(*) AS total FROM patients GROUP BY gender');

$monthly = fetchAll('SELECT DATE_FORMAT(payment_date, "%Y-%m") AS month, COALESCE(SUM(amount),0) AS total
    FROM payments WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH) GROUP BY month ORDER BY month');

$doctorStats = fetchAll('SELECT COALESCE(NULLIF(o.referring_doctor, ""), "Not specified") AS doctor,
        COUNT(*) AS orders, COALESCE(SUM(o.net_amount),0) AS revenue
    FROM test_orders o WHERE DATE(o.order_date) BETWEEN ? AND ?
    GROUP BY doctor ORDER BY orders DESC LIMIT 8', $range);

$paymentMix = fetchAll('SELECT payment_method, COALESCE(SUM(amount),0) AS total FROM payments
    WHERE DATE(payment_date) BETWEEN ? AND ? GROUP BY payment_method', $range);

$useCharts = true;
$pageTitle = 'Analytics & Reports';
$pageSubtitle = formatDate($from) . ' - ' . formatDate($to);
$activeMenu = 'analytics';
$pageActions = '<button class="btn btn-outline-primary" data-print="1"><i class="bi bi-printer me-1"></i>Print</button>';
require_once INC_PATH . '/header.php';
?>

<div class="card mb-3 no-print">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-3">
                <label class="form-label">From</label>
                <input type="date" name="from" class="form-control" value="<?= sanitize($from) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">To</label>
                <input type="date" name="to" class="form-control" value="<?= sanitize($to) ?>">
            </div>
            <div class="col-md-6 d-flex flex-wrap gap-2">
                <button class="btn btn-primary btn-sm"><i class="bi bi-graph-up me-1"></i>Apply</button>
                <a class="btn btn-outline-secondary btn-sm" href="?from=<?= date('Y-m-d') ?>&to=<?= date('Y-m-d') ?>">Today</a>
                <a class="btn btn-outline-secondary btn-sm" href="?from=<?= date('Y-m-d', strtotime('-6 days')) ?>&to=<?= date('Y-m-d') ?>">Last 7 days</a>
                <a class="btn btn-outline-secondary btn-sm" href="?from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-t') ?>">This month</a>
                <a class="btn btn-outline-secondary btn-sm" href="?from=<?= date('Y-01-01') ?>&to=<?= date('Y-12-31') ?>">This year</a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card stat-card"><div class="card-body">
        <div class="text-muted small">Collections</div>
        <div class="h4 mb-0 text-success"><?= formatCurrency($revenue) ?></div>
        <small class="text-muted">Billed <?= formatCurrency($billed) ?></small>
    </div></div></div>
    <div class="col-md-3"><div class="card stat-card"><div class="card-body">
        <div class="text-muted small">Expenses</div>
        <div class="h4 mb-0 text-danger"><?= formatCurrency($expenses) ?></div>
        <small class="text-muted"><?= count($expenseByCategory) ?> categories</small>
    </div></div></div>
    <div class="col-md-3"><div class="card stat-card"><div class="card-body">
        <div class="text-muted small">Net profit</div>
        <div class="h4 mb-0 <?= $profit >= 0 ? 'text-primary' : 'text-danger' ?>"><?= formatCurrency($profit) ?></div>
        <small class="text-muted">Outstanding <?= formatCurrency($outstanding) ?></small>
    </div></div></div>
    <div class="col-md-3"><div class="card stat-card"><div class="card-body">
        <div class="text-muted small">Workload</div>
        <div class="h4 mb-0"><?= number_format($orders) ?> orders</div>
        <small class="text-muted"><?= number_format($testsDone) ?> tests · <?= number_format($reportsMade) ?> reports</small>
    </div></div></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card"><div class="card-body py-3">
        <div class="text-muted small">New patients</div><div class="h5 mb-0"><?= number_format($newPatients) ?></div>
    </div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body py-3">
        <div class="text-muted small">Visits</div><div class="h5 mb-0"><?= number_format($visits) ?></div>
    </div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body py-3">
        <div class="text-muted small">Avg. order value</div>
        <div class="h5 mb-0"><?= formatCurrency($orders > 0 ? $billed / $orders : 0) ?></div>
    </div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body py-3">
        <div class="text-muted small">Total patients</div>
        <div class="h5 mb-0"><?= number_format((int)fetchValue('SELECT COUNT(*) FROM patients')) ?></div>
    </div></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-graph-up-arrow me-2"></i>Income vs expenses</div>
            <div class="card-body"><canvas id="trendChart" height="110"></canvas></div>
        </div>
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-calendar3 me-2"></i>Monthly collections (12 months)</div>
            <div class="card-body"><canvas id="monthlyChart" height="110"></canvas></div>
        </div>
        <div class="card">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-trophy me-2"></i>Most requested tests</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Test</th><th>Times ordered</th><th class="text-end">Revenue</th></tr></thead>
                    <tbody>
                    <?php if (!$topTests): ?>
                        <tr><td colspan="3" class="text-muted small">No tests ordered in this period.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($topTests as $t): ?>
                        <tr>
                            <td class="small"><?= sanitize($t['name']) ?></td>
                            <td class="small"><?= (int)$t['times'] ?></td>
                            <td class="small text-end"><?= formatCurrency($t['revenue']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-pie-chart me-2"></i>Expenses by category</div>
            <div class="card-body"><canvas id="expenseChart" height="200"></canvas></div>
        </div>
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-people me-2"></i>Patients by gender</div>
            <div class="card-body"><canvas id="genderChart" height="200"></canvas></div>
        </div>
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-credit-card me-2"></i>Payment mix</div>
            <ul class="list-group list-group-flush">
                <?php if (!$paymentMix): ?>
                    <li class="list-group-item small text-muted">No payments in this period.</li>
                <?php endif; ?>
                <?php foreach ($paymentMix as $m): ?>
                    <li class="list-group-item d-flex justify-content-between small">
                        <span><?= sanitize($m['payment_method']) ?></span><strong><?= formatCurrency($m['total']) ?></strong>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="card">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-person-vcard me-2"></i>Referring doctors</div>
            <ul class="list-group list-group-flush">
                <?php if (!$doctorStats): ?>
                    <li class="list-group-item small text-muted">No referrals in this period.</li>
                <?php endif; ?>
                <?php foreach ($doctorStats as $d): ?>
                    <li class="list-group-item d-flex justify-content-between small">
                        <span><?= sanitize($d['doctor']) ?><br><span class="text-muted"><?= (int)$d['orders'] ?> order(s)</span></span>
                        <strong><?= formatCurrency($d['revenue']) ?></strong>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<?php
$chartData = json_encode([
    'labels'    => $labels,
    'revenue'   => $revenueSeries,
    'expenses'  => $expenseSeries,
    'months'    => array_column($monthly, 'month'),
    'monthly'   => array_map('floatval', array_column($monthly, 'total')),
    'expCats'   => array_column($expenseByCategory, 'category'),
    'expTotals' => array_map('floatval', array_column($expenseByCategory, 'total')),
    'genders'   => array_column($genderSplit, 'gender'),
    'genderNums' => array_map('intval', array_column($genderSplit, 'total')),
], JSON_UNESCAPED_SLASHES);

$pageScripts = <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') { return; }
    var data = {$chartData};
    var palette = ['#2563EB', '#16A34A', '#D97706', '#DC2626', '#7C3AED', '#0891B2', '#DB2777', '#65A30D'];

    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [
                { label: 'Collections', data: data.revenue, borderColor: '#16A34A', backgroundColor: 'rgba(22,163,74,.12)', fill: true, tension: .35 },
                { label: 'Expenses', data: data.expenses, borderColor: '#DC2626', backgroundColor: 'rgba(220,38,38,.10)', fill: true, tension: .35 }
            ]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });

    new Chart(document.getElementById('monthlyChart'), {
        type: 'bar',
        data: { labels: data.months, datasets: [{ label: 'Collections', data: data.monthly, backgroundColor: '#2563EB' }] },
        options: { responsive: true, plugins: { legend: { display: false } } }
    });

    if (data.expCats.length) {
        new Chart(document.getElementById('expenseChart'), {
            type: 'doughnut',
            data: { labels: data.expCats, datasets: [{ data: data.expTotals, backgroundColor: palette }] },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });
    }

    if (data.genders.length) {
        new Chart(document.getElementById('genderChart'), {
            type: 'pie',
            data: { labels: data.genders, datasets: [{ data: data.genderNums, backgroundColor: palette }] },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });
    }
});
</script>
HTML;
require_once INC_PATH . '/footer.php';
