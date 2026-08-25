<?php
/** Add or edit an inventory item. */
require_once __DIR__ . '/../../config/session.php';

$id = (int)get('id', post('id'));
$item = $id ? fetchOne('SELECT * FROM inventory_items WHERE id = ?', [$id]) : null;
if ($id && !$item) {
    flash('danger', 'Item not found.');
    redirect(BASE_URL . '/modules/inventory/index.php');
}

$data = $item ?: [
    'item_code' => '', 'name' => '', 'category' => '', 'supplier_id' => 0, 'purchase_price' => '0',
    'selling_price' => '0', 'quantity' => '0', 'minimum_stock' => '0', 'expiry_date' => '',
    'batch_number' => '', 'notes' => '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(BASE_URL . '/modules/inventory/add.php' . ($id ? '?id=' . $id : ''));
    foreach (['item_code', 'name', 'category', 'batch_number', 'notes', 'expiry_date'] as $field) {
        $data[$field] = post($field);
    }
    $data['supplier_id']    = (int)post('supplier_id');
    $data['purchase_price'] = (float)post('purchase_price', 0);
    $data['selling_price']  = (float)post('selling_price', 0);
    $data['quantity']       = (int)post('quantity', 0);
    $data['minimum_stock']  = (int)post('minimum_stock', 0);

    if ($data['name'] === '') {
        $errors[] = 'Item name is required.';
    }
    if ($data['item_code'] !== '') {
        $dupe = $item
            ? fetchOne('SELECT id FROM inventory_items WHERE item_code = ? AND id <> ?', [$data['item_code'], $id])
            : fetchOne('SELECT id FROM inventory_items WHERE item_code = ?', [$data['item_code']]);
        if ($dupe) {
            $errors[] = 'This item code is already used.';
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            if ($item) {
                $pdo->prepare('UPDATE inventory_items SET item_code=?, name=?, category=?, supplier_id=?, purchase_price=?,
                        selling_price=?, quantity=?, minimum_stock=?, expiry_date=?, batch_number=?, notes=? WHERE id=?')
                    ->execute([$data['item_code'] ?: $item['item_code'], $data['name'], $data['category'] ?: null,
                        $data['supplier_id'] ?: null, $data['purchase_price'], $data['selling_price'], $data['quantity'],
                        $data['minimum_stock'], $data['expiry_date'] ?: null, $data['batch_number'] ?: null,
                        $data['notes'] ?: null, $id]);
                $difference = (int)$data['quantity'] - (int)$item['quantity'];
                if ($difference !== 0) {
                    $pdo->prepare('INSERT INTO inventory_transactions (item_id, type, quantity, notes, transaction_date) VALUES (?,?,?,?,NOW())')
                        ->execute([$id, 'Adjustment', $difference, 'Quantity corrected while editing the item']);
                }
                logActivity('Inventory item updated', $data['name'], 'inventory_items', $id);
                flash('success', 'Item updated.');
            } else {
                $code = $data['item_code'] !== '' ? $data['item_code'] : generateItemCode();
                $pdo->prepare('INSERT INTO inventory_items (item_code, name, category, supplier_id, purchase_price, selling_price,
                        quantity, minimum_stock, expiry_date, batch_number, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$code, $data['name'], $data['category'] ?: null, $data['supplier_id'] ?: null,
                        $data['purchase_price'], $data['selling_price'], $data['quantity'], $data['minimum_stock'],
                        $data['expiry_date'] ?: null, $data['batch_number'] ?: null, $data['notes'] ?: null]);
                $newId = (int)$pdo->lastInsertId();
                if ((int)$data['quantity'] > 0) {
                    $pdo->prepare('INSERT INTO inventory_transactions (item_id, type, quantity, notes, transaction_date) VALUES (?,?,?,?,NOW())')
                        ->execute([$newId, 'Stock In', (int)$data['quantity'], 'Opening stock']);
                }
                logActivity('Inventory item added', $code . ' - ' . $data['name'], 'inventory_items', $newId);
                flash('success', 'Item ' . $code . ' added.');
            }
            $pdo->commit();
            redirect(BASE_URL . '/modules/inventory/index.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = friendlyError($e, 'Could not save the item. Please try again.');
        }
    }
}

$suppliers = fetchAll('SELECT id, name FROM suppliers ORDER BY name');
$history = $item ? fetchAll('SELECT * FROM inventory_transactions WHERE item_id = ? ORDER BY transaction_date DESC, id DESC LIMIT 10', [$id]) : [];

$pageTitle = $item ? 'Edit Item' : 'Add Inventory Item';
$activeMenu = 'inventory';
$pageActions = '<a href="' . BASE_URL . '/modules/inventory/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Inventory</a>';
require_once INC_PATH . '/header.php';
?>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-octagon me-2"></i><?= sanitize($error) ?></div>
<?php endforeach; ?>

<div class="row g-3">
    <div class="col-lg-8">
        <form method="post" class="card">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= (int)$id ?>">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-box-seam me-2"></i>Item details</div>
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label">Item code</label>
                    <input type="text" name="item_code" class="form-control" value="<?= sanitize($data['item_code']) ?>" placeholder="auto">
                </div>
                <div class="col-md-8">
                    <label class="form-label required">Item name</label>
                    <input type="text" name="name" class="form-control" value="<?= sanitize($data['name']) ?>" required autofocus>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Category</label>
                    <input type="text" name="category" class="form-control" list="categoryList" value="<?= sanitize($data['category']) ?>">
                    <datalist id="categoryList">
                        <?php foreach (['Reagent', 'Consumable', 'Medicine', 'Equipment', 'Stationery', 'Other'] as $c): ?>
                            <option value="<?= $c ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Supplier</label>
                    <select name="supplier_id" class="form-select">
                        <option value="">-- none --</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= (int)$s['id'] ?>" <?= (int)$data['supplier_id'] === (int)$s['id'] ? 'selected' : '' ?>><?= sanitize($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Purchase price</label>
                    <input type="number" step="0.01" min="0" name="purchase_price" class="form-control" value="<?= sanitize($data['purchase_price']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Selling price</label>
                    <input type="number" step="0.01" min="0" name="selling_price" class="form-control" value="<?= sanitize($data['selling_price']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Quantity</label>
                    <input type="number" name="quantity" class="form-control" value="<?= sanitize($data['quantity']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Minimum stock</label>
                    <input type="number" min="0" name="minimum_stock" class="form-control" value="<?= sanitize($data['minimum_stock']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Batch number</label>
                    <input type="text" name="batch_number" class="form-control" value="<?= sanitize($data['batch_number']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Expiry date</label>
                    <input type="date" name="expiry_date" class="form-control" value="<?= sanitize($data['expiry_date']) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"><?= sanitize($data['notes']) ?></textarea>
                </div>
            </div>
            <div class="card-footer bg-white d-flex gap-2">
                <button class="btn btn-primary"><i class="bi bi-save me-1"></i><?= $item ? 'Update item' : 'Save item' ?></button>
                <a href="<?= BASE_URL ?>/modules/inventory/index.php" class="btn btn-outline-secondary">Cancel</a>
                <?php if ($item): ?>
                    <a href="transactions.php?item_id=<?= (int)$id ?>" class="btn btn-outline-success ms-auto"><i class="bi bi-arrow-left-right me-1"></i>Stock movement</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if ($item): ?>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history me-2"></i>Recent movements</div>
                <ul class="list-group list-group-flush">
                    <?php if (!$history): ?>
                        <li class="list-group-item small text-muted">No stock movements recorded.</li>
                    <?php endif; ?>
                    <?php foreach ($history as $h): ?>
                        <li class="list-group-item d-flex justify-content-between small">
                            <span><?= sanitize($h['type']) ?><br><span class="text-muted"><?= formatDateTime($h['transaction_date']) ?></span></span>
                            <strong class="<?= $h['quantity'] < 0 ? 'text-danger' : 'text-success' ?>"><?= (int)$h['quantity'] > 0 ? '+' : '' ?><?= (int)$h['quantity'] ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once INC_PATH . '/footer.php'; ?>
