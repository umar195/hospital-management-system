<?php
/**
 * Hospital Management System - Shared helper functions
 */

// ---------------------------------------------------------------------------
// Output / input helpers
// ---------------------------------------------------------------------------

/** Escape a value for safe HTML output. */
function sanitize($input)
{
    return htmlspecialchars((string)($input ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Short alias for sanitize(). */
function e($input)
{
    return sanitize($input);
}

/** Read a trimmed value from an array (usually $_POST / $_GET). */
function post($key, $default = '')
{
    return isset($_POST[$key]) ? (is_array($_POST[$key]) ? $_POST[$key] : trim((string)$_POST[$key])) : $default;
}

function get($key, $default = '')
{
    return isset($_GET[$key]) ? (is_array($_GET[$key]) ? $_GET[$key] : trim((string)$_GET[$key])) : $default;
}

/** Redirect helper. */
function redirect($url)
{
    if (strpos($url, 'http') !== 0) {
        $url = BASE_URL . '/' . ltrim($url, '/');
    }
    header('Location: ' . $url);
    exit;
}

// ---------------------------------------------------------------------------
// Flash messages
// ---------------------------------------------------------------------------

function flash($type, $message)
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function getFlash()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return [];
    }
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function renderFlash()
{
    $html = '';
    foreach (getFlash() as $item) {
        $type = in_array($item['type'], ['success', 'danger', 'warning', 'info'], true) ? $item['type'] : 'info';
        $icon = ['success' => 'check-circle', 'danger' => 'exclamation-octagon', 'warning' => 'exclamation-triangle', 'info' => 'info-circle'][$type];
        $html .= '<div class="alert alert-' . $type . ' alert-dismissible fade show d-flex align-items-center" role="alert">'
            . '<i class="bi bi-' . $icon . ' me-2"></i><div>' . sanitize($item['message']) . '</div>'
            . '<button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
    return $html;
}

// ---------------------------------------------------------------------------
// CSRF protection
// ---------------------------------------------------------------------------

function csrfToken()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField()
{
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

function verifyCsrf($token = null)
{
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? ($_GET['csrf_token'] ?? '');
    }
    return !empty($token) && hash_equals(csrfToken(), (string)$token);
}

/** Abort the request when the CSRF token is missing/invalid. */
function requireCsrf($redirectTo = null)
{
    if (!verifyCsrf()) {
        flash('danger', 'Security check failed (invalid CSRF token). Please try again.');
        redirect($redirectTo ?: (BASE_URL . '/dashboard.php'));
    }
}

// ---------------------------------------------------------------------------
// Settings
// ---------------------------------------------------------------------------

function getSetting($key, $default = '')
{
    global $pdo;
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach ($pdo->query('SELECT setting_key, setting_value FROM settings') as $row) {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            $cache = [];
        }
    }
    if (array_key_exists($key, $cache) && $cache[$key] !== null && $cache[$key] !== '') {
        return $cache[$key];
    }
    return $default;
}

function setSetting($key, $value)
{
    global $pdo;
    $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                           ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->execute([$key, $value]);
}

// ---------------------------------------------------------------------------
// Formatting
// ---------------------------------------------------------------------------

function currencySymbol()
{
    return getSetting('currency_symbol', 'Rs.');
}

function formatCurrency($amount)
{
    return currencySymbol() . ' ' . number_format((float)$amount, 2);
}

function formatDate($date, $format = null)
{
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return '-';
    }
    $format = $format ?: getSetting('date_format', 'd M Y');
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '-';
}

function formatDateTime($date)
{
    if (empty($date) || strpos($date, '0000-00-00') === 0) {
        return '-';
    }
    $ts = strtotime($date);
    return $ts ? date(getSetting('date_format', 'd M Y') . ' ' . getSetting('time_format', 'h:i A'), $ts) : '-';
}

function formatTime($time)
{
    if (empty($time)) {
        return '-';
    }
    $ts = strtotime($time);
    return $ts ? date(getSetting('time_format', 'h:i A'), $ts) : '-';
}

/** Calculate age (years) from a date of birth. */
function calculateAge($dob)
{
    if (empty($dob)) {
        return null;
    }
    try {
        $birth = new DateTime($dob);
        $now = new DateTime('today');
        return (int)$birth->diff($now)->y;
    } catch (Exception $e) {
        return null;
    }
}

// ---------------------------------------------------------------------------
// Auto generated identifiers
// ---------------------------------------------------------------------------

/**
 * Build the next sequential code for a table column.
 * Example: nextSequence('patients','patient_id','PAT-2026-', 5) => PAT-2026-00001
 */
function nextSequence($table, $column, $prefix, $padding = 5)
{
    global $pdo;
    $allowed = [
        'patients'          => 'patient_id',
        'visits'            => 'visit_number',
        'test_orders'       => 'order_number',
        'reports'           => 'report_number',
        'invoices'          => 'invoice_number',
        'expenses'          => 'expense_id',
        'inventory_items'   => 'item_code',
        'tests'             => 'test_code',
    ];
    if (!isset($allowed[$table]) || $allowed[$table] !== $column) {
        throw new InvalidArgumentException('Invalid sequence target.');
    }
    $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING($column, ?) AS UNSIGNED)) FROM $table WHERE $column LIKE ?");
    $stmt->execute([strlen($prefix) + 1, $prefix . '%']);
    $max = (int)$stmt->fetchColumn();
    return $prefix . str_pad((string)($max + 1), $padding, '0', STR_PAD_LEFT);
}

function generatePatientId()
{
    $prefix = getSetting('patient_prefix', 'PAT') . '-' . date('Y') . '-';
    return nextSequence('patients', 'patient_id', $prefix, 5);
}

function generateVisitNumber()
{
    return nextSequence('visits', 'visit_number', 'VIS-' . date('Ymd') . '-', 4);
}

function generateOrderNumber()
{
    $prefix = getSetting('order_prefix', 'ORD') . '-' . date('Ymd') . '-';
    return nextSequence('test_orders', 'order_number', $prefix, 4);
}

function generateReportNumber()
{
    $prefix = getSetting('report_prefix', 'RPT') . '-' . date('Ymd') . '-';
    return nextSequence('reports', 'report_number', $prefix, 4);
}

function generateInvoiceNumber()
{
    $prefix = getSetting('invoice_prefix', 'INV') . '-' . date('Ymd') . '-';
    return nextSequence('invoices', 'invoice_number', $prefix, 4);
}

function generateExpenseId()
{
    return nextSequence('expenses', 'expense_id', 'EXP-' . date('Ym') . '-', 4);
}

function generateItemCode()
{
    return nextSequence('inventory_items', 'item_code', 'ITM-', 5);
}

function generateTestCode()
{
    return nextSequence('tests', 'test_code', 'T-', 4);
}

// ---------------------------------------------------------------------------
// Activity log
// ---------------------------------------------------------------------------

function logActivity($action, $description = '', $table = null, $id = null)
{
    global $pdo;
    try {
        $stmt = $pdo->prepare('INSERT INTO activity_logs (action, description, related_table, related_id) VALUES (?,?,?,?)');
        $stmt->execute([$action, $description, $table, $id]);
    } catch (Exception $e) {
        // logging must never break the app
    }
}

// ---------------------------------------------------------------------------
// Reference range evaluation
// ---------------------------------------------------------------------------

/**
 * Pick the reference range that applies to a patient.
 */
function pickReferenceRange(array $row, $gender = null, $age = null)
{
    if ($age !== null && $age !== '' && (int)$age < 13 && !empty($row['child_range'])) {
        return $row['child_range'];
    }
    if ($gender === 'Male' && !empty($row['male_range'])) {
        return $row['male_range'];
    }
    if ($gender === 'Female' && !empty($row['female_range'])) {
        return $row['female_range'];
    }
    return $row['normal_range'] ?? '';
}

/**
 * Compare a numeric result to a reference range string and return
 * 'Low', 'Normal', 'High' or '' when it cannot be determined.
 * Supported formats: "12-16", "12 - 16", "<200", "> 40", "up to 40", "0.5 to 1.2"
 */
function evaluateFlag($value, $range)
{
    if ($value === null || $value === '' || !is_numeric($value) || $range === null || $range === '') {
        return '';
    }
    $val = (float)$value;
    $r = strtolower(trim($range));
    $r = str_replace(['–', '—', 'to'], ['-', '-', '-'], $r);
    $r = str_replace(['up -', 'up to'], '<', $r);
    $r = preg_replace('/[a-z%\/^\s]+/', '', $r);

    if (preg_match('/^<=?(-?\d+(?:\.\d+)?)$/', $r, $m)) {
        return $val > (float)$m[1] ? 'High' : 'Normal';
    }
    if (preg_match('/^>=?(-?\d+(?:\.\d+)?)$/', $r, $m)) {
        return $val < (float)$m[1] ? 'Low' : 'Normal';
    }
    if (preg_match('/^(-?\d+(?:\.\d+)?)-(-?\d+(?:\.\d+)?)$/', $r, $m)) {
        $low = (float)$m[1];
        $high = (float)$m[2];
        if ($val < $low) { return 'Low'; }
        if ($val > $high) { return 'High'; }
        return 'Normal';
    }
    return '';
}

function flagBadge($flag)
{
    switch ($flag) {
        case 'Low':
            return '<span class="badge bg-info text-dark">Low</span>';
        case 'High':
            return '<span class="badge bg-danger">High</span>';
        case 'Normal':
            return '<span class="badge bg-success">Normal</span>';
    }
    return '<span class="text-muted">-</span>';
}

function statusBadge($status)
{
    $map = [
        'Ordered'          => 'secondary',
        'Sample Collected' => 'info',
        'Processing'       => 'primary',
        'Pending'          => 'warning',
        'Completed'        => 'success',
        'Delivered'        => 'success',
        'Scheduled'        => 'primary',
        'Confirmed'        => 'info',
        'Arrived'          => 'info',
        'Checked In'       => 'info',
        'Waiting'          => 'warning',
        'Called'           => 'info',
        'In Consultation'  => 'primary',
        'Cancelled'        => 'danger',
        'No Show'          => 'dark',
        'Missed'           => 'danger',
        'Due Today'        => 'warning',
        'Upcoming'         => 'info',
        'Overdue'          => 'danger',
        'Paid'             => 'success',
        'Partial'          => 'warning',
        'Draft'            => 'secondary',
        'Final'            => 'success',
    ];
    $class = $map[$status] ?? 'secondary';
    return '<span class="badge badge-status badge-' . strtolower(str_replace(' ', '-', $status)) . ' bg-' . $class . '">' . sanitize($status) . '</span>';
}

// ---------------------------------------------------------------------------
// Consultation / queue / follow-up workflow helpers
// ---------------------------------------------------------------------------

/** Next queue token for a doctor for today (starts at 1 every day). */
function nextTokenNumber($doctorId = null)
{
    if ($doctorId) {
        return (int)fetchValue('SELECT COALESCE(MAX(token_number),0) + 1 FROM visits WHERE DATE(visit_date) = CURDATE() AND doctor_id = ?', [$doctorId], 1);
    }
    return (int)fetchValue('SELECT COALESCE(MAX(token_number),0) + 1 FROM visits WHERE DATE(visit_date) = CURDATE()', [], 1);
}

/**
 * Create a consultation visit and place the patient in the doctor waiting queue.
 * Returns the new visit id. Must be called inside an open transaction when
 * combined with other inserts.
 */
function createQueueVisit(array $data)
{
    global $pdo;
    $visitNumber = generateVisitNumber();
    $token = nextTokenNumber($data['doctor_id'] ?? null);
    $stmt = $pdo->prepare('INSERT INTO visits (visit_number, patient_id, visit_date, visit_type, doctor_id, appointment_id,
                           follow_up_of_visit_id, token_number, queue_status, chief_complaint, symptoms, notes,
                           consultation_fee, total_charges, payment_status, checked_in_at)
                           VALUES (?,?,NOW(),?,?,?,?,?,\'Waiting\',?,?,?,?,?,\'Pending\',NOW())');
    $fee = (float)($data['consultation_fee'] ?? 0);
    $stmt->execute([
        $visitNumber,
        (int)$data['patient_id'],
        $data['visit_type'] ?? 'Walk-In',
        $data['doctor_id'] ?? null,
        $data['appointment_id'] ?? null,
        $data['follow_up_of_visit_id'] ?? null,
        $token,
        $data['chief_complaint'] ?? null,
        $data['symptoms'] ?? null,
        $data['notes'] ?? null,
        $fee,
        $fee,
    ]);
    return (int)$pdo->lastInsertId();
}

/** Effective display status of a follow-up ('Scheduled' becomes Due Today / Upcoming / Overdue). */
function followUpDisplayStatus(array $followUp)
{
    if ($followUp['status'] !== 'Scheduled') {
        return $followUp['status'];
    }
    $today = date('Y-m-d');
    if ($followUp['follow_up_date'] === $today) {
        return 'Due Today';
    }
    return $followUp['follow_up_date'] < $today ? 'Overdue' : 'Upcoming';
}

/** Small coloured queue token badge. */
function tokenBadge($token)
{
    if ($token === null || $token === '') {
        return '<span class="text-muted">-</span>';
    }
    return '<span class="token-badge">#' . (int)$token . '</span>';
}

// ---------------------------------------------------------------------------
// Pagination
// ---------------------------------------------------------------------------

define('PER_PAGE', 20);

function currentPage()
{
    $page = (int)($_GET['page'] ?? 1);
    return $page > 0 ? $page : 1;
}

/**
 * Render bootstrap pagination links preserving current query string.
 */
function paginationLinks($total, $perPage = PER_PAGE, $page = null)
{
    $page = $page ?: currentPage();
    $pages = (int)ceil($total / max(1, $perPage));
    if ($pages < 2) {
        return '';
    }
    $query = $_GET;
    $html = '<nav><ul class="pagination pagination-sm mb-0">';
    $build = function ($p) use ($query) {
        $query['page'] = $p;
        return '?' . http_build_query($query);
    };
    $html .= '<li class="page-item ' . ($page <= 1 ? 'disabled' : '') . '"><a class="page-link" href="' . sanitize($build(max(1, $page - 1))) . '">&laquo;</a></li>';
    $start = max(1, $page - 3);
    $end = min($pages, $page + 3);
    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . sanitize($build(1)) . '">1</a></li>';
        if ($start > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
    }
    for ($i = $start; $i <= $end; $i++) {
        $html .= '<li class="page-item ' . ($i === $page ? 'active' : '') . '"><a class="page-link" href="' . sanitize($build($i)) . '">' . $i . '</a></li>';
    }
    if ($end < $pages) {
        if ($end < $pages - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . sanitize($build($pages)) . '">' . $pages . '</a></li>';
    }
    $html .= '<li class="page-item ' . ($page >= $pages ? 'disabled' : '') . '"><a class="page-link" href="' . sanitize($build(min($pages, $page + 1))) . '">&raquo;</a></li>';
    $html .= '</ul></nav>';
    return $html;
}

// ---------------------------------------------------------------------------
// Misc
// ---------------------------------------------------------------------------

/** Build a wa.me sharing link with a pre-filled message. */
function whatsappLink($phone, $message)
{
    $digits = preg_replace('/\D+/', '', (string)$phone);
    $code = preg_replace('/\D+/', '', getSetting('country_code', '92'));
    if ($digits !== '' && strpos($digits, '0') === 0) {
        $digits = $code . substr($digits, 1);
    }
    return 'https://wa.me/' . $digits . '?text=' . rawurlencode($message);
}

/** Recalculate the payment totals of a test order. */
function recalcOrderTotals($orderId)
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(price),0) FROM test_order_items WHERE order_id = ?');
    $stmt->execute([$orderId]);
    $total = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT discount, paid_amount FROM test_orders WHERE id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) {
        return;
    }
    $net = max(0, $total - (float)$order['discount']);
    $remaining = max(0, $net - (float)$order['paid_amount']);
    $stmt = $pdo->prepare('UPDATE test_orders SET total_amount=?, net_amount=?, remaining_amount=? WHERE id=?');
    $stmt->execute([$total, $net, $remaining, $orderId]);
}

/** Refresh invoice paid amount / balance / status from the payments table. */
function recalcInvoiceTotals($invoiceId)
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id = ?');
    $stmt->execute([$invoiceId]);
    $paid = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT total FROM invoices WHERE id = ?');
    $stmt->execute([$invoiceId]);
    $total = (float)$stmt->fetchColumn();

    $balance = round($total - $paid, 2);
    $status = $paid <= 0 ? 'Pending' : ($balance > 0.001 ? 'Partial' : 'Paid');
    $stmt = $pdo->prepare('UPDATE invoices SET paid_amount=?, balance=?, payment_status=? WHERE id=?');
    $stmt->execute([$paid, max(0, $balance), $status, $invoiceId]);
}

/** Fetch a single row or null. */
function fetchOne($sql, array $params = [])
{
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Fetch all rows. */
function fetchAll($sql, array $params = [])
{
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** Fetch a single scalar value. */
function fetchValue($sql, array $params = [], $default = 0)
{
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : $value;
}

/** Hospital logo URL (or empty string). */
function hospitalLogoUrl()
{
    $logo = getSetting('hospital_logo', '');
    if ($logo && is_file(UPLOADS_PATH . '/logos/' . $logo)) {
        return BASE_URL . '/uploads/logos/' . rawurlencode($logo);
    }
    return '';
}

/**
 * Use the local copy of a library when it has been downloaded into /assets,
 * otherwise fall back to the CDN (see README for offline instructions).
 */
function asset_url($localRelativePath, $cdnUrl)
{
    $full = APP_PATH . '/' . ltrim($localRelativePath, '/');
    if (is_file($full) && filesize($full) > 1024) {
        return BASE_URL . '/' . ltrim($localRelativePath, '/') . '?v=' . APP_VERSION;
    }
    return $cdnUrl;
}

/**
 * Log an exception server side and return a message that is safe to show to the user.
 * Validation problems (RuntimeException / InvalidArgumentException thrown by the app) are shown
 * as-is; database and runtime errors are replaced by a generic notice so that no internal
 * details leak into the browser.
 */
function friendlyError($e, $fallback = 'An internal error occurred. Please try again or contact the administrator.')
{
    error_log('[HMS] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if ($e instanceof PDOException || $e instanceof Error) {
        return $fallback;
    }
    if ($e instanceof RuntimeException || $e instanceof InvalidArgumentException) {
        return $e->getMessage();
    }
    return $fallback;
}
