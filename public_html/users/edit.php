<?php
// users/edit.php — Admin edits an existing account (name, role, linked supplier, password reset)
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/audit_log.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/csrf.php';

require_role(['Admin']);

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM User WHERE UserID = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    set_flash('error', 'User not found.');
    header('Location: ' . url('users/list.php'));
    exit;
}

$isSelf    = ($id === (int)$_SESSION['user_id']);
$roles     = $pdo->query("SELECT * FROM Role ORDER BY RoleID")->fetchAll();
$suppliers = $pdo->query("SELECT * FROM Supplier ORDER BY Name")->fetchAll();
$errors    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $fullName         = trim($_POST['full_name'] ?? '');
    $newPassword      = $_POST['new_password'] ?? '';
    $linkedSupplierId = (string)($_POST['supplier_id'] ?? '');
    // Admins cannot change their own role (prevents locking everyone out of user management).
    $roleId = $isSelf ? (string)$user['RoleID'] : (string)($_POST['role_id'] ?? '');

    if ($fullName === '') {
        $errors[] = 'Full name is required.';
    } elseif (mb_strlen($fullName) > 100) {
        $errors[] = 'Full name is too long (100 characters maximum).';
    }
    if ($newPassword !== '') {
        if (strlen($newPassword) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } elseif (strlen($newPassword) > 72) {
            $errors[] = 'New password must be 72 characters or fewer.';
        }
    }

    $selectedRole = null;
    foreach ($roles as $r) {
        if ((string)$r['RoleID'] === $roleId) {
            $selectedRole = $r;
        }
    }
    if ($selectedRole === null) {
        $errors[] = 'Please select a role.';
    }

    $isSupplierRole = $selectedRole !== null && $selectedRole['RoleName'] === 'Supplier';
    if ($isSupplierRole) {
        $supplierOk = false;
        foreach ($suppliers as $s) {
            if ((string)$s['SupplierID'] === $linkedSupplierId) {
                $supplierOk = true;
            }
        }
        if (!$supplierOk) {
            $errors[] = 'Please select which supplier this login belongs to.';
        }
    }

    if (empty($errors)) {
        $supplierValue = $isSupplierRole ? (int)$linkedSupplierId : null;
        if ($newPassword !== '') {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE User SET FullName = ?, RoleID = ?, SupplierID = ?, PasswordHash = ? WHERE UserID = ?");
            $stmt->execute([$fullName, (int)$roleId, $supplierValue, $hash, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE User SET FullName = ?, RoleID = ?, SupplierID = ? WHERE UserID = ?");
            $stmt->execute([$fullName, (int)$roleId, $supplierValue, $id]);
        }

        $note = $newPassword !== '' ? ' (password reset)' : '';
        log_action($pdo, (int)$_SESSION['user_id'], "Updated user account '{$user['Username']}'$note", 'User', $id);

        set_flash('success', "User \"$fullName\" was updated.");
        header('Location: ' . url('users/list.php'));
        exit;
    }

    // Keep what was typed so the form can be corrected.
    $user['FullName']   = $fullName;
    $user['RoleID']     = $roleId;
    $user['SupplierID'] = $linkedSupplierId;
}

$pageTitle = 'Edit User';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Edit User</h1>
    <a class="btn btn-secondary" href="<?= e(url('users/list.php')) ?>">&larr; Back to Users</a>
</div>

<?php foreach ($errors as $msg): ?>
    <div class="alert alert-error"><?= e($msg) ?></div>
<?php endforeach; ?>

<form class="form-box" method="POST" action="<?= e(url('users/edit.php?id=' . $id)) ?>">
    <?= csrf_field() ?>
    <div class="form-group">
        <label>Username</label>
        <input type="text" value="<?= e($user['Username']) ?>" disabled>
    </div>
    <div class="form-group">
        <label for="full_name">Full Name</label>
        <input type="text" id="full_name" name="full_name" value="<?= e($user['FullName']) ?>" maxlength="100" required>
    </div>
    <div class="form-group">
        <label for="role_id">Role</label>
        <select id="role_id" name="role_id" required onchange="toggleSupplierField()" <?= $isSelf ? 'disabled' : '' ?>>
            <?php foreach ($roles as $r): ?>
                <option value="<?= (int)$r['RoleID'] ?>" data-role-name="<?= e($r['RoleName']) ?>"
                        <?= (string)$user['RoleID'] === (string)$r['RoleID'] ? 'selected' : '' ?>>
                    <?= e($r['RoleName']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($isSelf): ?>
            <small>You cannot change your own role. Ask another Admin if it needs to change.</small>
        <?php endif; ?>
    </div>
    <div class="form-group" id="supplierField" style="display:none;">
        <label for="supplier_id">Linked Supplier</label>
        <select id="supplier_id" name="supplier_id">
            <option value="">-- Select Supplier --</option>
            <?php foreach ($suppliers as $s): ?>
                <option value="<?= (int)$s['SupplierID'] ?>" <?= (string)($user['SupplierID'] ?? '') === (string)$s['SupplierID'] ? 'selected' : '' ?>>
                    <?= e($s['Name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <small>Required for Supplier-role accounts — this is what the login sees in their portal.</small>
    </div>
    <div class="form-group">
        <label for="new_password">Reset Password (optional)</label>
        <input type="password" id="new_password" name="new_password" minlength="8" maxlength="72"
               placeholder="Leave blank to keep current password" autocomplete="new-password">
    </div>
    <div class="form-actions">
        <button class="btn" type="submit">Save Changes</button>
        <a class="btn btn-secondary" href="<?= e(url('users/list.php')) ?>">Cancel</a>
    </div>
</form>

<script>
function toggleSupplierField() {
    const sel = document.getElementById('role_id');
    const opt = sel.selectedOptions[0];
    const isSupplier = opt && opt.dataset.roleName === 'Supplier';
    document.getElementById('supplierField').style.display = isSupplier ? 'block' : 'none';
}
toggleSupplierField();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
