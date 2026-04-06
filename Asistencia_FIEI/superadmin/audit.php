<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_role('superadmin');

$logs = get_audit_logs(database(), null, 200);

render_header('Auditoría global', 'audit');
?>
<section class="hero-card">
    <div>
        <p class="eyebrow">Registro inalterable desde UI</p>
        <h2>Auditoría completa del sistema</h2>
        <p>Cada creación, actualización, cambio de estado e inicio de sesión queda trazado para seguimiento institucional.</p>
    </div>
</section>

<section class="table-card">
    <p class="eyebrow">Historial</p>
    <h3>Eventos del sistema</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Usuario</th>
                    <th>Acción</th>
                    <th>Tabla</th>
                    <th>Escuela</th>
                    <th>Detalle</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= e($log['created_at']) ?></td>
                        <td><?= e($log['actor_name'] ?: 'Sistema') ?></td>
                        <td><span class="badge badge-info"><?= e($log['action']) ?></span></td>
                        <td><?= e($log['table_name']) ?></td>
                        <td><?= e($log['school_name'] ?: 'Global') ?></td>
                        <td><?= e($log['description']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php render_footer(); ?>
