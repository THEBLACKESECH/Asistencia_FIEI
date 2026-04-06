<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_role('teacher');

$pdo = database();
$user = current_user();
$courses = get_teacher_courses($pdo, (int) $user['id']);
$alerts = get_risk_students_for_teacher($pdo, (int) $user['id']);
$allowedStatuses = ['present', 'late', 'justified', 'absent'];

$selectedCourseId = request_int('course_id');
if ($selectedCourseId === null && $courses !== []) {
    $selectedCourseId = (int) $courses[0]['id'];
}

$selectedDate = (string) ($_GET['date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate) || $selectedDate > date('Y-m-d')) {
    $selectedDate = date('Y-m-d');
}

if (is_post()) {
    verify_csrf_or_abort('teacher/attendance.php');

    $selectedCourseId = (int) ($_POST['course_id'] ?? 0);
    $selectedDate = (string) ($_POST['attendance_date'] ?? date('Y-m-d'));
    $course = get_teacher_course($pdo, (int) $user['id'], $selectedCourseId);

    if ($course === null) {
        set_flash('error', 'No puedes registrar asistencia en un curso que no te pertenece.');
        redirect('teacher/attendance.php');
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate) || $selectedDate > date('Y-m-d')) {
        set_flash('error', 'La fecha de asistencia no es válida.');
        redirect('teacher/attendance.php?course_id=' . $selectedCourseId);
    }

    if (get_attendance_session($pdo, $selectedCourseId, $selectedDate) !== null) {
        set_flash('error', 'La asistencia de ese curso y fecha ya fue registrada y no puede modificarse.');
        redirect('teacher/attendance.php?course_id=' . $selectedCourseId . '&date=' . $selectedDate);
    }

    $students = get_course_students($pdo, $selectedCourseId);

    if ($students === []) {
        set_flash('error', 'No hay alumnos matriculados en este curso.');
        redirect('teacher/attendance.php?course_id=' . $selectedCourseId);
    }

    $attendanceInput = $_POST['attendance'] ?? [];

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO attendance_sessions (course_id, teacher_user_id, attendance_date) VALUES (?, ?, ?)'
        );
        $stmt->execute([$selectedCourseId, (int) $user['id'], $selectedDate]);
        $sessionId = (int) $pdo->lastInsertId();

        $recordStmt = $pdo->prepare(
            'INSERT INTO attendance_records (session_id, student_id, status) VALUES (?, ?, ?)'
        );

        foreach ($students as $student) {
            $status = (string) ($attendanceInput[$student['id']] ?? 'present');

            if (!in_array($status, $allowedStatuses, true)) {
                throw new RuntimeException('Se detectó un estado de asistencia inválido.');
            }

            $recordStmt->execute([$sessionId, (int) $student['id'], $status]);
        }

        log_audit(
            $pdo,
            (int) $user['id'],
            'CREATE',
            'attendance_sessions',
            $sessionId,
            'Registro de asistencia del curso ' . $course['code'] . ' para la fecha ' . $selectedDate,
            (int) $course['school_id']
        );

        $pdo->commit();

        set_flash('success', 'La asistencia se registró correctamente y quedó bloqueada para edición.');
        redirect('teacher/attendance.php?course_id=' . $selectedCourseId . '&date=' . $selectedDate);
    } catch (Throwable $exception) {
        $pdo->rollBack();
        set_flash('error', 'No se pudo registrar la asistencia. ' . $exception->getMessage());
        redirect('teacher/attendance.php?course_id=' . $selectedCourseId);
    }
}

$selectedCourse = $selectedCourseId !== null ? get_teacher_course($pdo, (int) $user['id'], $selectedCourseId) : null;

if ($selectedCourse === null && $courses !== []) {
    $selectedCourse = get_teacher_course($pdo, (int) $user['id'], (int) $courses[0]['id']);
    $selectedCourseId = $selectedCourse ? (int) $selectedCourse['id'] : null;
}

$students = $selectedCourse ? get_course_students($pdo, (int) $selectedCourse['id']) : [];
$existingSession = $selectedCourse ? get_attendance_session($pdo, (int) $selectedCourse['id'], $selectedDate) : null;
$existingRecords = $existingSession ? get_attendance_records_by_session($pdo, (int) $existingSession['id']) : [];

render_header('Registro de asistencia', 'attendance', $alerts);
?>
<section class="hero-card">
    <div>
        <p class="eyebrow">Regla de control</p>
        <h2>La asistencia diaria no se puede editar después del registro.</h2>
        <p>Selecciona uno de tus cursos asignados, registra la asistencia y el sistema bloqueará automáticamente cualquier modificación posterior para esa fecha.</p>
    </div>
    <div class="inline-list">
        <span class="badge badge-success">Asistió</span>
        <span class="badge badge-warning">Tardanza</span>
        <span class="badge badge-info">Justificada</span>
        <span class="badge badge-danger">Falta</span>
    </div>
</section>

<section class="form-card">
    <p class="eyebrow">Filtro de trabajo</p>
    <h3>Curso y fecha</h3>
    <form method="get" class="form-grid">
        <div class="field">
            <label for="course_id">Curso asignado</label>
            <select id="course_id" name="course_id" required>
                <?php foreach ($courses as $course): ?>
                    <option value="<?= e((string) $course['id']) ?>" <?= $selectedCourseId === (int) $course['id'] ? 'selected' : '' ?>>
                        <?= e($course['code'] . ' - ' . $course['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="date">Fecha</label>
            <input id="date" type="date" name="date" max="<?= e(date('Y-m-d')) ?>" value="<?= e($selectedDate) ?>">
        </div>
        <div class="field field-full">
            <button class="btn-secondary" type="submit">Actualizar vista</button>
        </div>
    </form>
</section>

<?php if ($selectedCourse === null): ?>
    <section class="empty-state">
        <h3>No hay curso seleccionado</h3>
        <p class="muted">Primero necesitas tener cursos asignados para registrar asistencia.</p>
    </section>
<?php elseif ($existingSession !== null): ?>
    <section class="table-card">
        <p class="eyebrow">Registro bloqueado</p>
        <h3><?= e($selectedCourse['code'] . ' - ' . $selectedCourse['name']) ?> | <?= e($selectedDate) ?></h3>
        <p class="muted">Esta asistencia ya fue registrada por <?= e($existingSession['teacher_name']) ?> y quedó en modo solo lectura.</p>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Alumno</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($existingRecords as $record): ?>
                        <tr>
                            <td><?= e($record['student_code']) ?></td>
                            <td><?= e($record['full_name']) ?></td>
                            <td><span class="badge <?= e(attendance_badge_class($record['status'])) ?>"><?= e(attendance_label($record['status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php else: ?>
    <section class="table-card">
        <p class="eyebrow">Captura diaria</p>
        <h3><?= e($selectedCourse['code'] . ' - ' . $selectedCourse['name']) ?></h3>
        <p class="muted">
            Escuela: <?= e($selectedCourse['school_name']) ?> |
            Sección: <?= e($selectedCourse['section'] ?: 'Única') ?> |
            Periodo: <?= e($selectedCourse['period_label'] ?: 'Actual') ?>
        </p>

        <?php if ($students === []): ?>
            <div class="empty-state">
                <h3>Sin alumnos matriculados</h3>
                <p class="muted">El superadmin debe asignar alumnos a este curso antes del registro de asistencia.</p>
            </div>
        <?php else: ?>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="course_id" value="<?= e((string) $selectedCourse['id']) ?>">
                <input type="hidden" name="attendance_date" value="<?= e($selectedDate) ?>">
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Alumno</th>
                                <th>Semestre</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><?= e($student['student_code']) ?></td>
                                    <td><?= e($student['full_name']) ?></td>
                                    <td><?= e($student['semester'] ?: '-') ?></td>
                                    <td>
                                        <select class="attendance-select" name="attendance[<?= e((string) $student['id']) ?>]">
                                            <option value="present">Asistió</option>
                                            <option value="late">Tardanza</option>
                                            <option value="justified">Justificada</option>
                                            <option value="absent">Falta</option>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="button-row" style="margin-top: 1rem;">
                    <button class="btn" type="submit">Guardar asistencia del día</button>
                </div>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php render_footer(); ?>
