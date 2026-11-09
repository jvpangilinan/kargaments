<?php
// api/prefs.php — saves account settings from the "Aa" panel and the mascot's
// first-login question. POST only, with the page's CSRF token. Returns JSON.
//   guided=1 | guided=0
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/prefs.php';

header('Content-Type: application/json; charset=UTF-8');

$token = $_POST['csrf_token'] ?? '';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_string($token) || !hash_equals(csrf_token(), $token)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Please refresh the page and try again.']);
    exit;
}

if (isset($_POST['guided'])) {
    $on = (string)$_POST['guided'] === '1';
    $ok = km_set_guided($pdo, (int)$_SESSION['user_id'], $on);
    echo json_encode([
        'ok'      => $ok,
        'guided'  => $on,
        'message' => $ok ? ($on ? 'Guided mode is on.' : 'Guided mode is off.') : 'Sorry, that setting could not be saved.',
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'message' => 'Nothing to save.']);
