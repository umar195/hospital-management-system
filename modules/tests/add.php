<?php
/** Add a test (optionally with parameters). */
require_once __DIR__ . '/../../config/session.php';

$errors = [];
$data = [
    'test_code' => '', 'name' => '', 'category_id' => (int)get('category_id'), 'price' => '0',
    'sample_type' => '', 'unit' => '', 'normal_range' => '', 'male_range' => '', 'female_range' => '',
    'child_range' => '', 'description' => '', 'instructions' => '', 'is_active' => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(BASE_URL . '/modules/tests/add.php');
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
    if ($data['test_code'] !== '' && fetchOne('SELECT id FROM tests WHERE test_code = ?', [$data['test_code']])) {
        $errors[] = 'This test code is already in use.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $code = $data['test_code'] !== '' ? $data['test_code'] : generateTestCode();
            $stmt = $pdo->prepare('INSERT INTO tests (test_code, name, category_id, price, sample_type, unit, normal_range,
                    male_range, female_range, child_range, description, instructions, is_active)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$code, $data['name'], $data['category_id'] ?: null, $data['price'], $data['sample_type'] ?: null,
                $data['unit'] ?: null, $data['normal_range'] ?: null, $data['male_range'] ?: null, $data['female_range'] ?: null,
                $data['child_range'] ?: null, $data['description'] ?: null, $data['instructions'] ?: null, $data['is_active']]);
            $testId = (int)$pdo->lastInsertId();

            // optional parameters
            $names = (array)($_POST['param_name'] ?? []);
            $units = (array)($_POST['param_unit'] ?? []);
            $ranges = (array)($_POST['param_range'] ?? []);
            $males = (array)($_POST['param_male'] ?? []);
            $females = (array)($_POST['param_female'] ?? []);
            $children = (array)($_POST['param_child'] ?? []);
            $paramStmt = $pdo->prepare('INSERT INTO test_parameters (test_id, name, unit, normal_range, male_range, female_range, child_range, sort_order)
                                        VALUES (?,?,?,?,?,?,?,?)');
            $sort = 0;
            foreach ($names as $i => $paramName) {
                $paramName = trim((string)$paramName);
                if ($paramName === '') {
                    continue;
                }
                $sort++;
                $paramStmt->execute([$testId, $paramName, trim((string)($units[$i] ?? '')) ?: null,
                    trim((string)($ranges[$i] ?? '')) ?: null, trim((string)($males[$i] ?? '')) ?: null,
                    trim((string)($females[$i] ?? '')) ?: null, trim((string)($children[$i] ?? '')) ?: null, $sort]);
            }
            $pdo->commit();
            logActivity('Test created', $code . ' - ' . $data['name'], 'tests', $testId);
            flash('success', 'Test ' . $code . ' created successfully.');
            redirect(BASE_URL . '/modules/tests/index.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = friendlyError($e, 'Could not save the test. Please try again.');
        }
    }
}

$categories = fetchAll('SELECT id, name FROM test_categories WHERE is_active = 1 ORDER BY sort_order, name');

$pageTitle = 'Add Test';
$activeMenu = 'tests';
$pageActions = '<a href="' . BASE_URL . '/modules/tests/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to list</a>';
require_once INC_PATH . '/header.php';
?>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-octagon me-2"></i><?= sanitize($error) ?></div>
<?php endforeach; ?>

<form method="post" class="row g-3">
    <?= csrfField() ?>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-droplet-half me-2"></i>Test information</div>
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label">Test code</label>
                    <input type="text" name="test_code" class="form-control" value="<?= sanitize($data['test_code']) ?>" placeholder="auto">
                    <div class="form-text">Leave empty to auto-generate.</div>
                </div>
                <div class="col-md-8">
                    <label class="form-label required">Test name</label>
                    <input type="text" name="name" class="form-control" value="<?= sanitize($data['name']) ?>" required autofocus>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-select">
                        <option value="">-- none --</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= $data['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= sanitize($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Price</label>
                    <input type="number" step="0.01" min="0" name="price" class="form-control" value="<?= sanitize($data['price']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Unit</label>
                    <input type="text" name="unit" class="form-control" value="<?= sanitize($data['unit']) ?>" placeholder="mg/dL">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Sample type</label>
                    <input type="text" name="sample_type" class="form-control" list="sampleTypes" value="<?= sanitize($data['sample_type']) ?>">
                    <datalist id="sampleTypes">
                        <?php foreach (['Serum', 'Whole Blood (EDTA)', 'Plasma', 'Urine', 'Stool', 'Swab', 'Imaging'] as $s): ?>
                            <option value="<?= $s ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Normal range</label>
                    <input type="text" name="normal_range" class="form-control" value="<?= sanitize($data['normal_range']) ?>" placeholder="e.g. 12-16 or &lt;200">
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
                    <textarea name="instructions" class="form-control" rows="2" placeholder="Fasting required, etc."><?= sanitize($data['instructions']) ?></textarea>
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" checked>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-list-ol me-2"></i>Parameters (optional)</span>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addParam"><i class="bi bi-plus"></i> Add row</button>
            </div>
            <div class="card-body">
                <p class="small text-muted">Add parameters for panels such as CBC or LFT. Leave empty for single-result tests.</p>
                <div class="table-responsive">
                    <table class="table table-sm align-middle" id="paramTable">
                        <thead><tr><th>Name</th><th>Unit</th><th>Normal</th><th>M</th><th>F</th><th>Child</th><th></th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white d-grid gap-2">
                <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Save test</button>
                <a href="<?= BASE_URL ?>/modules/tests/index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </div>
</form>

<?php
$pageScripts = <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded', function () {
    var tbody = document.querySelector('#paramTable tbody');
    function addRow() {
        var tr = document.createElement('tr');
        tr.innerHTML = '<td><input type="text" name="param_name[]" class="form-control form-control-sm"></td>' +
            '<td><input type="text" name="param_unit[]" class="form-control form-control-sm" style="width:70px"></td>' +
            '<td><input type="text" name="param_range[]" class="form-control form-control-sm" style="width:90px"></td>' +
            '<td><input type="text" name="param_male[]" class="form-control form-control-sm" style="width:80px"></td>' +
            '<td><input type="text" name="param_female[]" class="form-control form-control-sm" style="width:80px"></td>' +
            '<td><input type="text" name="param_child[]" class="form-control form-control-sm" style="width:80px"></td>' +
            '<td><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-x"></i></button></td>';
        tbody.appendChild(tr);
        tr.querySelector('.remove-row').addEventListener('click', function () { tr.remove(); });
    }
    document.getElementById('addParam').addEventListener('click', addRow);
});
</script>
HTML;
require_once INC_PATH . '/footer.php';
