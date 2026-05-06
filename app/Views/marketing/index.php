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
