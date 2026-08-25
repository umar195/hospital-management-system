<?php
/** Invoice list. */
require_once __DIR__ . '/../../config/session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(BASE_URL . '/modules/billing/invoices.php');
    $id = (int)post('id');
    if (post('action') === 'delete' && $id) {
        $inv = fetchOne('SELECT invoice_number FROM invoices WHERE id = ?', [$id]);
        $pdo->prepare('UPDATE payments SET invoice_id = NULL WHERE invoice_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM invoices WHERE id = ?')->execute([$id]);
        logActivity('Invoice deleted', $inv['invoice_number'] ?? ('#' . $id), 'invoices', $id);
        flash('success', 'Invoice deleted.');
    }
    redirect(BASE_URL . '/modules/billing/invoices.php');
}

$search = get('q');
$status = get('status');
$from   = get('from');
$to     = get('to');
$page   = currentPage();
$offset = ($page - 1) * PER_PAGE;

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(i.invoice_number LIKE ? OR p.full_name LIKE ? OR p.patient_id LIKE ? OR p.phone LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}
if (in_array($status, ['Pending', 'Partial', 'Paid'], true)) {
    $where[] = 'i.payment_status = ?';
    $params[] = $status;
}
if ($from !== '') {
    $where[] = 'DATE(i.invoice_date) >= ?';
    $params[] = $from;
}
if ($to !== '') {
    $where[] = 'DATE(i.invoice_date) <= ?';
    $params[] = $to;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int)fetchValue("SELECT COUNT(*) FROM invoices i JOIN patients p ON p.id = i.patient_id $whereSql", $params);
$invoices = fetchAll("SELECT i.*, p.full_name, p.patient_id AS patient_code, p.phone
    FROM invoices i JOIN patients p ON p.id = i.patient_id
    $whereSql ORDER BY i.invoice_date DESC, i.id DESC LIMIT " . (int)PER_PAGE . ' OFFSET ' . (int)$offset, $params);

$totals = fetchOne("SELECT COALESCE(SUM(i.total),0) AS billed, COALESCE(SUM(i.paid_amount),0) AS paid,
        COALESCE(SUM(i.balance),0) AS balance
    FROM invoices i JOIN patients p ON p.id = i.patient_id $whereSql", $params);

$pageTitle = 'Invoices';
$pageSubtitle = number_format($total) . ' invoice(s)';
$activeMenu = 'invoices';
$pageActions = '<a href="' . BASE_URL . '/modules/billing/add_payment.php" class="btn btn-primary"><i class="bi bi-cash-coin me-1"></i>Record Payment</a> '
    . '<a href="' . BASE_URL . '/modules/orders/add.php" class="btn btn-outline-primary"><i class="bi bi-plus-circle me-1"></i>New Order</a>';
require_once INC_PATH . '/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="search" name="q" class="form-control" value="<?= sanitize($search) ?>" placeholder="Invoice #, patient, phone">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <?php foreach (['Pending', 'Partial', 'Paid'] as $s): ?>
                        <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">From</label>
                <input type="date" name="from" class="form-control" value="<?= sanitize($from) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">To</label>
                <input type="date" name="to" class="form-control" value="<?= sanitize($to) ?>">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Filter</button>
                <a href="<?= BASE_URL ?>/modules/billing/invoices.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card"><div class="card-body py-3">
        <div class="text-muted small">Total billed</div><div class="h5 mb-0"><?= formatCurrency($totals['billed'] ?? 0) ?></div>
    </div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body py-3">
        <div class="text-muted small">Received</div><div class="h5 mb-0 text-success"><?= formatCurrency($totals['paid'] ?? 0) ?></div>
    </div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body py-3">
        <div class="text-muted small">Outstanding</div><div class="h5 mb-0 text-danger"><?= formatCurrency($totals['balance'] ?? 0) ?></div>
    </div></div></div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Invoice #</th><th>Patient</th><th>Date</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php if (!$invoices): ?>
                <tr><td colspan="8"><div class="empty-state"><i class="bi bi-receipt"></i>No invoices found.</div></td></tr>
            <?php endif; ?>
            <?php foreach ($invoices as $inv): ?>
                <tr>
                    <td><a href="invoice_view.php?id=<?= (int)$inv['id'] ?>" class="fw-semibold text-decoration-none"><?= sanitize($inv['invoice_number']) ?></a></td>
                    <td>
                        <div class="fw-semibold"><?= sanitize($inv['full_name']) ?></div>
                        <small class="text-muted"><?= sanitize($inv['patient_code']) ?></small>
                    </td>
                    <td class="small"><?= formatDateTime($inv['invoice_date']) ?></td>
                    <td class="small"><?= formatCurrency($inv['total']) ?></td>
                    <td class="small text-success"><?= formatCurrency($inv['paid_amount']) ?></td>
                    <td class="small <?= $inv['balance'] > 0 ? 'text-danger fw-semibold' : '' ?>"><?= formatCurrency($inv['balance']) ?></td>
                    <td><?= statusBadge($inv['payment_status']) ?></td>
                    <td class="text-end text-nowrap">
                        <a href="invoice_view.php?id=<?= (int)$inv['id'] ?>" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                        <a href="invoice_view.php?id=<?= (int)$inv['id'] ?>&print=1" target="_blank" class="btn btn-sm btn-outline-success" title="Print"><i class="bi bi-printer"></i></a>
                        <?php if ($inv['balance'] > 0): ?>
                            <a href="add_payment.php?invoice_id=<?= (int)$inv['id'] ?>" class="btn btn-sm btn-outline-primary" title="Add payment"><i class="bi bi-cash-coin"></i></a>
                        <?php endif; ?>
                        <form method="post" class="d-inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$inv['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" data-confirm="Delete invoice <?= sanitize($inv['invoice_number']) ?>?" title="Delete"><i class="bi bi-trash"></i></button>
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
