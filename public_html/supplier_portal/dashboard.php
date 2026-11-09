<?php
// supplier_portal/dashboard.php — Supplier Portal module.
// A Supplier login sees only its own company info, the products it supplies,
// and the status of updates it has submitted for review.
require_once __DIR__ . '/../includes/auth_check.php';

require_role(['Supplier']);

// auth_check.php refreshes this from the database on every request.
$supplierId = $_SESSION['supplier_id'] ?? null;

$pageTitle = 'Supplier Dashboard';

if (!$supplierId) {
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="alert alert-error">Your account is not linked to a supplier record yet. Please contact an administrator.</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM Supplier WHERE SupplierID = ?");
$stmt->execute([$supplierId]);
$supplier = $stmt->fetch();

if (!$supplier) {
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="alert alert-error">Your supplier record could not be found. Please contact an administrator.</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$stmt = $pdo->prepare(
    "SELECT p.*, c.CategoryName
     FROM Product p
     JOIN Category c ON p.CategoryID = c.CategoryID
     WHERE p.SupplierID = ?
     ORDER BY p.Name"
);
$stmt->execute([$supplierId]);
$products = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT ss.*, p.Name AS ProductName
     FROM Supplier_Submission ss
     JOIN Product p ON ss.ProductID = p.ProductID
     WHERE ss.SupplierID = ?
     ORDER BY ss.SubmittedAt DESC, ss.SubmissionID DESC"
);
$stmt->execute([$supplierId]);
$submissions = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<?php
$myLow = 0;
foreach ($products as $p) { if ((int)$p['Quantity'] <= (int)$p['MinThreshold']) $myLow++; }
$myWaiting = 0;
foreach ($submissions as $s) { if ($s['Status'] === 'Pending') $myWaiting++; }
?>
<div class="km-home-hero">
    <div>
        <p class="km-eyebrow">Supplier Portal</p>
        <h1><?= e($supplier['Name']) ?></h1>
        <p>View your items at the store and send availability, price or delivery updates.</p>
    </div>
    <div class="km-hero-date"><?= e(date('l')) ?><strong><?= e(date('F j, Y')) ?></strong></div>
</div>

<div class="km-tiles">
    <a class="km-tile km-tile-main" href="<?= e(url('supplier_portal/submit.php')) ?>" data-km="tile-submit">
        <span class="km-tile-ico"><?= km_icon('send') ?></span>
        <span><strong>Send an update</strong><span class="km-tile-sub">Offer stock, a price or a delivery date</span></span>
        <?php if ($myLow): ?><span class="km-tile-count"><?= $myLow ?> low</span><?php endif; ?>
    </a>
    <a class="km-tile" href="#km-my-updates">
        <span class="km-tile-ico"><?= km_icon('clock') ?></span>
        <span><strong>Waiting for review</strong><span class="km-tile-sub">Updates not yet reviewed by the store</span></span>
        <span class="km-tile-count"><?= $myWaiting ?></span>
    </a>
</div>

<div class="card km-extra" style="text-align:left; margin-bottom:20px;">
    <p><strong>Contact Info:</strong> <?= e($supplier['ContactInfo'] ?? '—') ?></p>
    <p><strong>Address:</strong> <?= e($supplier['Address'] ?? '—') ?></p>
</div>

<h2>Your items at the store</h2>
<?php if (count($products) === 0): ?>
    <div class="empty-state">No products are currently linked to your company. Contact a manager to get set up.</div>
<?php else: ?>
    <div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr><th>Product</th><th class="km-extra">Category</th><th>Store price</th><th>Store has</th><th class="km-extra">Low at</th></tr>
        </thead>
        <tbody>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td><?= e($p['Name']) ?></td>
                    <td class="km-extra"><?= e($p['CategoryName']) ?></td>
                    <td class="num">₱<?= number_format((float)$p['UnitPrice'], 2) ?></td>
                    <td class="num <?= (int)$p['Quantity'] <= (int)$p['MinThreshold'] ? 'low-stock' : '' ?>"><?= (int)$p['Quantity'] ?></td>
                    <td class="num km-extra"><?= (int)$p['MinThreshold'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>

<h2 style="margin-top:30px;" id="km-my-updates">Your updates</h2>
<?php if (count($submissions) === 0): ?>
    <div class="empty-state" data-km="my-updates">You have not sent any updates yet. Tap “Send an update” to start.</div>
<?php else: ?>
    <div class="table-wrap" data-km="my-updates">
    <table class="data-table">
        <thead>
            <tr><th>Sent</th><th>Product</th><th>How many</th><th>Price</th><th class="km-extra">Delivery</th><th>Status</th><th>Store's note</th></tr>
        </thead>
        <tbody>
            <?php foreach ($submissions as $s):
                $badgeClass = match ($s['Status']) {
                    'Approved' => 'badge-received',
                    'Rejected' => 'badge-cancelled',
                    default    => 'badge-pending',
                };
            ?>
                <tr>
                    <td><?= e(date('M j, Y', strtotime($s['SubmittedAt']))) ?></td>
                    <td><?= e($s['ProductName']) ?></td>
                    <td class="num"><?= (int)$s['AvailableQuantity'] ?></td>
                    <td class="num">₱<?= number_format((float)$s['UnitPrice'], 2) ?></td>
                    <td class="km-extra"><?= $s['DeliveryDate'] ? e(date('M j, Y', strtotime($s['DeliveryDate']))) : '—' ?></td>
                    <td><span class="badge <?= $badgeClass ?>"><?= e(km_label($s['Status'], 'submission')) ?></span></td>
                    <td><?= e($s['ReviewNotes'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
