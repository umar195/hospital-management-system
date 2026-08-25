<?php
/** View a laboratory test order. */
require_once __DIR__ . '/../../config/session.php';

$id = (int)get('id', post('id'));
$order = $id ? fetchOne('SELECT o.*, p.full_name, p.patient_id AS patient_code, p.gender, p.age, p.date_of_birth,
        p.phone, p.whatsapp, p.address, v.visit_number
    FROM test_orders o
    JOIN patients p ON p.id = o.patient_id
    LEFT JOIN visits v ON v.id = o.visit_id
    WHERE o.id = ?', [$id]) : null;

if (!$order) {
    flash('danger', 'Order not found.');
    redirect(BASE_URL . '/modules/orders/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(BASE_URL . '/modules/orders/view.php?id=' . $id);
    $action = post('action');

    if ($action === 'item_status') {
        $itemId = (int)post('item_id');
        $status = post('status');
        $allowed = ['Ordered', 'Sample Collected', 'Processing', 'Pending', 'Completed', 'Delivered'];
        if ($itemId && in_array($status, $allowed, true)) {
            $pdo->prepare('UPDATE test_order_items SET status = ? WHERE id = ? AND order_id = ?')->execute([$status, $itemId, $id]);
            flash('success', 'Test status updated.');
        }
    } elseif ($action === 'add_payment') {
        $amount = round((float)post('amount', 0), 2);
        $method = post('payment_method', 'Cash');
        if (!in_array($method, ['Cash', 'Card', 'Bank Transfer', 'Other'], true)) {
            $method = 'Cash';
        }
        if ($amount <= 0) {
            flash('danger', 'Enter a payment amount greater than zero.');
        } else {
            $amount = min($amount, (float)$order['remaining_amount']);
            $invoice = fetchOne('SELECT id FROM invoices WHERE visit_id = ? OR notes LIKE ? ORDER BY id DESC LIMIT 1',
                [$order['visit_id'], '%' . $order['order_number'] . '%']);
            $pdo->prepare('INSERT INTO payments (patient_id, invoice_id, order_id, amount, payment_method, payment_date, notes)
                           VALUES (?,?,?,?,?,NOW(),?)')
                ->execute([$order['patient_id'], $invoice['id'] ?? null, $id, $amount, $method, post('notes') ?: null]);
            $newPaid = round((float)$order['paid_amount'] + $amount, 2);
            $newRemaining = max(0, round((float)$order['net_amount'] - $newPaid, 2));
            $pdo->prepare('UPDATE test_orders SET paid_amount = ?, remaining_amount = ? WHERE id = ?')
                ->execute([$newPaid, $newRemaining, $id]);
            if (!empty($invoice['id'])) {
                recalcInvoiceTotals((int)$invoice['id']);
            }
            logActivity('Payment received', formatCurrency($amount) . ' for ' . $order['order_number'], 'test_orders', $id);
            flash('success', 'Payment of ' . formatCurrency($amount) . ' recorded.');
        }
    } elseif ($action === 'generate_report') {
        $pending = (int)fetchValue('SELECT COUNT(*) FROM test_order_items WHERE order_id = ? AND status NOT IN ("Completed","Delivered")', [$id]);
        $hasResults = (int)fetchValue('SELECT COUNT(*) FROM test_results r JOIN test_order_items i ON i.id = r.order_item_id WHERE i.order_id = ?', [$id]);
        if (!$hasResults) {
            flash('warning', 'Enter the results before generating a report.');
            redirect(BASE_URL . '/modules/orders/results.php?id=' . $id);
        }
        $existing = fetchOne('SELECT id FROM reports WHERE order_id = ? ORDER BY id DESC LIMIT 1', [$id]);
        if ($existing) {
            flash('info', 'A report already exists for this order.');
            redirect(BASE_URL . '/modules/reports/view.php?id=' . (int)$existing['id']);
        }
        $number = generateReportNumber();
        $pdo->prepare('INSERT INTO reports (report_number, order_id, patient_id, generated_at, authorized_by, remarks, status)
                       VALUES (?,?,?,NOW(),?,?,?)')
            ->execute([$number, $id, $order['patient_id'], getSetting('authorized_by', ''), null, $pending ? 'Draft' : 'Final']);
        $reportId = (int)$pdo->lastInsertId();
        logActivity('Report generated', $number . ' for ' . $order['order_number'], 'reports', $reportId);
        flash('success', 'Report ' . $number . ' generated.');
        redirect(BASE_URL . '/modules/reports/view.php?id=' . $reportId);
    }
    redirect(BASE_URL . '/modules/orders/view.php?id=' . $id);
}

$items = fetchAll('SELECT i.*, t.name AS test_name, t.test_code, t.sample_type, t.unit, t.normal_range,
        (SELECT COUNT(*) FROM test_results r WHERE r.order_item_id = i.id) AS result_count
    FROM test_order_items i JOIN tests t ON t.id = i.test_id WHERE i.order_id = ? ORDER BY t.name', [$id]);

$results = fetchAll('SELECT r.*, i.test_id, t.name AS test_name FROM test_results r
    JOIN test_order_items i ON i.id = r.order_item_id
    JOIN tests t ON t.id = i.test_id
    WHERE i.order_id = ? ORDER BY t.name, r.id', [$id]);

$payments = fetchAll('SELECT * FROM payments WHERE order_id = ? ORDER BY payment_date DESC, id DESC', [$id]);
$report   = fetchOne('SELECT * FROM reports WHERE order_id = ? ORDER BY id DESC LIMIT 1', [$id]);
$invoice  = fetchOne('SELECT * FROM invoices WHERE visit_id = ? OR notes LIKE ? ORDER BY id DESC LIMIT 1',
    [$order['visit_id'], '%' . $order['order_number'] . '%']);

$pageTitle = 'Order ' . sanitize($order['order_number']);
$pageSubtitle = sanitize($order['full_name']) . ' · ' . formatDateTime($order['order_date']);
$activeMenu = 'orders';
$pageActions = '<a href="' . BASE_URL . '/modules/orders/results.php?id=' . $id . '" class="btn btn-primary"><i class="bi bi-pencil-square me-1"></i>Enter Results</a> '
    . ($report ? '<a href="' . BASE_URL . '/modules/reports/print.php?id=' . (int)$report['id'] . '" target="_blank" class="btn btn-outline-success"><i class="bi bi-printer me-1"></i>Print Report</a> ' : '')
    . '<a href="' . BASE_URL . '/modules/orders/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>';
require_once INC_PATH . '/header.php';
?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-clipboard2-pulse me-2"></i>Ordered tests</span>
                <span class="small text-muted"><?= count($items) ?> item(s)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Test</th><th>Sample</th><th>Price</th><th>Results</th><th style="width:210px">Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= sanitize($item['test_name']) ?></div>
                                <small class="text-muted"><?= sanitize($item['test_code']) ?></small>
                            </td>
                            <td class="small"><?= sanitize($item['sample_type'] ?: '-') ?></td>
                            <td class="small"><?= formatCurrency($item['price']) ?></td>
                            <td class="small">
                                <?= (int)$item['result_count'] > 0
                                    ? '<span class="badge bg-success-subtle text-success-emphasis">' . (int)$item['result_count'] . ' entered</span>'
                                    : '<span class="badge bg-light text-muted">none</span>' ?>
                            </td>
                            <td>
                                <form method="post" class="d-flex gap-1">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="item_status">
                                    <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                        <?php foreach (['Ordered', 'Sample Collected', 'Processing', 'Pending', 'Completed', 'Delivered'] as $s): ?>
                                            <option value="<?= $s ?>" <?= $item['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($results): ?>
            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-activity me-2"></i>Recorded results</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Test</th><th>Parameter</th><th>Result</th><th>Unit</th><th>Reference</th><th>Flag</th></tr></thead>
                        <tbody>
                        <?php foreach ($results as $r): ?>
                            <tr>
                                <td class="small"><?= sanitize($r['test_name']) ?></td>
                                <td class="small"><?= sanitize($r['parameter_name'] ?: '-') ?></td>
                                <td class="fw-semibold"><?= sanitize($r['result_value']) ?></td>
                                <td class="small"><?= sanitize($r['unit']) ?></td>
                                <td class="small"><?= sanitize($r['reference_range']) ?></td>
                                <td><?= flagBadge($r['flag']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-cash-coin me-2"></i>Payments</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Notes</th></tr></thead>
                    <tbody>
                    <?php if (!$payments): ?>
                        <tr><td colspan="4" class="text-muted small">No payments recorded yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($payments as $p): ?>
                        <tr>
                            <td class="small"><?= formatDateTime($p['payment_date']) ?></td>
                            <td class="fw-semibold text-success"><?= formatCurrency($p['amount']) ?></td>
                            <td class="small"><?= sanitize($p['payment_method']) ?></td>
                            <td class="small text-muted"><?= sanitize($p['notes'] ?: '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ((float)$order['remaining_amount'] > 0): ?>
                <div class="card-body border-top">
                    <form method="post" class="row g-2 align-items-end">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="add_payment">
                        <div class="col-md-3">
                            <label class="form-label">Amount</label>
                            <input type="number" step="0.01" min="0.01" max="<?= (float)$order['remaining_amount'] ?>"
                                   name="amount" class="form-control" value="<?= (float)$order['remaining_amount'] ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Method</label>
                            <select name="payment_method" class="form-select">
                                <?php foreach (['Cash', 'Card', 'Bank Transfer', 'Other'] as $m): ?>
                                    <option value="<?= $m ?>"><?= $m ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Notes</label>
                            <input type="text" name="notes" class="form-control" placeholder="Optional">
                        </div>
                        <div class="col-md-2 d-grid">
                            <button class="btn btn-success"><i class="bi bi-plus-circle me-1"></i>Receive</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-person-badge me-2"></i>Patient</div>
            <div class="card-body">
                <h6 class="mb-1"><?= sanitize($order['full_name']) ?></h6>
                <div class="small text-muted mb-2"><?= sanitize($order['patient_code']) ?></div>
                <ul class="list-unstyled small mb-3">
                    <li><strong>Gender:</strong> <?= sanitize($order['gender']) ?></li>
                    <li><strong>Age:</strong> <?= $order['age'] !== null ? (int)$order['age'] . ' yrs' : ($order['date_of_birth'] ? calculateAge($order['date_of_birth']) . ' yrs' : '-') ?></li>
                    <li><strong>Phone:</strong> <?= sanitize($order['phone'] ?: '-') ?></li>
                    <li><strong>Referred by:</strong> <?= sanitize($order['referring_doctor'] ?: '-') ?></li>
                    <?php if ($order['visit_number']): ?><li><strong>Visit:</strong> <?= sanitize($order['visit_number']) ?></li><?php endif; ?>
                </ul>
                <a href="<?= BASE_URL ?>/modules/patients/view.php?id=<?= (int)$order['patient_id'] ?>" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-folder2-open me-1"></i>Open patient file
                </a>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-receipt me-2"></i>Billing</div>
            <div class="card-body">
                <div class="d-flex justify-content-between small"><span>Total</span><strong><?= formatCurrency($order['total_amount']) ?></strong></div>
                <div class="d-flex justify-content-between small"><span>Discount</span><strong><?= formatCurrency($order['discount']) ?></strong></div>
                <div class="d-flex justify-content-between"><span>Net</span><strong class="text-primary"><?= formatCurrency($order['net_amount']) ?></strong></div>
                <div class="d-flex justify-content-between small"><span>Paid</span><strong class="text-success"><?= formatCurrency($order['paid_amount']) ?></strong></div>
                <div class="d-flex justify-content-between small"><span>Balance</span><strong class="text-danger"><?= formatCurrency($order['remaining_amount']) ?></strong></div>
                <hr>
                <div class="small text-muted">Payment method: <?= sanitize($order['payment_method']) ?></div>
                <?php if ($invoice): ?>
                    <a href="<?= BASE_URL ?>/modules/billing/invoice_view.php?id=<?= (int)$invoice['id'] ?>" class="btn btn-sm btn-outline-secondary w-100 mt-2">
                        <i class="bi bi-file-earmark-text me-1"></i>Invoice <?= sanitize($invoice['invoice_number']) ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-file-medical me-2"></i>Report</div>
            <div class="card-body">
                <?php if ($report): ?>
                    <p class="small mb-2">Report <strong><?= sanitize($report['report_number']) ?></strong> generated on <?= formatDateTime($report['generated_at']) ?> (<?= sanitize($report['status']) ?>).</p>
                    <div class="d-grid gap-2">
                        <a href="<?= BASE_URL ?>/modules/reports/view.php?id=<?= (int)$report['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>View report</a>
                        <a href="<?= BASE_URL ?>/modules/reports/print.php?id=<?= (int)$report['id'] ?>" target="_blank" class="btn btn-sm btn-outline-success"><i class="bi bi-printer me-1"></i>Print / PDF</a>
                    </div>
                <?php else: ?>
                    <p class="small text-muted">No report has been generated for this order yet.</p>
                    <form method="post">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="generate_report">
                        <button class="btn btn-sm btn-primary w-100"><i class="bi bi-file-earmark-plus me-1"></i>Generate report</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once INC_PATH . '/footer.php'; ?>
