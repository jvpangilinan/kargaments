<?php
// categories/edit.php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/audit_log.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/csrf.php';

require_role(['Admin', 'Manager']);

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM Category WHERE CategoryID = ?");
$stmt->execute([$id]);
$category = $stmt->fetch();

if (!$category) {
    set_flash('error', 'Category not found.');
    header('Location: ' . url('categories/list.php'));
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name = trim($_POST['category_name'] ?? '');

    if ($name === '') {
        $errors[] = 'Category name is required.';
    } elseif (mb_strlen($name) > 255) {
        $errors[] = 'Category name is too long (255 characters maximum).';
    } else {
        $dup = $pdo->prepare("SELECT COUNT(*) FROM Category WHERE CategoryName = ? AND CategoryID <> ?");
        $dup->execute([$name, $id]);
        if ($dup->fetchColumn() > 0) {
            $errors[] = 'A category with that name already exists.';
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE Category SET CategoryName = ? WHERE CategoryID = ?");
        $stmt->execute([$name, $id]);

        log_action($pdo, (int)$_SESSION['user_id'], "Renamed category '{$category['CategoryName']}' to '$name'", 'Category', $id);

        set_flash('success', "Category \"$name\" was updated.");
        header('Location: ' . url('categories/list.php'));
        exit;
    }
    $category['CategoryName'] = $name;
}

$pageTitle = 'Edit Category';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Edit Category</h1>
    <a class="btn btn-secondary" href="<?= e(url('categories/list.php')) ?>">&larr; Back to Categories</a>
</div>

<?php foreach ($errors as $msg): ?>
    <div class="alert alert-error"><?= e($msg) ?></div>
<?php endforeach; ?>

<form class="form-box" method="POST" action="<?= e(url('categories/edit.php?id=' . $id)) ?>">
    <?= csrf_field() ?>
    <div class="form-group">
        <label for="category_name">Category Name</label>
        <input type="text" id="category_name" name="category_name" value="<?= e($category['CategoryName']) ?>" maxlength="255" required autofocus>
    </div>
    <div class="form-actions">
        <button class="btn" type="submit">Save Changes</button>
        <a class="btn btn-secondary" href="<?= e(url('categories/list.php')) ?>">Cancel</a>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
