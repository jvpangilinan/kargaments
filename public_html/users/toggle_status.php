<?php
// users/toggle_status.php — disable / re-enable an account (accounts are never deleted).
// POST only, with CSRF token. The list page's Disable/Enable button posts here.
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/audit_log.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/csrf.php';

require_role(['Admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('users/list.php'));
    exit;
}
csrf_verify();

// Status is a free VARCHAR(50) column; accounts are either 'active' or 'inactive'.
// (The old version wrote 'disabled'; anything other than 'active' is treated as disabled by the app.)
const USER_STATUS_DISABLED = 'inactive';

$id = (int)($_POST['id'] ?? 0);

if ($id === (int)$_SESSION['user_id']) {
    set_flash('error', 'You cannot disable your own account.');
    header('Location: ' . url('users/list.php'));
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM User WHERE UserID = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    set_flash('error', 'User not found.');
    header('Location: ' . url('users/list.php'));
    exit;
}

$wasActive = ($user['Status'] === 'active');
$newStatus = $wasActive ? USER_STATUS_DISABLED : 'active';

$stmt = $pdo->prepare("UPDATE User SET Status = ? WHERE UserID = ?");
$stmt->execute([$newStatus, $id]);

log_action(
    $pdo,
    (int)$_SESSION['user_id'],
    ($wasActive ? 'Disabled' : 'Re-enabled') . " user account '{$user['Username']}'",
    'User',
    $id
);

set_flash('success', "\"{$user['FullName']}\" is now " . ($wasActive ? 'disabled' : 'active') . '.');
header('Location: ' . url('users/list.php'));
exit;
