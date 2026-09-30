<?php
/**
 * Shared application helpers
 */

declare(strict_types=1);

function str_len(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function str_cut(string $value, int $start, ?int $length = null): string
{
    if (function_exists('mb_substr')) {
        return $length === null ? mb_substr($value, $start) : mb_substr($value, $start, $length);
    }
    return $length === null ? substr($value, $start) : substr($value, $start, $length);
}

function public_base_url(): string
{
    $configured = rtrim((string) APP_URL, '/');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $looksLocal = $configured === ''
        || str_contains($configured, '127.0.0.1')
        || str_contains($configured, 'localhost');

    // On cPanel, never bounce users / API calls to a developer machine URL
    if ($host !== '' && $looksLocal && !preg_match('/^(127\.0\.0\.1|localhost)(:\d+)?$/i', $host)) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443')
            || ((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $scheme = $https ? 'https' : 'http';
        $dir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
        // chat.php → /Web_Based_Chat ; api/calls.php → /Web_Based_Chat/api → strip /api|/admin
        if (preg_match('#/(api|admin)$#i', $dir)) {
            $dir = dirname($dir);
        }
        if ($dir === '/' || $dir === '.' || $dir === '\\') {
            $dir = '';
        }
        return $scheme . '://' . $host . $dir;
    }

    return $configured;
}

function redirect(string $path): void
{
    if (preg_match('#^https?://#i', $path)) {
        header('Location: ' . $path);
    } else {
        header('Location: ' . public_base_url() . '/' . ltrim($path, '/'));
    }
    exit;
}

function url(string $path = ''): string
{
    return public_base_url() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function avatar_url(?string $avatar): string
{
    if ($avatar) {
        return url('api/media.php?type=avatar&f=' . rawurlencode(basename($avatar)));
    }
    return asset('images/default-avatar.svg');
}

function group_avatar_url(?string $avatar): string
{
    if ($avatar) {
        return url('api/media.php?type=group&f=' . rawurlencode(basename($avatar)));
    }
    return asset('images/default-group.svg');
}

function media_url(string $relativePath, string $disposition = 'inline'): string
{
    return url('api/media.php?f=' . rawurlencode($relativePath) . '&d=' . rawurlencode($disposition));
}

function friendship_pair(int $a, int $b): array
{
    return $a < $b ? [$a, $b] : [$b, $a];
}

function are_friends(int $a, int $b): bool
{
    if ($a === $b) {
        return false;
    }
    [$low, $high] = friendship_pair($a, $b);
    $stmt = db()->prepare('SELECT id FROM friendships WHERE user_low_id = ? AND user_high_id = ? LIMIT 1');
    $stmt->execute([$low, $high]);
    return (bool) $stmt->fetch();
}

function is_blocked_either(int $a, int $b): bool
{
    $stmt = db()->prepare(
        'SELECT id FROM user_blocks WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?) LIMIT 1'
    );
    $stmt->execute([$a, $b, $b, $a]);
    return (bool) $stmt->fetch();
}

function is_blocked_by(int $blockerId, int $blockedId): bool
{
    $stmt = db()->prepare('SELECT id FROM user_blocks WHERE blocker_id = ? AND blocked_id = ? LIMIT 1');
    $stmt->execute([$blockerId, $blockedId]);
    return (bool) $stmt->fetch();
}

function notify_user(int $userId, string $type, string $title, string $body = '', ?string $link = null, ?string $refType = null, ?int $refId = null): void
{
    $stmt = db()->prepare(
        'INSERT INTO notifications (user_id, type, title, body, link, ref_type, ref_id) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $type, $title, $body, $link, $refType, $refId]);
}

function unread_notification_count(int $userId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function validate_password(string $password): ?string
{
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return 'Password must include letters and numbers.';
    }
    return null;
}

function validate_username(string $username): ?string
{
    if ($username === '' || strlen($username) < 3 || strlen($username) > 50) {
        return 'Username must be 3–50 characters.';
    }
    if (!preg_match('/^[A-Za-z0-9_\.]+$/', $username)) {
        return 'Username may only contain letters, numbers, underscores, and dots.';
    }
    return null;
}

function set_typing(int $userId, string $type, int $targetId): void
{
    $expires = (new DateTimeImmutable('+' . TYPING_TTL_SECONDS . ' seconds'))->format('Y-m-d H:i:s');
    $stmt = db()->prepare(
        'INSERT INTO typing_indicators (user_id, conversation_type, target_id, expires_at)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE expires_at = VALUES(expires_at)'
    );
    $stmt->execute([$userId, $type, $targetId, $expires]);
}

function get_typing(string $type, int $targetId, int $excludeUserId): array
{
    $pdo = db();
    $pdo->prepare('DELETE FROM typing_indicators WHERE expires_at < NOW()')->execute();
    $stmt = $pdo->prepare(
        'SELECT t.user_id, u.username
         FROM typing_indicators t
         JOIN users u ON u.id = t.user_id
         WHERE t.conversation_type = ? AND t.target_id = ? AND t.user_id != ? AND t.expires_at >= NOW()'
    );
    $stmt->execute([$type, $targetId, $excludeUserId]);
    return $stmt->fetchAll();
}

function can_message(int $senderId, int $receiverId): ?string
{
    if ($senderId === $receiverId) {
        return 'Cannot message yourself.';
    }
    $receiver = get_user_by_id($receiverId);
    if (!$receiver || (int) $receiver['is_disabled'] === 1) {
        return 'User not available.';
    }
    if (is_blocked_either($senderId, $receiverId)) {
        return 'Messaging is not allowed with this user.';
    }
    if (!are_friends($senderId, $receiverId)) {
        return 'You can only message friends.';
    }
    return null;
}

function can_call(int $callerId, int $calleeId): ?string
{
    if ($callerId === $calleeId) {
        return 'Cannot call yourself.';
    }
    $callee = get_user_by_id($calleeId);
    if (!$callee || (int) $callee['is_disabled'] === 1) {
        return 'User not available.';
    }
    if (is_blocked_either($callerId, $calleeId)) {
        return 'Calling is not allowed with this user.';
    }
    if (!are_friends($callerId, $calleeId)) {
        return 'You can only call friends.';
    }
    return null;
}

function group_member(int $groupId, int $userId): ?array
{
    $stmt = db()->prepare('SELECT * FROM group_members WHERE group_id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$groupId, $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function group_role_at_least(?array $member, string $minRole): bool
{
    if (!$member) {
        return false;
    }
    $rank = ['member' => 1, 'admin' => 2, 'owner' => 3];
    return ($rank[$member['role']] ?? 0) >= ($rank[$minRole] ?? 99);
}

function format_message_row(array $row, int $viewerId): array
{
    $deleted = (int) ($row['is_deleted'] ?? 0) === 1;
    $reactions = [];
    if (!empty($row['id'])) {
        $reactions = get_message_reactions((int) $row['id'], 'private');
    }

    $reply = null;
    if (!empty($row['reply_to_id'])) {
        $reply = [
            'id'   => (int) $row['reply_to_id'],
            'body' => !empty($row['reply_deleted']) ? 'Deleted message' : ($row['reply_body'] ?? 'Message'),
            'type' => $row['reply_type'] ?? 'text',
        ];
    }

    return [
        'id'             => (int) $row['id'],
        'sender_id'      => (int) $row['sender_id'],
        'receiver_id'    => (int) $row['receiver_id'],
        'message_type'   => $row['message_type'],
        'body'           => $deleted ? null : $row['body'],
        'file_path'      => $deleted ? null : $row['file_path'],
        'file_name'      => $deleted ? null : $row['file_name'],
        'mime_type'      => $deleted ? null : $row['mime_type'],
        'file_size'      => $deleted ? null : (isset($row['file_size']) ? (int) $row['file_size'] : null),
        'voice_duration' => $deleted ? null : $row['voice_duration'],
        'media_url'      => (
            !$deleted
            && !empty($row['file_path'])
            && ($row['message_type'] ?? '') !== 'call'
            && !str_starts_with((string) $row['file_path'], 'call:')
        ) ? media_url($row['file_path']) : null,
        'reply_to'       => $reply,
        'delivery_status'=> $row['delivery_status'] ?? 'sent',
        'is_deleted'     => $deleted,
        'is_mine'        => (int) $row['sender_id'] === $viewerId,
        'created_at'     => $row['created_at'],
        'edited_at'      => $row['edited_at'] ?? null,
        'reactions'      => $reactions,
        'sender'         => [
            'id'       => (int) ($row['sender_user_id'] ?? $row['sender_id']),
            'username' => $row['sender_username'] ?? '',
            'avatar_url' => avatar_url($row['sender_avatar'] ?? null),
        ],
    ];
}

function get_message_reactions(int $messageId, string $scope = 'private'): array
{
    $table = $scope === 'group' ? 'group_message_reactions' : 'message_reactions';
    $stmt = db()->prepare(
        "SELECT reaction, COUNT(*) AS cnt, GROUP_CONCAT(user_id) AS user_ids
         FROM {$table} WHERE message_id = ? GROUP BY reaction"
    );
    $stmt->execute([$messageId]);
    $out = [];
    foreach ($stmt->fetchAll() as $r) {
        $out[] = [
            'reaction' => $r['reaction'],
            'count'    => (int) $r['cnt'],
            'user_ids' => array_map('intval', explode(',', (string) $r['user_ids'])),
        ];
    }
    return $out;
}

function format_group_message_row(array $row, int $viewerId): array
{
    $deleted = (int) ($row['is_deleted'] ?? 0) === 1;
    $reactions = get_message_reactions((int) $row['id'], 'group');
    $reply = null;
    if (!empty($row['reply_to_id'])) {
        $reply = [
            'id'   => (int) $row['reply_to_id'],
            'body' => !empty($row['reply_deleted']) ? 'Deleted message' : ($row['reply_body'] ?? 'Message'),
            'type' => $row['reply_type'] ?? 'text',
        ];
    }
    return [
        'id'             => (int) $row['id'],
        'group_id'       => (int) $row['group_id'],
        'sender_id'      => (int) $row['sender_id'],
        'message_type'   => $row['message_type'],
        'body'           => $deleted ? null : $row['body'],
        'file_path'      => $deleted ? null : $row['file_path'],
        'file_name'      => $deleted ? null : $row['file_name'],
        'mime_type'      => $deleted ? null : $row['mime_type'],
        'file_size'      => $deleted ? null : (isset($row['file_size']) ? (int) $row['file_size'] : null),
        'voice_duration' => $deleted ? null : $row['voice_duration'],
        'media_url'      => (!$deleted && !empty($row['file_path'])) ? media_url($row['file_path']) : null,
        'reply_to'       => $reply,
        'is_deleted'     => $deleted,
        'is_mine'        => (int) $row['sender_id'] === $viewerId,
        'created_at'     => $row['created_at'],
        'edited_at'      => $row['edited_at'] ?? null,
        'read_count'     => isset($row['read_count']) ? (int) $row['read_count'] : null,
        'reactions'      => $reactions,
        'sender'         => [
            'id'         => (int) $row['sender_id'],
            'username'   => $row['sender_username'] ?? '',
            'avatar_url' => avatar_url($row['sender_avatar'] ?? null),
        ],
    ];
}

function ice_servers_config(): array
{
    $servers = STUN_SERVERS;
    if (defined('TURN_SERVERS') && is_array(TURN_SERVERS)) {
        $servers = array_merge($servers, TURN_SERVERS);
    }
    return $servers;
}

/**
 * Write a call event into the private chat timeline (both users see it).
 */
function record_call_chat_message(int $callId, ?string $statusOverride = null): void
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM voice_call_sessions WHERE id = ? LIMIT 1');
    $stmt->execute([$callId]);
    $call = $stmt->fetch();
    if (!$call) {
        return;
    }

    $marker = 'call:' . $callId;
    $dup = $pdo->prepare('SELECT id FROM messages WHERE message_type = \'call\' AND file_path = ? LIMIT 1');
    $dup->execute([$marker]);
    if ($dup->fetch()) {
        return;
    }

    $status = $statusOverride ?: (string) $call['status'];
    $typeLabel = (($call['call_type'] ?? 'voice') === 'video') ? 'Video call' : 'Voice call';
    $durationSec = null;
    if (!empty($call['started_at'])) {
        $end = !empty($call['ended_at']) ? strtotime((string) $call['ended_at']) : time();
        $start = strtotime((string) $call['started_at']);
        if ($start && $end && $end >= $start) {
            $durationSec = max(0, $end - $start);
        }
    }

    $body = match ($status) {
        'ended' => $typeLabel . ($durationSec !== null ? (' · ' . format_call_duration($durationSec)) : ''),
        'rejected' => 'Declined ' . strtolower($typeLabel),
        'cancelled' => 'Cancelled ' . strtolower($typeLabel),
        'missed' => 'Missed ' . strtolower($typeLabel),
        default => $typeLabel . ' · ' . $status,
    };

    $pdo->prepare(
        'INSERT INTO messages (sender_id, receiver_id, message_type, body, file_path, voice_duration, delivery_status)
         VALUES (?, ?, \'call\', ?, ?, ?, \'delivered\')'
    )->execute([
        (int) $call['caller_id'],
        (int) $call['callee_id'],
        $body,
        $marker,
        $durationSec,
    ]);
}

function format_call_duration(int $seconds): string
{
    if ($seconds < 60) {
        return $seconds . 's';
    }
    $m = intdiv($seconds, 60);
    $s = $seconds % 60;
    return $m . ':' . str_pad((string) $s, 2, '0', STR_PAD_LEFT);
}

function expire_stale_ringing_calls(): void
{
    $pdo = db();
    $timeout = max(15, (int) CALL_RING_TIMEOUT);
    $stmt = $pdo->prepare(
        "SELECT id FROM voice_call_sessions
         WHERE status = 'ringing'
           AND created_at < DATE_SUB(NOW(), INTERVAL {$timeout} SECOND)"
    );
    $stmt->execute();
    foreach ($stmt->fetchAll() as $row) {
        $id = (int) $row['id'];
        $upd = $pdo->prepare(
            "UPDATE voice_call_sessions SET status = 'missed', ended_at = NOW()
             WHERE id = ? AND status = 'ringing'"
        );
        $upd->execute([$id]);
        if ($upd->rowCount() > 0) {
            record_call_chat_message($id, 'missed');
        }
    }
}

function request_json(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        $cached = $_POST ?: [];
        return $cached;
    }
    $data = json_decode($raw, true);
    $cached = is_array($data) ? $data : ($_POST ?: []);
    return $cached;
}

function input(string $key, $default = null)
{
    $json = request_json();
    if (array_key_exists($key, $json)) {
        return $json[$key];
    }
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}
