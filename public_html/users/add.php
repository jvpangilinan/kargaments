<?php
// users/add.php — the administrator creates accounts and assigns roles
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/audit_log.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/csrf.php';

require_role(['Admin']);

$roles     = $pdo->query("SELECT * FROM Role ORDER BY RoleID")->fetchAll();
$suppliers = $pdo->query("SELECT * FROM Supplier ORDER BY Name")->fetchAll();
$errors    = [];
$username = $fullName = $roleId = $linkedSupplierId = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $username         = trim($_POST['username'] ?? '');
    $fullName         = trim($_POST['full_name'] ?? '');
    $roleId           = (string)($_POST['role_id'] ?? '');
    $password         = $_POST['password'] ?? '';
    $linkedSupplierId = (string)($_POST['supplier_id'] ?? '');

    if (!preg_match('/^[A-Za-z0-9._-]{3,30}$/', $username)) {
        $errors[] = 'Username must be 3 to 30 characters: letters, numbers, dot, dash or underscore.';
    }
    if ($fullName === '') {
        $errors[] = 'Full name is required.';
    } elseif (mb_strlen($fullName) > 100) {
        $errors[] = 'Full name is too long (100 characters maximum).';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } elseif (strlen($password) > 72) {
        $errors[] = 'Password must be 72 characters or fewer.';
    }

    // The role must be a real one.
    $selectedRole = null;
    foreach ($roles as $r) {
        if ((string)$r['RoleID'] === $roleId) {
            $selectedRole = $r;
        }
    }
    if ($selectedRole === null) {
        $errors[] = 'Please select a role.';
    }

    // A Supplier-role login must be linked to a real supplier record.
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
        $check = $pdo->prepare("SELECT COUNT(*) FROM User WHERE Username = ?");
        $check->execute([$username]);
        if ($check->fetchColumn() > 0) {
            $errors[] = 'That username is already taken.';
        }
    }

    if (empty($errors)) {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                "INSERT INTO User (Username, PasswordHash, FullName, RoleID, SupplierID, Status)
                 VALUES (?, ?, ?, ?, ?, 'active')"
            );
            $stmt->execute([$username, $hash, $fullName, (int)$roleId, $isSupplierRole ? (int)$linkedSupplierId : null]);
            $newId = (int)$pdo->lastInsertId();

            log_action($pdo, (int)$_SESSION['user_id'], "Created user account '$username'", 'User', $newId);

            set_flash('success', "User \"$fullName\" was created.");
            header('Location: ' . url('users/list.php'));
            exit;
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                $errors[] = 'That username is already taken.';   // two admins saving at the same moment
            } else {
                throw $ex;
            }
        }
    }
}

$pageTitle = 'Add User';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Add User</h1>
    <a class="btn btn-secondary" href="<?= e(url('users/list.php')) ?>">&larr; Back to Users</a>
</div>

<?php foreach ($errors as $msg): ?>
    <div class="alert alert-error"><?= e($msg) ?></div>
<?php endforeach; ?>

<form class="form-box" method="POST" action="<?= e(url('users/add.php')) ?>">
    <?= csrf_field() ?>
    <div class="form-group">
        <label for="full_name">Full Name</label>
        <input type="text" id="full_name" name="full_name" value="<?= e($fullName) ?>" maxlength="100" required>
    </div>
    <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" value="<?= e($username) ?>" maxlength="30" required autocomplete="off">
        <small>3 to 30 characters: letters, numbers, dot, dash, underscore.</small>
    </div>
    <div class="form-group">
        <label for="password">Temporary Password</label>
        <input type="password" id="password" name="password" required minlength="8" maxlength="72" autocomplete="new-password">
        <small>At least 8 characters. Ask the user to change it after first login.</small>
    </div>
    <div class="form-group">
        <label for="role_id">Role</label>
        <select id="role_id" name="role_id" required onchange="toggleSupplierField()">
            <option value="">-- Select Role --</option>
            <?php foreach ($roles as $r): ?>
                <option value="<?= (int)$r['RoleID'] ?>" data-role-name="<?= e($r['RoleName']) ?>"
                        <?= $roleId === (string)$r['RoleID'] ? 'selected' : '' ?>>
                    <?= e($r['RoleName']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group" id="supplierField" style="display:none;">
        <label for="supplier_id">Linked Supplier</label>
        <select id="supplier_id" name="supplier_id">
            <option value="">-- Select Supplier --</option>
            <?php foreach ($suppliers as $s): ?>
                <option value="<?= (int)$s['SupplierID'] ?>" <?= $linkedSupplierId === (string)$s['SupplierID'] ? 'selected' : '' ?>>
                    <?= e($s['Name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <small>Required for Supplier-role accounts — this is what the login sees in their portal.</small>
    </div>
    <div class="form-actions">
        <button class="btn" type="submit">Create User</button>
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
