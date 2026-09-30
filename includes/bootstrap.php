<?php
/**
 * Application bootstrap
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

if (!headers_sent()) {
    header('Permissions-Policy: microphone=(self), camera=(self), display-capture=(self)');
}

if (!is_dir(APP_ROOT . '/logs')) {
    @mkdir(APP_ROOT . '/logs', 0755, true);
}
