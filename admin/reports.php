<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
start_admin_session();
require_admin();

$pdo = db();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $id = (int) ($_POST['report_id'] ?? 0);
    $status = $_POST['status'] ?? 'reviewed';
    $notes = trim((string) ($_POST['admin_notes'] ?? ''));
    if (!in_array($status, ['open', 'reviewed', 'resolved'], true)) {
        $status = 'reviewed';
    }
    $pdo->prepare(
        'UPDATE reports SET status = ?, admin_notes = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?'
    )->execute([$status, $notes, current_admin_id(), $id]);
    $msg = 'Report updated.';
}

$reports = $pdo->query(
    'SELECT r.*, u.username AS reporter_name
     FROM reports r JOIN users u ON u.id = r.reporter_id
     ORDER BY FIELD(r.status,\'open\',\'reviewed\',\'resolved\'), r.id DESC LIMIT 100'
)->fetchAll();

$adminPage = 'reports';
require __DIR__ . '/_layout.php';
?>
<h1 class="h3 mb-4">Reports</h1>
<?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
<div class="list-stack">
<?php foreach ($reports as $r): ?>
    <div class="surface-card">
        <div class="d-flex justify-content-between gap-2 flex-wrap">
            <div>
                <span class="badge text-bg-<?= $r['status'] === 'open' ? 'danger' : ($r['status'] === 'resolved' ? 'success' : 'secondary') ?>"><?= e($r['status']) ?></span>
                <strong class="ms-1"><?= e($r['target_type']) ?> #<?= (int) $r['target_id'] ?></strong>
                <div class="small text-muted">By <?= e($r['reporter_name']) ?> · <?= e($r['created_at']) ?></div>
                <p class="mb-0 mt-2"><?= e($r['reason']) ?></p>
            </div>
        </div>
        <form method="post" class="row g-2 mt-3 align-items-end">
            <?= csrf_field() ?>
            <input type="hidden" name="report_id" value="<?= (int) $r['id'] ?>">
            <div class="col-md-3">
                <label class="form-label small">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <?php foreach (['open', 'reviewed', 'resolved'] as $s): ?>
                        <option value="<?= $s ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label small">Admin notes</label>
                <input type="text" name="admin_notes" class="form-control form-control-sm" value="<?= e((string) $r['admin_notes']) ?>">
            </div>
            <div class="col-md-3">
                <button class="btn btn-sm btn-primary w-100">Update</button>
            </div>
        </form>
    </div>
<?php endforeach; ?>
<?php if (!$reports): ?><div class="text-muted">No reports.</div><?php endif; ?>
</div>
<?php require __DIR__ . '/_layout_end.php'; ?>
