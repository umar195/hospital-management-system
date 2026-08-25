<?php
/** Laboratory test catalogue. */
require_once __DIR__ . '/../../config/session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(BASE_URL . '/modules/tests/index.php');
    $id = (int)post('id');
    if (post('action') === 'delete' && $id) {
        $used = (int)fetchValue('SELECT COUNT(*) FROM test_order_items WHERE test_id = ?', [$id]);
        if ($used > 0) {
            $pdo->prepare('UPDATE tests SET is_active = 0 WHERE id = ?')->execute([$id]);
            flash('warning', 'This test is used in ' . $used . ' order(s) so it was deactivated instead of deleted.');
        } else {
            $pdo->prepare('DELETE FROM tests WHERE id = ?')->execute([$id]);
            flash('success', 'Test deleted.');
        }
        logActivity('Test removed', 'Test #' . $id, 'tests', $id);
    } elseif (post('action') === 'toggle' && $id) {
        $pdo->prepare('UPDATE tests SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
        flash('success', 'Test status updated.');
    }
    redirect(BASE_URL . '/modules/tests/index.php');
}

$search     = get('q');
$categoryId = (int)get('category_id');
$page       = currentPage();
$offset     = ($page - 1) * PER_PAGE;

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(t.name LIKE ? OR t.test_code LIKE ? OR t.sample_type LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
if ($categoryId > 0) {
    $where[] = 't.category_id = ?';
    $params[] = $categoryId;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int)fetchValue("SELECT COUNT(*) FROM tests t $whereSql", $params);
$tests = fetchAll("SELECT t.*, c.name AS category_name,
                    (SELECT COUNT(*) FROM test_parameters p WHERE p.test_id = t.id) AS param_count,
                    (SELECT COUNT(*) FROM test_order_items i WHERE i.test_id = t.id) AS order_count
                   FROM tests t LEFT JOIN test_categories c ON c.id = t.category_id
                   $whereSql ORDER BY c.sort_order, t.name LIMIT " . (int)PER_PAGE . ' OFFSET ' . (int)$offset, $params);

$categories = fetchAll('SELECT id, name FROM test_categories ORDER BY sort_order, name');

$pageTitle = 'Tests';
$pageSubtitle = number_format($total) . ' test(s) in the catalogue';
$activeMenu = 'tests';
$pageActions = '<a href="' . BASE_URL . '/modules/tests/add.php" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Add Test</a> '
    . '<a href="' . BASE_URL . '/modules/tests/categories.php" class="btn btn-outline-primary"><i class="bi bi-diagram-3 me-1"></i>Categories</a>';
require_once INC_PATH . '/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-5">
                <label class="form-label">Search</label>
                <input type="search" name="q" class="form-control" value="<?= sanitize($search) ?>" placeholder="Test name, code or sample type">
            </div>
            <div class="col-md-4">
                <label class="form-label">Category</label>
                <select name="category_id" class="form-select">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $categoryId === (int)$c['id'] ? 'selected' : '' ?>><?= sanitize($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Filter</button>
                <a href="<?= BASE_URL ?>/modules/tests/index.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Code</th><th>Test</th><th>Category</th><th>Sample</th><th>Parameters</th><th>Price</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php if (!$tests): ?>
                <tr><td colspan="8"><div class="empty-state"><i class="bi bi-droplet-half"></i>No tests found.
                    <div class="mt-2"><a href="add.php" class="btn btn-sm btn-primary">Add a test</a></div></div></td></tr>
            <?php endif; ?>
            <?php foreach ($tests as $t): ?>
                <tr>
                    <td><span class="badge bg-light text-dark"><?= sanitize($t['test_code']) ?></span></td>
                    <td>
                        <div class="fw-semibold"><?= sanitize($t['name']) ?></div>
                        <?php if ($t['normal_range']): ?><small class="text-muted">Ref: <?= sanitize($t['normal_range']) ?> <?= sanitize($t['unit']) ?></small><?php endif; ?>
                    </td>
                    <td class="small"><?= sanitize($t['category_name'] ?: '-') ?></td>
                    <td class="small"><?= sanitize($t['sample_type'] ?: '-') ?></td>
                    <td class="small">
                        <a href="parameters.php?test_id=<?= (int)$t['id'] ?>" class="badge bg-info-subtle text-info-emphasis text-decoration-none">
                            <?= (int)$t['param_count'] ?> parameter(s)
                        </a>
                    </td>
                    <td class="small fw-semibold"><?= formatCurrency($t['price']) ?></td>
                    <td>
                        <form method="post" class="d-inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                            <button class="btn btn-sm p-0 border-0 bg-transparent" title="Toggle status">
                                <?= $t['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?>
                            </button>
                        </form>
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="parameters.php?test_id=<?= (int)$t['id'] ?>" class="btn btn-sm btn-outline-info" title="Parameters"><i class="bi bi-list-ol"></i></a>
                        <a href="edit.php?id=<?= (int)$t['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                        <form method="post" class="d-inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" data-confirm="Delete this test?" title="Delete"><i class="bi bi-trash"></i></button>
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
