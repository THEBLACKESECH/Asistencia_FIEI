<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_role('head');

$pdo = database();
$user = current_user();
$schools = get_head_schools($pdo, (int) $user['id']);
$schoolIds = get_head_school_ids($pdo, (int) $user['id']);
$stats = get_head_dashboard_stats($pdo, $schoolIds);
$alerts = get_risk_students_for_schools($pdo, $schoolIds);
$recentSessions = get_recent_attendance_sessions($pdo, ['school_ids' => $schoolIds], 8);
$courses = get_courses($pdo, $schoolIds, true);

render_header('Panel del jefe de escuela', 'dashboard', $alerts);
?>
<section class="hero-card">
    <div>
        <p class="eyebrow">Escuelas bajo supervisión</p>
        <h2><?= e($user['full_name']) ?></h2>
        <p>Este panel solo permite consulta, seguimiento de alertas y generación de reportes sobre las escuelas que administras.</p>
    </div>
    <div class="inline-list">
        <?php foreach ($schools as $school): ?>
            <span class="badge badge-info"><?= e($school['code'] . ' - ' . $school['name']) ?></span>
        <?php endforeach; ?>
    </div>
</section>

<section class="metrics-grid">
    <article class="metric-card">
        <p class="eyebrow">Escuelas</p>
        <p class="metric-value"><?= e((string) $stats['schools']) ?></p>
        <p class="muted">Ámbito de consulta habilitado.</p>
    </article>
    <article class="metric-card">
        <p class="eyebrow">Cursos activos</p>
        <p class="metric-value"><?= e((string) $stats['courses']) ?></p>
        <p class="muted">Cursos visibles dentro de tu escuela.</p>
    </article>
    <article class="metric-card">
        <p class="eyebrow">Alumnos</p>
        <p class="metric-value"><?= e((string) $stats['students']) ?></p>
        <p class="muted">Matrícula disponible para consulta.</p>
    </article>
    <article class="metric-card">
        <p class="eyebrow">Sesiones registradas</p>
        <p class="metric-value"><?= e((string) $stats['sessions']) ?></p>
        <p class="muted">Asistencias acumuladas por los docentes.</p>
    </article>
</section>

<section class="two-column">
    <article class="table-card">
        <div class="button-row" style="justify-content: space-between;">
            <div>
                <p class="eyebrow">Riesgo de inasistencia</p>
                <h3>Alumnos con más de 30% de faltas</h3>
            </div>
            <a class="btn-secondary" href="<?= e(app_url('head/reports.php')) ?>">Abrir reportes</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Alumno</th>
                        <th>Curso</th>
                        <th>Porcentaje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($alerts === []): ?>
                        <tr>
                            <td colspan="3" class="muted">No hay alumnos en riesgo por inasistencia.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach (array_slice($alerts, 0, 10) as $alert): ?>
                            <tr>
                                <td><?= e($alert['student_name']) ?></td>
                                <td><?= e($alert['course_code'] . ' - ' . $alert['course_name']) ?></td>
                                <td><span class="badge badge-danger"><?= e(format_percentage((float) $alert['absence_rate'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>

    <article class="table-card">
        <p class="eyebrow">Actividad reciente</p>
        <h3>Últimas asistencias registradas</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Curso</th>
                        <th>Docente</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recentSessions === []): ?>
                        <tr>
                            <td colspan="3" class="muted">Aún no hay asistencias registradas en tus escuelas.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentSessions as $session): ?>
                            <tr>
                                <td><?= e($session['attendance_date']) ?></td>
                                <td><?= e($session['course_code'] . ' - ' . $session['course_name']) ?></td>
                                <td><?= e($session['teacher_name']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>

<section class="table-card">
    <p class="eyebrow">Cursos visibles</p>
    <h3>Oferta académica de tus escuelas</h3>
    <div class="cards-grid">
        <?php foreach ($courses as $course): ?>
            <article class="card">
                <p class="eyebrow"><?= e($course['school_code'] . ' - ' . $course['school_name']) ?></p>
                <h3><?= e($course['code'] . ' - ' . $course['name']) ?></h3>
                <p>
                    Sección: <strong><?= e($course['section'] ?: 'Única') ?></strong><br>
                    Ciclo: <strong><?= e($course['cycle'] ?: 'No definido') ?></strong>
                </p>
                <div class="inline-list">
                    <span class="badge badge-info"><?= e((string) $course['total_students']) ?> alumnos</span>
                    <span class="badge badge-muted"><?= e((string) $course['total_sessions']) ?> sesiones</span>
                </div>
                <p style="margin-top: 1rem;">
                    <a class="btn-secondary" href="<?= e(app_url('head/reports.php?course_id=' . $course['id'])) ?>">Ver reporte</a>
                </p>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php render_footer(); ?>
