<?php
declare(strict_types=1);

function current_user(): ?array
{
    return $_SESSION['auth_user'] ?? null;
}

function is_role(string $role): bool
{
    $user = current_user();

    return $user !== null && $user['role'] === $role;
}

function login_user(array $user): void
{
    session_regenerate_id(true);

    $_SESSION['auth_user'] = [
        'id' => (int) $user['id'],
        'username' => $user['username'],
        'full_name' => $user['full_name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'is_enabled' => (int) $user['is_enabled'],
    ];
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

function require_login(): void
{
    if (current_user() === null) {
        set_flash('error', 'Debes iniciar sesión para continuar.');
        redirect('index.php');
    }
}

function require_role(string|array $roles): void
{
    require_login();

    $roles = (array) $roles;
    $user = current_user();

    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        set_flash('error', 'No tienes permisos para acceder a esta sección.');
        redirect('dashboard.php');
    }
}
