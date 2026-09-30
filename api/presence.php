<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
start_user_session();
require_login();

$uid = (int) current_user_id();
touch_presence($uid);
$action = $_GET['action'] ?? input('action', 'heartbeat');
$pdo = db();

if ($action === 'heartbeat' || $action === 'online') {
    $presence = (string) input('presence', 'online');
    if (!in_array($presence, ['online', 'away', 'busy', 'offline'], true)) {
        $presence = 'online';
    }
    touch_presence($uid, $presence);
    json_success('OK', ['presence' => $presence]);
}

if ($action === 'get') {
    $ids = input('ids', []);
    if (is_string($ids)) {
        $ids = array_filter(array_map('intval', explode(',', $ids)));
    }
    if (!is_array($ids) || !$ids) {
        json_success('OK', []);
    }
    $ids = array_values(array_unique(array_map('intval', $ids)));
    $ids = array_slice($ids, 0, 50);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, username, avatar, presence, last_seen_at, last_activity_at FROM users WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $out = [];
    foreach ($stmt->fetchAll() as $u) {
        $out[] = public_user($u);
    }
    json_success('OK', $out);
}

json_error('Unknown action', 404);
