<?php
/** Add or edit a supplier. */
require_once __DIR__ . '/../../config/session.php';

$id = (int)get('id', post('id'));
$supplier = $id ? fetchOne('SELECT * FROM suppliers WHERE id = ?', [$id]) : null;
if ($id && !$supplier) {
    flash('danger', 'Supplier not found.');
    redirect(BASE_URL . '/modules/suppliers/index.php');
}

$data = $supplier ?: ['name' => '', 'contact_person' => '', 'phone' => '', 'email' => '', 'address' => '', 'notes' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(BASE_URL . '/modules/suppliers/add.php' . ($id ? '?id=' . $id : ''));
    foreach (['name', 'contact_person', 'phone', 'email', 'address', 'notes'] as $field) {
        $data[$field] = post($field);
    }
    if ($data['name'] === '') {
        $errors[] = 'Supplier name is required.';
    }
    if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    if (!$errors) {
        if ($supplier) {
            $pdo->prepare('UPDATE suppliers SET name=?, contact_person=?, phone=?, email=?, address=?, notes=? WHERE id=?')
                ->execute([$data['name'], $data['contact_person'] ?: null, $data['phone'] ?: null, $data['email'] ?: null,
                    $data['address'] ?: null, $data['notes'] ?: null, $id]);
            logActivity('Supplier updated', $data['name'], 'suppliers', $id);
            flash('success', 'Supplier updated.');
        } else {
            $pdo->prepare('INSERT INTO suppliers (name, contact_person, phone, email, address, notes) VALUES (?,?,?,?,?,?)')
                ->execute([$data['name'], $data['contact_person'] ?: null, $data['phone'] ?: null, $data['email'] ?: null,
                    $data['address'] ?: null, $data['notes'] ?: null]);
            logActivity('Supplier added', $data['name'], 'suppliers', (int)$pdo->lastInsertId());
            flash('success', 'Supplier added.');
        }
        redirect(BASE_URL . '/modules/suppliers/index.php');
    }
}

$items = $supplier ? fetchAll('SELECT * FROM inventory_items WHERE supplier_id = ? ORDER BY name LIMIT 15', [$id]) : [];

$pageTitle = $supplier ? 'Edit Supplier' : 'Add Supplier';
$activeMenu = 'suppliers';
$pageActions = '<a href="' . BASE_URL . '/modules/suppliers/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>All suppliers</a>';
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
            <div class="card-header bg-white fw-semibold"><i class="bi bi-truck me-2"></i>Supplier details</div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label required">Name</label>
                    <input type="text" name="name" class="form-control" value="<?= sanitize($data['name']) ?>" required autofocus>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Contact person</label>
                    <input type="text" name="contact_person" class="form-control" value="<?= sanitize($data['contact_person']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= sanitize($data['phone']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= sanitize($data['email']) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2"><?= sanitize($data['address']) ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"><?= sanitize($data['notes']) ?></textarea>
                </div>
            </div>
            <div class="card-footer bg-white d-flex gap-2">
                <button class="btn btn-primary"><i class="bi bi-save me-1"></i><?= $supplier ? 'Update supplier' : 'Save supplier' ?></button>
                <a href="<?= BASE_URL ?>/modules/suppliers/index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <?php if ($supplier): ?>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-box-seam me-2"></i>Supplied items</div>
                <ul class="list-group list-group-flush">
                    <?php if (!$items): ?>
                        <li class="list-group-item small text-muted">No inventory items linked to this supplier.</li>
                    <?php endif; ?>
                    <?php foreach ($items as $i): ?>
                        <li class="list-group-item d-flex justify-content-between small">
                            <span><?= sanitize($i['name']) ?><br><span class="text-muted"><?= sanitize($i['item_code']) ?></span></span>
                            <span class="badge bg-light text-dark align-self-center"><?= (int)$i['quantity'] ?> in stock</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once INC_PATH . '/footer.php'; ?>
