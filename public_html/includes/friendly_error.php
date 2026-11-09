<?php
// includes/friendly_error.php — a calm, plain-language error page with the mascot,
// used instead of bare "403" / "419" text. Stops the page after printing.
//   km_fail_page('Sorry, this page is not for your account', 'Ask a manager if you need it.', 403);
require_once __DIR__ . '/../config/config.php';

if (!function_exists('km_fail_page')) {
    function km_fail_page(string $title, string $message, int $httpCode = 400): void
    {
        if (!headers_sent()) {
            http_response_code($httpCode);
            header('Content-Type: text/html; charset=UTF-8');
        }
        $back = $_SERVER['HTTP_REFERER'] ?? '';
        // Only offer "Go back" to a page on this same site.
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($back === '' || parse_url($back, PHP_URL_HOST) !== $host) {
            $back = '';
        }
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> — Karga-Ments</title>
    <?php include __DIR__ . '/prepaint.php'; ?>
    <link rel="icon" href="<?= e(asset('assets/img/logo.png')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
</head>
<body class="km-plain">
<main class="content">
    <div class="km-oops">
        <img src="<?= e(asset('assets/img/chanelly.png')) ?>" alt="" class="km-oops-mascot km-wiggle">
        <h1><?= e($title) ?></h1>
        <p><?= e($message) ?></p>
        <div class="km-stack">
            <?php if ($back !== ''): ?>
                <a class="btn btn-block" href="<?= e($back) ?>">&larr; Go back</a>
            <?php endif; ?>
            <a class="btn btn-block <?= $back !== '' ? 'btn-secondary' : '' ?>" href="<?= e(url('index.php')) ?>">Go to the home page</a>
        </div>
    </div>
</main>
</body>
</html>
        <?php
        exit;
    }
}
