<?php

use Browser\Core\Csrf;
use Browser\Core\View;

$query = $query ?? '';
$results = $results ?? [];
?>
<div class="search-home card">
    <p class="eyebrow">Browser</p>
    <h1>Busca en Browser</h1>
    <form class="search-form-large" method="post" action="/search" aria-label="Formulario de búsqueda Browser">
        <?= Csrf::field() ?>
        <label for="search-q" class="sr-only">Buscar en Browser</label>
        <input id="search-q" class="input search-input-large" type="search" name="q" value="<?= View::e($query) ?>" placeholder="Escribe una búsqueda o dominio" autocomplete="off" required>
        <button class="button" type="submit">Buscar</button>
    </form>
</div>

<?php if ($query !== ''): ?>
    <section class="card">
        <p class="muted">Resultados para: <strong><?= View::e($query) ?></strong></p>

        <?php if ($results === []): ?>
            <p>No se encontraron resultados.</p>
        <?php else: ?>
            <div class="grid" style="margin-top: 16px;">
                <?php foreach ($results as $result): ?>
                    <article class="card">
                        <h2><?= View::e($result['title'] ?: $result['domain']) ?></h2>
                        <p class="muted"><?= View::e($result['domain']) ?></p>
                        <a href="<?= View::e($result['url']) ?>" rel="noopener noreferrer"><?= View::e($result['url']) ?></a>
                        <p><?= View::e($result['description'] ?? '') ?></p>
                        <?php if (!empty($result['last_crawled_at'])): ?>
                            <p class="muted">Último rastreo: <?= View::e((string) $result['last_crawled_at']) ?></p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
