<?php
// purchase_orders/create_order.php — Manager/Admin create purchase orders to suppliers.
// Prompted by low stock: pick a supplier and the page lists that supplier's low-stock
// products with an "Add" button (suggested quantity can be edited).
// Optional prefill: create_order.php?product_id=5 (used by the Low Stock report).
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/audit_log.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/csrf.php';

require_role(['Admin', 'Manager']);

const PO_MAX_QTY   = 1000000;
const PO_MAX_PRICE = 99999999.99;
const PO_MAX_LINES = 50;

$suppliers = $pdo->query("SELECT SupplierID, Name FROM Supplier ORDER BY Name")->fetchAll();
$products  = $pdo->query(
    "SELECT ProductID, Name, SupplierID, UnitPrice, Quantity, MinThreshold FROM Product ORDER BY Name"
)->fetchAll();

$errors     = [];
$supplierId = '';
$postedRows = [];

// Prefill from a product link (GET only).
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ctype_digit((string)($_GET['product_id'] ?? ''))) {
    foreach ($products as $p) {
        if ((int)$p['ProductID'] === (int)$_GET['product_id']) {
            $supplierId = (string)$p['SupplierID'];
            $suggest = max((int)$p['MinThreshold'] * 2 - (int)$p['Quantity'], 1);
            $postedRows[] = ['product' => (string)$p['ProductID'], 'qty' => (string)$suggest, 'price' => (string)$p['UnitPrice']];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $supplierId     = (string)($_POST['supplier_id'] ?? '');
    $itemProductIds = (array)($_POST['item_product_id'] ?? []);
    $itemQtys       = (array)($_POST['item_quantity'] ?? []);
    $itemPrices     = (array)($_POST['item_unit_price'] ?? []);

    // Product lookup for validation.
    $productById = [];
    foreach ($products as $p) { $productById[(string)$p['ProductID']] = $p; }

    $supplierOk = false;
    foreach ($suppliers as $s) { if ((string)$s['SupplierID'] === $supplierId) $supplierOk = true; }
    if (!$supplierOk) $errors[] = 'Please select a supplier.';

    $items = [];
    $seen  = [];
    foreach ($itemProductIds as $i => $pid) {
        $pid   = (string)$pid;
        $qty   = trim((string)($itemQtys[$i] ?? ''));
        $price = trim((string)($itemPrices[$i] ?? ''));
        $postedRows[] = ['product' => $pid, 'qty' => $qty, 'price' => $price];

        if ($pid === '' && $qty === '') continue;   // blank row

        if (!isset($productById[$pid])) { $errors[] = 'Each order line needs a product.'; continue; }
        if ($supplierOk && (string)$productById[$pid]['SupplierID'] !== $supplierId) {
            $errors[] = "\"{$productById[$pid]['Name']}\" is not supplied by the selected supplier.";
            continue;
        }
        if (isset($seen[$pid])) { $errors[] = "\"{$productById[$pid]['Name']}\" is listed more than once. Combine the quantities into one line."; continue; }
        $seen[$pid] = true;

        $qtyInt = filter_var($qty, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => PO_MAX_QTY]]);
        if ($qtyInt === false) { $errors[] = 'Each order line needs a whole-number quantity of at least 1.'; continue; }
        if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $price) || (float)$price > PO_MAX_PRICE) {
            $errors[] = 'Each order line needs a valid unit price (2 decimals at most).'; continue;
        }
        $items[] = ['product_id' => (int)$pid, 'quantity' => $qtyInt, 'unit_price' => $price];
    }
    if (empty($items) && empty($errors)) $errors[] = 'Add at least one product line item.';
    if (count($items) > PO_MAX_LINES) $errors[] = 'An order can have at most ' . PO_MAX_LINES . ' lines.';
    $errors = array_values(array_unique($errors));

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO Purchase_Order (SupplierID, UserID, Status) VALUES (?, ?, 'Pending')");
            $stmt->execute([(int)$supplierId, (int)$_SESSION['user_id']]);
            $orderId = (int)$pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                "INSERT INTO Purchase_Order_Item (OrderID, ProductID, QuantityOrdered, UnitPrice) VALUES (?, ?, ?, ?)"
            );
            foreach ($items as $item) {
                $itemStmt->execute([$orderId, $item['product_id'], $item['quantity'], $item['unit_price']]);
            }
            $pdo->commit();

            log_action($pdo, (int)$_SESSION['user_id'], "Created purchase order #$orderId", 'Purchase_Order', $orderId);

            set_flash('success', "Order #$orderId was created. It now says “Waiting for delivery”.");
            header('Location: ' . url('purchase_orders/order_detail.php?id=' . $orderId));
            exit;
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Create purchase order failed: ' . $ex->getMessage());
            $errors[] = 'Something went wrong creating the order. Please try again.';
        }
    }
}

// Data for the page script.
$jsProducts = [];
foreach ($products as $p) {
    $jsProducts[] = [
        'id' => (int)$p['ProductID'], 'name' => $p['Name'], 'supplier' => (int)$p['SupplierID'],
        'price' => (string)$p['UnitPrice'], 'qty' => (int)$p['Quantity'], 'min' => (int)$p['MinThreshold'],
    ];
}
$jsFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

$pageTitle = 'New order';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>New order from a supplier</h1>
    <a class="btn btn-secondary" href="<?= e(url('purchase_orders/view_orders.php')) ?>">&larr; Back to Orders</a>
</div>

<?php foreach ($errors as $msg): ?>
    <div class="alert alert-error"><?= e($msg) ?></div>
<?php endforeach; ?>

<form class="form-box" style="max-width:800px" method="POST" action="<?= e(url('purchase_orders/create_order.php')) ?>" id="orderForm" data-confirm-builder="order">
    <?= csrf_field() ?>
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
        <small>Only this supplier's products can be added to the order.</small>
    </div>

    <div id="lowStockBox" class="low-stock-box" style="display:none;">
        <strong>Low-stock items from this supplier</strong>
        <ul id="lowStockList"></ul>
    </div>

    <h3>Items to order</h3>
    <div class="table-wrap">
    <table class="data-table order-items-table" id="itemsTable">
        <thead>
            <tr><th>Product</th><th>How many</th><th>Price each (₱)</th><th></th></tr>
        </thead>
        <tbody></tbody>
    </table>
    </div>
    <button type="button" class="btn btn-sm btn-secondary" id="addRow">+ Add another item</button>

    <div class="form-actions">
        <button class="btn" type="submit">Create order</button>
        <a class="btn btn-secondary" href="<?= e(url('purchase_orders/view_orders.php')) ?>">Cancel</a>
    </div>
</form>

<script>
const PRODUCTS = <?= json_encode($jsProducts, $jsFlags) ?>;
const INITIAL_ROWS = <?= json_encode($postedRows, $jsFlags) ?>;

const supplierSel = document.getElementById('supplier_id');
const tbody = document.querySelector('#itemsTable tbody');
const lowBox = document.getElementById('lowStockBox');
const lowList = document.getElementById('lowStockList');

function productById(id) {
    return PRODUCTS.find(p => String(p.id) === String(id));
}

function fillProducts(sel, supplierId, selected) {
    sel.innerHTML = '';
    const blank = document.createElement('option');
    blank.value = '';
    blank.textContent = supplierId ? 'Choose an item' : 'Choose a supplier first';
    sel.appendChild(blank);
    PRODUCTS.filter(p => String(p.supplier) === String(supplierId)).forEach(p => {
        const o = document.createElement('option');
        o.value = p.id;
        o.textContent = p.name;
        if (String(p.id) === String(selected)) o.selected = true;
        sel.appendChild(o);
    });
}

function addRow(pid, qty, price) {
    const tr = document.createElement('tr');
    tr.innerHTML =
        '<td><select name="item_product_id[]"></select></td>' +
        '<td><input type="number" min="1" step="1" name="item_quantity[]"></td>' +
        '<td><input type="number" min="0" step="0.01" name="item_unit_price[]"></td>' +
        '<td><button type="button" class="btn btn-sm btn-danger removeRow" aria-label="Remove this item">&times;</button></td>';
    tbody.appendChild(tr);
    fillProducts(tr.querySelector('select'), supplierSel.value, pid || '');
    if (qty) tr.querySelector('input[name="item_quantity[]"]').value = qty;
    if (price) tr.querySelector('input[name="item_unit_price[]"]').value = price;
    return tr;
}

function rowHasProduct(pid) {
    return Array.from(tbody.querySelectorAll('select')).some(s => String(s.value) === String(pid));
}

function renderLowStock() {
    lowList.innerHTML = '';
    const low = PRODUCTS.filter(p =>
        String(p.supplier) === String(supplierSel.value) && p.qty <= p.min);
    lowBox.style.display = low.length ? 'block' : 'none';
    low.forEach(p => {
        const li = document.createElement('li');
        const label = document.createElement('span');
        label.textContent = p.name + ' (stock ' + p.qty + ', minimum ' + p.min + ') ';
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm btn-secondary';
        btn.dataset.km = 'add-low-' + p.id;
        btn.textContent = 'Add';
        btn.addEventListener('click', function () {
            if (rowHasProduct(p.id)) return;
            const suggested = Math.max(p.min * 2 - p.qty, 1);
            // Reuse an empty first row if there is one.
            const blankRow = Array.from(tbody.querySelectorAll('tr')).find(tr =>
                tr.querySelector('select').value === '' && tr.querySelector('input[name="item_quantity[]"]').value === '');
            if (blankRow) {
                fillProducts(blankRow.querySelector('select'), supplierSel.value, p.id);
                blankRow.querySelector('input[name="item_quantity[]"]').value = suggested;
                blankRow.querySelector('input[name="item_unit_price[]"]').value = p.price;
            } else {
                addRow(p.id, suggested, p.price);
            }
        });
        li.appendChild(label);
        li.appendChild(btn);
        lowList.appendChild(li);
    });
}

document.getElementById('addRow').addEventListener('click', function () { addRow('', '', ''); });

tbody.addEventListener('click', function (e) {
    if (e.target.classList.contains('removeRow')) {
        if (tbody.querySelectorAll('tr').length > 1) {
            e.target.closest('tr').remove();
        } else {
            const tr = e.target.closest('tr');
            fillProducts(tr.querySelector('select'), supplierSel.value, '');
            tr.querySelector('input[name="item_quantity[]"]').value = '';
            tr.querySelector('input[name="item_unit_price[]"]').value = '';
        }
    }
});

// Auto-fill the unit price from the product's current price when it is picked.
tbody.addEventListener('change', function (e) {
    if (e.target.name === 'item_product_id[]') {
        const p = productById(e.target.value);
        const priceInput = e.target.closest('tr').querySelector('input[name="item_unit_price[]"]');
        if (p && !priceInput.value) priceInput.value = p.price;
    }
});

// Changing the supplier resets each line's product list to that supplier's products.
supplierSel.addEventListener('change', function () {
    tbody.querySelectorAll('tr').forEach(tr => {
        fillProducts(tr.querySelector('select'), supplierSel.value, '');
        tr.querySelector('input[name="item_unit_price[]"]').value = '';
    });
    renderLowStock();
});

// Initial state (also restores what was typed after a validation error).
if (INITIAL_ROWS.length === 0) {
    addRow('', '', '');
} else {
    INITIAL_ROWS.forEach(r => addRow(r.product, r.qty, r.price));
}
renderLowStock();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
