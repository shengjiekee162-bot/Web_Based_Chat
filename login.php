<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
start_user_session();

if (current_user_id()) {
    redirect('chat.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Invalid security token.';
    } elseif (!rate_limit('login', RATE_LOGIN_MAX, RATE_LOGIN_WINDOW)) {
        $error = 'Too many login attempts. Please wait.';
    } else {
        $login = trim((string) ($_POST['login'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $user = get_user_by_login($login);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'Invalid username/email or password.';
        } elseif ((int) $user['is_disabled'] === 1) {
            $error = 'This account has been disabled.';
        } else {
            login_user($user);
            redirect('chat.php');
        }
    }
}

$pageTitle = 'Sign in';
$disabledNote = isset($_GET['disabled']);
ob_start();
?>
<h2 class="h4 mb-3">Sign in</h2>
<?php if ($disabledNote): ?>
<div class="alert alert-warning">Your account is disabled or the session expired.</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>
<form method="post" autocomplete="on" id="userLoginForm">
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label">Username or email</label>
        <input type="text" name="login" id="userLogin" class="form-control" required value="<?= e($_POST['login'] ?? '') ?>">
    </div>
    <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" id="userPassword" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-primary w-100">Sign in</button>
</form>
<p class="mt-2 mb-0 text-center small"><a href="<?= e(url('forgot_password.php')) ?>">Forgot password?</a></p>
<?php if (SHOW_DEMO_LOGIN): ?>
<div class="mt-3">
    <div class="small text-muted mb-2 text-center">DEMO ONLY — quick login</div>
    <div class="d-grid gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-demo-user="alice" data-demo-pass="Demo@12345">Fill &amp; sign in as alice</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-demo-user="bob" data-demo-pass="Demo@12345">Fill &amp; sign in as bob</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-demo-user="carol" data-demo-pass="Demo@12345">Fill &amp; sign in as carol</button>
    </div>
</div>
<script>
document.querySelectorAll('[data-demo-user]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('userLogin').value = btn.getAttribute('data-demo-user');
        document.getElementById('userPassword').value = btn.getAttribute('data-demo-pass');
        document.getElementById('userLoginForm').submit();
    });
});
</script>
<?php endif; ?>
<p class="mt-3 mb-0 text-center text-muted small">
    No account? <a href="<?= e(url('register.php')) ?>">Create one</a>
</p>
<p class="mt-2 mb-0 text-center">
    <a class="btn btn-outline-dark btn-sm w-100" href="admin/login.php">Administrator login</a>
</p>
<?php
$authContent = ob_get_clean();
require __DIR__ . '/includes/layout_guest.php';
