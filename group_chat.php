<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
start_user_session();
require_login();

$groupId = (int) ($_GET['id'] ?? 0);
if ($groupId <= 0 || !group_member($groupId, (int) current_user_id())) {
    redirect('groups.php');
}

$pageTitle = 'Group chat';
$activeNav = 'groups';
$pageScripts = ['groups.js'];
require __DIR__ . '/includes/layout_start.php';
?>
<div class="chat-layout group-chat-layout" id="groupChatLayout" data-group="<?= (int) $groupId ?>">
    <section class="chat-main-panel" style="grid-column: 1 / -1;">
        <div class="chat-thread">
            <header class="chat-thread-header">
                <a href="<?= e(url('groups.php')) ?>" class="btn btn-link"><i class="fa-solid fa-arrow-left"></i></a>
                <img src="<?= e(asset('images/default-group.svg')) ?>" alt="" class="avatar-sm" id="groupAvatar">
                <div class="flex-grow-1">
                    <div class="fw-semibold" id="groupName">Group</div>
                    <small class="text-muted" id="groupMeta">—</small>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="offcanvas" data-bs-target="#groupManage">
                    <i class="fa-solid fa-gear"></i>
                </button>
            </header>
            <div class="messages-scroll" id="groupMessages"></div>
            <div class="typing-line d-none" id="groupTyping"></div>
            <div class="reply-bar d-none" id="groupReplyBar">
                <div>
                    <small class="text-muted">Replying to</small>
                    <div id="groupReplyPreview" class="small"></div>
                </div>
                <button type="button" class="btn btn-sm btn-link" id="groupCancelReply"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <footer class="composer">
                <div class="composer-tools">
                    <label class="btn btn-light btn-sm mb-0"><i class="fa-solid fa-image"></i>
                        <input type="file" id="gImageInput" accept="image/*" hidden>
                    </label>
                    <label class="btn btn-light btn-sm mb-0"><i class="fa-solid fa-paperclip"></i>
                        <input type="file" id="gFileInput" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.zip" hidden>
                    </label>
                    <button type="button" class="btn btn-light btn-sm" id="gBtnVoice"><i class="fa-solid fa-microphone"></i></button>
                </div>
                <div class="voice-rec-bar d-none" id="gVoiceBar">
                    <span class="rec-dot"></span>
                    <span id="gVoiceTimer">0:00</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="gCancelVoice">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" id="gSendVoice">Send</button>
                </div>
                <form id="groupComposer" class="composer-form">
                    <textarea id="groupInput" class="form-control" rows="1" placeholder="Message the group..." maxlength="5000"></textarea>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i></button>
                </form>
            </footer>
        </div>
    </section>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="groupManage">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title">Manage group</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <form id="groupSettingsForm" class="mb-4">
            <div class="mb-3">
                <label class="form-label">Name</label>
                <input type="text" class="form-control" id="editGroupName" maxlength="120">
            </div>
            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea class="form-control" id="editGroupDesc" rows="2" maxlength="500"></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Avatar</label>
                <input type="file" class="form-control" id="editGroupAvatar" accept="image/*">
            </div>
            <button type="submit" class="btn btn-primary btn-sm" id="btnSaveGroup">Save</button>
        </form>
        <h6>Members</h6>
        <div id="groupMembersList" class="list-stack mb-3"></div>
        <div class="mb-3">
            <label class="form-label">Add friend</label>
            <select id="addMemberSelect" class="form-select form-select-sm"></select>
            <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="btnAddMember">Add</button>
        </div>
        <hr>
        <button type="button" class="btn btn-outline-warning btn-sm w-100 mb-2" id="btnLeaveGroup">Leave group</button>
        <button type="button" class="btn btn-outline-danger btn-sm w-100 d-none" id="btnDeleteGroup">Delete group</button>
    </div>
</div>
<?php require __DIR__ . '/includes/layout_end.php'; ?>
