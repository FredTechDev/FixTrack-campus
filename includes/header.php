<?php
/**
 * FixTrack Campus - Shared application header (navigation, flash messages).
 *
 * Expected optional variables:
 *   $page_title  string  Browser/page title
 *   $active_nav  string  Key of the active navigation item
 *   $body_class  string  Extra class for <body>
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$page_title = $page_title ?? APP_NAME;
$active_nav = $active_nav ?? '';
$body_class = $body_class ?? '';

$role = $_SESSION['user_role'] ?? null;
$unread = 0;
if (is_logged_in()) {
    $unread = get_unread_notifications_count(get_db(), (int)$_SESSION['user_id']);
}

// Role-aware navigation definition. Each entry: key => [label, path, icon]
$nav_links = [];
if ($role === ROLE_STUDENT) {
    $nav_links = [
        'dashboard'     => ['Dashboard',   'student/dashboard.php',     'gauge-high'],
        'new-report'    => ['Report Issue','student/report-new.php',    'circle-plus'],
        'my-reports'    => ['My Reports',  'student/reports.php',       'clipboard-list'],
        'notifications' => ['Alerts',      'notifications.php', 'bell'],
    ];
} elseif ($role === ROLE_ADMIN) {
    $nav_links = [
        'dashboard'  => ['Overview',   'admin/dashboard.php',  'gauge-high'],
        'reports'    => ['Reports',    'admin/reports.php',    'clipboard-list'],
        'analytics'  => ['Analytics',  'admin/analytics.php',  'chart-simple'],
        'users'      => ['People',     'admin/users.php',      'users'],
        'facilities' => ['Facilities', 'admin/facilities.php', 'building'],
        'categories' => ['Categories', 'admin/categories.php', 'layer-group'],
        'qr-codes'   => ['QR Codes',   'admin/qr-codes.php',   'qrcode'],
    ];
} elseif ($role === ROLE_TECHNICIAN) {
    $nav_links = [
        'dashboard' => ['Dashboard', 'technician/dashboard.php', 'gauge-high'],
        'jobs'      => ['My Jobs',   'technician/jobs.php',      'wrench'],
    ];
} else {
    $nav_links = [
        'home'    => ['Home',     'index.php',     'house'],
        'features'=> ['Features', 'index.php#features', 'list-check'],
        'about'   => ['About',    'index.php#about', 'circle-info'],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e(APP_TAGLINE) ?> - report, track and resolve campus maintenance issues.">
    <meta name="theme-color" content="#1e40af">
    <title><?= e($page_title) ?> &middot; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/vendor/fontawesome/all.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/pages.css')) ?>">
    <?php if (!empty($extra_css)): ?>
        <?php foreach ((array)$extra_css as $css): ?>
            <link rel="stylesheet" href="<?= e(url($css)) ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body class="<?= e($body_class) ?>">
<div class="app-wrapper">
    <header class="navbar">
        <div class="nav-container">
            <a class="brand-link" href="<?= e(url(is_logged_in() ? 'dashboard.php' : 'index.php')) ?>">
                <span class="brand-icon"><i class="fas fa-tools" aria-hidden="true"></i></span>
                <span>FixTrack <span class="brand-badge">Campus</span></span>
            </a>

            <button type="button" class="nav-toggle" id="navToggle" aria-label="Toggle navigation" aria-expanded="false" aria-controls="navMenu">
                <i class="fas fa-bars" aria-hidden="true"></i>
            </button>

            <nav class="nav-menu" id="navMenu" aria-label="Main navigation">
                <?php foreach ($nav_links as $key => $link): ?>
                    <a class="nav-link <?= $active_nav === $key ? 'active' : '' ?>"
                       href="<?= e(url($link[1])) ?>"
                       <?= $active_nav === $key ? 'aria-current="page"' : '' ?>>
                        <i class="fas fa-<?= e($link[2]) ?> mr-1" aria-hidden="true"></i><?= e($link[0]) ?>
                    </a>
                <?php endforeach; ?>

                <?php if (is_logged_in()): ?>
                    <?php $user = current_user(); ?>
                    <div class="nav-user">
                        <a class="notif-badge-btn" href="<?= e(url('notifications.php')) ?>"
                           title="Notifications" aria-label="Notifications, <?= $unread ?> unread">
                            <i class="fas fa-bell" aria-hidden="true"></i>
                            <?php if ($unread > 0): ?><span class="notif-counter"><?= $unread > 99 ? '99+' : (int)$unread ?></span><?php endif; ?>
                        </a>
                        <div class="user-badge" title="<?= e($user['name']) ?> (<?= e(ucfirst($role)) ?>)">
                            <span class="user-avatar"><?= e(strtoupper(mb_substr($user['name'], 0, 2))) ?></span>
                            <span class="d-none d-md-inline"><?= e(explode(' ', $user['name'])[0]) ?></span>
                        </div>
                        <a class="btn btn-outline btn-sm" href="<?= e(url('logout.php')) ?>" title="Sign out">
                            <i class="fas fa-right-from-bracket" aria-hidden="true"></i>
                            <span class="d-none d-md-inline">Logout</span>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="nav-user">
                        <a class="btn btn-outline btn-sm" href="<?= e(url('login.php')) ?>"><i class="fas fa-right-to-bracket mr-1"></i>Sign In</a>
                        <a class="btn btn-primary btn-sm" href="<?= e(url('register.php')) ?>"><i class="fas fa-user-plus mr-1"></i>Register</a>
                    </div>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            <?= render_flash_messages() ?>
