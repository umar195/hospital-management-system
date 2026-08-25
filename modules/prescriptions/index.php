<?php
/** Prescriptions list, and detail view when ?id= is supplied. */
require_once __DIR__ . '/../../config/session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(BASE_URL . '/modules/prescriptions/index.php');
    $id = (int)post('id');
    if (post('action') === 'delete' && $id) {
        $pdo->prepare('DELETE FROM prescriptions WHERE id = ?')->execute([$id]);
        logActivity('Prescription deleted', 'Prescription #' . $id, 'prescriptions', $id);
        flash('success', 'Prescription deleted.');
    }
    redirect(BASE_URL . '/modules/prescriptions/index.php');
}

$detailId = (int)get('id');

if ($detailId) {
    $prescription = fetchOne('SELECT pr.*, p.full_name, p.patient_id AS patient_code, p.gender, p.age, p.date_of_birth,
            p.phone, p.whatsapp, v.visit_number
        FROM prescriptions pr JOIN patients p ON p.id = pr.patient_id
        LEFT JOIN visits v ON v.id = pr.visit_id WHERE pr.id = ?', [$detailId]);
    if (!$prescription) {
        flash('danger', 'Prescription not found.');
        redirect(BASE_URL . '/modules/prescriptions/index.php');
    }
    $medicines = fetchAll('SELECT * FROM prescription_items WHERE prescription_id = ? ORDER BY id', [$detailId]);
    $age = $prescription['age'] !== null ? (int)$prescription['age'] : ($prescription['date_of_birth'] ? calculateAge($prescription['date_of_birth']) : null);
    $phone = $prescription['whatsapp'] ?: $prescription['phone'];

    $pageTitle = 'Prescription #' . $detailId;
    $pageSubtitle = sanitize($prescription['full_name']) . ' · ' . formatDate($prescription['prescription_date']);
    $activeMenu = 'prescriptions';
    $pageActions = '<button class="btn btn-primary" data-print="1"><i class="bi bi-printer me-1"></i>Print</button> '
        . '<a href="' . BASE_URL . '/modules/prescriptions/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>';
    require_once INC_PATH . '/header.php';
    ?>
    <div class="print-sheet card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                <div class="d-flex gap-3 align-items-center">
                    <?php if ($logo = hospitalLogoUrl()): ?>
                        <img src="<?= sanitize($logo) ?>" alt="Logo" style="max-height:60px">
                    <?php endif; ?>
                    <div>
                        <h5 class="mb-1 text-primary"><?= sanitize(getSetting('hospital_name', 'City Care Hospital')) ?></h5>
                        <div class="small text-muted"><?= nl2br(sanitize(getSetting('hospital_address', ''))) ?></div>
                        <div class="small text-muted"><?= sanitize(getSetting('hospital_phone', '')) ?></div>
                    </div>
                </div>
                <div class="text-end small">
                    <div class="fw-bold">PRESCRIPTION</div>
                    <div>Date: <?= formatDate($prescription['prescription_date']) ?></div>
                    <?php if ($prescription['visit_number']): ?><div>Visit: <?= sanitize($prescription['visit_number']) ?></div><?php endif; ?>
                </div>
            </div>

            <div class="row small mb-4">
                <div class="col-md-6">
                    <div><strong>Patient:</strong> <?= sanitize($prescription['full_name']) ?> (<?= sanitize($prescription['patient_code']) ?>)</div>
                    <div><strong>Age / Gender:</strong> <?= $age !== null ? $age . ' yrs' : '-' ?> / <?= sanitize($prescription['gender']) ?></div>
                </div>
                <div class="col-md-6 text-md-end">
                    <div><strong>Phone:</strong> <?= sanitize($prescription['phone'] ?: '-') ?></div>
                </div>
            </div>

            <h5 class="text-primary">℞</h5>
            <table class="table table-sm table-bordered">
                <thead class="table-light"><tr><th style="width:28%">Medicine</th><th>Dosage</th><th>Frequency</th><th>Duration</th><th>Instructions</th></tr></thead>
                <tbody>
                <?php if (!$medicines): ?>
                    <tr><td colspan="5" class="text-muted small">No medicines listed.</td></tr>
                <?php endif; ?>
                <?php foreach ($medicines as $m): ?>
                    <tr>
                        <td class="fw-semibold"><?= sanitize($m['medicine']) ?></td>
                        <td><?= sanitize($m['dosage'] ?: '-') ?></td>
                        <td><?= sanitize($m['frequency'] ?: '-') ?></td>
                        <td><?= sanitize($m['duration'] ?: '-') ?></td>
                        <td class="small"><?= sanitize($m['instructions'] ?: '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($prescription['notes']): ?>
                <div class="mt-3 small"><strong>Advice:</strong> <?= nl2br(sanitize($prescription['notes'])) ?></div>
            <?php endif; ?>

            <div class="text-end mt-5">
                <div style="border-top:1px solid #333; display:inline-block; padding-top:.35rem; min-width:220px">
                    <small class="text-muted">Doctor's signature</small>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-3 no-print d-flex gap-2">
        <a href="<?= BASE_URL ?>/modules/patients/view.php?id=<?= (int)$prescription['patient_id'] ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-folder2-open me-1"></i>Patient file
        </a>
        <?php if ($phone): ?>
            <a href="<?= sanitize(whatsappLink($phone, 'Dear ' . $prescription['full_name'] . ', your prescription from ' . getSetting('hospital_name', APP_NAME) . ' is ready.')) ?>"
               target="_blank" class="btn btn-outline-success btn-sm"><i class="bi bi-whatsapp me-1"></i>Share on WhatsApp</a>
        <?php endif; ?>
        <form method="post" class="ms-auto">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$detailId ?>">
            <button class="btn btn-outline-danger btn-sm" data-confirm="Delete this prescription?"><i class="bi bi-trash me-1"></i>Delete</button>
        </form>
    </div>
    <?php
    require_once INC_PATH . '/footer.php';
    return;
}

$search = get('q');
$from   = get('from');
$to     = get('to');
$page   = currentPage();
$offset = ($page - 1) * PER_PAGE;

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(p.full_name LIKE ? OR p.patient_id LIKE ? OR pr.notes LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
if ($from !== '') {
    $where[] = 'pr.prescription_date >= ?';
    $params[] = $from;
}
if ($to !== '') {
    $where[] = 'pr.prescription_date <= ?';
    $params[] = $to;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int)fetchValue("SELECT COUNT(*) FROM prescriptions pr JOIN patients p ON p.id = pr.patient_id $whereSql", $params);
$prescriptions = fetchAll("SELECT pr.*, p.full_name, p.patient_id AS patient_code, p.phone,
        (SELECT COUNT(*) FROM prescription_items pi WHERE pi.prescription_id = pr.id) AS medicine_count
    FROM prescriptions pr JOIN patients p ON p.id = pr.patient_id
    $whereSql ORDER BY pr.prescription_date DESC, pr.id DESC LIMIT " . (int)PER_PAGE . ' OFFSET ' . (int)$offset, $params);

$pageTitle = 'Prescriptions';
$pageSubtitle = number_format($total) . ' prescription(s)';
$activeMenu = 'prescriptions';
$pageActions = '<a href="' . BASE_URL . '/modules/prescriptions/add.php" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>New Prescription</a>';
require_once INC_PATH . '/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-5">
                <label class="form-label">Search</label>
                <input type="search" name="q" class="form-control" value="<?= sanitize($search) ?>" placeholder="Patient name or ID">
            </div>
            <div class="col-md-2">
                <label class="form-label">From</label>
                <input type="date" name="from" class="form-control" value="<?= sanitize($from) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">To</label>
                <input type="date" name="to" class="form-control" value="<?= sanitize($to) ?>">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Filter</button>
                <a href="<?= BASE_URL ?>/modules/prescriptions/index.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>#</th><th>Patient</th><th>Date</th><th>Medicines</th><th>Advice</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php if (!$prescriptions): ?>
                <tr><td colspan="6"><div class="empty-state"><i class="bi bi-prescription2"></i>No prescriptions yet.
                    <div class="mt-2"><a href="add.php" class="btn btn-sm btn-primary">Write a prescription</a></div></div></td></tr>
            <?php endif; ?>
            <?php foreach ($prescriptions as $pr): ?>
                <tr>
                    <td class="small">#<?= (int)$pr['id'] ?></td>
                    <td>
                        <a href="?id=<?= (int)$pr['id'] ?>" class="fw-semibold text-decoration-none"><?= sanitize($pr['full_name']) ?></a>
                        <div><small class="text-muted"><?= sanitize($pr['patient_code']) ?></small></div>
                    </td>
                    <td class="small"><?= formatDate($pr['prescription_date']) ?></td>
                    <td class="small"><span class="badge bg-light text-dark"><?= (int)$pr['medicine_count'] ?> medicine(s)</span></td>
                    <td class="small text-muted"><?= sanitize(mb_substr((string)$pr['notes'], 0, 60) ?: '-') ?></td>
                    <td class="text-end text-nowrap">
                        <a href="?id=<?= (int)$pr['id'] ?>" class="btn btn-sm btn-outline-secondary" title="View / print"><i class="bi bi-eye"></i></a>
                        <a href="add.php?patient_id=<?= (int)$pr['patient_id'] ?>" class="btn btn-sm btn-outline-primary" title="New for this patient"><i class="bi bi-plus"></i></a>
                        <form method="post" class="d-inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$pr['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" data-confirm="Delete this prescription?"><i class="bi bi-trash"></i></button>
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
