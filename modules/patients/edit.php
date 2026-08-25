<?php
/** Edit an existing patient. */
require_once __DIR__ . '/../../config/session.php';

$id = (int)get('id');
$patient = fetchOne('SELECT * FROM patients WHERE id = ?', [$id]);
if (!$patient) {
    flash('danger', 'Patient not found.');
    redirect(BASE_URL . '/modules/patients/index.php');
}

$errors = [];
$fields = ['full_name', 'father_husband_name', 'gender', 'date_of_birth', 'age', 'cnic', 'phone', 'whatsapp',
           'email', 'address', 'emergency_contact', 'blood_group', 'referring_doctor', 'medical_notes', 'registration_date'];
$data = [];
foreach ($fields as $field) {
    $data[$field] = $patient[$field] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(BASE_URL . '/modules/patients/edit.php?id=' . $id);
    foreach ($fields as $field) {
        $data[$field] = post($field, '');
    }

    if ($data['full_name'] === '') {
        $errors[] = 'Patient name is required.';
    }
    if (!in_array($data['gender'], ['Male', 'Female', 'Other'], true)) {
        $errors[] = 'Please select a valid gender.';
    }
    if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($data['registration_date'] === '') {
        $data['registration_date'] = date('Y-m-d');
    }
    if ($data['age'] === '' && $data['date_of_birth'] !== '') {
        $data['age'] = calculateAge($data['date_of_birth']);
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare('UPDATE patients SET full_name=?, father_husband_name=?, gender=?, date_of_birth=?, age=?,
                    cnic=?, phone=?, whatsapp=?, email=?, address=?, emergency_contact=?, blood_group=?, referring_doctor=?,
                    medical_notes=?, registration_date=? WHERE id=?');
            $stmt->execute([
                $data['full_name'],
                $data['father_husband_name'] ?: null,
                $data['gender'],
                $data['date_of_birth'] ?: null,
                $data['age'] !== '' ? (int)$data['age'] : null,
                $data['cnic'] ?: null,
                $data['phone'] ?: null,
                $data['whatsapp'] ?: null,
                $data['email'] ?: null,
                $data['address'] ?: null,
                $data['emergency_contact'] ?: null,
                $data['blood_group'] ?: null,
                $data['referring_doctor'] ?: null,
                $data['medical_notes'] ?: null,
                $data['registration_date'],
                $id,
            ]);
            logActivity('Patient updated', $patient['patient_id'] . ' - ' . $data['full_name'], 'patients', $id);
            flash('success', 'Patient record updated successfully.');
            redirect(BASE_URL . '/modules/patients/view.php?id=' . $id);
        } catch (PDOException $e) {
            $errors[] = 'Could not update the patient: ' . $e->getMessage();
        }
    }
}

$doctors = fetchAll('SELECT name FROM doctors WHERE is_active = 1 ORDER BY name');

$pageTitle = 'Edit Patient';
$pageSubtitle = $patient['patient_id'] . ' - ' . $patient['full_name'];
$activeMenu = 'patients';
$pageActions = '<a href="' . BASE_URL . '/modules/patients/view.php?id=' . $id . '" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to profile</a>';
require_once INC_PATH . '/header.php';
?>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-octagon me-2"></i><?= sanitize($error) ?></div>
<?php endforeach; ?>

<form method="post" class="row g-3">
    <?= csrfField() ?>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between">
                <span class="fw-semibold"><i class="bi bi-person-vcard me-2"></i>Personal Information</span>
                <span class="badge bg-light text-dark"><?= sanitize($patient['patient_id']) ?></span>
            </div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label required">Full name</label>
                    <input type="text" name="full_name" class="form-control" value="<?= sanitize($data['full_name']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Father / Husband name</label>
                    <input type="text" name="father_husband_name" class="form-control" value="<?= sanitize($data['father_husband_name']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label required">Gender</label>
                    <select name="gender" class="form-select" required>
                        <?php foreach (['Male', 'Female', 'Other'] as $g): ?>
                            <option value="<?= $g ?>" <?= $data['gender'] === $g ? 'selected' : '' ?>><?= $g ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date of birth</label>
                    <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" value="<?= sanitize($data['date_of_birth']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Age</label>
                    <input type="number" id="age" name="age" class="form-control" min="0" max="130" value="<?= sanitize($data['age']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">CNIC</label>
                    <input type="text" name="cnic" class="form-control" value="<?= sanitize($data['cnic']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= sanitize($data['phone']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">WhatsApp</label>
                    <input type="text" name="whatsapp" class="form-control" value="<?= sanitize($data['whatsapp']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= sanitize($data['email']) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2"><?= sanitize($data['address']) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-heart-pulse me-2"></i>Medical Information</div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label">Blood group</label>
                    <select name="blood_group" class="form-select">
                        <option value="">-- select --</option>
                        <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                            <option value="<?= $bg ?>" <?= $data['blood_group'] === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Registration date</label>
                    <input type="date" name="registration_date" class="form-control" value="<?= sanitize($data['registration_date']) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Referring doctor</label>
                    <input type="text" name="referring_doctor" class="form-control" list="doctorList" value="<?= sanitize($data['referring_doctor']) ?>">
                    <datalist id="doctorList">
                        <?php foreach ($doctors as $d): ?>
                            <option value="<?= sanitize($d['name']) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="col-12">
                    <label class="form-label">Emergency contact</label>
                    <input type="text" name="emergency_contact" class="form-control" value="<?= sanitize($data['emergency_contact']) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Medical notes / allergies</label>
                    <textarea name="medical_notes" class="form-control" rows="3"><?= sanitize($data['medical_notes']) ?></textarea>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-body d-grid gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Update patient</button>
                <a href="<?= BASE_URL ?>/modules/patients/view.php?id=<?= $id ?>" class="btn btn-outline-secondary">Cancel</a>
                <a href="delete.php?id=<?= $id ?>" class="btn btn-outline-danger"><i class="bi bi-trash me-1"></i>Delete patient</a>
            </div>
        </div>
    </div>
</form>

<?php require_once INC_PATH . '/footer.php'; ?>
