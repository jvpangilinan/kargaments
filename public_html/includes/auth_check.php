<?php
// includes/auth_check.php
// Include at the top of every protected page (before any output).
//  - starts the session
//  - redirects to login if nobody is logged in
//  - re-checks the account on every request (disabled accounts are logged out,
//    role changes take effect immediately)
//  - keeps Supplier logins inside supplier_portal/

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/friendly_error.php';

start_secure_session();

// Stop browsers from showing cached protected pages after logout (Back button).
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . url('auth/login.php'));
    exit;
}

$stmt = $pdo->prepare(
    "SELECT u.Status, u.FullName, u.SupplierID, r.RoleName
     FROM User u
     JOIN Role r ON u.RoleID = r.RoleID
     WHERE u.UserID = ?"
);
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch();

if (!$currentUser || $currentUser['Status'] !== 'active') {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    header('Location: ' . url('auth/login.php'));
    exit;
}

// Refresh session values from the database.
$_SESSION['role']        = $currentUser['RoleName'];
$_SESSION['full_name']   = $currentUser['FullName'];
$_SESSION['supplier_id'] = $currentUser['SupplierID'] !== null ? (int)$currentUser['SupplierID'] : null;

// Supplier logins may only use the supplier portal (plus the small api/ endpoints that
// save their own settings).
if ($_SESSION['role'] === 'Supplier'
    && strpos(current_script_rel(), 'supplier_portal/') !== 0
    && strpos(current_script_rel(), 'api/') !== 0) {
    header('Location: ' . url('supplier_portal/dashboard.php'));
    exit;
}

// Helper used everywhere to restrict pages by role, e.g.:
//   require_role(['Admin', 'Manager']);
if (!function_exists('require_role')) {
    function require_role(array $allowedRoles): void
    {
        if (!in_array($_SESSION['role'] ?? '', $allowedRoles, true)) {
            km_fail_page(
                'Sorry, this page is not for your account',
                'Your account (' . ($_SESSION['role'] ?? 'unknown') . ') cannot open this page. If you need it, ask a manager or the admin.',
                403
            );
        }
    }
}

// True if the logged-in user has one of the given roles (for showing/hiding buttons).
if (!function_exists('has_role')) {
    function has_role(array $roles): bool
    {
        return in_array($_SESSION['role'] ?? '', $roles, true);
    }
}
