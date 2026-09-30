<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
start_admin_session();
require_admin();

$pdo = db();
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');
    $stmt = $pdo->prepare('SELECT * FROM admins WHERE id = ?');
    $stmt->execute([(int) current_admin_id()]);
    $admin = $stmt->fetch();
    if (!$admin || !password_verify($current, $admin['password_hash'])) {
        $error = 'Current password is incorrect.';
    } else {
        $pwErr = validate_password($new);
        if ($pwErr) {
            $error = $pwErr;
        } elseif ($new !== $confirm) {
            $error = 'Password confirmation does not match.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE admins SET password_hash = ?, must_change_password = 0 WHERE id = ?')
                ->execute([$hash, current_admin_id()]);
            session_regenerate_id(true);
            $msg = 'Password updated successfully.';
        }
    }
}

$adminPage = 'password';
require __DIR__ . '/_layout.php';
?>
<h1 class="h3 mb-4">Change password</h1>
<?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="surface-card" style="max-width:480px">
    <form method="post">
        <?= csrf_field() ?>
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
        <button class="btn btn-primary">Update password</button>
    </form>
</div>
<?php require __DIR__ . '/_layout_end.php'; ?>
