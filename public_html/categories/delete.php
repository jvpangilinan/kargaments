<?php
// categories/delete.php — POST only (with CSRF). The database blocks the delete if products still use it.
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/audit_log.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/csrf.php';

require_role(['Admin', 'Manager']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('categories/list.php'));
    exit;
}
csrf_verify();

$id = (int)($_POST['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM Category WHERE CategoryID = ?");
$stmt->execute([$id]);
$category = $stmt->fetch();

if (!$category) {
    set_flash('error', 'Category not found.');
    header('Location: ' . url('categories/list.php'));
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM Category WHERE CategoryID = ?");
    $stmt->execute([$id]);

    log_action($pdo, (int)$_SESSION['user_id'], "Deleted category '{$category['CategoryName']}'", 'Category', $id);

    set_flash('success', "Category \"{$category['CategoryName']}\" was deleted.");
} catch (PDOException $ex) {
    if ($ex->getCode() === '23000') {   // foreign key: products still assigned
        set_flash('error', "Cannot delete \"{$category['CategoryName']}\": products are still assigned to it.");
    } else {
        throw $ex;
    }
}

header('Location: ' . url('categories/list.php'));
exit;
