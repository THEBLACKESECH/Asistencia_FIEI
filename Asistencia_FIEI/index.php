<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (current_user() !== null) {
    redirect('dashboard.php');
}

$error = null;
$flash = consume_flash();

if (is_post()) {
    verify_csrf_or_abort('index.php');

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    remember_input($_POST, ['username']);

    $user = find_user_by_username(database(), $username);

    if (
        $user !== null &&
        (int) $user['is_enabled'] === 1 &&
        password_verify($password, $user['password_hash'])
    ) {
        clear_old_input();
        login_user($user);
        touch_last_login(database(), (int) $user['id']);
        log_audit(
            database(),
            (int) $user['id'],
            'LOGIN',
            'users',
            (int) $user['id'],
            'Ingreso correcto al sistema'
        );

        redirect('dashboard.php');
    }

    $error = 'Credenciales inválidas o usuario inhabilitado.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(app_config('app_name')) ?></title>
    <link rel="stylesheet" href="<?= e(app_url('assets/css/styles.css')) ?>">
</head>
<body>
<div class="login-shell">
    <section class="login-copy">
        <div>
            <p class="eyebrow">Facultad de Ingeniería Electrónica e Informática</p>
            <h1>Control de asistencia con acceso seguro por rol.</h1>
            <p>
                Plataforma pensada para docentes, jefes de escuela y superadministración.
                Cada usuario accede únicamente a la interfaz que le corresponde, con sesiones seguras,
                validación por backend y trazabilidad de cambios mediante auditoría.
            </p>
        </div>

        <div class="feature-list">
            <article class="feature-item">
                <strong>Docente</strong>
                <span>Registra asistencia diaria de sus cursos asignados y no puede modificarla después.</span>
            </article>
            <article class="feature-item">
                <strong>Jefe de escuela</strong>
                <span>Consulta reportes por su escuela, revisa alertas y monitorea alumnos en riesgo.</span>
            </article>
            <article class="feature-item">
                <strong>Superadmin</strong>
                <span>Administra usuarios, cursos, alumnos, escuelas, asignaciones y observa la auditoría global.</span>
            </article>
        </div>
    </section>

    <section class="login-panel">
        <p class="eyebrow">Inicio de sesión</p>
        <h2 class="page-title">Bienvenido</h2>
        <p class="muted">Ingresa con tu usuario institucional para acceder al módulo correspondiente.</p>

        <?php if ($error !== null): ?>
            <div class="flash flash-error">
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($flash !== null): ?>
            <div class="flash flash-<?= e($flash['type']) ?>">
                <span><?= e($flash['message']) ?></span>
            </div>
        <?php endif; ?>

        <form method="post" class="form-grid">
            <?= csrf_field() ?>
            <div class="field field-full">
                <label for="username">Usuario</label>
                <input id="username" name="username" type="text" maxlength="60" required value="<?= e(old('username')) ?>">
            </div>
            <div class="field field-full">
                <label for="password">Contraseña</label>
                <input id="password" name="password" type="password" required>
            </div>
            <div class="field field-full">
                <button class="btn" type="submit">Ingresar al sistema</button>
            </div>
        </form>

        <div class="card" style="margin-top: 1rem;">
            <p class="eyebrow">Seguridad aplicada</p>
            <div class="inline-list">
                <span class="badge badge-info">Sesiones seguras</span>
                <span class="badge badge-info">CSRF</span>
                <span class="badge badge-info">Hash de contraseñas</span>
                <span class="badge badge-info">Control por rol</span>
            </div>
            <p class="muted" style="margin-top: 1rem;">
                Primera instalación: crea el superadmin inicial desde
                <a href="<?= e(app_url('setup.php')) ?>"><strong>setup.php</strong></a>
                después de importar la base de datos.
            </p>
        </div>
    </section>
</div>
<script src="<?= e(app_url('assets/js/app.js')) ?>"></script>
</body>
</html>
