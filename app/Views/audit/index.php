<?php

use Browser\Core\View;

$logs = $logs ?? [];
$filters = $filters ?? [];
$total = $total ?? 0;
?>
<section class="card">
    <h1>Eventos de auditoría</h1>
    <p class="muted">Registros recientes de la tabla <code>audit_logs</code>.</p>

    <form method="get" action="/admin/audit-logs" class="card" style="margin-top: 16px;">
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
            <label>Acción<input type="text" name="action" value="<?= View::e((string) ($filters['action'] ?? '')) ?>"></label>
            <label>User ID<input type="number" min="1" step="1" name="user_id" value="<?= View::e((string) ($filters['user_id'] ?? '')) ?>"></label>
            <label>Fecha desde<input type="date" name="date_from" value="<?= View::e((string) ($filters['date_from'] ?? '')) ?>"></label>
            <label>Fecha hasta<input type="date" name="date_to" value="<?= View::e((string) ($filters['date_to'] ?? '')) ?>"></label>
        </div>
        <div style="margin-top:12px;"><button type="submit" class="button">Filtrar</button><a href="/admin/audit-logs" class="button secondary">Limpiar</a></div>
    </form>

    <p><strong>Total:</strong> <?= View::e((string) $total) ?></p>

    <div style="overflow-x:auto;">
        <table>
            <thead><tr><th>Fecha</th><th>User ID</th><th>Action</th><th>Entity Type</th><th>Entity ID</th><th>IP</th><th>User Agent</th><th>Metadata</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= View::e((string) ($log['created_at'] ?? '')) ?></td><td><?= View::e((string) ($log['user_id'] ?? '')) ?></td><td><?= View::e((string) ($log['action'] ?? '')) ?></td><td><?= View::e((string) ($log['entity_type'] ?? '')) ?></td><td><?= View::e((string) ($log['entity_id'] ?? '')) ?></td><td><?= View::e((string) ($log['ip_address'] ?? '')) ?></td>
                    <td title="<?= View::e((string) ($log['user_agent'] ?? '')) ?>"><?= View::e(substr((string) ($log['user_agent'] ?? ''), 0, 80)) ?></td>
                    <td><?php $metadataDisplay = $log['metadata_display'] ?? null; if (is_array($metadataDisplay)): ?><pre style="white-space: pre-wrap;"><?= View::e((string) json_encode($metadataDisplay, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) ?></pre><?php else: ?><?= View::e((string) $metadataDisplay) ?><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
