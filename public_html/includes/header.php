<?php
// includes/header.php  (v4)
// Shared page header: logo, menu with icons, "Aa" display settings, bottom tab bar on
// phones, and the settings that assets/js/km.js needs (help, guided mode, demo tour).
// Optional before including:  $pageTitle = 'Products';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/prefs.php';
require_once __DIR__ . '/guide_state.php';
require_once __DIR__ . '/demo.php';
require_once __DIR__ . '/icons.php';

$role    = $_SESSION['role'] ?? '';
$kmTitle = isset($pageTitle) && $pageTitle !== '' ? $pageTitle . ' — Karga-Ments' : 'Karga-Ments';
$kmPage  = current_script_rel();

// ---- Menu items: [path, icon, label, pages that light it up, show in phone tab bar] ----
$kmNav = [];
if ($role === 'Supplier') {
    $kmNav[] = ['supplier_portal/dashboard.php', 'home', 'My home', ['supplier_portal/dashboard.php'], true, 'nav-home'];
    $kmNav[] = ['supplier_portal/submit.php', 'send', 'Send an update', ['supplier_portal/submit.php'], true, 'nav-submit'];
} elseif ($role !== '') {
    $isMgr = in_array($role, ['Admin', 'Manager'], true);
    $kmNav[] = ['index.php', 'home', 'Home', ['index.php'], true, 'nav-home'];
    $kmNav[] = ['products/list.php', 'box', 'Products', ['products/'], true, 'nav-products'];
    $kmNav[] = ['stock/transaction_history.php', 'swap', 'Sales & deliveries', ['stock/'], true, 'nav-stock', 'Stock'];
    if ($isMgr) {
        $kmNav[] = ['purchase_orders/view_orders.php', 'receipt', 'Orders', ['purchase_orders/'], true, 'nav-orders'];
        $kmNav[] = ['suppliers/review_submissions.php', 'inbox', 'Supplier updates', ['suppliers/review_submissions.php'], false, 'nav-review'];
    }
    $kmNav[] = ['categories/list.php', 'tag', 'Categories', ['categories/'], false, 'nav-categories'];
    $kmNav[] = ['suppliers/list.php', 'truck', 'Suppliers', ['suppliers/list.php', 'suppliers/add.php', 'suppliers/edit.php', 'suppliers/delete.php'], false, 'nav-suppliers'];
    if ($isMgr) {
        $kmNav[] = ['reports/low_stock_report.php', 'chart', 'Reports', ['reports/low_stock_report.php', 'reports/stock_levels_report.php', 'reports/sales_history_report.php'], false, 'nav-reports'];
    }
    if ($role === 'Admin') {
        $kmNav[] = ['users/list.php', 'users', 'Users', ['users/'], false, 'nav-users'];
        $kmNav[] = ['reports/audit_log_report.php', 'list', 'Activity log', ['reports/audit_log_report.php'], false, 'nav-audit'];
        $kmNav[] = ['admin/backup.php', 'save', 'Backup', ['admin/'], false, 'nav-backup'];
    }
}

if (!function_exists('km_nav_active')) {
    function km_nav_active(array $matches, string $current): bool
    {
        foreach ($matches as $m) {
            if (strpos($current, $m) === 0) return true;
        }
        return false;
    }
}

// ---- Settings for assets/js/km.js ----
$kmUserId = (int)($_SESSION['user_id'] ?? 0);
$kmGuided = $kmUserId ? km_get_guided($pdo, $kmUserId) : false;
$kmDemo   = km_demo_active();
$kmJs = [
    'base'     => BASE_URL,
    'page'     => $kmPage,
    'csrf'     => csrf_token(),
    'role'     => $role,
    'name'     => $_SESSION['full_name'] ?? '',
    'username' => $_SESSION['username'] ?? '',
    'guided'   => $kmGuided,               // null = ask, true/false
    'state'    => ($kmGuided !== false || $kmDemo) && $role !== ''
                    ? km_guide_state($pdo, $role, $_SESSION['supplier_id'] ?? null) : new stdClass(),
    'demo'     => [
        'enabled' => DEMO_MODE && ($role === 'Admin' || $kmDemo),
        'active'  => $kmDemo,
    ],
    'mascot'   => asset('assets/img/chanelly.png'),
];
$kmFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$kmTabs  = array_values(array_filter($kmNav, fn($n) => $n[4] === true));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1e293b">
    <title><?= e($kmTitle) ?></title>
    <?php include __DIR__ . '/prepaint.php'; ?>
    <link rel="icon" href="<?= e(asset('assets/img/logo.png')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
    <script>window.KM = <?= json_encode($kmJs, $kmFlags) ?>;</script>
    <script src="<?= e(asset('assets/js/km.js')) ?>" defer></script>
</head>
<body class="km-role-<?= e(strtolower($role ?: 'guest')) ?>">
<a class="km-skip" href="#km-main">Skip to the page</a>
<header class="topbar">
    <a class="brand" href="<?= e(url('index.php')) ?>">
        <img src="<?= e(asset('assets/img/logo.png')) ?>" alt="" class="brand-logo">
        <span class="brand-text">Karga-Ments<small>Inventory Management System</small></span>
    </a>
    <nav class="km-nav" aria-label="Main menu">
        <?php $kmHidden = false; foreach ($kmNav as $n):
            if (!$n[4]) { $kmHidden = $kmHidden || km_nav_active($n[3], $kmPage); continue; } ?>
            <a href="<?= e(url($n[0])) ?>" data-km="<?= e($n[5]) ?>"<?= km_nav_active($n[3], $kmPage) ? ' class="active" aria-current="page"' : '' ?>>
                <?= km_icon($n[1]) ?><?= e($n[2]) ?>
            </a>
        <?php endforeach; ?>
        <?php if (count($kmNav) > count(array_filter($kmNav, fn($n) => $n[4]))): ?>
            <button type="button" class="km-nav-more<?= $kmHidden ? ' active' : '' ?>" data-km-more aria-haspopup="dialog" aria-controls="km-more">
                <?= km_icon('menu') ?>More
            </button>
        <?php endif; ?>
    </nav>
    <div class="user-info">
        <span class="km-who" title="<?= e($role) ?>"><?= km_icon('user') ?> <?= e($_SESSION['full_name'] ?? '') ?> <small>(<?= e($role) ?>)</small></span>
        <button type="button" class="km-aa-btn" id="km-aa-btn" aria-haspopup="dialog" aria-controls="km-aa-panel" title="Text size and display">Aa</button>
        <form method="post" action="<?= e(url('auth/logout.php')) ?>" class="inline-form km-logout"><?= csrf_field() ?><button type="submit" class="link-button"><?= km_icon('logout') ?> Log out</button></form>
    </div>
</header>

<?php if ($kmTabs): ?>
<nav class="km-tabbar" aria-label="Quick menu">
    <?php foreach ($kmTabs as $n): ?>
        <a href="<?= e(url($n[0])) ?>" data-km="<?= e($n[5]) ?>"<?= km_nav_active($n[3], $kmPage) ? ' class="active" aria-current="page"' : '' ?>>
            <?= km_icon($n[1]) ?><span><?= e($n[6] ?? $n[2]) ?></span>
        </a>
    <?php endforeach; ?>
    <button type="button" id="km-more-btn" data-km-more aria-haspopup="dialog" aria-controls="km-more">
        <?= km_icon('menu') ?><span>More</span>
    </button>
</nav>

<div class="km-sheet" id="km-more" role="dialog" aria-modal="true" aria-label="All pages" hidden>
    <div class="km-sheet-box">
        <div class="km-sheet-head"><strong>All pages</strong><button type="button" class="km-x" data-km-close aria-label="Close"><?= km_icon('close') ?></button></div>
        <div class="km-sheet-list">
            <?php foreach ($kmNav as $n): ?>
                <a href="<?= e(url($n[0])) ?>" data-km="<?= e($n[5]) ?>"<?= km_nav_active($n[3], $kmPage) ? ' class="active"' : '' ?>><?= km_icon($n[1]) ?><?= e($n[2]) ?></a>
            <?php endforeach; ?>
        </div>
        <p class="text-muted">Signed in as <?= e($_SESSION['full_name'] ?? '') ?> (<?= e($role) ?>)</p>
        <form method="post" action="<?= e(url('auth/logout.php')) ?>"><?= csrf_field() ?><button type="submit" class="btn btn-secondary btn-block"><?= km_icon('logout') ?> Log out</button></form>
    </div>
</div>
<?php endif; ?>

<main class="content" id="km-main">
<?php render_flash(); ?>
