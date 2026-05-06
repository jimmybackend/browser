<?php use Browser\Core\Csrf; use Browser\Core\View; ?>
<div class="card"><h1>Nueva campaña</h1><p><a href="/marketing/campaigns">← Volver</a></p></div>
<form class="card" method="post" action="/marketing/campaigns/store" style="margin-top:16px;"><?= Csrf::field() ?>
<label>Cliente <select name="client_id" required><?php foreach ($clients as $client): ?><option value="<?= View::e((string)$client['id']) ?>"><?= View::e($client['company_name']) ?></option><?php endforeach; ?></select></label>
<label>Nombre <input name="name" required maxlength="190"></label>
<label>Canal <select name="channel" required><?php foreach (['seo','sem','social','email','content','display','other'] as $ch): ?><option value="<?= View::e($ch) ?>"><?= View::e($ch) ?></option><?php endforeach; ?></select></label>
<label>Presupuesto <input name="budget" type="number" min="0" step="0.01"></label>
<label>Inicio <input name="start_date" type="date"></label>
<label>Fin <input name="end_date" type="date"></label>
<label>Estado <select name="status" required><?php foreach (['draft','active','paused','completed','cancelled'] as $st): ?><option value="<?= View::e($st) ?>" <?= $st==='draft'?'selected':'' ?>><?= View::e($st) ?></option><?php endforeach; ?></select></label>
<label>Descripción <textarea name="description" rows="4" maxlength="5000"></textarea></label>
<button class="button" type="submit">Guardar campaña</button></form>
