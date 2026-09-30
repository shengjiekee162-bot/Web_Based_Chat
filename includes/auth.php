<?php
/**
 * User & admin authentication helpers
 */

declare(strict_types=1);

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function current_admin_id(): ?int
{
    return isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
}

function require_login(): void
{
    if (!current_user_id()) {
        if (is_api_request()) {
            json_error('Unauthorized', 401);
        }
        redirect('login.php');
    }
    $user = get_user_by_id((int) current_user_id());
    if (!$user || (int) $user['is_disabled'] === 1) {
        logout_user();
        if (is_api_request()) {
            json_error('Account disabled or not found', 403);
        }
        redirect('login.php?disabled=1');
    }
}

function require_admin(): void
{
    if (!current_admin_id()) {
        redirect('admin/login.php');
    }
}

function is_api_request(): bool
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    return str_contains($uri, '/api/');
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = $user['username'];
    touch_presence((int) $user['id'], 'online');
}

function logout_user(): void
{
    $uid = current_user_id();
    if ($uid) {
        try {
            touch_presence($uid, 'offline');
        } catch (Throwable $e) {
            // ignore
        }
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function login_admin(array $admin): void
{
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $stmt = db()->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = ?');
    $stmt->execute([(int) $admin['id']]);
}

function logout_admin(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function get_user_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_user_by_login(string $login): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$login, $login]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_admin_by_login(string $login): ?array
{
    $stmt = db()->prepare('SELECT * FROM admins WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$login, $login]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function public_user(array $user): array
{
    $showSeen = (int) ($user['show_last_seen'] ?? 1) === 1;
    $viewerId = current_user_id();
    $isSelf = $viewerId && (int) $user['id'] === (int) $viewerId;
    return [
        'id'             => (int) $user['id'],
        'username'       => $user['username'],
        'avatar'         => $user['avatar'],
        'avatar_url'     => avatar_url($user['avatar'] ?? null),
        'status_message' => $user['status_message'] ?? '',
        'presence'       => effective_presence($user),
        'last_seen_at'   => ($showSeen || $isSelf) ? ($user['last_seen_at'] ?? null) : null,
        'created_at'     => $user['created_at'] ?? null,
        'prefer_call_type' => $user['prefer_call_type'] ?? 'voice',
        'notify_desktop' => (int) ($user['notify_desktop'] ?? 1) === 1,
        'show_last_seen' => $showSeen,
    ];
}

function effective_presence(array $user): string
{
    if (($user['presence'] ?? '') === 'busy') {
        return 'busy';
    }
    $last = $user['last_activity_at'] ?? null;
    if (!$last) {
        return 'offline';
    }
    $ts = strtotime($last);
    if ($ts === false) {
        return 'offline';
    }
    if (time() - $ts <= PRESENCE_ONLINE_SECONDS) {
        return in_array($user['presence'] ?? '', ['online', 'away', 'busy'], true)
            ? $user['presence']
            : 'online';
    }
    return 'offline';
}

function touch_presence(int $userId, ?string $presence = null): void
{
    if ($presence !== null) {
        $stmt = db()->prepare(
            'UPDATE users SET last_activity_at = NOW(), last_seen_at = NOW(), presence = ? WHERE id = ?'
        );
        $stmt->execute([$presence, $userId]);
    } else {
        $stmt = db()->prepare(
            'UPDATE users SET last_activity_at = NOW(), last_seen_at = NOW(), presence = IF(presence = \'busy\', \'busy\', \'online\') WHERE id = ?'
        );
        $stmt->execute([$userId]);
    }
}
