<?php
declare(strict_types=1);

function navigation_items(string $role): array
{
    return match ($role) {
        'superadmin' => [
            ['key' => 'dashboard', 'label' => 'Resumen', 'href' => 'superadmin/dashboard.php'],
            ['key' => 'schools', 'label' => 'Escuelas', 'href' => 'superadmin/schools.php'],
            ['key' => 'users', 'label' => 'Usuarios', 'href' => 'superadmin/users.php'],
            ['key' => 'students', 'label' => 'Alumnos', 'href' => 'superadmin/students.php'],
            ['key' => 'courses', 'label' => 'Cursos', 'href' => 'superadmin/courses.php'],
            ['key' => 'assignments', 'label' => 'Asignaciones', 'href' => 'superadmin/assignments.php'],
            ['key' => 'audit', 'label' => 'Auditoría', 'href' => 'superadmin/audit.php'],
        ],
        'head' => [
            ['key' => 'dashboard', 'label' => 'Resumen', 'href' => 'head/dashboard.php'],
            ['key' => 'reports', 'label' => 'Reportes', 'href' => 'head/reports.php'],
            ['key' => 'audit', 'label' => 'Auditoría', 'href' => 'head/audit.php'],
        ],
        'teacher' => [
            ['key' => 'dashboard', 'label' => 'Mi panel', 'href' => 'teacher/dashboard.php'],
            ['key' => 'attendance', 'label' => 'Asistencia', 'href' => 'teacher/attendance.php'],
        ],
        default => [],
    };
}

function render_header(string $title, string $activeKey, array $alerts = []): void
{
    $user = current_user();
    $flash = consume_flash();
    $navItems = navigation_items($user['role']);
    $pageTitle = $title . ' | ' . app_config('app_name');
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= e(app_url('assets/css/styles.css')) ?>">
</head>
<body>
<div class="shell" data-shell>
    <aside class="sidebar" data-sidebar>
        <a class="brand" href="<?= e(app_url('dashboard.php')) ?>">
            <span class="brand-mark">F</span>
            <div>
                <strong>Asistencia FIEI</strong>
                <small>Control académico</small>
            </div>
        </a>

        <div class="sidebar-user">
            <p class="eyebrow">Sesión activa</p>
            <h2><?= e($user['full_name']) ?></h2>
            <p><?= e(role_label($user['role'])) ?></p>
        </div>

        <nav class="nav">
            <?php foreach ($navItems as $item): ?>
                <a class="nav-link <?= e(active_class($activeKey === $item['key'])) ?>" href="<?= e(app_url($item['href'])) ?>">
                    <?= e($item['label']) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <a class="nav-link logout-link" href="<?= e(app_url('logout.php')) ?>">Cerrar sesión</a>
    </aside>

    <div class="shell-main">
        <header class="topbar">
            <button class="nav-toggle" type="button" data-nav-toggle aria-label="Abrir menú">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <div>
                <p class="eyebrow">Panel protegido</p>
                <h1><?= e($title) ?></h1>
            </div>
        </header>

        <main class="page">
            <?php if ($flash): ?>
                <div class="flash flash-<?= e($flash['type']) ?>" data-flash>
                    <span><?= e($flash['message']) ?></span>
                    <button type="button" class="flash-close" data-close-flash aria-label="Cerrar">×</button>
                </div>
            <?php endif; ?>

            <?php if ($alerts !== []): ?>
                <div class="modal-backdrop is-visible" data-alert-modal>
                    <div class="modal-card">
                        <div class="modal-head">
                            <div>
                                <p class="eyebrow">Alerta automática</p>
                                <h2>Estudiantes con riesgo por inasistencia</h2>
                            </div>
                            <button type="button" class="icon-button" data-close-modal aria-label="Cerrar">×</button>
                        </div>
                        <div class="modal-body">
                            <p>Los siguientes alumnos superaron el 30% de inasistencias registradas.</p>
                            <ul class="alert-list">
                                <?php foreach ($alerts as $alert): ?>
                                    <li>
                                        <strong><?= e($alert['student_name']) ?></strong>
                                        <span><?= e($alert['course_code'] . ' - ' . $alert['course_name']) ?></span>
                                        <span class="badge badge-danger"><?= e(format_percentage((float) $alert['absence_rate'])) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
    <?php
}

function render_footer(): void
{
    ?>
        </main>
    </div>
</div>
<script src="<?= e(app_url('assets/js/app.js')) ?>"></script>
</body>
</html>
    <?php
}
