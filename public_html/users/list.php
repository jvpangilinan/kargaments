<?php
// users/list.php — System Admin module: manage user accounts and access roles
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/csrf.php';

require_role(['Admin']);

$users = $pdo->query(
    "SELECT u.*, r.RoleName, s.Name AS SupplierName
     FROM User u
     JOIN Role r ON u.RoleID = r.RoleID
     LEFT JOIN Supplier s ON u.SupplierID = s.SupplierID
     ORDER BY u.FullName"
)->fetchAll();

$pageTitle = 'User Accounts';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>User Accounts</h1>
    <a class="btn" href="<?= e(url('users/add.php')) ?>">+ Add User</a>
</div>

<div class="table-wrap">
<table class="data-table">
    <thead>
        <tr><th>Full Name</th><th>Username</th><th>Role</th><th>Linked Supplier</th><th>Status</th><th>Actions</th></tr>
    </thead>
    <tbody>
        <?php foreach ($users as $u): ?>
            <?php $isActive = ($u['Status'] === 'active'); $isSelf = ((int)$u['UserID'] === (int)$_SESSION['user_id']); ?>
            <tr>
                <td>
                    <?= e($u['FullName']) ?>
                    <?php if ($isSelf): ?><span class="text-muted">(you)</span><?php endif; ?>
                </td>
                <td><?= e($u['Username']) ?></td>
                <td><?= e($u['RoleName']) ?></td>
                <td><?= e($u['SupplierName'] ?? '—') ?></td>
                <td>
                    <span class="badge <?= $isActive ? 'badge-active' : 'badge-inactive' ?>">
                        <?= $isActive ? 'Active' : 'Disabled' ?>
                    </span>
                </td>
                <td>
                    <div class="actions">
                        <a class="btn btn-sm btn-secondary" href="<?= e(url('users/edit.php?id=' . (int)$u['UserID'])) ?>">Edit</a>
                        <?php if (!$isSelf): ?>
                            <form method="post" action="<?= e(url('users/toggle_status.php')) ?>" class="inline-form"
                                  data-confirm-title="<?= $isActive ? 'Turn off this login?' : 'Turn this login back on?' ?>" data-confirm="<?= $isActive ? e($u['Username']) . ' will not be able to sign in until you turn it back on.' : e($u['Username']) . ' will be able to sign in again.' ?>" data-confirm-yes="<?= $isActive ? 'Yes, disable' : 'Yes, enable' ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$u['UserID'] ?>">
                                <button type="submit" class="btn btn-sm <?= $isActive ? 'btn-danger' : 'btn-secondary' ?>">
                                    <?= $isActive ? 'Disable' : 'Enable' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
