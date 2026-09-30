<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
start_admin_session();
logout_admin();
start_admin_session();
redirect('admin/login.php');
