<?php
/** Patient profile with complete history. */
require_once __DIR__ . '/../../config/session.php';

$id = (int)get('id');
$patient = fetchOne('SELECT * FROM patients WHERE id = ?', [$id]);
if (!$patient) {
    flash('danger', 'Patient not found.');
    redirect(BASE_URL . '/modules/patients/index.php');
}

$visits = fetchAll('SELECT v.*, d.name AS doctor_name FROM visits v
                    LEFT JOIN doctors d ON d.id = v.doctor_id
                    WHERE v.patient_id = ? ORDER BY v.visit_date DESC LIMIT 25', [$id]);

$orders = fetchAll('SELECT o.*, (SELECT COUNT(*) FROM test_order_items i WHERE i.order_id = o.id) AS item_count
                    FROM test_orders o WHERE o.patient_id = ? ORDER BY o.order_date DESC LIMIT 25', [$id]);

$reports = fetchAll('SELECT r.*, o.order_number FROM reports r
                     JOIN test_orders o ON o.id = r.order_id
                     WHERE r.patient_id = ? ORDER BY r.generated_at DESC LIMIT 25', [$id]);

$invoices = fetchAll('SELECT * FROM invoices WHERE patient_id = ? ORDER BY invoice_date DESC LIMIT 25', [$id]);

$appointments = fetchAll('SELECT a.*, d.name AS doctor_name FROM appointments a
                          LEFT JOIN doctors d ON d.id = a.doctor_id
                          WHERE a.patient_id = ? ORDER BY a.appointment_date DESC LIMIT 25', [$id]);

$prescriptions = fetchAll('SELECT p.*, (SELECT COUNT(*) FROM prescription_items i WHERE i.prescription_id = p.id) AS item_count
                           FROM prescriptions p WHERE p.patient_id = ? ORDER BY p.prescription_date DESC LIMIT 25', [$id]);

$totals = [
    'billed' => (float)fetchValue('SELECT COALESCE(SUM(net_amount),0) FROM test_orders WHERE patient_id = ?', [$id]),
    'paid'   => (float)fetchValue('SELECT COALESCE(SUM(amount),0) FROM payments WHERE patient_id = ?', [$id]),
    'due'    => (float)fetchValue('SELECT COALESCE(SUM(remaining_amount),0) FROM test_orders WHERE patient_id = ?', [$id]),
];

$pageTitle = $patient['full_name'];
$pageSubtitle = 'Patient ID ' . $patient['patient_id'] . ' · Registered ' . formatDate($patient['registration_date']);
$activeMenu = 'patients';
$pageActions = '<a href="' . BASE_URL . '/modules/orders/add.php?patient_id=' . $id . '" class="btn btn-primary"><i class="bi bi-clipboard2-plus me-1"></i>New Test Order</a> '
    . '<a href="' . BASE_URL . '/modules/appointments/add.php?patient_id=' . $id . '" class="btn btn-outline-primary"><i class="bi bi-calendar-plus me-1"></i>Appointment</a> '
    . '<a href="' . BASE_URL . '/modules/prescriptions/add.php?patient_id=' . $id . '" class="btn btn-outline-primary"><i class="bi bi-capsule me-1"></i>Prescription</a> '
    . '<a href="edit.php?id=' . $id . '" class="btn btn-outline-secondary"><i class="bi bi-pencil me-1"></i>Edit</a>';
require_once INC_PATH . '/header.php';
?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="stat-icon bg-primary text-white"><i class="bi bi-person"></i></div>
                    <div>
                        <div class="fw-bold"><?= sanitize($patient['full_name']) ?></div>
                        <div class="small text-muted"><?= sanitize($patient['gender']) ?><?= $patient['age'] !== null ? ' · ' . (int)$patient['age'] . ' years' : '' ?></div>
                    </div>
                </div>
                <dl class="detail-list mb-0">
                    <dt>Patient ID</dt><dd><?= sanitize($patient['patient_id']) ?></dd>
                    <dt>Father / Husband</dt><dd><?= sanitize($patient['father_husband_name'] ?: '-') ?></dd>
                    <dt>Date of birth</dt><dd><?= formatDate($patient['date_of_birth']) ?></dd>
                    <dt>CNIC</dt><dd><?= sanitize($patient['cnic'] ?: '-') ?></dd>
                    <dt>Phone</dt><dd><?= sanitize($patient['phone'] ?: '-') ?></dd>
                    <dt>WhatsApp</dt>
                    <dd>
                        <?php if ($patient['whatsapp']): ?>
                            <a target="_blank" rel="noopener" class="text-success"
                               href="<?= sanitize(whatsappLink($patient['whatsapp'], 'Hello ' . $patient['full_name'] . ', this is ' . getSetting('hospital_name', 'our hospital') . '.')) ?>">
                                <i class="bi bi-whatsapp me-1"></i><?= sanitize($patient['whatsapp']) ?>
                            </a>
                        <?php else: ?>-<?php endif; ?>
                    </dd>
                    <dt>Email</dt><dd><?= sanitize($patient['email'] ?: '-') ?></dd>
                    <dt>Blood group</dt><dd><?= sanitize($patient['blood_group'] ?: '-') ?></dd>
                    <dt>Address</dt><dd><?= nl2br(sanitize($patient['address'] ?: '-')) ?></dd>
                    <dt>Emergency contact</dt><dd><?= sanitize($patient['emergency_contact'] ?: '-') ?></dd>
                    <dt>Referring doctor</dt><dd><?= sanitize($patient['referring_doctor'] ?: '-') ?></dd>
                    <dt>Medical notes</dt><dd><?= nl2br(sanitize($patient['medical_notes'] ?: '-')) ?></dd>
                </dl>
            </div>
        </div>
        <div class="card">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-cash-stack me-2"></i>Financial Summary</div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between"><span>Total billed</span><strong><?= formatCurrency($totals['billed']) ?></strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>Total paid</span><strong class="text-success"><?= formatCurrency($totals['paid']) ?></strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>Outstanding</span><strong class="text-danger"><?= formatCurrency($totals['due']) ?></strong></li>
            </ul>
            <div class="card-footer bg-white">
                <a href="<?= BASE_URL ?>/modules/billing/add_payment.php?patient_id=<?= $id ?>" class="btn btn-sm btn-outline-success w-100">
                    <i class="bi bi-cash-coin me-1"></i>Record payment
                </a>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white p-0">
                <ul class="nav nav-tabs card-header-tabs m-0 px-2 pt-2" role="tablist">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-orders" type="button">Test Orders (<?= count($orders) ?>)</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-reports" type="button">Reports (<?= count($reports) ?>)</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-visits" type="button">Visits (<?= count($visits) ?>)</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-invoices" type="button">Invoices (<?= count($invoices) ?>)</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-appts" type="button">Appointments (<?= count($appointments) ?>)</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-rx" type="button">Prescriptions (<?= count($prescriptions) ?>)</button></li>
                </ul>
            </div>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="tab-orders">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 align-middle">
                            <thead><tr><th>Order #</th><th>Date</th><th>Tests</th><th>Net</th><th>Due</th><th></th></tr></thead>
                            <tbody>
                            <?php if (!$orders): ?><tr><td colspan="6" class="text-center text-muted py-4">No test orders.</td></tr><?php endif; ?>
                            <?php foreach ($orders as $o): ?>
                                <tr>
                                    <td class="small"><?= sanitize($o['order_number']) ?></td>
                                    <td class="small"><?= formatDateTime($o['order_date']) ?></td>
                                    <td class="small"><?= (int)$o['item_count'] ?></td>
                                    <td class="small"><?= formatCurrency($o['net_amount']) ?></td>
                                    <td class="small"><?= $o['remaining_amount'] > 0 ? '<span class="text-danger">' . formatCurrency($o['remaining_amount']) . '</span>' : '<span class="badge bg-success">Paid</span>' ?></td>
                                    <td class="text-end">
                                        <a href="<?= BASE_URL ?>/modules/orders/view.php?id=<?= (int)$o['id'] ?>" class="btn btn-sm btn-outline-primary py-0"><i class="bi bi-eye"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade" id="tab-reports">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 align-middle">
                            <thead><tr><th>Report #</th><th>Order #</th><th>Generated</th><th>Status</th><th></th></tr></thead>
                            <tbody>
                            <?php if (!$reports): ?><tr><td colspan="5" class="text-center text-muted py-4">No reports generated.</td></tr><?php endif; ?>
                            <?php foreach ($reports as $r): ?>
                                <tr>
                                    <td class="small"><?= sanitize($r['report_number']) ?></td>
                                    <td class="small"><?= sanitize($r['order_number']) ?></td>
                                    <td class="small"><?= formatDateTime($r['generated_at']) ?></td>
                                    <td><?= statusBadge($r['status']) ?></td>
                                    <td class="text-end text-nowrap">
                                        <a href="<?= BASE_URL ?>/modules/reports/view.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary py-0"><i class="bi bi-eye"></i></a>
                                        <a href="<?= BASE_URL ?>/modules/reports/print.php?id=<?= (int)$r['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-printer"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade" id="tab-visits">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 align-middle">
                            <thead><tr><th>Visit #</th><th>Date</th><th>Type</th><th>Doctor</th><th>Diagnosis</th><th>Charges</th></tr></thead>
                            <tbody>
                            <?php if (!$visits): ?><tr><td colspan="6" class="text-center text-muted py-4">No visits recorded.</td></tr><?php endif; ?>
                            <?php foreach ($visits as $v): ?>
                                <tr>
                                    <td class="small"><?= sanitize($v['visit_number']) ?></td>
                                    <td class="small"><?= formatDateTime($v['visit_date']) ?></td>
                                    <td class="small"><?= sanitize($v['visit_type']) ?></td>
                                    <td class="small"><?= sanitize($v['doctor_name'] ?: '-') ?></td>
                                    <td class="small"><?= sanitize($v['diagnosis'] ?: '-') ?></td>
                                    <td class="small"><?= formatCurrency($v['total_charges']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade" id="tab-invoices">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 align-middle">
                            <thead><tr><th>Invoice #</th><th>Date</th><th>Total</th><th>Paid</th><th>Status</th><th></th></tr></thead>
                            <tbody>
                            <?php if (!$invoices): ?><tr><td colspan="6" class="text-center text-muted py-4">No invoices.</td></tr><?php endif; ?>
                            <?php foreach ($invoices as $inv): ?>
                                <tr>
                                    <td class="small"><?= sanitize($inv['invoice_number']) ?></td>
                                    <td class="small"><?= formatDateTime($inv['invoice_date']) ?></td>
                                    <td class="small"><?= formatCurrency($inv['total']) ?></td>
                                    <td class="small"><?= formatCurrency($inv['paid_amount']) ?></td>
                                    <td><?= statusBadge($inv['payment_status']) ?></td>
                                    <td class="text-end">
                                        <a href="<?= BASE_URL ?>/modules/billing/invoice_view.php?id=<?= (int)$inv['id'] ?>" class="btn btn-sm btn-outline-primary py-0"><i class="bi bi-eye"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade" id="tab-appts">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 align-middle">
                            <thead><tr><th>Date</th><th>Time</th><th>Doctor</th><th>Purpose</th><th>Status</th><th></th></tr></thead>
                            <tbody>
                            <?php if (!$appointments): ?><tr><td colspan="6" class="text-center text-muted py-4">No appointments.</td></tr><?php endif; ?>
                            <?php foreach ($appointments as $a): ?>
                                <tr>
                                    <td class="small"><?= formatDate($a['appointment_date']) ?></td>
                                    <td class="small"><?= formatTime($a['appointment_time']) ?></td>
                                    <td class="small"><?= sanitize($a['doctor_name'] ?: '-') ?></td>
                                    <td class="small"><?= sanitize($a['purpose'] ?: '-') ?></td>
                                    <td><?= statusBadge($a['status']) ?></td>
                                    <td class="text-end">
                                        <a href="<?= BASE_URL ?>/modules/appointments/edit.php?id=<?= (int)$a['id'] ?>" class="btn btn-sm btn-outline-primary py-0"><i class="bi bi-pencil"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade" id="tab-rx">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 align-middle">
                            <thead><tr><th>Date</th><th>Medicines</th><th>Notes</th><th></th></tr></thead>
                            <tbody>
                            <?php if (!$prescriptions): ?><tr><td colspan="4" class="text-center text-muted py-4">No prescriptions.</td></tr><?php endif; ?>
                            <?php foreach ($prescriptions as $rx): ?>
                                <tr>
                                    <td class="small"><?= formatDate($rx['prescription_date']) ?></td>
                                    <td class="small"><?= (int)$rx['item_count'] ?> item(s)</td>
                                    <td class="small"><?= sanitize(mb_strimwidth((string)$rx['notes'], 0, 60, '…')) ?></td>
                                    <td class="text-end">
                                        <a href="<?= BASE_URL ?>/modules/prescriptions/index.php?id=<?= (int)$rx['id'] ?>" class="btn btn-sm btn-outline-primary py-0"><i class="bi bi-eye"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once INC_PATH . '/footer.php'; ?>
