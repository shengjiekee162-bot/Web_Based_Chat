<?php
/**
 * Copy this file to config.local.php on the server (cPanel) or locally.
 * config.local.php is gitignored — do not commit passwords.
 *
 * cPanel notes:
 * - DB_HOST is usually "localhost" (PHP and MySQL on same host)
 * - APP_URL must be your real HTTPS domain (not 127.0.0.1)
 * - Import database/database.sql in phpMyAdmin first
 */

// --- cPanel example ---
// define('DB_HOST', 'localhost');
// define('DB_NAME', 'synergy1_your_database');
// define('DB_USER', 'synergy1_your_user');
// define('DB_PASS', 'your-db-password');
// define('APP_URL', 'https://your-domain.com');
// define('SHOW_DEMO_LOGIN', false);

// --- Local XAMPP example ---
// define('DB_HOST', '127.0.0.1');
// define('DB_NAME', 'webconnect');
// define('DB_USER', 'root');
// define('DB_PASS', '123qwe');
// define('APP_URL', 'http://127.0.0.1:8000');

// Optional TURN for WebRTC:
// define('TURN_SERVERS', [
//     ['urls' => 'turn:your.server:3478', 'username' => 'user', 'credential' => 'secret'],
// ]);
