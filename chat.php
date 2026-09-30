<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
start_user_session();
require_login();

$pageTitle = 'Chats';
$activeNav = 'chat';
$pageScripts = ['chat.js'];
$peerId = (int) ($_GET['user'] ?? 0);
require __DIR__ . '/includes/layout_start.php';
?>
<div class="chat-layout" id="chatLayout" data-peer="<?= (int) $peerId ?>">
    <section class="chat-sidebar-panel" id="chatSidebar">
        <div class="panel-header">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="mb-0">Conversations</h2>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnMarkAllRead" title="Mark all read">
                    <i class="fa-solid fa-check-double"></i>
                </button>
            </div>
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" id="convSearch" placeholder="Search chats..." class="form-control form-control-sm">
            </div>
        </div>
        <div class="conv-list" id="convList">
            <div class="text-muted p-3 small">Loading...</div>
        </div>
    </section>

    <section class="chat-main-panel <?= $peerId ? '' : 'empty-state' ?>" id="chatMain">
        <div class="chat-empty" id="chatEmpty">
            <i class="fa-solid fa-comments"></i>
            <h3>Select a conversation</h3>
            <p>Choose a friend from your conversations or start chatting from Friends.</p>
        </div>
        <div class="chat-thread d-none" id="chatThread">
            <header class="chat-thread-header">
                <button type="button" class="btn btn-link d-lg-none" id="backToList"><i class="fa-solid fa-arrow-left"></i></button>
                <img src="<?= e(asset('images/default-avatar.svg')) ?>" alt="" class="avatar-sm" id="peerAvatar">
                <div class="flex-grow-1">
                    <div class="fw-semibold" id="peerName">—</div>
                    <small class="text-muted" id="peerStatus">—</small>
                </div>
                <div class="chat-header-actions">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnSearchMsgs" title="Search"><i class="fa-solid fa-magnifying-glass"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnVoiceCall" title="Voice call"><i class="fa-solid fa-phone"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnVideoCall" title="Video call"><i class="fa-solid fa-video"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary d-none d-xl-inline-flex" id="btnToggleInfo"><i class="fa-solid fa-circle-info"></i></button>
                </div>
            </header>
            <div class="messages-scroll" id="messagesScroll"></div>
            <div class="typing-line d-none" id="typingLine"><span></span> is typing...</div>
            <div class="search-in-chat d-none" id="chatSearchBar">
                <i class="fa-solid fa-magnifying-glass text-muted"></i>
                <input type="search" id="msgSearchInput" placeholder="Search in conversation...">
                <button type="button" class="btn btn-sm btn-link" id="closeMsgSearch"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="reply-bar d-none" id="replyBar">
                <div>
                    <small class="text-muted">Replying to</small>
                    <div id="replyPreview" class="small"></div>
                </div>
                <button type="button" class="btn btn-sm btn-link" id="cancelReply"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="upload-progress d-none" id="uploadProgress">
                <span class="small">Uploading...</span>
                <div class="bar"><span id="uploadBarFill"></span></div>
                <span class="small" id="uploadPct">0%</span>
            </div>
            <footer class="composer">
                <div class="composer-tools">
                    <button type="button" class="btn btn-light btn-sm" id="btnEmoji" title="Emoji"><i class="fa-regular fa-face-smile"></i></button>
                    <label class="btn btn-light btn-sm mb-0" title="Image">
                        <i class="fa-solid fa-image"></i>
                        <input type="file" id="imageInput" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
                    </label>
                    <label class="btn btn-light btn-sm mb-0" title="File">
                        <i class="fa-solid fa-paperclip"></i>
                        <input type="file" id="fileInput" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.zip" hidden>
                    </label>
                    <button type="button" class="btn btn-light btn-sm" id="btnVoiceRec" title="Voice message"><i class="fa-solid fa-microphone"></i></button>
                </div>
                <div class="emoji-picker d-none" id="emojiPicker">
                    😀 😂 😊 😍 😎 😢 😡 👍 👎 ❤️ 🔥 🎉 👏 🙏 ✅ ❌
                </div>
                <div class="voice-rec-bar d-none" id="voiceRecBar">
                    <span class="rec-dot"></span>
                    <span id="voiceTimer">0:00</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnCancelVoice">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" id="btnSendVoice">Send</button>
                </div>
                <form id="composerForm" class="composer-form">
                    <textarea id="messageInput" class="form-control" rows="1" placeholder="Type a message..." maxlength="5000"></textarea>
                    <button type="submit" class="btn btn-primary" title="Send"><i class="fa-solid fa-paper-plane"></i></button>
                </form>
            </footer>
        </div>
    </section>

    <aside class="chat-info-panel d-none d-xl-flex" id="chatInfo">
        <div class="info-inner text-center p-4">
            <img src="<?= e(asset('images/default-avatar.svg')) ?>" alt="" class="avatar-lg mb-3" id="infoAvatar">
            <h3 id="infoName">—</h3>
            <p class="text-muted" id="infoStatusMsg"></p>
            <p class="small" id="infoPresence"></p>
            <hr>
            <button type="button" class="btn btn-outline-warning btn-sm w-100 mb-2" id="btnClearChat">
                <i class="fa-solid fa-broom"></i> Clear conversation
            </button>
            <a class="btn btn-outline-secondary btn-sm w-100 mb-2" id="btnExportChat" href="#">
                <i class="fa-solid fa-download"></i> Export chat (CSV)
            </a>
            <button type="button" class="btn btn-outline-danger btn-sm w-100" id="btnBlockPeer">
                <i class="fa-solid fa-ban"></i> Block user
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm w-100 mt-2" id="btnReportPeer">
                <i class="fa-solid fa-flag"></i> Report user
            </button>
            <a class="btn btn-outline-secondary btn-sm w-100 mt-2" href="<?= e(url('call_history.php')) ?>">
                <i class="fa-solid fa-clock-rotate-left"></i> Call history
            </a>
        </div>
    </aside>
</div>

<div id="lightbox" class="lightbox d-none" role="dialog" aria-modal="true">
    <button type="button" class="lightbox-close" id="lightboxClose" aria-label="Close">&times;</button>
    <img src="" alt="" id="lightboxImg">
</div>

<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="reportForm">
            <div class="modal-header">
                <h5 class="modal-title">Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="reportTargetType" value="user">
                <input type="hidden" id="reportTargetId" value="">
                <label class="form-label">Reason</label>
                <textarea class="form-control" id="reportReason" required maxlength="500" rows="3"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Submit report</button>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/includes/layout_end.php'; ?>
