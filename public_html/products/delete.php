<?php
// products/delete.php — Manager/Admin remove discontinued items. POST only (with CSRF).
// The database refuses the delete if stock transactions, order items or submissions exist.
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/audit_log.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/csrf.php';

require_role(['Admin', 'Manager']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('products/list.php'));
    exit;
}
csrf_verify();

$id = (int)($_POST['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM Product WHERE ProductID = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    set_flash('error', 'Product not found.');
    header('Location: ' . url('products/list.php'));
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM Product WHERE ProductID = ?");
    $stmt->execute([$id]);

    log_action($pdo, (int)$_SESSION['user_id'], "Deleted product '{$product['Name']}'", 'Product', $id);

    set_flash('success', "Product \"{$product['Name']}\" was deleted.");
} catch (PDOException $ex) {
    if ($ex->getCode() === '23000') {
        set_flash('error', "Cannot delete \"{$product['Name']}\": it has related stock transactions, purchase order or supplier submission records.");
    } else {
        throw $ex;
    }
}

header('Location: ' . url('products/list.php'));
exit;
