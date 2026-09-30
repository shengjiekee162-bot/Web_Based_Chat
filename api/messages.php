<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
start_user_session();
require_login();

$uid = (int) current_user_id();
touch_presence($uid);
$action = $_GET['action'] ?? input('action', '');
$pdo = db();

if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    require_rate_limit('message', RATE_MESSAGE_MAX, RATE_MESSAGE_WINDOW);

    $receiverId = (int) input('receiver_id', 0);
    $type = (string) input('message_type', 'text');
    $body = trim((string) input('body', ''));
    $replyTo = (int) input('reply_to_id', 0) ?: null;
    $voiceDuration = input('voice_duration', null);

    if (!in_array($type, ['text', 'image', 'file', 'voice'], true)) {
        json_error('Invalid message type.');
    }

    $err = can_message($uid, $receiverId);
    if ($err) {
        json_error($err, 403);
    }

    $filePath = $fileName = $mime = null;
    $fileSize = null;

    if ($type === 'text') {
        if ($body === '' || str_len($body) > 5000) {
            json_error('Message text is required (max 5000 characters).');
        }
    } else {
        $fileKey = $type === 'image' ? 'image' : ($type === 'voice' ? 'voice' : 'file');
        if (empty($_FILES[$fileKey]) && empty($_FILES['file'])) {
            json_error('File is required.');
        }
        $uploadFile = $_FILES[$fileKey] ?? $_FILES['file'];
        $rules = upload_rules($type === 'image' ? 'image' : ($type === 'voice' ? 'voice' : 'file'));
        $stored = store_upload($uploadFile, $rules['subdir'], $rules['ext'], $rules['mime'], $rules['max']);
        if (!$stored) {
            json_error('Unable to upload file. Check type and size.');
        }
        $filePath = $stored['path'];
        $fileName = $stored['original'];
        $mime = $stored['mime'];
        $fileSize = $stored['size'];
        if ($body === '') {
            $body = null;
        }
    }

    if ($replyTo) {
        $rchk = $pdo->prepare(
            'SELECT id FROM messages WHERE id = ? AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) LIMIT 1'
        );
        $rchk->execute([$replyTo, $uid, $receiverId, $receiverId, $uid]);
        if (!$rchk->fetch()) {
            $replyTo = null;
        }
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO messages (sender_id, receiver_id, message_type, body, file_path, file_name, mime_type, file_size, voice_duration, reply_to_id, delivery_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'sent\')'
        );
        $stmt->execute([
            $uid, $receiverId, $type, $body, $filePath, $fileName, $mime, $fileSize,
            $voiceDuration !== null && $voiceDuration !== '' ? (float) $voiceDuration : null,
            $replyTo,
        ]);
        $msgId = (int) $pdo->lastInsertId();
    } catch (Throwable $e) {
        app_log('Send message failed: ' . $e->getMessage());
        json_error('Unable to send message. Please try again.');
    }

    $me = get_user_by_id($uid);
    notify_user(
        $receiverId,
        'new_message',
        'New message from ' . $me['username'],
        $type === 'text' ? str_cut((string) $body, 0, 100) : ucfirst($type) . ' message',
        'chat.php?user=' . $uid . '&msg=' . $msgId,
        'message',
        $msgId
    );

    $row = fetch_private_message($msgId);
    json_success('Message sent', format_message_row($row, $uid));
}

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $msgId = (int) input('message_id', 0);
    $stmt = $pdo->prepare('SELECT * FROM messages WHERE id = ? LIMIT 1');
    $stmt->execute([$msgId]);
    $msg = $stmt->fetch();
    if (!$msg) {
        json_error('Message not found.', 404);
    }
    if ((int) $msg['sender_id'] === $uid) {
        // Soft delete for everyone if sender deletes own message
        $pdo->prepare('UPDATE messages SET is_deleted = 1, body = NULL WHERE id = ? AND sender_id = ?')->execute([$msgId, $uid]);
        json_success('Message deleted');
    }
    if ((int) $msg['receiver_id'] === $uid) {
        $pdo->prepare('UPDATE messages SET deleted_by_receiver = 1 WHERE id = ?')->execute([$msgId]);
        json_success('Message removed');
    }
    json_error('Not allowed.', 403);
}

if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $msgId = (int) input('message_id', 0);
    $body = trim((string) input('body', ''));
    if ($body === '' || str_len($body) > 5000) {
        json_error('Message text is required (max 5000 characters).');
    }
    $stmt = $pdo->prepare('SELECT * FROM messages WHERE id = ? AND sender_id = ? AND is_deleted = 0 LIMIT 1');
    $stmt->execute([$msgId, $uid]);
    $msg = $stmt->fetch();
    if (!$msg) {
        json_error('Message not found.', 404);
    }
    if ($msg['message_type'] !== 'text') {
        json_error('Only text messages can be edited.');
    }
    $pdo->prepare('UPDATE messages SET body = ?, edited_at = NOW() WHERE id = ?')->execute([$body, $msgId]);
    json_success('Message updated', format_message_row(fetch_private_message($msgId), $uid));
}

if ($action === 'clear' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $peerId = (int) input('user_id', 0);
    if ($peerId <= 0) {
        json_error('Invalid user.');
    }
    $pdo->prepare(
        'UPDATE messages SET deleted_by_sender = 1 WHERE sender_id = ? AND receiver_id = ?'
    )->execute([$uid, $peerId]);
    $pdo->prepare(
        'UPDATE messages SET deleted_by_receiver = 1 WHERE sender_id = ? AND receiver_id = ?'
    )->execute([$peerId, $uid]);
    json_success('Conversation cleared');
}

if ($action === 'statuses') {
    $ids = $_GET['ids'] ?? input('ids', '');
    if (is_string($ids)) {
        $ids = array_filter(array_map('intval', explode(',', $ids)));
    }
    if (!is_array($ids) || !$ids) {
        json_success('OK', []);
    }
    $ids = array_slice(array_values(array_unique(array_map('intval', $ids))), 0, 100);
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $params = $ids;
    $params[] = $uid;
    $stmt = $pdo->prepare(
        "SELECT id, delivery_status, edited_at, is_deleted, body FROM messages
         WHERE id IN ($ph) AND sender_id = ?"
    );
    $stmt->execute($params);
    $out = [];
    foreach ($stmt->fetchAll() as $r) {
        $out[] = [
            'id' => (int) $r['id'],
            'delivery_status' => $r['delivery_status'],
            'edited_at' => $r['edited_at'],
            'is_deleted' => (int) $r['is_deleted'] === 1,
            'body' => $r['body'],
        ];
    }
    json_success('OK', $out);
}

if ($action === 'report' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $msgId = (int) input('message_id', 0);
    $reason = trim((string) input('reason', ''));
    if ($reason === '' || str_len($reason) > 500) {
        json_error('Please provide a reason (max 500 characters).');
    }
    $stmt = $pdo->prepare(
        'SELECT id FROM messages WHERE id = ? AND (sender_id = ? OR receiver_id = ?) LIMIT 1'
    );
    $stmt->execute([$msgId, $uid, $uid]);
    if (!$stmt->fetch()) {
        json_error('Message not found.', 404);
    }
    $pdo->prepare(
        'INSERT INTO reports (reporter_id, target_type, target_id, reason) VALUES (?, \'private_message\', ?, ?)'
    )->execute([$uid, $msgId, $reason]);
    json_success('Report submitted');
}

json_error('Unknown action', 404);

function fetch_private_message(int $id): array
{
    $stmt = db()->prepare(
        'SELECT m.*,
                u.username AS sender_username, u.avatar AS sender_avatar, u.id AS sender_user_id,
                r.body AS reply_body, r.message_type AS reply_type, r.is_deleted AS reply_deleted
         FROM messages m
         JOIN users u ON u.id = m.sender_id
         LEFT JOIN messages r ON r.id = m.reply_to_id
         WHERE m.id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: [];
}
