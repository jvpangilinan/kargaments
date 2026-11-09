<?php
// supplier_portal/submit.php — a Supplier submits product availability and delivery info
// for a Manager/Admin to review. A supplier can only submit for its own products.
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/audit_log.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/csrf.php';

require_role(['Supplier']);

const SUB_MAX_QTY   = 1000000;
const SUB_MAX_PRICE = 99999999.99;

$supplierId = $_SESSION['supplier_id'] ?? null;

if (!$supplierId) {
    set_flash('error', 'Your account is not linked to a supplier record yet. Please contact an administrator.');
    header('Location: ' . url('supplier_portal/dashboard.php'));
    exit;
}

$stmt = $pdo->prepare("SELECT ProductID, Name FROM Product WHERE SupplierID = ? ORDER BY Name");
$stmt->execute([$supplierId]);
$products = $stmt->fetchAll();

$errors = [];
$productId = $quantity = $unitPrice = $deliveryDate = $notes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $productId    = (string)($_POST['product_id'] ?? '');
    $quantity     = trim((string)($_POST['quantity'] ?? ''));
    $unitPrice    = trim((string)($_POST['unit_price'] ?? ''));
    $deliveryDate = trim((string)($_POST['delivery_date'] ?? ''));
    $notes        = trim((string)($_POST['notes'] ?? ''));

    // The chosen product must belong to this supplier.
    $product = null;
    foreach ($products as $p) {
        if ((string)$p['ProductID'] === $productId) { $product = $p; break; }
    }
    if ($product === null) $errors[] = 'Please select one of your own products.';

    $qtyInt = filter_var($quantity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => SUB_MAX_QTY]]);
    if ($qtyInt === false) $errors[] = 'Available quantity must be a whole number of at least 1.';

    if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $unitPrice) || (float)$unitPrice > SUB_MAX_PRICE) {
        $errors[] = 'Unit price must be a number with at most 2 decimals.';
    }

    if ($deliveryDate !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $deliveryDate);
        if (!$d || $d->format('Y-m-d') !== $deliveryDate) {
            $errors[] = 'Delivery date is not a valid date.';
        } elseif ($deliveryDate < date('Y-m-d')) {
            $errors[] = 'Delivery date cannot be in the past.';
        }
    }
    if (mb_strlen($notes) > 500) $errors[] = 'Delivery notes are too long (500 characters maximum).';

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            "INSERT INTO Supplier_Submission (SupplierID, ProductID, AvailableQuantity, UnitPrice, DeliveryDate, DeliveryNotes, Status)
             VALUES (?, ?, ?, ?, ?, ?, 'Pending')"
        );
        $stmt->execute([
            (int)$supplierId, (int)$productId, $qtyInt, $unitPrice,
            $deliveryDate !== '' ? $deliveryDate : null,
            $notes !== '' ? $notes : null,
        ]);
        $newId = (int)$pdo->lastInsertId();

        log_action($pdo, (int)$_SESSION['user_id'], "Submitted availability update for '{$product['Name']}'", 'Supplier_Submission', $newId);

        set_flash('success', 'Sent! The store will review your update.');
        header('Location: ' . url('supplier_portal/dashboard.php'));
        exit;
    }
}

$pageTitle = 'Submit Update';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Submit Availability / Delivery Update</h1>
    <a class="btn btn-secondary" href="<?= e(url('supplier_portal/dashboard.php')) ?>">&larr; Back to Dashboard</a>
</div>

<?php foreach ($errors as $msg): ?>
    <div class="alert alert-error"><?= e($msg) ?></div>
<?php endforeach; ?>

<?php if (count($products) === 0): ?>
    <div class="empty-state">No products are linked to your company yet. Contact a manager to get set up before submitting updates.</div>
<?php else: ?>
    <form class="form-box" method="POST" action="<?= e(url('supplier_portal/submit.php')) ?>" data-confirm-builder="submission">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="product_id">Product</label>
            <select id="product_id" name="product_id" required>
                <option value="">-- Select Product --</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= (int)$p['ProductID'] ?>" <?= $productId === (string)$p['ProductID'] ? 'selected' : '' ?>>
                        <?= e($p['Name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="quantity">Quantity Available to Ship</label>
            <input type="number" min="1" step="1" id="quantity" name="quantity" value="<?= e($quantity) ?>" required>
        </div>
        <div class="form-group">
            <label for="unit_price">Your Unit Price (₱)</label>
            <input type="number" step="0.01" min="0" id="unit_price" name="unit_price" value="<?= e($unitPrice) ?>" required>
        </div>
        <div class="form-group">
            <label for="delivery_date">Earliest Delivery Date</label>
            <input type="date" id="delivery_date" name="delivery_date" value="<?= e($deliveryDate) ?>" min="<?= e(date('Y-m-d')) ?>">
        </div>
        <div class="form-group">
            <label for="notes">Delivery Notes</label>
            <textarea id="notes" name="notes" rows="3" maxlength="500" placeholder="Shipping terms, minimum order size, etc."><?= e($notes) ?></textarea>
        </div>
        <div class="form-actions">
            <button class="btn" type="submit">Send update</button>
            <a class="btn btn-secondary" href="<?= e(url('supplier_portal/dashboard.php')) ?>">Cancel</a>
        </div>
    </form>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
