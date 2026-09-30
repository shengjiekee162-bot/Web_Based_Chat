<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
start_user_session();
require_login();

$pageTitle = 'Groups';
$activeNav = 'groups';
$pageScripts = ['groups.js'];
require __DIR__ . '/includes/layout_start.php';
?>
<div class="page-wrap">
    <div class="page-header d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
            <h1>Groups</h1>
            <p class="text-muted mb-0">Create group chats and manage members.</p>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createGroupModal">
            <i class="fa-solid fa-plus"></i> New group
        </button>
    </div>
    <div id="groupsList" class="group-grid"></div>
</div>

<div class="modal fade" id="createGroupModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="createGroupForm">
            <div class="modal-header">
                <h5 class="modal-title">Create group</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" required maxlength="120">
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2" maxlength="500"></textarea>
                </div>
                <div class="mb-0">
                    <label class="form-label">Add friends</label>
                    <div id="friendPicker" class="friend-picker"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Create</button>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/includes/layout_end.php'; ?>
