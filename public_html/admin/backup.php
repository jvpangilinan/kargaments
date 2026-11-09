<?php
// admin/backup.php — System Admin module: download a full backup of the database as a .sql file.
// Pure PHP (PDO), so it works on shared hosting where mysqldump / exec() are usually disabled.
// To restore: phpMyAdmin > select the database > Import > choose the .sql file.
// The backup has no CREATE DATABASE / DROP DATABASE, so it is safe on Hostinger.
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/audit_log.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/csrf.php';

require_role(['Admin']);

/** Quotes a table name for use inside SQL. */
function km_ident(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

/** Writes one chunk to the browser. */
function km_out(string $text): void
{
    echo $text;
    if (ob_get_level() > 0) { @ob_flush(); }
    flush();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    @set_time_limit(300);
    @ini_set('memory_limit', '256M');

    // Work out base tables and views.
    $tables = [];
    $views  = [];
    foreach ($pdo->query("SHOW FULL TABLES")->fetchAll(PDO::FETCH_NUM) as $row) {
        if (strtoupper($row[1]) === 'VIEW') { $views[] = $row[0]; } else { $tables[] = $row[0]; }
    }

    log_action($pdo, (int)$_SESSION['user_id'], 'Downloaded database backup', 'Backup', 0);

    $filename = 'karga_ments_backup_' . date('Ymd_His') . '.sql';
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    while (ob_get_level() > 0) { @ob_end_clean(); }

    km_out("-- Karga-Ments database backup\n");
    km_out("-- Created: " . date('Y-m-d H:i:s') . " (Asia/Manila)\n");
    km_out("-- Restore: phpMyAdmin > choose your database > Import.\n\n");
    km_out("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");

    foreach ($tables as $table) {
        $create = $pdo->query('SHOW CREATE TABLE ' . km_ident($table))->fetch(PDO::FETCH_NUM);
        km_out("-- ----------------------------\n-- Table: $table\n-- ----------------------------\n");
        km_out('DROP TABLE IF EXISTS ' . km_ident($table) . ";\n" . $create[1] . ";\n\n");

        $offset = 0;
        $chunk  = 500;
        while (true) {
            $stmt = $pdo->query('SELECT * FROM ' . km_ident($table) . ' ORDER BY 1 LIMIT ' . $chunk . ' OFFSET ' . $offset);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) break;

            $cols = implode(', ', array_map('km_ident', array_keys($rows[0])));
            $values = [];
            foreach ($rows as $r) {
                $values[] = '(' . implode(', ', array_map(
                    fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v),
                    array_values($r)
                )) . ')';
            }
            km_out('INSERT INTO ' . km_ident($table) . " ($cols) VALUES\n" . implode(",\n", $values) . ";\n");

            $offset += $chunk;
            if (count($rows) < $chunk) break;
        }
        km_out("\n");
    }

    foreach ($views as $view) {
        $create = $pdo->query('SHOW CREATE VIEW ' . km_ident($view))->fetch(PDO::FETCH_NUM);
        // Drop the DEFINER so the view can be restored under a different database user.
        $sql = preg_replace('/\sDEFINER=`[^`]*`@`[^`]*`/i', '', $create[1]);
        $sql = preg_replace('/\sSQL SECURITY DEFINER/i', ' SQL SECURITY INVOKER', $sql);
        km_out("-- View: $view\nDROP VIEW IF EXISTS " . km_ident($view) . ";\n" . $sql . ";\n\n");
    }

    km_out("SET FOREIGN_KEY_CHECKS = 1;\n-- End of backup\n");
    exit;
}

// Page: table list with row counts, and the date of the last backup.
$tableInfo = [];
foreach ($pdo->query("SHOW FULL TABLES")->fetchAll(PDO::FETCH_NUM) as $row) {
    if (strtoupper($row[1]) !== 'VIEW') {
        $tableInfo[$row[0]] = (int)$pdo->query('SELECT COUNT(*) FROM ' . km_ident($row[0]))->fetchColumn();
    }
}
$last = $pdo->query("SELECT Timestamp FROM Audit_Log WHERE TargetTable = 'Backup' ORDER BY Timestamp DESC, LogID DESC LIMIT 1")->fetchColumn();

$pageTitle = 'Backup';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>System Backup</h1>
</div>

<div class="form-box" style="max-width:640px;">
    <p>Download a complete copy of the database (all tables and the low-stock view) as a single <code>.sql</code> file.
       Keep it somewhere safe, away from the web server.</p>
    <p class="text-muted">
        Last backup downloaded:
        <?= $last ? e(date('M j, Y g:i A', strtotime($last))) : 'never' ?>
    </p>
    <form method="POST" action="<?= e(url('admin/backup.php')) ?>">
        <?= csrf_field() ?>
        <div class="form-actions">
            <button class="btn" type="submit">Download Backup (.sql)</button>
        </div>
    </form>
    <p><small>To restore: open phpMyAdmin, pick the database, choose Import and select the file.
       Restoring replaces the current data in these tables.</small></p>
    <div class="alert alert-warning">The backup file contains password hashes and every record. Treat it as confidential.</div>
</div>

<h2 style="margin-top:24px;">What is included</h2>
<div class="table-wrap">
<table class="data-table" style="max-width:420px;">
    <thead><tr><th>Table</th><th>Rows</th></tr></thead>
    <tbody>
        <?php foreach ($tableInfo as $name => $count): ?>
            <tr><td><?= e($name) ?></td><td class="num"><?= number_format($count) ?></td></tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
