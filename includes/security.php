<?php
/**
 * Security helpers: CSRF, XSS, sessions, rate limits, uploads
 */

declare(strict_types=1);

function app_log(string $message): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    @file_put_contents(LOG_PATH, $line, FILE_APPEND | LOCK_EX);
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function start_user_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    session_name(SESSION_NAME);
    session_start();
}

function start_admin_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    session_name(ADMIN_SESSION_NAME);
    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(?string $token = null): bool
{
    $token = $token ?? ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($token === '' || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function require_csrf(): void
{
    if (!verify_csrf()) {
        json_error('Invalid security token. Please refresh and try again.', 403);
    }
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function rate_limit(string $action, int $max, int $windowSeconds): bool
{
    $key = $action . ':' . client_ip() . ':' . (string) ($_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? 'guest');
    $pdo = db();
    $now = new DateTimeImmutable('now');

    $stmt = $pdo->prepare('SELECT id, hits, window_start FROM rate_limits WHERE rate_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $row = $stmt->fetch();

    if (!$row) {
        $ins = $pdo->prepare('INSERT INTO rate_limits (rate_key, hits, window_start) VALUES (?, 1, ?)');
        $ins->execute([$key, $now->format('Y-m-d H:i:s')]);
        return true;
    }

    $windowStart = new DateTimeImmutable($row['window_start']);
    $elapsed = $now->getTimestamp() - $windowStart->getTimestamp();

    if ($elapsed >= $windowSeconds) {
        $upd = $pdo->prepare('UPDATE rate_limits SET hits = 1, window_start = ? WHERE id = ?');
        $upd->execute([$now->format('Y-m-d H:i:s'), $row['id']]);
        return true;
    }

    if ((int) $row['hits'] >= $max) {
        return false;
    }

    $upd = $pdo->prepare('UPDATE rate_limits SET hits = hits + 1 WHERE id = ?');
    $upd->execute([$row['id']]);
    return true;
}

function require_rate_limit(string $action, int $max, int $windowSeconds): void
{
    if (!rate_limit($action, $max, $windowSeconds)) {
        json_error('Too many requests. Please wait and try again.', 429);
    }
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function json_success(string $message = 'OK', $data = null, int $status = 200): void
{
    $out = ['success' => true, 'message' => $message];
    if ($data !== null) {
        $out['data'] = $data;
    }
    json_response($out, $status);
}

function json_error(string $message, int $status = 400, $data = null): void
{
    $out = ['success' => false, 'message' => $message];
    if ($data !== null) {
        $out['data'] = $data;
    }
    json_response($out, $status);
}

function random_filename(string $extension): string
{
    $ext = strtolower(preg_replace('/[^a-z0-9]/i', '', $extension) ?? '');
    return bin2hex(random_bytes(16)) . ($ext !== '' ? '.' . $ext : '');
}

function detect_mime(string $path): string
{
    if (!is_file($path)) {
        return 'application/octet-stream';
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($path);
    return $mime ?: 'application/octet-stream';
}

function safe_extension(string $filename): string
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return preg_replace('/[^a-z0-9]/', '', $ext) ?? '';
}

/**
 * @return array{path:string,stored:string,original:string,mime:string,size:int}|null
 */
function store_upload(array $file, string $subdir, array $allowedExt, array $allowedMime, int $maxSize): ?array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return null;
    }
    if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxSize) {
        return null;
    }

    $ext = safe_extension($file['name'] ?? '');
    if ($ext === '' || !in_array($ext, $allowedExt, true)) {
        return null;
    }

    $mime = detect_mime($file['tmp_name']);
    if (!in_array($mime, $allowedMime, true)) {
        return null;
    }

    $stored = random_filename($ext);
    $dir = APP_ROOT . '/uploads/' . $subdir;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return null;
    }

    $dest = $dir . '/' . $stored;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return null;
    }
    @chmod($dest, 0644);

    return [
        'path'     => $subdir . '/' . $stored,
        'stored'   => $stored,
        'original' => basename((string) ($file['name'] ?? $stored)),
        'mime'     => $mime,
        'size'     => (int) $file['size'],
    ];
}

function upload_rules(string $kind): array
{
    return match ($kind) {
        'avatar', 'group_avatar' => [
            'subdir' => $kind === 'avatar' ? 'avatars' : 'groups',
            'ext'    => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'mime'   => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            'max'    => UPLOAD_MAX_AVATAR,
        ],
        'image' => [
            'subdir' => 'images',
            'ext'    => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'mime'   => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            'max'    => UPLOAD_MAX_IMAGE,
        ],
        'file' => [
            'subdir' => 'files',
            'ext'    => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip'],
            'mime'   => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/plain',
                'application/zip',
                'application/x-zip-compressed',
            ],
            'max' => UPLOAD_MAX_FILE,
        ],
        'voice' => [
            'subdir' => 'voices',
            'ext'    => ['webm', 'ogg', 'mp3', 'wav', 'm4a'],
            'mime'   => [
                'audio/webm',
                'video/webm',
                'audio/ogg',
                'audio/mpeg',
                'audio/wav',
                'audio/x-wav',
                'audio/mp4',
                'audio/x-m4a',
            ],
            'max' => UPLOAD_MAX_VOICE,
        ],
        default => throw new InvalidArgumentException('Unknown upload kind'),
    };
}
