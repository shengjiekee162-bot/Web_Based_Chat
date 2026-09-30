<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
start_admin_session();
require_admin();

$pdo = db();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $op = $_POST['op'] ?? '';
    if ($op === 'create_forum') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $desc = trim((string) ($_POST['description'] ?? ''));
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? '');
        $slug = trim($slug, '-');
        if ($name !== '' && $slug !== '') {
            $pdo->prepare('INSERT INTO forums (name, slug, description, sort_order) VALUES (?, ?, ?, 99)')
                ->execute([$name, $slug, $desc]);
            $msg = 'Forum created.';
        }
    } elseif ($op === 'toggle_forum') {
        $id = (int) ($_POST['forum_id'] ?? 0);
        $pdo->prepare('UPDATE forums SET is_active = IF(is_active=1,0,1) WHERE id = ?')->execute([$id]);
        $msg = 'Forum updated.';
    } elseif ($op === 'delete_forum') {
        $id = (int) ($_POST['forum_id'] ?? 0);
        $pdo->prepare('DELETE FROM forums WHERE id = ?')->execute([$id]);
        $msg = 'Forum deleted.';
    } elseif ($op === 'hide_post') {
        $id = (int) ($_POST['post_id'] ?? 0);
        $pdo->prepare('UPDATE forum_posts SET is_hidden = 1 WHERE id = ?')->execute([$id]);
        $msg = 'Post hidden.';
    } elseif ($op === 'lock_post') {
        $id = (int) ($_POST['post_id'] ?? 0);
        $pdo->prepare('UPDATE forum_posts SET is_locked = IF(is_locked=1,0,1) WHERE id = ?')->execute([$id]);
        $msg = 'Post lock toggled.';
    }
}

$forums = $pdo->query('SELECT * FROM forums ORDER BY sort_order, name')->fetchAll();
$posts = $pdo->query(
    'SELECT p.*, u.username, f.name AS forum_name FROM forum_posts p
     JOIN users u ON u.id = p.user_id
     JOIN forums f ON f.id = p.forum_id
     ORDER BY p.id DESC LIMIT 40'
)->fetchAll();

$adminPage = 'forums';
require __DIR__ . '/_layout.php';
?>
<h1 class="h3 mb-4">Forums</h1>
<?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="surface-card mb-4">
            <h2 class="h5">Create category</h2>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="op" value="create_forum">
                <div class="mb-2"><input name="name" class="form-control" placeholder="Name" required></div>
                <div class="mb-2"><textarea name="description" class="form-control" rows="2" placeholder="Description"></textarea></div>
                <button class="btn btn-primary btn-sm">Create</button>
            </form>
        </div>
        <div class="surface-card">
            <h2 class="h5">Categories</h2>
            <?php foreach ($forums as $f): ?>
                <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                    <div>
                        <strong><?= e($f['name']) ?></strong>
                        <div class="small text-muted"><?= (int) $f['is_active'] ? 'Active' : 'Inactive' ?></div>
                    </div>
                    <form method="post" class="d-flex gap-1">
                        <?= csrf_field() ?>
                        <input type="hidden" name="forum_id" value="<?= (int) $f['id'] ?>">
                        <button name="op" value="toggle_forum" class="btn btn-sm btn-outline-secondary">Toggle</button>
                        <button name="op" value="delete_forum" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete forum and posts?')">Del</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="surface-card">
            <h2 class="h5">Recent posts</h2>
            <?php foreach ($posts as $p): ?>
                <div class="border-bottom py-2">
                    <div class="fw-semibold"><?= e($p['title']) ?></div>
                    <div class="small text-muted"><?= e($p['forum_name']) ?> · <?= e($p['username']) ?> · <?= e($p['created_at']) ?></div>
                    <form method="post" class="mt-1">
                        <?= csrf_field() ?>
                        <input type="hidden" name="post_id" value="<?= (int) $p['id'] ?>">
                        <button name="op" value="lock_post" class="btn btn-sm btn-outline-secondary"><?= (int) $p['is_locked'] ? 'Unlock' : 'Lock' ?></button>
                        <?php if (!(int) $p['is_hidden']): ?>
                        <button name="op" value="hide_post" class="btn btn-sm btn-outline-warning">Hide</button>
                        <?php else: ?>
                        <span class="badge text-bg-secondary">Hidden</span>
                        <?php endif; ?>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/_layout_end.php'; ?>
