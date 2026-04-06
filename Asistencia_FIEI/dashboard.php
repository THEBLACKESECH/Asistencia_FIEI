<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

require_login();

$role = current_user()['role'];

if ($role === 'superadmin') {
    redirect('superadmin/dashboard.php');
}

if ($role === 'head') {
    redirect('head/dashboard.php');
}

redirect('teacher/dashboard.php');
