<?php
/** Edit a doctor. */
require_once __DIR__ . '/../../config/session.php';

$id = (int)get('id');
$doctor = fetchOne('SELECT * FROM doctors WHERE id = ?', [$id]);
if (!$doctor) {
    flash('danger', 'Doctor not found.');
    redirect(BASE_URL . '/modules/doctors/index.php');
}

$errors = [];
$data = $doctor;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(BASE_URL . '/modules/doctors/edit.php?id=' . $id);
    foreach (['name', 'specialty', 'phone', 'email', 'license_number', 'address', 'notes'] as $field) {
        $data[$field] = post($field);
    }
    $data['is_active'] = post('is_active') ? 1 : 0;

    if ($data['name'] === '') {
        $errors[] = 'Doctor name is required.';
    }
    if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('UPDATE doctors SET name=?, specialty=?, phone=?, email=?, license_number=?, address=?, notes=?, is_active=? WHERE id=?');
        $stmt->execute([$data['name'], $data['specialty'] ?: null, $data['phone'] ?: null, $data['email'] ?: null,
                        $data['license_number'] ?: null, $data['address'] ?: null, $data['notes'] ?: null, $data['is_active'], $id]);
        logActivity('Doctor updated', $data['name'], 'doctors', $id);
        flash('success', 'Doctor updated successfully.');
        redirect(BASE_URL . '/modules/doctors/index.php');
    }
}

$appointments = fetchAll('SELECT a.*, p.full_name FROM appointments a JOIN patients p ON p.id = a.patient_id
                          WHERE a.doctor_id = ? ORDER BY a.appointment_date DESC LIMIT 10', [$id]);

$pageTitle = 'Edit Doctor';
$pageSubtitle = $doctor['name'];
$activeMenu = 'doctors';
$pageActions = '<a href="' . BASE_URL . '/modules/doctors/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to list</a>';
require_once INC_PATH . '/header.php';
?>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-octagon me-2"></i><?= sanitize($error) ?></div>
<?php endforeach; ?>

<div class="row g-3">
    <div class="col-lg-8">
        <form method="post" class="card">
            <?= csrfField() ?>
            <div class="card-header bg-white fw-semibold"><i class="bi bi-person-badge me-2"></i>Doctor details</div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label required">Name</label>
                    <input type="text" name="name" class="form-control" value="<?= sanitize($data['name']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Specialty</label>
                    <input type="text" name="specialty" class="form-control" value="<?= sanitize($data['specialty']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= sanitize($data['phone']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= sanitize($data['email']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">License number</label>
                    <input type="text" name="license_number" class="form-control" value="<?= sanitize($data['license_number']) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2"><?= sanitize($data['address']) ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"><?= sanitize($data['notes']) ?></textarea>
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" <?= $data['is_active'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-between">
                <a href="delete.php?id=<?= $id ?>" class="btn btn-outline-danger"><i class="bi bi-trash me-1"></i>Delete</a>
                <div class="d-flex gap-2">
                    <a href="<?= BASE_URL ?>/modules/doctors/index.php" class="btn btn-outline-secondary">Cancel</a>
                    <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Update doctor</button>
                </div>
            </div>
        </form>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-calendar-check me-2"></i>Recent appointments</div>
            <ul class="list-group list-group-flush">
                <?php if (!$appointments): ?>
                    <li class="list-group-item text-muted small">No appointments yet.</li>
                <?php endif; ?>
                <?php foreach ($appointments as $a): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small fw-semibold"><?= sanitize($a['full_name']) ?></div>
                            <small class="text-muted"><?= formatDate($a['appointment_date']) ?> <?= formatTime($a['appointment_time']) ?></small>
                        </div>
                        <?= statusBadge($a['status']) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<?php require_once INC_PATH . '/footer.php'; ?>
