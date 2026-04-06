<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_role('teacher');

$pdo = database();
$user = current_user();
$courses = get_teacher_courses($pdo, (int) $user['id']);
$schools = get_teacher_schools($pdo, (int) $user['id']);
$alerts = get_risk_students_for_teacher($pdo, (int) $user['id']);
$recentSessions = get_recent_attendance_sessions($pdo, ['teacher_user_id' => (int) $user['id']], 6);

render_header('Panel del docente', 'dashboard', $alerts);
?>
<section class="hero-card">
    <div>
        <p class="eyebrow">Perfil docente</p>
        <h2><?= e($user['full_name']) ?></h2>
        <p>
            Usuario: <strong><?= e($user['username']) ?></strong><br>
            Correo: <strong><?= e($user['email'] ?: 'No registrado') ?></strong>
        </p>
    </div>
    <div class="inline-list">
        <?php foreach ($schools as $school): ?>
            <span class="badge badge-info"><?= e($school['code'] . ' - ' . $school['name']) ?></span>
        <?php endforeach; ?>
    </div>
</section>

<section class="metrics-grid">
    <article class="metric-card">
        <p class="eyebrow">Cursos asignados</p>
        <p class="metric-value"><?= e((string) count($courses)) ?></p>
        <p class="muted">Solo puedes registrar asistencia en estos cursos.</p>
    </article>
    <article class="metric-card">
        <p class="eyebrow">Escuelas vinculadas</p>
        <p class="metric-value"><?= e((string) count($schools)) ?></p>
        <p class="muted">Tu acceso se limita a las escuelas asignadas.</p>
    </article>
    <article class="metric-card">
        <p class="eyebrow">Alertas activas</p>
        <p class="metric-value"><?= e((string) count($alerts)) ?></p>
        <p class="muted">Alumnos por encima del 30% de inasistencia.</p>
    </article>
    <article class="metric-card">
        <p class="eyebrow">Registros recientes</p>
        <p class="metric-value"><?= e((string) count($recentSessions)) ?></p>
        <p class="muted">Sesiones de asistencia más recientes.</p>
    </article>
</section>

<section class="table-card">
    <div class="button-row" style="justify-content: space-between;">
        <div>
            <p class="eyebrow">Tus cursos</p>
            <h3>Cursos habilitados para registrar asistencia</h3>
        </div>
        <a class="btn" href="<?= e(app_url('teacher/attendance.php')) ?>">Registrar asistencia</a>
    </div>

    <?php if ($courses === []): ?>
        <div class="empty-state">
            <h3>No tienes cursos asignados</h3>
            <p class="muted">El superadmin debe asignarte al menos un curso para habilitar el registro de asistencia.</p>
        </div>
    <?php else: ?>
        <div class="cards-grid">
            <?php foreach ($courses as $course): ?>
                <article class="card">
                    <p class="eyebrow"><?= e($course['school_code'] . ' - ' . $course['school_name']) ?></p>
                    <h3><?= e($course['code'] . ' - ' . $course['name']) ?></h3>
                    <p>
                        Sección: <strong><?= e($course['section'] ?: 'Única') ?></strong><br>
                        Ciclo: <strong><?= e($course['cycle'] ?: 'No definido') ?></strong><br>
                        Periodo: <strong><?= e($course['period_label'] ?: 'Actual') ?></strong>
                    </p>
                    <div class="inline-list">
                        <span class="badge badge-info"><?= e((string) $course['total_students']) ?> alumnos</span>
                        <span class="badge badge-muted"><?= e((string) $course['total_sessions']) ?> sesiones</span>
                    </div>
                    <p style="margin-top: 1rem;">
                        <a class="btn-secondary" href="<?= e(app_url('teacher/attendance.php?course_id=' . $course['id'])) ?>">Abrir curso</a>
                    </p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="table-card">
    <p class="eyebrow">Actividad reciente</p>
    <h3>Últimos registros de asistencia</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Curso</th>
                    <th>Escuela</th>
                    <th>Registros</th>
                    <th>Faltas</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recentSessions === []): ?>
                    <tr>
                        <td colspan="5" class="muted">Todavía no registraste asistencias.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentSessions as $session): ?>
                        <tr>
                            <td><?= e($session['attendance_date']) ?></td>
                            <td><?= e($session['course_code'] . ' - ' . $session['course_name']) ?></td>
                            <td><?= e($session['school_name']) ?></td>
                            <td><?= e((string) $session['total_records']) ?></td>
                            <td><?= e((string) $session['total_absences']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php render_footer(); ?>
