<?php
/**
 * One-click database upgrade (schema v1 -> v2).
 *
 * Adds the doctor consultation / queue / follow-up workflow columns and the
 * follow_ups table. All existing records are preserved - the upgrade only
 * adds new columns and tables (see includes/migrations.php).
 */
define('SKIP_UPGRADE_CHECK', 1);
require_once __DIR__ . '/config/session.php';
require_once INC_PATH . '/migrations.php';

$log = [];
$error = '';
$done = (int)getSetting('schema_version', 1) >= SCHEMA_VERSION;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$done) {
    requireCsrf(BASE_URL . '/upgrade.php');
    try {
        $log = runSchemaUpgrades($pdo);
        setSetting('schema_version', (string)SCHEMA_VERSION);
        logActivity('Database upgraded', 'Schema upgraded to v' . SCHEMA_VERSION);
        $done = true;
        if (!$log) {
            $log[] = 'Schema was already up to date - version marker updated.';
        }
    } catch (Throwable $e) {
        $error = friendlyError($e, 'The upgrade could not be completed. Please restore your backup and try again.');
    }
}

$pageTitle = 'Database Upgrade';
$pageSubtitle = 'Enable the consultation, queue and follow-up workflow';
require_once INC_PATH . '/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header fw-semibold"><i class="bi bi-database-up me-2"></i>Schema upgrade to version <?= (int)SCHEMA_VERSION ?></div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="bi bi-exclamation-octagon me-2"></i><?= sanitize($error) ?></div>
                <?php endif; ?>

                <?php if ($done): ?>
                    <div class="alert alert-success mb-3"><i class="bi bi-check-circle me-2"></i>Your database is up to date. All existing data has been preserved.</div>
                    <?php if ($log): ?>
                        <h6 class="text-uppercase text-muted small fw-bold">Changes applied</h6>
                        <ul class="small mb-3">
                            <?php foreach ($log as $line): ?><li><?= sanitize($line) ?></li><?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-primary"><i class="bi bi-speedometer2 me-1"></i>Go to dashboard</a>
                <?php else: ?>
                    <p>This update adds the <strong>doctor consultation</strong>, <strong>waiting queue</strong> and
                        <strong>follow-up</strong> workflow to your hospital system. The upgrade:</p>
                    <ul class="small">
                        <li>Adds new columns to <code>appointments</code>, <code>visits</code>, <code>doctors</code>, <code>prescriptions</code> and <code>test_orders</code></li>
                        <li>Creates the new <code>follow_ups</code> table</li>
                        <li><strong>Never deletes or modifies existing records</strong></li>
                    </ul>
                    <div class="alert alert-warning small"><i class="bi bi-exclamation-triangle me-2"></i>
                        We still recommend taking a <a href="<?= BASE_URL ?>/modules/backup/index.php">database backup</a> before upgrading.
                    </div>
                    <form method="post">
                        <?= csrfField() ?>
                        <button class="btn btn-primary"><i class="bi bi-database-up me-1"></i>Run upgrade now</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once INC_PATH . '/footer.php'; ?>
