<?php
/**
 * cPanel production config example
 *
 * On the SERVER (File Manager → Web_Based_Chat/includes/):
 *   Copy this file to: config.local.php
 *   Then fill in your real MySQL credentials from cPanel.
 *
 * Do NOT commit real passwords. config.local.php is gitignored.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'your_cpanel_database');
define('DB_USER', 'your_cpanel_db_user');
define('DB_PASS', 'your_cpanel_db_password');

define('APP_URL', 'https://your-domain.com/Web_Based_Chat');
define('SHOW_DEMO_LOGIN', true); // set false in real production
