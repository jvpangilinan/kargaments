<?php
// categories/list.php — everyone signed in (except Suppliers) can view; Admin/Manager manage.
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/csrf.php';

$categories = $pdo->query(
    "SELECT c.*, COUNT(p.ProductID) AS ProductCount
     FROM Category c
     LEFT JOIN Product p ON p.CategoryID = c.CategoryID
     GROUP BY c.CategoryID
     ORDER BY c.CategoryName"
)->fetchAll();

$canManage = has_role(['Admin', 'Manager']);

$pageTitle = 'Categories';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Categories</h1>
    <?php if ($canManage): ?>
        <a class="btn" href="<?= e(url('categories/add.php')) ?>">+ Add Category</a>
    <?php endif; ?>
</div>

<?php if (count($categories) === 0): ?>
    <div class="empty-state">No categories yet.</div>
<?php else: ?>
    <div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr><th>Category Name</th><th>Products</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php foreach ($categories as $c): ?>
                <tr>
                    <td><?= e($c['CategoryName']) ?></td>
                    <td><?= (int)$c['ProductCount'] ?></td>
                    <td>
                        <?php if ($canManage): ?>
                            <div class="actions">
                                <a class="btn btn-sm btn-secondary" href="<?= e(url('categories/edit.php?id=' . (int)$c['CategoryID'])) ?>">Edit</a>
                                <form method="post" action="<?= e(url('categories/delete.php')) ?>" class="inline-form"
                                      data-confirm-title="Delete this category?" data-confirm="&quot;<?= e($c['CategoryName']) ?>&quot; will be removed. This cannot be undone." data-confirm-yes="Yes, delete it">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$c['CategoryID'] ?>">
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
