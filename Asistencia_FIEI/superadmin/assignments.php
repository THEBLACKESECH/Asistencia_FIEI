<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_role('superadmin');

$pdo = database();

if (is_post()) {
    verify_csrf_or_abort('superadmin/assignments.php');

    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'assign_head_school') {
            $headUserId = (int) ($_POST['head_user_id'] ?? 0);
            $schoolId = (int) ($_POST['school_id'] ?? 0);

            if ($headUserId <= 0 || $schoolId <= 0) {
                throw new RuntimeException('Selecciona un jefe y una escuela.');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO head_school_assignments (head_user_id, school_id, is_active)
                 VALUES (?, ?, 1)
                 ON DUPLICATE KEY UPDATE is_active = 1, assigned_at = CURRENT_TIMESTAMP'
            );
            $stmt->execute([$headUserId, $schoolId]);

            log_audit($pdo, (int) current_user()['id'], 'ASSIGN', 'head_school_assignments', null, 'Asignación de jefe a escuela', $schoolId);
            set_flash('success', 'Jefe de escuela asignado correctamente.');
        }

        if ($action === 'assign_teacher_school') {
            $teacherUserId = (int) ($_POST['teacher_user_id'] ?? 0);
            $schoolId = (int) ($_POST['school_id'] ?? 0);

            if ($teacherUserId <= 0 || $schoolId <= 0) {
                throw new RuntimeException('Selecciona un docente y una escuela.');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO teacher_school_assignments (teacher_user_id, school_id, is_active)
                 VALUES (?, ?, 1)
                 ON DUPLICATE KEY UPDATE is_active = 1, assigned_at = CURRENT_TIMESTAMP'
            );
            $stmt->execute([$teacherUserId, $schoolId]);

            log_audit($pdo, (int) current_user()['id'], 'ASSIGN', 'teacher_school_assignments', null, 'Asignación de docente a escuela', $schoolId);
            set_flash('success', 'Docente asignado a la escuela correctamente.');
        }

        if ($action === 'assign_teacher_course') {
            $teacherUserId = (int) ($_POST['teacher_user_id'] ?? 0);
            $courseId = (int) ($_POST['course_id'] ?? 0);
            $periodLabel = trim((string) ($_POST['period_label'] ?? ''));

            $courseStmt = $pdo->prepare('SELECT * FROM courses WHERE id = ? LIMIT 1');
            $courseStmt->execute([$courseId]);
            $course = $courseStmt->fetch();

            if ($teacherUserId <= 0 || !$course) {
                throw new RuntimeException('Selecciona un docente y un curso válido.');
            }

            $checkStmt = $pdo->prepare(
                'SELECT COUNT(*) FROM teacher_school_assignments WHERE teacher_user_id = ? AND school_id = ? AND is_active = 1'
            );
            $checkStmt->execute([$teacherUserId, (int) $course['school_id']]);

            if ((int) $checkStmt->fetchColumn() === 0) {
                throw new RuntimeException('Primero debes asignar al docente a la escuela del curso.');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO teacher_course_assignments (teacher_user_id, course_id, period_label, is_active)
                 VALUES (?, ?, ?, 1)
                 ON DUPLICATE KEY UPDATE period_label = VALUES(period_label), is_active = 1, assigned_at = CURRENT_TIMESTAMP'
            );
            $stmt->execute([$teacherUserId, $courseId, $periodLabel]);

            log_audit($pdo, (int) current_user()['id'], 'ASSIGN', 'teacher_course_assignments', null, 'Asignación de docente a curso ' . $course['code'], (int) $course['school_id']);
            set_flash('success', 'Docente asignado al curso correctamente.');
        }

        if ($action === 'enroll_student') {
            $studentId = (int) ($_POST['student_id'] ?? 0);
            $courseId = (int) ($_POST['course_id'] ?? 0);
            $periodLabel = trim((string) ($_POST['period_label'] ?? ''));

            $studentStmt = $pdo->prepare('SELECT * FROM students WHERE id = ? LIMIT 1');
            $studentStmt->execute([$studentId]);
            $student = $studentStmt->fetch();

            $courseStmt = $pdo->prepare('SELECT * FROM courses WHERE id = ? LIMIT 1');
            $courseStmt->execute([$courseId]);
            $course = $courseStmt->fetch();

            if (!$student || !$course) {
                throw new RuntimeException('Selecciona un alumno y un curso válido.');
            }

            if ((int) $student['school_id'] !== (int) $course['school_id']) {
                throw new RuntimeException('El alumno y el curso deben pertenecer a la misma escuela.');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO course_enrollments (course_id, student_id, period_label, is_active)
                 VALUES (?, ?, ?, 1)
                 ON DUPLICATE KEY UPDATE period_label = VALUES(period_label), is_active = 1, enrolled_at = CURRENT_TIMESTAMP'
            );
            $stmt->execute([$courseId, $studentId, $periodLabel]);

            log_audit($pdo, (int) current_user()['id'], 'ASSIGN', 'course_enrollments', null, 'Matrícula de alumno en curso ' . $course['code'], (int) $course['school_id']);
            set_flash('success', 'Alumno matriculado correctamente.');
        }

        if ($action === 'toggle_head_assignment') {
            $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
            $schoolId = (int) ($_POST['school_id'] ?? 0);
            $stmt = $pdo->prepare('UPDATE head_school_assignments SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?');
            $stmt->execute([$assignmentId]);
            log_audit($pdo, (int) current_user()['id'], 'STATUS', 'head_school_assignments', $assignmentId, 'Cambio de estado en asignación jefe-escuela', $schoolId);
            set_flash('success', 'Asignación jefe-escuela actualizada.');
        }

        if ($action === 'toggle_teacher_school_assignment') {
            $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
            $schoolId = (int) ($_POST['school_id'] ?? 0);
            $stmt = $pdo->prepare('UPDATE teacher_school_assignments SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?');
            $stmt->execute([$assignmentId]);
            log_audit($pdo, (int) current_user()['id'], 'STATUS', 'teacher_school_assignments', $assignmentId, 'Cambio de estado en asignación docente-escuela', $schoolId);
            set_flash('success', 'Asignación docente-escuela actualizada.');
        }

        if ($action === 'toggle_teacher_course_assignment') {
            $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
            $schoolId = (int) ($_POST['school_id'] ?? 0);
            $stmt = $pdo->prepare('UPDATE teacher_course_assignments SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?');
            $stmt->execute([$assignmentId]);
            log_audit($pdo, (int) current_user()['id'], 'STATUS', 'teacher_course_assignments', $assignmentId, 'Cambio de estado en asignación docente-curso', $schoolId);
            set_flash('success', 'Asignación docente-curso actualizada.');
        }

        if ($action === 'toggle_enrollment') {
            $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
            $schoolId = (int) ($_POST['school_id'] ?? 0);
            $stmt = $pdo->prepare('UPDATE course_enrollments SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?');
            $stmt->execute([$assignmentId]);
            log_audit($pdo, (int) current_user()['id'], 'STATUS', 'course_enrollments', $assignmentId, 'Cambio de estado en matrícula de curso', $schoolId);
            set_flash('success', 'Matrícula actualizada.');
        }
    } catch (Throwable $exception) {
        set_flash('error', 'No se pudo guardar la asignación. ' . $exception->getMessage());
    }

    redirect('superadmin/assignments.php');
}

$schools = get_all_schools($pdo, false);
$heads = get_active_users_by_role($pdo, 'head');
$teachers = get_active_users_by_role($pdo, 'teacher');
$courses = get_courses($pdo, null, true);
$students = get_students($pdo);
$headAssignments = get_head_school_assignments($pdo);
$teacherSchoolAssignments = get_teacher_school_assignments($pdo);
$teacherCourseAssignments = get_teacher_course_assignments($pdo);
$enrollments = get_course_enrollments($pdo);

render_header('Asignaciones académicas', 'assignments');
?>
<section class="cards-grid">
    <article class="form-card">
        <p class="eyebrow">Jefe por escuela</p>
        <h3>Asignar jefe</h3>
        <form method="post" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="assign_head_school">
            <div class="field">
                <label>Jefe</label>
                <select name="head_user_id" required>
                    <option value="">Selecciona</option>
                    <?php foreach ($heads as $head): ?>
                        <option value="<?= e((string) $head['id']) ?>"><?= e($head['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Escuela</label>
                <select name="school_id" required>
                    <option value="">Selecciona</option>
                    <?php foreach ($schools as $school): ?>
                        <option value="<?= e((string) $school['id']) ?>"><?= e($school['code'] . ' - ' . $school['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field field-full">
                <button class="btn" type="submit">Asignar jefe</button>
            </div>
        </form>
    </article>

    <article class="form-card">
        <p class="eyebrow">Docente por escuela</p>
        <h3>Asignar docente a escuela</h3>
        <form method="post" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="assign_teacher_school">
            <div class="field">
                <label>Docente</label>
                <select name="teacher_user_id" required>
                    <option value="">Selecciona</option>
                    <?php foreach ($teachers as $teacher): ?>
                        <option value="<?= e((string) $teacher['id']) ?>"><?= e($teacher['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Escuela</label>
                <select name="school_id" required>
                    <option value="">Selecciona</option>
                    <?php foreach ($schools as $school): ?>
                        <option value="<?= e((string) $school['id']) ?>"><?= e($school['code'] . ' - ' . $school['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field field-full">
                <button class="btn" type="submit">Asignar escuela</button>
            </div>
        </form>
    </article>

    <article class="form-card">
        <p class="eyebrow">Docente por curso</p>
        <h3>Asignar curso</h3>
        <form method="post" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="assign_teacher_course">
            <div class="field">
                <label>Docente</label>
                <select name="teacher_user_id" required>
                    <option value="">Selecciona</option>
                    <?php foreach ($teachers as $teacher): ?>
                        <option value="<?= e((string) $teacher['id']) ?>"><?= e($teacher['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Curso</label>
                <select name="course_id" required>
                    <option value="">Selecciona</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= e((string) $course['id']) ?>"><?= e($course['school_code'] . ' | ' . $course['code'] . ' - ' . $course['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field field-full">
                <label>Periodo</label>
                <input name="period_label" placeholder="2026-I">
            </div>
            <div class="field field-full">
                <button class="btn" type="submit">Asignar curso</button>
            </div>
        </form>
    </article>

    <article class="form-card">
        <p class="eyebrow">Matrícula</p>
        <h3>Matricular alumno</h3>
        <form method="post" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="enroll_student">
            <div class="field">
                <label>Alumno</label>
                <select name="student_id" required>
                    <option value="">Selecciona</option>
                    <?php foreach ($students as $student): ?>
                        <option value="<?= e((string) $student['id']) ?>"><?= e($student['student_code'] . ' - ' . $student['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Curso</label>
                <select name="course_id" required>
                    <option value="">Selecciona</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= e((string) $course['id']) ?>"><?= e($course['school_code'] . ' | ' . $course['code'] . ' - ' . $course['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field field-full">
                <label>Periodo</label>
                <input name="period_label" placeholder="2026-I">
            </div>
            <div class="field field-full">
                <button class="btn" type="submit">Matricular alumno</button>
            </div>
        </form>
    </article>
</section>

<section class="table-card">
    <p class="eyebrow">Relaciones activas e históricas</p>
    <h3>Asignaciones registradas</h3>
</section>

<section class="two-column">
    <article class="table-card">
        <p class="eyebrow">Jefes y escuelas</p>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Jefe</th>
                        <th>Escuela</th>
                        <th>Estado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($headAssignments as $assignment): ?>
                        <tr>
                            <td><?= e($assignment['head_name']) ?></td>
                            <td><?= e($assignment['school_code'] . ' - ' . $assignment['school_name']) ?></td>
                            <td><span class="badge <?= (int) $assignment['is_active'] === 1 ? 'badge-success' : 'badge-muted' ?>"><?= (int) $assignment['is_active'] === 1 ? 'Activo' : 'Inactivo' ?></span></td>
                            <td>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_head_assignment">
                                    <input type="hidden" name="assignment_id" value="<?= e((string) $assignment['id']) ?>">
                                    <input type="hidden" name="school_id" value="<?= e((string) $assignment['school_id']) ?>">
                                    <button class="btn-ghost" type="submit"><?= (int) $assignment['is_active'] === 1 ? 'Desactivar' : 'Activar' ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>

    <article class="table-card">
        <p class="eyebrow">Docentes y escuelas</p>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Docente</th>
                        <th>Escuela</th>
                        <th>Estado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teacherSchoolAssignments as $assignment): ?>
                        <tr>
                            <td><?= e($assignment['teacher_name']) ?></td>
                            <td><?= e($assignment['school_code'] . ' - ' . $assignment['school_name']) ?></td>
                            <td><span class="badge <?= (int) $assignment['is_active'] === 1 ? 'badge-success' : 'badge-muted' ?>"><?= (int) $assignment['is_active'] === 1 ? 'Activo' : 'Inactivo' ?></span></td>
                            <td>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_teacher_school_assignment">
                                    <input type="hidden" name="assignment_id" value="<?= e((string) $assignment['id']) ?>">
                                    <input type="hidden" name="school_id" value="<?= e((string) $assignment['school_id']) ?>">
                                    <button class="btn-ghost" type="submit"><?= (int) $assignment['is_active'] === 1 ? 'Desactivar' : 'Activar' ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>

<section class="two-column">
    <article class="table-card">
        <p class="eyebrow">Docentes y cursos</p>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Docente</th>
                        <th>Curso</th>
                        <th>Periodo</th>
                        <th>Estado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teacherCourseAssignments as $assignment): ?>
                        <tr>
                            <td><?= e($assignment['teacher_name']) ?></td>
                            <td><?= e($assignment['course_code'] . ' - ' . $assignment['course_name']) ?></td>
                            <td><?= e($assignment['period_label'] ?: '-') ?></td>
                            <td><span class="badge <?= (int) $assignment['is_active'] === 1 ? 'badge-success' : 'badge-muted' ?>"><?= (int) $assignment['is_active'] === 1 ? 'Activo' : 'Inactivo' ?></span></td>
                            <td>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_teacher_course_assignment">
                                    <input type="hidden" name="assignment_id" value="<?= e((string) $assignment['id']) ?>">
                                    <input type="hidden" name="school_id" value="<?= e((string) $assignment['school_id']) ?>">
                                    <button class="btn-ghost" type="submit"><?= (int) $assignment['is_active'] === 1 ? 'Desactivar' : 'Activar' ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>

    <article class="table-card">
        <p class="eyebrow">Matrículas</p>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Alumno</th>
                        <th>Curso</th>
                        <th>Periodo</th>
                        <th>Estado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($enrollments as $assignment): ?>
                        <tr>
                            <td><?= e($assignment['student_code'] . ' - ' . $assignment['student_name']) ?></td>
                            <td><?= e($assignment['course_code'] . ' - ' . $assignment['course_name']) ?></td>
                            <td><?= e($assignment['period_label'] ?: '-') ?></td>
                            <td><span class="badge <?= (int) $assignment['is_active'] === 1 ? 'badge-success' : 'badge-muted' ?>"><?= (int) $assignment['is_active'] === 1 ? 'Activo' : 'Inactivo' ?></span></td>
                            <td>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_enrollment">
                                    <input type="hidden" name="assignment_id" value="<?= e((string) $assignment['id']) ?>">
                                    <input type="hidden" name="school_id" value="<?= e((string) $assignment['school_id']) ?>">
                                    <button class="btn-ghost" type="submit"><?= (int) $assignment['is_active'] === 1 ? 'Desactivar' : 'Activar' ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>
<?php render_footer(); ?>
