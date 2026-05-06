<?php use Browser\Core\Csrf; use Browser\Core\View; ?>
<div class="card"><h1>Editar campaña</h1><p><a href="/marketing/campaigns/show?id=<?= View::e((string)$campaign['id']) ?>">← Volver al detalle</a></p></div>
<form class="card" method="post" action="/marketing/campaigns/update" style="margin-top:16px;"><?= Csrf::field() ?><input type="hidden" name="id" value="<?= View::e((string)$campaign['id']) ?>">
<label>Cliente <select name="client_id" required><?php foreach ($clients as $client): ?><option value="<?= View::e((string)$client['id']) ?>" <?= (int)$campaign['client_id']===(int)$client['id']?'selected':'' ?>><?= View::e($client['company_name']) ?></option><?php endforeach; ?></select></label>
<label>Nombre <input name="name" required maxlength="190" value="<?= View::e($campaign['name']) ?>"></label>
<label>Canal <select name="channel" required><?php foreach ($channels as $ch): ?><option value="<?= View::e($ch) ?>" <?= $campaign['channel']===$ch?'selected':'' ?>><?= View::e($ch) ?></option><?php endforeach; ?></select></label>
<label>Presupuesto <input name="budget" type="number" min="0" step="0.01" value="<?= View::e((string)($campaign['budget'] ?? '')) ?>"></label>
<label>Inicio <input name="start_date" type="date" value="<?= View::e((string)($campaign['start_date'] ?? '')) ?>"></label>
<label>Fin <input name="end_date" type="date" value="<?= View::e((string)($campaign['end_date'] ?? '')) ?>"></label>
<label>Estado <select name="status" required><?php foreach (['draft','active','paused','completed','cancelled'] as $st): ?><option value="<?= View::e($st) ?>" <?= $campaign['status']===$st?'selected':'' ?>><?= View::e($st) ?></option><?php endforeach; ?></select></label>
<label>Descripción <textarea name="description" rows="4" maxlength="5000"><?= View::e((string)$campaign['description']) ?></textarea></label>
<button class="button" type="submit">Guardar cambios</button></form>
