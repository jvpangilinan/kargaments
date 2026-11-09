<?php
// reports/stock_levels_report.php — Reporting module (Manager and Admin): current snapshot.
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/report_helpers.php';

require_role(['Admin', 'Manager']);

$categoryId = ctype_digit((string)($_GET['category_id'] ?? '')) ? (int)$_GET['category_id'] : 0;

$sql = "SELECT p.*, c.CategoryName, s.Name AS SupplierName
        FROM Product p
        JOIN Category c ON p.CategoryID = c.CategoryID
        JOIN Supplier s ON p.SupplierID = s.SupplierID";
$params = [];
if ($categoryId > 0) {
    $sql .= " WHERE p.CategoryID = ?";
    $params[] = $categoryId;
}
$sql .= " ORDER BY p.Name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = $pdo->query("SELECT * FROM Category ORDER BY CategoryName")->fetchAll();

$totalUnits = 0;
$totalValue = 0.0;
foreach ($products as $p) {
    $totalUnits += (int)$p['Quantity'];
    $totalValue += (int)$p['Quantity'] * (float)$p['UnitPrice'];
}

if (report_wants_csv()) {
    $rows = [];
    foreach ($products as $p) {
        $rows[] = [$p['Name'], $p['CategoryName'], $p['SupplierName'], (int)$p['Quantity'],
                   (float)$p['UnitPrice'], round((int)$p['Quantity'] * (float)$p['UnitPrice'], 2)];
    }
    report_send_csv('stock_levels_' . date('Y-m-d') . '.csv',
        ['Product', 'Category', 'Supplier', 'Qty', 'Unit Price', 'Value'], $rows);
}

$pageTitle = 'Stock Levels Report';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Reports</h1>
    <?php if (count($products) > 0): ?>
        <a class="btn btn-secondary" href="<?= e(report_csv_url('reports/stock_levels_report.php')) ?>">Download CSV</a>
    <?php endif; ?>
</div>

<?php report_tabs('levels'); ?>

<h2>Current Stock Levels</h2>

<form class="search-bar" method="GET" action="<?= e(url('reports/stock_levels_report.php')) ?>">
    <select name="category_id">
        <option value="">All Categories</option>
        <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['CategoryID'] ?>" <?= $categoryId === (int)$c['CategoryID'] ? 'selected' : '' ?>>
                <?= e($c['CategoryName']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-secondary" type="submit">Filter</button>
    <a class="btn btn-secondary" href="<?= e(url('reports/stock_levels_report.php')) ?>">Reset</a>
</form>

<div class="card-grid">
    <div class="card"><h3><?= count($products) ?></h3><p>Products Shown</p></div>
    <div class="card"><h3><?= number_format($totalUnits) ?></h3><p>Total Units in Stock</p></div>
    <div class="card"><h3>₱<?= number_format($totalValue, 2) ?></h3><p>Total Inventory Value</p></div>
</div>

<?php if (count($products) === 0): ?>
    <div class="empty-state">No products found.</div>
<?php else: ?>
    <div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr><th>Product</th><th>Category</th><th>Supplier</th><th>Qty</th><th>Unit Price</th><th>Value</th></tr>
        </thead>
        <tbody>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td><?= e($p['Name']) ?></td>
                    <td><?= e($p['CategoryName']) ?></td>
                    <td><?= e($p['SupplierName']) ?></td>
                    <td class="num <?= (int)$p['Quantity'] <= (int)$p['MinThreshold'] ? 'low-stock' : '' ?>"><?= (int)$p['Quantity'] ?></td>
                    <td class="num">₱<?= number_format((float)$p['UnitPrice'], 2) ?></td>
                    <td class="num">₱<?= number_format((int)$p['Quantity'] * (float)$p['UnitPrice'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
