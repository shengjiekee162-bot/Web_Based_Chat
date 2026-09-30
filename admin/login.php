<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
start_admin_session();

if (current_admin_id()) {
    redirect('admin/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Invalid security token.';
    } elseif (!rate_limit('admin_login', RATE_LOGIN_MAX, RATE_LOGIN_WINDOW)) {
        $error = 'Too many attempts.';
    } else {
        $login = trim((string) ($_POST['login'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $admin = get_admin_by_login($login);
        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            $error = 'Invalid credentials.';
        } else {
            login_admin($admin);
            redirect('admin/index.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(asset('css/app.css')) ?>" rel="stylesheet">
</head>
<body class="auth-body">
<div class="auth-wrap">
    <div class="auth-panel">
        <div class="auth-brand">
            <h1><?= e(APP_NAME) ?> Admin</h1>
            <p>Separate administrator access</p>
        </div>
        <div class="auth-card">
            <h2 class="h5 mb-3">Administrator sign in</h2>
            <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
            <form method="post" id="adminLoginForm">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">Username or email</label>
                    <input type="text" name="login" id="adminLogin" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" id="adminPassword" class="form-control" required>
                </div>
                <button class="btn btn-dark w-100" type="submit">Sign in</button>
            </form>
            <?php if (SHOW_DEMO_LOGIN): ?>
            <button type="button" class="btn btn-outline-secondary w-100 mt-2" id="btnFillDemoLogin">
                Fill demo account &amp; sign in
            </button>
            <p class="small text-muted mt-3 mb-0">DEMO ONLY: admin / Admin@ChangeMe1 — change immediately in production.</p>
            <script>
            document.getElementById('btnFillDemoLogin').addEventListener('click', function () {
                document.getElementById('adminLogin').value = 'admin';
                document.getElementById('adminPassword').value = 'Admin@ChangeMe1';
                document.getElementById('adminLoginForm').submit();
            });
            </script>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
