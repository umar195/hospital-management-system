<?php
/** Delete a doctor (with confirmation). */
require_once __DIR__ . '/../../config/session.php';

$id = (int)($_REQUEST['id'] ?? 0);
$doctor = fetchOne('SELECT * FROM doctors WHERE id = ?', [$id]);
if (!$doctor) {
    flash('danger', 'Doctor not found.');
    redirect(BASE_URL . '/modules/doctors/index.php');
}

$appointments = (int)fetchValue('SELECT COUNT(*) FROM appointments WHERE doctor_id = ?', [$id]);
$visits = (int)fetchValue('SELECT COUNT(*) FROM visits WHERE doctor_id = ?', [$id]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(BASE_URL . '/modules/doctors/index.php');
    try {
        $pdo->prepare('DELETE FROM doctors WHERE id = ?')->execute([$id]);
        logActivity('Doctor deleted', $doctor['name'], 'doctors', $id);
        flash('success', 'Doctor deleted. Linked appointments and visits were kept without a doctor.');
    } catch (PDOException $e) {
        flash('danger', 'Could not delete the doctor: ' . $e->getMessage());
    }
    redirect(BASE_URL . '/modules/doctors/index.php');
}

$pageTitle = 'Delete Doctor';
$pageSubtitle = $doctor['name'];
$activeMenu = 'doctors';
require_once INC_PATH . '/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white fw-semibold"><i class="bi bi-exclamation-triangle me-2"></i>Confirm deletion</div>
            <div class="card-body">
                <p>Delete doctor <strong><?= sanitize($doctor['name']) ?></strong>?</p>
                <ul class="list-group list-group-flush mb-3">
                    <li class="list-group-item d-flex justify-content-between py-1"><span>Appointments</span><strong><?= $appointments ?></strong></li>
                    <li class="list-group-item d-flex justify-content-between py-1"><span>Visits</span><strong><?= $visits ?></strong></li>
                </ul>
                <div class="alert alert-warning mb-0">
                    <i class="bi bi-info-circle me-2"></i>Related appointments and visits are kept but will no longer reference this doctor.
                    Consider marking the doctor as <strong>inactive</strong> instead.
                </div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-between">
                <a href="<?= BASE_URL ?>/modules/doctors/index.php" class="btn btn-outline-secondary">Cancel</a>
                <form method="post" class="m-0">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <button class="btn btn-danger" data-confirm="Delete this doctor permanently?"><i class="bi bi-trash me-1"></i>Yes, delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once INC_PATH . '/footer.php'; ?>
