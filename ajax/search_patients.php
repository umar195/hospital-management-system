<?php
/** JSON patient search used by the walk-in workflow and order screens. */
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json; charset=utf-8');

$term = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($term) < 2) {
    echo json_encode([]);
    exit;
}

$like = '%' . $term . '%';
$rows = fetchAll(
    'SELECT id, patient_id, full_name, gender, age, phone, whatsapp, blood_group, referring_doctor
     FROM patients
     WHERE full_name LIKE ? OR patient_id LIKE ? OR phone LIKE ? OR whatsapp LIKE ? OR cnic LIKE ?
     ORDER BY id DESC LIMIT 15',
    [$like, $like, $like, $like, $like]
);

echo json_encode($rows, JSON_UNESCAPED_UNICODE);
