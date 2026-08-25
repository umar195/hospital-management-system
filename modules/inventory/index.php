<?php
/** Inventory list with low-stock and expiry alerts. */
require_once __DIR__ . '/../../config/session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(BASE_URL . '/modules/inventory/index.php');
    $id = (int)post('id');
    if (post('action') === 'delete' && $id) {
        $item = fetchOne('SELECT name FROM inventory_items WHERE id = ?', [$id]);
        $pdo->prepare('DELETE FROM inventory_items WHERE id = ?')->execute([$id]);
        logActivity('Inventory item deleted', $item['name'] ?? ('#' . $id), 'inventory_items', $id);
        flash('success', 'Item deleted with its stock history.');
    }
    redirect(BASE_URL . '/modules/inventory/index.php');
}

$search     = get('q');
$supplierId = (int)get('supplier_id');
$filter     = get('filter');
$page       = currentPage();
$offset     = ($page - 1) * PER_PAGE;

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(i.name LIKE ? OR i.item_code LIKE ? OR i.category LIKE ? OR i.batch_number LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($supplierId > 0) {
    $where[] = 'i.supplier_id = ?';
    $params[] = $supplierId;
}
if ($filter === 'low') {
    $where[] = 'i.quantity <= i.minimum_stock';
} elseif ($filter === 'expiring') {
    $where[] = 'i.expiry_date IS NOT NULL AND i.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)';
} elseif ($filter === 'out') {
    $where[] = 'i.quantity <= 0';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int)fetchValue("SELECT COUNT(*) FROM inventory_items i $whereSql", $params);
$items = fetchAll("SELECT i.*, s.name AS supplier_name FROM inventory_items i
    LEFT JOIN suppliers s ON s.id = i.supplier_id
    $whereSql ORDER BY i.name LIMIT " . (int)PER_PAGE . ' OFFSET ' . (int)$offset, $params);

$stats = fetchOne('SELECT COUNT(*) AS items, COALESCE(SUM(quantity),0) AS units,
        COALESCE(SUM(quantity * purchase_price),0) AS stock_value,
        SUM(CASE WHEN quantity <= minimum_stock THEN 1 ELSE 0 END) AS low_stock,
        SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY) THEN 1 ELSE 0 END) AS expiring
    FROM inventory_items');
$suppliers = fetchAll('SELECT id, name FROM suppliers ORDER BY name');

$pageTitle = 'Inventory';
$pageSubtitle = number_format($total) . ' item(s) listed';
$activeMenu = 'inventory';
$pageActions = '<a href="' . BASE_URL . '/modules/inventory/add.php" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Add Item</a> '
    . '<a href="' . BASE_URL . '/modules/inventory/transactions.php" class="btn btn-outline-primary"><i class="bi bi-arrow-left-right me-1"></i>Stock Movements</a>';
require_once INC_PATH . '/header.php';
?>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card"><div class="card-body py-3">
        <div class="text-muted small">Items</div><div class="h5 mb-0"><?= number_format($stats['items'] ?? 0) ?></div>
    </div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body py-3">
        <div class="text-muted small">Stock value</div><div class="h5 mb-0"><?= formatCurrency($stats['stock_value'] ?? 0) ?></div>
    </div></div></div>
    <div class="col-md-3"><a href="?filter=low" class="text-decoration-none"><div class="card"><div class="card-body py-3">
        <div class="text-muted small">Low stock</div><div class="h5 mb-0 text-warning"><?= number_format($stats['low_stock'] ?? 0) ?></div>
    </div></div></a></div>
    <div class="col-md-3"><a href="?filter=expiring" class="text-decoration-none"><div class="card"><div class="card-body py-3">
        <div class="text-muted small">Expiring ≤ 60 days</div><div class="h5 mb-0 text-danger"><?= number_format($stats['expiring'] ?? 0) ?></div>
    </div></div></a></div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="search" name="q" class="form-control" value="<?= sanitize($search) ?>" placeholder="Item, code, category or batch">
            </div>
            <div class="col-md-3">
                <label class="form-label">Supplier</label>
                <select name="supplier_id" class="form-select">
                    <option value="">All suppliers</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= (int)$s['id'] ?>" <?= $supplierId === (int)$s['id'] ? 'selected' : '' ?>><?= sanitize($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Filter</label>
                <select name="filter" class="form-select">
                    <option value="">All items</option>
                    <option value="low" <?= $filter === 'low' ? 'selected' : '' ?>>Low stock</option>
                    <option value="out" <?= $filter === 'out' ? 'selected' : '' ?>>Out of stock</option>
                    <option value="expiring" <?= $filter === 'expiring' ? 'selected' : '' ?>>Expiring soon</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Filter</button>
                <a href="<?= BASE_URL ?>/modules/inventory/index.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Code</th><th>Item</th><th>Category</th><th>Supplier</th><th>Qty</th><th>Min</th><th>Expiry</th><th>Value</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php if (!$items): ?>
                <tr><td colspan="9"><div class="empty-state"><i class="bi bi-box-seam"></i>No inventory items found.
                    <div class="mt-2"><a href="add.php" class="btn btn-sm btn-primary">Add an item</a></div></div></td></tr>
            <?php endif; ?>
            <?php foreach ($items as $i):
                $low = (int)$i['quantity'] <= (int)$i['minimum_stock'];
                $expiring = $i['expiry_date'] && strtotime($i['expiry_date']) <= strtotime('+60 days'); ?>
                <tr class="<?= $low ? 'table-warning' : '' ?>">
                    <td><span class="badge bg-light text-dark"><?= sanitize($i['item_code']) ?></span></td>
                    <td>
                        <div class="fw-semibold"><?= sanitize($i['name']) ?></div>
                        <?php if ($i['batch_number']): ?><small class="text-muted">Batch <?= sanitize($i['batch_number']) ?></small><?php endif; ?>
                    </td>
                    <td class="small"><?= sanitize($i['category'] ?: '-') ?></td>
                    <td class="small"><?= sanitize($i['supplier_name'] ?: '-') ?></td>
                    <td class="fw-semibold <?= $low ? 'text-danger' : '' ?>"><?= (int)$i['quantity'] ?></td>
                    <td class="small"><?= (int)$i['minimum_stock'] ?></td>
                    <td class="small <?= $expiring ? 'text-danger fw-semibold' : '' ?>"><?= $i['expiry_date'] ? formatDate($i['expiry_date']) : '-' ?></td>
                    <td class="small"><?= formatCurrency($i['quantity'] * $i['purchase_price']) ?></td>
                    <td class="text-end text-nowrap">
                        <a href="transactions.php?item_id=<?= (int)$i['id'] ?>" class="btn btn-sm btn-outline-success" title="Stock in/out"><i class="bi bi-arrow-left-right"></i></a>
                        <a href="add.php?id=<?= (int)$i['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                        <form method="post" class="d-inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$i['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" data-confirm="Delete <?= sanitize($i['name']) ?>?"><i class="bi bi-trash"></i></button>
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
