<?php

use Browser\Core\Csrf;
use Browser\Core\View;
?>
<div class="card">
    <h1>Buscador</h1>
    <form class="form" method="post" action="/search">
        <?= Csrf::field() ?>
        <input class="input" type="search" name="q" value="<?= View::e($query ?? '') ?>" placeholder="Buscar..." autocomplete="off">
        <button class="button" type="submit">Buscar</button>
    </form>
</div>

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
