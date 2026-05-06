<?php use Browser\Core\Csrf; ?>
<div class="card"><h1>Nuevo cliente</h1><p><a href="/marketing/clients">← Volver</a></p></div>
<form class="card" method="post" action="/marketing/clients/store" style="margin-top:16px;">
<?= Csrf::field() ?>
<label>Empresa <input name="company_name" required maxlength="190"></label>
<label>Contacto <input name="contact_name" maxlength="120"></label>
<label>Email <input name="contact_email" type="email" maxlength="190"></label>
<label>Teléfono <input name="contact_phone" maxlength="60"></label>
<label>Website <input name="website" type="url" maxlength="255"></label>
<label>Estado <select name="status" required><option value="prospect">prospect</option><option value="active">active</option><option value="paused">paused</option><option value="inactive">inactive</option></select></label>
<label>Notas <textarea name="notes" rows="4" maxlength="5000"></textarea></label>
<button class="button" type="submit">Guardar cliente</button>
</form>
