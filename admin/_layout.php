<?php
declare(strict_types=1);
$adminPage = $adminPage ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="<?= e(asset('css/app.css')) ?>" rel="stylesheet">
</head>
<body class="admin-body">
<div class="admin-shell">
    <aside class="admin-side">
        <div class="brand-block"><strong><?= e(APP_NAME) ?></strong><span>Admin</span></div>
        <nav>
            <a class="<?= $adminPage === 'dashboard' ? 'active' : '' ?>" href="index.php"><i class="fa-solid fa-gauge"></i> Dashboard</a>
            <a class="<?= $adminPage === 'users' ? 'active' : '' ?>" href="users.php"><i class="fa-solid fa-users"></i> Users</a>
            <a class="<?= $adminPage === 'forums' ? 'active' : '' ?>" href="forums.php"><i class="fa-solid fa-comments"></i> Forums</a>
            <a class="<?= $adminPage === 'reports' ? 'active' : '' ?>" href="reports.php"><i class="fa-solid fa-flag"></i> Reports</a>
            <a class="<?= $adminPage === 'password' ? 'active' : '' ?>" href="password.php"><i class="fa-solid fa-key"></i> Password</a>
            <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </nav>
    </aside>
    <main class="admin-main">
