<?php
/** Create an appointment. */
require_once __DIR__ . '/../../config/session.php';

$errors = [];
$data = [
    'patient_id'       => (int)get('patient_id'),
    'doctor_id'        => (int)get('doctor_id'),
    'appointment_date' => get('date') ?: date('Y-m-d'),
    'appointment_time' => '10:00',
    'purpose'          => '',
    'status'           => 'Scheduled',
    'notes'            => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(BASE_URL . '/modules/appointments/add.php');
    $data['patient_id']       = (int)post('patient_id');
    $data['doctor_id']        = (int)post('doctor_id');
    $data['appointment_date'] = post('appointment_date');
    $data['appointment_time'] = post('appointment_time');
    $data['purpose']          = post('purpose');
    $data['status']           = post('status', 'Scheduled');
    $data['notes']            = post('notes');

    if ($data['patient_id'] <= 0) {
        $errors[] = 'Please select a patient.';
    }
    if ($data['appointment_date'] === '' || $data['appointment_time'] === '') {
        $errors[] = 'Appointment date and time are required.';
    }
    if (!in_array($data['status'], ['Scheduled', 'Arrived', 'Completed', 'Cancelled', 'No Show'], true)) {
        $data['status'] = 'Scheduled';
    }

    if (!$errors) {
        $clash = fetchOne('SELECT id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status != "Cancelled"',
            [$data['doctor_id'] ?: null, $data['appointment_date'], $data['appointment_time']]);
        if ($clash && $data['doctor_id']) {
            $errors[] = 'This doctor already has an appointment at the selected time slot.';
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare('INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, purpose, status, notes)
                               VALUES (?,?,?,?,?,?,?)');
        $stmt->execute([$data['patient_id'], $data['doctor_id'] ?: null, $data['appointment_date'], $data['appointment_time'],
                        $data['purpose'] ?: null, $data['status'], $data['notes'] ?: null]);
        $id = (int)$pdo->lastInsertId();
        logActivity('Appointment created', 'Appointment #' . $id, 'appointments', $id);
        flash('success', 'Appointment scheduled successfully.');
        redirect(BASE_URL . '/modules/appointments/index.php?date=' . urlencode($data['appointment_date']));
    }
}

$selectedPatient = $data['patient_id'] ? fetchOne('SELECT * FROM patients WHERE id = ?', [$data['patient_id']]) : null;
$doctors = fetchAll('SELECT id, name, specialty FROM doctors WHERE is_active = 1 ORDER BY name');

$pageTitle = 'New Appointment';
$activeMenu = 'appointments';
$pageActions = '<a href="' . BASE_URL . '/modules/appointments/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to list</a>';
require_once INC_PATH . '/header.php';
?>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-octagon me-2"></i><?= sanitize($error) ?></div>
<?php endforeach; ?>

<form method="post" class="row g-3 justify-content-center">
    <?= csrfField() ?>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-calendar-plus me-2"></i>Appointment details</div>
            <div class="card-body row g-3">
                <div class="col-12">
                    <label class="form-label required">Patient</label>
                    <input type="hidden" name="patient_id" id="patientId" value="<?= (int)$data['patient_id'] ?>">
                    <input type="text" id="patientSearch" class="form-control" placeholder="Search patient by name, phone or ID"
                           value="<?= $selectedPatient ? sanitize($selectedPatient['full_name'] . ' (' . $selectedPatient['patient_id'] . ')') : '' ?>">
                    <div class="list-group mt-1" id="patientResults" style="max-height:220px; overflow:auto"></div>
                    <div class="form-text">Not registered yet? <a href="<?= BASE_URL ?>/modules/patients/add.php">Register a patient</a>.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Doctor</label>
                    <select name="doctor_id" class="form-select">
                        <option value="">-- none --</option>
                        <?php foreach ($doctors as $d): ?>
                            <option value="<?= (int)$d['id'] ?>" <?= $data['doctor_id'] === (int)$d['id'] ? 'selected' : '' ?>>
                                <?= sanitize($d['name']) ?><?= $d['specialty'] ? ' (' . sanitize($d['specialty']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php foreach (['Scheduled', 'Arrived', 'Completed', 'Cancelled', 'No Show'] as $s): ?>
                            <option value="<?= $s ?>" <?= $data['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Date</label>
                    <input type="date" name="appointment_date" class="form-control" value="<?= sanitize($data['appointment_date']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Time</label>
                    <input type="time" name="appointment_time" class="form-control" value="<?= sanitize($data['appointment_time']) ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Purpose</label>
                    <input type="text" name="purpose" class="form-control" value="<?= sanitize($data['purpose']) ?>" placeholder="Consultation, follow-up, lab sample...">
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"><?= sanitize($data['notes']) ?></textarea>
                </div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-end gap-2">
                <a href="<?= BASE_URL ?>/modules/appointments/index.php" class="btn btn-outline-secondary">Cancel</a>
                <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Schedule appointment</button>
            </div>
        </div>
    </div>
</form>

<?php
$pageScripts = <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('patientSearch');
    var results = document.getElementById('patientResults');
    var hidden = document.getElementById('patientId');
    HMS.patientSearch(input, results, function (p) {
        hidden.value = p.id;
        input.value = p.full_name + ' (' + p.patient_id + ')';
        results.innerHTML = '';
    });
    input.addEventListener('input', function () {
        if (!input.value.trim()) { hidden.value = ''; }
    });
});
</script>
HTML;
require_once INC_PATH . '/footer.php';
