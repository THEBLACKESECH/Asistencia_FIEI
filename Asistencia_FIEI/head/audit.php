<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_role('head');

$pdo = database();
$schoolIds = get_head_school_ids($pdo, (int) current_user()['id']);
$logs = get_audit_logs($pdo, $schoolIds, 150);

render_header('Auditoría de la escuela', 'audit');
?>
<section class="hero-card">
    <div>
        <p class="eyebrow">Solo lectura</p>
        <h2>Trazabilidad de acciones</h2>
        <p>La auditoría es visible para jefes de escuela y superadmin. No se puede editar ni eliminar desde la interfaz.</p>
    </div>
</section>

<section class="table-card">
    <p class="eyebrow">Eventos recientes</p>
    <h3>Historial auditado</h3>
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
                <?php if ($logs === []): ?>
                    <tr>
                        <td colspan="6" class="muted">No hay eventos de auditoría para mostrar.</td>
                    </tr>
                <?php else: ?>
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
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php render_footer(); ?>
