<?php
/**
 * Doctor consultation screen.
 *
 * Shows the patient's history next to the clinical form (complaint, vitals,
 * examination, diagnosis, notes) and lets the doctor add a prescription,
 * order laboratory tests, and schedule a follow-up. Completing the
 * consultation creates the prescription, lab order, invoice and follow-up in
 * one transaction and moves the queue entry to Completed.
 */
require_once __DIR__ . '/../../config/session.php';

$visitId = (int)(get('visit_id') ?: post('visit_id'));
$visit = $visitId ? fetchOne('SELECT v.*, p.full_name, p.patient_id AS patient_code, p.gender, p.age, p.date_of_birth,
                              p.phone, p.whatsapp, p.blood_group, p.medical_notes
                              FROM visits v JOIN patients p ON p.id = v.patient_id WHERE v.id = ?', [$visitId]) : null;
if (!$visit) {
    flash('danger', 'Consultation visit not found.');
    redirect(BASE_URL . '/modules/queue/index.php');
}
if ($visit['consultation_completed_at']) {
    redirect(BASE_URL . '/modules/consultations/view.php?id=' . $visitId);
}

$patientId = (int)$visit['patient_id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(BASE_URL . '/modules/consultations/consult.php?visit_id=' . $visitId);
    $action = post('action', 'save');

    $doctorId = (int)post('doctor_id') ?: null;
    $fee = max(0, (float)post('consultation_fee', 0));
    $clinical = [
        'chief_complaint'   => post('chief_complaint') ?: null,
        'symptoms'          => post('symptoms') ?: null,
        'temperature'       => post('temperature') ?: null,
        'blood_pressure'    => post('blood_pressure') ?: null,
        'pulse'             => post('pulse') ?: null,
        'respiratory_rate'  => post('respiratory_rate') ?: null,
        'oxygen_saturation' => post('oxygen_saturation') ?: null,
        'weight'            => post('weight') ?: null,
        'height'            => post('height') ?: null,
        'diagnosis'         => post('diagnosis') ?: null,
        'examination_notes' => post('examination_notes') ?: null,
        'doctor_notes'      => post('doctor_notes') ?: null,
    ];

    // Prescription rows
    $rxRows = [];
    foreach ((array)($_POST['medicine'] ?? []) as $i => $medicine) {
        $medicine = trim((string)$medicine);
        if ($medicine === '') {
            continue;
        }
        $rxRows[] = [
            $medicine,
            trim((string)($_POST['dosage'][$i] ?? '')) ?: null,
            trim((string)($_POST['frequency'][$i] ?? '')) ?: null,
            trim((string)($_POST['duration'][$i] ?? '')) ?: null,
            trim((string)($_POST['route'][$i] ?? '')) ?: null,
            trim((string)($_POST['rx_instructions'][$i] ?? '')) ?: null,
        ];
    }

    // Lab tests
    $testIds = array_values(array_unique(array_map('intval', (array)($_POST['tests'] ?? []))));

    // Follow-up
    $fuRequired = post('follow_up_required') === 'yes';
    $fuDate = post('follow_up_date');
    $fuTime = post('follow_up_time') ?: null;
    $fuDoctor = (int)post('follow_up_doctor_id') ?: $doctorId;
    $fuType = post('follow_up_type', 'Routine Follow-Up');
    $fuReason = post('follow_up_reason') ?: null;
    $fuNotes = post('follow_up_notes') ?: null;
    if (!in_array($fuType, ['Routine Follow-Up', 'Test Result Review', 'Medication Review', 'Post-Treatment Review', 'Chronic Care Follow-Up', 'Other'], true)) {
        $fuType = 'Routine Follow-Up';
    }

    if ($action === 'complete') {
        if (!$clinical['diagnosis'] && !$clinical['examination_notes'] && !$clinical['doctor_notes']) {
            $errors[] = 'Please record a diagnosis or clinical notes before completing the consultation.';
        }
        if ($fuRequired && (!$fuDate || $fuDate < date('Y-m-d'))) {
            $errors[] = 'Please choose a valid (future) follow-up date.';
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // ------------------------------------------------ clinical data
            $stmt = $pdo->prepare('UPDATE visits SET doctor_id = ?, chief_complaint = ?, symptoms = ?, temperature = ?,
                    blood_pressure = ?, pulse = ?, respiratory_rate = ?, oxygen_saturation = ?, weight = ?, height = ?,
                    diagnosis = ?, examination_notes = ?, doctor_notes = ?, consultation_fee = ? WHERE id = ?');
            $stmt->execute([
                $doctorId,
                $clinical['chief_complaint'], $clinical['symptoms'], $clinical['temperature'],
                $clinical['blood_pressure'], $clinical['pulse'], $clinical['respiratory_rate'],
                $clinical['oxygen_saturation'], $clinical['weight'], $clinical['height'],
                $clinical['diagnosis'], $clinical['examination_notes'], $clinical['doctor_notes'],
                $fee, $visitId,
            ]);

            if ($action === 'save') {
                $pdo->commit();
                flash('success', 'Consultation notes saved. You can continue later from the Doctor Queue.');
                redirect(BASE_URL . '/modules/consultations/consult.php?visit_id=' . $visitId);
            }

            // -------------------------------------------------- prescription
            $prescriptionId = null;
            if ($rxRows) {
                $pdo->prepare('INSERT INTO prescriptions (patient_id, visit_id, doctor_id, prescription_date, diagnosis, notes)
                               VALUES (?,?,?,CURDATE(),?,?)')
                    ->execute([$patientId, $visitId, $doctorId, $clinical['diagnosis'], $clinical['doctor_notes']]);
                $prescriptionId = (int)$pdo->lastInsertId();
                $itemStmt = $pdo->prepare('INSERT INTO prescription_items (prescription_id, medicine, dosage, frequency, duration, route, instructions)
                                           VALUES (?,?,?,?,?,?,?)');
                foreach ($rxRows as $row) {
                    $itemStmt->execute(array_merge([$prescriptionId], $row));
                }
            }

            // ----------------------------------------------------- lab order
            $orderId = null;
            $testsTotal = 0.0;
            $orderedTests = [];
            if ($testIds) {
                $placeholders = implode(',', array_fill(0, count($testIds), '?'));
                $orderedTests = fetchAll("SELECT id, name, price FROM tests WHERE id IN ($placeholders) AND is_active = 1", $testIds);
                foreach ($orderedTests as $t) {
                    $testsTotal += (float)$t['price'];
                }
                if ($orderedTests) {
                    $doctorName = $doctorId ? fetchValue('SELECT name FROM doctors WHERE id = ?', [$doctorId], null) : null;
                    $orderNumber = generateOrderNumber();
                    $pdo->prepare('INSERT INTO test_orders (order_number, patient_id, visit_id, doctor_id, order_date, referring_doctor,
                                   total_amount, discount, net_amount, paid_amount, remaining_amount, payment_method, notes)
                                   VALUES (?,?,?,?,NOW(),?,?,0,?,0,?, \'Cash\', ?)')
                        ->execute([$orderNumber, $patientId, $visitId, $doctorId, $doctorName,
                            $testsTotal, $testsTotal, $testsTotal, 'Ordered during consultation ' . $visit['visit_number']]);
                    $orderId = (int)$pdo->lastInsertId();
                    $itemStmt = $pdo->prepare('INSERT INTO test_order_items (order_id, test_id, price, status) VALUES (?,?,?,"Ordered")');
                    foreach ($orderedTests as $t) {
                        $itemStmt->execute([$orderId, $t['id'], $t['price']]);
                    }
                }
            }

            // -------------------------------------------------------- invoice
            $grandTotal = round($fee + $testsTotal, 2);
            $invoiceId = null;
            if ($grandTotal > 0) {
                $invoiceNumber = generateInvoiceNumber();
                $pdo->prepare('INSERT INTO invoices (invoice_number, patient_id, visit_id, invoice_date, subtotal, discount,
                               total, paid_amount, balance, payment_method, payment_status, notes)
                               VALUES (?,?,?,NOW(),?,0,?,0,?, \'Cash\', \'Pending\', ?)')
                    ->execute([$invoiceNumber, $patientId, $visitId, $grandTotal, $grandTotal, $grandTotal,
                        'Consultation ' . $visit['visit_number']]);
                $invoiceId = (int)$pdo->lastInsertId();
                $invItem = $pdo->prepare('INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, total) VALUES (?,?,1,?,?)');
                if ($fee > 0) {
                    $invItem->execute([$invoiceId, 'Doctor consultation fee', $fee, $fee]);
                }
                foreach ($orderedTests as $t) {
                    $invItem->execute([$invoiceId, $t['name'], $t['price'], $t['price']]);
                }
            }

            // ------------------------------------------------------ follow-up
            $followUpId = null;
            if ($fuRequired) {
                $pdo->prepare('INSERT INTO follow_ups (patient_id, doctor_id, visit_id, follow_up_date, follow_up_time,
                               follow_up_type, reason, notes, status) VALUES (?,?,?,?,?,?,?,?, \'Scheduled\')')
                    ->execute([$patientId, $fuDoctor ?: null, $visitId, $fuDate, $fuTime, $fuType, $fuReason, $fuNotes]);
                $followUpId = (int)$pdo->lastInsertId();
            }

            // -------------------------------------------------- close the visit
            $pdo->prepare("UPDATE visits SET queue_status = 'Completed', consultation_completed_at = NOW(),
                           total_charges = ?, payment_status = ? WHERE id = ?")
                ->execute([$grandTotal, $grandTotal > 0 ? 'Pending' : 'Paid', $visitId]);

            if ($visit['appointment_id']) {
                $pdo->prepare("UPDATE appointments SET status = 'Completed' WHERE id = ?")->execute([(int)$visit['appointment_id']]);
            }
            // When this visit is a follow-up consultation, close the follow-up that started it.
            if ($visit['follow_up_of_visit_id']) {
                $pdo->prepare("UPDATE follow_ups SET status = 'Completed', new_visit_id = ? WHERE new_visit_id = ? OR (visit_id = ? AND status = 'Scheduled')")
                    ->execute([$visitId, $visitId, (int)$visit['follow_up_of_visit_id']]);
            }

            $pdo->commit();
            logActivity('Consultation completed', 'Visit ' . $visit['visit_number'] . ' for ' . $visit['full_name'], 'visits', $visitId);
            if ($prescriptionId) {
                logActivity('Prescription created', count($rxRows) . ' medicine(s) for ' . $visit['full_name'], 'prescriptions', $prescriptionId);
            }
            if ($orderId) {
                logActivity('Lab tests ordered', count($orderedTests) . ' test(s) ordered during ' . $visit['visit_number'], 'test_orders', $orderId);
            }
            if ($followUpId) {
                logActivity('Follow-up scheduled', 'Follow-up on ' . $fuDate . ' for ' . $visit['full_name'], 'follow_ups', $followUpId);
            }
            flash('success', 'Consultation completed for ' . $visit['full_name'] . '.');
            redirect(BASE_URL . '/modules/consultations/view.php?id=' . $visitId);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = friendlyError($e, 'Could not save the consultation. Please try again.');
        }
    }
}

// ------------------------------------------------------------- history data
$previousVisits = fetchAll('SELECT v.id, v.visit_number, v.visit_date, v.diagnosis, v.chief_complaint, d.name AS doctor_name
                            FROM visits v LEFT JOIN doctors d ON d.id = v.doctor_id
                            WHERE v.patient_id = ? AND v.id != ? ORDER BY v.visit_date DESC LIMIT 8', [$patientId, $visitId]);
$previousRx = fetchAll('SELECT pr.id, pr.prescription_date,
                        (SELECT GROUP_CONCAT(medicine SEPARATOR ", ") FROM prescription_items i WHERE i.prescription_id = pr.id) AS meds
                        FROM prescriptions pr WHERE pr.patient_id = ? ORDER BY pr.prescription_date DESC LIMIT 5', [$patientId]);
$previousReports = fetchAll('SELECT r.id, r.report_number, r.generated_at FROM reports r
                             WHERE r.patient_id = ? ORDER BY r.generated_at DESC LIMIT 5', [$patientId]);
$originVisit = $visit['follow_up_of_visit_id']
    ? fetchOne('SELECT id, visit_number, visit_date, diagnosis FROM visits WHERE id = ?', [(int)$visit['follow_up_of_visit_id']])
    : null;

$doctors = fetchAll('SELECT id, name, specialty, consultation_fee FROM doctors WHERE is_active = 1 ORDER BY name');
$categories = fetchAll('SELECT * FROM test_categories WHERE is_active = 1 ORDER BY sort_order, name');
$testsByCategory = [];
foreach (fetchAll('SELECT id, test_code, name, price, category_id FROM tests WHERE is_active = 1 ORDER BY name') as $t) {
    $testsByCategory[(int)$t['category_id']][] = $t;
}

$defaultFee = (float)$visit['consultation_fee'];
if ($defaultFee <= 0 && $visit['doctor_id']) {
    $defaultFee = (float)fetchValue('SELECT consultation_fee FROM doctors WHERE id = ?', [(int)$visit['doctor_id']], 0);
}
if ($defaultFee <= 0) {
    $defaultFee = (float)getSetting('default_consultation_fee', 0);
}

$age = $visit['age'] !== null ? (int)$visit['age'] : ($visit['date_of_birth'] ? calculateAge($visit['date_of_birth']) : null);

$pageTitle = 'Consultation · ' . $visit['visit_number'];
$pageSubtitle = $visit['full_name'] . ' · Token ' . ($visit['token_number'] ? '#' . (int)$visit['token_number'] : '-');
$activeMenu = 'consultations';
$pageActions = '<a href="' . BASE_URL . '/modules/queue/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to queue</a>';
require_once INC_PATH . '/header.php';
?>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-octagon me-2"></i><?= sanitize($error) ?></div>
<?php endforeach; ?>

<form method="post" id="consultForm">
    <?= csrfField() ?>
    <input type="hidden" name="visit_id" value="<?= $visitId ?>">

    <div class="row g-3">
        <!-- ------------------------------------------------ patient sidebar -->
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header fw-semibold"><i class="bi bi-person-vcard me-2"></i>Patient</div>
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <?= tokenBadge($visit['token_number']) ?>
                        <div>
                            <div class="fw-bold"><?= sanitize($visit['full_name']) ?></div>
                            <div class="small text-muted"><?= sanitize($visit['patient_code']) ?> · <?= sanitize($visit['gender']) ?><?= $age !== null ? ' · ' . $age . 'y' : '' ?></div>
                        </div>
                    </div>
                    <div class="small"><i class="bi bi-telephone me-1 text-muted"></i><?= sanitize($visit['phone'] ?: '-') ?>
                        <?php if ($visit['blood_group']): ?> · <span class="badge badge-status bg-danger"><?= sanitize($visit['blood_group']) ?></span><?php endif; ?></div>
                    <?php if ($visit['medical_notes']): ?>
                        <div class="alert alert-warning small mt-2 mb-0 py-2"><i class="bi bi-exclamation-triangle me-1"></i>
                            <strong>Medical notes / allergies:</strong><br><?= nl2br(sanitize($visit['medical_notes'])) ?></div>
                    <?php endif; ?>
                    <?php if ($originVisit): ?>
                        <div class="alert alert-info small mt-2 mb-0 py-2"><i class="bi bi-arrow-repeat me-1"></i>
                            Follow-up of <a href="<?= BASE_URL ?>/modules/consultations/view.php?id=<?= (int)$originVisit['id'] ?>"><?= sanitize($originVisit['visit_number']) ?></a>
                            (<?= formatDate($originVisit['visit_date']) ?>)<?= $originVisit['diagnosis'] ? ' · ' . sanitize($originVisit['diagnosis']) : '' ?></div>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/modules/patients/view.php?id=<?= $patientId ?>" class="btn btn-sm btn-outline-primary w-100 mt-3" target="_blank"><i class="bi bi-person me-1"></i>Open full profile</a>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header fw-semibold"><i class="bi bi-clock-history me-2"></i>Previous visits</div>
                <ul class="list-group list-group-flush">
                    <?php if (!$previousVisits): ?><li class="list-group-item small text-muted">First visit — no history yet.</li><?php endif; ?>
                    <?php foreach ($previousVisits as $pv): ?>
                        <li class="list-group-item small">
                            <div class="d-flex justify-content-between">
                                <a href="<?= BASE_URL ?>/modules/consultations/view.php?id=<?= (int)$pv['id'] ?>" class="fw-semibold"><?= sanitize($pv['visit_number']) ?></a>
                                <span class="text-muted"><?= formatDate($pv['visit_date']) ?></span>
                            </div>
                            <div class="text-muted"><?= sanitize($pv['doctor_name'] ?: '') ?><?= $pv['diagnosis'] ? ' · ' . sanitize(mb_strimwidth($pv['diagnosis'], 0, 60, '…')) : ($pv['chief_complaint'] ? ' · ' . sanitize(mb_strimwidth($pv['chief_complaint'], 0, 60, '…')) : '') ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="card mb-3">
                <div class="card-header fw-semibold"><i class="bi bi-capsule me-2"></i>Previous prescriptions</div>
                <ul class="list-group list-group-flush">
                    <?php if (!$previousRx): ?><li class="list-group-item small text-muted">No previous prescriptions.</li><?php endif; ?>
                    <?php foreach ($previousRx as $rx): ?>
                        <li class="list-group-item small">
                            <a href="<?= BASE_URL ?>/modules/prescriptions/index.php?id=<?= (int)$rx['id'] ?>" target="_blank"><?= formatDate($rx['prescription_date']) ?></a>
                            <div class="text-muted"><?= sanitize(mb_strimwidth((string)$rx['meds'], 0, 70, '…')) ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="card">
                <div class="card-header fw-semibold"><i class="bi bi-file-earmark-medical me-2"></i>Previous lab reports</div>
                <ul class="list-group list-group-flush">
                    <?php if (!$previousReports): ?><li class="list-group-item small text-muted">No lab reports yet.</li><?php endif; ?>
                    <?php foreach ($previousReports as $r): ?>
                        <li class="list-group-item small d-flex justify-content-between">
                            <a href="<?= BASE_URL ?>/modules/reports/view.php?id=<?= (int)$r['id'] ?>" target="_blank"><?= sanitize($r['report_number']) ?></a>
                            <span class="text-muted"><?= formatDate($r['generated_at']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- --------------------------------------------------- clinical form -->
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header fw-semibold"><i class="bi bi-clipboard2-heart me-2"></i>Consultation</div>
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label required">Doctor</label>
                        <select name="doctor_id" id="doctorSelect" class="form-select" required>
                            <option value="">-- select doctor --</option>
                            <?php foreach ($doctors as $d): ?>
                                <option value="<?= (int)$d['id'] ?>" data-fee="<?= (float)$d['consultation_fee'] ?>" <?= (int)$visit['doctor_id'] === (int)$d['id'] ? 'selected' : '' ?>>
                                    <?= sanitize($d['name']) ?><?= $d['specialty'] ? ' (' . sanitize($d['specialty']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Consultation fee</label>
                        <input type="number" step="0.01" min="0" name="consultation_fee" id="consultationFee" class="form-control" value="<?= sanitize(number_format($defaultFee, 2, '.', '')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Chief complaint</label>
                        <input type="text" name="chief_complaint" class="form-control" value="<?= sanitize($visit['chief_complaint']) ?>" placeholder="e.g. Fever for 3 days">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Symptoms</label>
                        <input type="text" name="symptoms" class="form-control" value="<?= sanitize($visit['symptoms']) ?>" placeholder="e.g. Headache, body aches, cough">
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header fw-semibold"><i class="bi bi-heart-pulse me-2"></i>Vital signs <span class="text-muted small fw-normal">(optional)</span></div>
                <div class="card-body">
                    <div class="row g-2 vitals-grid">
                        <?php
                        $vitals = [
                            ['temperature', 'Temp', '°F', $visit['temperature']],
                            ['blood_pressure', 'BP', 'mmHg', $visit['blood_pressure']],
                            ['pulse', 'Pulse', 'bpm', $visit['pulse']],
                            ['respiratory_rate', 'Resp rate', '/min', $visit['respiratory_rate']],
                            ['oxygen_saturation', 'SpO2', '%', $visit['oxygen_saturation']],
                            ['weight', 'Weight', 'kg', $visit['weight']],
                            ['height', 'Height', 'cm', $visit['height']],
                        ];
                        foreach ($vitals as [$name, $label, $unit, $value]): ?>
                            <div class="col-6 col-md-3 col-lg-auto flex-lg-fill">
                                <label class="form-label mb-0"><?= $label ?></label>
                                <input type="text" name="<?= $name ?>" class="form-control form-control-sm" value="<?= sanitize($value) ?>">
                                <div class="vital-unit text-center"><?= $unit ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header fw-semibold"><i class="bi bi-journal-medical me-2"></i>Examination &amp; diagnosis</div>
                <div class="card-body row g-3">
                    <div class="col-12">
                        <label class="form-label">Examination / clinical notes</label>
                        <textarea name="examination_notes" class="form-control" rows="3" placeholder="Findings on examination..."><?= sanitize($visit['examination_notes']) ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Diagnosis</label>
                        <textarea name="diagnosis" class="form-control" rows="2" placeholder="One or more diagnoses (separate with commas or new lines)"><?= sanitize($visit['diagnosis']) ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Doctor notes / advice</label>
                        <textarea name="doctor_notes" class="form-control" rows="3" placeholder="Advice, diet, warnings..."><?= sanitize($visit['doctor_notes']) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><span class="rx-symbol me-1">℞</span>Prescription</span>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addMedicine"><i class="bi bi-plus"></i> Add medicine</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" id="medicineTable">
                        <thead><tr><th style="width:24%">Medicine</th><th>Dosage</th><th>Frequency</th><th>Duration</th><th>Route</th><th>Instructions</th><th></th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="card-footer small text-muted">Leave empty when no prescription is required.</div>
            </div>

            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-droplet-half me-2"></i>Order laboratory tests &amp; services</span>
                    <input type="search" id="testFilter" class="form-control form-control-sm w-40" style="max-width:220px" placeholder="Filter tests...">
                </div>
                <div class="card-body" style="max-height:320px; overflow:auto">
                    <?php foreach ($categories as $cat): $catTests = $testsByCategory[(int)$cat['id']] ?? []; ?>
                        <?php if (!$catTests) { continue; } ?>
                        <h6 class="text-uppercase text-muted small fw-bold mt-1"><?= sanitize($cat['name']) ?></h6>
                        <div class="row g-2 mb-2">
                            <?php foreach ($catTests as $t): ?>
                                <div class="col-md-6 test-item" data-name="<?= sanitize(strtolower($t['name'] . ' ' . $t['test_code'])) ?>">
                                    <div class="test-pick d-flex justify-content-between align-items-center">
                                        <div class="form-check m-0">
                                            <input class="form-check-input test-check" type="checkbox" name="tests[]" value="<?= (int)$t['id'] ?>"
                                                   id="test<?= (int)$t['id'] ?>" data-price="<?= (float)$t['price'] ?>">
                                            <label class="form-check-label small" for="test<?= (int)$t['id'] ?>"><?= sanitize($t['name']) ?></label>
                                        </div>
                                        <span class="badge bg-light text-dark"><?= formatCurrency($t['price']) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="card-footer d-flex justify-content-between small">
                    <span class="text-muted">Selected tests: <strong id="testCount">0</strong></span>
                    <span>Tests total: <strong id="testsTotal"><?= formatCurrency(0) ?></strong></span>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header fw-semibold"><i class="bi bi-arrow-repeat me-2"></i>Follow-up</div>
                <div class="card-body">
                    <div class="mb-2">
                        <label class="form-label d-block">Follow-up required?</label>
                        <div class="btn-group" role="group">
                            <input type="radio" class="btn-check" name="follow_up_required" id="fuNo" value="no" checked>
                            <label class="btn btn-outline-secondary btn-sm" for="fuNo">No</label>
                            <input type="radio" class="btn-check" name="follow_up_required" id="fuYes" value="yes">
                            <label class="btn btn-outline-primary btn-sm" for="fuYes">Yes</label>
                        </div>
                    </div>
                    <div class="row g-3 d-none" id="followUpFields">
                        <div class="col-md-4">
                            <label class="form-label required">Follow-up date</label>
                            <input type="date" name="follow_up_date" id="fuDate" class="form-control" min="<?= date('Y-m-d') ?>">
                            <div class="mt-1">
                                <?php foreach ([3, 7, 14, 30] as $days): ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 fu-quick" data-days="<?= $days ?>">+<?= $days ?>d</button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Preferred time</label>
                            <input type="time" name="follow_up_time" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Doctor</label>
                            <select name="follow_up_doctor_id" class="form-select">
                                <option value="">Same as consultation</option>
                                <?php foreach ($doctors as $d): ?>
                                    <option value="<?= (int)$d['id'] ?>"><?= sanitize($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Type</label>
                            <select name="follow_up_type" class="form-select">
                                <?php foreach (['Routine Follow-Up', 'Test Result Review', 'Medication Review', 'Post-Treatment Review', 'Chronic Care Follow-Up', 'Other'] as $t): ?>
                                    <option><?= $t ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Reason</label>
                            <input type="text" name="follow_up_reason" class="form-control" placeholder="e.g. Review blood pressure and lab results">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="follow_up_notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="small text-muted">
                        Billing preview: consultation <strong id="feePreview"><?= formatCurrency($defaultFee) ?></strong>
                        + tests <strong id="testsPreview"><?= formatCurrency(0) ?></strong>
                        = <strong id="grandPreview"><?= formatCurrency($defaultFee) ?></strong>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" name="action" value="save" class="btn btn-outline-primary"><i class="bi bi-save me-1"></i>Save draft</button>
                        <button type="submit" name="action" value="complete" class="btn btn-success"
                                data-confirm="Complete this consultation? Prescription, lab orders, billing and follow-up will be created."><i class="bi bi-check2-circle me-1"></i>Complete consultation</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<datalist id="medicineList">
    <?php foreach (['Paracetamol 500mg', 'Amoxicillin 500mg', 'Azithromycin 250mg', 'Omeprazole 20mg', 'Cetirizine 10mg',
        'Metformin 500mg', 'Ibuprofen 400mg', 'ORS Sachet', 'Multivitamin', 'Ceftriaxone 1g'] as $m): ?>
        <option value="<?= sanitize($m) ?>"></option>
    <?php endforeach; ?>
</datalist>
<datalist id="routeList">
    <?php foreach (['Oral', 'IV', 'IM', 'Topical', 'Sublingual', 'Inhalation', 'Per Rectal', 'Eye drops', 'Ear drops'] as $r): ?>
        <option value="<?= sanitize($r) ?>"></option>
    <?php endforeach; ?>
</datalist>

<?php
$currency = json_encode(currencySymbol());
$pageScripts = <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function () {
    var CURRENCY = {$currency};
    function money(v) { return CURRENCY + ' ' + (parseFloat(v) || 0).toFixed(2); }

    // ----------------------------------------------------- medicine rows
    var tbody = document.querySelector('#medicineTable tbody');
    function addRow() {
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td><input type="text" name="medicine[]" list="medicineList" class="form-control form-control-sm" placeholder="Medicine name"></td>' +
            '<td><input type="text" name="dosage[]" class="form-control form-control-sm" placeholder="1 tab"></td>' +
            '<td><input type="text" name="frequency[]" class="form-control form-control-sm" placeholder="TDS"></td>' +
            '<td><input type="text" name="duration[]" class="form-control form-control-sm" placeholder="5 days"></td>' +
            '<td><input type="text" name="route[]" list="routeList" class="form-control form-control-sm" placeholder="Oral"></td>' +
            '<td><input type="text" name="rx_instructions[]" class="form-control form-control-sm" placeholder="After meals"></td>' +
            '<td><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-x"></i></button></td>';
        tbody.appendChild(tr);
        tr.querySelector('.remove-row').addEventListener('click', function () { tr.remove(); });
        return tr;
    }
    document.getElementById('addMedicine').addEventListener('click', function () { addRow().querySelector('input').focus(); });
    for (var i = 0; i < 3; i++) { addRow(); }

    // ------------------------------------------------------- test filter
    var filter = document.getElementById('testFilter');
    filter.addEventListener('input', function () {
        var term = filter.value.trim().toLowerCase();
        document.querySelectorAll('.test-item').forEach(function (item) {
            item.style.display = !term || item.dataset.name.indexOf(term) !== -1 ? '' : 'none';
        });
    });

    // -------------------------------------------------- billing preview
    var feeInput = document.getElementById('consultationFee');
    function recalc() {
        var testsTotal = 0, count = 0;
        document.querySelectorAll('.test-check:checked').forEach(function (c) { testsTotal += parseFloat(c.dataset.price) || 0; count++; });
        var fee = parseFloat(feeInput.value) || 0;
        document.getElementById('testCount').textContent = count;
        document.getElementById('testsTotal').textContent = money(testsTotal);
        document.getElementById('feePreview').textContent = money(fee);
        document.getElementById('testsPreview').textContent = money(testsTotal);
        document.getElementById('grandPreview').textContent = money(fee + testsTotal);
    }
    document.querySelectorAll('.test-check').forEach(function (c) { c.addEventListener('change', recalc); });
    feeInput.addEventListener('input', recalc);

    // Update fee suggestion when the doctor changes (only when the field is untouched)
    var doctorSelect = document.getElementById('doctorSelect');
    doctorSelect.addEventListener('change', function () {
        var opt = doctorSelect.options[doctorSelect.selectedIndex];
        var fee = parseFloat(opt && opt.dataset.fee) || 0;
        if (fee > 0) { feeInput.value = fee.toFixed(2); recalc(); }
    });

    // ---------------------------------------------------- follow-up form
    var fuFields = document.getElementById('followUpFields');
    document.querySelectorAll('input[name="follow_up_required"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            fuFields.classList.toggle('d-none', radio.value !== 'yes' || !radio.checked);
        });
    });
    document.querySelectorAll('.fu-quick').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var d = new Date();
            d.setDate(d.getDate() + parseInt(btn.dataset.days, 10));
            document.getElementById('fuDate').value = d.toISOString().slice(0, 10);
        });
    });
    recalc();
});
</script>
HTML;
require_once INC_PATH . '/footer.php';
