<?php
/** Manage test categories (list + create + edit + delete on one screen). */
require_once __DIR__ . '/../../config/session.php';

$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(BASE_URL . '/modules/tests/categories.php');
    $action = post('action');
    $id     = (int)post('id');
    $name   = post('name');
    $desc   = post('description');
    $sort   = (int)post('sort_order');
    $active = post('is_active') ? 1 : 0;

    if ($action === 'delete' && $id) {
        $inUse = (int)fetchValue('SELECT COUNT(*) FROM tests WHERE category_id = ?', [$id]);
        $pdo->prepare('DELETE FROM test_categories WHERE id = ?')->execute([$id]);
        logActivity('Category deleted', 'Category #' . $id, 'test_categories', $id);
        flash('success', 'Category deleted.' . ($inUse ? ' ' . $inUse . ' test(s) are now uncategorised.' : ''));
    } elseif ($name === '') {
        flash('danger', 'Category name is required.');
    } elseif ($action === 'update' && $id) {
        $pdo->prepare('UPDATE test_categories SET name=?, description=?, sort_order=?, is_active=? WHERE id=?')
            ->execute([$name, $desc ?: null, $sort, $active, $id]);
        logActivity('Category updated', $name, 'test_categories', $id);
        flash('success', 'Category updated.');
    } else {
        $pdo->prepare('INSERT INTO test_categories (name, description, sort_order, is_active) VALUES (?,?,?,?)')
            ->execute([$name, $desc ?: null, $sort, $active]);
        logActivity('Category created', $name, 'test_categories', (int)$pdo->lastInsertId());
        flash('success', 'Category added.');
    }
    redirect(BASE_URL . '/modules/tests/categories.php');
}

if ($editId = (int)get('edit')) {
    $editing = fetchOne('SELECT * FROM test_categories WHERE id = ?', [$editId]);
}

$categories = fetchAll('SELECT c.*, (SELECT COUNT(*) FROM tests t WHERE t.category_id = c.id) AS test_count
                        FROM test_categories c ORDER BY c.sort_order, c.name');

$pageTitle = 'Test Categories';
$pageSubtitle = count($categories) . ' categories';
$activeMenu = 'categories';
$pageActions = '<a href="' . BASE_URL . '/modules/tests/index.php" class="btn btn-outline-primary"><i class="bi bi-droplet-half me-1"></i>Tests</a>';
require_once INC_PATH . '/header.php';
?>

<div class="row g-3">
    <div class="col-lg-4">
        <form method="post" class="card">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
            <input type="hidden" name="id" value="<?= $editing ? (int)$editing['id'] : '' ?>">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-<?= $editing ? 'pencil' : 'plus-circle' ?> me-2"></i><?= $editing ? 'Edit category' : 'New category' ?>
            </div>
            <div class="card-body row g-3">
                <div class="col-12">
                    <label class="form-label required">Name</label>
                    <input type="text" name="name" class="form-control" value="<?= sanitize($editing['name'] ?? '') ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2"><?= sanitize($editing['description'] ?? '') ?></textarea>
                </div>
                <div class="col-6">
                    <label class="form-label">Sort order</label>
                    <input type="number" name="sort_order" class="form-control" value="<?= (int)($editing['sort_order'] ?? 0) ?>">
                </div>
                <div class="col-6 d-flex align-items-end">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" <?= !$editing || $editing['is_active'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white d-flex gap-2">
                <button class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i><?= $editing ? 'Update' : 'Add category' ?></button>
                <?php if ($editing): ?>
                    <a href="categories.php" class="btn btn-outline-secondary btn-sm">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>#</th><th>Name</th><th>Description</th><th>Tests</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    <?php if (!$categories): ?>
                        <tr><td colspan="6"><div class="empty-state"><i class="bi bi-diagram-3"></i>No categories yet.</div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($categories as $c): ?>
                        <tr>
                            <td class="small text-muted"><?= (int)$c['sort_order'] ?></td>
                            <td class="fw-semibold"><?= sanitize($c['name']) ?></td>
                            <td class="small text-muted"><?= sanitize($c['description'] ?: '-') ?></td>
                            <td><a href="index.php?category_id=<?= (int)$c['id'] ?>" class="badge bg-light text-dark"><?= (int)$c['test_count'] ?> tests</a></td>
                            <td><?= $c['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                            <td class="text-end text-nowrap">
                                <a href="?edit=<?= (int)$c['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                <form method="post" class="d-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" data-confirm="Delete this category? Tests will keep existing without a category."><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once INC_PATH . '/footer.php'; ?>
