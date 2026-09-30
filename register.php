<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
start_user_session();

if (current_user_id()) {
    redirect('chat.php');
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Invalid security token.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        $error = validate_username($username) ?? '';
        if ($error === '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        }
        if ($error === '') {
            $pwErr = validate_password($password);
            if ($pwErr) {
                $error = $pwErr;
            } elseif ($password !== $confirm) {
                $error = 'Password confirmation does not match.';
            }
        }

        if ($error === '') {
            $pdo = db();
            $chk = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
            $chk->execute([$username, $email]);
            if ($chk->fetch()) {
                $error = 'Username or email is already registered.';
            } else {
                try {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $ins = $pdo->prepare(
                        'INSERT INTO users (username, email, password_hash, presence) VALUES (?, ?, ?, ?)'
                    );
                    $ins->execute([$username, $email, $hash, 'offline']);
                    $success = true;
                } catch (Throwable $e) {
                    app_log('Register failed: ' . $e->getMessage());
                    $error = 'Unable to create account. Please try again.';
                }
            }
        }
    }
}

$pageTitle = 'Register';
ob_start();
?>
<h2 class="h4 mb-3">Create account</h2>
<?php if ($success): ?>
<div class="alert alert-success">Account created. <a href="<?= e(url('login.php')) ?>">Sign in</a></div>
<?php else: ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form method="post" autocomplete="on">
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control" required maxlength="50"
               value="<?= e($_POST['username'] ?? '') ?>">
    </div>
    <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" required
               value="<?= e($_POST['email'] ?? '') ?>">
    </div>
    <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required minlength="<?= PASSWORD_MIN_LENGTH ?>">
        <div class="form-text">At least <?= PASSWORD_MIN_LENGTH ?> characters, with letters and numbers.</div>
    </div>
    <div class="mb-3">
        <label class="form-label">Confirm password</label>
        <input type="password" name="confirm_password" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-primary w-100">Register</button>
</form>
<p class="mt-3 mb-0 text-center text-muted small">
    Already have an account? <a href="<?= e(url('login.php')) ?>">Sign in</a>
</p>
<?php endif; ?>
<?php
$authContent = ob_get_clean();
require __DIR__ . '/includes/layout_guest.php';
