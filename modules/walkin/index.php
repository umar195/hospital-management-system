<?php
/**
 * Walk-in registration workflow.
 *
 * Step 1  find an existing patient (AJAX) or register a new one
 * Step 2  pick the laboratory tests
 * Step 3  billing (discount / payment)
 * Step 4  confirmation + printable receipt
 *
 * Everything is submitted as a single POST which creates the patient (when new),
 * a visit, a test order with its items, an invoice with items and the payment.
 */
require_once __DIR__ . '/../../config/session.php';

$receiptOrderId = (int)get('receipt');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(BASE_URL . '/modules/walkin/index.php');

    $mode      = post('patient_mode', 'existing');
    $patientId = (int)post('patient_id');
    $testIds   = array_values(array_unique(array_map('intval', (array)($_POST['tests'] ?? []))));
    $discount  = max(0, (float)post('discount', 0));
    $paid      = max(0, (float)post('paid_amount', 0));
    $method    = post('payment_method', 'Cash');
    $doctorId  = (int)post('doctor_id') ?: null;
    $refDoctor = post('referring_doctor');
    $symptoms  = post('symptoms');
    $notes     = post('notes');
    $visitType = post('visit_type', 'Walk-In');

    if (!in_array($method, ['Cash', 'Card', 'Bank Transfer', 'Other'], true)) {
        $method = 'Cash';
    }
    if (!in_array($visitType, ['Walk-In', 'Appointment', 'Emergency', 'Follow-Up'], true)) {
        $visitType = 'Walk-In';
    }
    if (!$testIds) {
        $errors[] = 'Please select at least one test.';
    }
    if ($mode === 'new') {
        if (post('new_full_name') === '') {
            $errors[] = 'Patient name is required for a new registration.';
        }
    } elseif ($patientId <= 0) {
        $errors[] = 'Please select a patient first.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // ---------------------------------------------------- patient
            if ($mode === 'new') {
                $code = generatePatientId();
                $dob = post('new_dob') ?: null;
                $age = post('new_age');
                if ($age === '' && $dob) {
                    $age = calculateAge($dob);
                }
                $stmt = $pdo->prepare('INSERT INTO patients (patient_id, full_name, father_husband_name, gender, date_of_birth,
                        age, cnic, phone, whatsapp, address, blood_group, referring_doctor, registration_date)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $gender = post('new_gender', 'Male');
                $stmt->execute([
                    $code,
                    post('new_full_name'),
                    post('new_father') ?: null,
                    in_array($gender, ['Male', 'Female', 'Other'], true) ? $gender : 'Male',
                    $dob,
                    $age !== '' && $age !== null ? (int)$age : null,
                    post('new_cnic') ?: null,
                    post('new_phone') ?: null,
                    post('new_whatsapp') ?: (post('new_phone') ?: null),
                    post('new_address') ?: null,
                    post('new_blood_group') ?: null,
                    $refDoctor ?: null,
                    date('Y-m-d'),
                ]);
                $patientId = (int)$pdo->lastInsertId();
                logActivity('Patient created', 'Walk-in registration ' . $code, 'patients', $patientId);
            }

            $patient = fetchOne('SELECT * FROM patients WHERE id = ?', [$patientId]);
            if (!$patient) {
                throw new RuntimeException('Selected patient could not be found.');
            }

            // ---------------------------------------------------- tests
            $placeholders = implode(',', array_fill(0, count($testIds), '?'));
            $tests = fetchAll("SELECT id, name, price FROM tests WHERE id IN ($placeholders) AND is_active = 1", $testIds);
            if (!$tests) {
                throw new RuntimeException('The selected tests are no longer available.');
            }
            $subtotal = 0.0;
            foreach ($tests as $t) {
                $subtotal += (float)$t['price'];
            }
            $discount = min($discount, $subtotal);
            $net = round($subtotal - $discount, 2);
            $paid = min($paid, $net);
            $remaining = round($net - $paid, 2);

            // ---------------------------------------------------- visit
            $visitNumber = generateVisitNumber();
            $stmt = $pdo->prepare('INSERT INTO visits (visit_number, patient_id, visit_date, visit_type, doctor_id, symptoms,
                                   notes, total_charges, payment_status) VALUES (?,?,NOW(),?,?,?,?,?,?)');
            $status = $remaining <= 0.001 ? 'Paid' : ($paid > 0 ? 'Partial' : 'Pending');
            $stmt->execute([$visitNumber, $patientId, $visitType, $doctorId, $symptoms ?: null, $notes ?: null, $net, $status]);
            $visitId = (int)$pdo->lastInsertId();

            // ---------------------------------------------------- test order
            $orderNumber = generateOrderNumber();
            $stmt = $pdo->prepare('INSERT INTO test_orders (order_number, patient_id, visit_id, order_date, referring_doctor,
                    total_amount, discount, net_amount, paid_amount, remaining_amount, payment_method, notes)
                    VALUES (?,?,?,NOW(),?,?,?,?,?,?,?,?)');
            $stmt->execute([$orderNumber, $patientId, $visitId, $refDoctor ?: null, $subtotal, $discount, $net, $paid, $remaining, $method, $notes ?: null]);
            $orderId = (int)$pdo->lastInsertId();

            $itemStmt = $pdo->prepare('INSERT INTO test_order_items (order_id, test_id, price, status) VALUES (?,?,?,"Ordered")');
            foreach ($tests as $t) {
                $itemStmt->execute([$orderId, $t['id'], $t['price']]);
            }

            // ---------------------------------------------------- invoice
            $invoiceNumber = generateInvoiceNumber();
            $stmt = $pdo->prepare('INSERT INTO invoices (invoice_number, patient_id, visit_id, invoice_date, subtotal, discount,
                    total, paid_amount, balance, payment_method, payment_status, notes)
                    VALUES (?,?,?,NOW(),?,?,?,?,?,?,?,?)');
            $stmt->execute([$invoiceNumber, $patientId, $visitId, $subtotal, $discount, $net, $paid, $remaining, $method, $status,
                'Walk-in order ' . $orderNumber]);
            $invoiceId = (int)$pdo->lastInsertId();

            $invItem = $pdo->prepare('INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, total) VALUES (?,?,1,?,?)');
            foreach ($tests as $t) {
                $invItem->execute([$invoiceId, $t['name'], $t['price'], $t['price']]);
            }

            // ---------------------------------------------------- payment
            if ($paid > 0) {
                $stmt = $pdo->prepare('INSERT INTO payments (patient_id, invoice_id, order_id, amount, payment_method, payment_date, notes)
                                       VALUES (?,?,?,?,?,NOW(),?)');
                $stmt->execute([$patientId, $invoiceId, $orderId, $paid, $method, 'Walk-in payment for ' . $orderNumber]);
            }

            $pdo->commit();
            logActivity('Walk-in completed', $orderNumber . ' for ' . $patient['full_name'], 'test_orders', $orderId);
            flash('success', 'Walk-in registered. Order ' . $orderNumber . ' created successfully.');
            redirect(BASE_URL . '/modules/walkin/index.php?receipt=' . $orderId);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Could not complete the walk-in: ' . $e->getMessage();
        }
    }
}

$categories = fetchAll('SELECT * FROM test_categories WHERE is_active = 1 ORDER BY sort_order, name');
$testsByCategory = [];
foreach (fetchAll('SELECT id, test_code, name, price, category_id, sample_type FROM tests WHERE is_active = 1 ORDER BY name') as $t) {
    $testsByCategory[(int)$t['category_id']][] = $t;
}
$doctors = fetchAll('SELECT id, name, specialty FROM doctors WHERE is_active = 1 ORDER BY name');

$pageTitle = 'Walk-In Registration';
$pageSubtitle = 'Register a patient, order tests and collect payment in one screen';
$activeMenu = 'walkin';
require_once INC_PATH . '/header.php';

// ---------------------------------------------------------------------------
// Receipt view
// ---------------------------------------------------------------------------
if ($receiptOrderId) {
    $order = fetchOne('SELECT o.*, p.full_name, p.patient_id AS patient_code, p.phone, p.whatsapp, p.gender, p.age
                       FROM test_orders o JOIN patients p ON p.id = o.patient_id WHERE o.id = ?', [$receiptOrderId]);
    if ($order) {
        $items = fetchAll('SELECT i.*, t.name, t.test_code FROM test_order_items i
                           JOIN tests t ON t.id = i.test_id WHERE i.order_id = ?', [$receiptOrderId]);
        $invoice = fetchOne('SELECT * FROM invoices WHERE notes LIKE ? ORDER BY id DESC LIMIT 1', ['%' . $order['order_number'] . '%']);
        $waMessage = 'Dear ' . $order['full_name'] . ", your tests have been booked at " . getSetting('hospital_name', 'our hospital')
            . '. Order number: ' . $order['order_number'] . '. Amount: ' . formatCurrency($order['net_amount'])
            . '. We will inform you as soon as the report is ready.';
        ?>
        <div class="alert alert-success no-print">
            <i class="bi bi-check-circle me-2"></i><strong>Walk-in completed.</strong>
            Order <strong><?= sanitize($order['order_number']) ?></strong> created for <?= sanitize($order['full_name']) ?>.
        </div>

        <div class="d-flex flex-wrap gap-2 mb-3 no-print">
            <button class="btn btn-primary" data-print><i class="bi bi-printer me-1"></i>Print receipt</button>
            <a class="btn btn-success" target="_blank" rel="noopener" href="<?= sanitize(whatsappLink($order['whatsapp'] ?: $order['phone'], $waMessage)) ?>">
                <i class="bi bi-whatsapp me-1"></i>Send on WhatsApp
            </a>
            <a class="btn btn-outline-primary" href="<?= BASE_URL ?>/modules/orders/view.php?id=<?= (int)$order['id'] ?>"><i class="bi bi-clipboard2-pulse me-1"></i>Open order</a>
            <a class="btn btn-outline-primary" href="<?= BASE_URL ?>/modules/orders/results.php?id=<?= (int)$order['id'] ?>"><i class="bi bi-pencil-square me-1"></i>Enter results</a>
            <?php if ($invoice): ?>
                <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/modules/billing/invoice_view.php?id=<?= (int)$invoice['id'] ?>"><i class="bi bi-receipt me-1"></i>Invoice</a>
            <?php endif; ?>
            <a class="btn btn-outline-success" href="<?= BASE_URL ?>/modules/walkin/index.php"><i class="bi bi-plus-circle me-1"></i>New walk-in</a>
        </div>

        <div class="print-sheet">
            <div class="print-header">
                <div>
                    <h2><?= sanitize(getSetting('hospital_name', 'City Care Hospital')) ?></h2>
                    <div class="muted"><?= sanitize(getSetting('hospital_address')) ?></div>
                    <div class="muted">Ph: <?= sanitize(getSetting('hospital_phone')) ?> &middot; <?= sanitize(getSetting('hospital_email')) ?></div>
                </div>
                <?php if (hospitalLogoUrl()): ?>
                    <img src="<?= sanitize(hospitalLogoUrl()) ?>" class="print-logo" alt="Logo">
                <?php endif; ?>
            </div>
            <div class="print-title">Walk-In Receipt</div>
            <table class="print-meta">
                <tr>
                    <td class="k">Order #</td><td><strong><?= sanitize($order['order_number']) ?></strong></td>
                    <td class="k">Date</td><td><?= formatDateTime($order['order_date']) ?></td>
                </tr>
                <tr>
                    <td class="k">Patient</td><td><?= sanitize($order['full_name']) ?> (<?= sanitize($order['patient_code']) ?>)</td>
                    <td class="k">Gender / Age</td><td><?= sanitize($order['gender']) ?><?= $order['age'] !== null ? ' / ' . (int)$order['age'] . 'y' : '' ?></td>
                </tr>
                <tr>
                    <td class="k">Phone</td><td><?= sanitize($order['phone'] ?: '-') ?></td>
                    <td class="k">Referred by</td><td><?= sanitize($order['referring_doctor'] ?: '-') ?></td>
                </tr>
            </table>
            <table class="print-table">
                <thead><tr><th style="width:60px">#</th><th>Test</th><th>Code</th><th style="width:110px" class="text-end">Price</th></tr></thead>
                <tbody>
                <?php $n = 1; foreach ($items as $item): ?>
                    <tr>
                        <td><?= $n++ ?></td>
                        <td><?= sanitize($item['name']) ?></td>
                        <td><?= sanitize($item['test_code']) ?></td>
                        <td class="text-end"><?= formatCurrency($item['price']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr><th colspan="3" class="text-end">Subtotal</th><th class="text-end"><?= formatCurrency($order['total_amount']) ?></th></tr>
                    <tr><th colspan="3" class="text-end">Discount</th><th class="text-end">- <?= formatCurrency($order['discount']) ?></th></tr>
                    <tr><th colspan="3" class="text-end">Net payable</th><th class="text-end"><?= formatCurrency($order['net_amount']) ?></th></tr>
                    <tr><th colspan="3" class="text-end">Paid (<?= sanitize($order['payment_method']) ?>)</th><th class="text-end"><?= formatCurrency($order['paid_amount']) ?></th></tr>
                    <tr><th colspan="3" class="text-end">Balance</th><th class="text-end"><?= formatCurrency($order['remaining_amount']) ?></th></tr>
                </tfoot>
            </table>
            <div class="print-signature">
                <div>Received by</div>
                <div>Patient signature</div>
            </div>
            <div class="print-footer"><?= sanitize(getSetting('invoice_footer', 'Please keep this receipt for collecting your reports.')) ?></div>
        </div>
        <?php
        require_once INC_PATH . '/footer.php';
        return;
    }
}
?>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-octagon me-2"></i><?= sanitize($error) ?></div>
<?php endforeach; ?>

<div class="step-indicator">
    <div class="step active" data-step-badge="1"><span class="step-no">1</span> Patient</div>
    <div class="step" data-step-badge="2"><span class="step-no">2</span> Tests</div>
    <div class="step" data-step-badge="3"><span class="step-no">3</span> Billing</div>
    <div class="step" data-step-badge="4"><span class="step-no">4</span> Confirm</div>
</div>

<form method="post" id="walkinForm">
    <?= csrfField() ?>
    <input type="hidden" name="patient_mode" id="patientMode" value="existing">
    <input type="hidden" name="patient_id" id="selectedPatientId" value="">

    <!-- ---------------------------------------------------------- Step 1 -->
    <section class="walkin-step" data-step="1">
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-search me-2"></i>Find existing patient</div>
                    <div class="card-body">
                        <input type="text" id="patientSearch" class="form-control" placeholder="Type name, phone or patient ID (min 2 characters)">
                        <div class="list-group mt-2" id="patientResults" style="max-height:260px; overflow:auto"></div>
                        <div id="selectedPatientBox" class="alert alert-success mt-3 d-none">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong id="selPatientName"></strong>
                                    <div class="small" id="selPatientMeta"></div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="clearPatient">Change</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span class="fw-semibold"><i class="bi bi-person-plus me-2"></i>New patient</span>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" id="newPatientToggle">
                            <label class="form-check-label small" for="newPatientToggle">Register new</label>
                        </div>
                    </div>
                    <div class="card-body row g-2 d-none" id="newPatientFields">
                        <div class="col-md-6">
                            <label class="form-label required">Full name</label>
                            <input type="text" name="new_full_name" class="form-control" id="newFullName">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Father / Husband</label>
                            <input type="text" name="new_father" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Gender</label>
                            <select name="new_gender" class="form-select">
                                <option>Male</option><option>Female</option><option>Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Age</label>
                            <input type="number" name="new_age" class="form-control" min="0" max="130">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Date of birth</label>
                            <input type="date" name="new_dob" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="new_phone" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">WhatsApp</label>
                            <input type="text" name="new_whatsapp" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">CNIC</label>
                            <input type="text" name="new_cnic" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Blood group</label>
                            <select name="new_blood_group" class="form-select">
                                <option value="">-- select --</option>
                                <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                                    <option value="<?= $bg ?>"><?= $bg ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="new_address" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="card-body text-muted small" id="newPatientHint">
                        Turn on <strong>Register new</strong> if the patient is not in the system yet.
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex justify-content-end mt-3">
            <button type="button" class="btn btn-primary" data-next="2">Continue to tests <i class="bi bi-arrow-right ms-1"></i></button>
        </div>
    </section>

    <!-- ---------------------------------------------------------- Step 2 -->
    <section class="walkin-step d-none" data-step="2">
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span class="fw-semibold"><i class="bi bi-clipboard2-pulse me-2"></i>Select tests</span>
                        <input type="search" id="testFilter" class="form-control form-control-sm w-50" placeholder="Filter tests...">
                    </div>
                    <div class="card-body" style="max-height:520px; overflow:auto">
                        <?php foreach ($categories as $cat): $catTests = $testsByCategory[(int)$cat['id']] ?? []; ?>
                            <?php if (!$catTests) { continue; } ?>
                            <h6 class="text-uppercase text-muted small fw-bold mt-2"><?= sanitize($cat['name']) ?></h6>
                            <div class="row g-2 mb-2">
                                <?php foreach ($catTests as $t): ?>
                                    <div class="col-md-6 test-item" data-name="<?= sanitize(strtolower($t['name'] . ' ' . $t['test_code'])) ?>">
                                        <div class="test-pick d-flex justify-content-between align-items-center">
                                            <div class="form-check m-0">
                                                <input class="form-check-input test-check" type="checkbox" name="tests[]"
                                                       value="<?= (int)$t['id'] ?>" id="test<?= (int)$t['id'] ?>"
                                                       data-price="<?= (float)$t['price'] ?>" data-name="<?= sanitize($t['name']) ?>">
                                                <label class="form-check-label small" for="test<?= (int)$t['id'] ?>">
                                                    <?= sanitize($t['name']) ?>
                                                    <span class="text-muted d-block" style="font-size:.72rem"><?= sanitize($t['test_code']) ?><?= $t['sample_type'] ? ' · ' . sanitize($t['sample_type']) : '' ?></span>
                                                </label>
                                            </div>
                                            <span class="badge bg-light text-dark"><?= formatCurrency($t['price']) ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$categories): ?>
                            <div class="empty-state"><i class="bi bi-droplet-half"></i>No tests configured yet.
                                <div><a href="<?= BASE_URL ?>/modules/tests/add.php" class="btn btn-sm btn-primary mt-2">Add a test</a></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-cart-check me-2"></i>Selected tests (<span id="testCount">0</span>)</div>
                    <ul class="list-group list-group-flush" id="selectedTests">
                        <li class="list-group-item text-muted small">No tests selected yet.</li>
                    </ul>
                    <div class="card-footer bg-white d-flex justify-content-between">
                        <span>Total</span><strong id="testTotal"><?= formatCurrency(0) ?></strong>
                    </div>
                </div>
                <div class="card mt-3">
                    <div class="card-body row g-2">
                        <div class="col-12">
                            <label class="form-label">Visit type</label>
                            <select name="visit_type" class="form-select form-select-sm">
                                <option>Walk-In</option><option>Appointment</option><option>Emergency</option><option>Follow-Up</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Consulting doctor</label>
                            <select name="doctor_id" class="form-select form-select-sm">
                                <option value="">-- none --</option>
                                <?php foreach ($doctors as $d): ?>
                                    <option value="<?= (int)$d['id'] ?>"><?= sanitize($d['name']) ?><?= $d['specialty'] ? ' (' . sanitize($d['specialty']) . ')' : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Referring doctor</label>
                            <input type="text" name="referring_doctor" class="form-control form-control-sm" list="doctorNames">
                            <datalist id="doctorNames">
                                <?php foreach ($doctors as $d): ?><option value="<?= sanitize($d['name']) ?>"></option><?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Symptoms / complaint</label>
                            <textarea name="symptoms" class="form-control form-control-sm" rows="2"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex justify-content-between mt-3">
            <button type="button" class="btn btn-outline-secondary" data-prev="1"><i class="bi bi-arrow-left me-1"></i>Back</button>
            <button type="button" class="btn btn-primary" data-next="3">Continue to billing <i class="bi bi-arrow-right ms-1"></i></button>
        </div>
    </section>

    <!-- ---------------------------------------------------------- Step 3 -->
    <section class="walkin-step d-none" data-step="3">
        <div class="row g-3 justify-content-center">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-cash-coin me-2"></i>Billing</div>
                    <div class="card-body row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Subtotal</label>
                            <input type="text" class="form-control" id="billSubtotal" value="0.00" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Discount</label>
                            <input type="number" step="0.01" min="0" name="discount" id="billDiscount" class="form-control" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Net payable</label>
                            <input type="text" class="form-control fw-bold" id="billNet" value="0.00" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Amount paid</label>
                            <input type="number" step="0.01" min="0" name="paid_amount" id="billPaid" class="form-control" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Balance</label>
                            <input type="text" class="form-control" id="billBalance" value="0.00" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment method</label>
                            <select name="payment_method" class="form-select">
                                <option>Cash</option><option>Card</option><option>Bank Transfer</option><option>Other</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <button type="button" class="btn btn-sm btn-outline-success" id="payFull"><i class="bi bi-check2-all me-1"></i>Mark as fully paid</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex justify-content-between mt-3">
            <button type="button" class="btn btn-outline-secondary" data-prev="2"><i class="bi bi-arrow-left me-1"></i>Back</button>
            <button type="button" class="btn btn-primary" data-next="4">Review <i class="bi bi-arrow-right ms-1"></i></button>
        </div>
    </section>

    <!-- ---------------------------------------------------------- Step 4 -->
    <section class="walkin-step d-none" data-step="4">
        <div class="row g-3 justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-check2-circle me-2"></i>Review &amp; confirm</div>
                    <div class="card-body">
                        <h6 class="text-uppercase text-muted small fw-bold">Patient</h6>
                        <p id="reviewPatient" class="mb-3">-</p>
                        <h6 class="text-uppercase text-muted small fw-bold">Tests</h6>
                        <ul id="reviewTests" class="mb-3"></ul>
                        <h6 class="text-uppercase text-muted small fw-bold">Billing</h6>
                        <table class="table table-sm w-auto">
                            <tr><td>Subtotal</td><td class="text-end" id="reviewSubtotal">0.00</td></tr>
                            <tr><td>Discount</td><td class="text-end" id="reviewDiscount">0.00</td></tr>
                            <tr><td>Net payable</td><td class="text-end fw-bold" id="reviewNet">0.00</td></tr>
                            <tr><td>Paid</td><td class="text-end" id="reviewPaid">0.00</td></tr>
                            <tr><td>Balance</td><td class="text-end" id="reviewBalance">0.00</td></tr>
                        </table>
                    </div>
                    <div class="card-footer bg-white d-flex justify-content-between">
                        <button type="button" class="btn btn-outline-secondary" data-prev="3"><i class="bi bi-arrow-left me-1"></i>Back</button>
                        <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-check2-circle me-1"></i>Complete walk-in</button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</form>

<?php
$currency = json_encode(currencySymbol());
$pageScripts = <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function () {
    var CURRENCY = {$currency};
    var form = document.getElementById('walkinForm');
    if (!form) { return; }

    var steps = form.querySelectorAll('.walkin-step');
    var badges = document.querySelectorAll('[data-step-badge]');
    var modeInput = document.getElementById('patientMode');
    var patientIdInput = document.getElementById('selectedPatientId');
    var newToggle = document.getElementById('newPatientToggle');
    var newFields = document.getElementById('newPatientFields');
    var newHint = document.getElementById('newPatientHint');
    var selectedBox = document.getElementById('selectedPatientBox');
    var searchInput = document.getElementById('patientSearch');
    var results = document.getElementById('patientResults');

    function money(v) { return CURRENCY + ' ' + (parseFloat(v) || 0).toFixed(2); }

    function showStep(n) {
        steps.forEach(function (s) { s.classList.toggle('d-none', s.dataset.step !== String(n)); });
        badges.forEach(function (b) {
            var idx = parseInt(b.dataset.stepBadge, 10);
            b.classList.toggle('active', idx === n);
            b.classList.toggle('done', idx < n);
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function validateStep(n) {
        if (n >= 2) {
            if (modeInput.value === 'new') {
                var name = document.getElementById('newFullName');
                if (!name.value.trim()) { alert('Please enter the new patient name.'); name.focus(); return false; }
            } else if (!patientIdInput.value) {
                alert('Please search and select a patient, or switch on "Register new".');
                return false;
            }
        }
        if (n >= 3 && form.querySelectorAll('.test-check:checked').length === 0) {
            alert('Please select at least one test.');
            return false;
        }
        return true;
    }

    form.querySelectorAll('[data-next]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = parseInt(btn.dataset.next, 10);
            if (!validateStep(target)) { return; }
            if (target === 3) { recalc(); }
            if (target === 4) { buildReview(); }
            showStep(target);
        });
    });
    form.querySelectorAll('[data-prev]').forEach(function (btn) {
        btn.addEventListener('click', function () { showStep(parseInt(btn.dataset.prev, 10)); });
    });

    // ------------------------------------------------------- patient search
    HMS.patientSearch(searchInput, results, function (p) {
        patientIdInput.value = p.id;
        modeInput.value = 'existing';
        newToggle.checked = false;
        newFields.classList.add('d-none');
        newHint.classList.remove('d-none');
        document.getElementById('selPatientName').textContent = p.full_name + ' (' + p.patient_id + ')';
        document.getElementById('selPatientMeta').textContent = p.gender + (p.age ? ' / ' + p.age + 'y' : '') + ' · ' + (p.phone || 'no phone');
        selectedBox.classList.remove('d-none');
        results.innerHTML = '';
        searchInput.value = '';
    });

    document.getElementById('clearPatient').addEventListener('click', function () {
        patientIdInput.value = '';
        selectedBox.classList.add('d-none');
        searchInput.focus();
    });

    newToggle.addEventListener('change', function () {
        if (newToggle.checked) {
            modeInput.value = 'new';
            patientIdInput.value = '';
            selectedBox.classList.add('d-none');
            newFields.classList.remove('d-none');
            newHint.classList.add('d-none');
        } else {
            modeInput.value = 'existing';
            newFields.classList.add('d-none');
            newHint.classList.remove('d-none');
        }
    });

    // ------------------------------------------------------- test selection
    var filter = document.getElementById('testFilter');
    filter.addEventListener('keyup', function () {
        var term = filter.value.toLowerCase();
        document.querySelectorAll('.test-item').forEach(function (item) {
            item.style.display = item.dataset.name.indexOf(term) > -1 ? '' : 'none';
        });
    });

    function selectedTests() {
        return Array.prototype.slice.call(form.querySelectorAll('.test-check:checked'));
    }

    function refreshSelected() {
        var list = document.getElementById('selectedTests');
        var picked = selectedTests();
        document.getElementById('testCount').textContent = picked.length;
        if (!picked.length) {
            list.innerHTML = '<li class="list-group-item text-muted small">No tests selected yet.</li>';
        } else {
            list.innerHTML = picked.map(function (c) {
                return '<li class="list-group-item d-flex justify-content-between align-items-center py-1">' +
                    '<span class="small">' + c.dataset.name + '</span>' +
                    '<span class="small fw-semibold">' + money(c.dataset.price) + '</span></li>';
            }).join('');
        }
        document.getElementById('testTotal').textContent = money(subtotal());
        recalc();
    }

    function subtotal() {
        return selectedTests().reduce(function (sum, c) { return sum + parseFloat(c.dataset.price || 0); }, 0);
    }

    form.querySelectorAll('.test-check').forEach(function (c) {
        c.addEventListener('change', refreshSelected);
    });

    // ------------------------------------------------------- billing
    var discountEl = document.getElementById('billDiscount');
    var paidEl = document.getElementById('billPaid');

    function recalc() {
        var sub = subtotal();
        var disc = Math.min(parseFloat(discountEl.value) || 0, sub);
        var net = Math.max(0, sub - disc);
        var paid = Math.min(parseFloat(paidEl.value) || 0, net);
        document.getElementById('billSubtotal').value = sub.toFixed(2);
        document.getElementById('billNet').value = net.toFixed(2);
        document.getElementById('billBalance').value = (net - paid).toFixed(2);
    }
    discountEl.addEventListener('input', recalc);
    paidEl.addEventListener('input', recalc);
    document.getElementById('payFull').addEventListener('click', function () {
        var sub = subtotal();
        var disc = Math.min(parseFloat(discountEl.value) || 0, sub);
        paidEl.value = Math.max(0, sub - disc).toFixed(2);
        recalc();
    });

    // ------------------------------------------------------- review
    function buildReview() {
        recalc();
        var patientText;
        if (modeInput.value === 'new') {
            patientText = 'New patient: ' + (document.getElementById('newFullName').value || '-');
        } else {
            patientText = document.getElementById('selPatientName').textContent;
        }
        document.getElementById('reviewPatient').textContent = patientText;
        document.getElementById('reviewTests').innerHTML = selectedTests().map(function (c) {
            return '<li>' + c.dataset.name + ' — ' + money(c.dataset.price) + '</li>';
        }).join('') || '<li class="text-muted">None</li>';
        var sub = subtotal();
        var disc = Math.min(parseFloat(discountEl.value) || 0, sub);
        var net = Math.max(0, sub - disc);
        var paid = Math.min(parseFloat(paidEl.value) || 0, net);
        document.getElementById('reviewSubtotal').textContent = money(sub);
        document.getElementById('reviewDiscount').textContent = money(disc);
        document.getElementById('reviewNet').textContent = money(net);
        document.getElementById('reviewPaid').textContent = money(paid);
        document.getElementById('reviewBalance').textContent = money(net - paid);
    }

    refreshSelected();
});
</script>
HTML;
require_once INC_PATH . '/footer.php';
