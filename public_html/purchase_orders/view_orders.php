<?php
// purchase_orders/view_orders.php — Order Management module (Manager and Admin).
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/csrf.php';

require_role(['Admin', 'Manager']);

$status = in_array($_GET['status'] ?? '', ['Pending', 'Received', 'Cancelled'], true) ? $_GET['status'] : '';

$sql = "SELECT po.*, s.Name AS SupplierName, u.FullName AS CreatedBy,
               COUNT(poi.OrderItemID) AS ItemCount,
               COALESCE(SUM(poi.QuantityOrdered * poi.UnitPrice), 0) AS OrderTotal
        FROM Purchase_Order po
        JOIN Supplier s ON po.SupplierID = s.SupplierID
        JOIN User u ON po.UserID = u.UserID
        LEFT JOIN Purchase_Order_Item poi ON poi.OrderID = po.OrderID";
$params = [];
if ($status !== '') {
    $sql .= " WHERE po.Status = ?";
    $params[] = $status;
}
$sql .= " GROUP BY po.OrderID ORDER BY po.OrderDate DESC, po.OrderID DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$pageTitle = 'Orders from suppliers';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Orders from suppliers</h1>
    <a class="btn" href="<?= e(url('purchase_orders/create_order.php')) ?>">+ New order</a>
</div>

<div class="report-tabs">
    <a href="<?= e(url('purchase_orders/view_orders.php')) ?>" class="<?= $status === '' ? 'active' : '' ?>">All</a>
    <a href="<?= e(url('purchase_orders/view_orders.php?status=Pending')) ?>" class="<?= $status === 'Pending' ? 'active' : '' ?>">Waiting for delivery</a>
    <a href="<?= e(url('purchase_orders/view_orders.php?status=Received')) ?>" class="<?= $status === 'Received' ? 'active' : '' ?>">Delivered</a>
    <a href="<?= e(url('purchase_orders/view_orders.php?status=Cancelled')) ?>" class="<?= $status === 'Cancelled' ? 'active' : '' ?>">Cancelled</a>
</div>

<?php if (count($orders) === 0): ?>
    <div class="empty-state">No orders here yet.</div>
<?php else: ?>
    <div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr><th>Order #</th><th>Supplier</th><th class="km-extra">Date</th><th class="km-extra">Items</th><th>Total</th><th>Status</th><th class="km-extra">Made by</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $o):
                $badgeClass = match ($o['Status']) {
                    'Received'  => 'badge-received',
                    'Cancelled' => 'badge-cancelled',
                    default     => 'badge-pending',
                };
            ?>
                <tr data-status="<?= e($o['Status']) ?>">
                    <td>#<?= (int)$o['OrderID'] ?></td>
                    <td><?= e($o['SupplierName']) ?></td>
                    <td class="km-extra"><?= e(date('M j, Y', strtotime($o['OrderDate']))) ?></td>
                    <td class="num km-extra"><?= (int)$o['ItemCount'] ?></td>
                    <td class="num">₱<?= number_format((float)$o['OrderTotal'], 2) ?></td>
                    <td><span class="badge <?= $badgeClass ?>"><?= e(km_label($o['Status'], 'order')) ?></span></td>
                    <td class="km-extra"><?= e($o['CreatedBy']) ?></td>
                    <td><a class="btn btn-sm btn-secondary" data-km="view-order-<?= (int)$o['OrderID'] ?>" href="<?= e(url('purchase_orders/order_detail.php?id=' . (int)$o['OrderID'])) ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
