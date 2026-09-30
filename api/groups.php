<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
start_user_session();
require_login();

$uid = (int) current_user_id();
touch_presence($uid);
$action = $_GET['action'] ?? input('action', '');
$pdo = db();

if ($action === 'list') {
    $stmt = $pdo->prepare(
        'SELECT g.*, m.role,
                (SELECT COUNT(*) FROM group_members gm2 WHERE gm2.group_id = g.id) AS member_count,
                (SELECT COUNT(*) FROM group_messages msg
                   LEFT JOIN group_message_reads r ON r.message_id = msg.id AND r.user_id = ?
                 WHERE msg.group_id = g.id AND msg.sender_id != ? AND r.id IS NULL AND msg.is_deleted = 0) AS unread_count
         FROM group_chats g
         JOIN group_members m ON m.group_id = g.id AND m.user_id = ?
         ORDER BY g.updated_at DESC'
    );
    $stmt->execute([$uid, $uid, $uid]);
    $out = [];
    foreach ($stmt->fetchAll() as $g) {
        $out[] = format_group($g);
    }
    json_success('OK', $out);
}

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name = trim((string) input('name', ''));
    $desc = trim((string) input('description', ''));
    $memberIds = input('member_ids', []);
    if (!is_array($memberIds)) {
        $memberIds = [];
    }
    if ($name === '' || str_len($name) > 120) {
        json_error('Group name is required (max 120 characters).');
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO group_chats (name, description, owner_id) VALUES (?, ?, ?)')
            ->execute([$name, $desc, $uid]);
        $gid = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, \'owner\')')
            ->execute([$gid, $uid]);

        $ins = $pdo->prepare('INSERT IGNORE INTO group_members (group_id, user_id, role) VALUES (?, ?, \'member\')');
        foreach ($memberIds as $mid) {
            $mid = (int) $mid;
            if ($mid === $uid || !are_friends($uid, $mid) || is_blocked_either($uid, $mid)) {
                continue;
            }
            $ins->execute([$gid, $mid]);
            notify_user($mid, 'group_invite', 'Added to group', 'You were added to ' . $name, 'group_chat.php?id=' . $gid, 'group', $gid);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        app_log('Create group failed: ' . $e->getMessage());
        json_error('Unable to create group.');
    }
    json_success('Group created', ['id' => $gid]);
}

if ($action === 'get') {
    $gid = (int) ($_GET['id'] ?? input('id', 0));
    $member = group_member($gid, $uid);
    if (!$member) {
        json_error('Not a member.', 403);
    }
    $stmt = $pdo->prepare('SELECT * FROM group_chats WHERE id = ?');
    $stmt->execute([$gid]);
    $g = $stmt->fetch();
    if (!$g) {
        json_error('Group not found.', 404);
    }
    $g['role'] = $member['role'];
    $members = $pdo->prepare(
        'SELECT u.id, u.username, u.avatar, u.presence, u.last_seen_at, u.last_activity_at, u.status_message, m.role
         FROM group_members m JOIN users u ON u.id = m.user_id WHERE m.group_id = ? ORDER BY FIELD(m.role,\'owner\',\'admin\',\'member\'), u.username'
    );
    $members->execute([$gid]);
    $list = [];
    foreach ($members->fetchAll() as $row) {
        $pu = public_user($row);
        $pu['role'] = $row['role'];
        $list[] = $pu;
    }
    json_success('OK', ['group' => format_group($g), 'members' => $list]);
}

if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $gid = (int) input('id', 0);
    $member = group_member($gid, $uid);
    if (!group_role_at_least($member, 'admin')) {
        json_error('Permission denied.', 403);
    }
    $name = trim((string) input('name', ''));
    $desc = trim((string) input('description', ''));
    if ($name === '') {
        json_error('Name required.');
    }
    $pdo->prepare('UPDATE group_chats SET name = ?, description = ? WHERE id = ?')->execute([$name, $desc, $gid]);
    json_success('Group updated');
}

if ($action === 'avatar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $gid = (int) input('id', 0);
    $member = group_member($gid, $uid);
    if (!group_role_at_least($member, 'admin')) {
        json_error('Permission denied.', 403);
    }
    if (empty($_FILES['avatar'])) {
        json_error('No file.');
    }
    $rules = upload_rules('group_avatar');
    $stored = store_upload($_FILES['avatar'], $rules['subdir'], $rules['ext'], $rules['mime'], $rules['max']);
    if (!$stored) {
        json_error('Invalid image.');
    }
    $pdo->prepare('UPDATE group_chats SET avatar = ? WHERE id = ?')->execute([$stored['stored'], $gid]);
    json_success('Avatar updated', ['avatar_url' => group_avatar_url($stored['stored'])]);
}

if ($action === 'add_member' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $gid = (int) input('group_id', 0);
    $userId = (int) input('user_id', 0);
    $member = group_member($gid, $uid);
    if (!group_role_at_least($member, 'admin')) {
        json_error('Permission denied.', 403);
    }
    if (!are_friends($uid, $userId) || is_blocked_either($uid, $userId)) {
        json_error('You can only add friends.');
    }
    if (group_member($gid, $userId)) {
        json_error('Already a member.');
    }
    $pdo->prepare('INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, \'member\')')
        ->execute([$gid, $userId]);
    $g = $pdo->prepare('SELECT name FROM group_chats WHERE id = ?');
    $g->execute([$gid]);
    $name = $g->fetchColumn();
    notify_user($userId, 'group_invite', 'Added to group', 'You were added to ' . $name, 'group_chat.php?id=' . $gid, 'group', $gid);
    json_success('Member added');
}

if ($action === 'remove_member' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $gid = (int) input('group_id', 0);
    $userId = (int) input('user_id', 0);
    $me = group_member($gid, $uid);
    $target = group_member($gid, $userId);
    if (!$me || !$target) {
        json_error('Not found.', 404);
    }
    if ($target['role'] === 'owner') {
        json_error('Cannot remove the owner.');
    }
    if ($userId === $uid) {
        json_error('Use leave instead.');
    }
    if ($me['role'] === 'admin' && $target['role'] !== 'member') {
        json_error('Admins can only remove members.');
    }
    if (!group_role_at_least($me, 'admin')) {
        json_error('Permission denied.', 403);
    }
    $pdo->prepare('DELETE FROM group_members WHERE group_id = ? AND user_id = ?')->execute([$gid, $userId]);
    json_success('Member removed');
}

if ($action === 'set_role' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $gid = (int) input('group_id', 0);
    $userId = (int) input('user_id', 0);
    $role = (string) input('role', 'member');
    $me = group_member($gid, $uid);
    if (!group_role_at_least($me, 'owner') || $me['role'] !== 'owner') {
        json_error('Only the owner can change roles.', 403);
    }
    if (!in_array($role, ['admin', 'member'], true)) {
        json_error('Invalid role.');
    }
    $target = group_member($gid, $userId);
    if (!$target || $target['role'] === 'owner') {
        json_error('Invalid member.');
    }
    $pdo->prepare('UPDATE group_members SET role = ? WHERE group_id = ? AND user_id = ?')
        ->execute([$role, $gid, $userId]);
    json_success('Role updated');
}

if ($action === 'transfer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $gid = (int) input('group_id', 0);
    $newOwnerId = (int) input('user_id', 0);
    $me = group_member($gid, $uid);
    if (!$me || $me['role'] !== 'owner') {
        json_error('Only the owner can transfer ownership.', 403);
    }
    $target = group_member($gid, $newOwnerId);
    if (!$target) {
        json_error('User is not a member.');
    }
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE group_members SET role = \'admin\' WHERE group_id = ? AND user_id = ?')->execute([$gid, $uid]);
        $pdo->prepare('UPDATE group_members SET role = \'owner\' WHERE group_id = ? AND user_id = ?')->execute([$gid, $newOwnerId]);
        $pdo->prepare('UPDATE group_chats SET owner_id = ? WHERE id = ?')->execute([$newOwnerId, $gid]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        app_log('Transfer owner failed: ' . $e->getMessage());
        json_error('Unable to transfer ownership.');
    }
    notify_user($newOwnerId, 'group_invite', 'You are now the group owner', 'Ownership was transferred to you.', 'group_chat.php?id=' . $gid, 'group', $gid);
    json_success('Ownership transferred');
}

if ($action === 'leave' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $gid = (int) input('group_id', 0);
    $me = group_member($gid, $uid);
    if (!$me) {
        json_error('Not a member.');
    }
    if ($me['role'] === 'owner') {
        json_error('Owner must delete the group or transfer ownership first.');
    }
    $pdo->prepare('DELETE FROM group_members WHERE group_id = ? AND user_id = ?')->execute([$gid, $uid]);
    json_success('Left group');
}

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $gid = (int) input('group_id', 0);
    $me = group_member($gid, $uid);
    if (!$me || $me['role'] !== 'owner') {
        json_error('Only the owner can delete the group.', 403);
    }
    $pdo->prepare('DELETE FROM group_chats WHERE id = ?')->execute([$gid]);
    json_success('Group deleted');
}

if ($action === 'messages') {
    $gid = (int) ($_GET['group_id'] ?? 0);
    $afterId = (int) ($_GET['after_id'] ?? 0);
    if (!group_member($gid, $uid)) {
        json_error('Not a member.', 403);
    }
    $sql = 'SELECT gm.*,
                   u.username AS sender_username, u.avatar AS sender_avatar,
                   r.body AS reply_body, r.message_type AS reply_type, r.is_deleted AS reply_deleted,
                   (SELECT COUNT(*) FROM group_message_reads gr WHERE gr.message_id = gm.id) AS read_count
            FROM group_messages gm
            JOIN users u ON u.id = gm.sender_id
            LEFT JOIN group_messages r ON r.id = gm.reply_to_id
            WHERE gm.group_id = ?';
    $params = [$gid];
    if ($afterId > 0) {
        $sql .= ' AND gm.id > ?';
        $params[] = $afterId;
    }
    $sql .= ' ORDER BY gm.id ASC LIMIT 100';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $messages = [];
    $insRead = $pdo->prepare('INSERT IGNORE INTO group_message_reads (message_id, user_id) VALUES (?, ?)');
    foreach ($stmt->fetchAll() as $row) {
        $messages[] = format_group_message_row($row, $uid);
        if ((int) $row['sender_id'] !== $uid) {
            $insRead->execute([(int) $row['id'], $uid]);
        }
    }
    $typing = get_typing('group', $gid, $uid);
    json_success('OK', ['messages' => $messages, 'typing' => $typing]);
}

if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    require_rate_limit('message', RATE_MESSAGE_MAX, RATE_MESSAGE_WINDOW);
    $gid = (int) input('group_id', 0);
    if (!group_member($gid, $uid)) {
        json_error('Not a member.', 403);
    }
    $type = (string) input('message_type', 'text');
    $body = trim((string) input('body', ''));
    $replyTo = (int) input('reply_to_id', 0) ?: null;
    $voiceDuration = input('voice_duration', null);

    if (!in_array($type, ['text', 'image', 'file', 'voice'], true)) {
        json_error('Invalid type.');
    }

    $filePath = $fileName = $mime = null;
    $fileSize = null;

    if ($type === 'text') {
        if ($body === '') {
            json_error('Message required.');
        }
    } else {
        $fileKey = $type === 'image' ? 'image' : ($type === 'voice' ? 'voice' : 'file');
        $uploadFile = $_FILES[$fileKey] ?? $_FILES['file'] ?? null;
        if (!$uploadFile) {
            json_error('File required.');
        }
        $rules = upload_rules($type === 'image' ? 'image' : ($type === 'voice' ? 'voice' : 'file'));
        $stored = store_upload($uploadFile, $rules['subdir'], $rules['ext'], $rules['mime'], $rules['max']);
        if (!$stored) {
            json_error('Upload failed.');
        }
        $filePath = $stored['path'];
        $fileName = $stored['original'];
        $mime = $stored['mime'];
        $fileSize = $stored['size'];
    }

    if ($replyTo) {
        $rchk = $pdo->prepare('SELECT id FROM group_messages WHERE id = ? AND group_id = ?');
        $rchk->execute([$replyTo, $gid]);
        if (!$rchk->fetch()) {
            $replyTo = null;
        }
    }

    $pdo->prepare(
        'INSERT INTO group_messages (group_id, sender_id, message_type, body, file_path, file_name, mime_type, file_size, voice_duration, reply_to_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $gid, $uid, $type, $body !== '' ? $body : null, $filePath, $fileName, $mime, $fileSize,
        $voiceDuration !== null && $voiceDuration !== '' ? (float) $voiceDuration : null,
        $replyTo,
    ]);
    $msgId = (int) $pdo->lastInsertId();
    $pdo->prepare('UPDATE group_chats SET updated_at = NOW() WHERE id = ?')->execute([$gid]);

    $gname = $pdo->prepare('SELECT name FROM group_chats WHERE id = ?');
    $gname->execute([$gid]);
    $name = (string) $gname->fetchColumn();
    $me = get_user_by_id($uid);
    $mems = $pdo->prepare('SELECT user_id FROM group_members WHERE group_id = ? AND user_id != ?');
    $mems->execute([$gid, $uid]);
    foreach ($mems->fetchAll() as $m) {
        notify_user(
            (int) $m['user_id'],
            'group_message',
            $name,
            $me['username'] . ': ' . ($type === 'text' ? str_cut((string) $body, 0, 80) : ucfirst($type)),
            'group_chat.php?id=' . $gid,
            'group_message',
            $msgId
        );
    }

    $row = $pdo->prepare(
        'SELECT gm.*, u.username AS sender_username, u.avatar AS sender_avatar,
                r.body AS reply_body, r.message_type AS reply_type, r.is_deleted AS reply_deleted
         FROM group_messages gm JOIN users u ON u.id = gm.sender_id
         LEFT JOIN group_messages r ON r.id = gm.reply_to_id WHERE gm.id = ?'
    );
    $row->execute([$msgId]);
    json_success('Message sent', format_group_message_row($row->fetch(), $uid));
}

if ($action === 'delete_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $msgId = (int) input('message_id', 0);
    $stmt = $pdo->prepare('SELECT * FROM group_messages WHERE id = ?');
    $stmt->execute([$msgId]);
    $msg = $stmt->fetch();
    if (!$msg) {
        json_error('Not found.', 404);
    }
    $me = group_member((int) $msg['group_id'], $uid);
    if (!$me) {
        json_error('Forbidden', 403);
    }
    if ((int) $msg['sender_id'] !== $uid && !group_role_at_least($me, 'admin')) {
        json_error('Forbidden', 403);
    }
    $pdo->prepare('UPDATE group_messages SET is_deleted = 1, body = NULL WHERE id = ?')->execute([$msgId]);
    json_success('Deleted');
}

if ($action === 'typing' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $gid = (int) input('group_id', 0);
    if (!group_member($gid, $uid)) {
        json_error('Forbidden', 403);
    }
    set_typing($uid, 'group', $gid);
    json_success('OK');
}

if ($action === 'report' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $msgId = (int) input('message_id', 0);
    $reason = trim((string) input('reason', ''));
    $stmt = $pdo->prepare(
        'SELECT gm.id FROM group_messages gm
         JOIN group_members m ON m.group_id = gm.group_id AND m.user_id = ?
         WHERE gm.id = ?'
    );
    $stmt->execute([$uid, $msgId]);
    if (!$stmt->fetch() || $reason === '') {
        json_error('Invalid report.');
    }
    $pdo->prepare(
        'INSERT INTO reports (reporter_id, target_type, target_id, reason) VALUES (?, \'group_message\', ?, ?)'
    )->execute([$uid, $msgId, $reason]);
    json_success('Report submitted');
}

json_error('Unknown action', 404);

function format_group(array $g): array
{
    return [
        'id' => (int) $g['id'],
        'name' => $g['name'],
        'description' => $g['description'] ?? '',
        'avatar' => $g['avatar'],
        'avatar_url' => group_avatar_url($g['avatar'] ?? null),
        'owner_id' => (int) $g['owner_id'],
        'role' => $g['role'] ?? null,
        'member_count' => isset($g['member_count']) ? (int) $g['member_count'] : null,
        'unread_count' => isset($g['unread_count']) ? (int) $g['unread_count'] : 0,
        'created_at' => $g['created_at'] ?? null,
    ];
}
