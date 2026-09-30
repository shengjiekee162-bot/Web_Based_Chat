<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
start_user_session();
require_login();

$uid = (int) current_user_id();
$action = $_GET['action'] ?? input('action', '');
$pdo = db();

$allowed = ALLOWED_REACTIONS;

if ($action === 'toggle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $scope = (string) input('scope', 'private'); // private|group
    $messageId = (int) input('message_id', 0);
    $reaction = (string) input('reaction', '');
    if (!in_array($reaction, $allowed, true)) {
        json_error('Invalid reaction.');
    }

    if ($scope === 'group') {
        $stmt = $pdo->prepare(
            'SELECT gm.* FROM group_messages gm
             JOIN group_members m ON m.group_id = gm.group_id AND m.user_id = ?
             WHERE gm.id = ? LIMIT 1'
        );
        $stmt->execute([$uid, $messageId]);
        $msg = $stmt->fetch();
        if (!$msg) {
            json_error('Message not found.', 404);
        }
        $table = 'group_message_reactions';
    } else {
        $stmt = $pdo->prepare(
            'SELECT * FROM messages WHERE id = ? AND (sender_id = ? OR receiver_id = ?) LIMIT 1'
        );
        $stmt->execute([$messageId, $uid, $uid]);
        $msg = $stmt->fetch();
        if (!$msg) {
            json_error('Message not found.', 404);
        }
        $table = 'message_reactions';
    }

    $chk = $pdo->prepare("SELECT id FROM {$table} WHERE message_id = ? AND user_id = ? AND reaction = ?");
    $chk->execute([$messageId, $uid, $reaction]);
    $existing = $chk->fetch();
    if ($existing) {
        $pdo->prepare("DELETE FROM {$table} WHERE id = ?")->execute([$existing['id']]);
        $state = 'removed';
    } else {
        $pdo->prepare("INSERT INTO {$table} (message_id, user_id, reaction) VALUES (?, ?, ?)")
            ->execute([$messageId, $uid, $reaction]);
        $state = 'added';
        // Notify message sender
        $senderId = (int) $msg['sender_id'];
        if ($senderId !== $uid) {
            $me = get_user_by_id($uid);
            notify_user(
                $senderId,
                'reaction',
                'New reaction',
                $me['username'] . ' reacted ' . $reaction,
                $scope === 'group' ? 'group_chat.php?id=' . (int) $msg['group_id'] : 'chat.php?user=' . $uid,
                'message',
                $messageId
            );
        }
    }

    json_success('OK', [
        'state' => $state,
        'reactions' => get_message_reactions($messageId, $scope === 'group' ? 'group' : 'private'),
    ]);
}

json_error('Unknown action', 404);
