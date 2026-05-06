<?php
use Browser\Core\Csrf;
use Browser\Core\View;
$signalLabel = match ($healthSignal) {
    1 => 'Saludable',
    0 => 'Pendiente',
    default => 'Riesgo',
};
?>
<div class="card">
<h1><?= View::e($client['company_name']) ?></h1>
<p><strong>Estado:</strong> <?= View::e($client['status']) ?></p>
<p><strong>client_health_signal:</strong> <?= View::e((string)$healthSignal) ?> (<?= View::e($signalLabel) ?>)</p>
<p><strong>Contacto:</strong> <?= View::e((string)$client['contact_name']) ?> | <?= View::e((string)$client['contact_email']) ?> | <?= View::e((string)$client['contact_phone']) ?></p>
<p><strong>Sitio:</strong> <?= View::e((string)$client['website']) ?></p>
<p><strong>Notas:</strong><br><?= nl2br(View::e((string)$client['notes'])) ?></p>
<p><a href="/marketing/clients/edit?id=<?= View::e((string)$client['id']) ?>">Editar</a> · <a href="/marketing/clients">Listado</a></p>
</div>
<form class="card" style="margin-top:16px;" method="post" action="/marketing/clients/status">
<?= Csrf::field() ?><input type="hidden" name="id" value="<?= View::e((string)$client['id']) ?>">
<label>Cambiar estado <select name="status"><option value="active">active</option><option value="prospect">prospect</option><option value="paused">paused</option><option value="inactive">inactive</option></select></label>
<button class="button secondary" type="submit">Actualizar estado</button>
</form>
<form class="card" style="margin-top:16px;" method="post" action="/marketing/clients/delete" onsubmit="return confirm('¿Eliminar cliente?');">
<?= Csrf::field() ?><input type="hidden" name="id" value="<?= View::e((string)$client['id']) ?>">
<button class="button" type="submit">Eliminar cliente</button>
</form>
