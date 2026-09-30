<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
start_admin_session();
require_admin();

$pdo = db();
$stats = [
    'users' => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'active' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_disabled = 0')->fetchColumn(),
    'disabled' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_disabled = 1')->fetchColumn(),
    'messages' => (int) $pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn(),
    'groups' => (int) $pdo->query('SELECT COUNT(*) FROM group_chats')->fetchColumn(),
    'posts' => (int) $pdo->query('SELECT COUNT(*) FROM forum_posts')->fetchColumn(),
    'reports' => (int) $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'open'")->fetchColumn(),
];

$msgDays = $pdo->query(
    "SELECT DATE(created_at) AS d, COUNT(*) AS c
     FROM messages
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY DATE(created_at)
     ORDER BY d ASC"
)->fetchAll();
$userDays = $pdo->query(
    "SELECT DATE(created_at) AS d, COUNT(*) AS c
     FROM users
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY DATE(created_at)
     ORDER BY d ASC"
)->fetchAll();

$labels = [];
for ($i = 6; $i >= 0; $i--) {
    $labels[] = (new DateTimeImmutable("-{$i} days"))->format('Y-m-d');
}
$msgMap = array_column($msgDays, 'c', 'd');
$userMap = array_column($userDays, 'c', 'd');
$msgSeries = array_map(static fn($d) => (int) ($msgMap[$d] ?? 0), $labels);
$userSeries = array_map(static fn($d) => (int) ($userMap[$d] ?? 0), $labels);
$labelShort = array_map(static fn($d) => date('M j', strtotime($d)), $labels);

$admin = db()->prepare('SELECT * FROM admins WHERE id = ?');
$admin->execute([(int) current_admin_id()]);
$adminRow = $admin->fetch();
$adminPage = 'dashboard';
require __DIR__ . '/_layout.php';
?>
<h1 class="h3 mb-4">Dashboard</h1>
<?php if (!empty($adminRow['must_change_password'])): ?>
<div class="alert alert-warning">DEMO ONLY credentials are in use. <a href="password.php">Change the admin password</a> as soon as possible.</div>
<?php endif; ?>
<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['Total Users', $stats['users'], 'fa-users'],
        ['Active Users', $stats['active'], 'fa-user-check'],
        ['Disabled Users', $stats['disabled'], 'fa-user-slash'],
        ['Total Messages', $stats['messages'], 'fa-message'],
        ['Total Groups', $stats['groups'], 'fa-users-rectangle'],
        ['Forum Posts', $stats['posts'], 'fa-newspaper'],
        ['Open Reports', $stats['reports'], 'fa-flag'],
    ];
    foreach ($cards as [$label, $val, $icon]): ?>
    <div class="col-6 col-md-4 col-xl-3">
        <div class="admin-stat">
            <i class="fa-solid <?= e($icon) ?>"></i>
            <div>
                <div class="stat-val"><?= (int) $val ?></div>
                <div class="stat-label"><?= e($label) ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="surface-card">
            <h2 class="h6 mb-3">Messages (last 7 days)</h2>
            <canvas id="msgChart" height="160"></canvas>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="surface-card">
            <h2 class="h6 mb-3">New registrations (last 7 days)</h2>
            <canvas id="userChart" height="160"></canvas>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const labels = <?= json_encode($labelShort) ?>;
const msgData = <?= json_encode($msgSeries) ?>;
const userData = <?= json_encode($userSeries) ?>;
const common = {
  type: 'bar',
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
  }
};
new Chart(document.getElementById('msgChart'), {
  ...common,
  data: { labels, datasets: [{ data: msgData, backgroundColor: 'rgba(13,115,119,0.65)', borderRadius: 8 }] }
});
new Chart(document.getElementById('userChart'), {
  ...common,
  data: { labels, datasets: [{ data: userData, backgroundColor: 'rgba(224,122,47,0.7)', borderRadius: 8 }] }
});
</script>
<?php require __DIR__ . '/_layout_end.php'; ?>
