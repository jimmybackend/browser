<?php

use Browser\Core\View;
?>
<div class="card">
    <h1>Marketing</h1>
    <p class="muted">Base para la empresa de marketing: clientes, campañas, leads y eventos.</p>
</div>

<section class="grid" style="margin-top: 24px;">
    <?php foreach ($cards as $title => $description): ?>
        <article class="card">
            <h2><?= View::e($title) ?></h2>
            <p><?= View::e($description) ?></p>
        </article>
    <?php endforeach; ?>
</section>


<div class="card" style="margin-top: 24px;">
    <h2>Señal ternaria de ejemplo (lead)</h2>
    <p><strong>Clave:</strong> <?= View::e($leadQualityExample['signal_key']) ?></p>
    <p><strong>Valor interno:</strong> <?= View::e((string)$leadQualityExample['signal_value']) ?></p>
    <p><strong>Etiqueta visible:</strong> <?= View::e($leadQualityExample['signal_label']) ?></p>
    <p class="muted"><?= View::e($leadQualityExample['human_hint']) ?></p>
</div>
