<?php
// categories/add.php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/audit_log.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/csrf.php';

require_role(['Admin', 'Manager']);

$errors = [];
$name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name = trim($_POST['category_name'] ?? '');

    if ($name === '') {
        $errors[] = 'Category name is required.';
    } elseif (mb_strlen($name) > 255) {
        $errors[] = 'Category name is too long (255 characters maximum).';
    } else {
        $dup = $pdo->prepare("SELECT COUNT(*) FROM Category WHERE CategoryName = ?");
        $dup->execute([$name]);
        if ($dup->fetchColumn() > 0) {
            $errors[] = 'A category with that name already exists.';
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO Category (CategoryName) VALUES (?)");
        $stmt->execute([$name]);
        $newId = (int)$pdo->lastInsertId();

        log_action($pdo, (int)$_SESSION['user_id'], "Created category '$name'", 'Category', $newId);

        set_flash('success', "Category \"$name\" was added.");
        header('Location: ' . url('categories/list.php'));
        exit;
    }
}

$pageTitle = 'Add Category';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Add Category</h1>
    <a class="btn btn-secondary" href="<?= e(url('categories/list.php')) ?>">&larr; Back to Categories</a>
</div>

<?php foreach ($errors as $msg): ?>
    <div class="alert alert-error"><?= e($msg) ?></div>
<?php endforeach; ?>

<form class="form-box" method="POST" action="<?= e(url('categories/add.php')) ?>">
    <?= csrf_field() ?>
    <div class="form-group">
        <label for="category_name">Category Name</label>
        <input type="text" id="category_name" name="category_name" value="<?= e($name) ?>" maxlength="255" required autofocus>
    </div>
    <div class="form-actions">
        <button class="btn" type="submit">Save Category</button>
        <a class="btn btn-secondary" href="<?= e(url('categories/list.php')) ?>">Cancel</a>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
