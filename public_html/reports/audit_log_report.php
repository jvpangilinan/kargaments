<?php
// reports/audit_log_report.php — Audit Log / Activity History module (Admin only).
// Stores who did what, when and to which record (no old/new values, by design).
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/report_helpers.php';

require_role(['Admin']);

const AUDIT_PER_PAGE = 100;

$userId   = ctype_digit((string)($_GET['user_id'] ?? '')) ? (int)$_GET['user_id'] : 0;
$table    = (string)($_GET['table'] ?? '');
$dateFrom = report_clean_date((string)($_GET['date_from'] ?? ''));
$dateTo   = report_clean_date((string)($_GET['date_to'] ?? ''));
$page     = max(1, (int)($_GET['page'] ?? 1));

$tables = $pdo->query("SELECT DISTINCT TargetTable FROM Audit_Log ORDER BY TargetTable")->fetchAll(PDO::FETCH_COLUMN);
if ($table !== '' && !in_array($table, $tables, true)) {
    $table = '';
}
$users = $pdo->query("SELECT UserID, FullName FROM User ORDER BY FullName")->fetchAll();

$where  = [];
$params = [];
if ($userId > 0)      { $where[] = "al.UserID = ?";      $params[] = $userId; }
if ($table !== '')    { $where[] = "al.TargetTable = ?"; $params[] = $table; }
if ($dateFrom !== '') { $where[] = "al.Timestamp >= ?";  $params[] = $dateFrom . ' 00:00:00'; }
if ($dateTo !== '')   { $where[] = "al.Timestamp <= ?";  $params[] = $dateTo . ' 23:59:59'; }
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$baseSql = "SELECT al.*, u.FullName, u.Username
            FROM Audit_Log al
            JOIN User u ON al.UserID = u.UserID" . $whereSql .
           " ORDER BY al.Timestamp DESC, al.LogID DESC";

if (report_wants_csv()) {
    $stmt = $pdo->prepare($baseSql);
    $stmt->execute($params);
    $rows = [];
    foreach ($stmt->fetchAll() as $l) {
        $rows[] = [$l['Timestamp'], $l['FullName'], $l['Username'], $l['Action'], $l['TargetTable'], (int)$l['TargetID']];
    }
    report_send_csv('audit_log_' . date('Y-m-d') . '.csv',
        ['Timestamp', 'User', 'Username', 'Action', 'Target Table', 'Target ID'], $rows);
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM Audit_Log al" . $whereSql);
$countStmt->execute($params);
$totalRows  = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / AUDIT_PER_PAGE));
$page       = min($page, $totalPages);

$stmt = $pdo->prepare($baseSql . " LIMIT " . AUDIT_PER_PAGE . " OFFSET " . (($page - 1) * AUDIT_PER_PAGE));
$stmt->execute($params);
$logs = $stmt->fetchAll();

function audit_page_url(int $p): string
{
    $q = $_GET;
    $q['page'] = $p;
    return url('reports/audit_log_report.php') . '?' . http_build_query($q);
}

$pageTitle = 'Audit Log';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Audit Log</h1>
    <?php if ($totalRows > 0): ?>
        <a class="btn btn-secondary" href="<?= e(report_csv_url('reports/audit_log_report.php')) ?>">Download CSV</a>
    <?php endif; ?>
</div>

<form class="search-bar" method="GET" action="<?= e(url('reports/audit_log_report.php')) ?>">
    <select name="user_id">
        <option value="">All Users</option>
        <?php foreach ($users as $u): ?>
            <option value="<?= (int)$u['UserID'] ?>" <?= $userId === (int)$u['UserID'] ? 'selected' : '' ?>>
                <?= e($u['FullName']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <select name="table">
        <option value="">All Tables</option>
        <?php foreach ($tables as $t): ?>
            <option value="<?= e($t) ?>" <?= $table === $t ? 'selected' : '' ?>><?= e($t) ?></option>
        <?php endforeach; ?>
    </select>
    <label class="check-label">From <input type="date" name="date_from" value="<?= e($dateFrom) ?>"></label>
    <label class="check-label">To <input type="date" name="date_to" value="<?= e($dateTo) ?>"></label>
    <button class="btn btn-secondary" type="submit">Filter</button>
    <a class="btn btn-secondary" href="<?= e(url('reports/audit_log_report.php')) ?>">Reset</a>
</form>

<p class="text-muted"><?= number_format($totalRows) ?> entr<?= $totalRows === 1 ? 'y' : 'ies' ?>.</p>

<?php if (count($logs) === 0): ?>
    <div class="empty-state">No audit log entries found.</div>
<?php else: ?>
    <div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr><th>Timestamp</th><th>User</th><th>Action</th><th>Target</th></tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $l): ?>
                <tr>
                    <td><?= e(date('M j, Y g:i A', strtotime($l['Timestamp']))) ?></td>
                    <td><?= e($l['FullName']) ?> (<?= e($l['Username']) ?>)</td>
                    <td><?= e($l['Action']) ?></td>
                    <td><?= e($l['TargetTable']) ?> #<?= (int)$l['TargetID'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="pager">
            <?php if ($page > 1): ?><a class="btn btn-sm btn-secondary" href="<?= e(audit_page_url($page - 1)) ?>">&larr; Newer</a><?php endif; ?>
            <span class="text-muted">Page <?= $page ?> of <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?><a class="btn btn-sm btn-secondary" href="<?= e(audit_page_url($page + 1)) ?>">Older &rarr;</a><?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
