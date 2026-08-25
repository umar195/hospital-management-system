<?php
/** Patient list with search, filters and pagination. */
require_once __DIR__ . '/../../config/session.php';

$search   = get('q');
$gender   = get('gender');
$blood    = get('blood_group');
$from     = get('from');
$to       = get('to');
$page     = currentPage();
$offset   = ($page - 1) * PER_PAGE;

$where  = [];
$params = [];

if ($search !== '') {
    $where[] = '(full_name LIKE ? OR patient_id LIKE ? OR phone LIKE ? OR cnic LIKE ? OR whatsapp LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like, $like);
}
if (in_array($gender, ['Male', 'Female', 'Other'], true)) {
    $where[] = 'gender = ?';
    $params[] = $gender;
}
if ($blood !== '') {
    $where[] = 'blood_group = ?';
    $params[] = $blood;
}
if ($from !== '') {
    $where[] = 'registration_date >= ?';
    $params[] = $from;
}
if ($to !== '') {
    $where[] = 'registration_date <= ?';
    $params[] = $to;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int)fetchValue("SELECT COUNT(*) FROM patients $whereSql", $params);
$patients = fetchAll("SELECT * FROM patients $whereSql ORDER BY id DESC LIMIT " . (int)PER_PAGE . " OFFSET " . (int)$offset, $params);

$pageTitle = 'Patients';
$pageSubtitle = number_format($total) . ' patient(s) registered';
$activeMenu = 'patients';
$pageActions = '<a href="' . BASE_URL . '/modules/patients/add.php" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>New Patient</a> '
    . '<a href="' . BASE_URL . '/modules/walkin/index.php" class="btn btn-outline-primary"><i class="bi bi-lightning-charge me-1"></i>Walk-In</a>';
require_once INC_PATH . '/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="search" name="q" class="form-control" value="<?= sanitize($search) ?>" placeholder="Name, patient ID, phone or CNIC">
            </div>
            <div class="col-md-2">
                <label class="form-label">Gender</label>
                <select name="gender" class="form-select">
                    <option value="">All</option>
                    <?php foreach (['Male', 'Female', 'Other'] as $g): ?>
                        <option value="<?= $g ?>" <?= $gender === $g ? 'selected' : '' ?>><?= $g ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Blood group</label>
                <select name="blood_group" class="form-select">
                    <option value="">All</option>
                    <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                        <option value="<?= $bg ?>" <?= $blood === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                    <?php endforeach; ?>
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
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Filter</button>
                <a href="<?= BASE_URL ?>/modules/patients/index.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Patient ID</th>
                    <th>Name</th>
                    <th>Gender / Age</th>
                    <th>Phone</th>
                    <th>Blood</th>
                    <th>Registered</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$patients): ?>
                <tr><td colspan="7">
                    <div class="empty-state"><i class="bi bi-people"></i>No patients found.
                        <div class="mt-2"><a href="<?= BASE_URL ?>/modules/patients/add.php" class="btn btn-sm btn-primary">Register the first patient</a></div>
                    </div>
                </td></tr>
            <?php endif; ?>
            <?php foreach ($patients as $p): ?>
                <tr>
                    <td><span class="badge bg-light text-dark"><?= sanitize($p['patient_id']) ?></span></td>
                    <td>
                        <a href="view.php?id=<?= (int)$p['id'] ?>" class="fw-semibold"><?= sanitize($p['full_name']) ?></a>
                        <?php if ($p['father_husband_name']): ?>
                            <div class="small text-muted">S/D/W of <?= sanitize($p['father_husband_name']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="small"><?= sanitize($p['gender']) ?><?= $p['age'] !== null ? ' / ' . (int)$p['age'] . 'y' : '' ?></td>
                    <td class="small">
                        <?= sanitize($p['phone'] ?: '-') ?>
                        <?php if ($p['whatsapp']): ?>
                            <a class="ms-1 text-success" target="_blank" rel="noopener"
                               href="<?= sanitize(whatsappLink($p['whatsapp'], 'Hello ' . $p['full_name'])) ?>"
                               title="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                        <?php endif; ?>
                    </td>
                    <td class="small"><?= sanitize($p['blood_group'] ?: '-') ?></td>
                    <td class="small"><?= formatDate($p['registration_date']) ?></td>
                    <td class="text-end text-nowrap">
                        <a href="view.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                        <a href="edit.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                        <a href="<?= BASE_URL ?>/modules/orders/add.php?patient_id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-success" title="New test order"><i class="bi bi-clipboard2-plus"></i></a>
                        <a href="delete.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></a>
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
