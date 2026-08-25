<?php
/** Payment ledger. */
require_once __DIR__ . '/../../config/session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(BASE_URL . '/modules/billing/payments.php');
    $id = (int)post('id');
    if (post('action') === 'delete' && $id) {
        $payment = fetchOne('SELECT * FROM payments WHERE id = ?', [$id]);
        if ($payment) {
            $pdo->prepare('DELETE FROM payments WHERE id = ?')->execute([$id]);
            if ($payment['invoice_id']) {
                recalcInvoiceTotals((int)$payment['invoice_id']);
            }
            if ($payment['order_id']) {
                $paid = (float)fetchValue('SELECT COALESCE(SUM(amount),0) FROM payments WHERE order_id = ?', [$payment['order_id']]);
                $net = (float)fetchValue('SELECT net_amount FROM test_orders WHERE id = ?', [$payment['order_id']]);
                $pdo->prepare('UPDATE test_orders SET paid_amount = ?, remaining_amount = ? WHERE id = ?')
                    ->execute([$paid, max(0, round($net - $paid, 2)), $payment['order_id']]);
            }
            logActivity('Payment deleted', formatCurrency($payment['amount']), 'payments', $id);
            flash('success', 'Payment removed and balances updated.');
        }
    }
    redirect(BASE_URL . '/modules/billing/payments.php');
}

$search = get('q');
$method = get('method');
$from   = get('from', date('Y-m-01'));
$to     = get('to', date('Y-m-d'));
$page   = currentPage();
$offset = ($page - 1) * PER_PAGE;

$where = ['DATE(pay.payment_date) BETWEEN ? AND ?'];
$params = [$from, $to];
if ($search !== '') {
    $where[] = '(p.full_name LIKE ? OR p.patient_id LIKE ? OR i.invoice_number LIKE ? OR o.order_number LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}
if (in_array($method, ['Cash', 'Card', 'Bank Transfer', 'Other'], true)) {
    $where[] = 'pay.payment_method = ?';
    $params[] = $method;
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$baseJoin = 'FROM payments pay
    JOIN patients p ON p.id = pay.patient_id
    LEFT JOIN invoices i ON i.id = pay.invoice_id
    LEFT JOIN test_orders o ON o.id = pay.order_id';

$total = (int)fetchValue("SELECT COUNT(*) $baseJoin $whereSql", $params);
$sum = (float)fetchValue("SELECT COALESCE(SUM(pay.amount),0) $baseJoin $whereSql", $params);
$payments = fetchAll("SELECT pay.*, p.full_name, p.patient_id AS patient_code, i.invoice_number, o.order_number
    $baseJoin $whereSql ORDER BY pay.payment_date DESC, pay.id DESC LIMIT " . (int)PER_PAGE . ' OFFSET ' . (int)$offset, $params);

$byMethod = fetchAll("SELECT pay.payment_method, COALESCE(SUM(pay.amount),0) AS total $baseJoin $whereSql GROUP BY pay.payment_method", $params);

$pageTitle = 'Payments';
$pageSubtitle = number_format($total) . ' payment(s) · ' . formatCurrency($sum) . ' collected';
$activeMenu = 'payments';
$pageActions = '<a href="' . BASE_URL . '/modules/billing/add_payment.php" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Record Payment</a>';
require_once INC_PATH . '/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="search" name="q" class="form-control" value="<?= sanitize($search) ?>" placeholder="Patient, invoice #, order #">
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
                <label class="form-label">Method</label>
                <select name="method" class="form-select">
                    <option value="">All</option>
                    <?php foreach (['Cash', 'Card', 'Bank Transfer', 'Other'] as $m): ?>
                        <option value="<?= $m ?>" <?= $method === $m ? 'selected' : '' ?>><?= $m ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Filter</button>
                <a href="<?= BASE_URL ?>/modules/billing/payments.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card"><div class="card-body py-3">
            <div class="text-muted small">Collected in range</div>
            <div class="h4 mb-0 text-success"><?= formatCurrency($sum) ?></div>
        </div></div>
    </div>
    <?php foreach ($byMethod as $m): ?>
        <div class="col-md-2">
            <div class="card"><div class="card-body py-3">
                <div class="text-muted small"><?= sanitize($m['payment_method']) ?></div>
                <div class="h6 mb-0"><?= formatCurrency($m['total']) ?></div>
            </div></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Date</th><th>Patient</th><th>Reference</th><th>Method</th><th>Amount</th><th>Notes</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php if (!$payments): ?>
                <tr><td colspan="7"><div class="empty-state"><i class="bi bi-cash-coin"></i>No payments in this period.</div></td></tr>
            <?php endif; ?>
            <?php foreach ($payments as $p): ?>
                <tr>
                    <td class="small"><?= formatDateTime($p['payment_date']) ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/modules/patients/view.php?id=<?= (int)$p['patient_id'] ?>" class="fw-semibold text-decoration-none"><?= sanitize($p['full_name']) ?></a>
                        <div><small class="text-muted"><?= sanitize($p['patient_code']) ?></small></div>
                    </td>
                    <td class="small">
                        <?php if ($p['invoice_number']): ?>
                            <a href="invoice_view.php?id=<?= (int)$p['invoice_id'] ?>"><?= sanitize($p['invoice_number']) ?></a>
                        <?php endif; ?>
                        <?php if ($p['order_number']): ?>
                            <div><a href="<?= BASE_URL ?>/modules/orders/view.php?id=<?= (int)$p['order_id'] ?>"><?= sanitize($p['order_number']) ?></a></div>
                        <?php endif; ?>
                        <?php if (!$p['invoice_number'] && !$p['order_number']): ?><span class="text-muted">General</span><?php endif; ?>
                    </td>
                    <td class="small"><?= sanitize($p['payment_method']) ?></td>
                    <td class="fw-semibold text-success"><?= formatCurrency($p['amount']) ?></td>
                    <td class="small text-muted"><?= sanitize($p['notes'] ?: '-') ?></td>
                    <td class="text-end">
                        <form method="post" class="d-inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" data-confirm="Delete this payment and update balances?"><i class="bi bi-trash"></i></button>
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
