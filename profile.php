<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
start_user_session();
require_login();

$pageTitle = 'Profile';
$activeNav = 'profile';
$pageScripts = ['profile.js'];
$user = get_user_by_id((int) current_user_id());
require __DIR__ . '/includes/layout_start.php';
?>
<div class="page-wrap" style="max-width:720px">
    <div class="page-header">
        <h1>Your profile</h1>
    </div>
    <div class="surface-card text-center mb-4">
        <img src="<?= e(avatar_url($user['avatar'] ?? null)) ?>" alt="" class="avatar-lg mb-3" id="profileAvatarPreview">
        <form id="avatarForm" class="d-inline-block">
            <label class="btn btn-outline-primary btn-sm">
                Change avatar
                <input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
            </label>
        </form>
    </div>
    <div class="surface-card mb-4">
        <h2 class="h5">Profile details</h2>
        <form id="profileForm">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" value="<?= e($user['username']) ?>" required maxlength="50">
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= e($user['email']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Status message</label>
                <input type="text" name="status_message" class="form-control" value="<?= e($user['status_message']) ?>" maxlength="255">
            </div>
            <button type="submit" class="btn btn-primary">Save profile</button>
            <div id="profileMsg" class="mt-2 small"></div>
        </form>
    </div>
    <div class="surface-card">
        <h2 class="h5">Change password</h2>
        <form id="passwordForm">
            <div class="mb-3">
                <label class="form-label">Current password</label>
                <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">New password</label>
                <input type="password" name="new_password" class="form-control" required minlength="8">
            </div>
            <div class="mb-3">
                <label class="form-label">Confirm new password</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">Update password</button>
            <div id="passwordMsg" class="mt-2 small"></div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/includes/layout_end.php'; ?>
