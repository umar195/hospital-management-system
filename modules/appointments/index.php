<?php
/** Appointments list with day view, filters and quick status updates. */
require_once __DIR__ . '/../../config/session.php';

// ---------------------------------------------------------------- actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(BASE_URL . '/modules/appointments/index.php');
    $action = post('action');
    $id = (int)post('id');

    if ($action === 'status' && $id) {
        $status = post('status');
        if (in_array($status, ['Scheduled', 'Arrived', 'Completed', 'Cancelled', 'No Show'], true)) {
            $pdo->prepare('UPDATE appointments SET status = ? WHERE id = ?')->execute([$status, $id]);
            logActivity('Appointment status', 'Appointment #' . $id . ' -> ' . $status, 'appointments', $id);
            flash('success', 'Appointment marked as ' . $status . '.');
        }
    } elseif ($action === 'delete' && $id) {
        $pdo->prepare('DELETE FROM appointments WHERE id = ?')->execute([$id]);
        logActivity('Appointment deleted', 'Appointment #' . $id, 'appointments', $id);
        flash('success', 'Appointment deleted.');
    }
    redirect(BASE_URL . '/modules/appointments/index.php?' . http_build_query(array_diff_key($_GET, ['page' => 1])));
}

// ---------------------------------------------------------------- filters
$search  = get('q');
$date    = get('date');
$status  = get('status');
$doctor  = (int)get('doctor_id');
$page    = currentPage();
$offset  = ($page - 1) * PER_PAGE;

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(p.full_name LIKE ? OR p.patient_id LIKE ? OR p.phone LIKE ? OR a.purpose LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($date !== '') {
    $where[] = 'a.appointment_date = ?';
    $params[] = $date;
}
if (in_array($status, ['Scheduled', 'Arrived', 'Completed', 'Cancelled', 'No Show'], true)) {
    $where[] = 'a.status = ?';
    $params[] = $status;
}
if ($doctor > 0) {
    $where[] = 'a.doctor_id = ?';
    $params[] = $doctor;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int)fetchValue("SELECT COUNT(*) FROM appointments a JOIN patients p ON p.id = a.patient_id $whereSql", $params);
$appointments = fetchAll("SELECT a.*, p.full_name, p.patient_id AS patient_code, p.phone, p.whatsapp, d.name AS doctor_name
                          FROM appointments a
                          JOIN patients p ON p.id = a.patient_id
                          LEFT JOIN doctors d ON d.id = a.doctor_id
                          $whereSql
                          ORDER BY a.appointment_date DESC, a.appointment_time DESC
                          LIMIT " . (int)PER_PAGE . ' OFFSET ' . (int)$offset, $params);

$doctors = fetchAll('SELECT id, name FROM doctors WHERE is_active = 1 ORDER BY name');
$todayCount = (int)fetchValue('SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()');

$pageTitle = 'Appointments';
$pageSubtitle = number_format($total) . ' appointment(s) · ' . $todayCount . ' scheduled today';
$activeMenu = 'appointments';
$pageActions = '<a href="' . BASE_URL . '/modules/appointments/add.php" class="btn btn-primary"><i class="bi bi-calendar-plus me-1"></i>New Appointment</a>';
require_once INC_PATH . '/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="search" name="q" class="form-control" value="<?= sanitize($search) ?>" placeholder="Patient or purpose">
            </div>
            <div class="col-md-2">
                <label class="form-label">Date</label>
                <input type="date" name="date" class="form-control" value="<?= sanitize($date) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <?php foreach (['Scheduled', 'Arrived', 'Completed', 'Cancelled', 'No Show'] as $s): ?>
                        <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
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
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Filter</button>
                <a href="<?= BASE_URL ?>/modules/appointments/index.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
            <div class="col-12">
                <a href="?date=<?= date('Y-m-d') ?>" class="btn btn-link btn-sm p-0 me-3">Today</a>
                <a href="?date=<?= date('Y-m-d', strtotime('+1 day')) ?>" class="btn btn-link btn-sm p-0 me-3">Tomorrow</a>
                <a href="?status=Scheduled" class="btn btn-link btn-sm p-0">Upcoming scheduled</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Date</th><th>Time</th><th>Patient</th><th>Doctor</th><th>Purpose</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php if (!$appointments): ?>
                <tr><td colspan="7"><div class="empty-state"><i class="bi bi-calendar-x"></i>No appointments found.
                    <div class="mt-2"><a href="add.php" class="btn btn-sm btn-primary">Schedule an appointment</a></div></div></td></tr>
            <?php endif; ?>
            <?php foreach ($appointments as $a): ?>
                <tr>
                    <td class="small"><?= formatDate($a['appointment_date']) ?></td>
                    <td class="small"><?= formatTime($a['appointment_time']) ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/modules/patients/view.php?id=<?= (int)$a['patient_id'] ?>" class="fw-semibold small"><?= sanitize($a['full_name']) ?></a>
                        <div class="small text-muted"><?= sanitize($a['patient_code']) ?> · <?= sanitize($a['phone'] ?: 'no phone') ?></div>
                    </td>
                    <td class="small"><?= sanitize($a['doctor_name'] ?: '-') ?></td>
                    <td class="small"><?= sanitize($a['purpose'] ?: '-') ?></td>
                    <td><?= statusBadge($a['status']) ?></td>
                    <td class="text-end text-nowrap">
                        <form method="post" class="d-inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="status">
                            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-info" name="status" value="Arrived" title="Mark arrived"><i class="bi bi-door-open"></i></button>
                                <button class="btn btn-outline-success" name="status" value="Completed" title="Mark completed"><i class="bi bi-check2"></i></button>
                                <button class="btn btn-outline-danger" name="status" value="Cancelled" title="Cancel"><i class="bi bi-x"></i></button>
                            </div>
                        </form>
                        <a href="edit.php?id=<?= (int)$a['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                        <?php if ($a['whatsapp'] || $a['phone']): ?>
                            <a class="btn btn-sm btn-outline-success" target="_blank" rel="noopener" title="WhatsApp reminder"
                               href="<?= sanitize(whatsappLink($a['whatsapp'] ?: $a['phone'], 'Dear ' . $a['full_name'] . ', this is a reminder for your appointment on ' . formatDate($a['appointment_date']) . ' at ' . formatTime($a['appointment_time']) . ' at ' . getSetting('hospital_name', 'our hospital') . '.')) ?>">
                                <i class="bi bi-whatsapp"></i>
                            </a>
                        <?php endif; ?>
                        <form method="post" class="d-inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" data-confirm="Delete this appointment?" title="Delete"><i class="bi bi-trash"></i></button>
                        </form>
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
