<?php
// suppliers/delete.php — POST only (with CSRF).
// Blocked when products, purchase orders or portal submissions still reference the supplier
// (foreign keys), and also when a Supplier login is linked to it (that foreign key would
// otherwise silently unlink the login instead of blocking the delete).
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/audit_log.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/csrf.php';

require_role(['Admin', 'Manager']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('suppliers/list.php'));
    exit;
}
csrf_verify();

$id = (int)($_POST['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM Supplier WHERE SupplierID = ?");
$stmt->execute([$id]);
$supplier = $stmt->fetch();

if (!$supplier) {
    set_flash('error', 'Supplier not found.');
    header('Location: ' . url('suppliers/list.php'));
    exit;
}

$linked = $pdo->prepare("SELECT COUNT(*) FROM User WHERE SupplierID = ?");
$linked->execute([$id]);
if ($linked->fetchColumn() > 0) {
    set_flash('error', "Cannot delete \"{$supplier['Name']}\": a Supplier login is linked to it. Disable or re-link that account first.");
    header('Location: ' . url('suppliers/list.php'));
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM Supplier WHERE SupplierID = ?");
    $stmt->execute([$id]);

    log_action($pdo, (int)$_SESSION['user_id'], "Deleted supplier '{$supplier['Name']}'", 'Supplier', $id);

    set_flash('success', "Supplier \"{$supplier['Name']}\" was deleted.");
} catch (PDOException $ex) {
    if ($ex->getCode() === '23000') {
        set_flash('error', "Cannot delete \"{$supplier['Name']}\": products, purchase orders or submissions still reference it.");
    } else {
        throw $ex;
    }
}

header('Location: ' . url('suppliers/list.php'));
exit;
