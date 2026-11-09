<?php
// auth/login.php — sign in.
// Step 2: BASE_URL paths, CSRF token, session ID regenerated on login,
// simple lockout after repeated failures, Suppliers sent to their portal.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/audit_log.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/csrf.php';

start_secure_session();

const LOGIN_MAX_FAILS    = 5;   // wrong attempts allowed...
const LOGIN_LOCK_SECONDS = 60;  // ...before this browser session must wait

function post_login_url(string $role): string
{
    return $role === 'Supplier' ? url('supplier_portal/dashboard.php') : url('index.php');
}

// Already signed in? Go straight to the right place.
if (isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ' . post_login_url((string)$_SESSION['role']));
    exit;
}

// Valid-looking bcrypt hash that matches nothing. Checked when the username does not
// exist so a wrong username and a wrong password take about the same time.
$dummyHash = '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ01234';

$error    = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $lockedUntil = (int)($_SESSION['login_locked_until'] ?? 0);

    if ($lockedUntil > time()) {
        $error = 'Too many tries. Please wait ' . ($lockedUntil - time()) . ' seconds, then try again.';
    } elseif ($username === '' || $password === '') {
        $error = 'Please type both your username and your password.';
    } else {
        $stmt = $pdo->prepare(
            "SELECT u.UserID, u.Username, u.PasswordHash, u.FullName, u.Status, u.SupplierID, r.RoleName
             FROM User u
             JOIN Role r ON u.RoleID = r.RoleID
             WHERE u.Username = ?"
        );
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        $hash = $user ? (string)$user['PasswordHash'] : $dummyHash;
        $passwordOk = password_verify($password, $hash) && $user !== false;

        if ($passwordOk && $user['Status'] !== 'active') {
            // Only revealed after the correct password, so it does not leak which usernames exist.
            $error = 'This login has been turned off. Please ask the store admin to turn it back on.';
        } elseif ($passwordOk) {
            // Success: new session ID (prevents session fixation) and a fresh CSRF token.
            session_regenerate_id(true);
            unset($_SESSION['login_fails'], $_SESSION['login_locked_until'], $_SESSION['csrf_token']);

            $_SESSION['user_id']     = (int)$user['UserID'];
            $_SESSION['username']    = $user['Username'];
            $_SESSION['full_name']   = $user['FullName'];
            $_SESSION['role']        = $user['RoleName'];
            $_SESSION['supplier_id'] = $user['SupplierID'] !== null ? (int)$user['SupplierID'] : null;

            // Upgrade the stored hash if PHP's default hashing has become stronger.
            if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                try {
                    $up = $pdo->prepare("UPDATE User SET PasswordHash = ? WHERE UserID = ?");
                    $up->execute([password_hash($password, PASSWORD_DEFAULT), $user['UserID']]);
                } catch (PDOException $ex) {
                    error_log('Password rehash failed: ' . $ex->getMessage());
                }
            }

            log_action($pdo, (int)$user['UserID'], 'Logged in', 'User', (int)$user['UserID']);

            header('Location: ' . post_login_url($user['RoleName']));
            exit;
        } else {
            $_SESSION['login_fails'] = (int)($_SESSION['login_fails'] ?? 0) + 1;
            if ($_SESSION['login_fails'] >= LOGIN_MAX_FAILS) {
                $_SESSION['login_locked_until'] = time() + LOGIN_LOCK_SECONDS;
                $_SESSION['login_fails'] = 0;
                $error = 'Too many tries. Please wait ' . LOGIN_LOCK_SECONDS . ' seconds, then try again.';
            } else {
                $error = 'That username or password is not right. Please check and try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1e293b">
    <title>Log in — Karga-Ments</title>
    <?php include __DIR__ . '/../includes/prepaint.php'; ?>
    <link rel="icon" href="<?= e(asset('assets/img/logo.png')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
    <script>window.KM = <?= json_encode([
        'base' => BASE_URL, 'page' => 'auth/login.php', 'role' => '', 'guided' => false,
        'state' => new stdClass(), 'demo' => ['enabled' => false, 'active' => false],
        'mascot' => asset('assets/img/chanelly.png'),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
    <script src="<?= e(asset('assets/js/km.js')) ?>" defer></script>
</head>
<body class="login-page">
    <main class="login-box">
        <button type="button" class="km-aa-btn" id="km-aa-btn" title="Text size and display">Aa</button>
        <img src="<?= e(asset('assets/img/logo.png')) ?>" alt="Karga-Ments logo" class="login-logo">
        <h1>Karga-Ments</h1>
        <p class="subtitle">Inventory Management System · Hardware Store</p>

        <div class="login-mascot">
            <img src="<?= e(asset('assets/img/chanelly.png')) ?>" alt="" class="km-wiggle">
            <span>Enter your username and password, then select <strong>Log in</strong>. Need assistance? Chanelly can help.</span>
        </div>

        <?php render_flash(); ?>

        <?php if ($error): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= e(url('auth/login.php')) ?>">
            <?= csrf_field() ?>
            <label for="username">Username</label>
            <input type="text" id="username" name="username" value="<?= e($username) ?>" required autofocus
                   autocomplete="username" autocapitalize="none" spellcheck="false">

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
            <label class="km-showpw"><input type="checkbox" data-km-showpw="password"> Show password</label>

            <button type="submit" class="btn btn-block">Log in</button>
        </form>
    </main>
    <button type="button" class="km-help-btn" id="km-help-btn" aria-haspopup="dialog">
        <img src="<?= e(asset('assets/img/chanelly.png')) ?>" alt=""><span>Need help?</span>
    </button>
</body>
</html>
