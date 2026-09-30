<?php
/**
 * WebConnect — Central configuration
 * Local / cPanel overrides: includes/config.local.php (loaded first)
 */

declare(strict_types=1);

if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

if (!defined('APP_NAME')) {
    define('APP_NAME', 'WebConnect');
}
if (!defined('APP_VERSION')) {
    define('APP_VERSION', '1.0.0');
}

// Default = local XAMPP. Override on cPanel via config.local.php
if (!defined('DB_HOST')) {
    define('DB_HOST', '127.0.0.1');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'webconnect');
}
if (!defined('DB_USER')) {
    define('DB_USER', 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', '');
}
if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8mb4');
}

if (!defined('APP_URL')) {
    define('APP_URL', 'http://127.0.0.1:8000');
}
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

if (!defined('SESSION_NAME')) {
    define('SESSION_NAME', 'webconnect_sess');
}
if (!defined('ADMIN_SESSION_NAME')) {
    define('ADMIN_SESSION_NAME', 'webconnect_admin_sess');
}

if (!defined('UPLOAD_MAX_IMAGE')) {
    define('UPLOAD_MAX_IMAGE', 5 * 1024 * 1024);
}
if (!defined('UPLOAD_MAX_FILE')) {
    define('UPLOAD_MAX_FILE', 15 * 1024 * 1024);
}
if (!defined('UPLOAD_MAX_VOICE')) {
    define('UPLOAD_MAX_VOICE', 8 * 1024 * 1024);
}
if (!defined('UPLOAD_MAX_AVATAR')) {
    define('UPLOAD_MAX_AVATAR', 2 * 1024 * 1024);
}

if (!defined('TYPING_TTL_SECONDS')) {
    define('TYPING_TTL_SECONDS', 4);
}
if (!defined('PRESENCE_ONLINE_SECONDS')) {
    define('PRESENCE_ONLINE_SECONDS', 60);
}
if (!defined('POLL_MESSAGE_MS')) {
    define('POLL_MESSAGE_MS', 1800);
}
if (!defined('POLL_CALL_MS')) {
    define('POLL_CALL_MS', 900);
}
if (!defined('POLL_NOTIF_MS')) {
    define('POLL_NOTIF_MS', 4000);
}

if (!defined('PASSWORD_MIN_LENGTH')) {
    define('PASSWORD_MIN_LENGTH', 8);
}

if (!defined('RATE_LOGIN_MAX')) {
    define('RATE_LOGIN_MAX', 8);
}
if (!defined('RATE_LOGIN_WINDOW')) {
    define('RATE_LOGIN_WINDOW', 300);
}
if (!defined('RATE_MESSAGE_MAX')) {
    define('RATE_MESSAGE_MAX', 40);
}
if (!defined('RATE_MESSAGE_WINDOW')) {
    define('RATE_MESSAGE_WINDOW', 60);
}
if (!defined('RATE_FRIEND_MAX')) {
    define('RATE_FRIEND_MAX', 20);
}
if (!defined('RATE_FRIEND_WINDOW')) {
    define('RATE_FRIEND_WINDOW', 300);
}
if (!defined('RATE_FORUM_MAX')) {
    define('RATE_FORUM_MAX', 10);
}
if (!defined('RATE_FORUM_WINDOW')) {
    define('RATE_FORUM_WINDOW', 300);
}
if (!defined('RATE_CALL_MAX')) {
    define('RATE_CALL_MAX', 10);
}
if (!defined('RATE_CALL_WINDOW')) {
    define('RATE_CALL_WINDOW', 120);
}

if (!defined('ALLOWED_REACTIONS')) {
    define('ALLOWED_REACTIONS', ['👍', '❤️', '😂', '😮', '😢', '👏']);
}

if (!defined('STUN_SERVERS')) {
    define('STUN_SERVERS', [
        ['urls' => 'stun:stun.l.google.com:19302'],
        ['urls' => 'stun:stun1.l.google.com:19302'],
        // Public TURN fallback so mobile / different networks can connect
        [
            'urls' => 'turn:openrelay.metered.ca:80',
            'username' => 'openrelayproject',
            'credential' => 'openrelayproject',
        ],
        [
            'urls' => 'turn:openrelay.metered.ca:443',
            'username' => 'openrelayproject',
            'credential' => 'openrelayproject',
        ],
        [
            'urls' => 'turn:openrelay.metered.ca:443?transport=tcp',
            'username' => 'openrelayproject',
            'credential' => 'openrelayproject',
        ],
    ]);
}

if (!defined('LOG_PATH')) {
    define('LOG_PATH', APP_ROOT . '/logs/app.log');
}

if (!defined('SHOW_DEMO_LOGIN')) {
    define('SHOW_DEMO_LOGIN', true);
}

if (!defined('CALL_RING_TIMEOUT')) {
    define('CALL_RING_TIMEOUT', 45);
}
