<?php
declare(strict_types=1);

function app_url(string $path = ''): string
{
    $baseUrl = (string) app_config('base_url', '');
    $path = ltrim($path, '/');

    if ($baseUrl === '') {
        return $path === '' ? '/' : '/' . $path;
    }

    return $path === '' ? $baseUrl . '/' : $baseUrl . '/' . $path;
}

function redirect(string $path): never
{
    header('Location: ' . app_url($path));
    exit;
}

function redirect_back(string $fallback = 'dashboard.php'): never
{
    $location = $_SERVER['HTTP_REFERER'] ?? app_url($fallback);
    header('Location: ' . $location);
    exit;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function consume_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return $flash;
}

function remember_input(array $input, array $keys): void
{
    $remembered = [];

    foreach ($keys as $key) {
        $remembered[$key] = $input[$key] ?? '';
    }

    $_SESSION['old'] = $remembered;
}

function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['old'][$key] ?? $default;
}

function clear_old_input(): void
{
    unset($_SESSION['old']);
}

function request_int(string $key): ?int
{
    if (!isset($_REQUEST[$key]) || $_REQUEST[$key] === '') {
        return null;
    }

    return filter_var($_REQUEST[$key], FILTER_VALIDATE_INT) ?: null;
}

function sql_placeholders(int $count): string
{
    return implode(', ', array_fill(0, $count, '?'));
}

function role_label(string $role): string
{
    return match ($role) {
        'superadmin' => 'Superadmin',
        'head' => 'Jefe de escuela',
        'teacher' => 'Docente',
        default => ucfirst($role),
    };
}

function attendance_label(string $status): string
{
    return match ($status) {
        'present' => 'Asistió',
        'late' => 'Tardanza',
        'justified' => 'Justificada',
        'absent' => 'Falta',
        default => ucfirst($status),
    };
}

function attendance_badge_class(string $status): string
{
    return match ($status) {
        'present' => 'badge-success',
        'late' => 'badge-warning',
        'justified' => 'badge-info',
        'absent' => 'badge-danger',
        default => 'badge-muted',
    };
}

function absence_rate(array $row): float
{
    $total = (int) ($row['total_classes'] ?? 0);
    $absences = (int) ($row['absences'] ?? 0);

    if ($total === 0) {
        return 0.0;
    }

    return round(($absences / $total) * 100, 1);
}

function absence_state(float $rate): string
{
    return $rate > 30 ? 'Desaprobado por inasistencia' : 'Regular';
}

function format_percentage(float $value): string
{
    return number_format($value, 1) . '%';
}

function active_class(bool $condition, string $class = 'is-active'): string
{
    return $condition ? $class : '';
}
