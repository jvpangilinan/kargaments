<?php
// includes/csrf.php — CSRF protection for every POST form.
//
// In a form:        put  csrf_field()  (echoed with the PHP short echo tag) inside the <form> element.
// In the handler:   if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_verify(); ... }
//
// Include after auth_check.php (or after the session has started).

require_once __DIR__ . '/../config/config.php';

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        start_secure_session();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('csrf_verify')) {
    function csrf_verify(): void
    {
        start_secure_session();
        $sent = $_POST['csrf_token'] ?? '';
        $real = $_SESSION['csrf_token'] ?? '';
        if (!is_string($sent) || $real === '' || !hash_equals($real, $sent)) {
            require_once __DIR__ . '/friendly_error.php';
            km_fail_page(
                'This page was open too long',
                'For your safety, nothing was saved. Go back, refresh the page, and try once more.',
                419
            );
        }
    }
}
