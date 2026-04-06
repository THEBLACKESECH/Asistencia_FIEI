<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_role('superadmin');

$pdo = database();
$editId = request_int('edit');

if (is_post()) {
    verify_csrf_or_abort('superadmin/users.php');

    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'save_user') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            $fullName = trim((string) ($_POST['full_name'] ?? ''));
            $username = trim((string) ($_POST['username'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $role = (string) ($_POST['role'] ?? 'teacher');
            $password = (string) ($_POST['password'] ?? '');
            $isEnabled = isset($_POST['is_enabled']) ? 1 : 0;

            if ($fullName === '' || $username === '' || !in_array($role, ['superadmin', 'head', 'teacher'], true)) {
                throw new RuntimeException('Completa los campos obligatorios del usuario.');
            }

            if ($userId > 0) {
                if ($password !== '') {
                    $stmt = $pdo->prepare(
                        'UPDATE users SET full_name = ?, username = ?, email = ?, role = ?, password_hash = ?, is_enabled = ? WHERE id = ?'
                    );
                    $stmt->execute([$fullName, $username, $email, $role, password_hash($password, PASSWORD_DEFAULT), $isEnabled, $userId]);
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE users SET full_name = ?, username = ?, email = ?, role = ?, is_enabled = ? WHERE id = ?'
                    );
                    $stmt->execute([$fullName, $username, $email, $role, $isEnabled, $userId]);
                }

                log_audit($pdo, (int) current_user()['id'], 'UPDATE', 'users', $userId, 'Actualización del usuario ' . $username);
                set_flash('success', 'Usuario actualizado correctamente.');
            } else {
                if ($password === '') {
                    throw new RuntimeException('La contraseña es obligatoria al crear un usuario.');
                }

                $stmt = $pdo->prepare(
                    'INSERT INTO users (full_name, username, email, role, password_hash, is_enabled) VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$fullName, $username, $email, $role, password_hash($password, PASSWORD_DEFAULT), $isEnabled]);
                $newId = (int) $pdo->lastInsertId();

                log_audit($pdo, (int) current_user()['id'], 'CREATE', 'users', $newId, 'Creación del usuario ' . $username);
                set_flash('success', 'Usuario creado correctamente.');
            }
        }

        if ($action === 'toggle_user') {
            $userId = (int) ($_POST['user_id'] ?? 0);

            if ($userId === (int) current_user()['id']) {
                throw new RuntimeException('No puedes inhabilitar tu propio usuario mientras estás conectado.');
            }

            $stmt = $pdo->prepare('UPDATE users SET is_enabled = IF(is_enabled = 1, 0, 1) WHERE id = ?');
            $stmt->execute([$userId]);
            log_audit($pdo, (int) current_user()['id'], 'STATUS', 'users', $userId, 'Cambio de estado de usuario');
            set_flash('success', 'Estado del usuario actualizado.');
        }
    } catch (Throwable $exception) {
        set_flash('error', 'No se pudo guardar el usuario. ' . $exception->getMessage());
    }

    redirect('superadmin/users.php');
}

$editingUser = null;
if ($editId !== null) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$editId]);
    $editingUser = $stmt->fetch() ?: null;
}

$users = get_users($pdo);

render_header('Gestión de usuarios', 'users');
?>
<section class="two-column">
    <article class="form-card">
        <p class="eyebrow"><?= $editingUser ? 'Editar usuario' : 'Nuevo usuario' ?></p>
        <h3><?= $editingUser ? 'Actualizar datos de acceso' : 'Registrar usuario' ?></h3>
        <form method="post" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_user">
            <input type="hidden" name="user_id" value="<?= e((string) ($editingUser['id'] ?? 0)) ?>">
            <div class="field">
                <label for="full_name">Nombre completo</label>
                <input id="full_name" name="full_name" required value="<?= e($editingUser['full_name'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="username">Usuario</label>
                <input id="username" name="username" required value="<?= e($editingUser['username'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="email">Correo</label>
                <input id="email" name="email" type="email" value="<?= e($editingUser['email'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="role">Rol</label>
                <select id="role" name="role">
                    <?php foreach (['superadmin' => 'Superadmin', 'head' => 'Jefe de escuela', 'teacher' => 'Docente'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= ($editingUser['role'] ?? 'teacher') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field field-full">
                <label for="password">Contraseña <?= $editingUser ? '(dejar en blanco para conservar la actual)' : '' ?></label>
                <input id="password" name="password" type="password" <?= $editingUser ? '' : 'required' ?>>
            </div>
            <div class="field field-full">
                <label><input type="checkbox" name="is_enabled" <?= !isset($editingUser['is_enabled']) || (int) $editingUser['is_enabled'] === 1 ? 'checked' : '' ?>> Usuario habilitado</label>
            </div>
            <div class="field field-full button-row">
                <button class="btn" type="submit"><?= $editingUser ? 'Actualizar' : 'Guardar' ?></button>
                <a class="btn-ghost" href="<?= e(app_url('superadmin/users.php')) ?>">Limpiar</a>
            </div>
        </form>
    </article>

    <article class="card">
        <p class="eyebrow">Notas de gestión</p>
        <h3>Roles y asignaciones</h3>
        <p class="muted">La relación de jefes con escuelas y docentes con escuelas o cursos se gestiona en el módulo de asignaciones.</p>
    </article>
</section>

<section class="table-card">
    <p class="eyebrow">Listado</p>
    <h3>Usuarios del sistema</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Usuario</th>
                    <th>Rol</th>
                    <th>Escuelas</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $userRow): ?>
                    <tr>
                        <td><?= e($userRow['full_name']) ?></td>
                        <td><?= e($userRow['username']) ?></td>
                        <td><span class="badge badge-info"><?= e(role_label($userRow['role'])) ?></span></td>
                        <td><?= e($userRow['head_schools'] ?: $userRow['teacher_schools'] ?: 'Sin asignación') ?></td>
                        <td><span class="badge <?= (int) $userRow['is_enabled'] === 1 ? 'badge-success' : 'badge-muted' ?>"><?= (int) $userRow['is_enabled'] === 1 ? 'Habilitado' : 'Inhabilitado' ?></span></td>
                        <td>
                            <div class="button-row">
                                <a class="btn-secondary" href="<?= e(app_url('superadmin/users.php?edit=' . $userRow['id'])) ?>">Editar</a>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_user">
                                    <input type="hidden" name="user_id" value="<?= e((string) $userRow['id']) ?>">
                                    <button class="btn-ghost" type="submit"><?= (int) $userRow['is_enabled'] === 1 ? 'Inhabilitar' : 'Habilitar' ?></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php render_footer(); ?>
