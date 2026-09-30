<?php
/**
 * Layout start — main application chrome
 * Expects: $pageTitle, optional $activeNav, $currentUser
 */

declare(strict_types=1);

$currentUser = $currentUser ?? get_user_by_id((int) current_user_id());
$pageTitle = $pageTitle ?? APP_NAME;
$activeNav = $activeNav ?? '';
$notifCount = unread_notification_count((int) current_user_id());
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e($pageTitle) ?> — <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="<?= e(asset('css/app.css')) ?>?v=10" rel="stylesheet">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <script>
        (function () {
            var t = localStorage.getItem('wc_theme') || 'light';
            document.documentElement.setAttribute('data-theme', t);
        })();
        window.APP = {
            url: <?= json_encode(public_base_url()) ?>,
            csrf: <?= json_encode(csrf_token()) ?>,
            userId: <?= (int) current_user_id() ?>,
            username: <?= json_encode($currentUser['username'] ?? '') ?>,
            iceServers: <?= json_encode(ice_servers_config()) ?>,
            poll: {
                messages: <?= (int) POLL_MESSAGE_MS ?>,
                calls: <?= (int) POLL_CALL_MS ?>,
                notif: <?= (int) POLL_NOTIF_MS ?>
            }
        };
    </script>
</head>
<body class="app-body">
<div class="app-shell">
    <aside class="app-sidebar" id="appSidebar">
        <div class="brand-block">
            <a href="<?= e(url('chat.php')) ?>" class="brand-link">
                <span class="brand-mark"><i class="fa-solid fa-comments"></i></span>
                <span class="brand-text"><?= e(APP_NAME) ?></span>
            </a>
        </div>
        <nav class="side-nav">
            <a class="nav-item <?= $activeNav === 'chat' ? 'active' : '' ?>" href="<?= e(url('chat.php')) ?>">
                <i class="fa-solid fa-message"></i><span>Chats</span>
            </a>
            <a class="nav-item <?= $activeNav === 'friends' ? 'active' : '' ?>" href="<?= e(url('friends.php')) ?>">
                <i class="fa-solid fa-user-group"></i><span>Friends</span>
            </a>
            <a class="nav-item <?= $activeNav === 'groups' ? 'active' : '' ?>" href="<?= e(url('groups.php')) ?>">
                <i class="fa-solid fa-users"></i><span>Groups</span>
            </a>
            <a class="nav-item <?= $activeNav === 'forums' ? 'active' : '' ?>" href="<?= e(url('forums.php')) ?>">
                <i class="fa-solid fa-comments"></i><span>Community</span>
            </a>
            <a class="nav-item <?= $activeNav === 'notifications' ? 'active' : '' ?>" href="<?= e(url('notifications.php')) ?>">
                <i class="fa-solid fa-bell"></i><span>Alerts</span>
                <?php if ($notifCount > 0): ?>
                    <span class="badge-count" id="navNotifBadge"><?= (int) $notifCount ?></span>
                <?php else: ?>
                    <span class="badge-count d-none" id="navNotifBadge">0</span>
                <?php endif; ?>
            </a>
            <a class="nav-item <?= $activeNav === 'profile' ? 'active' : '' ?>" href="<?= e(url('profile.php')) ?>">
                <i class="fa-solid fa-circle-user"></i><span>Profile</span>
            </a>
            <a class="nav-item <?= $activeNav === 'settings' ? 'active' : '' ?>" href="<?= e(url('settings.php')) ?>">
                <i class="fa-solid fa-gear"></i><span>Settings</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <div class="me-chip">
                <img src="<?= e(avatar_url($currentUser['avatar'] ?? null)) ?>" alt="" class="avatar-sm">
                <div>
                    <div class="fw-semibold"><?= e($currentUser['username'] ?? '') ?></div>
                    <small class="text-muted presence-dot online">Online</small>
                </div>
            </div>
            <button type="button" class="theme-toggle" id="themeToggle" title="Toggle theme">
                <i class="fa-solid fa-moon"></i> Toggle theme
            </button>
            <a href="<?= e(url('logout.php')) ?>" class="btn btn-sm btn-outline-light w-100 mt-2" title="Logout">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </a>
        </div>
    </aside>
    <main class="app-main">
        <header class="app-topbar d-lg-none">
            <button type="button" class="btn btn-link text-dark" id="sidebarToggle" aria-label="Menu">
                <i class="fa-solid fa-bars"></i>
            </button>
            <strong><?= e(APP_NAME) ?></strong>
            <a href="<?= e(url('notifications.php')) ?>" class="btn btn-link text-dark position-relative">
                <i class="fa-solid fa-bell"></i>
            </a>
        </header>
        <div class="app-content">
