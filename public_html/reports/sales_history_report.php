<?php
// reports/sales_history_report.php — Reporting module (Manager and Admin): sales over a date range.
// Revenue is estimated at each product's CURRENT unit price (the schema stores no sale price).
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/report_helpers.php';

require_role(['Admin', 'Manager']);

const SALES_ROW_LIMIT = 500;   // rows shown on screen; the CSV always has every row

$dateFrom = report_clean_date((string)($_GET['date_from'] ?? ''), date('Y-m-01'));   // default: start of this month
$dateTo   = report_clean_date((string)($_GET['date_to'] ?? ''), date('Y-m-d'));
$swapped  = false;
if ($dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
    $swapped = true;
}
$range = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];

// Totals come from the database so they cover every sale in the range.
$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS Cnt, COALESCE(SUM(st.Quantity), 0) AS Units,
            COALESCE(SUM(st.Quantity * p.UnitPrice), 0) AS Revenue
     FROM Stock_Transaction st
     JOIN Product p ON st.ProductID = p.ProductID
     WHERE st.TransactionType = 'Sale' AND st.TransactionDate BETWEEN ? AND ?"
);
$stmt->execute($range);
$totals = $stmt->fetch();

$wantsCsv = report_wants_csv();
$stmt = $pdo->prepare(
    "SELECT st.*, p.Name AS ProductName, p.UnitPrice, u.FullName AS UserName
     FROM Stock_Transaction st
     JOIN Product p ON st.ProductID = p.ProductID
     JOIN User u ON st.UserID = u.UserID
     WHERE st.TransactionType = 'Sale' AND st.TransactionDate BETWEEN ? AND ?
     ORDER BY st.TransactionDate DESC, st.TransactionID DESC"
     . ($wantsCsv ? '' : ' LIMIT ' . SALES_ROW_LIMIT)
);
$stmt->execute($range);
$sales = $stmt->fetchAll();

if ($wantsCsv) {
    $rows = [];
    foreach ($sales as $s) {
        $rows[] = [$s['TransactionDate'], $s['ProductName'], (int)$s['Quantity'], (float)$s['UnitPrice'],
                   round((int)$s['Quantity'] * (float)$s['UnitPrice'], 2), $s['UserName']];
    }
    report_send_csv("sales_{$dateFrom}_to_{$dateTo}.csv",
        ['Date', 'Product', 'Qty Sold', 'Unit Price (current)', 'Line Total', 'Recorded By'], $rows);
}

$pageTitle = 'Sales History Report';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Reports</h1>
    <?php if ((int)$totals['Cnt'] > 0): ?>
        <a class="btn btn-secondary" href="<?= e(report_csv_url('reports/sales_history_report.php')) ?>">Download CSV</a>
    <?php endif; ?>
</div>

<?php report_tabs('sales'); ?>

<h2>Sales History</h2>

<?php if ($swapped): ?>
    <div class="alert alert-info">The start date was after the end date, so the two were swapped.</div>
<?php endif; ?>

<form class="search-bar" method="GET" action="<?= e(url('reports/sales_history_report.php')) ?>">
    <label class="check-label">From <input type="date" name="date_from" value="<?= e($dateFrom) ?>"></label>
    <label class="check-label">To <input type="date" name="date_to" value="<?= e($dateTo) ?>"></label>
    <button class="btn btn-secondary" type="submit">Apply</button>
</form>

<div class="card-grid">
    <div class="card"><h3><?= number_format((int)$totals['Cnt']) ?></h3><p>Sale Transactions</p></div>
    <div class="card"><h3><?= number_format((int)$totals['Units']) ?></h3><p>Units Sold</p></div>
    <div class="card"><h3>₱<?= number_format((float)$totals['Revenue'], 2) ?></h3><p>Estimated Revenue</p>
        <small class="text-muted">(at current unit price)</small>
    </div>
</div>

<?php if (count($sales) === 0): ?>
    <div class="empty-state">No sales recorded in this date range.</div>
<?php else: ?>
    <div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr><th>Date</th><th>Product</th><th>Qty Sold</th><th>Unit Price</th><th>Line Total</th><th>Recorded By</th></tr>
        </thead>
        <tbody>
            <?php foreach ($sales as $s): ?>
                <tr>
                    <td><?= e(date('M j, Y g:i A', strtotime($s['TransactionDate']))) ?></td>
                    <td><?= e($s['ProductName']) ?></td>
                    <td class="num"><?= (int)$s['Quantity'] ?></td>
                    <td class="num">₱<?= number_format((float)$s['UnitPrice'], 2) ?></td>
                    <td class="num">₱<?= number_format((int)$s['Quantity'] * (float)$s['UnitPrice'], 2) ?></td>
                    <td><?= e($s['UserName']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php if ((int)$totals['Cnt'] > SALES_ROW_LIMIT): ?>
        <p class="text-muted">Showing the most recent <?= SALES_ROW_LIMIT ?> of <?= number_format((int)$totals['Cnt']) ?> sales. Download the CSV for all of them.</p>
    <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
