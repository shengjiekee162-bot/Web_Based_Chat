<?php
/**
 * Secure media delivery with auth + optional HTTP Range support
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
start_user_session();

$type = $_GET['type'] ?? 'file';
$f = (string) ($_GET['f'] ?? '');
$disposition = ($_GET['d'] ?? 'inline') === 'attachment' ? 'attachment' : 'inline';

if ($f === '' || str_contains($f, '..') || str_contains($f, '\\')) {
    http_response_code(400);
    exit('Bad request');
}

$uploadsRoot = realpath(APP_ROOT . '/uploads');
if ($uploadsRoot === false) {
    http_response_code(500);
    exit('Uploads unavailable');
}

// Avatars / group avatars — require login but allow any logged-in user
if ($type === 'avatar') {
    require_login();
    $path = realpath($uploadsRoot . '/avatars/' . basename($f));
    serve_file($path, $uploadsRoot . DIRECTORY_SEPARATOR . 'avatars', $disposition);
}

if ($type === 'group') {
    require_login();
    $path = realpath($uploadsRoot . '/groups/' . basename($f));
    serve_file($path, $uploadsRoot . DIRECTORY_SEPARATOR . 'groups', $disposition);
}

// Private media: path like images/xxx.jpg or voices/xxx.webm
require_login();
$uid = (int) current_user_id();
$relative = ltrim(str_replace('\\', '/', $f), '/');
$parts = explode('/', $relative, 2);
if (count($parts) !== 2) {
    http_response_code(400);
    exit('Bad path');
}
[$subdir, $name] = $parts;
$allowedDirs = ['images', 'files', 'voices', 'forums'];
if (!in_array($subdir, $allowedDirs, true)) {
    http_response_code(403);
    exit('Forbidden');
}
$name = basename($name);
$full = realpath($uploadsRoot . '/' . $subdir . '/' . $name);
$dirReal = realpath($uploadsRoot . '/' . $subdir);

if ($full === false || $dirReal === false) {
    http_response_code(404);
    exit('Not found');
}
$fullNorm = strtolower(str_replace('\\', '/', $full));
$dirNorm = strtolower(str_replace('\\', '/', $dirReal));
if (!str_starts_with($fullNorm, rtrim($dirNorm, '/') . '/')) {
    http_response_code(404);
    exit('Not found');
}

$relPath = $subdir . '/' . $name;

// Authorize: private message attachment, group message, or forum (forum open to members)
$ok = false;

$stmt = db()->prepare(
    'SELECT sender_id, receiver_id FROM messages WHERE file_path = ? LIMIT 1'
);
$stmt->execute([$relPath]);
$msg = $stmt->fetch();
if ($msg && ((int) $msg['sender_id'] === $uid || (int) $msg['receiver_id'] === $uid)) {
    $ok = true;
}

if (!$ok) {
    $stmt = db()->prepare(
        'SELECT gm.group_id FROM group_messages gm
         JOIN group_members m ON m.group_id = gm.group_id AND m.user_id = ?
         WHERE gm.file_path = ? LIMIT 1'
    );
    $stmt->execute([$uid, $relPath]);
    if ($stmt->fetch()) {
        $ok = true;
    }
}

if (!$ok && $subdir === 'forums') {
    $ok = true; // forum attachments visible to logged-in users
}

if (!$ok) {
    http_response_code(403);
    exit('Forbidden');
}

serve_file($full, $dirReal, $disposition);

function serve_file(?string $path, string $allowedDir, string $disposition): void
{
    if ($path === false || $path === null || !is_file($path)) {
        http_response_code(404);
        exit('Not found');
    }

    $pathNorm = strtolower(str_replace('\\', '/', $path));
    $dirNorm = strtolower(str_replace('\\', '/', $allowedDir));
    if (!str_starts_with($pathNorm, rtrim($dirNorm, '/') . '/')) {
        http_response_code(404);
        exit('Not found');
    }

    $mime = detect_mime($path);
    $size = filesize($path);
    $filename = basename($path);

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Content-Type: ' . $mime);
    header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($filename) . '"');
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, max-age=3600');

    $start = 0;
    $end = $size - 1;

    if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
        if ($m[1] !== '') {
            $start = (int) $m[1];
        }
        if ($m[2] !== '') {
            $end = (int) $m[2];
        }
        if ($start > $end || $start >= $size) {
            http_response_code(416);
            header("Content-Range: bytes */$size");
            exit;
        }
        http_response_code(206);
        header("Content-Range: bytes $start-$end/$size");
        header('Content-Length: ' . ($end - $start + 1));
    } else {
        header('Content-Length: ' . $size);
    }

    $fp = fopen($path, 'rb');
    if ($fp === false) {
        http_response_code(500);
        exit;
    }
    fseek($fp, $start);
    $remaining = $end - $start + 1;
    while ($remaining > 0 && !feof($fp)) {
        $chunk = fread($fp, min(8192, $remaining));
        if ($chunk === false) {
            break;
        }
        echo $chunk;
        $remaining -= strlen($chunk);
    }
    fclose($fp);
    exit;
}
