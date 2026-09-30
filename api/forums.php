<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
start_user_session();
require_login();

$uid = (int) current_user_id();
$action = $_GET['action'] ?? input('action', 'categories');
$pdo = db();

if ($action === 'categories') {
    $stmt = $pdo->query(
        'SELECT f.*,
                (SELECT COUNT(*) FROM forum_posts p WHERE p.forum_id = f.id AND p.is_hidden = 0) AS post_count
         FROM forums f WHERE f.is_active = 1 ORDER BY f.sort_order ASC, f.name ASC'
    );
    json_success('OK', $stmt->fetchAll());
}

if ($action === 'posts') {
    $forumId = (int) ($_GET['forum_id'] ?? 0);
    $stmt = $pdo->prepare(
        'SELECT p.*, u.username, u.avatar,
                (SELECT COUNT(*) FROM post_comments c WHERE c.post_id = p.id AND c.is_hidden = 0) AS comment_count,
                (SELECT COUNT(*) FROM post_likes l WHERE l.post_id = p.id) AS like_count,
                (SELECT COUNT(*) FROM post_likes l2 WHERE l2.post_id = p.id AND l2.user_id = ?) AS liked
         FROM forum_posts p
         JOIN users u ON u.id = p.user_id
         WHERE p.forum_id = ? AND p.is_hidden = 0
         ORDER BY p.created_at DESC LIMIT 50'
    );
    $stmt->execute([$uid, $forumId]);
    $rows = [];
    foreach ($stmt->fetchAll() as $r) {
        $rows[] = format_post($r);
    }
    json_success('OK', $rows);
}

if ($action === 'post') {
    $id = (int) ($_GET['id'] ?? 0);
    $stmt = $pdo->prepare(
        'SELECT p.*, u.username, u.avatar,
                (SELECT COUNT(*) FROM post_likes l WHERE l.post_id = p.id) AS like_count,
                (SELECT COUNT(*) FROM post_likes l2 WHERE l2.post_id = p.id AND l2.user_id = ?) AS liked,
                (SELECT COUNT(*) FROM post_shares s WHERE s.post_id = p.id) AS share_count
         FROM forum_posts p JOIN users u ON u.id = p.user_id
         WHERE p.id = ? AND p.is_hidden = 0 LIMIT 1'
    );
    $stmt->execute([$uid, $id]);
    $post = $stmt->fetch();
    if (!$post) {
        json_error('Post not found.', 404);
    }
    $comments = $pdo->prepare(
        'SELECT c.*, u.username, u.avatar FROM post_comments c
         JOIN users u ON u.id = c.user_id
         WHERE c.post_id = ? AND c.is_hidden = 0 ORDER BY c.created_at ASC'
    );
    $comments->execute([$id]);
    $clist = [];
    foreach ($comments->fetchAll() as $c) {
        $clist[] = [
            'id' => (int) $c['id'],
            'content' => $c['content'],
            'created_at' => $c['created_at'],
            'user' => [
                'id' => (int) $c['user_id'],
                'username' => $c['username'],
                'avatar_url' => avatar_url($c['avatar']),
            ],
        ];
    }
    json_success('OK', ['post' => format_post($post), 'comments' => $clist]);
}

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    require_rate_limit('forum', RATE_FORUM_MAX, RATE_FORUM_WINDOW);
    $forumId = (int) input('forum_id', 0);
    $title = trim((string) input('title', ''));
    $content = trim((string) input('content', ''));
    if ($title === '' || $content === '' || str_len($title) > 200) {
        json_error('Title and content are required.');
    }
    $f = $pdo->prepare('SELECT id FROM forums WHERE id = ? AND is_active = 1');
    $f->execute([$forumId]);
    if (!$f->fetch()) {
        json_error('Forum not found.');
    }
    $pdo->prepare('INSERT INTO forum_posts (forum_id, user_id, title, content) VALUES (?, ?, ?, ?)')
        ->execute([$forumId, $uid, $title, $content]);
    json_success('Post created', ['id' => (int) $pdo->lastInsertId()]);
}

if ($action === 'comment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    require_rate_limit('forum', RATE_FORUM_MAX, RATE_FORUM_WINDOW);
    $postId = (int) input('post_id', 0);
    $content = trim((string) input('content', ''));
    if ($content === '') {
        json_error('Comment required.');
    }
    $p = $pdo->prepare('SELECT id, user_id, is_locked FROM forum_posts WHERE id = ? AND is_hidden = 0');
    $p->execute([$postId]);
    $post = $p->fetch();
    if (!$post) {
        json_error('Post not found.');
    }
    if ((int) $post['is_locked'] === 1) {
        json_error('Post is locked.');
    }
    $pdo->prepare('INSERT INTO post_comments (post_id, user_id, content) VALUES (?, ?, ?)')
        ->execute([$postId, $uid, $content]);
    $authorId = (int) $post['user_id'];
    if ($authorId !== $uid) {
        $me = get_user_by_id($uid);
        notify_user($authorId, 'forum_comment', 'New comment', $me['username'] . ' commented on your post.', 'forums.php?post=' . $postId, 'post', $postId);
    }
    json_success('Comment added');
}

if ($action === 'like' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $postId = (int) input('post_id', 0);
    $chk = $pdo->prepare('SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?');
    $chk->execute([$postId, $uid]);
    if ($chk->fetch()) {
        $pdo->prepare('DELETE FROM post_likes WHERE post_id = ? AND user_id = ?')->execute([$postId, $uid]);
        $liked = false;
    } else {
        $pdo->prepare('INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)')->execute([$postId, $uid]);
        $liked = true;
    }
    $cnt = $pdo->prepare('SELECT COUNT(*) FROM post_likes WHERE post_id = ?');
    $cnt->execute([$postId]);
    json_success('OK', ['liked' => $liked, 'like_count' => (int) $cnt->fetchColumn()]);
}

if ($action === 'share' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $postId = (int) input('post_id', 0);
    $p = $pdo->prepare('SELECT id FROM forum_posts WHERE id = ? AND is_hidden = 0');
    $p->execute([$postId]);
    if (!$p->fetch()) {
        json_error('Post not found.');
    }
    $pdo->prepare('INSERT INTO post_shares (post_id, user_id) VALUES (?, ?)')->execute([$postId, $uid]);
    json_success('Shared', ['share_url' => url('forums.php?post=' . $postId)]);
}

if ($action === 'report' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $targetType = (string) input('target_type', 'forum_post');
    $targetId = (int) input('target_id', 0);
    $reason = trim((string) input('reason', ''));
    if (!in_array($targetType, ['user', 'private_message', 'group_message', 'forum_post'], true)) {
        json_error('Invalid target.');
    }
    if ($reason === '') {
        json_error('Reason required.');
    }
    $pdo->prepare(
        'INSERT INTO reports (reporter_id, target_type, target_id, reason) VALUES (?, ?, ?, ?)'
    )->execute([$uid, $targetType, $targetId, $reason]);
    json_success('Report submitted');
}

json_error('Unknown action', 404);

function format_post(array $r): array
{
    return [
        'id' => (int) $r['id'],
        'forum_id' => (int) $r['forum_id'],
        'title' => $r['title'],
        'content' => $r['content'],
        'is_locked' => (int) ($r['is_locked'] ?? 0) === 1,
        'created_at' => $r['created_at'],
        'updated_at' => $r['updated_at'] ?? null,
        'like_count' => (int) ($r['like_count'] ?? 0),
        'comment_count' => isset($r['comment_count']) ? (int) $r['comment_count'] : null,
        'share_count' => isset($r['share_count']) ? (int) $r['share_count'] : null,
        'liked' => (int) ($r['liked'] ?? 0) > 0,
        'author' => [
            'id' => (int) $r['user_id'],
            'username' => $r['username'],
            'avatar_url' => avatar_url($r['avatar'] ?? null),
        ],
    ];
}
