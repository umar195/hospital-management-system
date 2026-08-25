<?php
/**
 * Print-optimised laboratory report (standalone page, no sidebar).
 * Use the browser print dialog to save it as a PDF.
 */
require_once __DIR__ . '/../../config/session.php';

$id = (int)get('id');
$report = $id ? fetchOne('SELECT r.*, o.order_number, o.order_date, o.referring_doctor,
        p.full_name, p.patient_id AS patient_code, p.gender, p.age, p.date_of_birth, p.phone, p.whatsapp,
        p.father_husband_name
    FROM reports r JOIN test_orders o ON o.id = r.order_id JOIN patients p ON p.id = r.patient_id WHERE r.id = ?', [$id]) : null;

if (!$report) {
    flash('danger', 'Report not found.');
    redirect(BASE_URL . '/modules/reports/index.php');
}

$rows = fetchAll('SELECT r.*, t.name AS test_name, t.test_code, t.sample_type
    FROM test_order_items i
    JOIN tests t ON t.id = i.test_id
    LEFT JOIN test_results r ON r.order_item_id = i.id
    WHERE i.order_id = ? ORDER BY t.name, r.id', [$report['order_id']]);

$grouped = [];
foreach ($rows as $row) {
    if ($row['id'] === null) {
        continue;
    }
    $grouped[$row['test_name']][] = $row;
}

$patientAge = $report['age'] !== null ? (int)$report['age'] : ($report['date_of_birth'] ? calculateAge($report['date_of_birth']) : null);
$hospitalName = getSetting('hospital_name', 'City Care Hospital');
$phone = $report['whatsapp'] ?: $report['phone'];
$waMessage = 'Dear ' . $report['full_name'] . ', your laboratory report ' . $report['report_number'] . ' from '
    . $hospitalName . ' is ready. Please find the PDF attached.';
$logo = hospitalLogoUrl();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= sanitize($report['report_number']) ?> &middot; <?= sanitize($report['full_name']) ?></title>
    <link rel="stylesheet" href="<?= asset_url('assets/css/bootstrap.min.css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('assets/css/bootstrap-icons.css', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css') ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/custom.css?v=<?= APP_VERSION ?>">
</head>
<body class="bg-light">

<div class="no-print bg-white border-bottom py-2 mb-3">
    <div class="container d-flex flex-wrap gap-2 align-items-center">
        <button class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print / Save as PDF</button>
        <?php if ($phone): ?>
            <a class="btn btn-success btn-sm" target="_blank" href="<?= sanitize(whatsappLink($phone, $waMessage)) ?>">
                <i class="bi bi-whatsapp me-1"></i>Send on WhatsApp
            </a>
        <?php endif; ?>
        <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/modules/reports/view.php?id=<?= (int)$id ?>">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
        <span class="small text-muted ms-auto">Tip: choose "Save as PDF" in the print dialog, then attach the file in WhatsApp.</span>
    </div>
</div>

<div class="print-sheet container bg-white p-4 mb-4">
    <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
        <div class="d-flex gap-3 align-items-center">
            <?php if ($logo): ?>
                <img src="<?= sanitize($logo) ?>" alt="Logo" style="max-height:70px">
            <?php endif; ?>
            <div>
                <h4 class="mb-1 text-primary"><?= sanitize($hospitalName) ?></h4>
                <div class="small text-muted"><?= nl2br(sanitize(getSetting('hospital_address', ''))) ?></div>
                <div class="small text-muted">
                    <?php if (getSetting('hospital_phone')): ?><i class="bi bi-telephone me-1"></i><?= sanitize(getSetting('hospital_phone')) ?><?php endif; ?>
                    <?php if (getSetting('hospital_email')): ?><i class="bi bi-envelope ms-2 me-1"></i><?= sanitize(getSetting('hospital_email')) ?><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="text-end">
            <div class="fw-bold">LABORATORY REPORT</div>
            <div class="small">Report #: <strong><?= sanitize($report['report_number']) ?></strong></div>
            <div class="small">Order #: <?= sanitize($report['order_number']) ?></div>
            <div class="small">Printed: <?= date('d M Y H:i') ?></div>
            <?php if ($report['status'] === 'Draft'): ?>
                <span class="badge bg-warning text-dark mt-1">DRAFT</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($header = getSetting('report_header', '')): ?>
        <p class="small text-muted"><?= nl2br(sanitize($header)) ?></p>
    <?php endif; ?>

    <table class="table table-sm table-bordered mb-4">
        <tbody>
        <tr>
            <th class="bg-light" style="width:15%">Patient</th>
            <td style="width:35%"><?= sanitize($report['full_name']) ?></td>
            <th class="bg-light" style="width:15%">Patient ID</th>
            <td><?= sanitize($report['patient_code']) ?></td>
        </tr>
        <tr>
            <th class="bg-light">Father / Husband</th>
            <td><?= sanitize($report['father_husband_name'] ?: '-') ?></td>
            <th class="bg-light">Age / Gender</th>
            <td><?= $patientAge !== null ? $patientAge . ' yrs' : '-' ?> / <?= sanitize($report['gender']) ?></td>
        </tr>
        <tr>
            <th class="bg-light">Referred by</th>
            <td><?= sanitize($report['referring_doctor'] ?: '-') ?></td>
            <th class="bg-light">Sample / Report date</th>
            <td><?= formatDateTime($report['order_date']) ?> / <?= formatDateTime($report['generated_at']) ?></td>
        </tr>
        </tbody>
    </table>

    <?php if (!$grouped): ?>
        <div class="alert alert-warning">No results have been recorded for this order yet.</div>
    <?php endif; ?>

    <?php foreach ($grouped as $testName => $group): ?>
        <h6 class="text-uppercase text-primary mb-2"><?= sanitize($testName) ?></h6>
        <table class="table table-sm table-bordered mb-4">
            <thead class="table-light">
            <tr>
                <th style="width:32%">Investigation</th>
                <th style="width:18%">Result</th>
                <th style="width:12%">Unit</th>
                <th style="width:24%">Reference range</th>
                <th style="width:14%">Flag</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($group as $row): ?>
                <tr>
                    <td><?= sanitize($row['parameter_name'] ?: $testName) ?></td>
                    <td class="fw-bold <?= $row['flag'] === 'High' ? 'text-danger' : ($row['flag'] === 'Low' ? 'text-info' : '') ?>">
                        <?= sanitize($row['result_value']) ?>
                    </td>
                    <td><?= sanitize($row['unit']) ?></td>
                    <td><?= sanitize($row['reference_range']) ?></td>
                    <td class="flag-<?= sanitize($row['flag'] ?: 'None') ?>"><?= sanitize($row['flag'] ?: '') ?></td>
                </tr>
                <?php if (!empty($row['notes'])): ?>
                    <tr><td colspan="5" class="small fst-italic text-muted">Note: <?= sanitize($row['notes']) ?></td></tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endforeach; ?>

    <?php if ($report['remarks']): ?>
        <div class="mb-4">
            <div class="fw-semibold">Remarks / Interpretation</div>
            <div class="small"><?= nl2br(sanitize($report['remarks'])) ?></div>
        </div>
    <?php endif; ?>

    <div class="row mt-5">
        <div class="col-6 small text-muted">
            <?= nl2br(sanitize(getSetting('report_footer', ''))) ?>
        </div>
        <div class="col-6 text-end">
            <div style="border-top:1px solid #333; display:inline-block; padding-top:.35rem; min-width:220px">
                <strong><?= sanitize($report['authorized_by'] ?: getSetting('authorized_by', 'Authorized Signatory')) ?></strong><br>
                <small class="text-muted">Authorized signatory</small>
            </div>
        </div>
    </div>

    <?php if ($disclaimer = getSetting('report_disclaimer', '')): ?>
        <hr>
        <p class="small text-muted mb-0"><?= nl2br(sanitize($disclaimer)) ?></p>
    <?php endif; ?>
</div>

<script src="<?= asset_url('assets/js/bootstrap.bundle.min.js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js') ?>"></script>
<script>
    if (!window.location.search.match(/[?&]noprint=1/)) {
        window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });
    }
</script>
</body>
</html>
