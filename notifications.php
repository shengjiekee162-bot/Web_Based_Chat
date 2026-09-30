<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
start_user_session();
require_login();

$pageTitle = 'Notifications';
$activeNav = 'notifications';
$pageScripts = ['notifications.js'];
require __DIR__ . '/includes/layout_start.php';
?>
<div class="page-wrap" style="max-width:800px">
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h1>Notifications</h1>
            <p class="text-muted mb-0">Friend requests, messages, calls, and community activity.</p>
        </div>
        <button type="button" class="btn btn-outline-primary btn-sm" id="markAllRead">Mark all read</button>
    </div>
    <div id="notifList" class="list-stack"></div>
</div>
<script>window.NOTIF_PAGE = true;</script>
<?php require __DIR__ . '/includes/layout_end.php'; ?>
