<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
start_user_session();
require_login();

$uid = (int) current_user_id();
touch_presence($uid);
$action = $_GET['action'] ?? input('action', 'list');
$pdo = db();

if ($action === 'list') {
    $stmt = $pdo->prepare(
        'SELECT n.* FROM notifications n WHERE n.user_id = ? ORDER BY n.id DESC LIMIT 50'
    );
    $stmt->execute([$uid]);
    $rows = $stmt->fetchAll();
    json_success('OK', [
        'items' => $rows,
        'unread' => unread_notification_count($uid),
    ]);
}

if ($action === 'unread_count') {
    json_success('OK', ['unread' => unread_notification_count($uid)]);
}

if ($action === 'mark_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id = (int) input('id', 0);
    if ($id > 0) {
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
    } else {
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')->execute([$uid]);
    }
    json_success('OK', ['unread' => unread_notification_count($uid)]);
}

json_error('Unknown action', 404);
