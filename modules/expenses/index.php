<?php
/** Expenses list with filters and totals. */
require_once __DIR__ . '/../../config/session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(BASE_URL . '/modules/expenses/index.php');
    $id = (int)post('id');
    if (post('action') === 'delete' && $id) {
        $exp = fetchOne('SELECT expense_id FROM expenses WHERE id = ?', [$id]);
        $pdo->prepare('DELETE FROM expenses WHERE id = ?')->execute([$id]);
        logActivity('Expense deleted', $exp['expense_id'] ?? ('#' . $id), 'expenses', $id);
        flash('success', 'Expense deleted.');
    }
    redirect(BASE_URL . '/modules/expenses/index.php');
}

$search   = get('q');
$category = get('category');
$from     = get('from', date('Y-m-01'));
$to       = get('to', date('Y-m-d'));
$page     = currentPage();
$offset   = ($page - 1) * PER_PAGE;

$categories = ['Rent', 'Electricity', 'Salaries', 'Laboratory Supplies', 'Medicines', 'Maintenance', 'Equipment', 'Other'];

$where = ['expense_date BETWEEN ? AND ?'];
$params = [$from, $to];
if ($search !== '') {
    $where[] = '(description LIKE ? OR expense_id LIKE ? OR notes LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
if (in_array($category, $categories, true)) {
    $where[] = 'category = ?';
    $params[] = $category;
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$total    = (int)fetchValue("SELECT COUNT(*) FROM expenses $whereSql", $params);
$sum      = (float)fetchValue("SELECT COALESCE(SUM(amount),0) FROM expenses $whereSql", $params);
$expenses = fetchAll("SELECT * FROM expenses $whereSql ORDER BY expense_date DESC, id DESC LIMIT " . (int)PER_PAGE . ' OFFSET ' . (int)$offset, $params);
$byCategory = fetchAll("SELECT category, COALESCE(SUM(amount),0) AS total FROM expenses $whereSql GROUP BY category ORDER BY total DESC", $params);

$pageTitle = 'Expenses';
$pageSubtitle = number_format($total) . ' expense(s) · ' . formatCurrency($sum) . ' in the selected period';
$activeMenu = 'expenses';
$pageActions = '<a href="' . BASE_URL . '/modules/expenses/add.php" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Add Expense</a>';
require_once INC_PATH . '/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="search" name="q" class="form-control" value="<?= sanitize($search) ?>" placeholder="Description or ID">
            </div>
            <div class="col-md-3">
                <label class="form-label">Category</label>
                <select name="category" class="form-select">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c ?>" <?= $category === $c ? 'selected' : '' ?>><?= $c ?></option>
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
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Filter</button>
                <a href="<?= BASE_URL ?>/modules/expenses/index.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-9">
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Expense ID</th><th>Date</th><th>Category</th><th>Description</th><th>Method</th><th>Amount</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    <?php if (!$expenses): ?>
                        <tr><td colspan="7"><div class="empty-state"><i class="bi bi-wallet2"></i>No expenses recorded in this period.
                            <div class="mt-2"><a href="add.php" class="btn btn-sm btn-primary">Add an expense</a></div></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($expenses as $e): ?>
                        <tr>
                            <td><span class="badge bg-light text-dark"><?= sanitize($e['expense_id']) ?></span></td>
                            <td class="small"><?= formatDate($e['expense_date']) ?></td>
                            <td class="small"><?= sanitize($e['category']) ?></td>
                            <td class="small"><?= sanitize($e['description'] ?: '-') ?></td>
                            <td class="small"><?= sanitize($e['payment_method']) ?></td>
                            <td class="fw-semibold"><?= formatCurrency($e['amount']) ?></td>
                            <td class="text-end text-nowrap">
                                <a href="add.php?id=<?= (int)$e['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                <form method="post" class="d-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" data-confirm="Delete this expense?"><i class="bi bi-trash"></i></button>
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

    <div class="col-lg-3">
        <div class="card">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-pie-chart me-2"></i>By category</div>
            <ul class="list-group list-group-flush">
                <?php if (!$byCategory): ?>
                    <li class="list-group-item small text-muted">Nothing to summarise.</li>
                <?php endif; ?>
                <?php foreach ($byCategory as $c): ?>
                    <li class="list-group-item d-flex justify-content-between small">
                        <span><?= sanitize($c['category']) ?></span>
                        <strong><?= formatCurrency($c['total']) ?></strong>
                    </li>
                <?php endforeach; ?>
                <li class="list-group-item d-flex justify-content-between bg-light">
                    <strong>Total</strong><strong class="text-danger"><?= formatCurrency($sum) ?></strong>
                </li>
            </ul>
        </div>
    </div>
</div>

<?php require_once INC_PATH . '/footer.php'; ?>
