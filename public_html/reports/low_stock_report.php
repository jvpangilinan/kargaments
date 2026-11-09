<?php
// reports/low_stock_report.php — Reporting module (Manager and Admin): current snapshot.
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/report_helpers.php';

require_role(['Admin', 'Manager']);

$lowStock = $pdo->query(
    "SELECT p.*, c.CategoryName, s.Name AS SupplierName
     FROM Product p
     JOIN Category c ON p.CategoryID = c.CategoryID
     JOIN Supplier s ON p.SupplierID = s.SupplierID
     WHERE p.Quantity <= p.MinThreshold
     ORDER BY (p.MinThreshold - p.Quantity) DESC, p.Name"
)->fetchAll();

if (report_wants_csv()) {
    $rows = [];
    foreach ($lowStock as $p) {
        $rows[] = [$p['Name'], $p['CategoryName'], $p['SupplierName'], (int)$p['Quantity'], (int)$p['MinThreshold'],
                   max(0, (int)$p['MinThreshold'] - (int)$p['Quantity'])];
    }
    report_send_csv('low_stock_' . date('Y-m-d') . '.csv',
        ['Product', 'Category', 'Supplier', 'Current Qty', 'Min Threshold', 'Shortfall'], $rows);
}

$pageTitle = 'Low Stock Report';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Reports</h1>
    <?php if (count($lowStock) > 0): ?>
        <a class="btn btn-secondary" href="<?= e(report_csv_url('reports/low_stock_report.php')) ?>">Download CSV</a>
    <?php endif; ?>
</div>

<?php report_tabs('low'); ?>

<h2>Items at or below their low-stock level</h2>

<?php if (count($lowStock) === 0): ?>
    <div class="empty-state">All products are above their low-stock level.</div>
<?php else: ?>
    <div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr><th>Product</th><th class="km-extra">Category</th><th>Supplier</th><th>In stock</th><th class="km-extra">Low at</th><th class="km-extra">Short by</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($lowStock as $p): ?>
                <tr>
                    <td><?= e($p['Name']) ?></td>
                    <td class="km-extra"><?= e($p['CategoryName']) ?></td>
                    <td><?= e($p['SupplierName']) ?></td>
                    <td class="num low-stock"><?= (int)$p['Quantity'] ?></td>
                    <td class="num km-extra"><?= (int)$p['MinThreshold'] ?></td>
                    <td class="num km-extra"><?= max(0, (int)$p['MinThreshold'] - (int)$p['Quantity']) ?></td>
                    <td><a class="btn btn-sm btn-secondary" href="<?= e(url('purchase_orders/create_order.php?product_id=' . (int)$p['ProductID'])) ?>">Order</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
