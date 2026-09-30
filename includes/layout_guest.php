<?php
/**
 * Guest layout (login/register)
 */
$pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="<?= e(asset('css/app.css')) ?>" rel="stylesheet">
</head>
<body class="auth-body">
<div class="auth-wrap">
    <div class="auth-panel">
        <div class="auth-brand">
            <span class="brand-mark"><i class="fa-solid fa-comments"></i></span>
            <h1><?= e(APP_NAME) ?></h1>
            <p>Web-based chat, voice &amp; video</p>
        </div>
        <div class="auth-card">
            <?= $authContent ?? '' ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
