<?php
// suppliers/add.php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/audit_log.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/csrf.php';

require_role(['Admin', 'Manager']);

$errors = [];
$name = $contact = $address = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name    = trim($_POST['name'] ?? '');
    $contact = trim($_POST['contact_info'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($name === '') {
        $errors[] = 'Supplier name is required.';
    } elseif (mb_strlen($name) > 255) {
        $errors[] = 'Supplier name is too long (255 characters maximum).';
    } else {
        $dup = $pdo->prepare("SELECT COUNT(*) FROM Supplier WHERE Name = ?");
        $dup->execute([$name]);
        if ($dup->fetchColumn() > 0) {
            $errors[] = 'A supplier with that name already exists.';
        }
    }
    if (mb_strlen($contact) > 255) $errors[] = 'Contact info is too long (255 characters maximum).';
    if (mb_strlen($address) > 255) $errors[] = 'Address is too long (255 characters maximum).';

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO Supplier (Name, ContactInfo, Address) VALUES (?, ?, ?)");
        $stmt->execute([$name, $contact !== '' ? $contact : null, $address !== '' ? $address : null]);
        $newId = (int)$pdo->lastInsertId();

        log_action($pdo, (int)$_SESSION['user_id'], "Created supplier '$name'", 'Supplier', $newId);

        set_flash('success', "Supplier \"$name\" was added.");
        header('Location: ' . url('suppliers/list.php'));
        exit;
    }
}

$pageTitle = 'Add Supplier';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Add Supplier</h1>
    <a class="btn btn-secondary" href="<?= e(url('suppliers/list.php')) ?>">&larr; Back to Suppliers</a>
</div>

<?php foreach ($errors as $msg): ?>
    <div class="alert alert-error"><?= e($msg) ?></div>
<?php endforeach; ?>

<form class="form-box" method="POST" action="<?= e(url('suppliers/add.php')) ?>">
    <?= csrf_field() ?>
    <div class="form-group">
        <label for="name">Supplier Name</label>
        <input type="text" id="name" name="name" value="<?= e($name) ?>" maxlength="255" required autofocus>
    </div>
    <div class="form-group">
        <label for="contact_info">Contact Info</label>
        <input type="text" id="contact_info" name="contact_info" placeholder="Phone / email" value="<?= e($contact) ?>" maxlength="255">
    </div>
    <div class="form-group">
        <label for="address">Address</label>
        <input type="text" id="address" name="address" value="<?= e($address) ?>" maxlength="255">
    </div>
    <div class="form-actions">
        <button class="btn" type="submit">Save Supplier</button>
        <a class="btn btn-secondary" href="<?= e(url('suppliers/list.php')) ?>">Cancel</a>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
