<?php
// purchase_orders/order_detail.php — view items and update order status (Manager and Admin).
// Allowed changes: Pending -> Received (books Stock-In for every line) or Pending -> Cancelled.
// Received and Cancelled orders are final, so stock can never be booked twice.
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/audit_log.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/csrf.php';

require_role(['Admin', 'Manager']);

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT po.*, s.Name AS SupplierName, s.ContactInfo, u.FullName AS CreatedBy
     FROM Purchase_Order po
     JOIN Supplier s ON po.SupplierID = s.SupplierID
     JOIN User u ON po.UserID = u.UserID
     WHERE po.OrderID = ?"
);
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    set_flash('error', 'Purchase order not found.');
    header('Location: ' . url('purchase_orders/view_orders.php'));
    exit;
}

// Handle status change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $newStatus = (string)($_POST['status'] ?? '');

    if (!in_array($newStatus, ['Received', 'Cancelled'], true)) {
        set_flash('error', 'Invalid status.');
        header('Location: ' . url('purchase_orders/order_detail.php?id=' . $id));
        exit;
    }

    $pdo->beginTransaction();
    try {
        // Only a Pending order can change; if someone else got there first, nothing is updated.
        $stmt = $pdo->prepare("UPDATE Purchase_Order SET Status = ? WHERE OrderID = ? AND Status = 'Pending'");
        $stmt->execute([$newStatus, $id]);

        if ($stmt->rowCount() !== 1) {
            $pdo->rollBack();
            set_flash('warning', "Order #$id was already updated by someone else, so nothing changed.");
        } else {
            // Marking an order Received books each line item into inventory as a Stock-In.
            if ($newStatus === 'Received') {
                $itemStmt = $pdo->prepare("SELECT ProductID, QuantityOrdered FROM Purchase_Order_Item WHERE OrderID = ?");
                $itemStmt->execute([$id]);
                $lines = $itemStmt->fetchAll();

                $insertTxn = $pdo->prepare(
                    "INSERT INTO Stock_Transaction (ProductID, UserID, TransactionType, Quantity) VALUES (?, ?, 'Stock-In', ?)"
                );
                $updateQty = $pdo->prepare("UPDATE Product SET Quantity = Quantity + ? WHERE ProductID = ?");

                foreach ($lines as $line) {
                    $insertTxn->execute([(int)$line['ProductID'], (int)$_SESSION['user_id'], (int)$line['QuantityOrdered']]);
                    $updateQty->execute([(int)$line['QuantityOrdered'], (int)$line['ProductID']]);
                }
            }
            $pdo->commit();

            log_action($pdo, (int)$_SESSION['user_id'], "Marked purchase order #$id as $newStatus", 'Purchase_Order', $id);
            set_flash('success', "Order #$id marked as $newStatus.");
        }
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Purchase order status change failed: ' . $ex->getMessage());
        set_flash('error', 'Could not update the order status. Please try again.');
    }
    header('Location: ' . url('purchase_orders/order_detail.php?id=' . $id));
    exit;
}

$itemStmt = $pdo->prepare(
    "SELECT poi.*, p.Name AS ProductName
     FROM Purchase_Order_Item poi
     JOIN Product p ON poi.ProductID = p.ProductID
     WHERE poi.OrderID = ?
     ORDER BY p.Name"
);
$itemStmt->execute([$id]);
$items = $itemStmt->fetchAll();
$total = 0.0;
foreach ($items as $i) {
    $total += (int)$i['QuantityOrdered'] * (float)$i['UnitPrice'];
}

$badgeClass = match ($order['Status']) {
    'Received'  => 'badge-received',
    'Cancelled' => 'badge-cancelled',
    default     => 'badge-pending',
};

$pageTitle = 'Order #' . (int)$order['OrderID'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Order #<?= (int)$order['OrderID'] ?></h1>
    <a class="btn btn-secondary" href="<?= e(url('purchase_orders/view_orders.php')) ?>">&larr; Back to Orders</a>
</div>

<div class="card" style="text-align:left; margin-bottom:20px;">
    <p><strong>Supplier:</strong> <?= e($order['SupplierName']) ?>
        <?php if ($order['ContactInfo']): ?> (<?= e($order['ContactInfo']) ?>)<?php endif; ?></p>
    <p><strong>Order Date:</strong> <?= e(date('M j, Y g:i A', strtotime($order['OrderDate']))) ?></p>
    <p class="km-extra"><strong>Made by:</strong> <?= e($order['CreatedBy']) ?></p>
    <p><strong>Status:</strong> <span class="badge <?= $badgeClass ?>"><?= e(km_label($order['Status'], 'order')) ?></span></p>
</div>

<div class="table-wrap">
<table class="data-table order-items-table">
    <thead>
        <tr><th>Product</th><th>How many</th><th>Price each</th><th>Subtotal</th></tr>
    </thead>
    <tbody>
        <?php foreach ($items as $i): ?>
            <tr>
                <td><?= e($i['ProductName']) ?></td>
                <td class="num"><?= (int)$i['QuantityOrdered'] ?></td>
                <td class="num">₱<?= number_format((float)$i['UnitPrice'], 2) ?></td>
                <td class="num">₱<?= number_format((int)$i['QuantityOrdered'] * (float)$i['UnitPrice'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
        <tr class="total-row">
            <td colspan="3">Order Total</td>
            <td class="num">₱<?= number_format($total, 2) ?></td>
        </tr>
    </tbody>
</table>
</div>

<?php if ($order['Status'] === 'Pending'): ?>
    <div class="form-box" style="margin-top:20px;">
        <h3>Did the delivery arrive?</h3>
        <form method="POST" action="<?= e(url('purchase_orders/order_detail.php?id=' . $id)) ?>">
            <?= csrf_field() ?>
            <div class="form-actions">
                <button class="btn" type="submit" name="status" value="Received" data-km="receive"
                        data-confirm-title="Mark as delivered?" data-confirm="All items in this order (total ₱<?= number_format($total, 2) ?>) will be added to stock." data-confirm-warn="This cannot be undone." data-confirm-yes="Yes, it all arrived">Mark as delivered</button>
                <button class="btn btn-danger" type="submit" name="status" value="Cancelled" data-km="cancel-order"
                        data-confirm-title="Cancel this order?" data-confirm="Order #<?= (int)$order['OrderID'] ?> will be cancelled. Stock does not change." data-confirm-warn="This cannot be undone." data-confirm-yes="Yes, cancel the order" data-confirm-no="No, keep it">Cancel order</button>
            </div>
        </form>
        <small>“Mark as delivered” adds every item to stock for you.</small>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
