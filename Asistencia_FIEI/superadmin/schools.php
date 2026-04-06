<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_role('superadmin');

$pdo = database();
$editId = request_int('edit');

if (is_post()) {
    verify_csrf_or_abort('superadmin/schools.php');

    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'save_school') {
            $schoolId = (int) ($_POST['school_id'] ?? 0);
            $code = trim((string) ($_POST['code'] ?? ''));
            $name = trim((string) ($_POST['name'] ?? ''));
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($code === '' || $name === '') {
                throw new RuntimeException('El código y el nombre de la escuela son obligatorios.');
            }

            if ($schoolId > 0) {
                $stmt = $pdo->prepare('UPDATE schools SET code = ?, name = ?, is_active = ? WHERE id = ?');
                $stmt->execute([$code, $name, $isActive, $schoolId]);
                log_audit($pdo, (int) current_user()['id'], 'UPDATE', 'schools', $schoolId, 'Actualización de escuela ' . $code, $schoolId);
                set_flash('success', 'Escuela actualizada correctamente.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO schools (code, name, is_active) VALUES (?, ?, ?)');
                $stmt->execute([$code, $name, $isActive]);
                $newId = (int) $pdo->lastInsertId();
                log_audit($pdo, (int) current_user()['id'], 'CREATE', 'schools', $newId, 'Creación de escuela ' . $code, $newId);
                set_flash('success', 'Escuela creada correctamente.');
            }
        }

        if ($action === 'toggle_school') {
            $schoolId = (int) ($_POST['school_id'] ?? 0);
            $stmt = $pdo->prepare('UPDATE schools SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?');
            $stmt->execute([$schoolId]);
            log_audit($pdo, (int) current_user()['id'], 'STATUS', 'schools', $schoolId, 'Cambio de estado de escuela', $schoolId);
            set_flash('success', 'Estado de la escuela actualizado.');
        }
    } catch (Throwable $exception) {
        set_flash('error', 'No se pudo guardar la escuela. ' . $exception->getMessage());
    }

    redirect('superadmin/schools.php');
}

$editingSchool = null;
if ($editId !== null) {
    $stmt = $pdo->prepare('SELECT * FROM schools WHERE id = ?');
    $stmt->execute([$editId]);
    $editingSchool = $stmt->fetch() ?: null;
}

$schools = get_all_schools($pdo);

render_header('Gestión de escuelas', 'schools');
?>
<section class="two-column">
    <article class="form-card">
        <p class="eyebrow"><?= $editingSchool ? 'Editar escuela' : 'Nueva escuela' ?></p>
        <h3><?= $editingSchool ? 'Actualizar escuela' : 'Registrar escuela' ?></h3>
        <form method="post" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_school">
            <input type="hidden" name="school_id" value="<?= e((string) ($editingSchool['id'] ?? 0)) ?>">
            <div class="field">
                <label for="code">Código</label>
                <input id="code" name="code" maxlength="10" required value="<?= e($editingSchool['code'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="name">Nombre</label>
                <input id="name" name="name" maxlength="120" required value="<?= e($editingSchool['name'] ?? '') ?>">
            </div>
            <div class="field field-full">
                <label><input type="checkbox" name="is_active" <?= !isset($editingSchool['is_active']) || (int) $editingSchool['is_active'] === 1 ? 'checked' : '' ?>> Escuela habilitada</label>
            </div>
            <div class="field field-full button-row">
                <button class="btn" type="submit"><?= $editingSchool ? 'Actualizar' : 'Guardar' ?></button>
                <a class="btn-ghost" href="<?= e(app_url('superadmin/schools.php')) ?>">Limpiar</a>
            </div>
        </form>
    </article>

    <article class="card">
        <p class="eyebrow">Escuelas oficiales</p>
        <h3>Facultad FIEI</h3>
        <p class="muted">La configuración inicial contempla Mecatrónica (142), Informática (141), Electrónica (140) y Telecomunicaciones (143).</p>
    </article>
</section>

<section class="table-card">
    <p class="eyebrow">Listado</p>
    <h3>Escuelas registradas</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($schools as $school): ?>
                    <tr>
                        <td><?= e($school['code']) ?></td>
                        <td><?= e($school['name']) ?></td>
                        <td><span class="badge <?= (int) $school['is_active'] === 1 ? 'badge-success' : 'badge-muted' ?>"><?= (int) $school['is_active'] === 1 ? 'Habilitada' : 'Inhabilitada' ?></span></td>
                        <td>
                            <div class="button-row">
                                <a class="btn-secondary" href="<?= e(app_url('superadmin/schools.php?edit=' . $school['id'])) ?>">Editar</a>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_school">
                                    <input type="hidden" name="school_id" value="<?= e((string) $school['id']) ?>">
                                    <button class="btn-ghost" type="submit"><?= (int) $school['is_active'] === 1 ? 'Inhabilitar' : 'Habilitar' ?></button>
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
