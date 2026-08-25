<?php
/** Laboratory test orders. */
require_once __DIR__ . '/../../config/session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(BASE_URL . '/modules/orders/index.php');
    $id = (int)post('id');
    if (post('action') === 'delete' && $id) {
        $order = fetchOne('SELECT order_number FROM test_orders WHERE id = ?', [$id]);
        $pdo->prepare('DELETE FROM test_orders WHERE id = ?')->execute([$id]);
        logActivity('Order deleted', $order['order_number'] ?? ('#' . $id), 'test_orders', $id);
        flash('success', 'Order deleted along with its items and results.');
    }
    redirect(BASE_URL . '/modules/orders/index.php');
}

$search  = get('q');
$status  = get('status');
$from    = get('from');
$to      = get('to');
$payment = get('payment');
$page    = currentPage();
$offset  = ($page - 1) * PER_PAGE;

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(o.order_number LIKE ? OR p.full_name LIKE ? OR p.patient_id LIKE ? OR p.phone LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($from !== '') {
    $where[] = 'DATE(o.order_date) >= ?';
    $params[] = $from;
}
if ($to !== '') {
    $where[] = 'DATE(o.order_date) <= ?';
    $params[] = $to;
}
if ($payment === 'paid') {
    $where[] = 'o.remaining_amount <= 0';
} elseif ($payment === 'due') {
    $where[] = 'o.remaining_amount > 0';
}
if ($status === 'pending') {
    $where[] = 'EXISTS (SELECT 1 FROM test_order_items i WHERE i.order_id = o.id AND i.status NOT IN ("Completed","Delivered"))';
} elseif ($status === 'completed') {
    $where[] = 'NOT EXISTS (SELECT 1 FROM test_order_items i WHERE i.order_id = o.id AND i.status NOT IN ("Completed","Delivered"))';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int)fetchValue("SELECT COUNT(*) FROM test_orders o JOIN patients p ON p.id = o.patient_id $whereSql", $params);

$orders = fetchAll("SELECT o.*, p.full_name, p.patient_id AS patient_code, p.phone, p.gender,
        (SELECT COUNT(*) FROM test_order_items i WHERE i.order_id = o.id) AS item_count,
        (SELECT COUNT(*) FROM test_order_items i WHERE i.order_id = o.id AND i.status IN ('Completed','Delivered')) AS done_count,
        (SELECT r.id FROM reports r WHERE r.order_id = o.id ORDER BY r.id DESC LIMIT 1) AS report_id
    FROM test_orders o JOIN patients p ON p.id = o.patient_id
    $whereSql ORDER BY o.order_date DESC, o.id DESC LIMIT " . (int)PER_PAGE . ' OFFSET ' . (int)$offset, $params);

$sumStats = fetchOne("SELECT COALESCE(SUM(o.net_amount),0) AS net, COALESCE(SUM(o.paid_amount),0) AS paid,
        COALESCE(SUM(o.remaining_amount),0) AS due
    FROM test_orders o JOIN patients p ON p.id = o.patient_id $whereSql", $params);

$pageTitle = 'Test Orders';
$pageSubtitle = number_format($total) . ' order(s) found';
$activeMenu = 'orders';
$pageActions = '<a href="' . BASE_URL . '/modules/orders/add.php" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>New Order</a> '
    . '<a href="' . BASE_URL . '/modules/walkin/index.php" class="btn btn-outline-primary"><i class="bi bi-person-walking me-1"></i>Walk-In</a>';
require_once INC_PATH . '/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="search" name="q" class="form-control" value="<?= sanitize($search) ?>" placeholder="Order #, patient, phone">
            </div>
            <div class="col-md-2">
                <label class="form-label">From</label>
                <input type="date" name="from" class="form-control" value="<?= sanitize($from) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">To</label>
                <input type="date" name="to" class="form-control" value="<?= sanitize($to) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Lab status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending results</option>
                    <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Payment</label>
                <select name="payment" class="form-select">
                    <option value="">All</option>
                    <option value="paid" <?= $payment === 'paid' ? 'selected' : '' ?>>Fully paid</option>
                    <option value="due" <?= $payment === 'due' ? 'selected' : '' ?>>With balance</option>
                </select>
            </div>
            <div class="col-md-1 d-grid">
                <button class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card"><div class="card-body py-3">
        <div class="text-muted small">Billed</div><div class="h5 mb-0"><?= formatCurrency($sumStats['net'] ?? 0) ?></div>
    </div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body py-3">
        <div class="text-muted small">Collected</div><div class="h5 mb-0 text-success"><?= formatCurrency($sumStats['paid'] ?? 0) ?></div>
    </div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body py-3">
        <div class="text-muted small">Outstanding</div><div class="h5 mb-0 text-danger"><?= formatCurrency($sumStats['due'] ?? 0) ?></div>
    </div></div></div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Order #</th><th>Patient</th><th>Date</th><th>Tests</th><th>Net</th><th>Paid</th><th>Balance</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php if (!$orders): ?>
                <tr><td colspan="9"><div class="empty-state"><i class="bi bi-clipboard2-pulse"></i>No test orders found.
                    <div class="mt-2"><a href="add.php" class="btn btn-sm btn-primary">Create an order</a></div></div></td></tr>
            <?php endif; ?>
            <?php foreach ($orders as $o):
                $done = (int)$o['done_count'] === (int)$o['item_count'] && (int)$o['item_count'] > 0; ?>
                <tr>
                    <td><a href="view.php?id=<?= (int)$o['id'] ?>" class="fw-semibold text-decoration-none"><?= sanitize($o['order_number']) ?></a></td>
                    <td>
                        <div class="fw-semibold"><?= sanitize($o['full_name']) ?></div>
                        <small class="text-muted"><?= sanitize($o['patient_code']) ?><?= $o['phone'] ? ' · ' . sanitize($o['phone']) : '' ?></small>
                    </td>
                    <td class="small"><?= formatDateTime($o['order_date']) ?></td>
                    <td class="small"><?= (int)$o['done_count'] ?>/<?= (int)$o['item_count'] ?></td>
                    <td class="small"><?= formatCurrency($o['net_amount']) ?></td>
                    <td class="small text-success"><?= formatCurrency($o['paid_amount']) ?></td>
                    <td class="small <?= $o['remaining_amount'] > 0 ? 'text-danger fw-semibold' : '' ?>"><?= formatCurrency($o['remaining_amount']) ?></td>
                    <td><?= $done ? '<span class="badge bg-success">Completed</span>' : '<span class="badge bg-warning text-dark">In progress</span>' ?></td>
                    <td class="text-end text-nowrap">
                        <a href="view.php?id=<?= (int)$o['id'] ?>" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                        <a href="results.php?id=<?= (int)$o['id'] ?>" class="btn btn-sm btn-outline-primary" title="Enter results"><i class="bi bi-pencil-square"></i></a>
                        <?php if ($o['report_id']): ?>
                            <a href="<?= BASE_URL ?>/modules/reports/print.php?id=<?= (int)$o['report_id'] ?>" target="_blank" class="btn btn-sm btn-outline-success" title="Print report"><i class="bi bi-printer"></i></a>
                        <?php endif; ?>
                        <form method="post" class="d-inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" title="Delete"
                                    data-confirm="Delete order <?= sanitize($o['order_number']) ?> and all its results?"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total > PER_PAGE): ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
            <small class="text-muted">Page <?= $page ?> of <?= (int)ceil($total / PER_PAGE) ?></small>
            <?= paginationLinks($total) ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once INC_PATH . '/footer.php'; ?>
