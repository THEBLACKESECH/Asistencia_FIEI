<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (current_user() !== null) {
    log_audit(
        database(),
        (int) current_user()['id'],
        'LOGOUT',
        'users',
        (int) current_user()['id'],
        'Cierre de sesión'
    );
}

logout_user();
session_start();
set_flash('success', 'La sesión se cerró correctamente.');
redirect('index.php');
