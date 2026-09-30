<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
start_user_session();
require_login();

$uid = (int) current_user_id();
touch_presence($uid);
$action = $_GET['action'] ?? input('action', '');
$pdo = db();

if ($action === 'conversations') {
    $q = trim((string) ($_GET['q'] ?? ''));
    // Latest message per peer using correlated subquery approach
    $sql = 'SELECT u.id AS peer_id, u.username, u.avatar, u.presence, u.last_seen_at, u.last_activity_at, u.status_message,
                   m.id AS last_message_id, m.body AS last_body, m.message_type AS last_type,
                   m.created_at AS last_at, m.sender_id AS last_sender_id, m.is_deleted AS last_deleted,
                   (SELECT COUNT(*) FROM messages mx
                      WHERE mx.sender_id = u.id AND mx.receiver_id = ? AND mx.delivery_status != \'read\'
                        AND mx.deleted_by_receiver = 0 AND mx.is_deleted = 0) AS unread_count
            FROM users u
            INNER JOIN messages m ON m.id = (
                SELECT m2.id FROM messages m2
                WHERE ((m2.sender_id = ? AND m2.receiver_id = u.id AND m2.deleted_by_sender = 0)
                    OR (m2.sender_id = u.id AND m2.receiver_id = ? AND m2.deleted_by_receiver = 0))
                ORDER BY m2.id DESC LIMIT 1
            )
            WHERE u.id != ?';
    $params = [$uid, $uid, $uid, $uid];
    if ($q !== '') {
        $sql .= ' AND u.username LIKE ?';
        $params[] = '%' . $q . '%';
    }
    $sql .= ' ORDER BY m.created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $out = [];
    foreach ($stmt->fetchAll() as $r) {
        $preview = ((int) $r['last_deleted'] === 1) ? 'Deleted message' : preview_text($r['last_type'], $r['last_body']);
        $out[] = [
            'peer' => public_user([
                'id' => $r['peer_id'],
                'username' => $r['username'],
                'avatar' => $r['avatar'],
                'presence' => $r['presence'],
                'last_seen_at' => $r['last_seen_at'],
                'last_activity_at' => $r['last_activity_at'],
                'status_message' => $r['status_message'],
            ]),
            'last_message' => [
                'id' => (int) $r['last_message_id'],
                'body' => $preview,
                'type' => $r['last_type'],
                'created_at' => $r['last_at'],
                'is_mine' => (int) $r['last_sender_id'] === $uid,
            ],
            'unread_count' => (int) $r['unread_count'],
        ];
    }
    json_success('OK', $out);
}

if ($action === 'history') {
    $peerId = (int) ($_GET['user_id'] ?? input('user_id', 0));
    $afterId = (int) ($_GET['after_id'] ?? 0);
    $beforeId = (int) ($_GET['before_id'] ?? 0);
    $err = can_message($uid, $peerId);
    // Allow history if friends OR existing thread (even if unfriended later with prior msgs)
    $hasThread = false;
    if ($err) {
        $chk = $pdo->prepare(
            'SELECT id FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) LIMIT 1'
        );
        $chk->execute([$uid, $peerId, $peerId, $uid]);
        $hasThread = (bool) $chk->fetch();
        if (!$hasThread || is_blocked_either($uid, $peerId)) {
            json_error($err ?: 'Cannot open conversation.', 403);
        }
    }

    $sql = 'SELECT m.*,
                   u.username AS sender_username, u.avatar AS sender_avatar, u.id AS sender_user_id,
                   r.body AS reply_body, r.message_type AS reply_type, r.is_deleted AS reply_deleted
            FROM messages m
            JOIN users u ON u.id = m.sender_id
            LEFT JOIN messages r ON r.id = m.reply_to_id
            WHERE ((m.sender_id = ? AND m.receiver_id = ? AND m.deleted_by_sender = 0)
                OR (m.sender_id = ? AND m.receiver_id = ? AND m.deleted_by_receiver = 0))';
    $params = [$uid, $peerId, $peerId, $uid];
    if ($afterId > 0) {
        $sql .= ' AND m.id > ?';
        $params[] = $afterId;
    }
    if ($beforeId > 0) {
        $sql .= ' AND m.id < ?';
        $params[] = $beforeId;
        $sql .= ' ORDER BY m.id DESC LIMIT 40';
    } else {
        $sql .= ' ORDER BY m.id ASC LIMIT 100';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    if ($beforeId > 0) {
        $rows = array_reverse($rows);
    }
    $messages = [];
    foreach ($rows as $row) {
        $messages[] = format_message_row($row, $uid);
    }

    if ($beforeId <= 0) {
        // Mark delivered/read for incoming only on initial/open load
        $pdo->prepare(
            'UPDATE messages SET delivery_status = \'delivered\'
             WHERE sender_id = ? AND receiver_id = ? AND delivery_status = \'sent\''
        )->execute([$peerId, $uid]);
        $pdo->prepare(
            'UPDATE messages SET delivery_status = \'read\'
             WHERE sender_id = ? AND receiver_id = ? AND delivery_status IN (\'sent\',\'delivered\')'
        )->execute([$peerId, $uid]);

        $ins = $pdo->prepare('INSERT IGNORE INTO message_read_receipts (message_id, user_id) VALUES (?, ?)');
        foreach ($messages as $m) {
            if (!$m['is_mine'] && !$m['is_deleted']) {
                $ins->execute([$m['id'], $uid]);
            }
        }
    }

    $typingToMe = get_typing('private', $uid, $uid);

    json_success('OK', [
        'messages' => $messages,
        'peer' => public_user(get_user_by_id($peerId)),
        'can_send' => can_message($uid, $peerId) === null,
        'typing' => $typingToMe,
        'has_more' => $beforeId > 0 ? count($messages) >= 40 : false,
    ]);
}

if ($action === 'typing' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $peerId = (int) input('user_id', 0);
    if (can_message($uid, $peerId) !== null) {
        json_error('Not allowed.', 403);
    }
    // Store typing against peer's inbox (target_id = peer)
    set_typing($uid, 'private', $peerId);
    json_success('OK');
}

if ($action === 'poll') {
    $peerId = (int) ($_GET['user_id'] ?? 0);
    $afterId = (int) ($_GET['after_id'] ?? 0);
    if ($peerId <= 0) {
        json_error('user_id required');
    }
    if (is_blocked_either($uid, $peerId)) {
        json_error('Blocked', 403);
    }
    $sql = 'SELECT m.*,
                   u.username AS sender_username, u.avatar AS sender_avatar, u.id AS sender_user_id,
                   r.body AS reply_body, r.message_type AS reply_type, r.is_deleted AS reply_deleted
            FROM messages m
            JOIN users u ON u.id = m.sender_id
            LEFT JOIN messages r ON r.id = m.reply_to_id
            WHERE ((m.sender_id = ? AND m.receiver_id = ? AND m.deleted_by_sender = 0)
                OR (m.sender_id = ? AND m.receiver_id = ? AND m.deleted_by_receiver = 0))
              AND m.id > ?
            ORDER BY m.id ASC LIMIT 50';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$uid, $peerId, $peerId, $uid, $afterId]);
    $messages = array_map(static fn($r) => format_message_row($r, $uid), $stmt->fetchAll());

    $pdo->prepare(
        'UPDATE messages SET delivery_status = \'read\'
         WHERE sender_id = ? AND receiver_id = ? AND delivery_status IN (\'sent\',\'delivered\')'
    )->execute([$peerId, $uid]);

    $typing = get_typing('private', $uid, $uid);
    // People typing to me: conversation_type=private AND target_id=me
    $peer = get_user_by_id($peerId);

    json_success('OK', [
        'messages' => $messages,
        'typing' => $typing,
        'peer' => $peer ? public_user($peer) : null,
    ]);
}

if ($action === 'mark_all_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $pdo->prepare(
        "UPDATE messages SET delivery_status = 'read'
         WHERE receiver_id = ? AND delivery_status IN ('sent','delivered') AND is_deleted = 0"
    )->execute([$uid]);
    json_success('All conversations marked as read');
}

if ($action === 'export') {
    $peerId = (int) ($_GET['user_id'] ?? 0);
    if ($peerId <= 0 || (can_message($uid, $peerId) !== null && !are_friends($uid, $peerId))) {
        // allow export if thread exists
        $chk = $pdo->prepare(
            'SELECT id FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) LIMIT 1'
        );
        $chk->execute([$uid, $peerId, $peerId, $uid]);
        if (!$chk->fetch()) {
            json_error('Conversation not found.', 404);
        }
    }
    $stmt = $pdo->prepare(
        'SELECT m.created_at, m.message_type, m.body, m.file_name, m.delivery_status, m.is_deleted,
                u.username AS sender
         FROM messages m
         JOIN users u ON u.id = m.sender_id
         WHERE ((m.sender_id = ? AND m.receiver_id = ? AND m.deleted_by_sender = 0)
             OR (m.sender_id = ? AND m.receiver_id = ? AND m.deleted_by_receiver = 0))
         ORDER BY m.id ASC
         LIMIT 5000'
    );
    $stmt->execute([$uid, $peerId, $peerId, $uid]);
    $lines = ["timestamp,sender,type,body,file,status"];
    foreach ($stmt->fetchAll() as $r) {
        $body = (int) $r['is_deleted'] === 1 ? '[deleted]' : str_replace(['"', "\n", "\r"], ["'", ' ', ''], (string) ($r['body'] ?? ''));
        $lines[] = sprintf(
            '"%s","%s","%s","%s","%s","%s"',
            $r['created_at'],
            $r['sender'],
            $r['message_type'],
            $body,
            $r['file_name'] ?? '',
            $r['delivery_status']
        );
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="chat-export-' . $peerId . '.csv"');
    echo implode("\n", $lines);
    exit;
}

json_error('Unknown action', 404);

function preview_text(string $type, ?string $body): string
{
    return match ($type) {
        'image' => '📷 Image',
        'file' => '📎 File',
        'voice' => '🎤 Voice message',
        'call' => $body ? (string) $body : '📞 Call',
        default => str_cut((string) $body, 0, 80),
    };
}
