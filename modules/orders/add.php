<?php
/** Create a laboratory test order for an existing patient. */
require_once __DIR__ . '/../../config/session.php';

$errors = [];
$selectedPatient = null;
$patientId = (int)get('patient_id', post('patient_id'));
if ($patientId) {
    $selectedPatient = fetchOne('SELECT * FROM patients WHERE id = ?', [$patientId]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(BASE_URL . '/modules/orders/add.php');

    $testIds   = array_values(array_unique(array_map('intval', (array)($_POST['tests'] ?? []))));
    $discount  = max(0, (float)post('discount', 0));
    $paid      = max(0, (float)post('paid_amount', 0));
    $method    = post('payment_method', 'Cash');
    $refDoctor = post('referring_doctor');
    $notes     = post('notes');
    $orderDate = post('order_date') ?: date('Y-m-d H:i:s');
    $visitType = post('visit_type', 'Walk-In');
    if (!in_array($visitType, ['Walk-In', 'Appointment', 'Emergency', 'Follow-Up'], true)) {
        $visitType = 'Walk-In';
    }

    if (!in_array($method, ['Cash', 'Card', 'Bank Transfer', 'Other'], true)) {
        $method = 'Cash';
    }
    if (strlen($orderDate) === 16) { // datetime-local value
        $orderDate = str_replace('T', ' ', $orderDate) . ':00';
    }
    if (!$selectedPatient) {
        $errors[] = 'Please select a patient.';
    }
    if (!$testIds) {
        $errors[] = 'Please select at least one test.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $placeholders = implode(',', array_fill(0, count($testIds), '?'));
            $tests = fetchAll("SELECT id, name, price FROM tests WHERE id IN ($placeholders)", $testIds);
            if (!$tests) {
                throw new RuntimeException('Selected tests could not be found.');
            }
            $subtotal = 0.0;
            foreach ($tests as $t) {
                $subtotal += (float)$t['price'];
            }
            $discount = min($discount, $subtotal);
            $net = round($subtotal - $discount, 2);
            $paid = min($paid, $net);
            $remaining = round($net - $paid, 2);
            $payStatus = $remaining <= 0.001 ? 'Paid' : ($paid > 0 ? 'Partial' : 'Pending');

            $visitNumber = generateVisitNumber();
            $pdo->prepare('INSERT INTO visits (visit_number, patient_id, visit_date, visit_type, symptoms, notes, total_charges, payment_status)
                           VALUES (?,?,?,?,NULL,?,?,?)')
                ->execute([$visitNumber, $patientId, $orderDate, $visitType, $notes ?: null, $net, $payStatus]);
            $visitId = (int)$pdo->lastInsertId();

            $orderNumber = generateOrderNumber();
            $pdo->prepare('INSERT INTO test_orders (order_number, patient_id, visit_id, order_date, referring_doctor,
                    total_amount, discount, net_amount, paid_amount, remaining_amount, payment_method, notes)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$orderNumber, $patientId, $visitId, $orderDate, $refDoctor ?: null, $subtotal, $discount,
                    $net, $paid, $remaining, $method, $notes ?: null]);
            $orderId = (int)$pdo->lastInsertId();

            $itemStmt = $pdo->prepare('INSERT INTO test_order_items (order_id, test_id, price, status) VALUES (?,?,?,?)');
            foreach ($tests as $t) {
                $itemStmt->execute([$orderId, $t['id'], $t['price'], 'Ordered']);
            }

            $invoiceNumber = generateInvoiceNumber();
            $pdo->prepare('INSERT INTO invoices (invoice_number, patient_id, visit_id, invoice_date, subtotal, discount, total,
                    paid_amount, balance, payment_method, payment_status, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$invoiceNumber, $patientId, $visitId, $orderDate, $subtotal, $discount, $net, $paid, $remaining,
                    $method, $payStatus, 'Test order ' . $orderNumber]);
            $invoiceId = (int)$pdo->lastInsertId();

            $invItem = $pdo->prepare('INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, total) VALUES (?,?,1,?,?)');
            foreach ($tests as $t) {
                $invItem->execute([$invoiceId, $t['name'], $t['price'], $t['price']]);
            }
            if ($paid > 0) {
                $pdo->prepare('INSERT INTO payments (patient_id, invoice_id, order_id, amount, payment_method, payment_date, notes)
                               VALUES (?,?,?,?,?,?,?)')
                    ->execute([$patientId, $invoiceId, $orderId, $paid, $method, $orderDate, 'Payment for ' . $orderNumber]);
            }

            $pdo->commit();
            logActivity('Test order created', $orderNumber . ' for ' . $selectedPatient['full_name'], 'test_orders', $orderId);
            flash('success', 'Order ' . $orderNumber . ' created successfully.');
            redirect(BASE_URL . '/modules/orders/view.php?id=' . $orderId);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = friendlyError($e, 'Could not create the order. Please try again.');
        }
    }
}

$categories = fetchAll('SELECT c.id, c.name FROM test_categories c WHERE c.is_active = 1 ORDER BY c.sort_order, c.name');
$testsByCategory = [];
foreach (fetchAll('SELECT id, test_code, name, price, category_id, sample_type FROM tests WHERE is_active = 1 ORDER BY name') as $t) {
    $testsByCategory[(int)$t['category_id']][] = $t;
}
$doctors = fetchAll('SELECT id, name, specialty FROM doctors WHERE is_active = 1 ORDER BY name');

$pageTitle = 'New Test Order';
$activeMenu = 'orders';
$pageActions = '<a href="' . BASE_URL . '/modules/orders/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>All orders</a>';
require_once INC_PATH . '/header.php';
?>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-octagon me-2"></i><?= sanitize($error) ?></div>
<?php endforeach; ?>

<form method="post" id="orderForm" class="row g-3">
    <?= csrfField() ?>
    <input type="hidden" name="patient_id" id="patientIdField" value="<?= $selectedPatient ? (int)$selectedPatient['id'] : '' ?>">

    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-person-badge me-2"></i>Patient</div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-8 position-relative">
                        <input type="text" id="patientSearch" class="form-control" autocomplete="off"
                               placeholder="Search by name, patient ID, phone or CNIC">
                        <div id="patientResults" class="list-group search-results"></div>
                    </div>
                    <div class="col-md-4">
                        <a href="<?= BASE_URL ?>/modules/patients/add.php" class="btn btn-outline-primary w-100"><i class="bi bi-person-plus me-1"></i>New patient</a>
                    </div>
                </div>
                <div id="patientBox" class="alert alert-info mt-3 mb-0 <?= $selectedPatient ? '' : 'd-none' ?>">
                    <span id="patientBoxText">
                        <?php if ($selectedPatient): ?>
                            <strong><?= sanitize($selectedPatient['full_name']) ?></strong> ·
                            <?= sanitize($selectedPatient['patient_id']) ?> ·
                            <?= sanitize($selectedPatient['gender']) ?>
                            <?= $selectedPatient['phone'] ? ' · ' . sanitize($selectedPatient['phone']) : '' ?>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-droplet-half me-2"></i>Select tests</span>
                <input type="search" id="testFilter" class="form-control form-control-sm w-auto" placeholder="Filter tests...">
            </div>
            <div class="card-body" style="max-height:520px; overflow:auto">
                <?php foreach ($categories as $c):
                    $list = $testsByCategory[(int)$c['id']] ?? [];
                    if (!$list) { continue; } ?>
                    <div class="mb-3 test-group">
                        <div class="fw-semibold text-primary small text-uppercase mb-2"><?= sanitize($c['name']) ?></div>
                        <div class="row g-2">
                            <?php foreach ($list as $t): ?>
                                <div class="col-md-6 test-option" data-name="<?= sanitize(strtolower($t['name'] . ' ' . $t['test_code'])) ?>">
                                    <label class="test-pick d-flex justify-content-between align-items-center border rounded p-2">
                                        <span class="d-flex align-items-center gap-2">
                                            <input class="form-check-input mt-0 test-checkbox" type="checkbox" name="tests[]"
                                                   value="<?= (int)$t['id'] ?>" data-price="<?= (float)$t['price'] ?>"
                                                   data-name="<?= sanitize($t['name']) ?>">
                                            <span>
                                                <span class="d-block small fw-semibold"><?= sanitize($t['name']) ?></span>
                                                <small class="text-muted"><?= sanitize($t['sample_type'] ?: $t['test_code']) ?></small>
                                            </span>
                                        </span>
                                        <span class="badge bg-light text-dark"><?= formatCurrency($t['price']) ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php $uncategorised = $testsByCategory[0] ?? []; ?>
                <?php if ($uncategorised): ?>
                    <div class="mb-3 test-group">
                        <div class="fw-semibold text-primary small text-uppercase mb-2">Other</div>
                        <div class="row g-2">
                            <?php foreach ($uncategorised as $t): ?>
                                <div class="col-md-6 test-option" data-name="<?= sanitize(strtolower($t['name'])) ?>">
                                    <label class="test-pick d-flex justify-content-between align-items-center border rounded p-2">
                                        <span class="d-flex align-items-center gap-2">
                                            <input class="form-check-input mt-0 test-checkbox" type="checkbox" name="tests[]"
                                                   value="<?= (int)$t['id'] ?>" data-price="<?= (float)$t['price'] ?>"
                                                   data-name="<?= sanitize($t['name']) ?>">
                                            <span class="small fw-semibold"><?= sanitize($t['name']) ?></span>
                                        </span>
                                        <span class="badge bg-light text-dark"><?= formatCurrency($t['price']) ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card sticky-lg-top" style="top:1rem">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-receipt me-2"></i>Order summary</div>
            <div class="card-body">
                <ul class="list-group list-group-flush mb-3" id="selectedList">
                    <li class="list-group-item text-muted small px-0">No tests selected yet.</li>
                </ul>
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label">Order date &amp; time</label>
                        <input type="datetime-local" name="order_date" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Visit type</label>
                        <select name="visit_type" class="form-select">
                            <?php foreach (['Walk-In', 'Appointment', 'Emergency', 'Follow-Up'] as $vt): ?>
                                <option value="<?= $vt ?>" <?= post('visit_type', 'Walk-In') === $vt ? 'selected' : '' ?>><?= $vt ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Referring doctor</label>
                        <input type="text" name="referring_doctor" class="form-control" list="doctorList"
                               value="<?= sanitize(post('referring_doctor')) ?>">
                        <datalist id="doctorList">
                            <?php foreach ($doctors as $d): ?>
                                <option value="<?= sanitize($d['name']) ?>"><?= sanitize($d['specialty']) ?></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Discount</label>
                        <input type="number" step="0.01" min="0" name="discount" id="discount" class="form-control" value="<?= sanitize(post('discount', '0')) ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Paid amount</label>
                        <input type="number" step="0.01" min="0" name="paid_amount" id="paidAmount" class="form-control" value="<?= sanitize(post('paid_amount', '0')) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Payment method</label>
                        <select name="payment_method" class="form-select">
                            <?php foreach (['Cash', 'Card', 'Bank Transfer', 'Other'] as $m): ?>
                                <option value="<?= $m ?>" <?= post('payment_method') === $m ? 'selected' : '' ?>><?= $m ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"><?= sanitize(post('notes')) ?></textarea>
                    </div>
                </div>
                <hr>
                <div class="d-flex justify-content-between small"><span>Subtotal</span><strong id="sumSubtotal"><?= formatCurrency(0) ?></strong></div>
                <div class="d-flex justify-content-between small"><span>Discount</span><strong id="sumDiscount"><?= formatCurrency(0) ?></strong></div>
                <div class="d-flex justify-content-between"><span>Net payable</span><strong id="sumNet" class="text-primary"><?= formatCurrency(0) ?></strong></div>
                <div class="d-flex justify-content-between small"><span>Balance</span><strong id="sumBalance" class="text-danger"><?= formatCurrency(0) ?></strong></div>
            </div>
            <div class="card-footer bg-white d-grid gap-2">
                <button class="btn btn-primary" id="submitBtn"><i class="bi bi-check2-circle me-1"></i>Create order</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="payFull">Mark as fully paid</button>
            </div>
        </div>
    </div>
</form>

<?php
$currency = currencySymbol();
$pageScripts = <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function () {
    var CURRENCY = '{$currency}';
    var boxes = Array.prototype.slice.call(document.querySelectorAll('.test-checkbox'));
    var list = document.getElementById('selectedList');
    var discountEl = document.getElementById('discount');
    var paidEl = document.getElementById('paidAmount');

    function money(v) { return CURRENCY + ' ' + Number(v || 0).toFixed(2); }

    function refresh() {
        var subtotal = 0, items = [];
        boxes.forEach(function (b) {
            if (b.checked) {
                subtotal += parseFloat(b.dataset.price || 0);
                items.push({ name: b.dataset.name, price: parseFloat(b.dataset.price || 0) });
            }
        });
        var discount = Math.min(parseFloat(discountEl.value || 0), subtotal);
        var net = Math.max(0, subtotal - discount);
        var paid = Math.min(parseFloat(paidEl.value || 0), net);
        list.innerHTML = items.length ? '' : '<li class="list-group-item text-muted small px-0">No tests selected yet.</li>';
        items.forEach(function (i) {
            var li = document.createElement('li');
            li.className = 'list-group-item d-flex justify-content-between px-0 py-1 small';
            li.innerHTML = '<span></span><span class="text-muted"></span>';
            li.children[0].textContent = i.name;
            li.children[1].textContent = money(i.price);
            list.appendChild(li);
        });
        document.getElementById('sumSubtotal').textContent = money(subtotal);
        document.getElementById('sumDiscount').textContent = money(discount);
        document.getElementById('sumNet').textContent = money(net);
        document.getElementById('sumBalance').textContent = money(net - paid);
    }

    boxes.forEach(function (b) { b.addEventListener('change', refresh); });
    discountEl.addEventListener('input', refresh);
    paidEl.addEventListener('input', refresh);
    document.getElementById('payFull').addEventListener('click', function () {
        var subtotal = 0;
        boxes.forEach(function (b) { if (b.checked) { subtotal += parseFloat(b.dataset.price || 0); } });
        paidEl.value = Math.max(0, subtotal - parseFloat(discountEl.value || 0)).toFixed(2);
        refresh();
    });

    var filter = document.getElementById('testFilter');
    filter.addEventListener('input', function () {
        var q = filter.value.toLowerCase();
        document.querySelectorAll('.test-option').forEach(function (el) {
            el.style.display = !q || el.dataset.name.indexOf(q) > -1 ? '' : 'none';
        });
    });

    if (window.HMS && HMS.patientSearch) {
        HMS.patientSearch(document.getElementById('patientSearch'), document.getElementById('patientResults'), function (p) {
            document.getElementById('patientIdField').value = p.id;
            var box = document.getElementById('patientBox');
            box.classList.remove('d-none');
            document.getElementById('patientBoxText').textContent = p.full_name + ' · ' + p.patient_id + ' · ' + p.gender + (p.phone ? ' · ' + p.phone : '');
        });
    }

    document.getElementById('orderForm').addEventListener('submit', function (ev) {
        if (!document.getElementById('patientIdField').value) {
            ev.preventDefault();
            alert('Please select a patient first.');
            return;
        }
        if (!boxes.some(function (b) { return b.checked; })) {
            ev.preventDefault();
            alert('Please select at least one test.');
        }
    });

    refresh();
});
</script>
HTML;
require_once INC_PATH . '/footer.php';
