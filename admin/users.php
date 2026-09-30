<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
start_admin_session();
require_admin();

$pdo = db();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $userId = (int) ($_POST['user_id'] ?? 0);
    $op = $_POST['op'] ?? '';
    if ($op === 'disable') {
        $pdo->prepare('UPDATE users SET is_disabled = 1 WHERE id = ?')->execute([$userId]);
        $msg = 'User disabled.';
    } elseif ($op === 'enable') {
        $pdo->prepare('UPDATE users SET is_disabled = 0 WHERE id = ?')->execute([$userId]);
        $msg = 'User enabled.';
    } elseif ($op === 'delete') {
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
        $msg = 'User deleted.';
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
if ($q !== '') {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username LIKE ? OR email LIKE ? ORDER BY id DESC LIMIT 100');
    $like = '%' . $q . '%';
    $stmt->execute([$like, $like]);
} else {
    $stmt = $pdo->query('SELECT * FROM users ORDER BY id DESC LIMIT 100');
}
$users = $stmt->fetchAll();
$adminPage = 'users';
require __DIR__ . '/_layout.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h3 mb-0">Users</h1>
    <form class="d-flex gap-2" method="get">
        <input type="search" name="q" value="<?= e($q) ?>" class="form-control form-control-sm" placeholder="Search username/email">
        <button class="btn btn-sm btn-primary">Search</button>
    </form>
</div>
<?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
<div class="table-responsive surface-card p-0">
<table class="table table-hover mb-0 align-middle">
    <thead><tr><th>ID</th><th>User</th><th>Email</th><th>Status</th><th>Joined</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
        <tr>
            <td><?= (int) $u['id'] ?></td>
            <td><?= e($u['username']) ?></td>
            <td><?= e($u['email']) ?></td>
            <td><?= (int) $u['is_disabled'] ? '<span class="badge text-bg-danger">Disabled</span>' : '<span class="badge text-bg-success">Active</span>' ?></td>
            <td class="small"><?= e($u['created_at']) ?></td>
            <td class="text-end">
                <form method="post" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                    <?php if ((int) $u['is_disabled']): ?>
                        <button name="op" value="enable" class="btn btn-sm btn-outline-success">Enable</button>
                    <?php else: ?>
                        <button name="op" value="disable" class="btn btn-sm btn-outline-warning">Disable</button>
                    <?php endif; ?>
                    <button name="op" value="delete" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this user permanently?')">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php require __DIR__ . '/_layout_end.php'; ?>
