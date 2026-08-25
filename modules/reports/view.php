<?php
/** Report detail: preview the results, edit remarks / authorisation, share. */
require_once __DIR__ . '/../../config/session.php';

$id = (int)get('id', post('id'));
$report = $id ? fetchOne('SELECT r.*, o.order_number, o.referring_doctor, o.order_date,
        p.full_name, p.patient_id AS patient_code, p.gender, p.age, p.date_of_birth, p.phone, p.whatsapp
    FROM reports r JOIN test_orders o ON o.id = r.order_id JOIN patients p ON p.id = r.patient_id WHERE r.id = ?', [$id]) : null;

if (!$report) {
    flash('danger', 'Report not found.');
    redirect(BASE_URL . '/modules/reports/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(BASE_URL . '/modules/reports/view.php?id=' . $id);
    if (post('action') === 'delete') {
        $pdo->prepare('DELETE FROM reports WHERE id = ?')->execute([$id]);
        logActivity('Report deleted', $report['report_number'], 'reports', $id);
        flash('success', 'Report deleted.');
        redirect(BASE_URL . '/modules/reports/index.php');
    }
    $status = post('status') === 'Draft' ? 'Draft' : 'Final';
    $pdo->prepare('UPDATE reports SET authorized_by = ?, remarks = ?, status = ? WHERE id = ?')
        ->execute([post('authorized_by') ?: null, post('remarks') ?: null, $status, $id]);
    if ($status === 'Final') {
        $pdo->prepare('UPDATE test_order_items SET status = "Completed" WHERE order_id = ? AND status NOT IN ("Completed","Delivered")')
            ->execute([$report['order_id']]);
    }
    logActivity('Report updated', $report['report_number'], 'reports', $id);
    flash('success', 'Report updated.');
    redirect(BASE_URL . '/modules/reports/view.php?id=' . $id);
}

$rows = fetchAll('SELECT r.*, t.name AS test_name, t.test_code, i.status AS item_status
    FROM test_order_items i
    JOIN tests t ON t.id = i.test_id
    LEFT JOIN test_results r ON r.order_item_id = i.id
    WHERE i.order_id = ? ORDER BY t.name, r.id', [$report['order_id']]);

$grouped = [];
foreach ($rows as $row) {
    $grouped[$row['test_name']][] = $row;
}

$patientAge = $report['age'] !== null ? (int)$report['age'] : ($report['date_of_birth'] ? calculateAge($report['date_of_birth']) : null);
$phone = $report['whatsapp'] ?: $report['phone'];
$waMessage = 'Dear ' . $report['full_name'] . ', your laboratory report ' . $report['report_number'] . ' from '
    . getSetting('hospital_name', APP_NAME) . ' is ready. Please download the attached PDF.';

$pageTitle = 'Report ' . sanitize($report['report_number']);
$pageSubtitle = sanitize($report['full_name']) . ' · ' . formatDateTime($report['generated_at']);
$activeMenu = 'reports';
$pageActions = '<a href="' . BASE_URL . '/modules/reports/print.php?id=' . $id . '" target="_blank" class="btn btn-primary"><i class="bi bi-printer me-1"></i>Print / Save PDF</a> '
    . '<a href="' . BASE_URL . '/modules/reports/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>';
require_once INC_PATH . '/header.php';
?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-activity me-2"></i>Results</span>
                <span class="small text-muted">Order <?= sanitize($report['order_number']) ?></span>
            </div>
            <div class="card-body">
                <?php foreach ($grouped as $testName => $group): ?>
                    <h6 class="text-primary border-bottom pb-2 mb-2"><?= sanitize($testName) ?></h6>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Parameter</th><th>Result</th><th>Unit</th><th>Reference</th><th>Flag</th></tr></thead>
                            <tbody>
                            <?php foreach ($group as $row): ?>
                                <?php if ($row['id'] === null): ?>
                                    <tr><td colspan="5" class="text-muted small">No results entered yet
                                        (<a href="<?= BASE_URL ?>/modules/orders/results.php?id=<?= (int)$report['order_id'] ?>">enter results</a>).</td></tr>
                                <?php else: ?>
                                    <tr class="<?= in_array($row['flag'], ['Low', 'High'], true) ? 'row-abnormal' : '' ?>">
                                        <td class="small"><?= sanitize($row['parameter_name'] ?: $testName) ?></td>
                                        <td class="fw-semibold"><?= sanitize($row['result_value']) ?></td>
                                        <td class="small"><?= sanitize($row['unit']) ?></td>
                                        <td class="small"><?= sanitize($row['reference_range']) ?></td>
                                        <td><?= flagBadge($row['flag']) ?></td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-person-badge me-2"></i>Patient</div>
            <div class="card-body small">
                <div class="fw-semibold"><?= sanitize($report['full_name']) ?></div>
                <div class="text-muted mb-2"><?= sanitize($report['patient_code']) ?></div>
                <ul class="list-unstyled mb-0">
                    <li><strong>Gender:</strong> <?= sanitize($report['gender']) ?></li>
                    <li><strong>Age:</strong> <?= $patientAge !== null ? $patientAge . ' yrs' : '-' ?></li>
                    <li><strong>Phone:</strong> <?= sanitize($report['phone'] ?: '-') ?></li>
                    <li><strong>Referred by:</strong> <?= sanitize($report['referring_doctor'] ?: '-') ?></li>
                    <li><strong>Sample date:</strong> <?= formatDateTime($report['order_date']) ?></li>
                </ul>
            </div>
        </div>

        <form method="post" class="card mb-3">
            <?= csrfField() ?>
            <div class="card-header bg-white fw-semibold"><i class="bi bi-pencil-square me-2"></i>Report details</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Authorized by</label>
                    <input type="text" name="authorized_by" class="form-control"
                           value="<?= sanitize($report['authorized_by'] ?: getSetting('authorized_by', '')) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Remarks / interpretation</label>
                    <textarea name="remarks" class="form-control" rows="4"><?= sanitize($report['remarks']) ?></textarea>
                </div>
                <div class="mb-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="Final" <?= $report['status'] === 'Final' ? 'selected' : '' ?>>Final</option>
                        <option value="Draft" <?= $report['status'] === 'Draft' ? 'selected' : '' ?>>Draft</option>
                    </select>
                    <div class="form-text">Marking as Final completes all tests in the order.</div>
                </div>
            </div>
            <div class="card-footer bg-white d-grid gap-2">
                <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Save changes</button>
            </div>
        </form>

        <div class="card">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-share me-2"></i>Deliver report</div>
            <div class="card-body d-grid gap-2">
                <a href="<?= BASE_URL ?>/modules/reports/print.php?id=<?= (int)$id ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-printer me-1"></i>Print / Save as PDF
                </a>
                <?php if ($phone): ?>
                    <a href="<?= sanitize(whatsappLink($phone, $waMessage)) ?>" target="_blank" class="btn btn-outline-success btn-sm">
                        <i class="bi bi-whatsapp me-1"></i>Share on WhatsApp
                    </a>
                    <div class="form-text">Save the report as PDF first, then attach it in WhatsApp.</div>
                <?php else: ?>
                    <div class="small text-muted">No phone number saved for this patient.</div>
                <?php endif; ?>
                <form method="post" class="mt-2">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete">
                    <button class="btn btn-outline-danger btn-sm w-100" data-confirm="Delete this report?"><i class="bi bi-trash me-1"></i>Delete report</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once INC_PATH . '/footer.php'; ?>
