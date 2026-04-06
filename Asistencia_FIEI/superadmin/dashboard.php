<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_role('superadmin');

$pdo = database();
$stats = get_superadmin_stats($pdo);
$schools = get_all_schools($pdo);
$logs = get_audit_logs($pdo, null, 12);
$recentSessions = get_recent_attendance_sessions($pdo, [], 6);

render_header('Panel de superadministración', 'dashboard');
?>
<section class="hero-card">
    <div>
        <p class="eyebrow">Gestión central</p>
        <h2>Administración integral del sistema</h2>
        <p>Desde aquí puedes gestionar usuarios, cursos, alumnos, escuelas, asignaciones y revisar la auditoría global del sistema.</p>
    </div>
    <div class="inline-list">
        <?php foreach ($schools as $school): ?>
            <span class="badge <?= (int) $school['is_active'] === 1 ? 'badge-info' : 'badge-muted' ?>">
                <?= e($school['code'] . ' - ' . $school['name']) ?>
            </span>
        <?php endforeach; ?>
    </div>
</section>

<section class="metrics-grid">
    <article class="metric-card">
        <p class="eyebrow">Escuelas activas</p>
        <p class="metric-value"><?= e((string) $stats['schools']) ?></p>
        <p class="muted">Catálogo institucional disponible.</p>
    </article>
    <article class="metric-card">
        <p class="eyebrow">Usuarios habilitados</p>
        <p class="metric-value"><?= e((string) $stats['users']) ?></p>
        <p class="muted">Accesos vigentes al sistema.</p>
    </article>
    <article class="metric-card">
        <p class="eyebrow">Alumnos activos</p>
        <p class="metric-value"><?= e((string) $stats['students']) ?></p>
        <p class="muted">Matrícula registrada en el sistema.</p>
    </article>
    <article class="metric-card">
        <p class="eyebrow">Cursos activos</p>
        <p class="metric-value"><?= e((string) $stats['courses']) ?></p>
        <p class="muted">Oferta académica disponible.</p>
    </article>
</section>

<section class="cards-grid">
    <article class="card">
        <p class="eyebrow">Usuarios</p>
        <h3>Crear, editar y habilitar accesos</h3>
        <p>Administra superadmins, jefes y docentes con credenciales seguras y control de disponibilidad.</p>
        <a class="btn-secondary" href="<?= e(app_url('superadmin/users.php')) ?>">Ir a usuarios</a>
    </article>
    <article class="card">
        <p class="eyebrow">Académico</p>
        <h3>Alumnos y cursos</h3>
        <p>Registra alumnos, cursos y la estructura académica vinculada a sus escuelas respectivas.</p>
        <div class="button-row">
            <a class="btn-secondary" href="<?= e(app_url('superadmin/students.php')) ?>">Alumnos</a>
            <a class="btn-secondary" href="<?= e(app_url('superadmin/courses.php')) ?>">Cursos</a>
        </div>
    </article>
    <article class="card">
        <p class="eyebrow">Asignaciones</p>
        <h3>Relaciona personas y cursos</h3>
        <p>Define qué jefe supervisa una escuela, qué docente pertenece a una escuela y qué curso dicta.</p>
        <a class="btn-secondary" href="<?= e(app_url('superadmin/assignments.php')) ?>">Ir a asignaciones</a>
    </article>
</section>

<section class="two-column">
    <article class="table-card">
        <p class="eyebrow">Últimos eventos</p>
        <h3>Auditoría reciente</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Usuario</th>
                        <th>Acción</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= e($log['created_at']) ?></td>
                            <td><?= e($log['actor_name'] ?: 'Sistema') ?></td>
                            <td><?= e($log['action']) ?></td>
                            <td><?= e($log['description']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>

    <article class="table-card">
        <p class="eyebrow">Actividad académica</p>
        <h3>Registros recientes de asistencia</h3>
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
                            <td colspan="3" class="muted">Aún no hay registros de asistencia en el sistema.</td>
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
<?php render_footer(); ?>
