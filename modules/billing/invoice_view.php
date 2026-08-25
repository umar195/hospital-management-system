<?php
/** Invoice detail, printable, with item editing and payment recording. */
require_once __DIR__ . '/../../config/session.php';

$id = (int)get('id', post('id'));
$invoice = $id ? fetchOne('SELECT i.*, p.full_name, p.patient_id AS patient_code, p.phone, p.whatsapp, p.address,
        p.gender, p.age, v.visit_number
    FROM invoices i JOIN patients p ON p.id = i.patient_id
    LEFT JOIN visits v ON v.id = i.visit_id WHERE i.id = ?', [$id]) : null;

if (!$invoice) {
    flash('danger', 'Invoice not found.');
    redirect(BASE_URL . '/modules/billing/invoices.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(BASE_URL . '/modules/billing/invoice_view.php?id=' . $id);
    $action = post('action');

    if ($action === 'add_item') {
        $desc = post('description');
        $qty = max(1, (int)post('quantity', 1));
        $price = round((float)post('unit_price', 0), 2);
        if ($desc === '') {
            flash('danger', 'Item description is required.');
        } else {
            $pdo->prepare('INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, total) VALUES (?,?,?,?,?)')
                ->execute([$id, $desc, $qty, $price, round($qty * $price, 2)]);
            $subtotal = (float)fetchValue('SELECT COALESCE(SUM(total),0) FROM invoice_items WHERE invoice_id = ?', [$id]);
            $pdo->prepare('UPDATE invoices SET subtotal = ?, total = GREATEST(? - discount, 0) WHERE id = ?')
                ->execute([$subtotal, $subtotal, $id]);
            recalcInvoiceTotals($id);
            flash('success', 'Item added to the invoice.');
        }
    } elseif ($action === 'delete_item') {
        $itemId = (int)post('item_id');
        $pdo->prepare('DELETE FROM invoice_items WHERE id = ? AND invoice_id = ?')->execute([$itemId, $id]);
        $subtotal = (float)fetchValue('SELECT COALESCE(SUM(total),0) FROM invoice_items WHERE invoice_id = ?', [$id]);
        $pdo->prepare('UPDATE invoices SET subtotal = ?, total = GREATEST(? - discount, 0) WHERE id = ?')
            ->execute([$subtotal, $subtotal, $id]);
        recalcInvoiceTotals($id);
        flash('success', 'Item removed.');
    } elseif ($action === 'update_discount') {
        $discount = max(0, round((float)post('discount', 0), 2));
        $subtotal = (float)fetchValue('SELECT COALESCE(SUM(total),0) FROM invoice_items WHERE invoice_id = ?', [$id]);
        $discount = min($discount, $subtotal);
        $pdo->prepare('UPDATE invoices SET subtotal = ?, discount = ?, total = ?, notes = ? WHERE id = ?')
            ->execute([$subtotal, $discount, round($subtotal - $discount, 2), post('notes') ?: null, $id]);
        recalcInvoiceTotals($id);
        flash('success', 'Invoice updated.');
    } elseif ($action === 'add_payment') {
        $amount = round((float)post('amount', 0), 2);
        $method = post('payment_method', 'Cash');
        if (!in_array($method, ['Cash', 'Card', 'Bank Transfer', 'Other'], true)) {
            $method = 'Cash';
        }
        if ($amount <= 0) {
            flash('danger', 'Enter a valid payment amount.');
        } else {
            $orderId = fetchValue('SELECT id FROM test_orders WHERE visit_id = ? ORDER BY id DESC LIMIT 1', [$invoice['visit_id']], null);
            $pdo->prepare('INSERT INTO payments (patient_id, invoice_id, order_id, amount, payment_method, payment_date, notes)
                           VALUES (?,?,?,?,?,NOW(),?)')
                ->execute([$invoice['patient_id'], $id, $orderId ?: null, $amount, $method, post('notes') ?: null]);
            recalcInvoiceTotals($id);
            if ($orderId) {
                $paid = (float)fetchValue('SELECT COALESCE(SUM(amount),0) FROM payments WHERE order_id = ?', [$orderId]);
                $net = (float)fetchValue('SELECT net_amount FROM test_orders WHERE id = ?', [$orderId]);
                $pdo->prepare('UPDATE test_orders SET paid_amount = ?, remaining_amount = ? WHERE id = ?')
                    ->execute([$paid, max(0, round($net - $paid, 2)), $orderId]);
            }
            logActivity('Payment received', formatCurrency($amount) . ' on ' . $invoice['invoice_number'], 'invoices', $id);
            flash('success', 'Payment of ' . formatCurrency($amount) . ' recorded.');
        }
    }
    redirect(BASE_URL . '/modules/billing/invoice_view.php?id=' . $id);
}

$items    = fetchAll('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id', [$id]);
$payments = fetchAll('SELECT * FROM payments WHERE invoice_id = ? ORDER BY payment_date DESC, id DESC', [$id]);
$printMode = get('print') === '1';
$hospitalName = getSetting('hospital_name', 'City Care Hospital');
$phone = $invoice['whatsapp'] ?: $invoice['phone'];
$waMessage = 'Dear ' . $invoice['full_name'] . ', your invoice ' . $invoice['invoice_number'] . ' from ' . $hospitalName
    . ' totals ' . formatCurrency($invoice['total']) . ' (balance ' . formatCurrency($invoice['balance']) . ').';

$pageTitle = 'Invoice ' . sanitize($invoice['invoice_number']);
$pageSubtitle = sanitize($invoice['full_name']) . ' · ' . formatDateTime($invoice['invoice_date']);
$activeMenu = 'invoices';
$pageActions = '<button class="btn btn-primary" data-print="1"><i class="bi bi-printer me-1"></i>Print</button> '
    . ($phone ? '<a href="' . sanitize(whatsappLink($phone, $waMessage)) . '" target="_blank" class="btn btn-outline-success"><i class="bi bi-whatsapp me-1"></i>WhatsApp</a> ' : '')
    . '<a href="' . BASE_URL . '/modules/billing/invoices.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>';
require_once INC_PATH . '/header.php';
?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="print-sheet card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                    <div class="d-flex gap-3 align-items-center">
                        <?php if ($logo = hospitalLogoUrl()): ?>
                            <img src="<?= sanitize($logo) ?>" alt="Logo" style="max-height:60px">
                        <?php endif; ?>
                        <div>
                            <h5 class="mb-1 text-primary"><?= sanitize($hospitalName) ?></h5>
                            <div class="small text-muted"><?= nl2br(sanitize(getSetting('hospital_address', ''))) ?></div>
                            <div class="small text-muted"><?= sanitize(getSetting('hospital_phone', '')) ?></div>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold">INVOICE</div>
                        <div class="small">#<?= sanitize($invoice['invoice_number']) ?></div>
                        <div class="small"><?= formatDateTime($invoice['invoice_date']) ?></div>
                        <div class="small"><?= statusBadge($invoice['payment_status']) ?></div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-6 small">
                        <div class="text-muted">Billed to</div>
                        <div class="fw-semibold"><?= sanitize($invoice['full_name']) ?></div>
                        <div><?= sanitize($invoice['patient_code']) ?></div>
                        <div><?= sanitize($invoice['phone'] ?: '') ?></div>
                        <div class="text-muted"><?= nl2br(sanitize($invoice['address'] ?: '')) ?></div>
                    </div>
                    <div class="col-6 small text-end">
                        <?php if ($invoice['visit_number']): ?><div>Visit: <?= sanitize($invoice['visit_number']) ?></div><?php endif; ?>
                        <div>Payment method: <?= sanitize($invoice['payment_method']) ?></div>
                    </div>
                </div>

                <table class="table table-sm table-bordered">
                    <thead class="table-light"><tr><th style="width:52%">Description</th><th>Qty</th><th>Unit price</th><th class="text-end">Amount</th><th class="no-print text-end">·</th></tr></thead>
                    <tbody>
                    <?php if (!$items): ?>
                        <tr><td colspan="5" class="text-muted small">No items on this invoice yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= sanitize($item['description']) ?></td>
                            <td><?= (int)$item['quantity'] ?></td>
                            <td><?= formatCurrency($item['unit_price']) ?></td>
                            <td class="text-end"><?= formatCurrency($item['total']) ?></td>
                            <td class="no-print text-end">
                                <form method="post">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete_item">
                                    <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger py-0" data-confirm="Remove this item?"><i class="bi bi-x"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                    <tr><th colspan="3" class="text-end">Subtotal</th><th class="text-end"><?= formatCurrency($invoice['subtotal']) ?></th><th class="no-print"></th></tr>
                    <tr><th colspan="3" class="text-end">Discount</th><th class="text-end">- <?= formatCurrency($invoice['discount']) ?></th><th class="no-print"></th></tr>
                    <tr><th colspan="3" class="text-end">Total</th><th class="text-end"><?= formatCurrency($invoice['total']) ?></th><th class="no-print"></th></tr>
                    <tr><th colspan="3" class="text-end">Paid</th><th class="text-end text-success"><?= formatCurrency($invoice['paid_amount']) ?></th><th class="no-print"></th></tr>
                    <tr><th colspan="3" class="text-end">Balance</th><th class="text-end text-danger"><?= formatCurrency($invoice['balance']) ?></th><th class="no-print"></th></tr>
                    </tfoot>
                </table>

                <?php if ($invoice['notes']): ?>
                    <p class="small text-muted mb-0">Notes: <?= nl2br(sanitize($invoice['notes'])) ?></p>
                <?php endif; ?>
                <p class="small text-muted mt-4 mb-0"><?= nl2br(sanitize(getSetting('invoice_footer', 'Thank you for choosing our hospital.'))) ?></p>
            </div>
        </div>
    </div>

    <div class="col-lg-4 no-print">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-cash-coin me-2"></i>Record payment</div>
            <div class="card-body">
                <?php if ((float)$invoice['balance'] <= 0): ?>
                    <div class="alert alert-success mb-0"><i class="bi bi-check-circle me-1"></i>This invoice is fully paid.</div>
                <?php else: ?>
                    <form method="post" class="row g-2">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="add_payment">
                        <div class="col-12">
                            <label class="form-label">Amount</label>
                            <input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="<?= (float)$invoice['balance'] ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Method</label>
                            <select name="payment_method" class="form-select">
                                <?php foreach (['Cash', 'Card', 'Bank Transfer', 'Other'] as $m): ?>
                                    <option value="<?= $m ?>"><?= $m ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <input type="text" name="notes" class="form-control">
                        </div>
                        <div class="col-12 d-grid">
                            <button class="btn btn-success"><i class="bi bi-plus-circle me-1"></i>Save payment</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-list-check me-2"></i>Payment history</div>
            <ul class="list-group list-group-flush">
                <?php if (!$payments): ?>
                    <li class="list-group-item small text-muted">No payments recorded.</li>
                <?php endif; ?>
                <?php foreach ($payments as $p): ?>
                    <li class="list-group-item d-flex justify-content-between small">
                        <span><?= formatDateTime($p['payment_date']) ?><br><span class="text-muted"><?= sanitize($p['payment_method']) ?></span></span>
                        <strong class="text-success"><?= formatCurrency($p['amount']) ?></strong>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-plus-square me-2"></i>Add item</div>
            <form method="post" class="card-body row g-2">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="add_item">
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-control" required>
                </div>
                <div class="col-6">
                    <label class="form-label">Qty</label>
                    <input type="number" min="1" name="quantity" class="form-control" value="1">
                </div>
                <div class="col-6">
                    <label class="form-label">Unit price</label>
                    <input type="number" step="0.01" min="0" name="unit_price" class="form-control" value="0">
                </div>
                <div class="col-12 d-grid">
                    <button class="btn btn-outline-primary btn-sm">Add item</button>
                </div>
            </form>
        </div>

        <form method="post" class="card">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_discount">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-percent me-2"></i>Discount &amp; notes</div>
            <div class="card-body row g-2">
                <div class="col-12">
                    <label class="form-label">Discount</label>
                    <input type="number" step="0.01" min="0" name="discount" class="form-control" value="<?= (float)$invoice['discount'] ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"><?= sanitize($invoice['notes']) ?></textarea>
                </div>
                <div class="col-12 d-grid">
                    <button class="btn btn-outline-primary btn-sm">Update invoice</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
if ($printMode) {
    $pageScripts = '<script>window.addEventListener("load", function () { setTimeout(function () { window.print(); }, 300); });</script>';
}
require_once INC_PATH . '/footer.php';
