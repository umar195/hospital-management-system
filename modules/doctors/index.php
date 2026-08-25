<?php
/** Doctors list. */
require_once __DIR__ . '/../../config/session.php';

$search = get('q');
$status = get('status');
$page   = currentPage();
$offset = ($page - 1) * PER_PAGE;

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(name LIKE ? OR specialty LIKE ? OR phone LIKE ? OR license_number LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($status === 'active') {
    $where[] = 'is_active = 1';
} elseif ($status === 'inactive') {
    $where[] = 'is_active = 0';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int)fetchValue("SELECT COUNT(*) FROM doctors $whereSql", $params);
$doctors = fetchAll("SELECT d.*,
        (SELECT COUNT(*) FROM appointments a WHERE a.doctor_id = d.id) AS appointment_count
        FROM doctors d $whereSql ORDER BY d.name LIMIT " . (int)PER_PAGE . ' OFFSET ' . (int)$offset, $params);

$pageTitle = 'Doctors';
$pageSubtitle = number_format($total) . ' doctor(s)';
$activeMenu = 'doctors';
$pageActions = '<a href="' . BASE_URL . '/modules/doctors/add.php" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Add Doctor</a>';
require_once INC_PATH . '/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-5">
                <label class="form-label">Search</label>
                <input type="search" name="q" class="form-control" value="<?= sanitize($search) ?>" placeholder="Name, specialty, phone or license">
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Filter</button>
                <a href="<?= BASE_URL ?>/modules/doctors/index.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Name</th><th>Specialty</th><th>Phone</th><th>Email</th><th>License</th><th>Appointments</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php if (!$doctors): ?>
                <tr><td colspan="8"><div class="empty-state"><i class="bi bi-person-badge"></i>No doctors found.
                    <div class="mt-2"><a href="add.php" class="btn btn-sm btn-primary">Add the first doctor</a></div></div></td></tr>
            <?php endif; ?>
            <?php foreach ($doctors as $d): ?>
                <tr>
                    <td class="fw-semibold"><?= sanitize($d['name']) ?></td>
                    <td class="small"><?= sanitize($d['specialty'] ?: '-') ?></td>
                    <td class="small"><?= sanitize($d['phone'] ?: '-') ?></td>
                    <td class="small"><?= sanitize($d['email'] ?: '-') ?></td>
                    <td class="small"><?= sanitize($d['license_number'] ?: '-') ?></td>
                    <td class="small"><?= (int)$d['appointment_count'] ?></td>
                    <td><?= $d['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                    <td class="text-end text-nowrap">
                        <a href="<?= BASE_URL ?>/modules/appointments/add.php?doctor_id=<?= (int)$d['id'] ?>" class="btn btn-sm btn-outline-success" title="New appointment"><i class="bi bi-calendar-plus"></i></a>
                        <a href="edit.php?id=<?= (int)$d['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                        <a href="delete.php?id=<?= (int)$d['id'] ?>" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></a>
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
