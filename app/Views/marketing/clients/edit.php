<?php use Browser\Core\Csrf; use Browser\Core\View; ?>
<div class="card"><h1>Editar cliente</h1><p><a href="/marketing/clients/show?id=<?= View::e((string)$client['id']) ?>">← Volver al detalle</a></p></div>
<form class="card" method="post" action="/marketing/clients/update" style="margin-top:16px;">
<?= Csrf::field() ?><input type="hidden" name="id" value="<?= View::e((string)$client['id']) ?>">
<label>Empresa <input name="company_name" required maxlength="190" value="<?= View::e($client['company_name']) ?>"></label>
<label>Contacto <input name="contact_name" maxlength="120" value="<?= View::e((string)$client['contact_name']) ?>"></label>
<label>Email <input name="contact_email" type="email" maxlength="190" value="<?= View::e((string)$client['contact_email']) ?>"></label>
<label>Teléfono <input name="contact_phone" maxlength="60" value="<?= View::e((string)$client['contact_phone']) ?>"></label>
<label>Website <input name="website" type="url" maxlength="255" value="<?= View::e((string)$client['website']) ?>"></label>
<label>Estado <select name="status" required><?php foreach (['active','prospect','paused','inactive'] as $st): ?><option value="<?= View::e($st) ?>" <?= $client['status'] === $st ? 'selected' : '' ?>><?= View::e($st) ?></option><?php endforeach; ?></select></label>
<label>Notas <textarea name="notes" rows="4" maxlength="5000"><?= View::e((string)$client['notes']) ?></textarea></label>
<button class="button" type="submit">Guardar cambios</button>
</form>
