<?php
/** Edit an existing test. */
require_once __DIR__ . '/../../config/session.php';

$id = (int)get('id', post('id'));
$test = $id ? fetchOne('SELECT * FROM tests WHERE id = ?', [$id]) : null;
if (!$test) {
    flash('danger', 'Test not found.');
    redirect(BASE_URL . '/modules/tests/index.php');
}

$errors = [];
$data = $test;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(BASE_URL . '/modules/tests/edit.php?id=' . $id);
    foreach (['test_code', 'name', 'sample_type', 'unit', 'normal_range', 'male_range', 'female_range',
              'child_range', 'description', 'instructions'] as $field) {
        $data[$field] = post($field);
    }
    $data['category_id'] = (int)post('category_id');
    $data['price'] = (float)post('price', 0);
    $data['is_active'] = post('is_active') ? 1 : 0;

    if ($data['name'] === '') {
        $errors[] = 'Test name is required.';
    }
    if ($data['test_code'] === '') {
        $errors[] = 'Test code is required.';
    } elseif (fetchOne('SELECT id FROM tests WHERE test_code = ? AND id <> ?', [$data['test_code'], $id])) {
        $errors[] = 'This test code is already used by another test.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('UPDATE tests SET test_code=?, name=?, category_id=?, price=?, sample_type=?, unit=?,
                normal_range=?, male_range=?, female_range=?, child_range=?, description=?, instructions=?, is_active=?
                WHERE id=?');
        $stmt->execute([$data['test_code'], $data['name'], $data['category_id'] ?: null, $data['price'],
            $data['sample_type'] ?: null, $data['unit'] ?: null, $data['normal_range'] ?: null, $data['male_range'] ?: null,
            $data['female_range'] ?: null, $data['child_range'] ?: null, $data['description'] ?: null,
            $data['instructions'] ?: null, $data['is_active'], $id]);
        logActivity('Test updated', $data['test_code'] . ' - ' . $data['name'], 'tests', $id);
        flash('success', 'Test updated successfully.');
        redirect(BASE_URL . '/modules/tests/index.php');
    }
}

$categories = fetchAll('SELECT id, name FROM test_categories ORDER BY sort_order, name');
$parameters = fetchAll('SELECT * FROM test_parameters WHERE test_id = ? ORDER BY sort_order, id', [$id]);

$pageTitle = 'Edit Test';
$pageSubtitle = sanitize($test['name']);
$activeMenu = 'tests';
$pageActions = '<a href="' . BASE_URL . '/modules/tests/parameters.php?test_id=' . $id . '" class="btn btn-outline-info"><i class="bi bi-list-ol me-1"></i>Parameters</a> '
    . '<a href="' . BASE_URL . '/modules/tests/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>';
require_once INC_PATH . '/header.php';
?>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-octagon me-2"></i><?= sanitize($error) ?></div>
<?php endforeach; ?>

<form method="post" class="row g-3">
    <?= csrfField() ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-droplet-half me-2"></i>Test information</div>
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label required">Test code</label>
                    <input type="text" name="test_code" class="form-control" value="<?= sanitize($data['test_code']) ?>" required>
                </div>
                <div class="col-md-8">
                    <label class="form-label required">Test name</label>
                    <input type="text" name="name" class="form-control" value="<?= sanitize($data['name']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-select">
                        <option value="">-- none --</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= (int)$data['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= sanitize($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Price</label>
                    <input type="number" step="0.01" min="0" name="price" class="form-control" value="<?= sanitize($data['price']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Unit</label>
                    <input type="text" name="unit" class="form-control" value="<?= sanitize($data['unit']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Sample type</label>
                    <input type="text" name="sample_type" class="form-control" value="<?= sanitize($data['sample_type']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Normal range</label>
                    <input type="text" name="normal_range" class="form-control" value="<?= sanitize($data['normal_range']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Male range</label>
                    <input type="text" name="male_range" class="form-control" value="<?= sanitize($data['male_range']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Female range</label>
                    <input type="text" name="female_range" class="form-control" value="<?= sanitize($data['female_range']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Child range</label>
                    <input type="text" name="child_range" class="form-control" value="<?= sanitize($data['child_range']) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2"><?= sanitize($data['description']) ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Patient instructions</label>
                    <textarea name="instructions" class="form-control" rows="2"><?= sanitize($data['instructions']) ?></textarea>
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" <?= $data['is_active'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white d-flex gap-2">
                <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Update test</button>
                <a href="<?= BASE_URL ?>/modules/tests/index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-list-ol me-2"></i>Parameters</span>
                <a href="parameters.php?test_id=<?= (int)$id ?>" class="btn btn-sm btn-outline-primary">Manage</a>
            </div>
            <ul class="list-group list-group-flush">
                <?php if (!$parameters): ?>
                    <li class="list-group-item text-muted small">Single-result test (no parameters).</li>
                <?php endif; ?>
                <?php foreach ($parameters as $p): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span><?= sanitize($p['name']) ?></span>
                        <small class="text-muted"><?= sanitize($p['normal_range']) ?> <?= sanitize($p['unit']) ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</form>

<?php require_once INC_PATH . '/footer.php'; ?>
