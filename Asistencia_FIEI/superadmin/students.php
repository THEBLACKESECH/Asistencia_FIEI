<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_role('superadmin');

$pdo = database();
$editId = request_int('edit');

if (is_post()) {
    verify_csrf_or_abort('superadmin/students.php');

    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'save_student') {
            $studentId = (int) ($_POST['student_id'] ?? 0);
            $studentCode = trim((string) ($_POST['student_code'] ?? ''));
            $fullName = trim((string) ($_POST['full_name'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $semester = trim((string) ($_POST['semester'] ?? ''));
            $schoolId = (int) ($_POST['school_id'] ?? 0);
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($studentCode === '' || $fullName === '' || $schoolId <= 0) {
                throw new RuntimeException('Completa el código, nombre y escuela del alumno.');
            }

            if ($studentId > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE students SET student_code = ?, full_name = ?, email = ?, semester = ?, school_id = ?, is_active = ? WHERE id = ?'
                );
                $stmt->execute([$studentCode, $fullName, $email, $semester, $schoolId, $isActive, $studentId]);
                log_audit($pdo, (int) current_user()['id'], 'UPDATE', 'students', $studentId, 'Actualización del alumno ' . $studentCode, $schoolId);
                set_flash('success', 'Alumno actualizado correctamente.');
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO students (student_code, full_name, email, semester, school_id, is_active) VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$studentCode, $fullName, $email, $semester, $schoolId, $isActive]);
                $newId = (int) $pdo->lastInsertId();
                log_audit($pdo, (int) current_user()['id'], 'CREATE', 'students', $newId, 'Creación del alumno ' . $studentCode, $schoolId);
                set_flash('success', 'Alumno registrado correctamente.');
            }
        }

        if ($action === 'toggle_student') {
            $studentId = (int) ($_POST['student_id'] ?? 0);
            $schoolId = (int) ($_POST['school_id'] ?? 0);
            $stmt = $pdo->prepare('UPDATE students SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?');
            $stmt->execute([$studentId]);
            log_audit($pdo, (int) current_user()['id'], 'STATUS', 'students', $studentId, 'Cambio de estado de alumno', $schoolId);
            set_flash('success', 'Estado del alumno actualizado.');
        }
    } catch (Throwable $exception) {
        set_flash('error', 'No se pudo guardar el alumno. ' . $exception->getMessage());
    }

    redirect('superadmin/students.php');
}

$editingStudent = null;
if ($editId !== null) {
    $stmt = $pdo->prepare('SELECT * FROM students WHERE id = ?');
    $stmt->execute([$editId]);
    $editingStudent = $stmt->fetch() ?: null;
}

$schools = get_all_schools($pdo, false);
$students = get_students($pdo);

render_header('Gestión de alumnos', 'students');
?>
<section class="two-column">
    <article class="form-card">
        <p class="eyebrow"><?= $editingStudent ? 'Editar alumno' : 'Nuevo alumno' ?></p>
        <h3><?= $editingStudent ? 'Actualizar ficha del alumno' : 'Registrar alumno' ?></h3>
        <form method="post" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_student">
            <input type="hidden" name="student_id" value="<?= e((string) ($editingStudent['id'] ?? 0)) ?>">
            <div class="field">
                <label for="student_code">Código</label>
                <input id="student_code" name="student_code" required value="<?= e($editingStudent['student_code'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="full_name">Nombre completo</label>
                <input id="full_name" name="full_name" required value="<?= e($editingStudent['full_name'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="email">Correo</label>
                <input id="email" name="email" type="email" value="<?= e($editingStudent['email'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="semester">Semestre</label>
                <input id="semester" name="semester" value="<?= e($editingStudent['semester'] ?? '') ?>">
            </div>
            <div class="field field-full">
                <label for="school_id">Escuela</label>
                <select id="school_id" name="school_id" required>
                    <option value="">Selecciona una escuela</option>
                    <?php foreach ($schools as $school): ?>
                        <option value="<?= e((string) $school['id']) ?>" <?= (int) ($editingStudent['school_id'] ?? 0) === (int) $school['id'] ? 'selected' : '' ?>>
                            <?= e($school['code'] . ' - ' . $school['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field field-full">
                <label><input type="checkbox" name="is_active" <?= !isset($editingStudent['is_active']) || (int) $editingStudent['is_active'] === 1 ? 'checked' : '' ?>> Alumno activo</label>
            </div>
            <div class="field field-full button-row">
                <button class="btn" type="submit"><?= $editingStudent ? 'Actualizar' : 'Guardar' ?></button>
                <a class="btn-ghost" href="<?= e(app_url('superadmin/students.php')) ?>">Limpiar</a>
            </div>
        </form>
    </article>

    <article class="card">
        <p class="eyebrow">Importante</p>
        <h3>Matrícula y reportes</h3>
        <p class="muted">Los alumnos deben estar vinculados a su escuela y luego matriculados en cursos desde el módulo de asignaciones.</p>
    </article>
</section>

<section class="table-card">
    <p class="eyebrow">Listado</p>
    <h3>Alumnos registrados</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Alumno</th>
                    <th>Escuela</th>
                    <th>Semestre</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $student): ?>
                    <tr>
                        <td><?= e($student['student_code']) ?></td>
                        <td><?= e($student['full_name']) ?></td>
                        <td><?= e($student['school_code'] . ' - ' . $student['school_name']) ?></td>
                        <td><?= e($student['semester'] ?: '-') ?></td>
                        <td><span class="badge <?= (int) $student['is_active'] === 1 ? 'badge-success' : 'badge-muted' ?>"><?= (int) $student['is_active'] === 1 ? 'Activo' : 'Inactivo' ?></span></td>
                        <td>
                            <div class="button-row">
                                <a class="btn-secondary" href="<?= e(app_url('superadmin/students.php?edit=' . $student['id'])) ?>">Editar</a>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_student">
                                    <input type="hidden" name="student_id" value="<?= e((string) $student['id']) ?>">
                                    <input type="hidden" name="school_id" value="<?= e((string) $student['school_id']) ?>">
                                    <button class="btn-ghost" type="submit"><?= (int) $student['is_active'] === 1 ? 'Inhabilitar' : 'Habilitar' ?></button>
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
