<?php
// products/list.php — Product Search module.
// Everyone signed in (except Suppliers) can search; Employees can add; Manager/Admin edit and delete.
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/csrf.php';

$q          = trim($_GET['q'] ?? '');
$categoryId = ctype_digit((string)($_GET['category_id'] ?? '')) ? (int)$_GET['category_id'] : 0;
$supplierId = ctype_digit((string)($_GET['supplier_id'] ?? '')) ? (int)$_GET['supplier_id'] : 0;
$lowOnly    = ($_GET['low'] ?? '') === '1';

$where  = [];
$params = [];

if ($q !== '') {
    // Treat % and _ typed by the user as plain characters, not wildcards.
    $where[]  = "p.Name LIKE ? ESCAPE '\\\\'";
    $params[] = '%' . addcslashes($q, '%_\\') . '%';
}
if ($categoryId > 0) {
    $where[]  = "p.CategoryID = ?";
    $params[] = $categoryId;
}
if ($supplierId > 0) {
    $where[]  = "p.SupplierID = ?";
    $params[] = $supplierId;
}
if ($lowOnly) {
    $where[] = "p.Quantity <= p.MinThreshold";
}

$sql = "SELECT p.*, c.CategoryName, s.Name AS SupplierName
        FROM Product p
        JOIN Category c ON p.CategoryID = c.CategoryID
        JOIN Supplier s ON p.SupplierID = s.SupplierID"
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . " ORDER BY p.Name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = $pdo->query("SELECT * FROM Category ORDER BY CategoryName")->fetchAll();
$suppliers  = $pdo->query("SELECT * FROM Supplier ORDER BY Name")->fetchAll();

$canEdit   = has_role(['Admin', 'Manager']);
$filtering = ($q !== '' || $categoryId > 0 || $supplierId > 0 || $lowOnly);

$pageTitle = 'Products';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Products</h1>
    <a class="btn" href="<?= e(url('products/add.php')) ?>">+ Add product</a>
</div>

<form class="search-bar" method="GET" action="<?= e(url('products/list.php')) ?>">
    <input type="text" name="q" placeholder="Type a product name…" aria-label="Product name" value="<?= e($q) ?>">
    <select name="category_id" aria-label="Category">
        <option value="">All Categories</option>
        <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['CategoryID'] ?>" <?= $categoryId === (int)$c['CategoryID'] ? 'selected' : '' ?>>
                <?= e($c['CategoryName']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <select name="supplier_id" aria-label="Supplier">
        <option value="">All Suppliers</option>
        <?php foreach ($suppliers as $s): ?>
            <option value="<?= (int)$s['SupplierID'] ?>" <?= $supplierId === (int)$s['SupplierID'] ? 'selected' : '' ?>>
                <?= e($s['Name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <label class="check-label"><input type="checkbox" name="low" value="1" <?= $lowOnly ? 'checked' : '' ?>> Low stock only</label>
    <button class="btn btn-secondary" type="submit">Search</button>
    <a class="btn btn-secondary" href="<?= e(url('products/list.php')) ?>">Reset</a>
</form>

<p class="text-muted"><?= count($products) ?> product<?= count($products) === 1 ? '' : 's' ?><?= $filtering ? ' match your search' : '' ?>.</p>

<?php if (count($products) === 0): ?>
    <div class="empty-state">No products match. Try fewer words, or tap Reset.</div>
<?php else: ?>
    <div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th><th class="km-extra">Category</th><th class="km-extra">Supplier</th>
                <th>Price</th><th>In stock</th><th class="km-extra">Low at</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $p): ?>
                <?php $isLow = (int)$p['Quantity'] <= (int)$p['MinThreshold']; ?>
                <tr>
                    <td><?= e($p['Name']) ?></td>
                    <td class="km-extra"><?= e($p['CategoryName']) ?></td>
                    <td class="km-extra"><?= e($p['SupplierName']) ?></td>
                    <td class="num">₱<?= number_format((float)$p['UnitPrice'], 2) ?></td>
                    <td class="num <?= $isLow ? 'low-stock' : '' ?>"><?= (int)$p['Quantity'] ?></td>
                    <td class="num km-extra"><?= (int)$p['MinThreshold'] ?></td>
                    <td>
                        <?php if ($canEdit): ?>
                            <div class="actions">
                                <a class="btn btn-sm btn-secondary" href="<?= e(url('products/edit.php?id=' . (int)$p['ProductID'])) ?>">Edit</a>
                                <form method="post" action="<?= e(url('products/delete.php')) ?>" class="inline-form"
                                      data-confirm-title="Delete this product?" data-confirm="&quot;<?= e($p['Name']) ?>&quot; will be removed for good. This cannot be undone." data-confirm-yes="Yes, delete it">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$p['ProductID'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </div>
                        <?php else: ?>
                            &mdash;
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
