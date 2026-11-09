<?php
// api/demo.php — Demo mode (presenting) actions for the mascot's tour. POST + CSRF, JSON.
//   action=start          Admin only: reset all data to the demo data and begin the tour
//   action=reset          (demo running) reset the data again, e.g. "Restart chapter"
//   action=switch&as=...  (demo running) switch to one of the demo accounts
//   action=exit           (demo running) switch back to the Admin who started it
// Does nothing unless DEMO_MODE is true in config/config.php.
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/audit_log.php';
require_once __DIR__ . '/../includes/demo.php';

header('Content-Type: application/json; charset=UTF-8');

function demo_reply(bool $ok, string $message = '', array $extra = []): void
{
    if (!$ok) http_response_code(400);
    echo json_encode(['ok' => $ok, 'message' => $message, 'csrf' => csrf_token()] + $extra);
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_string($token) || !hash_equals(csrf_token(), $token)) {
    demo_reply(false, 'Please refresh the page and try again.');
}
if (!DEMO_MODE) {
    demo_reply(false, 'Demo mode is turned off for this site.');
}

$action = (string)($_POST['action'] ?? '');

try {
    switch ($action) {
        case 'start':
            if (($_SESSION['role'] ?? '') !== 'Admin') {
                demo_reply(false, 'Only an Admin can start the demo.');
            }
            $by = (string)($_SESSION['username'] ?? '');
            if ($by === '') {
                $q = $pdo->prepare("SELECT Username FROM User WHERE UserID = ?");
                $q->execute([(int)$_SESSION['user_id']]);
                $by = (string)$q->fetchColumn();
            }
            km_demo_reset($pdo);
            if (!km_login_as($pdo, $by)) {
                demo_reply(false, 'Could not sign back in after the reset.');
            }
            $_SESSION['km_demo'] = ['by' => $by, 'started' => time()];
            log_action($pdo, (int)$_SESSION['user_id'], 'Started demo mode (data reset to demo data)', 'Demo', 0);
            demo_reply(true, 'Demo data is ready.');

        case 'reset':
            if (!km_demo_active()) demo_reply(false, 'The demo is not running.');
            $as = (string)($_SESSION['username'] ?? '');
            km_demo_reset($pdo);
            km_login_as($pdo, $as) || km_login_as($pdo, $_SESSION['km_demo']['by']);
            demo_reply(true, 'Demo data was reset.');

        case 'switch':
            if (!km_demo_active()) demo_reply(false, 'The demo is not running.');
            $as = (string)($_POST['as'] ?? '');
            if (!in_array($as, KM_DEMO_USERS, true) && $as !== $_SESSION['km_demo']['by']) {
                demo_reply(false, 'That is not a demo account.');
            }
            if (!km_login_as($pdo, $as)) demo_reply(false, 'That demo account is missing or disabled.');
            demo_reply(true, 'Switched account.', ['role' => $_SESSION['role'], 'name' => $_SESSION['full_name']]);

        case 'exit':
            if (!km_demo_active()) demo_reply(true, 'The demo is not running.');
            $by = $_SESSION['km_demo']['by'];
            unset($_SESSION['km_demo']);
            km_login_as($pdo, $by);
            demo_reply(true, 'Demo finished.');
    }
} catch (Throwable $e) {
    error_log('Demo action failed: ' . $e->getMessage());
    demo_reply(false, 'Sorry, the demo could not do that. ' . (APP_ENV === 'local' ? $e->getMessage() : ''));
}

demo_reply(false, 'Unknown action.');
