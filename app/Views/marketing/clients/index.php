<?php
use Browser\Core\View;
?>
<div class="card">
    <h1>Clientes de marketing</h1>
    <p class="muted">Gestiona clientes, estado comercial y datos de contacto.</p>
    <p><a class="button" href="/marketing/clients/create">Nuevo cliente</a> <a class="button secondary" href="/marketing">Volver a marketing</a></p>
</div>
<section class="card" style="margin-top:16px; overflow:auto;">
    <table style="width:100%; min-width:760px;">
        <thead><tr><th>ID</th><th>Empresa</th><th>Contacto</th><th>Email</th><th>Estado</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($clients as $client): ?>
            <tr>
                <td><?= View::e((string) $client['id']) ?></td>
                <td><?= View::e($client['company_name']) ?></td>
                <td><?= View::e((string) ($client['contact_name'] ?? '')) ?></td>
                <td><?= View::e((string) ($client['contact_email'] ?? '')) ?></td>
                <td><?= View::e($client['status']) ?></td>
                <td><a href="/marketing/clients/show?id=<?= View::e((string) $client['id']) ?>">Ver</a> | <a href="/marketing/clients/edit?id=<?= View::e((string) $client['id']) ?>">Editar</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
