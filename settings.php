<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
start_user_session();
require_login();

$pageTitle = 'Settings';
$activeNav = 'settings';
$pageScripts = ['settings.js'];
$user = get_user_by_id((int) current_user_id());
require __DIR__ . '/includes/layout_start.php';
?>
<div class="page-wrap" style="max-width:720px">
    <div class="page-header">
        <h1>Settings</h1>
        <p class="text-muted">Privacy, notifications, and call preferences.</p>
    </div>
    <div class="surface-card mb-4">
        <form id="settingsForm">
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="notifyDesktop" <?= !empty($user['notify_desktop']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="notifyDesktop">Desktop notifications</label>
            </div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="showLastSeen" <?= !empty($user['show_last_seen']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="showLastSeen">Show my last seen to others</label>
            </div>
            <div class="mb-3">
                <label class="form-label">Preferred call type</label>
                <select id="preferCallType" class="form-select">
                    <option value="voice" <?= ($user['prefer_call_type'] ?? '') === 'voice' ? 'selected' : '' ?>>Voice</option>
                    <option value="video" <?= ($user['prefer_call_type'] ?? '') === 'video' ? 'selected' : '' ?>>Video</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Save settings</button>
            <div id="settingsMsg" class="small mt-2"></div>
        </form>
    </div>
    <div class="surface-card">
        <h2 class="h6">Quick links</h2>
        <a href="<?= e(url('profile.php')) ?>" class="btn btn-outline-secondary btn-sm me-2">Edit profile</a>
        <a href="<?= e(url('chat.php')) ?>" class="btn btn-outline-secondary btn-sm">Back to chats</a>
    </div>
</div>
<?php require __DIR__ . '/includes/layout_end.php'; ?>
