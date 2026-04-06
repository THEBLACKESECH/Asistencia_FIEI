<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_role('superadmin');

$pdo = database();
$editId = request_int('edit');

if (is_post()) {
    verify_csrf_or_abort('superadmin/courses.php');

    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'save_course') {
            $courseId = (int) ($_POST['course_id'] ?? 0);
            $schoolId = (int) ($_POST['school_id'] ?? 0);
            $code = trim((string) ($_POST['code'] ?? ''));
            $name = trim((string) ($_POST['name'] ?? ''));
            $section = trim((string) ($_POST['section'] ?? ''));
            $cycle = trim((string) ($_POST['cycle'] ?? ''));
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($schoolId <= 0 || $code === '' || $name === '') {
                throw new RuntimeException('Completa la escuela, código y nombre del curso.');
            }

            if ($courseId > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE courses SET school_id = ?, code = ?, name = ?, section = ?, cycle = ?, is_active = ? WHERE id = ?'
                );
                $stmt->execute([$schoolId, $code, $name, $section, $cycle, $isActive, $courseId]);
                log_audit($pdo, (int) current_user()['id'], 'UPDATE', 'courses', $courseId, 'Actualización del curso ' . $code, $schoolId);
                set_flash('success', 'Curso actualizado correctamente.');
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO courses (school_id, code, name, section, cycle, is_active) VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$schoolId, $code, $name, $section, $cycle, $isActive]);
                $newId = (int) $pdo->lastInsertId();
                log_audit($pdo, (int) current_user()['id'], 'CREATE', 'courses', $newId, 'Creación del curso ' . $code, $schoolId);
                set_flash('success', 'Curso registrado correctamente.');
            }
        }

        if ($action === 'toggle_course') {
            $courseId = (int) ($_POST['course_id'] ?? 0);
            $schoolId = (int) ($_POST['school_id'] ?? 0);
            $stmt = $pdo->prepare('UPDATE courses SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?');
            $stmt->execute([$courseId]);
            log_audit($pdo, (int) current_user()['id'], 'STATUS', 'courses', $courseId, 'Cambio de estado de curso', $schoolId);
            set_flash('success', 'Estado del curso actualizado.');
        }
    } catch (Throwable $exception) {
        set_flash('error', 'No se pudo guardar el curso. ' . $exception->getMessage());
    }

    redirect('superadmin/courses.php');
}

$editingCourse = null;
if ($editId !== null) {
    $stmt = $pdo->prepare('SELECT * FROM courses WHERE id = ?');
    $stmt->execute([$editId]);
    $editingCourse = $stmt->fetch() ?: null;
}

$schools = get_all_schools($pdo, false);
$courses = get_courses($pdo);

render_header('Gestión de cursos', 'courses');
?>
<section class="two-column">
    <article class="form-card">
        <p class="eyebrow"><?= $editingCourse ? 'Editar curso' : 'Nuevo curso' ?></p>
        <h3><?= $editingCourse ? 'Actualizar curso' : 'Registrar curso' ?></h3>
        <form method="post" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_course">
            <input type="hidden" name="course_id" value="<?= e((string) ($editingCourse['id'] ?? 0)) ?>">
            <div class="field">
                <label for="school_id">Escuela</label>
                <select id="school_id" name="school_id" required>
                    <option value="">Selecciona una escuela</option>
                    <?php foreach ($schools as $school): ?>
                        <option value="<?= e((string) $school['id']) ?>" <?= (int) ($editingCourse['school_id'] ?? 0) === (int) $school['id'] ? 'selected' : '' ?>>
                            <?= e($school['code'] . ' - ' . $school['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="code">Código del curso</label>
                <input id="code" name="code" required value="<?= e($editingCourse['code'] ?? '') ?>">
            </div>
            <div class="field field-full">
                <label for="name">Nombre del curso</label>
                <input id="name" name="name" required value="<?= e($editingCourse['name'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="section">Sección</label>
                <input id="section" name="section" value="<?= e($editingCourse['section'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="cycle">Ciclo</label>
                <input id="cycle" name="cycle" value="<?= e($editingCourse['cycle'] ?? '') ?>">
            </div>
            <div class="field field-full">
                <label><input type="checkbox" name="is_active" <?= !isset($editingCourse['is_active']) || (int) $editingCourse['is_active'] === 1 ? 'checked' : '' ?>> Curso habilitado</label>
            </div>
            <div class="field field-full button-row">
                <button class="btn" type="submit"><?= $editingCourse ? 'Actualizar' : 'Guardar' ?></button>
                <a class="btn-ghost" href="<?= e(app_url('superadmin/courses.php')) ?>">Limpiar</a>
            </div>
        </form>
    </article>

    <article class="card">
        <p class="eyebrow">Vinculación</p>
        <h3>Oferta académica</h3>
        <p class="muted">Después de crear un curso, asigna el docente responsable y matricula estudiantes desde el módulo de asignaciones.</p>
    </article>
</section>

<section class="table-card">
    <p class="eyebrow">Listado</p>
    <h3>Cursos registrados</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Escuela</th>
                    <th>Curso</th>
                    <th>Sección</th>
                    <th>Alumnos</th>
                    <th>Sesiones</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($courses as $course): ?>
                    <tr>
                        <td><?= e($course['school_code'] . ' - ' . $course['school_name']) ?></td>
                        <td><?= e($course['code'] . ' - ' . $course['name']) ?></td>
                        <td><?= e($course['section'] ?: 'Única') ?></td>
                        <td><?= e((string) $course['total_students']) ?></td>
                        <td><?= e((string) $course['total_sessions']) ?></td>
                        <td><span class="badge <?= (int) $course['is_active'] === 1 ? 'badge-success' : 'badge-muted' ?>"><?= (int) $course['is_active'] === 1 ? 'Activo' : 'Inactivo' ?></span></td>
                        <td>
                            <div class="button-row">
                                <a class="btn-secondary" href="<?= e(app_url('superadmin/courses.php?edit=' . $course['id'])) ?>">Editar</a>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_course">
                                    <input type="hidden" name="course_id" value="<?= e((string) $course['id']) ?>">
                                    <input type="hidden" name="school_id" value="<?= e((string) $course['school_id']) ?>">
                                    <button class="btn-ghost" type="submit"><?= (int) $course['is_active'] === 1 ? 'Inhabilitar' : 'Habilitar' ?></button>
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
