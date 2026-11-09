<?php
// products/edit.php — Manager/Admin edit name, category, unit price, supplier, threshold.
// Quantity is changed only through the Stock module.
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/audit_log.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/csrf.php';

require_role(['Admin', 'Manager']);

const PRODUCT_MAX_QTY   = 1000000;
const PRODUCT_MAX_PRICE = 99999999.99;

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM Product WHERE ProductID = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    set_flash('error', 'Product not found.');
    header('Location: ' . url('products/list.php'));
    exit;
}

$categories = $pdo->query("SELECT * FROM Category ORDER BY CategoryName")->fetchAll();
$suppliers  = $pdo->query("SELECT * FROM Supplier ORDER BY Name")->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name         = trim($_POST['name'] ?? '');
    $categoryId   = (string)($_POST['category_id'] ?? '');
    $supplierId   = (string)($_POST['supplier_id'] ?? '');
    $unitPrice    = trim((string)($_POST['unit_price'] ?? ''));
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
    $minInt = filter_var($minThreshold, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => PRODUCT_MAX_QTY]]);
    if ($minInt === false) $errors[] = 'Minimum threshold must be a whole number (0 or more).';

    if (empty($errors)) {
        $dup = $pdo->prepare("SELECT COUNT(*) FROM Product WHERE Name = ? AND SupplierID = ? AND ProductID <> ?");
        $dup->execute([$name, (int)$supplierId, $id]);
        if ($dup->fetchColumn() > 0) {
            $errors[] = 'This supplier already has a product with that name.';
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            "UPDATE Product
             SET Name = ?, CategoryID = ?, SupplierID = ?, UnitPrice = ?, MinThreshold = ?
             WHERE ProductID = ?"
        );
        $stmt->execute([$name, (int)$categoryId, (int)$supplierId, $unitPrice, $minInt, $id]);

        log_action($pdo, (int)$_SESSION['user_id'], "Updated product '$name'", 'Product', $id);

        set_flash('success', "Product \"$name\" was updated.");
        header('Location: ' . url('products/list.php'));
        exit;
    }
    // keep posted values on validation error
    $product['Name']         = $name;
    $product['CategoryID']   = $categoryId;
    $product['SupplierID']   = $supplierId;
    $product['UnitPrice']    = $unitPrice;
    $product['MinThreshold'] = $minThreshold;
}

$pageTitle = 'Edit Product';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Edit Product</h1>
    <a class="btn btn-secondary" href="<?= e(url('products/list.php')) ?>">&larr; Back to Products</a>
</div>

<?php foreach ($errors as $msg): ?>
    <div class="alert alert-error"><?= e($msg) ?></div>
<?php endforeach; ?>

<form class="form-box" method="POST" action="<?= e(url('products/edit.php?id=' . $id)) ?>">
    <?= csrf_field() ?>
    <div class="form-group">
        <label for="name">Product Name</label>
        <input type="text" id="name" name="name" value="<?= e($product['Name']) ?>" maxlength="255" required>
    </div>
    <div class="form-group">
        <label for="category_id">Category</label>
        <select id="category_id" name="category_id" required>
            <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['CategoryID'] ?>" <?= (string)$product['CategoryID'] === (string)$c['CategoryID'] ? 'selected' : '' ?>>
                    <?= e($c['CategoryName']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="supplier_id">Supplier</label>
        <select id="supplier_id" name="supplier_id" required>
            <?php foreach ($suppliers as $s): ?>
                <option value="<?= (int)$s['SupplierID'] ?>" <?= (string)$product['SupplierID'] === (string)$s['SupplierID'] ? 'selected' : '' ?>>
                    <?= e($s['Name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="unit_price">Unit Price (₱)</label>
        <input type="number" step="0.01" min="0" id="unit_price" name="unit_price" value="<?= e($product['UnitPrice']) ?>" required>
    </div>
    <div class="form-group">
        <label>Current Quantity</label>
        <input type="text" value="<?= (int)$product['Quantity'] ?>" disabled>
        <small>Use the Stock module to change quantity via Stock-In or Sale transactions.</small>
    </div>
    <div class="form-group">
        <label for="min_threshold">Minimum Stock Threshold</label>
        <input type="number" min="0" step="1" id="min_threshold" name="min_threshold" value="<?= e($product['MinThreshold']) ?>">
    </div>
    <div class="form-actions">
        <button class="btn" type="submit">Save Changes</button>
        <a class="btn btn-secondary" href="<?= e(url('products/list.php')) ?>">Cancel</a>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
