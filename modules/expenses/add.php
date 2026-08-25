<?php
/** Add or edit an expense. */
require_once __DIR__ . '/../../config/session.php';

$categories = ['Rent', 'Electricity', 'Salaries', 'Laboratory Supplies', 'Medicines', 'Maintenance', 'Equipment', 'Other'];
$methods = ['Cash', 'Card', 'Bank Transfer', 'Other'];

$id = (int)get('id', post('id'));
$expense = $id ? fetchOne('SELECT * FROM expenses WHERE id = ?', [$id]) : null;
if ($id && !$expense) {
    flash('danger', 'Expense not found.');
    redirect(BASE_URL . '/modules/expenses/index.php');
}

$data = $expense ?: [
    'expense_id' => '', 'expense_date' => date('Y-m-d'), 'category' => 'Laboratory Supplies',
    'description' => '', 'amount' => '', 'payment_method' => 'Cash', 'notes' => '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(BASE_URL . '/modules/expenses/add.php' . ($id ? '?id=' . $id : ''));
    $data['expense_date']   = post('expense_date', date('Y-m-d'));
    $data['category']       = post('category');
    $data['description']    = post('description');
    $data['amount']         = post('amount');
    $data['payment_method'] = post('payment_method', 'Cash');
    $data['notes']          = post('notes');

    if (!in_array($data['category'], $categories, true)) {
        $errors[] = 'Please select a valid category.';
    }
    if (!in_array($data['payment_method'], $methods, true)) {
        $data['payment_method'] = 'Cash';
    }
    if (!is_numeric($data['amount']) || (float)$data['amount'] <= 0) {
        $errors[] = 'Enter an amount greater than zero.';
    }
    if (!strtotime((string)$data['expense_date'])) {
        $errors[] = 'Enter a valid date.';
    }

    if (!$errors) {
        if ($expense) {
            $pdo->prepare('UPDATE expenses SET expense_date=?, category=?, description=?, amount=?, payment_method=?, notes=? WHERE id=?')
                ->execute([$data['expense_date'], $data['category'], $data['description'] ?: null, (float)$data['amount'],
                    $data['payment_method'], $data['notes'] ?: null, $id]);
            logActivity('Expense updated', $expense['expense_id'], 'expenses', $id);
            flash('success', 'Expense updated.');
        } else {
            $code = generateExpenseId();
            $pdo->prepare('INSERT INTO expenses (expense_id, expense_date, category, description, amount, payment_method, notes)
                           VALUES (?,?,?,?,?,?,?)')
                ->execute([$code, $data['expense_date'], $data['category'], $data['description'] ?: null,
                    (float)$data['amount'], $data['payment_method'], $data['notes'] ?: null]);
            logActivity('Expense recorded', $code . ' · ' . formatCurrency($data['amount']), 'expenses', (int)$pdo->lastInsertId());
            flash('success', 'Expense ' . $code . ' recorded.');
        }
        redirect(BASE_URL . '/modules/expenses/index.php');
    }
}

$recent = fetchAll('SELECT * FROM expenses ORDER BY id DESC LIMIT 8');

$pageTitle = $expense ? 'Edit Expense' : 'Add Expense';
$activeMenu = 'expenses';
$pageActions = '<a href="' . BASE_URL . '/modules/expenses/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>All expenses</a>';
require_once INC_PATH . '/header.php';
?>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-octagon me-2"></i><?= sanitize($error) ?></div>
<?php endforeach; ?>

<div class="row g-3">
    <div class="col-lg-7">
        <form method="post" class="card">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= (int)$id ?>">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-wallet2 me-2"></i>Expense details</div>
            <div class="card-body row g-3">
                <?php if ($expense): ?>
                    <div class="col-md-6">
                        <label class="form-label">Expense ID</label>
                        <input type="text" class="form-control" value="<?= sanitize($expense['expense_id']) ?>" readonly>
                    </div>
                <?php endif; ?>
                <div class="col-md-6">
                    <label class="form-label required">Date</label>
                    <input type="date" name="expense_date" class="form-control" value="<?= sanitize($data['expense_date']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Category</label>
                    <select name="category" class="form-select" required>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c ?>" <?= $data['category'] === $c ? 'selected' : '' ?>><?= $c ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Amount</label>
                    <input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="<?= sanitize($data['amount']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Payment method</label>
                    <select name="payment_method" class="form-select">
                        <?php foreach ($methods as $m): ?>
                            <option value="<?= $m ?>" <?= $data['payment_method'] === $m ? 'selected' : '' ?>><?= $m ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-control" value="<?= sanitize($data['description']) ?>" placeholder="e.g. Reagent purchase from ABC Traders">
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"><?= sanitize($data['notes']) ?></textarea>
                </div>
            </div>
            <div class="card-footer bg-white d-flex gap-2">
                <button class="btn btn-primary"><i class="bi bi-save me-1"></i><?= $expense ? 'Update expense' : 'Save expense' ?></button>
                <a href="<?= BASE_URL ?>/modules/expenses/index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history me-2"></i>Recent expenses</div>
            <ul class="list-group list-group-flush">
                <?php if (!$recent): ?>
                    <li class="list-group-item small text-muted">Nothing recorded yet.</li>
                <?php endif; ?>
                <?php foreach ($recent as $r): ?>
                    <li class="list-group-item d-flex justify-content-between small">
                        <span>
                            <span class="fw-semibold"><?= sanitize($r['category']) ?></span><br>
                            <span class="text-muted"><?= formatDate($r['expense_date']) ?> · <?= sanitize($r['description'] ?: $r['expense_id']) ?></span>
                        </span>
                        <strong><?= formatCurrency($r['amount']) ?></strong>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<?php require_once INC_PATH . '/footer.php'; ?>
