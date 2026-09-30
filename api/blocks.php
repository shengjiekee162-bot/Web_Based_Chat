<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
start_user_session();
require_login();

$uid = (int) current_user_id();
$action = $_GET['action'] ?? input('action', '');
$pdo = db();

if ($action === 'list') {
    $stmt = $pdo->prepare(
        'SELECT u.id, u.username, u.avatar, u.presence, u.last_seen_at, u.last_activity_at, u.status_message, b.created_at AS blocked_at
         FROM user_blocks b
         JOIN users u ON u.id = b.blocked_id
         WHERE b.blocker_id = ?
         ORDER BY b.created_at DESC'
    );
    $stmt->execute([$uid]);
    $rows = [];
    foreach ($stmt->fetchAll() as $u) {
        $item = public_user($u);
        $item['blocked_at'] = $u['blocked_at'];
        $rows[] = $item;
    }
    json_success('OK', $rows);
}

if ($action === 'block' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $targetId = (int) input('user_id', 0);
    if ($targetId <= 0 || $targetId === $uid) {
        json_error('Invalid user.');
    }
    if (!get_user_by_id($targetId)) {
        json_error('User not found.');
    }
    $pdo->prepare('INSERT IGNORE INTO user_blocks (blocker_id, blocked_id) VALUES (?, ?)')->execute([$uid, $targetId]);
    // Remove friendship if any
    if (are_friends($uid, $targetId)) {
        [$low, $high] = friendship_pair($uid, $targetId);
        $pdo->prepare('DELETE FROM friendships WHERE user_low_id = ? AND user_high_id = ?')->execute([$low, $high]);
    }
    $pdo->prepare(
        'UPDATE friend_requests SET status = \'cancelled\', updated_at = NOW()
         WHERE status = \'pending\' AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))'
    )->execute([$uid, $targetId, $targetId, $uid]);
    json_success('User blocked');
}

if ($action === 'unblock' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $targetId = (int) input('user_id', 0);
    $pdo->prepare('DELETE FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?')->execute([$uid, $targetId]);
    json_success('User unblocked');
}

json_error('Unknown action', 404);
