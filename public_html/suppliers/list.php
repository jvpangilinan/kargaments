<?php
// suppliers/list.php — everyone signed in (except Suppliers) can view; Admin/Manager manage.
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/csrf.php';

$suppliers = $pdo->query(
    "SELECT s.*, COUNT(p.ProductID) AS ProductCount
     FROM Supplier s
     LEFT JOIN Product p ON p.SupplierID = s.SupplierID
     GROUP BY s.SupplierID
     ORDER BY s.Name"
)->fetchAll();

$canManage = has_role(['Admin', 'Manager']);

$pageTitle = 'Suppliers';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Suppliers</h1>
    <?php if ($canManage): ?>
        <a class="btn" href="<?= e(url('suppliers/add.php')) ?>">+ Add Supplier</a>
    <?php endif; ?>
</div>

<?php if (count($suppliers) === 0): ?>
    <div class="empty-state">No suppliers yet.</div>
<?php else: ?>
    <div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr><th>Name</th><th>Contact Info</th><th>Address</th><th>Products</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php foreach ($suppliers as $s): ?>
                <tr>
                    <td><?= e($s['Name']) ?></td>
                    <td><?= e($s['ContactInfo'] ?? '') ?></td>
                    <td><?= e($s['Address'] ?? '') ?></td>
                    <td><?= (int)$s['ProductCount'] ?></td>
                    <td>
                        <?php if ($canManage): ?>
                            <div class="actions">
                                <a class="btn btn-sm btn-secondary" href="<?= e(url('suppliers/edit.php?id=' . (int)$s['SupplierID'])) ?>">Edit</a>
                                <form method="post" action="<?= e(url('suppliers/delete.php')) ?>" class="inline-form"
                                      data-confirm-title="Delete this supplier?" data-confirm="&quot;<?= e($s['Name']) ?>&quot; will be removed. This cannot be undone." data-confirm-yes="Yes, delete it">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$s['SupplierID'] ?>">
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
