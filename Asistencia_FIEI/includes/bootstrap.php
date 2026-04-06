<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

if (session_status() === PHP_SESSION_NONE) {
    session_name((string) app_config('security.session_name'));
    session_set_cookie_params([
        'lifetime' => (int) app_config('security.cookie_lifetime', 28800),
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/data.php';

if (current_user() !== null) {
    $freshUser = find_user_by_id(database(), (int) current_user()['id']);

    if ($freshUser === null || !(int) $freshUser['is_enabled']) {
        logout_user();
        session_start();
        set_flash('error', 'Tu usuario fue inhabilitado o dejó de estar disponible.');
        redirect('index.php');
    }

    $_SESSION['auth_user'] = [
        'id' => (int) $freshUser['id'],
        'username' => $freshUser['username'],
        'full_name' => $freshUser['full_name'],
        'email' => $freshUser['email'],
        'role' => $freshUser['role'],
        'is_enabled' => (int) $freshUser['is_enabled'],
    ];
}
