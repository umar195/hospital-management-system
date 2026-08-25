<?php
/** Database backup & restore (pure PHP, no mysqldump required). */
require_once __DIR__ . '/../../config/session.php';

if (!is_dir(BACKUP_PATH)) {
    @mkdir(BACKUP_PATH, 0777, true);
}

/** Split a SQL script into statements while respecting quoted strings and comments. */
function hmsSplitSql($sql)
{
    $statements = [];
    $buffer = '';
    $len = strlen($sql);
    $inString = false;
    $stringChar = '';
    for ($i = 0; $i < $len; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $len ? $sql[$i + 1] : '';

        if (!$inString) {
            if ($char === '-' && $next === '-') {
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
                continue;
            }
            if ($char === '#') {
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
                continue;
            }
            if ($char === '/' && $next === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $i = $end === false ? $len : $end + 1;
                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') {
                $inString = true;
                $stringChar = $char;
            } elseif ($char === ';') {
                if (trim($buffer) !== '') {
                    $statements[] = trim($buffer);
                }
                $buffer = '';
                continue;
            }
        } else {
            if ($char === '\\' && $stringChar !== '`') {
                $buffer .= $char . $next;
                $i++;
                continue;
            }
            if ($char === $stringChar) {
                $inString = false;
            }
        }
        $buffer .= $char;
    }
    if (trim($buffer) !== '') {
        $statements[] = trim($buffer);
    }
    return $statements;
}

/** Dump the whole database into an .sql file and return its path. */
function hmsCreateBackup(PDO $pdo)
{
    $tables = [];
    foreach ($pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_NUM) as $row) {
        $tables[] = $row[0];
    }

    $sql  = "-- " . APP_NAME . " database backup\n";
    $sql .= "-- Database: " . DB_NAME . "\n";
    $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n";
    $sql .= "SET NAMES utf8mb4;\n\n";

    foreach ($tables as $table) {
        $create = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM);
        $sql .= "-- Table structure for `{$table}`\n";
        $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $sql .= $create[1] . ";\n\n";

        $stmt = $pdo->query('SELECT * FROM `' . $table . '`');
        $rows = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($rows === 0) {
                $sql .= "-- Data for `{$table}`\n";
            }
            $columns = '`' . implode('`, `', array_keys($row)) . '`';
            $values = [];
            foreach ($row as $value) {
                if ($value === null) {
                    $values[] = 'NULL';
                } elseif (is_numeric($value) && !preg_match('/^0\d+/', (string)$value)) {
                    $values[] = $value;
                } else {
                    $values[] = $pdo->quote((string)$value);
                }
            }
            $sql .= "INSERT INTO `{$table}` ({$columns}) VALUES (" . implode(', ', $values) . ");\n";
            $rows++;
        }
        if ($rows > 0) {
            $sql .= "\n";
        }
    }

    $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

    $file = BACKUP_PATH . '/backup-' . date('Ymd-His') . '.sql';
    if (file_put_contents($file, $sql) === false) {
        throw new RuntimeException('Unable to write the backup file. Check permissions on uploads/backups.');
    }
    return $file;
}

/** Run a SQL script against the database. Returns number of executed statements. */
function hmsRestoreSql(PDO $pdo, $sql)
{
    $statements = hmsSplitSql($sql);
    if (!$statements) {
        throw new RuntimeException('The uploaded file does not contain any SQL statement.');
    }
    $executed = 0;
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($statements as $statement) {
        if (preg_match('/^\s*(CREATE\s+DATABASE|USE)\b/i', $statement)) {
            continue;
        }
        $pdo->exec($statement);
        $executed++;
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    return $executed;
}

$action = post('action', get('action'));

if ($action === 'download') {
    $name = basename((string)get('file'));
    $path = BACKUP_PATH . '/' . $name;
    if (preg_match('/^[A-Za-z0-9._-]+\.sql$/', $name) && is_file($path)) {
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
    flash('danger', 'Backup file not found.');
    redirect(BASE_URL . '/modules/backup/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf(BASE_URL . '/modules/backup/index.php');

    if ($action === 'create') {
        try {
            $file = hmsCreateBackup($pdo);
            logActivity('Backup created', basename($file), 'backups', null);
            flash('success', 'Backup created: ' . basename($file) . ' (' . number_format(filesize($file) / 1024, 1) . ' KB)');
        } catch (Throwable $e) {
            flash('danger', friendlyError($e, 'Backup failed. Please check the server error log.'));
        }
    } elseif ($action === 'delete') {
        $name = basename((string)post('file'));
        $path = BACKUP_PATH . '/' . $name;
        if (preg_match('/^[A-Za-z0-9._-]+\.sql$/', $name) && is_file($path) && @unlink($path)) {
            logActivity('Backup deleted', $name, 'backups', null);
            flash('success', 'Backup deleted.');
        } else {
            flash('danger', 'Could not delete that backup file.');
        }
    } elseif ($action === 'restore') {
        if (post('confirm') !== 'RESTORE') {
            flash('danger', 'Type RESTORE in the confirmation box to proceed.');
            redirect(BASE_URL . '/modules/backup/index.php');
        }
        try {
            $sql = null;
            $source = '';
            $name = basename((string)post('file'));
            if ($name !== '' && preg_match('/^[A-Za-z0-9._-]+\.sql$/', $name) && is_file(BACKUP_PATH . '/' . $name)) {
                $sql = file_get_contents(BACKUP_PATH . '/' . $name);
                $source = $name;
            } elseif (!empty($_FILES['sql_file']['tmp_name']) && is_uploaded_file($_FILES['sql_file']['tmp_name'])) {
                if (strtolower(pathinfo($_FILES['sql_file']['name'], PATHINFO_EXTENSION)) !== 'sql') {
                    throw new RuntimeException('Only .sql files can be restored.');
                }
                $sql = file_get_contents($_FILES['sql_file']['tmp_name']);
                $source = $_FILES['sql_file']['name'];
            }
            if ($sql === null || trim($sql) === '') {
                throw new RuntimeException('Choose a backup file to restore.');
            }
            $safety = hmsCreateBackup($pdo);
            $count = hmsRestoreSql($pdo, $sql);
            logActivity('Database restored', $source . ' (' . $count . ' statements)', 'backups', null);
            flash('success', 'Database restored from ' . sanitize($source) . '. ' . $count .
                ' statements executed. A safety backup was saved as ' . basename($safety) . '.');
        } catch (Throwable $e) {
            flash('danger', friendlyError($e, 'Restore failed. Please check the server error log.'));
        }
    }
    redirect(BASE_URL . '/modules/backup/index.php');
}

$files = [];
foreach (glob(BACKUP_PATH . '/*.sql') ?: [] as $path) {
    $files[] = ['name' => basename($path), 'size' => filesize($path), 'time' => filemtime($path)];
}
usort($files, function ($a, $b) {
    return $b['time'] <=> $a['time'];
});

$tableStats = [];
$totalRows = 0;
foreach ($pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_NUM) as $row) {
    $rows = (int)fetchValue('SELECT COUNT(*) FROM `' . $row[0] . '`');
    $tableStats[] = ['table' => $row[0], 'rows' => $rows];
    $totalRows += $rows;
}

$pageTitle = 'Backup & Restore';
$pageSubtitle = 'Protect your hospital data with regular database backups';
$activeMenu = 'backup';
require_once INC_PATH . '/header.php';
?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-hdd me-2"></i>Available backups (<?= count($files) ?>)</span>
                <form method="post" class="m-0">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="create">
                    <button class="btn btn-sm btn-primary"><i class="bi bi-download me-1"></i>Create backup now</button>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>File</th><th>Created</th><th>Size</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    <?php if (!$files): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">No backups yet. Click “Create backup now”.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($files as $f): ?>
                        <tr>
                            <td class="small"><i class="bi bi-file-earmark-code me-1 text-primary"></i><?= sanitize($f['name']) ?></td>
                            <td class="small"><?= formatDateTime(date('Y-m-d H:i:s', $f['time'])) ?></td>
                            <td class="small"><?= number_format($f['size'] / 1024, 1) ?> KB</td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="?action=download&file=<?= urlencode($f['name']) ?>"><i class="bi bi-download"></i></a>
                                <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#restoreModal"
                                        data-file="<?= sanitize($f['name']) ?>"><i class="bi bi-arrow-counterclockwise"></i></button>
                                <form method="post" class="d-inline" data-confirm="Delete <?= sanitize($f['name']) ?>?">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="file" value="<?= sanitize($f['name']) ?>">
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card border-danger">
            <div class="card-header bg-danger text-white fw-semibold"><i class="bi bi-exclamation-triangle me-2"></i>Restore from file</div>
            <div class="card-body">
                <p class="small text-danger mb-3">
                    <strong>Warning:</strong> restoring replaces every table in <code><?= sanitize(DB_NAME) ?></code>.
                    All data added after the backup was taken will be lost. A safety backup of the current data is created automatically before the restore runs.
                </p>
                <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="restore">
                    <div class="col-md-6">
                        <label class="form-label">Upload .sql file</label>
                        <input type="file" name="sql_file" class="form-control" accept=".sql" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Type RESTORE</label>
                        <input type="text" name="confirm" class="form-control" placeholder="RESTORE" required>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-danger w-100" data-confirm="This will overwrite the current database. Continue?">
                            <i class="bi bi-upload me-1"></i>Restore
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-2"></i>Database</div>
            <div class="card-body">
                <div class="d-flex justify-content-between small mb-1"><span>Name</span><strong><?= sanitize(DB_NAME) ?></strong></div>
                <div class="d-flex justify-content-between small mb-1"><span>Tables</span><strong><?= count($tableStats) ?></strong></div>
                <div class="d-flex justify-content-between small mb-1"><span>Total rows</span><strong><?= number_format($totalRows) ?></strong></div>
                <div class="d-flex justify-content-between small"><span>Backup folder</span><strong>uploads/backups</strong></div>
            </div>
        </div>
        <div class="card">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-table me-2"></i>Rows per table</div>
            <div class="table-responsive" style="max-height:420px;overflow:auto">
                <table class="table table-sm mb-0">
                    <tbody>
                    <?php foreach ($tableStats as $t): ?>
                        <tr><td class="small"><?= sanitize($t['table']) ?></td><td class="small text-end"><?= number_format($t['rows']) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="restoreModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="restore">
            <input type="hidden" name="file" id="restoreFile">
            <div class="modal-header">
                <h5 class="modal-title">Restore backup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small">You are about to restore <strong id="restoreFileName"></strong>. Every table will be dropped and recreated from that file.</p>
                <label class="form-label">Type <code>RESTORE</code> to confirm</label>
                <input type="text" name="confirm" class="form-control" placeholder="RESTORE" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-danger"><i class="bi bi-arrow-counterclockwise me-1"></i>Restore now</button>
            </div>
        </form>
    </div>
</div>

<?php
$pageScripts = <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('restoreModal');
    if (!modal) { return; }
    modal.addEventListener('show.bs.modal', function (event) {
        var file = event.relatedTarget ? event.relatedTarget.getAttribute('data-file') : '';
        document.getElementById('restoreFile').value = file || '';
        document.getElementById('restoreFileName').textContent = file || '';
    });
});
</script>
HTML;
require_once INC_PATH . '/footer.php';
