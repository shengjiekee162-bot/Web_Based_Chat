<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
start_user_session();
require_login();

$uid = (int) current_user_id();
$action = $_GET['action'] ?? ($_POST['action'] ?? input('action', ''));
$pdo = db();

if ($action === 'get') {
    $user = get_user_by_id($uid);
    $data = public_user($user);
    $data['email'] = $user['email'];
    json_success('OK', $data);
}

if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $status = trim((string) input('status_message', ''));
    $username = trim((string) input('username', ''));
    $email = trim((string) input('email', ''));

    if (strlen($status) > 255) {
        json_error('Status message is too long.');
    }

    $user = get_user_by_id($uid);
    $newUsername = $user['username'];
    $newEmail = $user['email'];

    if ($username !== '' && $username !== $user['username']) {
        $err = validate_username($username);
        if ($err) {
            json_error($err);
        }
        $chk = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
        $chk->execute([$username, $uid]);
        if ($chk->fetch()) {
            json_error('Username already taken.');
        }
        $newUsername = $username;
    }

    if ($email !== '' && $email !== $user['email']) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_error('Invalid email.');
        }
        $chk = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $chk->execute([$email, $uid]);
        if ($chk->fetch()) {
            json_error('Email already in use.');
        }
        $newEmail = $email;
    }

    $stmt = $pdo->prepare('UPDATE users SET username = ?, email = ?, status_message = ? WHERE id = ?');
    $stmt->execute([$newUsername, $newEmail, $status, $uid]);
    $_SESSION['username'] = $newUsername;
    json_success('Profile updated', public_user(get_user_by_id($uid)));
}

if ($action === 'password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $current = (string) input('current_password', '');
    $new = (string) input('new_password', '');
    $confirm = (string) input('confirm_password', '');
    $user = get_user_by_id($uid);
    if (!password_verify($current, $user['password_hash'])) {
        json_error('Current password is incorrect.');
    }
    $pwErr = validate_password($new);
    if ($pwErr) {
        json_error($pwErr);
    }
    if ($new !== $confirm) {
        json_error('Password confirmation does not match.');
    }
    $hash = password_hash($new, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $stmt->execute([$hash, $uid]);
    session_regenerate_id(true);
    json_success('Password changed');
}

if ($action === 'avatar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (empty($_FILES['avatar'])) {
        json_error('No file uploaded.');
    }
    $rules = upload_rules('avatar');
    $stored = store_upload($_FILES['avatar'], $rules['subdir'], $rules['ext'], $rules['mime'], $rules['max']);
    if (!$stored) {
        json_error('Invalid avatar. Use JPG/PNG/GIF/WEBP under 2MB.');
    }
    $user = get_user_by_id($uid);
    if (!empty($user['avatar'])) {
        $old = APP_ROOT . '/uploads/avatars/' . basename($user['avatar']);
        if (is_file($old)) {
            @unlink($old);
        }
    }
    $stmt = $pdo->prepare('UPDATE users SET avatar = ? WHERE id = ?');
    $stmt->execute([$stored['stored'], $uid]);
    json_success('Avatar updated', ['avatar_url' => avatar_url($stored['stored'])]);
}

if ($action === 'settings' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $user = get_user_by_id($uid);
    json_success('OK', [
        'notify_desktop' => (int) ($user['notify_desktop'] ?? 1) === 1,
        'prefer_call_type' => $user['prefer_call_type'] ?? 'voice',
        'show_last_seen' => (int) ($user['show_last_seen'] ?? 1) === 1,
    ]);
}

if ($action === 'settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $notify = input('notify_desktop', 1) ? 1 : 0;
    $showSeen = input('show_last_seen', 1) ? 1 : 0;
    $prefer = (string) input('prefer_call_type', 'voice');
    if (!in_array($prefer, ['voice', 'video'], true)) {
        $prefer = 'voice';
    }
    $pdo->prepare(
        'UPDATE users SET notify_desktop = ?, prefer_call_type = ?, show_last_seen = ? WHERE id = ?'
    )->execute([$notify, $prefer, $showSeen, $uid]);
    json_success('Settings saved', [
        'notify_desktop' => (bool) $notify,
        'prefer_call_type' => $prefer,
        'show_last_seen' => (bool) $showSeen,
    ]);
}

json_error('Unknown action', 404);
