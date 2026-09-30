<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
start_user_session();

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $username = trim((string) input('username', ''));
    $email = trim((string) input('email', ''));
    $password = (string) input('password', '');
    $confirm = (string) input('confirm_password', '');

    $err = validate_username($username);
    if ($err) {
        json_error($err);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_error('Invalid email.');
    }
    $pwErr = validate_password($password);
    if ($pwErr) {
        json_error($pwErr);
    }
    if ($password !== $confirm) {
        json_error('Passwords do not match.');
    }

    $pdo = db();
    $chk = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
    $chk->execute([$username, $email]);
    if ($chk->fetch()) {
        json_error('Username or email already exists.');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $ins = $pdo->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
    $ins->execute([$username, $email, $hash]);
    json_success('Account created', ['id' => (int) $pdo->lastInsertId()]);
}

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    require_rate_limit('login', RATE_LOGIN_MAX, RATE_LOGIN_WINDOW);
    $login = trim((string) input('login', ''));
    $password = (string) input('password', '');
    $user = get_user_by_login($login);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        json_error('Invalid credentials.', 401);
    }
    if ((int) $user['is_disabled'] === 1) {
        json_error('Account disabled.', 403);
    }
    login_user($user);
    json_success('Logged in', public_user($user));
}

if ($action === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    logout_user();
    json_success('Logged out');
}

if ($action === 'me') {
    require_login();
    $user = get_user_by_id((int) current_user_id());
    json_success('OK', public_user($user));
}

json_error('Unknown action', 404);
