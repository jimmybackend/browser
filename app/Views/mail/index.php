<?php

use Browser\Core\View;
?>
<div class="card">
    <h1>Correo</h1>
    <p class="muted">Módulo placeholder. En fases futuras conectará con correo real.</p>
</div>

<section class="grid" style="margin-top: 24px;">
    <?php foreach ($messages as $message): ?>
        <article class="card">
            <p class="muted"><?= View::e($message['from']) ?></p>
            <h2><?= View::e($message['subject']) ?></h2>
            <p><?= View::e($message['preview']) ?></p>
        </article>
    <?php endforeach; ?>
</section>
