<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$pdo = database();
$schemaReady = true;
$setupAllowed = false;
$error = null;

try {
    $pdo->query('SELECT 1 FROM users LIMIT 1');
    $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $setupAllowed = $count === 0;
} catch (Throwable $exception) {
    $schemaReady = false;
    $error = 'Primero debes importar el archivo db/schema.sql en tu base de datos.';
}

if (!$schemaReady) {
    http_response_code(500);
}

if (is_post() && $schemaReady) {
    verify_csrf_or_abort('setup.php');

    if (!$setupAllowed) {
        set_flash('error', 'El sistema ya tiene usuarios registrados.');
        redirect('index.php');
    }

    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

    try {
        if ($fullName === '' || $username === '' || $password === '') {
            throw new RuntimeException('Completa los campos obligatorios.');
        }

        if ($password !== $passwordConfirmation) {
            throw new RuntimeException('Las contraseñas no coinciden.');
        }

        $schoolsCount = (int) $pdo->query('SELECT COUNT(*) FROM schools')->fetchColumn();
        if ($schoolsCount === 0) {
            $pdo->exec(
                "INSERT INTO schools (code, name, is_active) VALUES
                ('140', 'Escuela de Electrónica', 1),
                ('141', 'Escuela de Informática', 1),
                ('142', 'Escuela de Mecatrónica', 1),
                ('143', 'Escuela de Telecomunicaciones', 1)"
            );
        }

        $stmt = $pdo->prepare(
            'INSERT INTO users (full_name, username, email, role, password_hash, is_enabled)
             VALUES (?, ?, ?, "superadmin", ?, 1)'
        );
        $stmt->execute([$fullName, $username, $email, password_hash($password, PASSWORD_DEFAULT)]);
        $newId = (int) $pdo->lastInsertId();

        log_audit($pdo, null, 'SETUP', 'users', $newId, 'Creación inicial del superadmin');

        set_flash('success', 'Superadmin inicial creado correctamente. Ahora puedes iniciar sesión.');
        redirect('index.php');
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración inicial | <?= e(app_config('app_name')) ?></title>
    <link rel="stylesheet" href="<?= e(app_url('assets/css/styles.css')) ?>">
</head>
<body>
<div class="login-shell">
    <section class="login-copy">
        <div>
            <p class="eyebrow">Configuración inicial</p>
            <h1>Primer superadmin del sistema.</h1>
            <p>Esta pantalla se usa una sola vez para crear el acceso inicial de administración luego de importar la base de datos.</p>
        </div>
        <div class="feature-list">
            <article class="feature-item">
                <strong>Paso previo</strong>
                <span>Importa el archivo `db/schema.sql` en MySQL o MariaDB.</span>
            </article>
            <article class="feature-item">
                <strong>Después</strong>
                <span>Ingresa con el superadmin y gestiona usuarios, cursos, alumnos y asignaciones.</span>
            </article>
        </div>
    </section>

    <section class="login-panel">
        <p class="eyebrow">Alta inicial</p>
        <h2 class="page-title">Crear superadmin</h2>

        <?php if ($error !== null): ?>
            <div class="flash flash-error">
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!$schemaReady): ?>
            <div class="card">
                <p class="muted">No se detectó la estructura de tablas necesaria para continuar.</p>
            </div>
        <?php elseif (!$setupAllowed): ?>
            <div class="card">
                <p class="muted">La configuración inicial ya fue realizada. Continúa desde el login principal.</p>
                <p><a class="btn-secondary" href="<?= e(app_url('index.php')) ?>">Ir al login</a></p>
            </div>
        <?php else: ?>
            <form method="post" class="form-grid">
                <?= csrf_field() ?>
                <div class="field field-full">
                    <label for="full_name">Nombre completo</label>
                    <input id="full_name" name="full_name" required>
                </div>
                <div class="field">
                    <label for="username">Usuario</label>
                    <input id="username" name="username" required>
                </div>
                <div class="field">
                    <label for="email">Correo</label>
                    <input id="email" name="email" type="email">
                </div>
                <div class="field">
                    <label for="password">Contraseña</label>
                    <input id="password" name="password" type="password" required>
                </div>
                <div class="field">
                    <label for="password_confirmation">Confirmar contraseña</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required>
                </div>
                <div class="field field-full">
                    <button class="btn" type="submit">Crear superadmin</button>
                </div>
            </form>
        <?php endif; ?>
    </section>
</div>
</body>
</html>
