<?php
/** Delete a patient (with confirmation screen). */
require_once __DIR__ . '/../../config/session.php';

$id = (int)($_REQUEST['id'] ?? 0);
$patient = fetchOne('SELECT * FROM patients WHERE id = ?', [$id]);
if (!$patient) {
    flash('danger', 'Patient not found.');
    redirect(BASE_URL . '/modules/patients/index.php');
}

$counts = [
    'Test orders'   => (int)fetchValue('SELECT COUNT(*) FROM test_orders WHERE patient_id = ?', [$id]),
    'Reports'       => (int)fetchValue('SELECT COUNT(*) FROM reports WHERE patient_id = ?', [$id]),
    'Invoices'      => (int)fetchValue('SELECT COUNT(*) FROM invoices WHERE patient_id = ?', [$id]),
    'Payments'      => (int)fetchValue('SELECT COUNT(*) FROM payments WHERE patient_id = ?', [$id]),
    'Visits'        => (int)fetchValue('SELECT COUNT(*) FROM visits WHERE patient_id = ?', [$id]),
    'Appointments'  => (int)fetchValue('SELECT COUNT(*) FROM appointments WHERE patient_id = ?', [$id]),
    'Prescriptions' => (int)fetchValue('SELECT COUNT(*) FROM prescriptions WHERE patient_id = ?', [$id]),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(BASE_URL . '/modules/patients/index.php');
    try {
        $stmt = $pdo->prepare('DELETE FROM patients WHERE id = ?');
        $stmt->execute([$id]);
        logActivity('Patient deleted', $patient['patient_id'] . ' - ' . $patient['full_name'], 'patients', $id);
        flash('success', 'Patient ' . $patient['patient_id'] . ' and all related records were deleted.');
    } catch (PDOException $e) {
        flash('danger', 'Could not delete the patient: ' . $e->getMessage());
    }
    redirect(BASE_URL . '/modules/patients/index.php');
}

$pageTitle = 'Delete Patient';
$pageSubtitle = $patient['patient_id'] . ' - ' . $patient['full_name'];
$activeMenu = 'patients';
require_once INC_PATH . '/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white fw-semibold"><i class="bi bi-exclamation-triangle me-2"></i>Confirm deletion</div>
            <div class="card-body">
                <p>You are about to permanently delete <strong><?= sanitize($patient['full_name']) ?></strong>
                    (<?= sanitize($patient['patient_id']) ?>).</p>
                <p class="mb-2">The following related records will also be removed:</p>
                <ul class="list-group list-group-flush mb-3">
                    <?php foreach ($counts as $label => $count): ?>
                        <li class="list-group-item d-flex justify-content-between py-1">
                            <span><?= sanitize($label) ?></span><strong><?= $count ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="alert alert-warning mb-0"><i class="bi bi-info-circle me-2"></i>This action cannot be undone. Create a backup first if you are unsure.</div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-between">
                <a href="<?= BASE_URL ?>/modules/patients/view.php?id=<?= $id ?>" class="btn btn-outline-secondary">Cancel</a>
                <form method="post" class="m-0">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <button class="btn btn-danger" data-confirm="Delete this patient and all related records permanently?">
                        <i class="bi bi-trash me-1"></i>Yes, delete permanently
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once INC_PATH . '/footer.php'; ?>
