<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
start_user_session();
require_login();

$uid = (int) current_user_id();
touch_presence($uid);
$action = $_GET['action'] ?? input('action', '');
$pdo = db();

if ($action === 'start' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    require_rate_limit('call', RATE_CALL_MAX, RATE_CALL_WINDOW);

    $calleeId = (int) input('callee_id', 0);
    $callType = (string) input('call_type', 'voice');
    $offerSdp = (string) input('offer_sdp', '');

    if (!in_array($callType, ['voice', 'video'], true)) {
        json_error('Invalid call type.');
    }
    $err = can_call($uid, $calleeId);
    if ($err) {
        json_error($err, 403);
    }
    if ($offerSdp === '') {
        json_error('Missing WebRTC offer.');
    }

    // End stale ringing calls (also writes missed call chat entries)
    expire_stale_ringing_calls();

    // Prevent concurrent active calls
    $busy = $pdo->prepare(
        "SELECT id, caller_id, callee_id FROM voice_call_sessions
         WHERE status IN ('ringing','accepted')
           AND (caller_id IN (?,?) OR callee_id IN (?,?))
         LIMIT 1"
    );
    $busy->execute([$uid, $calleeId, $uid, $calleeId]);
    if ($busy->fetch()) {
        json_error('User is busy on another call.', 409);
    }

    $pdo->prepare(
        'INSERT INTO voice_call_sessions (caller_id, callee_id, call_type, status, offer_sdp)
         VALUES (?, ?, ?, \'ringing\', ?)'
    )->execute([$uid, $calleeId, $callType, $offerSdp]);
    $callId = (int) $pdo->lastInsertId();

    $me = get_user_by_id($uid);
    notify_user(
        $calleeId,
        'incoming_call',
        'Incoming ' . $callType . ' call',
        $me['username'] . ' is calling you',
        'chat.php?user=' . $uid,
        'call',
        $callId
    );

    json_success('Call started', [
        'call_id' => $callId,
        'status' => 'ringing',
        'ice_servers' => ice_servers_config(),
    ]);
}

if ($action === 'incoming') {
    expire_stale_ringing_calls();
    $timeout = (int) CALL_RING_TIMEOUT;

    $stmt = $pdo->prepare(
        "SELECT c.*, u.username AS caller_name, u.avatar AS caller_avatar
         FROM voice_call_sessions c
         JOIN users u ON u.id = c.caller_id
         WHERE c.callee_id = ? AND c.status = 'ringing'
           AND c.created_at >= DATE_SUB(NOW(), INTERVAL {$timeout} SECOND)
         ORDER BY c.id DESC LIMIT 1"
    );
    $stmt->execute([$uid]);
    $call = $stmt->fetch();
    if (!$call) {
        json_success('OK', null);
    }
    json_success('OK', [
        'id' => (int) $call['id'],
        'call_type' => $call['call_type'],
        'status' => $call['status'],
        'offer_sdp' => $call['offer_sdp'],
        'caller' => public_user([
            'id' => $call['caller_id'],
            'username' => $call['caller_name'],
            'avatar' => $call['caller_avatar'],
            'presence' => 'online',
            'last_seen_at' => null,
            'last_activity_at' => date('Y-m-d H:i:s'),
            'status_message' => '',
        ]),
        'ice_servers' => ice_servers_config(),
    ]);
}

if ($action === 'accept' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $callId = (int) input('call_id', 0);
    $answerSdp = (string) input('answer_sdp', '');
    $stmt = $pdo->prepare('SELECT * FROM voice_call_sessions WHERE id = ? AND callee_id = ? AND status = \'ringing\'');
    $stmt->execute([$callId, $uid]);
    $call = $stmt->fetch();
    if (!$call) {
        json_error('Call not found or no longer ringing.', 404);
    }
    if ($answerSdp === '') {
        json_error('Missing answer.');
    }
    $err = can_call((int) $call['caller_id'], $uid);
    if ($err) {
        json_error($err, 403);
    }
    $pdo->prepare(
        'UPDATE voice_call_sessions SET status = \'accepted\', answer_sdp = ?, started_at = NOW() WHERE id = ?'
    )->execute([$answerSdp, $callId]);
    json_success('Accepted', ['call_id' => $callId, 'ice_servers' => ice_servers_config()]);
}

if ($action === 'reject' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $callId = (int) input('call_id', 0);
    $stmt = $pdo->prepare(
        "UPDATE voice_call_sessions SET status = 'rejected', ended_at = NOW()
         WHERE id = ? AND callee_id = ? AND status = 'ringing'"
    );
    $stmt->execute([$callId, $uid]);
    if ($stmt->rowCount() === 0) {
        json_error('Call not found.');
    }
    $pdo->prepare(
        'INSERT INTO voice_call_signals (call_id, sender_id, signal_type, payload) VALUES (?, ?, \'hangup\', ?)'
    )->execute([$callId, $uid, json_encode(['reason' => 'rejected'])]);
    record_call_chat_message($callId, 'rejected');
    json_success('Rejected');
}

if ($action === 'cancel' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $callId = (int) input('call_id', 0);
    $stmt = $pdo->prepare(
        "UPDATE voice_call_sessions SET status = 'cancelled', ended_at = NOW()
         WHERE id = ? AND caller_id = ? AND status = 'ringing'"
    );
    $stmt->execute([$callId, $uid]);
    $pdo->prepare(
        'INSERT INTO voice_call_signals (call_id, sender_id, signal_type, payload) VALUES (?, ?, \'hangup\', ?)'
    )->execute([$callId, $uid, json_encode(['reason' => 'cancelled'])]);
    if ($stmt->rowCount() > 0) {
        record_call_chat_message($callId, 'cancelled');
    }
    json_success('Cancelled');
}

if ($action === 'end' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $callId = (int) input('call_id', 0);
    $stmt = $pdo->prepare(
        "UPDATE voice_call_sessions SET status = 'ended', ended_at = NOW()
         WHERE id = ? AND status IN ('ringing','accepted') AND (caller_id = ? OR callee_id = ?)"
    );
    $stmt->execute([$callId, $uid, $uid]);
    // Signal hangup
    $pdo->prepare(
        'INSERT INTO voice_call_signals (call_id, sender_id, signal_type, payload) VALUES (?, ?, \'hangup\', ?)'
    )->execute([$callId, $uid, json_encode(['reason' => 'ended'])]);
    if ($stmt->rowCount() > 0) {
        // If never accepted, treat as cancelled/missed style ended while ringing
        $c = $pdo->prepare('SELECT status, started_at FROM voice_call_sessions WHERE id = ?');
        $c->execute([$callId]);
        $row = $c->fetch();
        // status already 'ended'; use started_at to decide label
        if ($row && empty($row['started_at'])) {
            record_call_chat_message($callId, 'cancelled');
        } else {
            record_call_chat_message($callId, 'ended');
        }
    }
    json_success('Ended');
}

if ($action === 'status') {
    $callId = (int) ($_GET['call_id'] ?? 0);
    $stmt = $pdo->prepare(
        'SELECT id, caller_id, callee_id, call_type, status, offer_sdp, answer_sdp, started_at, ended_at
         FROM voice_call_sessions WHERE id = ? AND (caller_id = ? OR callee_id = ?) LIMIT 1'
    );
    $stmt->execute([$callId, $uid, $uid]);
    $call = $stmt->fetch();
    if (!$call) {
        json_error('Not found', 404);
    }
    json_success('OK', $call);
}

if ($action === 'signal' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $callId = (int) input('call_id', 0);
    $signalType = (string) input('signal_type', 'ice');
    $payload = input('payload', null);
    if (!in_array($signalType, ['ice', 'renegotiate', 'hangup'], true)) {
        json_error('Invalid signal.');
    }
    $stmt = $pdo->prepare(
        "SELECT id FROM voice_call_sessions
         WHERE id = ? AND (caller_id = ? OR callee_id = ?) AND status IN ('ringing','accepted')"
    );
    $stmt->execute([$callId, $uid, $uid]);
    if (!$stmt->fetch()) {
        json_error('Call not active.', 404);
    }
    $json = is_string($payload) ? $payload : json_encode($payload);
    $pdo->prepare(
        'INSERT INTO voice_call_signals (call_id, sender_id, signal_type, payload) VALUES (?, ?, ?, ?)'
    )->execute([$callId, $uid, $signalType, $json]);
    json_success('OK');
}

if ($action === 'poll_signals') {
    $callId = (int) ($_GET['call_id'] ?? 0);
    $afterId = (int) ($_GET['after_id'] ?? 0);
    $stmt = $pdo->prepare(
        'SELECT id FROM voice_call_sessions WHERE id = ? AND (caller_id = ? OR callee_id = ?) LIMIT 1'
    );
    $stmt->execute([$callId, $uid, $uid]);
    if (!$stmt->fetch()) {
        json_error('Forbidden', 403);
    }

    $sig = $pdo->prepare(
        'SELECT id, sender_id, signal_type, payload, created_at
         FROM voice_call_signals
         WHERE call_id = ? AND sender_id != ? AND id > ? AND consumed = 0
         ORDER BY id ASC LIMIT 50'
    );
    $sig->execute([$callId, $uid, $afterId]);
    $rows = $sig->fetchAll();
    if ($rows) {
        $ids = array_column($rows, 'id');
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("UPDATE voice_call_signals SET consumed = 1 WHERE id IN ($ph)")->execute($ids);
    }

    $call = $pdo->prepare('SELECT status, answer_sdp FROM voice_call_sessions WHERE id = ?');
    $call->execute([$callId]);
    $c = $call->fetch();

    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id' => (int) $r['id'],
            'sender_id' => (int) $r['sender_id'],
            'signal_type' => $r['signal_type'],
            'payload' => json_decode($r['payload'], true) ?? $r['payload'],
            'created_at' => $r['created_at'],
        ];
    }

    json_success('OK', [
        'signals' => $out,
        'status' => $c['status'] ?? null,
        'answer_sdp' => $c['answer_sdp'] ?? null,
    ]);
}

if ($action === 'history') {
    $stmt = $pdo->prepare(
        "SELECT c.id, c.call_type, c.status, c.started_at, c.ended_at, c.created_at,
                c.caller_id, c.callee_id,
                cu.username AS caller_name, ru.username AS callee_name
         FROM voice_call_sessions c
         JOIN users cu ON cu.id = c.caller_id
         JOIN users ru ON ru.id = c.callee_id
         WHERE c.caller_id = ? OR c.callee_id = ?
         ORDER BY c.id DESC
         LIMIT 40"
    );
    $stmt->execute([$uid, $uid]);
    $rows = [];
    foreach ($stmt->fetchAll() as $r) {
        $otherName = ((int) $r['caller_id'] === $uid) ? $r['callee_name'] : $r['caller_name'];
        $rows[] = [
            'id' => (int) $r['id'],
            'call_type' => $r['call_type'],
            'status' => $r['status'],
            'peer_name' => $otherName,
            'direction' => ((int) $r['caller_id'] === $uid) ? 'outgoing' : 'incoming',
            'created_at' => $r['created_at'],
            'started_at' => $r['started_at'],
            'ended_at' => $r['ended_at'],
        ];
    }
    json_success('OK', $rows);
}

json_error('Unknown action', 404);
