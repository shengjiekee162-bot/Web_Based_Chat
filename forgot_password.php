<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
start_user_session();

if (current_user_id()) {
    redirect('chat.php');
}

$message = '';
$error = '';
$demoToken = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Invalid security token.';
    } elseif (!rate_limit('reset_request', 5, 300)) {
        $error = 'Too many requests. Please wait.';
    } else {
        $login = trim((string) ($_POST['login'] ?? ''));
        $user = get_user_by_login($login);
        // Always show same message to avoid account enumeration
        $message = 'If that account exists, a reset link has been prepared.';
        if ($user && (int) $user['is_disabled'] === 0) {
            $token = bin2hex(random_bytes(32));
            $expires = (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s');
            db()->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([(int) $user['id']]);
            db()->prepare(
                'INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)'
            )->execute([(int) $user['id'], $token, $expires]);
            // No mail server required for local/demo: expose one-time link on screen
            $demoToken = $token;
            app_log('Password reset token created for user ' . $user['id']);
        }
    }
}

$pageTitle = 'Forgot password';
ob_start();
?>
<h2 class="h4 mb-3">Forgot password</h2>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php if ($demoToken): ?>
<div class="alert alert-warning small">
    <strong>Local/demo mode:</strong> email is not configured. Use this one-time link:<br>
    <a href="<?= e(url('reset_password.php?token=' . $demoToken)) ?>">Reset password</a>
</div>
<?php endif; ?>
<form method="post">
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label">Username or email</label>
        <input type="text" name="login" class="form-control" required>
    </div>
    <button class="btn btn-primary w-100" type="submit">Continue</button>
</form>
<p class="mt-3 mb-0 text-center small"><a href="<?= e(url('login.php')) ?>">Back to sign in</a></p>
<?php
$authContent = ob_get_clean();
require __DIR__ . '/includes/layout_guest.php';
