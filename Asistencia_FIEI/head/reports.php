<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_role('head');

$pdo = database();
$user = current_user();
$schoolIds = get_head_school_ids($pdo, (int) $user['id']);
$alerts = get_risk_students_for_schools($pdo, $schoolIds);
$courses = get_courses($pdo, $schoolIds, true);
$selectedCourseId = request_int('course_id');
$selectedCourse = null;

foreach ($courses as $course) {
    if ((int) $course['id'] === $selectedCourseId) {
        $selectedCourse = $course;
        break;
    }
}

if ($selectedCourse === null) {
    $selectedCourseId = null;
}

$riskCountsByCourse = [];
foreach ($alerts as $alert) {
    $courseId = (int) $alert['course_id'];
    $riskCountsByCourse[$courseId] = ($riskCountsByCourse[$courseId] ?? 0) + 1;
}

$reportRows = $selectedCourseId !== null ? get_course_report($pdo, $selectedCourseId) : [];

render_header('Reportes de asistencia', 'reports', $alerts);
?>
<section class="form-card">
    <p class="eyebrow">Consulta por curso</p>
    <h3>Selecciona un curso de tu escuela</h3>
    <form method="get" class="form-grid">
        <div class="field field-full">
            <label for="course_id">Curso</label>
            <select id="course_id" name="course_id">
                <option value="">Vista general</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?= e((string) $course['id']) ?>" <?= $selectedCourseId === (int) $course['id'] ? 'selected' : '' ?>>
                        <?= e($course['school_code'] . ' | ' . $course['code'] . ' - ' . $course['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field field-full">
            <button class="btn-secondary" type="submit">Consultar</button>
        </div>
    </form>
</section>

<section class="table-card">
    <p class="eyebrow">Resumen general</p>
    <h3>Consolidado por curso</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Escuela</th>
                    <th>Curso</th>
                    <th>Alumnos</th>
                    <th>Sesiones</th>
                    <th>En riesgo</th>
                    <th>Consulta</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($courses as $course): ?>
                    <tr>
                        <td><?= e($course['school_name']) ?></td>
                        <td><?= e($course['code'] . ' - ' . $course['name']) ?></td>
                        <td><?= e((string) $course['total_students']) ?></td>
                        <td><?= e((string) $course['total_sessions']) ?></td>
                        <td>
                            <span class="badge <?= ($riskCountsByCourse[(int) $course['id']] ?? 0) > 0 ? 'badge-danger' : 'badge-success' ?>">
                                <?= e((string) ($riskCountsByCourse[(int) $course['id']] ?? 0)) ?>
                            </span>
                        </td>
                        <td>
                            <a class="btn-secondary" href="<?= e(app_url('head/reports.php?course_id=' . $course['id'])) ?>">Detalle</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($selectedCourse !== null): ?>
    <section class="table-card">
        <p class="eyebrow">Detalle del curso</p>
        <h3><?= e($selectedCourse['code'] . ' - ' . $selectedCourse['name']) ?></h3>
        <p class="muted">
            Escuela: <?= e($selectedCourse['school_name']) ?> |
            Sección: <?= e($selectedCourse['section'] ?: 'Única') ?> |
            Ciclo: <?= e($selectedCourse['cycle'] ?: 'No definido') ?>
        </p>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Alumno</th>
                        <th>Total clases</th>
                        <th>Faltas</th>
                        <th>% faltas</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($reportRows === []): ?>
                        <tr>
                            <td colspan="6" class="muted">Aún no hay datos suficientes para este curso.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reportRows as $row): ?>
                            <tr>
                                <td><?= e($row['student_code']) ?></td>
                                <td><?= e($row['full_name']) ?></td>
                                <td><?= e((string) $row['total_classes']) ?></td>
                                <td><?= e((string) $row['absences']) ?></td>
                                <td><span class="badge <?= $row['absence_rate'] > 30 ? 'badge-danger' : 'badge-success' ?>"><?= e(format_percentage((float) $row['absence_rate'])) ?></span></td>
                                <td><?= e($row['attendance_state']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
<?php render_footer(); ?>
