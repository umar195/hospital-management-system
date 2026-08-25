<?php
/** Suppliers list with inline create / edit. */
require_once __DIR__ . '/../../config/session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(BASE_URL . '/modules/suppliers/index.php');
    $id = (int)post('id');
    if (post('action') === 'delete' && $id) {
        $pdo->prepare('DELETE FROM suppliers WHERE id = ?')->execute([$id]);
        logActivity('Supplier deleted', 'Supplier #' . $id, 'suppliers', $id);
        flash('success', 'Supplier deleted. Linked inventory items were kept.');
    }
    redirect(BASE_URL . '/modules/suppliers/index.php');
}

$search = get('q');
$page   = currentPage();
$offset = ($page - 1) * PER_PAGE;

$where = '';
$params = [];
if ($search !== '') {
    $where = 'WHERE (s.name LIKE ? OR s.contact_person LIKE ? OR s.phone LIKE ? OR s.email LIKE ?)';
    $like = '%' . $search . '%';
    $params = [$like, $like, $like, $like];
}

$total = (int)fetchValue("SELECT COUNT(*) FROM suppliers s $where", $params);
$suppliers = fetchAll("SELECT s.*, (SELECT COUNT(*) FROM inventory_items i WHERE i.supplier_id = s.id) AS item_count
    FROM suppliers s $where ORDER BY s.name LIMIT " . (int)PER_PAGE . ' OFFSET ' . (int)$offset, $params);

$pageTitle = 'Suppliers';
$pageSubtitle = number_format($total) . ' supplier(s)';
$activeMenu = 'suppliers';
$pageActions = '<a href="' . BASE_URL . '/modules/suppliers/add.php" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Add Supplier</a>';
require_once INC_PATH . '/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-6">
                <label class="form-label">Search</label>
                <input type="search" name="q" class="form-control" value="<?= sanitize($search) ?>" placeholder="Name, contact person, phone or email">
            </div>
            <div class="col-md-6 d-flex gap-2">
                <button class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Search</button>
                <a href="<?= BASE_URL ?>/modules/suppliers/index.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Supplier</th><th>Contact person</th><th>Phone</th><th>Email</th><th>Items</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php if (!$suppliers): ?>
                <tr><td colspan="6"><div class="empty-state"><i class="bi bi-truck"></i>No suppliers yet.
                    <div class="mt-2"><a href="add.php" class="btn btn-sm btn-primary">Add a supplier</a></div></div></td></tr>
            <?php endif; ?>
            <?php foreach ($suppliers as $s): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= sanitize($s['name']) ?></div>
                        <?php if ($s['address']): ?><small class="text-muted"><?= sanitize($s['address']) ?></small><?php endif; ?>
                    </td>
                    <td class="small"><?= sanitize($s['contact_person'] ?: '-') ?></td>
                    <td class="small"><?= sanitize($s['phone'] ?: '-') ?></td>
                    <td class="small"><?= sanitize($s['email'] ?: '-') ?></td>
                    <td class="small"><a href="<?= BASE_URL ?>/modules/inventory/index.php?supplier_id=<?= (int)$s['id'] ?>" class="badge bg-light text-dark"><?= (int)$s['item_count'] ?> item(s)</a></td>
                    <td class="text-end text-nowrap">
                        <?php if ($s['phone']): ?>
                            <a href="<?= sanitize(whatsappLink($s['phone'], 'Hello ' . $s['name'] . ', this is ' . getSetting('hospital_name', APP_NAME) . '.')) ?>"
                               target="_blank" class="btn btn-sm btn-outline-success" title="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                        <?php endif; ?>
                        <a href="add.php?id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <form method="post" class="d-inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" data-confirm="Delete supplier <?= sanitize($s['name']) ?>?"><i class="bi bi-trash"></i></button>
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
