<?php
/**
 * JSON test lookup.
 *   ajax/get_test_price.php?id=5          -> single test
 *   ajax/get_test_price.php?ids=1,2,3     -> several tests + total
 *   ajax/get_test_price.php?q=cbc         -> search by name/code
 */
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json; charset=utf-8');

$id  = (int)($_GET['id'] ?? 0);
$ids = trim((string)($_GET['ids'] ?? ''));
$q   = trim((string)($_GET['q'] ?? ''));

if ($id > 0) {
    $test = fetchOne('SELECT t.id, t.test_code, t.name, t.price, t.sample_type, t.instructions, c.name AS category
                      FROM tests t LEFT JOIN test_categories c ON c.id = t.category_id
                      WHERE t.id = ? AND t.is_active = 1', [$id]);
    echo json_encode($test ?: ['error' => 'Test not found']);
    exit;
}

if ($ids !== '') {
    $list = array_values(array_filter(array_map('intval', explode(',', $ids))));
    if (!$list) {
        echo json_encode(['tests' => [], 'total' => 0]);
        exit;
    }
    $placeholders = implode(',', array_fill(0, count($list), '?'));
    $tests = fetchAll("SELECT id, test_code, name, price FROM tests WHERE id IN ($placeholders) AND is_active = 1", $list);
    $total = 0;
    foreach ($tests as $t) {
        $total += (float)$t['price'];
    }
    echo json_encode(['tests' => $tests, 'total' => round($total, 2)]);
    exit;
}

if ($q !== '') {
    $like = '%' . $q . '%';
    $tests = fetchAll('SELECT t.id, t.test_code, t.name, t.price, c.name AS category
                       FROM tests t LEFT JOIN test_categories c ON c.id = t.category_id
                       WHERE t.is_active = 1 AND (t.name LIKE ? OR t.test_code LIKE ?)
                       ORDER BY t.name LIMIT 20', [$like, $like]);
    echo json_encode($tests);
    exit;
}

echo json_encode([]);
