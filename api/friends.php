<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
start_user_session();
require_login();

$uid = (int) current_user_id();
touch_presence($uid);
$action = $_GET['action'] ?? input('action', '');
$pdo = db();

if ($action === 'search') {
    $q = trim((string) ($_GET['q'] ?? input('q', '')));
    if (strlen($q) < 2) {
        json_success('OK', []);
    }
    $like = '%' . $q . '%';
    $stmt = $pdo->prepare(
        'SELECT u.id, u.username, u.email, u.avatar, u.status_message, u.presence, u.last_seen_at, u.last_activity_at
         FROM users u
         WHERE u.id != ? AND u.is_disabled = 0 AND (u.username LIKE ? OR u.email LIKE ?)
         AND NOT EXISTS (
            SELECT 1 FROM user_blocks b
            WHERE (b.blocker_id = ? AND b.blocked_id = u.id) OR (b.blocker_id = u.id AND b.blocked_id = ?)
         )
         ORDER BY u.username ASC LIMIT 25'
    );
    $stmt->execute([$uid, $like, $like, $uid, $uid]);
    $rows = [];
    foreach ($stmt->fetchAll() as $u) {
        $item = public_user($u);
        $item['email'] = $u['email'];
        $item['is_friend'] = are_friends($uid, (int) $u['id']);
        $item['request'] = friend_request_status($uid, (int) $u['id']);
        $rows[] = $item;
    }
    json_success('OK', $rows);
}

if ($action === 'list') {
    $stmt = $pdo->prepare(
        'SELECT u.* FROM friendships f
         JOIN users u ON u.id = IF(f.user_low_id = ?, f.user_high_id, f.user_low_id)
         WHERE (f.user_low_id = ? OR f.user_high_id = ?) AND u.is_disabled = 0
         ORDER BY u.username ASC'
    );
    $stmt->execute([$uid, $uid, $uid]);
    $friends = array_map('public_user', $stmt->fetchAll());
    json_success('OK', $friends);
}

if ($action === 'requests') {
    $incoming = $pdo->prepare(
        'SELECT fr.id, fr.status, fr.created_at, u.id AS user_id, u.username, u.avatar, u.presence, u.last_activity_at, u.last_seen_at, u.status_message
         FROM friend_requests fr
         JOIN users u ON u.id = fr.sender_id
         WHERE fr.receiver_id = ? AND fr.status = \'pending\'
         ORDER BY fr.created_at DESC'
    );
    $incoming->execute([$uid]);
    $outgoing = $pdo->prepare(
        'SELECT fr.id, fr.status, fr.created_at, u.id AS user_id, u.username, u.avatar, u.presence, u.last_activity_at, u.last_seen_at, u.status_message
         FROM friend_requests fr
         JOIN users u ON u.id = fr.receiver_id
         WHERE fr.sender_id = ? AND fr.status = \'pending\'
         ORDER BY fr.created_at DESC'
    );
    $outgoing->execute([$uid]);

    $map = static function (array $r): array {
        return [
            'id' => (int) $r['id'],
            'status' => $r['status'],
            'created_at' => $r['created_at'],
            'user' => public_user([
                'id' => $r['user_id'],
                'username' => $r['username'],
                'avatar' => $r['avatar'],
                'presence' => $r['presence'],
                'last_activity_at' => $r['last_activity_at'],
                'last_seen_at' => $r['last_seen_at'],
                'status_message' => $r['status_message'],
            ]),
        ];
    };

    json_success('OK', [
        'incoming' => array_map($map, $incoming->fetchAll()),
        'outgoing' => array_map($map, $outgoing->fetchAll()),
    ]);
}

if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    require_rate_limit('friend', RATE_FRIEND_MAX, RATE_FRIEND_WINDOW);
    $targetId = (int) input('user_id', 0);
    if ($targetId <= 0 || $targetId === $uid) {
        json_error('Invalid user.');
    }
    $target = get_user_by_id($targetId);
    if (!$target || (int) $target['is_disabled'] === 1) {
        json_error('User not found.');
    }
    if (is_blocked_either($uid, $targetId)) {
        json_error('Cannot send request to this user.');
    }
    if (are_friends($uid, $targetId)) {
        json_error('Already friends.');
    }

    $chk = $pdo->prepare(
        'SELECT id, status, sender_id, receiver_id FROM friend_requests
         WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
         ORDER BY id DESC LIMIT 1'
    );
    $chk->execute([$uid, $targetId, $targetId, $uid]);
    $existing = $chk->fetch();
    if ($existing && $existing['status'] === 'pending') {
        json_error('A pending request already exists.');
    }

    if ($existing) {
        $upd = $pdo->prepare(
            'UPDATE friend_requests SET sender_id = ?, receiver_id = ?, status = \'pending\', updated_at = NOW() WHERE id = ?'
        );
        $upd->execute([$uid, $targetId, $existing['id']]);
        $reqId = (int) $existing['id'];
    } else {
        $ins = $pdo->prepare(
            'INSERT INTO friend_requests (sender_id, receiver_id, status) VALUES (?, ?, \'pending\')'
        );
        $ins->execute([$uid, $targetId]);
        $reqId = (int) $pdo->lastInsertId();
    }

    $me = get_user_by_id($uid);
    notify_user(
        $targetId,
        'friend_request',
        'New friend request',
        $me['username'] . ' sent you a friend request.',
        'friends.php',
        'friend_request',
        $reqId
    );
    json_success('Friend request sent');
}

if ($action === 'accept' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $reqId = (int) input('request_id', 0);
    $stmt = $pdo->prepare('SELECT * FROM friend_requests WHERE id = ? AND receiver_id = ? AND status = \'pending\' LIMIT 1');
    $stmt->execute([$reqId, $uid]);
    $req = $stmt->fetch();
    if (!$req) {
        json_error('Request not found.');
    }
    $senderId = (int) $req['sender_id'];
    if (is_blocked_either($uid, $senderId)) {
        json_error('Cannot accept — a block is in place.');
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE friend_requests SET status = \'accepted\', updated_at = NOW() WHERE id = ?')->execute([$reqId]);
        [$low, $high] = friendship_pair($uid, $senderId);
        $pdo->prepare(
            'INSERT IGNORE INTO friendships (user_low_id, user_high_id) VALUES (?, ?)'
        )->execute([$low, $high]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        app_log('Accept friend failed: ' . $e->getMessage());
        json_error('Unable to accept request.');
    }

    $me = get_user_by_id($uid);
    notify_user($senderId, 'friend_accepted', 'Friend request accepted', $me['username'] . ' accepted your request.', 'friends.php', 'user', $uid);
    json_success('Friend request accepted');
}

if ($action === 'reject' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $reqId = (int) input('request_id', 0);
    $stmt = $pdo->prepare('UPDATE friend_requests SET status = \'rejected\', updated_at = NOW() WHERE id = ? AND receiver_id = ? AND status = \'pending\'');
    $stmt->execute([$reqId, $uid]);
    if ($stmt->rowCount() === 0) {
        json_error('Request not found.');
    }
    json_success('Request rejected');
}

if ($action === 'cancel' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $reqId = (int) input('request_id', 0);
    $stmt = $pdo->prepare('UPDATE friend_requests SET status = \'cancelled\', updated_at = NOW() WHERE id = ? AND sender_id = ? AND status = \'pending\'');
    $stmt->execute([$reqId, $uid]);
    if ($stmt->rowCount() === 0) {
        json_error('Request not found.');
    }
    json_success('Request cancelled');
}

if ($action === 'remove' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $friendId = (int) input('user_id', 0);
    if (!are_friends($uid, $friendId)) {
        json_error('Not friends.');
    }
    [$low, $high] = friendship_pair($uid, $friendId);
    $pdo->prepare('DELETE FROM friendships WHERE user_low_id = ? AND user_high_id = ?')->execute([$low, $high]);
    json_success('Friend removed');
}

json_error('Unknown action', 404);

function friend_request_status(int $me, int $other): ?array
{
    $stmt = db()->prepare(
        'SELECT id, status, sender_id, receiver_id FROM friend_requests
         WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$me, $other, $other, $me]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    return [
        'id' => (int) $row['id'],
        'status' => $row['status'],
        'direction' => ((int) $row['sender_id'] === $me) ? 'outgoing' : 'incoming',
    ];
}
