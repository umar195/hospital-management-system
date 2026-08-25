<?php
/** JSON dashboard counters (used for live refresh). */
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json; charset=utf-8');

$today = date('Y-m-d');

echo json_encode([
    'generated_at'       => date('c'),
    'today_patients'     => (int)fetchValue('SELECT COUNT(*) FROM patients WHERE registration_date = ?', [$today]),
    'today_walkins'      => (int)fetchValue("SELECT COUNT(*) FROM visits WHERE DATE(visit_date) = ? AND visit_type = 'Walk-In'", [$today]),
    'today_appointments' => (int)fetchValue('SELECT COUNT(*) FROM appointments WHERE appointment_date = ?', [$today]),
    'today_orders'       => (int)fetchValue('SELECT COUNT(*) FROM test_orders WHERE DATE(order_date) = ?', [$today]),
    'today_tests'        => (int)fetchValue('SELECT COUNT(*) FROM test_order_items i JOIN test_orders o ON o.id = i.order_id WHERE DATE(o.order_date) = ?', [$today]),
    'pending_tests'      => (int)fetchValue("SELECT COUNT(*) FROM test_order_items WHERE status NOT IN ('Completed','Delivered')"),
    'reports'            => (int)fetchValue('SELECT COUNT(*) FROM reports'),
    'total_patients'     => (int)fetchValue('SELECT COUNT(*) FROM patients'),
    'today_revenue'      => round((float)fetchValue('SELECT COALESCE(SUM(amount),0) FROM payments WHERE DATE(payment_date) = ?', [$today]), 2),
    'month_revenue'      => round((float)fetchValue('SELECT COALESCE(SUM(amount),0) FROM payments WHERE YEAR(payment_date)=YEAR(CURDATE()) AND MONTH(payment_date)=MONTH(CURDATE())'), 2),
    'pending_payments'   => round((float)fetchValue('SELECT COALESCE(SUM(remaining_amount),0) FROM test_orders'), 2),
    'low_stock'          => (int)fetchValue('SELECT COUNT(*) FROM inventory_items WHERE quantity <= minimum_stock'),
], JSON_UNESCAPED_UNICODE);
