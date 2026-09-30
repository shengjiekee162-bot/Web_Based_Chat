<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
start_user_session();
require_login();

$pageTitle = 'Friends';
$activeNav = 'friends';
$pageScripts = ['friends.js'];
require __DIR__ . '/includes/layout_start.php';
?>
<div class="page-wrap">
    <div class="page-header">
        <h1>Friends</h1>
        <p class="text-muted">Search users, manage requests, and start conversations.</p>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="surface-card">
                <h2 class="h5">Find people</h2>
                <div class="input-group mb-3">
                    <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="search" id="userSearch" class="form-control" placeholder="Username or email">
                </div>
                <div id="searchResults" class="list-stack"></div>
            </div>
            <div class="surface-card mt-4">
                <h2 class="h5">Blocked</h2>
                <div id="blockedList" class="list-stack"></div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="surface-card mb-4">
                <h2 class="h5">Incoming requests</h2>
                <div id="incomingRequests" class="list-stack"></div>
            </div>
            <div class="surface-card mb-4">
                <h2 class="h5">Outgoing requests</h2>
                <div id="outgoingRequests" class="list-stack"></div>
            </div>
            <div class="surface-card">
                <h2 class="h5">Your friends</h2>
                <div id="friendsList" class="list-stack"></div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/layout_end.php'; ?>
