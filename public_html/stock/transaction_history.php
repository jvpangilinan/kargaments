<?php
// stock/transaction_history.php — Inventory Tracking module.
// Viewable by Admin, Manager and Employee (Employees record the transactions, so they can see them).
// Transactions are never edited or deleted.
require_once __DIR__ . '/../includes/auth_check.php';

require_role(['Admin', 'Manager', 'Employee']);

const HISTORY_PER_PAGE = 50;

// Accept only a real YYYY-MM-DD date.
function clean_date(string $value): string
{
    $d = DateTime::createFromFormat('Y-m-d', $value);
    return ($d && $d->format('Y-m-d') === $value) ? $value : '';
}

$productId = ctype_digit((string)($_GET['product_id'] ?? '')) ? (int)$_GET['product_id'] : 0;
$type      = in_array($_GET['type'] ?? '', ['Stock-In', 'Sale'], true) ? $_GET['type'] : '';
$dateFrom  = clean_date((string)($_GET['date_from'] ?? ''));
$dateTo    = clean_date((string)($_GET['date_to'] ?? ''));
$page      = max(1, (int)($_GET['page'] ?? 1));

$where  = [];
$params = [];
if ($productId > 0) { $where[] = "st.ProductID = ?";          $params[] = $productId; }
if ($type !== '')   { $where[] = "st.TransactionType = ?";    $params[] = $type; }
if ($dateFrom !== '') { $where[] = "st.TransactionDate >= ?"; $params[] = $dateFrom . ' 00:00:00'; }
if ($dateTo !== '')   { $where[] = "st.TransactionDate <= ?"; $params[] = $dateTo . ' 23:59:59'; }
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM Stock_Transaction st" . $whereSql);
$countStmt->execute($params);
$totalRows  = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / HISTORY_PER_PAGE));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * HISTORY_PER_PAGE;

$stmt = $pdo->prepare(
    "SELECT st.*, p.Name AS ProductName, u.FullName AS UserName
     FROM Stock_Transaction st
     JOIN Product p ON st.ProductID = p.ProductID
     JOIN User u ON st.UserID = u.UserID"
    . $whereSql .
    " ORDER BY st.TransactionDate DESC, st.TransactionID DESC
     LIMIT " . HISTORY_PER_PAGE . " OFFSET " . $offset
);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

$products = $pdo->query("SELECT ProductID, Name FROM Product ORDER BY Name")->fetchAll();

// Link that keeps the current filters when moving between pages.
function history_page_url(int $p): string
{
    $q = $_GET;
    $q['page'] = $p;
    return url('stock/transaction_history.php') . '?' . http_build_query($q);
}

$pageTitle = 'Sales & deliveries';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Sales &amp; deliveries</h1>
    <a class="btn" href="<?= e(url('stock/add_transaction.php')) ?>">+ Record a sale or delivery</a>
</div>

<form class="search-bar" method="GET" action="<?= e(url('stock/transaction_history.php')) ?>">
    <select name="product_id" aria-label="Product">
        <option value="">All Products</option>
        <?php foreach ($products as $p): ?>
            <option value="<?= (int)$p['ProductID'] ?>" <?= $productId === (int)$p['ProductID'] ? 'selected' : '' ?>>
                <?= e($p['Name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <select name="type" aria-label="Type">
        <option value="">Sales and deliveries</option>
        <option value="Stock-In" <?= $type === 'Stock-In' ? 'selected' : '' ?>>Deliveries only</option>
        <option value="Sale" <?= $type === 'Sale' ? 'selected' : '' ?>>Sales only</option>
    </select>
    <label class="check-label">From <input type="date" name="date_from" value="<?= e($dateFrom) ?>"></label>
    <label class="check-label">To <input type="date" name="date_to" value="<?= e($dateTo) ?>"></label>
    <button class="btn btn-secondary" type="submit">Filter</button>
    <a class="btn btn-secondary" href="<?= e(url('stock/transaction_history.php')) ?>">Reset</a>
</form>

<p class="text-muted"><?= number_format($totalRows) ?> transaction<?= $totalRows === 1 ? '' : 's' ?>.</p>

<?php if (count($transactions) === 0): ?>
    <div class="empty-state">No stock transactions found.</div>
<?php else: ?>
    <div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr><th>When</th><th>Product</th><th>What</th><th>How many</th><th class="km-extra">By</th></tr>
        </thead>
        <tbody>
            <?php foreach ($transactions as $t): ?>
                <tr>
                    <td><?= e(date('M j, Y g:i A', strtotime($t['TransactionDate']))) ?></td>
                    <td><?= e($t['ProductName']) ?></td>
                    <td>
                        <span class="badge <?= $t['TransactionType'] === 'Stock-In' ? 'badge-stockin' : 'badge-sale' ?>">
                            <?= e(km_label($t['TransactionType'], 'stock')) ?>
                        </span>
                    </td>
                    <td class="num"><?= (int)$t['Quantity'] ?></td>
                    <td class="km-extra"><?= e($t['UserName']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="pager">
            <?php if ($page > 1): ?><a class="btn btn-sm btn-secondary" href="<?= e(history_page_url($page - 1)) ?>">&larr; Newer</a><?php endif; ?>
            <span class="text-muted">Page <?= $page ?> of <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?><a class="btn btn-sm btn-secondary" href="<?= e(history_page_url($page + 1)) ?>">Older &rarr;</a><?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
