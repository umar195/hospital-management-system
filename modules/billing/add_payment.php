<?php
/** Record a payment against an invoice, a test order or a patient account. */
require_once __DIR__ . '/../../config/session.php';

$invoiceId = (int)get('invoice_id', post('invoice_id'));
$orderId   = (int)get('order_id', post('order_id'));
$patientId = (int)get('patient_id', post('patient_id'));

$invoice = $invoiceId ? fetchOne('SELECT i.*, p.full_name, p.patient_id AS patient_code FROM invoices i
    JOIN patients p ON p.id = i.patient_id WHERE i.id = ?', [$invoiceId]) : null;
$order = $orderId ? fetchOne('SELECT o.*, p.full_name, p.patient_id AS patient_code FROM test_orders o
    JOIN patients p ON p.id = o.patient_id WHERE o.id = ?', [$orderId]) : null;

if ($invoice) {
    $patientId = (int)$invoice['patient_id'];
} elseif ($order) {
    $patientId = (int)$order['patient_id'];
}
$patient = $patientId ? fetchOne('SELECT * FROM patients WHERE id = ?', [$patientId]) : null;

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(BASE_URL . '/modules/billing/add_payment.php');
    $amount = round((float)post('amount', 0), 2);
    $method = post('payment_method', 'Cash');
    $date   = post('payment_date') ?: date('Y-m-d H:i:s');
    if (strlen($date) === 16) {
        $date = str_replace('T', ' ', $date) . ':00';
    }
    if (!in_array($method, ['Cash', 'Card', 'Bank Transfer', 'Other'], true)) {
        $method = 'Cash';
    }
    if (!$patient) {
        $errors[] = 'Please select a patient.';
    }
    if ($amount <= 0) {
        $errors[] = 'Enter an amount greater than zero.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO payments (patient_id, invoice_id, order_id, amount, payment_method, payment_date, notes)
                           VALUES (?,?,?,?,?,?,?)')
                ->execute([$patientId, $invoiceId ?: null, $orderId ?: null, $amount, $method, $date, post('notes') ?: null]);

            if ($invoiceId) {
                recalcInvoiceTotals($invoiceId);
            }
            if ($orderId) {
                $paid = (float)fetchValue('SELECT COALESCE(SUM(amount),0) FROM payments WHERE order_id = ?', [$orderId]);
                $net = (float)fetchValue('SELECT net_amount FROM test_orders WHERE id = ?', [$orderId]);
                $pdo->prepare('UPDATE test_orders SET paid_amount = ?, remaining_amount = ? WHERE id = ?')
                    ->execute([$paid, max(0, round($net - $paid, 2)), $orderId]);
            }
            $pdo->commit();
            logActivity('Payment recorded', formatCurrency($amount) . ' from ' . $patient['full_name'], 'payments', $patientId);
            flash('success', 'Payment of ' . formatCurrency($amount) . ' recorded.');
            redirect($invoiceId
                ? BASE_URL . '/modules/billing/invoice_view.php?id=' . $invoiceId
                : BASE_URL . '/modules/billing/payments.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = friendlyError($e, 'Could not save the payment. Please try again.');
        }
    }
}

$openInvoices = $patient
    ? fetchAll('SELECT id, invoice_number, total, balance, invoice_date FROM invoices WHERE patient_id = ? AND balance > 0 ORDER BY invoice_date DESC', [$patientId])
    : [];
$openOrders = $patient
    ? fetchAll('SELECT id, order_number, net_amount, remaining_amount FROM test_orders WHERE patient_id = ? AND remaining_amount > 0 ORDER BY order_date DESC', [$patientId])
    : [];

if ($invoice && !in_array($invoiceId, array_map('intval', array_column($openInvoices, 'id')), true)) {
    array_unshift($openInvoices, $invoice);
}
if ($order && !in_array($orderId, array_map('intval', array_column($openOrders, 'id')), true)) {
    array_unshift($openOrders, $order);
}

$suggested = 0;
if ($invoice) {
    $suggested = (float)$invoice['balance'];
} elseif ($order) {
    $suggested = (float)$order['remaining_amount'];
}

$pageTitle = 'Record Payment';
$activeMenu = 'payments';
$pageActions = '<a href="' . BASE_URL . '/modules/billing/payments.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>All payments</a>';
require_once INC_PATH . '/header.php';
?>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-octagon me-2"></i><?= sanitize($error) ?></div>
<?php endforeach; ?>

<div class="row g-3">
    <div class="col-lg-7">
        <form method="post" class="card">
            <?= csrfField() ?>
            <input type="hidden" name="patient_id" value="<?= (int)$patientId ?>">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-cash-coin me-2"></i>Payment details</div>
            <div class="card-body row g-3">
                <div class="col-12 position-relative">
                    <label class="form-label">Patient</label>
                    <input type="text" id="patientSearch" class="form-control" autocomplete="off"
                           placeholder="<?= $patient ? sanitize($patient['full_name'] . ' (' . $patient['patient_id'] . ')') : 'Search patient by name, ID or phone' ?>">
                    <div id="patientResults" class="list-group search-results"></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Amount</label>
                    <input type="number" step="0.01" min="0.01" name="amount" class="form-control"
                           value="<?= $suggested > 0 ? $suggested : sanitize(post('amount')) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Payment method</label>
                    <select name="payment_method" class="form-select">
                        <?php foreach (['Cash', 'Card', 'Bank Transfer', 'Other'] as $m): ?>
                            <option value="<?= $m ?>" <?= post('payment_method') === $m ? 'selected' : '' ?>><?= $m ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Date &amp; time</label>
                    <input type="datetime-local" name="payment_date" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Apply to invoice</label>
                    <select name="invoice_id" class="form-select">
                        <option value="">-- none (general payment) --</option>
                        <?php foreach ($openInvoices as $inv): ?>
                            <option value="<?= (int)$inv['id'] ?>" <?= $invoiceId === (int)$inv['id'] ? 'selected' : '' ?>>
                                <?= sanitize($inv['invoice_number']) ?> · balance <?= formatCurrency($inv['balance']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Apply to test order</label>
                    <select name="order_id" class="form-select">
                        <option value="">-- none --</option>
                        <?php foreach ($openOrders as $o): ?>
                            <option value="<?= (int)$o['id'] ?>" <?= $orderId === (int)$o['id'] ? 'selected' : '' ?>>
                                <?= sanitize($o['order_number']) ?> · balance <?= formatCurrency($o['remaining_amount']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"><?= sanitize(post('notes')) ?></textarea>
                </div>
            </div>
            <div class="card-footer bg-white d-flex gap-2">
                <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Save payment</button>
                <a href="<?= BASE_URL ?>/modules/billing/payments.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-person-badge me-2"></i>Patient account</div>
            <div class="card-body">
                <?php if (!$patient): ?>
                    <p class="text-muted small mb-0">Search and select a patient to see their outstanding balances.</p>
                <?php else: ?>
                    <h6 class="mb-1"><?= sanitize($patient['full_name']) ?></h6>
                    <div class="small text-muted mb-3"><?= sanitize($patient['patient_id']) ?> · <?= sanitize($patient['phone'] ?: 'no phone') ?></div>
                    <div class="d-flex justify-content-between small">
                        <span>Invoiced</span>
                        <strong><?= formatCurrency(fetchValue('SELECT COALESCE(SUM(total),0) FROM invoices WHERE patient_id = ?', [$patientId])) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span>Paid</span>
                        <strong class="text-success"><?= formatCurrency(fetchValue('SELECT COALESCE(SUM(amount),0) FROM payments WHERE patient_id = ?', [$patientId])) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span>Outstanding</span>
                        <strong class="text-danger"><?= formatCurrency(fetchValue('SELECT COALESCE(SUM(balance),0) FROM invoices WHERE patient_id = ?', [$patientId])) ?></strong>
                    </div>
                    <hr>
                    <div class="fw-semibold small mb-2">Open invoices</div>
                    <?php if (!$openInvoices): ?>
                        <div class="small text-muted">No outstanding invoices.</div>
                    <?php endif; ?>
                    <ul class="list-unstyled small mb-0">
                        <?php foreach ($openInvoices as $inv): ?>
                            <li class="d-flex justify-content-between">
                                <a href="invoice_view.php?id=<?= (int)$inv['id'] ?>"><?= sanitize($inv['invoice_number']) ?></a>
                                <span class="text-danger"><?= formatCurrency($inv['balance']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$pageScripts = <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.HMS && HMS.patientSearch) {
        HMS.patientSearch(document.getElementById('patientSearch'), document.getElementById('patientResults'), function (p) {
            window.location.href = window.BASE_URL + '/modules/billing/add_payment.php?patient_id=' + p.id;
        });
    }
});
</script>
HTML;
require_once INC_PATH . '/footer.php';
