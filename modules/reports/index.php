<?php
/** Laboratory reports list. */
require_once __DIR__ . '/../../config/session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(BASE_URL . '/modules/reports/index.php');
    $id = (int)post('id');
    if (post('action') === 'delete' && $id) {
        $pdo->prepare('DELETE FROM reports WHERE id = ?')->execute([$id]);
        logActivity('Report deleted', 'Report #' . $id, 'reports', $id);
        flash('success', 'Report deleted.');
    }
    redirect(BASE_URL . '/modules/reports/index.php');
}

$search = get('q');
$status = get('status');
$from   = get('from');
$to     = get('to');
$page   = currentPage();
$offset = ($page - 1) * PER_PAGE;

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(r.report_number LIKE ? OR o.order_number LIKE ? OR p.full_name LIKE ? OR p.patient_id LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}
if (in_array($status, ['Draft', 'Final'], true)) {
    $where[] = 'r.status = ?';
    $params[] = $status;
}
if ($from !== '') {
    $where[] = 'DATE(r.generated_at) >= ?';
    $params[] = $from;
}
if ($to !== '') {
    $where[] = 'DATE(r.generated_at) <= ?';
    $params[] = $to;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int)fetchValue("SELECT COUNT(*) FROM reports r JOIN test_orders o ON o.id = r.order_id JOIN patients p ON p.id = r.patient_id $whereSql", $params);
$reports = fetchAll("SELECT r.*, o.order_number, p.full_name, p.patient_id AS patient_code, p.phone, p.whatsapp,
        (SELECT COUNT(*) FROM test_order_items i WHERE i.order_id = o.id) AS test_count
    FROM reports r JOIN test_orders o ON o.id = r.order_id JOIN patients p ON p.id = r.patient_id
    $whereSql ORDER BY r.generated_at DESC, r.id DESC LIMIT " . (int)PER_PAGE . ' OFFSET ' . (int)$offset, $params);

$pageTitle = 'Reports';
$pageSubtitle = number_format($total) . ' report(s)';
$activeMenu = 'reports';
$pageActions = '<a href="' . BASE_URL . '/modules/orders/index.php" class="btn btn-outline-primary"><i class="bi bi-clipboard2-pulse me-1"></i>Test orders</a>';
require_once INC_PATH . '/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="search" name="q" class="form-control" value="<?= sanitize($search) ?>" placeholder="Report #, order #, patient">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="Final" <?= $status === 'Final' ? 'selected' : '' ?>>Final</option>
                    <option value="Draft" <?= $status === 'Draft' ? 'selected' : '' ?>>Draft</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">From</label>
                <input type="date" name="from" class="form-control" value="<?= sanitize($from) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">To</label>
                <input type="date" name="to" class="form-control" value="<?= sanitize($to) ?>">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Filter</button>
                <a href="<?= BASE_URL ?>/modules/reports/index.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Report #</th><th>Patient</th><th>Order</th><th>Generated</th><th>Tests</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php if (!$reports): ?>
                <tr><td colspan="7"><div class="empty-state"><i class="bi bi-file-medical"></i>No reports generated yet.
                    <div class="mt-2 small">Enter results for an order and generate its report.</div></div></td></tr>
            <?php endif; ?>
            <?php foreach ($reports as $r): ?>
                <tr>
                    <td><a href="view.php?id=<?= (int)$r['id'] ?>" class="fw-semibold text-decoration-none"><?= sanitize($r['report_number']) ?></a></td>
                    <td>
                        <div class="fw-semibold"><?= sanitize($r['full_name']) ?></div>
                        <small class="text-muted"><?= sanitize($r['patient_code']) ?></small>
                    </td>
                    <td class="small"><a href="<?= BASE_URL ?>/modules/orders/view.php?id=<?= (int)$r['order_id'] ?>"><?= sanitize($r['order_number']) ?></a></td>
                    <td class="small"><?= formatDateTime($r['generated_at']) ?></td>
                    <td class="small"><?= (int)$r['test_count'] ?></td>
                    <td><?= $r['status'] === 'Final' ? '<span class="badge bg-success">Final</span>' : '<span class="badge bg-warning text-dark">Draft</span>' ?></td>
                    <td class="text-end text-nowrap">
                        <a href="view.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                        <a href="print.php?id=<?= (int)$r['id'] ?>" target="_blank" class="btn btn-sm btn-outline-success" title="Print"><i class="bi bi-printer"></i></a>
                        <?php $phone = $r['whatsapp'] ?: $r['phone']; ?>
                        <?php if ($phone): ?>
                            <a href="<?= sanitize(whatsappLink($phone, 'Dear ' . $r['full_name'] . ', your laboratory report ' . $r['report_number'] . ' from ' . getSetting('hospital_name', APP_NAME) . ' is ready.')) ?>"
                               target="_blank" class="btn btn-sm btn-outline-success" title="Share on WhatsApp"><i class="bi bi-whatsapp"></i></a>
                        <?php endif; ?>
                        <form method="post" class="d-inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" data-confirm="Delete report <?= sanitize($r['report_number']) ?>?" title="Delete"><i class="bi bi-trash"></i></button>
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
