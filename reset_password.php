<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
start_user_session();

if (current_user_id()) {
    redirect('chat.php');
}

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$error = '';
$success = false;

$row = null;
if ($token !== '') {
    $stmt = db()->prepare(
        'SELECT pr.*, u.username FROM password_resets pr
         JOIN users u ON u.id = pr.user_id
         WHERE pr.token = ? AND pr.used_at IS NULL AND pr.expires_at >= NOW()
         LIMIT 1'
    );
    $stmt->execute([$token]);
    $row = $stmt->fetch() ?: null;
}

if (!$row) {
    $error = 'This reset link is invalid or has expired.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $row) {
    if (!verify_csrf()) {
        $error = 'Invalid security token.';
    } else {
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');
        $pwErr = validate_password($password);
        if ($pwErr) {
            $error = $pwErr;
        } elseif ($password !== $confirm) {
            $error = 'Password confirmation does not match.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $row['user_id']]);
            db()->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')->execute([$row['id']]);
            $success = true;
            $row = null;
        }
    }
}

$pageTitle = 'Reset password';
ob_start();
?>
<h2 class="h4 mb-3">Reset password</h2>
<?php if ($success): ?>
<div class="alert alert-success">Password updated. <a href="<?= e(url('login.php')) ?>">Sign in</a></div>
<?php elseif ($error && !$row): ?>
<div class="alert alert-danger"><?= e($error) ?></div>
<p class="mb-0"><a href="<?= e(url('forgot_password.php')) ?>">Request a new link</a></p>
<?php else: ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<p class="small text-muted">Resetting password for <strong><?= e($row['username']) ?></strong></p>
<form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <div class="mb-3">
        <label class="form-label">New password</label>
        <input type="password" name="password" class="form-control" required minlength="8">
    </div>
    <div class="mb-3">
        <label class="form-label">Confirm password</label>
        <input type="password" name="confirm_password" class="form-control" required>
    </div>
    <button class="btn btn-primary w-100" type="submit">Update password</button>
</form>
<?php endif; ?>
<?php
$authContent = ob_get_clean();
require __DIR__ . '/includes/layout_guest.php';
