<?php
declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf_or_abort(string $fallback = 'dashboard.php'): void
{
    $token = (string) ($_POST['_token'] ?? '');

    if (!hash_equals(csrf_token(), $token)) {
        set_flash('error', 'La sesión del formulario expiró. Inténtalo nuevamente.');
        redirect_back($fallback);
    }
}
