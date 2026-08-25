<?php
/** Write a new prescription. */
require_once __DIR__ . '/../../config/session.php';

$patientId = (int)get('patient_id', post('patient_id'));
$patient = $patientId ? fetchOne('SELECT * FROM patients WHERE id = ?', [$patientId]) : null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(BASE_URL . '/modules/prescriptions/add.php');
    $date = post('prescription_date', date('Y-m-d'));
    $visitId = (int)post('visit_id') ?: null;
    $notes = post('notes');

    $medicines    = (array)($_POST['medicine'] ?? []);
    $dosages      = (array)($_POST['dosage'] ?? []);
    $frequencies  = (array)($_POST['frequency'] ?? []);
    $durations    = (array)($_POST['duration'] ?? []);
    $instructions = (array)($_POST['instructions'] ?? []);

    $rows = [];
    foreach ($medicines as $i => $medicine) {
        $medicine = trim((string)$medicine);
        if ($medicine === '') {
            continue;
        }
        $rows[] = [
            $medicine,
            trim((string)($dosages[$i] ?? '')) ?: null,
            trim((string)($frequencies[$i] ?? '')) ?: null,
            trim((string)($durations[$i] ?? '')) ?: null,
            trim((string)($instructions[$i] ?? '')) ?: null,
        ];
    }

    if (!$patient) {
        $errors[] = 'Please select a patient.';
    }
    if (!$rows) {
        $errors[] = 'Add at least one medicine.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO prescriptions (patient_id, visit_id, prescription_date, notes) VALUES (?,?,?,?)')
                ->execute([$patientId, $visitId, $date, $notes ?: null]);
            $prescriptionId = (int)$pdo->lastInsertId();
            $stmt = $pdo->prepare('INSERT INTO prescription_items (prescription_id, medicine, dosage, frequency, duration, instructions)
                                   VALUES (?,?,?,?,?,?)');
            foreach ($rows as $row) {
                $stmt->execute(array_merge([$prescriptionId], $row));
            }
            $pdo->commit();
            logActivity('Prescription created', count($rows) . ' medicine(s) for ' . $patient['full_name'], 'prescriptions', $prescriptionId);
            flash('success', 'Prescription saved.');
            redirect(BASE_URL . '/modules/prescriptions/index.php?id=' . $prescriptionId);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Could not save the prescription: ' . $e->getMessage();
        }
    }
}

$visits = $patient ? fetchAll('SELECT id, visit_number, visit_date FROM visits WHERE patient_id = ? ORDER BY visit_date DESC LIMIT 10', [$patientId]) : [];
$commonMedicines = ['Paracetamol 500mg', 'Amoxicillin 500mg', 'Azithromycin 250mg', 'Omeprazole 20mg', 'Cetirizine 10mg',
    'Metformin 500mg', 'Ibuprofen 400mg', 'ORS Sachet', 'Multivitamin', 'Ceftriaxone 1g'];

$pageTitle = 'New Prescription';
$activeMenu = 'prescriptions';
$pageActions = '<a href="' . BASE_URL . '/modules/prescriptions/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>All prescriptions</a>';
require_once INC_PATH . '/header.php';
?>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-octagon me-2"></i><?= sanitize($error) ?></div>
<?php endforeach; ?>

<form method="post" class="row g-3">
    <?= csrfField() ?>
    <input type="hidden" name="patient_id" value="<?= (int)$patientId ?>">

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-person-badge me-2"></i>Patient</div>
            <div class="card-body">
                <div class="position-relative mb-3">
                    <input type="text" id="patientSearch" class="form-control" autocomplete="off" placeholder="Search patient...">
                    <div id="patientResults" class="list-group search-results"></div>
                </div>
                <?php if ($patient): ?>
                    <div class="alert alert-info mb-0">
                        <strong><?= sanitize($patient['full_name']) ?></strong><br>
                        <small><?= sanitize($patient['patient_id']) ?> · <?= sanitize($patient['gender']) ?>
                            <?= $patient['age'] !== null ? ' · ' . (int)$patient['age'] . ' yrs' : '' ?></small>
                    </div>
                <?php else: ?>
                    <div class="small text-muted">Select a patient to continue.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-calendar3 me-2"></i>Details</div>
            <div class="card-body row g-3">
                <div class="col-12">
                    <label class="form-label">Date</label>
                    <input type="date" name="prescription_date" class="form-control" value="<?= sanitize(post('prescription_date', date('Y-m-d'))) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Link to visit</label>
                    <select name="visit_id" class="form-select">
                        <option value="">-- none --</option>
                        <?php foreach ($visits as $v): ?>
                            <option value="<?= (int)$v['id'] ?>"><?= sanitize($v['visit_number']) ?> · <?= formatDate($v['visit_date']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Advice / notes</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Diet, follow-up, warnings..."><?= sanitize(post('notes')) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">℞ Medicines</span>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addMedicine"><i class="bi bi-plus"></i> Add medicine</button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" id="medicineTable">
                    <thead><tr><th style="width:26%">Medicine</th><th>Dosage</th><th>Frequency</th><th>Duration</th><th>Instructions</th><th></th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="card-footer bg-white d-flex gap-2">
                <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Save prescription</button>
                <a href="<?= BASE_URL ?>/modules/prescriptions/index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </div>
</form>

<datalist id="medicineList">
    <?php foreach ($commonMedicines as $m): ?>
        <option value="<?= sanitize($m) ?>"></option>
    <?php endforeach; ?>
</datalist>

<?php
$pageScripts = <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded', function () {
    var tbody = document.querySelector('#medicineTable tbody');

    function addRow() {
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td><input type="text" name="medicine[]" list="medicineList" class="form-control form-control-sm" placeholder="Medicine name"></td>' +
            '<td><input type="text" name="dosage[]" class="form-control form-control-sm" placeholder="1 tab"></td>' +
            '<td><input type="text" name="frequency[]" class="form-control form-control-sm" placeholder="TDS"></td>' +
            '<td><input type="text" name="duration[]" class="form-control form-control-sm" placeholder="5 days"></td>' +
            '<td><input type="text" name="instructions[]" class="form-control form-control-sm" placeholder="After meals"></td>' +
            '<td><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-x"></i></button></td>';
        tbody.appendChild(tr);
        tr.querySelector('.remove-row').addEventListener('click', function () { tr.remove(); });
        return tr;
    }

    document.getElementById('addMedicine').addEventListener('click', function () {
        addRow().querySelector('input').focus();
    });
    for (var i = 0; i < 3; i++) { addRow(); }

    if (window.HMS && HMS.patientSearch) {
        HMS.patientSearch(document.getElementById('patientSearch'), document.getElementById('patientResults'), function (p) {
            window.location.href = window.BASE_URL + '/modules/prescriptions/add.php?patient_id=' + p.id;
        });
    }
});
</script>
HTML;
require_once INC_PATH . '/footer.php';
