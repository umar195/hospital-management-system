<?php
/**
 * Follow-Up management.
 *
 * Tabs: Today / Upcoming / Overdue / Completed / Cancelled.
 * Starting a follow-up creates a new consultation visit linked to the
 * original visit, puts the patient in the doctor queue and later (when the
 * consultation is completed) closes the follow-up automatically.
 */
require_once __DIR__ . '/../../config/session.php';

$tab = get('tab', 'today');

// ---------------------------------------------------------------- actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(BASE_URL . '/modules/followups/index.php');
    $action = post('action');
    $fid = (int)post('id');
    $followUp = $fid ? fetchOne('SELECT f.*, p.full_name FROM follow_ups f JOIN patients p ON p.id = f.patient_id WHERE f.id = ?', [$fid]) : null;

    if ($followUp) {
        try {
            if ($action === 'start') {
                if ($followUp['status'] !== 'Scheduled') {
                    throw new RuntimeException('This follow-up is not open any more.');
                }
                $pdo->beginTransaction();
                $fee = $followUp['doctor_id'] ? (float)fetchValue('SELECT consultation_fee FROM doctors WHERE id = ?', [(int)$followUp['doctor_id']], 0) : 0;
                $visitId = createQueueVisit([
                    'patient_id'            => (int)$followUp['patient_id'],
                    'visit_type'            => 'Follow-Up',
                    'doctor_id'             => $followUp['doctor_id'] ?: null,
                    'follow_up_of_visit_id' => $followUp['visit_id'] ?: null,
                    'chief_complaint'       => $followUp['reason'],
                    'notes'                 => $followUp['notes'],
                    'consultation_fee'      => $fee,
                ]);
                $pdo->prepare('UPDATE follow_ups SET new_visit_id = ? WHERE id = ?')->execute([$visitId, $fid]);
                $pdo->commit();
                logActivity('Follow-up started', 'Follow-up consultation started for ' . $followUp['full_name'], 'follow_ups', $fid);
                flash('success', 'Follow-up consultation created and added to the doctor queue.');
                redirect(BASE_URL . '/modules/queue/index.php');
            } elseif ($action === 'complete') {
                $pdo->prepare("UPDATE follow_ups SET status = 'Completed' WHERE id = ?")->execute([$fid]);
                logActivity('Follow-up completed', 'Follow-up for ' . $followUp['full_name'] . ' marked completed', 'follow_ups', $fid);
                flash('success', 'Follow-up marked as completed.');
            } elseif ($action === 'missed') {
                $pdo->prepare("UPDATE follow_ups SET status = 'Missed' WHERE id = ?")->execute([$fid]);
                logActivity('Follow-up missed', 'Follow-up for ' . $followUp['full_name'] . ' marked missed', 'follow_ups', $fid);
                flash('warning', 'Follow-up marked as missed.');
            } elseif ($action === 'cancel') {
                $pdo->prepare("UPDATE follow_ups SET status = 'Cancelled' WHERE id = ?")->execute([$fid]);
                logActivity('Follow-up cancelled', 'Follow-up for ' . $followUp['full_name'] . ' cancelled', 'follow_ups', $fid);
                flash('warning', 'Follow-up cancelled.');
            } elseif ($action === 'reschedule') {
                $newDate = post('follow_up_date');
                $newTime = post('follow_up_time') ?: null;
                $newDoctor = (int)post('doctor_id') ?: null;
                $newReason = post('reason') ?: null;
                $newNotes = post('notes') ?: null;
                if (!$newDate) {
                    throw new RuntimeException('Please choose a new follow-up date.');
                }
                // Keep the history of the original schedule instead of silently overwriting it.
                $historyLine = date('d M Y H:i') . ' — rescheduled from ' . formatDate($followUp['follow_up_date'])
                    . ($followUp['follow_up_time'] ? ' ' . formatTime($followUp['follow_up_time']) : '')
                    . ' to ' . formatDate($newDate) . ($newTime ? ' ' . formatTime($newTime) : '')
                    . ' by ' . ($currentUser['full_name'] ?: $currentUser['username']);
                $history = trim(($followUp['reschedule_history'] ? $followUp['reschedule_history'] . "\n" : '') . $historyLine);
                $pdo->prepare("UPDATE follow_ups SET follow_up_date = ?, follow_up_time = ?, doctor_id = ?,
                               reason = COALESCE(?, reason), notes = COALESCE(?, notes),
                               status = 'Scheduled', reschedule_history = ? WHERE id = ?")
                    ->execute([$newDate, $newTime, $newDoctor ?: $followUp['doctor_id'], $newReason, $newNotes, $history, $fid]);
                logActivity('Follow-up rescheduled', 'Follow-up for ' . $followUp['full_name'] . ' moved to ' . $newDate, 'follow_ups', $fid);
                flash('success', 'Follow-up rescheduled to ' . formatDate($newDate) . '.');
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('danger', friendlyError($e, 'Could not update the follow-up.'));
        }
    }
    redirect(BASE_URL . '/modules/followups/index.php?tab=' . urlencode($tab));
}

// ------------------------------------------------------------------- tabs
$today = date('Y-m-d');
$tabDefs = [
    'today'     => ["f.status = 'Scheduled' AND f.follow_up_date = ?", [$today]],
    'upcoming'  => ["f.status = 'Scheduled' AND f.follow_up_date > ?", [$today]],
    'overdue'   => ["((f.status = 'Scheduled' AND f.follow_up_date < ?) OR f.status = 'Missed')", [$today]],
    'completed' => ["f.status = 'Completed'", []],
    'cancelled' => ["f.status = 'Cancelled'", []],
];
if (!isset($tabDefs[$tab])) {
    $tab = 'today';
}

$counts = [];
foreach ($tabDefs as $key => [$cond, $condParams]) {
    $counts[$key] = (int)fetchValue("SELECT COUNT(*) FROM follow_ups f WHERE $cond", $condParams);
}

$doctorFilter = (int)get('doctor_id');
[$cond, $params] = $tabDefs[$tab];
$where = [$cond];
if ($doctorFilter > 0) {
    $where[] = 'f.doctor_id = ?';
    $params[] = $doctorFilter;
}
$whereSql = 'WHERE ' . implode(' AND ', $where);
$orderSql = in_array($tab, ['completed', 'cancelled'], true) ? 'f.follow_up_date DESC' : 'f.follow_up_date, f.follow_up_time';

$followUps = fetchAll("SELECT f.*, p.full_name, p.patient_id AS patient_code, p.phone, p.whatsapp,
                       d.name AS doctor_name, v.visit_number AS origin_visit_number, v.id AS origin_visit_id,
                       nv.visit_number AS new_visit_number
                       FROM follow_ups f
                       JOIN patients p ON p.id = f.patient_id
                       LEFT JOIN doctors d ON d.id = f.doctor_id
                       LEFT JOIN visits v ON v.id = f.visit_id
                       LEFT JOIN visits nv ON nv.id = f.new_visit_id
                       $whereSql ORDER BY $orderSql LIMIT 200", $params);

$doctors = fetchAll('SELECT id, name FROM doctors WHERE is_active = 1 ORDER BY name');

$pageTitle = 'Follow-Ups';
$pageSubtitle = $counts['today'] . ' due today · ' . $counts['overdue'] . ' overdue · ' . $counts['upcoming'] . ' upcoming';
$activeMenu = 'followups';
require_once INC_PATH . '/header.php';

$tabLabels = [
    'today'     => ['Today', 'calendar-day', 'warning'],
    'upcoming'  => ['Upcoming', 'calendar-plus', 'info'],
    'overdue'   => ['Overdue', 'exclamation-triangle', 'danger'],
    'completed' => ['Completed', 'check2-circle', 'success'],
    'cancelled' => ['Cancelled', 'x-circle', 'secondary'],
];
?>

<ul class="nav nav-pills mb-3 flex-wrap gap-1">
    <?php foreach ($tabLabels as $key => [$label, $icon]): ?>
        <li class="nav-item">
            <a class="nav-link <?= $tab === $key ? 'active' : '' ?>" href="?tab=<?= $key ?><?= $doctorFilter ? '&doctor_id=' . $doctorFilter : '' ?>">
                <i class="bi bi-<?= $icon ?> me-1"></i><?= $label ?>
                <span class="badge <?= $tab === $key ? 'bg-white text-primary' : 'bg-light text-dark' ?> ms-1"><?= $counts[$key] ?></span>
            </a>
        </li>
    <?php endforeach; ?>
    <li class="nav-item ms-auto">
        <form method="get" class="d-flex gap-2">
            <input type="hidden" name="tab" value="<?= sanitize($tab) ?>">
            <select name="doctor_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All doctors</option>
                <?php foreach ($doctors as $d): ?>
                    <option value="<?= (int)$d['id'] ?>" <?= $doctorFilter === (int)$d['id'] ? 'selected' : '' ?>><?= sanitize($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </li>
</ul>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Patient</th><th>Doctor</th><th>Original visit</th><th>Follow-up date</th><th>Type</th><th>Reason</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php if (!$followUps): ?>
                <tr><td colspan="8"><div class="empty-state"><i class="bi bi-arrow-repeat"></i>No follow-ups in this list.</div></td></tr>
            <?php endif; ?>
            <?php foreach ($followUps as $f): $display = followUpDisplayStatus($f); ?>
                <tr>
                    <td>
                        <a href="<?= BASE_URL ?>/modules/patients/view.php?id=<?= (int)$f['patient_id'] ?>" class="fw-semibold small"><?= sanitize($f['full_name']) ?></a>
                        <div class="small text-muted"><?= sanitize($f['patient_code']) ?> · <?= sanitize($f['phone'] ?: 'no phone') ?></div>
                    </td>
                    <td class="small"><?= sanitize($f['doctor_name'] ?: '-') ?></td>
                    <td class="small">
                        <?php if ($f['origin_visit_id']): ?>
                            <a href="<?= BASE_URL ?>/modules/consultations/view.php?id=<?= (int)$f['origin_visit_id'] ?>"><?= sanitize($f['origin_visit_number']) ?></a>
                        <?php else: ?>-<?php endif; ?>
                        <?php if ($f['new_visit_number']): ?>
                            <div class="text-muted">→ <a href="<?= BASE_URL ?>/modules/consultations/view.php?id=<?= (int)$f['new_visit_id'] ?>"><?= sanitize($f['new_visit_number']) ?></a></div>
                        <?php endif; ?>
                    </td>
                    <td class="small">
                        <?= formatDate($f['follow_up_date']) ?><?= $f['follow_up_time'] ? ' · ' . formatTime($f['follow_up_time']) : '' ?>
                        <?php if ($f['reschedule_history']): ?>
                            <i class="bi bi-clock-history text-muted" data-bs-toggle="tooltip" title="<?= sanitize($f['reschedule_history']) ?>"></i>
                        <?php endif; ?>
                    </td>
                    <td class="small"><?= sanitize($f['follow_up_type']) ?></td>
                    <td class="small text-truncate" style="max-width:160px"><?= sanitize($f['reason'] ?: '-') ?></td>
                    <td><?= statusBadge($display) ?></td>
                    <td class="text-end text-nowrap">
                        <?php if ($f['status'] === 'Scheduled' || $f['status'] === 'Missed'): ?>
                            <?php if ($f['status'] === 'Scheduled'): ?>
                                <form method="post" class="d-inline"><?= csrfField() ?>
                                    <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                                    <button class="btn btn-sm btn-primary py-0" name="action" value="start" title="Start follow-up consultation"><i class="bi bi-play-fill"></i> Start</button>
                                </form>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-outline-primary py-0" type="button" data-bs-toggle="modal" data-bs-target="#rescheduleModal"
                                    data-id="<?= (int)$f['id'] ?>" data-date="<?= sanitize($f['follow_up_date']) ?>" data-time="<?= sanitize($f['follow_up_time']) ?>"
                                    data-doctor="<?= (int)$f['doctor_id'] ?>" data-reason="<?= sanitize($f['reason']) ?>" title="Reschedule"><i class="bi bi-calendar2-week"></i></button>
                            <form method="post" class="d-inline"><?= csrfField() ?>
                                <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                                <button class="btn btn-sm btn-outline-success py-0" name="action" value="complete" title="Mark completed"><i class="bi bi-check2"></i></button>
                                <?php if ($display === 'Overdue' && $f['status'] === 'Scheduled'): ?>
                                    <button class="btn btn-sm btn-outline-dark py-0" name="action" value="missed" title="Mark missed"><i class="bi bi-person-x"></i></button>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-outline-danger py-0" name="action" value="cancel" title="Cancel" data-confirm="Cancel this follow-up?"><i class="bi bi-x"></i></button>
                            </form>
                        <?php endif; ?>
                        <?php if ($f['whatsapp'] || $f['phone']): ?>
                            <a class="btn btn-sm btn-outline-success py-0" target="_blank" rel="noopener" title="WhatsApp reminder"
                               href="<?= sanitize(whatsappLink($f['whatsapp'] ?: $f['phone'], 'Dear ' . $f['full_name'] . ', this is a reminder for your follow-up visit on ' . formatDate($f['follow_up_date']) . ($f['follow_up_time'] ? ' at ' . formatTime($f['follow_up_time']) : '') . ' at ' . getSetting('hospital_name', 'our hospital') . '.')) ?>">
                                <i class="bi bi-whatsapp"></i>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Reschedule modal -->
<div class="modal fade" id="rescheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="reschedule">
            <input type="hidden" name="id" id="rsId" value="">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-calendar2-week me-2"></i>Reschedule follow-up</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-md-6">
                    <label class="form-label required">New date</label>
                    <input type="date" name="follow_up_date" id="rsDate" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">New time</label>
                    <input type="time" name="follow_up_time" id="rsTime" class="form-control">
                </div>
                <div class="col-12">
                    <label class="form-label">Doctor</label>
                    <select name="doctor_id" id="rsDoctor" class="form-select">
                        <option value="">Keep current doctor</option>
                        <?php foreach ($doctors as $d): ?>
                            <option value="<?= (int)$d['id'] ?>"><?= sanitize($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Reason</label>
                    <input type="text" name="reason" id="rsReason" class="form-control" placeholder="Leave empty to keep the current reason">
                </div>
                <div class="col-12">
                    <label class="form-label">Additional notes</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Leave empty to keep the current notes"></textarea>
                </div>
                <div class="col-12 small text-muted">
                    <i class="bi bi-info-circle me-1"></i>The previous schedule is kept in the follow-up history.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Reschedule</button>
            </div>
        </form>
    </div>
</div>

<?php
$pageScripts = <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('rescheduleModal');
    if (!modal) { return; }
    modal.addEventListener('show.bs.modal', function (event) {
        var btn = event.relatedTarget;
        if (!btn) { return; }
        document.getElementById('rsId').value = btn.dataset.id || '';
        document.getElementById('rsDate').value = btn.dataset.date || '';
        document.getElementById('rsTime').value = btn.dataset.time || '';
        document.getElementById('rsDoctor').value = btn.dataset.doctor || '';
        document.getElementById('rsReason').placeholder = btn.dataset.reason ? 'Current: ' + btn.dataset.reason : 'Reason';
    });
});
</script>
HTML;
require_once INC_PATH . '/footer.php';
