<?php
// auth/logout.php — sign out. POST only (with CSRF token), so a stray link or image
// on another site cannot log someone out. The header's "Log Out" button posts here.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/audit_log.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/csrf.php';

start_secure_session();

// Nobody signed in: just show the login page.
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . url('auth/login.php'));
    exit;
}

// Opening this URL directly (GET) does not log anyone out.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('index.php'));
    exit;
}

csrf_verify();

$userId = (int)$_SESSION['user_id'];
log_action($pdo, $userId, 'Logged out', 'User', $userId);

// Destroy the session and its cookie.
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $p['path'],
        'domain'   => $p['domain'],
        'secure'   => $p['secure'],
        'httponly' => $p['httponly'],
        'samesite' => 'Lax',
    ]);
}
session_destroy();

// Fresh session just to carry the "logged out" message to the login page.
start_secure_session();
set_flash('info', 'You have been logged out.');

header('Location: ' . url('auth/login.php'));
exit;
