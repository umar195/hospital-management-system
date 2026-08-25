<?php
/** Read-only consultation summary with everything linked to the visit. */
require_once __DIR__ . '/../../config/session.php';

$id = (int)get('id');
$visit = $id ? fetchOne('SELECT v.*, p.full_name, p.patient_id AS patient_code, p.gender, p.age, p.date_of_birth,
                         p.phone, p.whatsapp, p.blood_group, d.name AS doctor_name, d.specialty
                         FROM visits v
                         JOIN patients p ON p.id = v.patient_id
                         LEFT JOIN doctors d ON d.id = v.doctor_id
                         WHERE v.id = ?', [$id]) : null;
if (!$visit) {
    flash('danger', 'Consultation not found.');
    redirect(BASE_URL . '/modules/consultations/index.php');
}

$prescriptions = fetchAll('SELECT pr.*, (SELECT COUNT(*) FROM prescription_items i WHERE i.prescription_id = pr.id) AS item_count
                           FROM prescriptions pr WHERE pr.visit_id = ? ORDER BY pr.id', [$id]);
$orders = fetchAll('SELECT o.*, (SELECT COUNT(*) FROM test_order_items i WHERE i.order_id = o.id) AS item_count,
                    (SELECT COUNT(*) FROM test_order_items i WHERE i.order_id = o.id AND i.status IN ("Completed","Delivered")) AS done_count
                    FROM test_orders o WHERE o.visit_id = ? ORDER BY o.id', [$id]);
$invoices = fetchAll('SELECT * FROM invoices WHERE visit_id = ? ORDER BY id', [$id]);
$followUps = fetchAll('SELECT f.*, d.name AS doctor_name FROM follow_ups f LEFT JOIN doctors d ON d.id = f.doctor_id
                       WHERE f.visit_id = ? ORDER BY f.follow_up_date', [$id]);
$originVisit = $visit['follow_up_of_visit_id']
    ? fetchOne('SELECT id, visit_number, visit_date FROM visits WHERE id = ?', [(int)$visit['follow_up_of_visit_id']]) : null;
$childVisits = fetchAll('SELECT id, visit_number, visit_date FROM visits WHERE follow_up_of_visit_id = ? ORDER BY visit_date', [$id]);

$age = $visit['age'] !== null ? (int)$visit['age'] : ($visit['date_of_birth'] ? calculateAge($visit['date_of_birth']) : null);

$pageTitle = 'Consultation ' . $visit['visit_number'];
$pageSubtitle = $visit['full_name'] . ' · ' . formatDateTime($visit['visit_date']);
$activeMenu = 'consultations';
$pageActions = '<a href="' . BASE_URL . '/modules/patients/view.php?id=' . (int)$visit['patient_id'] . '" class="btn btn-outline-primary"><i class="bi bi-person me-1"></i>Patient profile</a> '
    . (!$visit['consultation_completed_at']
        ? '<a href="' . BASE_URL . '/modules/consultations/consult.php?visit_id=' . $id . '" class="btn btn-primary"><i class="bi bi-pencil-square me-1"></i>Continue consultation</a> '
        : '')
    . '<a href="' . BASE_URL . '/modules/consultations/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>All consultations</a>';
require_once INC_PATH . '/header.php';

$vitals = array_filter([
    'Temperature' => $visit['temperature'] ? $visit['temperature'] . ' °F' : null,
    'Blood pressure' => $visit['blood_pressure'] ? $visit['blood_pressure'] . ' mmHg' : null,
    'Pulse' => $visit['pulse'] ? $visit['pulse'] . ' bpm' : null,
    'Resp. rate' => $visit['respiratory_rate'] ? $visit['respiratory_rate'] . ' /min' : null,
    'SpO2' => $visit['oxygen_saturation'] ? $visit['oxygen_saturation'] . ' %' : null,
    'Weight' => $visit['weight'] ? $visit['weight'] . ' kg' : null,
    'Height' => $visit['height'] ? $visit['height'] . ' cm' : null,
]);
?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header fw-semibold"><i class="bi bi-person-vcard me-2"></i>Visit summary</div>
            <div class="card-body">
                <dl class="detail-list mb-0">
                    <dt>Patient</dt>
                    <dd><a href="<?= BASE_URL ?>/modules/patients/view.php?id=<?= (int)$visit['patient_id'] ?>"><?= sanitize($visit['full_name']) ?></a>
                        <div class="small text-muted"><?= sanitize($visit['patient_code']) ?> · <?= sanitize($visit['gender']) ?><?= $age !== null ? ' · ' . $age . 'y' : '' ?></div></dd>
                    <dt>Doctor</dt><dd><?= sanitize($visit['doctor_name'] ?: '-') ?><?= $visit['specialty'] ? ' <span class="text-muted small">(' . sanitize($visit['specialty']) . ')</span>' : '' ?></dd>
                    <dt>Visit type</dt><dd><?= sanitize($visit['visit_type']) ?> <?= $visit['token_number'] ? tokenBadge($visit['token_number']) : '' ?></dd>
                    <dt>Status</dt><dd><?= $visit['queue_status'] ? statusBadge($visit['queue_status']) : statusBadge($visit['consultation_completed_at'] ? 'Completed' : 'Pending') ?></dd>
                    <?php if ($visit['checked_in_at']): ?><dt>Checked in</dt><dd><?= formatDateTime($visit['checked_in_at']) ?></dd><?php endif; ?>
                    <?php if ($visit['consultation_started_at']): ?><dt>Started</dt><dd><?= formatDateTime($visit['consultation_started_at']) ?></dd><?php endif; ?>
                    <?php if ($visit['consultation_completed_at']): ?><dt>Completed</dt><dd><?= formatDateTime($visit['consultation_completed_at']) ?></dd><?php endif; ?>
                    <dt>Charges</dt><dd><?= formatCurrency($visit['total_charges']) ?> · <?= statusBadge($visit['payment_status']) ?></dd>
                    <?php if ($originVisit): ?>
                        <dt>Follow-up of</dt>
                        <dd><a href="?id=<?= (int)$originVisit['id'] ?>"><?= sanitize($originVisit['visit_number']) ?></a>
                            <span class="text-muted small">(<?= formatDate($originVisit['visit_date']) ?>)</span></dd>
                    <?php endif; ?>
                    <?php if ($childVisits): ?>
                        <dt>Follow-up visits</dt>
                        <dd><?php foreach ($childVisits as $cv): ?>
                            <a href="?id=<?= (int)$cv['id'] ?>"><?= sanitize($cv['visit_number']) ?></a>
                            <span class="text-muted small">(<?= formatDate($cv['visit_date']) ?>)</span><br>
                        <?php endforeach; ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <?php if ($vitals): ?>
            <div class="card mb-3">
                <div class="card-header fw-semibold"><i class="bi bi-heart-pulse me-2"></i>Vital signs</div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($vitals as $label => $value): ?>
                        <li class="list-group-item d-flex justify-content-between small"><span class="text-muted"><?= $label ?></span><strong><?= sanitize($value) ?></strong></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header fw-semibold"><i class="bi bi-receipt me-2"></i>Billing</div>
            <ul class="list-group list-group-flush">
                <?php if (!$invoices): ?><li class="list-group-item small text-muted">No invoice for this visit.</li><?php endif; ?>
                <?php foreach ($invoices as $inv): ?>
                    <li class="list-group-item small">
                        <div class="d-flex justify-content-between">
                            <a href="<?= BASE_URL ?>/modules/billing/invoice_view.php?id=<?= (int)$inv['id'] ?>"><?= sanitize($inv['invoice_number']) ?></a>
                            <?= statusBadge($inv['payment_status']) ?>
                        </div>
                        <div class="d-flex justify-content-between text-muted mt-1">
                            <span>Total <?= formatCurrency($inv['total']) ?></span>
                            <span>Due <?= formatCurrency($inv['balance']) ?></span>
                        </div>
                        <?php if ((float)$inv['balance'] > 0): ?>
                            <a href="<?= BASE_URL ?>/modules/billing/add_payment.php?invoice_id=<?= (int)$inv['id'] ?>" class="btn btn-sm btn-outline-success w-100 mt-2"><i class="bi bi-cash-coin me-1"></i>Record payment</a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header fw-semibold"><i class="bi bi-journal-medical me-2"></i>Clinical record</div>
            <div class="card-body">
                <dl class="detail-list mb-0">
                    <dt>Chief complaint</dt><dd><?= sanitize($visit['chief_complaint'] ?: '-') ?></dd>
                    <dt>Symptoms</dt><dd><?= sanitize($visit['symptoms'] ?: '-') ?></dd>
                    <dt>Examination / clinical notes</dt><dd><?= nl2br(sanitize($visit['examination_notes'] ?: '-')) ?></dd>
                    <dt>Diagnosis</dt><dd><?= nl2br(sanitize($visit['diagnosis'] ?: '-')) ?></dd>
                    <dt>Doctor notes / advice</dt><dd><?= nl2br(sanitize($visit['doctor_notes'] ?: '-')) ?></dd>
                </dl>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><span class="rx-symbol me-1">℞</span>Prescriptions</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Date</th><th>Medicines</th><th></th></tr></thead>
                    <tbody>
                    <?php if (!$prescriptions): ?><tr><td colspan="3" class="text-center text-muted py-3">No prescription for this visit.</td></tr><?php endif; ?>
                    <?php foreach ($prescriptions as $rx): ?>
                        <tr>
                            <td class="small"><?= formatDate($rx['prescription_date']) ?></td>
                            <td class="small"><?= (int)$rx['item_count'] ?> medicine(s)</td>
                            <td class="text-end">
                                <a href="<?= BASE_URL ?>/modules/prescriptions/index.php?id=<?= (int)$rx['id'] ?>" class="btn btn-sm btn-outline-primary py-0"><i class="bi bi-eye me-1"></i>View / print</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header fw-semibold"><i class="bi bi-droplet-half me-2"></i>Laboratory orders</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Order #</th><th>Tests</th><th>Amount</th><th>Progress</th><th></th></tr></thead>
                    <tbody>
                    <?php if (!$orders): ?><tr><td colspan="5" class="text-center text-muted py-3">No lab tests ordered during this visit.</td></tr><?php endif; ?>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td class="small"><?= sanitize($o['order_number']) ?></td>
                            <td class="small"><?= (int)$o['item_count'] ?></td>
                            <td class="small"><?= formatCurrency($o['net_amount']) ?></td>
                            <td class="small"><?= (int)$o['done_count'] ?>/<?= (int)$o['item_count'] ?> done</td>
                            <td class="text-end text-nowrap">
                                <a href="<?= BASE_URL ?>/modules/orders/view.php?id=<?= (int)$o['id'] ?>" class="btn btn-sm btn-outline-primary py-0"><i class="bi bi-eye"></i></a>
                                <a href="<?= BASE_URL ?>/modules/orders/results.php?id=<?= (int)$o['id'] ?>" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-pencil-square"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header fw-semibold"><i class="bi bi-arrow-repeat me-2"></i>Follow-ups scheduled from this visit</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Date</th><th>Doctor</th><th>Type</th><th>Reason</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php if (!$followUps): ?><tr><td colspan="6" class="text-center text-muted py-3">No follow-up scheduled.</td></tr><?php endif; ?>
                    <?php foreach ($followUps as $f): ?>
                        <tr>
                            <td class="small"><?= formatDate($f['follow_up_date']) ?><?= $f['follow_up_time'] ? ' ' . formatTime($f['follow_up_time']) : '' ?></td>
                            <td class="small"><?= sanitize($f['doctor_name'] ?: '-') ?></td>
                            <td class="small"><?= sanitize($f['follow_up_type']) ?></td>
                            <td class="small"><?= sanitize($f['reason'] ?: '-') ?></td>
                            <td><?= statusBadge(followUpDisplayStatus($f)) ?></td>
                            <td class="text-end">
                                <a href="<?= BASE_URL ?>/modules/followups/index.php" class="btn btn-sm btn-outline-primary py-0"><i class="bi bi-box-arrow-up-right"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once INC_PATH . '/footer.php'; ?>
