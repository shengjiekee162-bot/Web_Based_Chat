<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
start_user_session();
logout_user();
start_user_session();
redirect('login.php');
