<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
start_user_session();
require_login();

$pageTitle = 'Call history';
$activeNav = 'chat';
$pageScripts = ['call_history.js'];
require __DIR__ . '/includes/layout_start.php';
?>
<div class="page-wrap" style="max-width:800px">
    <div class="page-header">
        <h1>Call history</h1>
        <p class="text-muted">Recent voice and video calls.</p>
    </div>
    <div id="callHistoryList" class="list-stack">
        <div class="text-muted">Loading...</div>
    </div>
</div>
<?php require __DIR__ . '/includes/layout_end.php'; ?>
