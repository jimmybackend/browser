<?php

use Browser\Core\View;

$query = $query ?? '';
$results = $results ?? [];
$suggestedPages = $suggestedPages ?? [];
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
            <p>No se encontraron resultados para esta búsqueda.</p>
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
                        <p class="muted">search_relevance=<?= View::e((string) $result['search_relevance']) ?>, trust_score=<?= View::e((string) $result['trust_score']) ?>, content_safety=<?= View::e((string) $result['content_safety']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php else: ?>
    <section class="card">
        <h2>Explorar</h2>
        <?php if ($suggestedPages === []): ?>
            <p class="muted">Todavía no hay páginas indexadas.</p>
        <?php else: ?>
            <ul>
                <?php foreach ($suggestedPages as $page): ?>
                    <li><a href="<?= View::e($page['url']) ?>" target="_blank" rel="noopener noreferrer"><?= View::e($page['title'] ?: $page['domain']) ?></a> <span class="muted">(<?= View::e($page['domain']) ?>)</span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
<?php endif; ?>
