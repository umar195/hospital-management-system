<?php
/** Manage the parameters of a multi-parameter test. */
require_once __DIR__ . '/../../config/session.php';

$testId = (int)get('test_id', post('test_id'));
$test = $testId ? fetchOne('SELECT * FROM tests WHERE id = ?', [$testId]) : null;
if (!$test) {
    flash('danger', 'Please choose a test first.');
    redirect(BASE_URL . '/modules/tests/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(BASE_URL . '/modules/tests/parameters.php?test_id=' . $testId);
    $action = post('action');

    if ($action === 'delete') {
        $pid = (int)post('id');
        $pdo->prepare('DELETE FROM test_parameters WHERE id = ? AND test_id = ?')->execute([$pid, $testId]);
        logActivity('Parameter deleted', 'Parameter #' . $pid, 'test_parameters', $pid);
        flash('success', 'Parameter deleted.');
    } elseif ($action === 'save') {
        $ids      = (array)($_POST['id'] ?? []);
        $names    = (array)($_POST['name'] ?? []);
        $units    = (array)($_POST['unit'] ?? []);
        $ranges   = (array)($_POST['normal_range'] ?? []);
        $males    = (array)($_POST['male_range'] ?? []);
        $females  = (array)($_POST['female_range'] ?? []);
        $children = (array)($_POST['child_range'] ?? []);

        $update = $pdo->prepare('UPDATE test_parameters SET name=?, unit=?, normal_range=?, male_range=?, female_range=?, child_range=?, sort_order=?
                                 WHERE id=? AND test_id=?');
        $insert = $pdo->prepare('INSERT INTO test_parameters (test_id, name, unit, normal_range, male_range, female_range, child_range, sort_order)
                                 VALUES (?,?,?,?,?,?,?,?)');
        $sort = 0;
        $saved = 0;
        foreach ($names as $i => $name) {
            $name = trim((string)$name);
            if ($name === '') {
                continue;
            }
            $sort++;
            $row = [
                $name,
                trim((string)($units[$i] ?? '')) ?: null,
                trim((string)($ranges[$i] ?? '')) ?: null,
                trim((string)($males[$i] ?? '')) ?: null,
                trim((string)($females[$i] ?? '')) ?: null,
                trim((string)($children[$i] ?? '')) ?: null,
                $sort,
            ];
            $pid = (int)($ids[$i] ?? 0);
            if ($pid > 0) {
                $update->execute(array_merge($row, [$pid, $testId]));
            } else {
                $insert->execute(array_merge([$testId], $row));
            }
            $saved++;
        }
        logActivity('Parameters saved', $test['name'] . ' (' . $saved . ')', 'tests', $testId);
        flash('success', $saved . ' parameter(s) saved for ' . sanitize($test['name']) . '.');
    }
    redirect(BASE_URL . '/modules/tests/parameters.php?test_id=' . $testId);
}

$parameters = fetchAll('SELECT * FROM test_parameters WHERE test_id = ? ORDER BY sort_order, id', [$testId]);
$tests = fetchAll('SELECT id, test_code, name FROM tests ORDER BY name');

$pageTitle = 'Test Parameters';
$pageSubtitle = sanitize($test['test_code'] . ' - ' . $test['name']);
$activeMenu = 'tests';
$pageActions = '<a href="' . BASE_URL . '/modules/tests/edit.php?id=' . $testId . '" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>Edit test</a> '
    . '<a href="' . BASE_URL . '/modules/tests/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>';
require_once INC_PATH . '/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-8">
                <label class="form-label">Switch test</label>
                <select name="test_id" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($tests as $t): ?>
                        <option value="<?= (int)$t['id'] ?>" <?= (int)$t['id'] === $testId ? 'selected' : '' ?>>
                            <?= sanitize($t['test_code'] . ' - ' . $t['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-repeat me-1"></i>Load</button>
            </div>
        </form>
    </div>
</div>

<form method="post">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="test_id" value="<?= (int)$testId ?>">
    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><i class="bi bi-list-ol me-2"></i>Parameters</span>
            <button type="button" class="btn btn-sm btn-outline-primary" id="addParam"><i class="bi bi-plus"></i> Add parameter</button>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" id="paramTable">
                <thead>
                <tr>
                    <th style="width:22%">Parameter</th><th>Unit</th><th>Normal range</th>
                    <th>Male range</th><th>Female range</th><th>Child range</th><th class="text-end">Remove</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($parameters as $p): ?>
                    <tr>
                        <td>
                            <input type="hidden" name="id[]" value="<?= (int)$p['id'] ?>">
                            <input type="text" name="name[]" class="form-control form-control-sm" value="<?= sanitize($p['name']) ?>">
                        </td>
                        <td><input type="text" name="unit[]" class="form-control form-control-sm" value="<?= sanitize($p['unit']) ?>"></td>
                        <td><input type="text" name="normal_range[]" class="form-control form-control-sm" value="<?= sanitize($p['normal_range']) ?>"></td>
                        <td><input type="text" name="male_range[]" class="form-control form-control-sm" value="<?= sanitize($p['male_range']) ?>"></td>
                        <td><input type="text" name="female_range[]" class="form-control form-control-sm" value="<?= sanitize($p['female_range']) ?>"></td>
                        <td><input type="text" name="child_range[]" class="form-control form-control-sm" value="<?= sanitize($p['child_range']) ?>"></td>
                        <td class="text-end">
                            <button type="submit" form="deleteParamForm" class="btn btn-sm btn-outline-danger"
                                    data-confirm="Delete parameter <?= sanitize($p['name']) ?>?"
                                    onclick="document.getElementById('deleteParamId').value = '<?= (int)$p['id'] ?>';">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white d-flex gap-2">
            <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Save parameters</button>
            <a href="<?= BASE_URL ?>/modules/tests/index.php" class="btn btn-outline-secondary">Back to tests</a>
        </div>
    </div>
</form>

<form method="post" id="deleteParamForm" class="d-none">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="test_id" value="<?= (int)$testId ?>">
    <input type="hidden" name="id" id="deleteParamId" value="">
</form>

<?php
$pageScripts = <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded', function () {
    var tbody = document.querySelector('#paramTable tbody');
    document.getElementById('addParam').addEventListener('click', function () {
        var tr = document.createElement('tr');
        tr.innerHTML = '<td><input type="hidden" name="id[]" value="0">' +
            '<input type="text" name="name[]" class="form-control form-control-sm" placeholder="Parameter name"></td>' +
            '<td><input type="text" name="unit[]" class="form-control form-control-sm"></td>' +
            '<td><input type="text" name="normal_range[]" class="form-control form-control-sm"></td>' +
            '<td><input type="text" name="male_range[]" class="form-control form-control-sm"></td>' +
            '<td><input type="text" name="female_range[]" class="form-control form-control-sm"></td>' +
            '<td><input type="text" name="child_range[]" class="form-control form-control-sm"></td>' +
            '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-x"></i></button></td>';
        tbody.appendChild(tr);
        tr.querySelector('.remove-row').addEventListener('click', function () { tr.remove(); });
        tr.querySelector('input[name="name[]"]').focus();
    });
});
</script>
HTML;
require_once INC_PATH . '/footer.php';
