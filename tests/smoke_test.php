<?php
/**
 * Lightweight API smoke tests (CLI): php tests/smoke_test.php
 */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

function assert_true(bool $cond, string $label): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    echo "OK: {$label}\n";
}

$pdo = db();
assert_true($pdo instanceof PDO, 'database connection');

$tables = ['users', 'messages', 'group_chats', 'voice_call_sessions', 'password_resets'];
foreach ($tables as $t) {
    $n = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = " . $pdo->quote($t))->fetchColumn();
    assert_true($n === 1, "table exists: {$t}");
}

$hash = password_hash('Demo@12345', PASSWORD_DEFAULT);
assert_true(password_verify('Demo@12345', $hash), 'password_hash/verify');

assert_true(function_exists('can_message'), 'can_message helper');
assert_true(function_exists('format_message_row'), 'format_message_row helper');
assert_true(defined('SHOW_DEMO_LOGIN'), 'SHOW_DEMO_LOGIN defined');
assert_true(defined('CALL_RING_TIMEOUT'), 'CALL_RING_TIMEOUT defined');

$cols = $pdo->query("SHOW COLUMNS FROM users LIKE 'notify_desktop'")->fetch();
assert_true((bool) $cols, 'users.notify_desktop column');

echo "\nAll smoke tests passed.\n";
