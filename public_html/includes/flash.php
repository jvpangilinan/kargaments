<?php
// includes/flash.php
// Simple one-time flash messages stored in the session, shown after a redirect.
// Types: success, error, warning, info.
// Usage:
//   set_flash('success', 'Product created.');
//   header('Location: list.php');
//   exit;
//
// The header include renders the message automatically on the next page.

require_once __DIR__ . '/../config/config.php';

if (!function_exists('set_flash')) {
    function set_flash(string $type, string $message): void
    {
        start_secure_session();
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }
}

if (!function_exists('render_flash')) {
    function render_flash(): void
    {
        if (empty($_SESSION['flash'])) {
            return;
        }
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);

        $classes = [
            'error'   => 'alert-error',
            'warning' => 'alert-warning',
            'info'    => 'alert-info',
        ];
        $class = $classes[$flash['type']] ?? 'alert-success';
        echo '<div class="alert ' . $class . '">' . e($flash['message']) . '</div>';
    }
}
