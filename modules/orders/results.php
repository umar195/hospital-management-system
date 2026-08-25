<?php
/**
 * Enter / update laboratory results for a test order.
 * Values are compared with the applicable reference range (child / gender / normal)
 * and automatically flagged as Low, Normal or High.
 */
require_once __DIR__ . '/../../config/session.php';

$id = (int)get('id', post('id'));
$order = $id ? fetchOne('SELECT o.*, p.full_name, p.patient_id AS patient_code, p.gender, p.age, p.date_of_birth, p.phone
    FROM test_orders o JOIN patients p ON p.id = o.patient_id WHERE o.id = ?', [$id]) : null;

if (!$order) {
    flash('danger', 'Order not found.');
    redirect(BASE_URL . '/modules/orders/index.php');
}

$patientAge = $order['age'] !== null ? (int)$order['age'] : ($order['date_of_birth'] ? calculateAge($order['date_of_birth']) : null);
$gender = $order['gender'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(BASE_URL . '/modules/orders/results.php?id=' . $id);

    $values      = (array)($_POST['value'] ?? []);
    $resultIds   = (array)($_POST['result_id'] ?? []);
    $itemIds     = (array)($_POST['item_id'] ?? []);
    $paramIds    = (array)($_POST['parameter_id'] ?? []);
    $paramNames  = (array)($_POST['parameter_name'] ?? []);
    $units       = (array)($_POST['unit'] ?? []);
    $ranges      = (array)($_POST['reference_range'] ?? []);
    $flags       = (array)($_POST['flag'] ?? []);
    $notes       = (array)($_POST['note'] ?? []);
    $completed   = array_map('intval', (array)($_POST['complete_item'] ?? []));

    $ownItems = array_map('intval', array_column(fetchAll('SELECT id FROM test_order_items WHERE order_id = ?', [$id]), 'id'));

    try {
        $pdo->beginTransaction();
        $insert = $pdo->prepare('INSERT INTO test_results (order_item_id, parameter_id, parameter_name, result_value, unit, reference_range, flag, notes)
                                 VALUES (?,?,?,?,?,?,?,?)');
        $update = $pdo->prepare('UPDATE test_results SET parameter_id=?, parameter_name=?, result_value=?, unit=?, reference_range=?, flag=?, notes=? WHERE id=?');
        $deleteStmt = $pdo->prepare('DELETE FROM test_results WHERE id = ?');
        $saved = 0;

        foreach ($itemIds as $i => $itemId) {
            $itemId = (int)$itemId;
            if (!in_array($itemId, $ownItems, true)) {
                continue;
            }
            $value = trim((string)($values[$i] ?? ''));
            $resultId = (int)($resultIds[$i] ?? 0);
            $range = trim((string)($ranges[$i] ?? ''));
            $flag = (string)($flags[$i] ?? 'auto');

            if ($value === '') {
                if ($resultId > 0) {
                    $deleteStmt->execute([$resultId]);
                }
                continue;
            }
            if ($flag === 'auto' || !in_array($flag, ['Normal', 'Low', 'High', ''], true)) {
                $flag = evaluateFlag($value, $range);
            }
            $row = [
                (int)($paramIds[$i] ?? 0) ?: null,
                trim((string)($paramNames[$i] ?? '')) ?: null,
                $value,
                trim((string)($units[$i] ?? '')) ?: null,
                $range ?: null,
                $flag,
                trim((string)($notes[$i] ?? '')) ?: null,
            ];
            if ($resultId > 0) {
                $update->execute(array_merge($row, [$resultId]));
            } else {
                $insert->execute(array_merge([$itemId], $row));
            }
            $saved++;
        }

        // update per-test status
        $statusStmt = $pdo->prepare('UPDATE test_order_items SET status = ? WHERE id = ? AND order_id = ?');
        foreach ($ownItems as $itemId) {
            $hasResults = (int)fetchValue('SELECT COUNT(*) FROM test_results WHERE order_item_id = ?', [$itemId]);
            if (in_array($itemId, $completed, true) && $hasResults) {
                $statusStmt->execute(['Completed', $itemId, $id]);
            } elseif ($hasResults) {
                $current = fetchValue('SELECT status FROM test_order_items WHERE id = ?', [$itemId], 'Ordered');
                if (in_array($current, ['Ordered', 'Sample Collected'], true)) {
                    $statusStmt->execute(['Processing', $itemId, $id]);
                }
            }
        }
        $pdo->commit();
        logActivity('Results saved', $saved . ' result(s) for ' . $order['order_number'], 'test_orders', $id);
        flash('success', $saved . ' result(s) saved.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('danger', 'Could not save results: ' . $e->getMessage());
    }

    if (post('after') === 'report') {
        redirect(BASE_URL . '/modules/orders/view.php?id=' . $id);
    }
    redirect(BASE_URL . '/modules/orders/results.php?id=' . $id);
}

$items = fetchAll('SELECT i.*, t.id AS test_id, t.name AS test_name, t.test_code, t.unit, t.normal_range, t.male_range,
        t.female_range, t.child_range, t.sample_type
    FROM test_order_items i JOIN tests t ON t.id = i.test_id WHERE i.order_id = ? ORDER BY t.name', [$id]);

$existing = [];
foreach (fetchAll('SELECT r.* FROM test_results r JOIN test_order_items i ON i.id = r.order_item_id WHERE i.order_id = ?', [$id]) as $r) {
    $key = $r['parameter_id'] ? 'p' . $r['parameter_id'] : 'n' . strtolower((string)$r['parameter_name']);
    $existing[(int)$r['order_item_id']][$key] = $r;
}

$pageTitle = 'Enter Results';
$pageSubtitle = sanitize($order['order_number'] . ' · ' . $order['full_name']);
$activeMenu = 'orders';
$pageActions = '<a href="' . BASE_URL . '/modules/orders/view.php?id=' . $id . '" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to order</a>';
require_once INC_PATH . '/header.php';
?>

<div class="alert alert-info d-flex flex-wrap gap-3 align-items-center">
    <span><i class="bi bi-person me-1"></i><strong><?= sanitize($order['full_name']) ?></strong> (<?= sanitize($order['patient_code']) ?>)</span>
    <span><i class="bi bi-gender-ambiguous me-1"></i><?= sanitize($gender) ?></span>
    <span><i class="bi bi-calendar3 me-1"></i><?= $patientAge !== null ? $patientAge . ' yrs' : 'Age n/a' ?></span>
    <span class="ms-auto small">Reference ranges are chosen automatically for the patient's age &amp; gender.</span>
</div>

<form method="post">
    <?= csrfField() ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <?php $rowIndex = 0; ?>
    <?php foreach ($items as $item):
        $parameters = fetchAll('SELECT * FROM test_parameters WHERE test_id = ? ORDER BY sort_order, id', [$item['test_id']]);
        $rows = $parameters ?: [[
            'id' => 0,
            'name' => $item['test_name'],
            'unit' => $item['unit'],
            'normal_range' => $item['normal_range'],
            'male_range' => $item['male_range'],
            'female_range' => $item['female_range'],
            'child_range' => $item['child_range'],
        ]];
        ?>
        <div class="card mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="fw-semibold"><i class="bi bi-droplet-half me-2"></i><?= sanitize($item['test_name']) ?>
                    <small class="text-muted ms-2"><?= sanitize($item['test_code']) ?><?= $item['sample_type'] ? ' · ' . sanitize($item['sample_type']) : '' ?></small>
                </span>
                <span class="d-flex align-items-center gap-3">
                    <?= statusBadge($item['status']) ?>
                    <span class="form-check">
                        <input class="form-check-input" type="checkbox" name="complete_item[]" value="<?= (int)$item['id'] ?>"
                               id="complete<?= (int)$item['id'] ?>" <?= in_array($item['status'], ['Completed', 'Delivered'], true) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="complete<?= (int)$item['id'] ?>">Mark completed</label>
                    </span>
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th style="width:24%">Parameter</th><th style="width:18%">Result</th><th>Unit</th><th>Reference range</th><th style="width:12%">Flag</th><th>Note</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $p):
                        $range = pickReferenceRange($p, $gender, $patientAge);
                        $key = !empty($p['id']) ? 'p' . (int)$p['id'] : 'n' . strtolower((string)$p['name']);
                        $prev = $existing[(int)$item['id']][$key] ?? null;
                        $value = $prev['result_value'] ?? '';
                        $flag = $prev['flag'] ?? '';
                        $rowIndex++; ?>
                        <tr class="result-row">
                            <td class="small fw-semibold"><?= sanitize($p['name']) ?></td>
                            <td>
                                <input type="hidden" name="item_id[]" value="<?= (int)$item['id'] ?>">
                                <input type="hidden" name="result_id[]" value="<?= (int)($prev['id'] ?? 0) ?>">
                                <input type="hidden" name="parameter_id[]" value="<?= (int)($p['id'] ?? 0) ?>">
                                <input type="hidden" name="parameter_name[]" value="<?= sanitize($p['name']) ?>">
                                <input type="text" name="value[]" class="form-control form-control-sm result-value"
                                       value="<?= sanitize($value) ?>" data-range="<?= sanitize($range) ?>" autocomplete="off">
                            </td>
                            <td><input type="text" name="unit[]" class="form-control form-control-sm" value="<?= sanitize($prev['unit'] ?? $p['unit'] ?? '') ?>" style="width:90px"></td>
                            <td><input type="text" name="reference_range[]" class="form-control form-control-sm reference-input" value="<?= sanitize($prev['reference_range'] ?? $range) ?>"></td>
                            <td>
                                <select name="flag[]" class="form-select form-select-sm flag-select">
                                    <option value="auto">Auto</option>
                                    <option value="Normal" <?= $flag === 'Normal' ? 'selected' : '' ?>>Normal</option>
                                    <option value="Low" <?= $flag === 'Low' ? 'selected' : '' ?>>Low</option>
                                    <option value="High" <?= $flag === 'High' ? 'selected' : '' ?>>High</option>
                                    <option value="" <?= $flag === '' && $value !== '' ? 'selected' : '' ?>>None</option>
                                </select>
                                <span class="flag-preview small"><?= flagBadge($flag) ?></span>
                            </td>
                            <td><input type="text" name="note[]" class="form-control form-control-sm" value="<?= sanitize($prev['notes'] ?? '') ?>" placeholder="Optional"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="card">
        <div class="card-body d-flex flex-wrap gap-2">
            <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Save results</button>
            <button class="btn btn-success" name="after" value="report"><i class="bi bi-check2-circle me-1"></i>Save &amp; return to order</button>
            <a href="<?= BASE_URL ?>/modules/orders/view.php?id=<?= (int)$id ?>" class="btn btn-outline-secondary">Cancel</a>
            <span class="ms-auto small text-muted align-self-center">Leave a result empty to remove it.</span>
        </div>
    </div>
</form>

<?php
$pageScripts = <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded', function () {
    function parseRange(range) {
        if (!range) { return null; }
        var r = range.toString().toLowerCase().replace(/,/g, '').trim();
        var m;
        if ((m = r.match(/^(-?\d+(?:\.\d+)?)\s*(?:-|to|–)\s*(-?\d+(?:\.\d+)?)$/))) {
            return { min: parseFloat(m[1]), max: parseFloat(m[2]) };
        }
        if ((m = r.match(/^(?:<|less than|up to|upto)\s*(-?\d+(?:\.\d+)?)$/))) {
            return { min: null, max: parseFloat(m[1]) };
        }
        if ((m = r.match(/^(?:>|greater than|above)\s*(-?\d+(?:\.\d+)?)$/))) {
            return { min: parseFloat(m[1]), max: null };
        }
        return null;
    }

    function badge(flag) {
        if (flag === 'Low') { return '<span class="badge bg-info text-dark">Low</span>'; }
        if (flag === 'High') { return '<span class="badge bg-danger">High</span>'; }
        if (flag === 'Normal') { return '<span class="badge bg-success">Normal</span>'; }
        return '';
    }

    function evaluate(row) {
        var input = row.querySelector('.result-value');
        var select = row.querySelector('.flag-select');
        var preview = row.querySelector('.flag-preview');
        var rangeInput = row.querySelector('.reference-input');
        var value = parseFloat((input.value || '').replace(/[^0-9.\-]/g, ''));
        var flag = '';
        if (select.value !== 'auto') {
            flag = select.value;
        } else {
            var range = parseRange(rangeInput.value || input.dataset.range);
            if (range && !isNaN(value) && input.value.trim() !== '') {
                if (range.min !== null && value < range.min) { flag = 'Low'; }
                else if (range.max !== null && value > range.max) { flag = 'High'; }
                else { flag = 'Normal'; }
            }
        }
        preview.innerHTML = badge(flag);
        input.classList.remove('is-low', 'is-high', 'is-normal');
        if (flag) { input.classList.add('is-' + flag.toLowerCase()); }
    }

    document.querySelectorAll('.result-row').forEach(function (row) {
        ['input', 'change'].forEach(function (ev) {
            row.querySelector('.result-value').addEventListener(ev, function () { evaluate(row); });
            row.querySelector('.reference-input').addEventListener(ev, function () { evaluate(row); });
            row.querySelector('.flag-select').addEventListener(ev, function () { evaluate(row); });
        });
        evaluate(row);
    });
});
</script>
HTML;
require_once INC_PATH . '/footer.php';
