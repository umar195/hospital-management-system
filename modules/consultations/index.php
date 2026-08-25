<?php
/** Consultations list (visits that went through the doctor workflow). */
require_once __DIR__ . '/../../config/session.php';

$search = get('q');
$date   = get('date');
$doctor = (int)get('doctor_id');
$status = get('status');
$page   = currentPage();
$offset = ($page - 1) * PER_PAGE;

$where = ['(v.queue_status IS NOT NULL OR v.doctor_id IS NOT NULL)'];
$params = [];
if ($search !== '') {
    $where[] = '(p.full_name LIKE ? OR p.patient_id LIKE ? OR v.visit_number LIKE ? OR v.diagnosis LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($date !== '') {
    $where[] = 'DATE(v.visit_date) = ?';
    $params[] = $date;
}
if ($doctor > 0) {
    $where[] = 'v.doctor_id = ?';
    $params[] = $doctor;
}
if ($status === 'completed') {
    $where[] = 'v.consultation_completed_at IS NOT NULL';
} elseif ($status === 'open') {
    $where[] = "v.consultation_completed_at IS NULL AND v.queue_status IN ('Waiting','Called','In Consultation')";
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$total = (int)fetchValue("SELECT COUNT(*) FROM visits v JOIN patients p ON p.id = v.patient_id $whereSql", $params);
$visits = fetchAll("SELECT v.*, p.full_name, p.patient_id AS patient_code, d.name AS doctor_name
                    FROM visits v
                    JOIN patients p ON p.id = v.patient_id
                    LEFT JOIN doctors d ON d.id = v.doctor_id
                    $whereSql
                    ORDER BY v.visit_date DESC
                    LIMIT " . (int)PER_PAGE . ' OFFSET ' . (int)$offset, $params);

$doctors = fetchAll('SELECT id, name FROM doctors WHERE is_active = 1 ORDER BY name');

$pageTitle = 'Consultations';
$pageSubtitle = number_format($total) . ' consultation(s)';
$activeMenu = 'consultations';
$pageActions = '<a href="' . BASE_URL . '/modules/queue/index.php" class="btn btn-primary"><i class="bi bi-people me-1"></i>Doctor Queue</a>';
require_once INC_PATH . '/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="search" name="q" class="form-control" value="<?= sanitize($search) ?>" placeholder="Patient, visit # or diagnosis">
            </div>
            <div class="col-md-2">
                <label class="form-label">Date</label>
                <input type="date" name="date" class="form-control" value="<?= sanitize($date) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Doctor</label>
                <select name="doctor_id" class="form-select">
                    <option value="">All</option>
                    <?php foreach ($doctors as $d): ?>
                        <option value="<?= (int)$d['id'] ?>" <?= $doctor === (int)$d['id'] ? 'selected' : '' ?>><?= sanitize($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>Open / in progress</option>
                    <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Filter</button>
                <a href="<?= BASE_URL ?>/modules/consultations/index.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Visit #</th><th>Date</th><th>Patient</th><th>Doctor</th><th>Type</th><th>Diagnosis</th><th>Charges</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php if (!$visits): ?>
                <tr><td colspan="9"><div class="empty-state"><i class="bi bi-clipboard2-heart"></i>No consultations found.</div></td></tr>
            <?php endif; ?>
            <?php foreach ($visits as $v): ?>
                <tr>
                    <td class="small"><?= sanitize($v['visit_number']) ?></td>
                    <td class="small"><?= formatDateTime($v['visit_date']) ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/modules/patients/view.php?id=<?= (int)$v['patient_id'] ?>" class="fw-semibold small"><?= sanitize($v['full_name']) ?></a>
                        <div class="small text-muted"><?= sanitize($v['patient_code']) ?></div>
                    </td>
                    <td class="small"><?= sanitize($v['doctor_name'] ?: '-') ?></td>
                    <td class="small"><?= sanitize($v['visit_type']) ?></td>
                    <td class="small text-truncate" style="max-width:160px"><?= sanitize($v['diagnosis'] ?: '-') ?></td>
                    <td class="small"><?= formatCurrency($v['total_charges']) ?></td>
                    <td><?= $v['queue_status'] ? statusBadge($v['queue_status']) : statusBadge($v['consultation_completed_at'] ? 'Completed' : 'Pending') ?></td>
                    <td class="text-end text-nowrap">
                        <?php if (!$v['consultation_completed_at'] && $v['queue_status'] && in_array($v['queue_status'], ['Waiting', 'Called', 'In Consultation'], true)): ?>
                            <a href="<?= BASE_URL ?>/modules/consultations/consult.php?visit_id=<?= (int)$v['id'] ?>" class="btn btn-sm btn-primary py-0"><i class="bi bi-pencil-square me-1"></i>Consult</a>
                        <?php endif; ?>
                        <a href="<?= BASE_URL ?>/modules/consultations/view.php?id=<?= (int)$v['id'] ?>" class="btn btn-sm btn-outline-primary py-0"><i class="bi bi-eye"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total > PER_PAGE): ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
            <small class="text-muted">Page <?= $page ?> of <?= (int)ceil($total / PER_PAGE) ?></small>
            <?= paginationLinks($total) ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once INC_PATH . '/footer.php'; ?>
