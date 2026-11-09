<?php
// config/config.php — central settings for Karga-Ments.
// Loaded by config/db.php and includes/auth_check.php (safe to require more than once).

if (defined('KM_CONFIG_LOADED')) {
    return;
}
define('KM_CONFIG_LOADED', true);

date_default_timezone_set('Asia/Manila');

// ------------------------------------------------------------
// Environment: "local" on XAMPP (localhost), "production" anywhere else (Hostinger).
// ------------------------------------------------------------
$km_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('APP_ENV', preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$/', $km_host) ? 'local' : 'production');
unset($km_host);

// ------------------------------------------------------------
// Database credentials
// ------------------------------------------------------------
if (APP_ENV === 'local') {
    // XAMPP defaults
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'karga_ments');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
    // >>> HOSTINGER: fill these in from hPanel > Databases > MySQL Databases.
    // Hostinger prefixes names, e.g. u123456789_karga.
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'CHANGE_ME');
    define('DB_USER', 'CHANGE_ME');
    define('DB_PASS', 'CHANGE_ME');
}

// ------------------------------------------------------------
// Demo mode (presenting): lets an Admin start Chanelly's guided demo tour, which
// RESETS ALL DATA to the demo data and switches between the demo accounts.
// Set to false once the site holds real data you want to keep.
// ------------------------------------------------------------
if (!defined('DEMO_MODE')) {
    define('DEMO_MODE', true);
}

// Bump this when you upload new CSS/JS if a phone still shows the old look.
// (Files are also versioned by their last-modified time automatically.)
define('APP_VERSION', '4.0.0');

// ------------------------------------------------------------
// Error display: visible while developing, hidden (but logged) in production.
// ------------------------------------------------------------
error_reporting(E_ALL);
if (APP_ENV === 'local') {
    ini_set('display_errors', '1');
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// ------------------------------------------------------------
// BASE_URL: the web path where the app lives, with no trailing slash.
//   XAMPP  http://localhost/karga-ments/   -> "/karga-ments"
//   Hostinger domain root (public_html)    -> ""
// It is detected automatically. Only set the override if detection is wrong,
// e.g. define('KM_BASE_URL_OVERRIDE', '/karga-ments');  or  ''  for the domain root.
// ------------------------------------------------------------
if (!defined('KM_BASE_URL_OVERRIDE')) {
    define('KM_BASE_URL_OVERRIDE', null);
}

if (!function_exists('km_detect_base_url')) {
    function km_detect_base_url(): string
    {
        $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
        $appRoot = realpath(__DIR__ . '/..');
        if ($docRoot === false || $appRoot === false) {
            return '/karga-ments';
        }
        $docRoot = rtrim(str_replace('\\', '/', $docRoot), '/');
        $appRoot = rtrim(str_replace('\\', '/', $appRoot), '/');
        if (stripos($appRoot, $docRoot) !== 0) {
            return '/karga-ments';
        }
        return rtrim(substr($appRoot, strlen($docRoot)), '/');
    }
}
define('BASE_URL', KM_BASE_URL_OVERRIDE ?? km_detect_base_url());

// ------------------------------------------------------------
// Small helpers used by every page
// ------------------------------------------------------------
if (!function_exists('url')) {
    // url('products/list.php') -> "/karga-ments/products/list.php"
    function url(string $path = ''): string
    {
        return BASE_URL . '/' . ltrim($path, '/');
    }
}

if (!function_exists('e')) {
    // Escape text for safe output in HTML.
    function e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('asset')) {
    // asset('assets/css/style.css') -> "/assets/css/style.css?v=4.0.0-1730900000"
    // The version changes whenever the file changes, so browsers never keep an old copy.
    function asset(string $path): string
    {
        $file = __DIR__ . '/../' . ltrim($path, '/');
        $v    = APP_VERSION . (is_file($file) ? '-' . filemtime($file) : '');
        return url($path) . '?v=' . rawurlencode($v);
    }
}

if (!function_exists('km_label')) {
    // Everyday words for the status values stored in the database.
    //   km_label('Pending', 'order') -> "Waiting for delivery"
    function km_label(string $value, string $context = ''): string
    {
        $map = [
            'order' => [
                'Pending'   => 'Waiting for delivery',
                'Received'  => 'Delivered',
                'Cancelled' => 'Cancelled',
            ],
            'submission' => [
                'Pending'  => 'Waiting for review',
                'Approved' => 'Approved',
                'Rejected' => 'Not approved',
            ],
            'stock' => [
                'Stock-In' => 'Received stock',
                'Sale'     => 'Sold',
            ],
        ];
        return $map[$context][$value] ?? $value;
    }
}

if (!function_exists('current_script_rel')) {
    // Path of the running page relative to the app root, e.g. "products/list.php".
    function current_script_rel(): string
    {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        if (BASE_URL !== '' && stripos($script, BASE_URL . '/') === 0) {
            $script = substr($script, strlen(BASE_URL));
        }
        return ltrim($script, '/');
    }
}

// ------------------------------------------------------------
// Session start with safer cookie settings (safe to call repeatedly).
// ------------------------------------------------------------
if (!function_exists('start_secure_session')) {
    function start_secure_session(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        ini_set('session.use_strict_mode', '1');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}
