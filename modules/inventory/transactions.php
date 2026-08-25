<?php
/** Stock in / stock out / adjustment movements. */
require_once __DIR__ . '/../../config/session.php';

$itemId = (int)get('item_id', post('item_id'));
$item = $itemId ? fetchOne('SELECT * FROM inventory_items WHERE id = ?', [$itemId]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(BASE_URL . '/modules/inventory/transactions.php' . ($itemId ? '?item_id=' . $itemId : ''));

    if (post('action') === 'delete') {
        $txId = (int)post('id');
        $tx = fetchOne('SELECT * FROM inventory_transactions WHERE id = ?', [$txId]);
        if ($tx) {
            try {
                $pdo->beginTransaction();
                $pdo->prepare('UPDATE inventory_items SET quantity = GREATEST(quantity - ?, 0) WHERE id = ?')
                    ->execute([(int)$tx['quantity'], $tx['item_id']]);
                $pdo->prepare('DELETE FROM inventory_transactions WHERE id = ?')->execute([$txId]);
                $pdo->commit();
                logActivity('Stock movement reversed', 'Transaction #' . $txId, 'inventory_transactions', $txId);
                flash('success', 'Stock movement reversed.');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                flash('danger', 'Could not reverse the movement: ' . $e->getMessage());
            }
        }
        redirect(BASE_URL . '/modules/inventory/transactions.php' . ($itemId ? '?item_id=' . $itemId : ''));
    }

    $type = post('type', 'Stock In');
    $qty = (int)post('quantity', 0);
    $date = post('transaction_date') ?: date('Y-m-d H:i:s');
    if (strlen($date) === 16) {
        $date = str_replace('T', ' ', $date) . ':00';
    }
    $targetId = (int)post('target_item_id') ?: $itemId;
    $target = $targetId ? fetchOne('SELECT * FROM inventory_items WHERE id = ?', [$targetId]) : null;

    if (!$target) {
        flash('danger', 'Please choose an inventory item.');
    } elseif (!in_array($type, ['Stock In', 'Stock Out', 'Adjustment'], true)) {
        flash('danger', 'Invalid movement type.');
    } elseif ($qty === 0) {
        flash('danger', 'Quantity must not be zero.');
    } else {
        $delta = $type === 'Stock Out' ? -abs($qty) : ($type === 'Stock In' ? abs($qty) : $qty);
        if ($type === 'Stock Out' && abs($delta) > (int)$target['quantity']) {
            flash('danger', 'Only ' . (int)$target['quantity'] . ' unit(s) are in stock.');
        } else {
            try {
                $pdo->beginTransaction();
                $pdo->prepare('INSERT INTO inventory_transactions (item_id, type, quantity, notes, transaction_date) VALUES (?,?,?,?,?)')
                    ->execute([$targetId, $type, $delta, post('notes') ?: null, $date]);
                $pdo->prepare('UPDATE inventory_items SET quantity = GREATEST(quantity + ?, 0) WHERE id = ?')
                    ->execute([$delta, $targetId]);
                $pdo->commit();
                logActivity('Stock movement', $type . ' ' . $delta . ' × ' . $target['name'], 'inventory_items', $targetId);
                flash('success', $type . ' recorded for ' . sanitize($target['name']) . '.');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                flash('danger', 'Could not record the movement: ' . $e->getMessage());
            }
        }
    }
    redirect(BASE_URL . '/modules/inventory/transactions.php' . ($targetId ? '?item_id=' . $targetId : ''));
}

$type   = get('type');
$from   = get('from', date('Y-m-01'));
$to     = get('to', date('Y-m-d'));
$page   = currentPage();
$offset = ($page - 1) * PER_PAGE;

$where = ['DATE(t.transaction_date) BETWEEN ? AND ?'];
$params = [$from, $to];
if ($itemId) {
    $where[] = 't.item_id = ?';
    $params[] = $itemId;
}
if (in_array($type, ['Stock In', 'Stock Out', 'Adjustment'], true)) {
    $where[] = 't.type = ?';
    $params[] = $type;
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$total = (int)fetchValue("SELECT COUNT(*) FROM inventory_transactions t $whereSql", $params);
$transactions = fetchAll("SELECT t.*, i.name AS item_name, i.item_code FROM inventory_transactions t
    JOIN inventory_items i ON i.id = t.item_id
    $whereSql ORDER BY t.transaction_date DESC, t.id DESC LIMIT " . (int)PER_PAGE . ' OFFSET ' . (int)$offset, $params);

$items = fetchAll('SELECT id, item_code, name, quantity FROM inventory_items ORDER BY name');

$pageTitle = 'Stock Movements';
$pageSubtitle = $item ? sanitize($item['name']) . ' · ' . (int)$item['quantity'] . ' in stock' : number_format($total) . ' movement(s)';
$activeMenu = 'inventory';
$pageActions = '<a href="' . BASE_URL . '/modules/inventory/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Inventory</a>';
require_once INC_PATH . '/header.php';
?>

<div class="row g-3">
    <div class="col-lg-4">
        <form method="post" class="card">
            <?= csrfField() ?>
            <input type="hidden" name="item_id" value="<?= (int)$itemId ?>">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-arrow-left-right me-2"></i>Record movement</div>
            <div class="card-body row g-3">
                <div class="col-12">
                    <label class="form-label required">Item</label>
                    <select name="target_item_id" class="form-select" required>
                        <option value="">-- select item --</option>
                        <?php foreach ($items as $i): ?>
                            <option value="<?= (int)$i['id'] ?>" <?= $itemId === (int)$i['id'] ? 'selected' : '' ?>>
                                <?= sanitize($i['name']) ?> (<?= (int)$i['quantity'] ?> in stock)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select">
                        <option value="Stock In">Stock In</option>
                        <option value="Stock Out">Stock Out</option>
                        <option value="Adjustment">Adjustment</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label required">Quantity</label>
                    <input type="number" name="quantity" class="form-control" value="1" required>
                    <div class="form-text">Use a negative number for a downward adjustment.</div>
                </div>
                <div class="col-12">
                    <label class="form-label">Date &amp; time</label>
                    <input type="datetime-local" name="transaction_date" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Purchase invoice, department, reason..."></textarea>
                </div>
            </div>
            <div class="card-footer bg-white d-grid">
                <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Save movement</button>
            </div>
        </form>
    </div>

    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-body">
                <form class="row g-2 align-items-end" method="get">
                    <input type="hidden" name="item_id" value="<?= (int)$itemId ?>">
                    <div class="col-md-3">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select">
                            <option value="">All</option>
                            <?php foreach (['Stock In', 'Stock Out', 'Adjustment'] as $t): ?>
                                <option value="<?= $t ?>" <?= $type === $t ? 'selected' : '' ?>><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">From</label>
                        <input type="date" name="from" class="form-control" value="<?= sanitize($from) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">To</label>
                        <input type="date" name="to" class="form-control" value="<?= sanitize($to) ?>">
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Filter</button>
                        <a href="<?= BASE_URL ?>/modules/inventory/transactions.php" class="btn btn-outline-secondary btn-sm">All items</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Date</th><th>Item</th><th>Type</th><th>Qty</th><th>Notes</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    <?php if (!$transactions): ?>
                        <tr><td colspan="6"><div class="empty-state"><i class="bi bi-arrow-left-right"></i>No stock movements in this period.</div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($transactions as $t): ?>
                        <tr>
                            <td class="small"><?= formatDateTime($t['transaction_date']) ?></td>
                            <td>
                                <a href="add.php?id=<?= (int)$t['item_id'] ?>" class="fw-semibold text-decoration-none"><?= sanitize($t['item_name']) ?></a>
                                <div><small class="text-muted"><?= sanitize($t['item_code']) ?></small></div>
                            </td>
                            <td><span class="badge bg-<?= $t['type'] === 'Stock In' ? 'success' : ($t['type'] === 'Stock Out' ? 'danger' : 'secondary') ?>"><?= sanitize($t['type']) ?></span></td>
                            <td class="fw-semibold <?= (int)$t['quantity'] < 0 ? 'text-danger' : 'text-success' ?>"><?= (int)$t['quantity'] > 0 ? '+' : '' ?><?= (int)$t['quantity'] ?></td>
                            <td class="small text-muted"><?= sanitize($t['notes'] ?: '-') ?></td>
                            <td class="text-end">
                                <form method="post" class="d-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="item_id" value="<?= (int)$itemId ?>">
                                    <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" data-confirm="Reverse this movement and adjust the stock?"><i class="bi bi-arrow-counterclockwise"></i></button>
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
    </div>
</div>

<?php require_once INC_PATH . '/footer.php'; ?>
