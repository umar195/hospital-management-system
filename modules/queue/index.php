<?php
/**
 * Doctor Queue / Waiting Room.
 *
 * Lists every consultation visit for the selected day with its token number
 * and queue status, and lets the operator move patients through the queue:
 * Waiting -> Called -> In Consultation -> Completed (or Cancelled / No Show).
 */
require_once __DIR__ . '/../../config/session.php';

// ---------------------------------------------------------------- actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(BASE_URL . '/modules/queue/index.php');
    $action = post('action');
    $visitId = (int)post('visit_id');
    $visit = $visitId ? fetchOne('SELECT * FROM visits WHERE id = ?', [$visitId]) : null;

    if ($visit) {
        try {
            if ($action === 'call') {
                $pdo->prepare("UPDATE visits SET queue_status = 'Called' WHERE id = ?")->execute([$visitId]);
                logActivity('Patient called', 'Token #' . $visit['token_number'] . ' called', 'visits', $visitId);
                flash('info', 'Token #' . (int)$visit['token_number'] . ' called.');
            } elseif ($action === 'start') {
                $pdo->prepare("UPDATE visits SET queue_status = 'In Consultation',
                               consultation_started_at = COALESCE(consultation_started_at, NOW()) WHERE id = ?")->execute([$visitId]);
                if ($visit['appointment_id']) {
                    $pdo->prepare("UPDATE appointments SET status = 'In Consultation' WHERE id = ?")->execute([(int)$visit['appointment_id']]);
                }
                logActivity('Consultation started', 'Visit ' . $visit['visit_number'], 'visits', $visitId);
                redirect(BASE_URL . '/modules/consultations/consult.php?visit_id=' . $visitId);
            } elseif ($action === 'waiting') {
                $pdo->prepare("UPDATE visits SET queue_status = 'Waiting' WHERE id = ?")->execute([$visitId]);
                flash('info', 'Patient moved back to the waiting list.');
            } elseif ($action === 'cancel') {
                $pdo->prepare("UPDATE visits SET queue_status = 'Cancelled' WHERE id = ?")->execute([$visitId]);
                if ($visit['appointment_id']) {
                    $pdo->prepare("UPDATE appointments SET status = 'Cancelled' WHERE id = ?")->execute([(int)$visit['appointment_id']]);
                }
                logActivity('Queue entry cancelled', 'Visit ' . $visit['visit_number'], 'visits', $visitId);
                flash('warning', 'Queue entry cancelled.');
            } elseif ($action === 'no_show') {
                $pdo->prepare("UPDATE visits SET queue_status = 'No Show' WHERE id = ?")->execute([$visitId]);
                if ($visit['appointment_id']) {
                    $pdo->prepare("UPDATE appointments SET status = 'No Show' WHERE id = ?")->execute([(int)$visit['appointment_id']]);
                }
                logActivity('Queue no-show', 'Visit ' . $visit['visit_number'], 'visits', $visitId);
                flash('warning', 'Patient marked as no show.');
            }
        } catch (Throwable $e) {
            flash('danger', friendlyError($e, 'Could not update the queue.'));
        }
    }
    redirect(BASE_URL . '/modules/queue/index.php?' . http_build_query($_GET));
}

// ---------------------------------------------------------------- filters
$date = get('date') ?: date('Y-m-d');
$doctor = (int)get('doctor_id');
$statusFilter = get('status');

$where = ['DATE(v.visit_date) = ?', 'v.queue_status IS NOT NULL'];
$params = [$date];
if ($doctor > 0) {
    $where[] = 'v.doctor_id = ?';
    $params[] = $doctor;
}
if (in_array($statusFilter, ['Waiting', 'Called', 'In Consultation', 'Completed', 'Cancelled', 'No Show'], true)) {
    $where[] = 'v.queue_status = ?';
    $params[] = $statusFilter;
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$queue = fetchAll("SELECT v.*, p.full_name, p.patient_id AS patient_code, p.gender, p.age, p.phone, d.name AS doctor_name
                   FROM visits v
                   JOIN patients p ON p.id = v.patient_id
                   LEFT JOIN doctors d ON d.id = v.doctor_id
                   $whereSql
                   ORDER BY FIELD(v.queue_status,'In Consultation','Called','Waiting','Completed','No Show','Cancelled'), v.token_number, v.id",
                   $params);

$counts = ['Waiting' => 0, 'Called' => 0, 'In Consultation' => 0, 'Completed' => 0];
foreach (fetchAll("SELECT queue_status, COUNT(*) AS c FROM visits WHERE DATE(visit_date) = ? AND queue_status IS NOT NULL GROUP BY queue_status", [$date]) as $row) {
    $counts[$row['queue_status']] = (int)$row['c'];
}

$doctors = fetchAll('SELECT id, name, specialty FROM doctors WHERE is_active = 1 ORDER BY name');

$pageTitle = 'Doctor Queue';
$pageSubtitle = 'Waiting room for ' . formatDate($date);
$activeMenu = 'queue';
$pageActions = '<a href="' . BASE_URL . '/modules/walkin/index.php" class="btn btn-primary"><i class="bi bi-lightning-charge me-1"></i>New Walk-In</a> '
    . '<a href="' . BASE_URL . '/modules/appointments/index.php?date=' . date('Y-m-d') . '" class="btn btn-outline-primary"><i class="bi bi-calendar-check me-1"></i>Appointments</a>';
require_once INC_PATH . '/header.php';
?>

<div class="row g-3 mb-3">
    <?php
    $queueCards = [
        ['Waiting', 'hourglass-split', 'warning'],
        ['Called', 'megaphone', 'info'],
        ['In Consultation', 'person-badge', 'primary'],
        ['Completed', 'check2-circle', 'success'],
    ];
    foreach ($queueCards as [$label, $icon, $color]): ?>
        <div class="col-6 col-xl-3">
            <a class="stat-card stat-outline stat-<?= $color ?>" href="?date=<?= sanitize($date) ?>&status=<?= urlencode($label) ?>">
                <div class="stat-icon"><i class="bi bi-<?= $icon ?>"></i></div>
                <div>
                    <div class="stat-value"><?= (int)($counts[$label] ?? 0) ?></div>
                    <div class="stat-label"><?= $label ?></div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-3">
                <label class="form-label">Date</label>
                <input type="date" name="date" class="form-control" value="<?= sanitize($date) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Doctor</label>
                <select name="doctor_id" class="form-select">
                    <option value="">All doctors</option>
                    <?php foreach ($doctors as $d): ?>
                        <option value="<?= (int)$d['id'] ?>" <?= $doctor === (int)$d['id'] ? 'selected' : '' ?>><?= sanitize($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All statuses</option>
                    <?php foreach (['Waiting', 'Called', 'In Consultation', 'Completed', 'Cancelled', 'No Show'] as $s): ?>
                        <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="<?= BASE_URL ?>/modules/queue/index.php" class="btn btn-outline-secondary btn-sm">Today</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th style="width:70px">Token</th><th>Patient</th><th>Doctor</th><th>Visit Type</th><th>Arrival</th><th>Reason</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php if (!$queue): ?>
                <tr><td colspan="8"><div class="empty-state"><i class="bi bi-people"></i>No patients in the queue for this day.
                    <div class="mt-2"><a href="<?= BASE_URL ?>/modules/walkin/index.php" class="btn btn-sm btn-primary">Register a walk-in</a></div></div></td></tr>
            <?php endif; ?>
            <?php foreach ($queue as $v): ?>
                <tr>
                    <td><?= tokenBadge($v['token_number']) ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/modules/patients/view.php?id=<?= (int)$v['patient_id'] ?>" class="fw-semibold small"><?= sanitize($v['full_name']) ?></a>
                        <div class="small text-muted"><?= sanitize($v['patient_code']) ?> · <?= sanitize($v['gender']) ?><?= $v['age'] !== null ? ' · ' . (int)$v['age'] . 'y' : '' ?></div>
                    </td>
                    <td class="small"><?= sanitize($v['doctor_name'] ?: '-') ?></td>
                    <td class="small"><?= sanitize($v['visit_type']) ?><?= $v['follow_up_of_visit_id'] ? ' <span class="badge badge-status bg-info">Follow-Up</span>' : '' ?></td>
                    <td class="small"><?= formatTime($v['checked_in_at'] ?: $v['visit_date']) ?></td>
                    <td class="small text-truncate" style="max-width:180px"><?= sanitize($v['chief_complaint'] ?: ($v['symptoms'] ?: '-')) ?></td>
                    <td><?= statusBadge($v['queue_status']) ?></td>
                    <td class="text-end text-nowrap">
                        <?php if (in_array($v['queue_status'], ['Waiting', 'Called'], true)): ?>
                            <form method="post" class="d-inline"><?= csrfField() ?>
                                <input type="hidden" name="visit_id" value="<?= (int)$v['id'] ?>">
                                <?php if ($v['queue_status'] === 'Waiting'): ?>
                                    <button class="btn btn-sm btn-outline-info" name="action" value="call" title="Call patient"><i class="bi bi-megaphone"></i></button>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-primary" name="action" value="start" title="Start consultation"><i class="bi bi-play-fill"></i> Start</button>
                                <button class="btn btn-sm btn-outline-dark" name="action" value="no_show" title="Mark no show" data-confirm="Mark this patient as no show?"><i class="bi bi-person-x"></i></button>
                                <button class="btn btn-sm btn-outline-danger" name="action" value="cancel" title="Cancel" data-confirm="Cancel this queue entry?"><i class="bi bi-x"></i></button>
                            </form>
                        <?php elseif ($v['queue_status'] === 'In Consultation'): ?>
                            <a href="<?= BASE_URL ?>/modules/consultations/consult.php?visit_id=<?= (int)$v['id'] ?>" class="btn btn-sm btn-primary"><i class="bi bi-pencil-square me-1"></i>Open consultation</a>
                        <?php elseif ($v['queue_status'] === 'Completed'): ?>
                            <a href="<?= BASE_URL ?>/modules/consultations/view.php?id=<?= (int)$v['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>View</a>
                        <?php else: ?>
                            <form method="post" class="d-inline"><?= csrfField() ?>
                                <input type="hidden" name="visit_id" value="<?= (int)$v['id'] ?>">
                                <button class="btn btn-sm btn-outline-secondary" name="action" value="waiting" title="Back to waiting"><i class="bi bi-arrow-counterclockwise"></i> Re-queue</button>
                            </form>
                        <?php endif; ?>
                        <a href="<?= BASE_URL ?>/modules/patients/view.php?id=<?= (int)$v['patient_id'] ?>" class="btn btn-sm btn-outline-secondary" title="View patient"><i class="bi bi-person"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once INC_PATH . '/footer.php'; ?>
