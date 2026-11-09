<?php
// index.php — Dashboard: summary cards, low-stock alerts and stock charts.
// Charts are drawn with plain HTML/CSS on the server, so they work on any hosting
// with no JavaScript library and no internet connection.
require_once __DIR__ . '/includes/auth_check.php';

// Suppliers don't get the internal inventory dashboard — send them to their own portal.
if (($_SESSION['role'] ?? '') === 'Supplier') {
    header('Location: ' . url('supplier_portal/dashboard.php'));
    exit;
}

$isManager = has_role(['Admin', 'Manager']);

$lowStock      = $pdo->query("SELECT * FROM vw_low_stock_products ORDER BY (MinThreshold - Quantity) DESC, Name")->fetchAll();
$productCount  = (int)$pdo->query("SELECT COUNT(*) FROM Product")->fetchColumn();
$supplierCount = (int)$pdo->query("SELECT COUNT(*) FROM Supplier")->fetchColumn();
$pendingOrders = $isManager ? (int)$pdo->query("SELECT COUNT(*) FROM Purchase_Order WHERE Status = 'Pending'")->fetchColumn() : 0;
$pendingSubs   = $isManager ? (int)$pdo->query("SELECT COUNT(*) FROM Supplier_Submission WHERE Status = 'Pending'")->fetchColumn() : 0;

// Chart 1: units in stock per category.
$byCategory = $pdo->query(
    "SELECT c.CategoryName, COALESCE(SUM(p.Quantity), 0) AS Units
     FROM Category c
     LEFT JOIN Product p ON p.CategoryID = c.CategoryID
     GROUP BY c.CategoryID, c.CategoryName
     ORDER BY Units DESC, c.CategoryName"
)->fetchAll();
$maxCategoryUnits = 0;
foreach ($byCategory as $row) { $maxCategoryUnits = max($maxCategoryUnits, (int)$row['Units']); }

// Chart 2: units sold per day over the last 14 days (days with no sales show as 0).
$days = [];
for ($i = 13; $i >= 0; $i--) {
    $days[date('Y-m-d', strtotime("-$i days"))] = 0;
}
$stmt = $pdo->prepare(
    "SELECT DATE(TransactionDate) AS Day, SUM(Quantity) AS Units
     FROM Stock_Transaction
     WHERE TransactionType = 'Sale' AND TransactionDate >= ?
     GROUP BY DATE(TransactionDate)"
);
$stmt->execute([array_key_first($days) . ' 00:00:00']);
foreach ($stmt->fetchAll() as $row) {
    if (isset($days[$row['Day']])) $days[$row['Day']] = (int)$row['Units'];
}
$maxDayUnits = max($days) ?: 0;
$soldTotal   = array_sum($days);

// Numbers for the big "what to do" tiles (same numbers Guided mode uses).
require_once __DIR__ . '/includes/guide_state.php';
$home = km_guide_state($pdo, $_SESSION['role'], null);
$role = $_SESSION['role'];
$orderUrl = !empty($home['firstLowNoOrder'])
    ? 'purchase_orders/create_order.php?product_id=' . (int)$home['firstLowNoOrder']
    : 'purchase_orders/create_order.php';
$roleTitle = ['Admin' => 'Administrator', 'Manager' => 'Store Manager', 'Employee' => 'Store Employee'][$role] ?? $role;

// One tile: [data-km name, icon, title, subtitle, link, count or null, style]
$tiles = [];
if ($role === 'Employee') {
    $tiles[] = ['tile-sale', 'cart', 'Record a sale', 'Items sold to a customer', 'stock/add_transaction.php?type=Sale', null, 'main'];
    $tiles[] = ['tile-receive', 'inbox', 'Record a delivery', 'Items received from a supplier', 'stock/add_transaction.php?type=Stock-In', null, ''];
    $tiles[] = ['tile-products', 'search', 'Find a product', 'Price and quantity on hand', 'products/list.php', null, ''];
    $tiles[] = ['tile-low', 'alert', 'Low-stock items', 'Report these to your manager', 'products/list.php?low=1', $home['low'] ?? 0, ($home['low'] ?? 0) ? 'alert' : ''];
} else {
    $tiles[] = ['tile-order', 'receipt', 'Reorder low-stock items', ($home['lowNoOrder'] ?? 0) ? 'Low stock with no open order' : 'Create a new order', $orderUrl, $home['lowNoOrder'] ?? 0, 'main'];
    $tiles[] = ['tile-orders', 'truck', 'Orders awaiting delivery', 'Mark as delivered when received', 'purchase_orders/view_orders.php?status=Pending', $home['pendingPO'] ?? 0, ''];
    $tiles[] = ['tile-review', 'send', 'Supplier updates', 'Offers awaiting your review', 'suppliers/review_submissions.php', $home['pendingSubs'] ?? 0, ($home['pendingSubs'] ?? 0) ? 'alert' : ''];
    $tiles[] = ['tile-sale', 'cart', 'Record a sale or delivery', 'Update stock quantities', 'stock/add_transaction.php?type=Sale', null, ''];
    $tiles[] = ['tile-reports', 'chart', 'Reports', 'Low stock, stock value, sales', 'reports/low_stock_report.php', null, ''];
    if ($role === 'Admin') {
        $tiles[] = ['tile-users', 'users', 'User accounts', 'Add staff, reset passwords', 'users/list.php', null, ''];
        $tiles[] = ['tile-backup', 'save', 'Backup', 'Download a copy of all data', 'admin/backup.php', null, ''];
    }
}
$roleLine = [
    'Admin'    => 'Manage inventory, orders, user accounts and backups.',
    'Manager'  => 'Monitor stock levels, place orders and review supplier offers.',
    'Employee' => 'Record sales and deliveries as they happen.',
][$role] ?? '';

$pageTitle = 'Home';
require_once __DIR__ . '/includes/header.php';
?>

<div class="km-home-hero">
    <div>
        <p class="km-eyebrow">Dashboard · <?= e($roleTitle) ?></p>
        <h1>Welcome, <?= e($_SESSION['full_name'] ?? '') ?></h1>
        <p><?= e($roleLine) ?></p>
    </div>
    <div class="km-hero-date"><?= e(date('l')) ?><strong><?= e(date('F j, Y')) ?></strong></div>
</div>

<h2 class="km-section">Tasks</h2>

<div class="km-tiles">
    <?php foreach ($tiles as $t): ?>
        <a class="km-tile <?= $t[6] ? 'km-tile-' . $t[6] : '' ?>" href="<?= e(url($t[4])) ?>" data-km="<?= e($t[0]) ?>">
            <span class="km-tile-ico"><?= km_icon($t[1]) ?></span>
            <span><strong><?= e($t[2]) ?></strong><span class="km-tile-sub"><?= e($t[3]) ?></span></span>
            <?php if ($t[5] !== null): ?><span class="km-tile-count" aria-label="<?= (int)$t[5] ?> to do"><?= (int)$t[5] ?></span><?php endif; ?>
        </a>
    <?php endforeach; ?>
</div>

<h2 class="km-section km-extra">Summary</h2>
<div class="card-grid km-extra">
    <div class="card"><h3><?= $productCount ?></h3><p>Products</p></div>
    <div class="card"><h3><?= $supplierCount ?></h3><p>Suppliers</p></div>
    <?php if ($isManager): ?>
        <div class="card"><h3><?= $pendingOrders ?></h3><p><a href="<?= e(url('purchase_orders/view_orders.php?status=Pending')) ?>">Orders waiting for delivery</a></p></div>
        <div class="card"><h3><?= $pendingSubs ?></h3><p><a href="<?= e(url('suppliers/review_submissions.php')) ?>">Supplier updates to review</a></p></div>
    <?php endif; ?>
    <div class="card <?= count($lowStock) > 0 ? 'card-alert' : '' ?>"><h3><?= count($lowStock) ?></h3><p><a href="<?= e(url('products/list.php?low=1')) ?>">Items running low</a></p></div>
</div>

<div class="chart-grid">
    <div class="chart-card">
        <h2>Units in Stock by Category</h2>
        <?php if (count($byCategory) === 0): ?>
            <div class="empty-state">No categories yet.</div>
        <?php else: ?>
            <?php foreach ($byCategory as $row):
                $units = (int)$row['Units'];
                $pct   = $maxCategoryUnits > 0 ? max($units > 0 ? 2 : 0, (int)round($units / $maxCategoryUnits * 100)) : 0;
            ?>
                <div class="hbar-row">
                    <span class="hbar-label" title="<?= e($row['CategoryName']) ?>"><?= e($row['CategoryName']) ?></span>
                    <span class="hbar-track"><span class="hbar-fill" style="width:<?= $pct ?>%"></span></span>
                    <span class="hbar-value"><?= number_format($units) ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="chart-card">
        <h2>Units Sold, Last 14 Days</h2>
        <?php if ($soldTotal === 0): ?>
            <div class="empty-state">No sales recorded in the last 14 days.</div>
        <?php else: ?>
            <div class="vchart">
                <?php foreach ($days as $day => $units):
                    $pct = $maxDayUnits > 0 ? max($units > 0 ? 3 : 0, (int)round($units / $maxDayUnits * 100)) : 0;
                ?>
                    <div class="vcol" title="<?= e(date('M j', strtotime($day))) ?>: <?= $units ?> units">
                        <span class="vvalue"><?= $units > 0 ? $units : '' ?></span>
                        <span class="vbar" style="height:<?= $pct ?>%"></span>
                        <span class="vlabel"><?= e(date('j', strtotime($day))) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="text-muted"><?= number_format($soldTotal) ?> units sold. Day of month shown under each bar.</p>
        <?php endif; ?>
    </div>
</div>

<?php if (count($lowStock) > 0): ?>
    <h2 class="km-section">Items at or below their low-stock level</h2>
    <div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr><th>Product</th><th>In stock</th><th class="km-extra">Low at</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($lowStock as $item): ?>
                <tr>
                    <td><?= e($item['Name']) ?></td>
                    <td class="num low-stock"><?= (int)$item['Quantity'] ?></td>
                    <td class="num km-extra"><?= (int)$item['MinThreshold'] ?></td>
                    <td>
                        <?php if ($isManager): ?>
                            <a class="btn btn-sm btn-secondary" data-km="order-for-<?= (int)$item['ProductID'] ?>" href="<?= e(url('purchase_orders/create_order.php?product_id=' . (int)$item['ProductID'])) ?>">Order</a>
                        <?php else: ?>
                            <a class="btn btn-sm btn-secondary" href="<?= e(url('stock/add_transaction.php?type=Stock-In&product_id=' . (int)$item['ProductID'])) ?>">Record delivery</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php else: ?>
    <p>No items are running low.</p>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
