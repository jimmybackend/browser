<?php

use Browser\Core\View;
?>
<div class="card">
    <h1>Buscador</h1>
    <form class="form" method="get" action="/search">
        <input class="input" type="search" name="q" value="<?= View::e($query ?? '') ?>" placeholder="Buscar..." autocomplete="off">
        <button class="button" type="submit">Buscar</button>
    </form>
</div>

<?php if (!empty($directNavigation)): ?>
    <section class="grid" style="margin-top: 24px;">
        <article class="card">
            <h2><?= View::e($directNavigation['title']) ?></h2>
            <p class="muted">URL: <?= View::e($directNavigation['url']) ?></p>
            <p class="muted">Dominio: <?= View::e($directNavigation['domain']) ?></p>
            <p><?= View::e($directNavigation['description']) ?></p>
            <p><a class="button" href="<?= View::e($directNavigation['url']) ?>" target="_blank" rel="noopener noreferrer">Abrir sitio</a></p>
        </article>
    </section>
<?php endif; ?>

<?php if (!empty($results)): ?>
    <section class="grid" style="margin-top: 24px;">
        <?php foreach ($results as $result): ?>
            <article class="card">
                <h2><?= View::e($result['title']) ?></h2>
                <p class="muted"><?= View::e($result['url']) ?></p>
                <p><?= View::e($result['description']) ?></p>
                <p class="muted">Señales internas: relevancia=<?= View::e((string)$result['relevance_signal']) ?>, confianza=<?= View::e((string)$result['trust_signal']) ?>, seguridad=<?= View::e((string)$result['safety_signal']) ?></p>
                <p class="muted">Etiquetas visibles: <?= View::e($result['relevance_label']) ?> / <?= View::e($result['trust_label']) ?> / <?= View::e($result['safety_label']) ?></p>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
