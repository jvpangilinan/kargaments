<?php
// products/add.php — Product Management module
// A starting quantity above 0 is recorded as a Stock-In transaction, so stock history
// always adds up to the quantity shown.
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/audit_log.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/csrf.php';

// Employees can add products; Managers/Admins too.
require_role(['Admin', 'Manager', 'Employee']);

const PRODUCT_MAX_QTY   = 1000000;     // sanity limits
const PRODUCT_MAX_PRICE = 99999999.99;

$errors = [];
$name = $categoryId = $supplierId = $unitPrice = $quantity = '';
$minThreshold = '0';

$categories = $pdo->query("SELECT * FROM Category ORDER BY CategoryName")->fetchAll();
$suppliers  = $pdo->query("SELECT * FROM Supplier ORDER BY Name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name         = trim($_POST['name'] ?? '');
    $categoryId   = (string)($_POST['category_id'] ?? '');
    $supplierId   = (string)($_POST['supplier_id'] ?? '');
    $unitPrice    = trim((string)($_POST['unit_price'] ?? ''));
    $quantity     = trim((string)($_POST['quantity'] ?? ''));
    $minThreshold = trim((string)($_POST['min_threshold'] ?? '0'));
    if ($minThreshold === '') $minThreshold = '0';

    if ($name === '') {
        $errors[] = 'Product name is required.';
    } elseif (mb_strlen($name) > 255) {
        $errors[] = 'Product name is too long (255 characters maximum).';
    }

    $categoryOk = false;
    foreach ($categories as $c) { if ((string)$c['CategoryID'] === $categoryId) $categoryOk = true; }
    if (!$categoryOk) $errors[] = 'Please select a category.';

    $supplierOk = false;
    foreach ($suppliers as $s) { if ((string)$s['SupplierID'] === $supplierId) $supplierOk = true; }
    if (!$supplierOk) $errors[] = 'Please select a supplier.';

    if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $unitPrice) || (float)$unitPrice > PRODUCT_MAX_PRICE) {
        $errors[] = 'Unit price must be a number with at most 2 decimals (0 or more).';
    }
    $qtyInt = filter_var($quantity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => PRODUCT_MAX_QTY]]);
    if ($qtyInt === false) $errors[] = 'Starting quantity must be a whole number (0 or more).';
    $minInt = filter_var($minThreshold, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => PRODUCT_MAX_QTY]]);
    if ($minInt === false) $errors[] = 'Minimum threshold must be a whole number (0 or more).';

    if (empty($errors)) {
        $dup = $pdo->prepare("SELECT COUNT(*) FROM Product WHERE Name = ? AND SupplierID = ?");
        $dup->execute([$name, (int)$supplierId]);
        if ($dup->fetchColumn() > 0) {
            $errors[] = 'This supplier already has a product with that name.';
        }
    }

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO Product (Name, CategoryID, SupplierID, UnitPrice, Quantity, MinThreshold)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$name, (int)$categoryId, (int)$supplierId, $unitPrice, $qtyInt, $minInt]);
            $newId = (int)$pdo->lastInsertId();

            if ($qtyInt > 0) {
                $tx = $pdo->prepare(
                    "INSERT INTO Stock_Transaction (ProductID, UserID, TransactionType, Quantity)
                     VALUES (?, ?, 'Stock-In', ?)"
                );
                $tx->execute([$newId, (int)$_SESSION['user_id'], $qtyInt]);
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $ex;
        }

        log_action($pdo, (int)$_SESSION['user_id'], "Created product '$name'", 'Product', $newId);

        set_flash('success', "Product \"$name\" was added.");
        header('Location: ' . url('products/list.php'));
        exit;
    }
}

$pageTitle = 'Add Product';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Add Product</h1>
    <a class="btn btn-secondary" href="<?= e(url('products/list.php')) ?>">&larr; Back to Products</a>
</div>

<?php foreach ($errors as $msg): ?>
    <div class="alert alert-error"><?= e($msg) ?></div>
<?php endforeach; ?>

<form class="form-box" method="POST" action="<?= e(url('products/add.php')) ?>">
    <?= csrf_field() ?>
    <div class="form-group">
        <label for="name">Product Name</label>
        <input type="text" id="name" name="name" value="<?= e($name) ?>" maxlength="255" required>
    </div>
    <div class="form-group">
        <label for="category_id">Category</label>
        <select id="category_id" name="category_id" required>
            <option value="">Choose a category</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['CategoryID'] ?>" <?= $categoryId === (string)$c['CategoryID'] ? 'selected' : '' ?>>
                    <?= e($c['CategoryName']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="supplier_id">Supplier</label>
        <select id="supplier_id" name="supplier_id" required>
            <option value="">Choose a supplier</option>
            <?php foreach ($suppliers as $s): ?>
                <option value="<?= (int)$s['SupplierID'] ?>" <?= $supplierId === (string)$s['SupplierID'] ? 'selected' : '' ?>>
                    <?= e($s['Name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="unit_price">Price for one (₱)</label>
        <input type="number" step="0.01" min="0" id="unit_price" name="unit_price" value="<?= e($unitPrice) ?>" required>
    </div>
    <div class="form-group">
        <label for="quantity">How many you have now</label>
        <input type="number" min="0" step="1" id="quantity" name="quantity" value="<?= e($quantity) ?>" required>
        <small>Saved as a delivery so the history adds up.</small>
    </div>
    <div class="form-group">
        <label for="min_threshold">Warn me when stock drops to</label>
        <input type="number" min="0" step="1" id="min_threshold" name="min_threshold" value="<?= e($minThreshold) ?>">
    </div>
    <div class="form-actions">
        <button class="btn" type="submit">Save Product</button>
        <a class="btn btn-secondary" href="<?= e(url('products/list.php')) ?>">Cancel</a>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
