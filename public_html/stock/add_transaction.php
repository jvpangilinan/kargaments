<?php
// stock/add_transaction.php — Store employees and managers update stock quantities.
// Every adjustment must specify a transaction type (Stock-In vs Sale).
// The quantity change is one atomic UPDATE, so two people selling the last items at the
// same moment can never push stock below zero.
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/audit_log.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/csrf.php';

require_role(['Admin', 'Manager', 'Employee']);

const STOCK_MAX_QTY = 1000000;

$products = $pdo->query("SELECT ProductID, Name, Quantity FROM Product ORDER BY Name")->fetchAll();
$errors = [];

// Optional prefill from other pages, e.g. stock/add_transaction.php?product_id=3&type=Stock-In
$productId = ctype_digit((string)($_GET['product_id'] ?? '')) ? (string)$_GET['product_id'] : '';
$type      = in_array($_GET['type'] ?? '', ['Stock-In', 'Sale'], true) ? $_GET['type'] : '';
$quantity  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $productId = (string)($_POST['product_id'] ?? '');
    $type      = (string)($_POST['transaction_type'] ?? '');
    $quantity  = trim((string)($_POST['quantity'] ?? ''));

    $product = null;
    if (!ctype_digit($productId) || $productId === '') {
        $errors[] = 'Please select a product.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM Product WHERE ProductID = ?");
        $stmt->execute([(int)$productId]);
        $product = $stmt->fetch();
        if (!$product) $errors[] = 'Selected product no longer exists.';
    }
    if (!in_array($type, ['Stock-In', 'Sale'], true)) {
        $errors[] = 'Please choose “Sold” or “Received a delivery”.';
    }
    $qty = filter_var($quantity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => STOCK_MAX_QTY]]);
    if ($qty === false) {
        $errors[] = 'Quantity must be a whole number from 1 to ' . number_format(STOCK_MAX_QTY) . '.';
    }

    if ($product && $qty !== false && $type === 'Sale' && $qty > (int)$product['Quantity']) {
        $errors[] = "Sorry, there are only " . (int)$product['Quantity'] . " left, so {$qty} cannot be sold.";
    }

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            if ($type === 'Sale') {
                // Only succeeds if enough stock is still there right now.
                $upd = $pdo->prepare("UPDATE Product SET Quantity = Quantity - ? WHERE ProductID = ? AND Quantity >= ?");
                $upd->execute([$qty, (int)$productId, $qty]);
            } else {
                $upd = $pdo->prepare("UPDATE Product SET Quantity = Quantity + ? WHERE ProductID = ?");
                $upd->execute([$qty, (int)$productId]);
            }

            if ($upd->rowCount() !== 1) {
                $pdo->rollBack();
                $now = $pdo->prepare("SELECT Quantity FROM Product WHERE ProductID = ?");
                $now->execute([(int)$productId]);
                $left = $now->fetchColumn();
                $errors[] = ($left === false)
                    ? 'Selected product no longer exists.'
                    : "Sorry, there are only " . (int)$left . " left, so {$qty} cannot be sold.";
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO Stock_Transaction (ProductID, UserID, TransactionType, Quantity)
                     VALUES (?, ?, ?, ?)"
                );
                $stmt->execute([(int)$productId, (int)$_SESSION['user_id'], $type, $qty]);
                $transactionId = (int)$pdo->lastInsertId();
                $pdo->commit();

                log_action(
                    $pdo, (int)$_SESSION['user_id'],
                    "$type of $qty units for '{$product['Name']}'",
                    'Stock_Transaction', $transactionId
                );

                set_flash('success', ($type === 'Sale' ? "Sale saved: $qty × " : "Delivery saved: $qty × ") . $product['Name'] . '.');
                header('Location: ' . url('stock/transaction_history.php'));
                exit;
            }
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Stock transaction failed: ' . $ex->getMessage());
            $errors[] = 'Something went wrong recording the transaction. Please try again.';
        }
        // Refresh the dropdown numbers after a failed attempt.
        $products = $pdo->query("SELECT ProductID, Name, Quantity FROM Product ORDER BY Name")->fetchAll();
    }
}

$pageTitle = 'Record a sale or delivery';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Record a sale or delivery</h1>
    <a class="btn btn-secondary" href="<?= e(url('stock/transaction_history.php')) ?>">&larr; Back to the list</a>
</div>

<?php foreach ($errors as $msg): ?>
    <div class="alert alert-error"><?= e($msg) ?></div>
<?php endforeach; ?>

<form class="form-box" method="POST" action="<?= e(url('stock/add_transaction.php')) ?>" data-confirm-builder="stock">
    <?= csrf_field() ?>
    <div class="form-group">
        <label for="product_id">Product</label>
        <select id="product_id" name="product_id" required>
            <option value="">Choose a product</option>
            <?php foreach ($products as $p): ?>
                <option value="<?= (int)$p['ProductID'] ?>" <?= $productId === (string)$p['ProductID'] ? 'selected' : '' ?>>
                    <?= e($p['Name']) ?> — <?= (int)$p['Quantity'] ?> in stock
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="transaction_type">What happened?</label>
        <select id="transaction_type" name="transaction_type" required>
            <option value="">Choose one</option>
            <option value="Stock-In" <?= $type === 'Stock-In' ? 'selected' : '' ?>>Received a delivery (stock goes up)</option>
            <option value="Sale" <?= $type === 'Sale' ? 'selected' : '' ?>>Sold to a customer (stock goes down)</option>
        </select>
    </div>
    <div class="form-group">
        <label for="quantity">How many?</label>
        <input type="number" min="1" step="1" id="quantity" name="quantity" value="<?= e($quantity) ?>" required>
    </div>
    <div class="form-actions">
        <button class="btn" type="submit">Save</button>
        <a class="btn btn-secondary" href="<?= e(url('stock/transaction_history.php')) ?>">Cancel</a>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
