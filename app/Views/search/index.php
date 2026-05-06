<?php

use Browser\Core\View;

$query = $query ?? '';
$results = $results ?? [];
?>
<div class="search-home card">
    <h1>Buscador Browser</h1>
    <form class="search-form-large" method="post" action="/search">
        <?= Csrf::field() ?>
        <input class="input search-input-large" type="search" name="q" value="<?= View::e($query) ?>" placeholder="Buscar..." autocomplete="off" required>
        <button class="button" type="submit">Buscar</button>
    </form>
</div>

<?php if ($query !== ''): ?>
    <section class="card">
        <p class="muted">Resultados para: <strong><?= View::e($query) ?></strong></p>

        <?php if ($results === []): ?>
            <p>No se encontraron resultados indexados para esta búsqueda.</p>
        <?php else: ?>
            <div class="grid" style="margin-top: 16px;">
                <?php foreach ($results as $result): ?>
                    <article class="card">
                        <h2><?= View::e($result['title'] ?: $result['domain']) ?></h2>
                        <p class="muted"><?= View::e($result['domain']) ?></p>
                        <a href="<?= View::e($result['url']) ?>" target="_blank" rel="noopener noreferrer"><?= View::e($result['url']) ?></a>
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
