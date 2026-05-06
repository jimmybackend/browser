<?php

use Browser\Core\View;

$query = $query ?? '';
$results = $results ?? [];
$suggestedPages = $suggestedPages ?? [];

$manualSuggestions = [
    ['domain' => 'google.com', 'url' => 'https://google.com', 'title' => 'Google'],
    ['domain' => 'chatgpt.com', 'url' => 'https://chatgpt.com', 'title' => 'ChatGPT'],
    ['domain' => 'esforzados.com', 'url' => 'https://esforzados.com', 'title' => 'Esforzados'],
    ['domain' => 'youtube.com', 'url' => 'https://youtube.com', 'title' => 'YouTube'],
    ['domain' => 'wikipedia.org', 'url' => 'https://wikipedia.org', 'title' => 'Wikipedia'],
];

$displaySuggestions = $suggestedPages === [] ? $manualSuggestions : $suggestedPages;

$normalized = strtolower(trim($query));
$normalized = preg_replace('#^https?://#', '', $normalized) ?? '';
$normalized = preg_replace('#/.*$#', '', $normalized) ?? '';
$isDomainQuery = $normalized !== '' && (bool) preg_match('/^(?:[a-z0-9-]+\.)+[a-z]{2,}$/i', $normalized);
$directUrl = $isDomainQuery ? 'https://' . $normalized : '';
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

        <?php if ($isDomainQuery): ?>
            <article class="card" style="margin: 12px 0 16px;">
                <h2 style="margin-top:0;">Abrir dominio directamente</h2>
                <p class="muted"><?= View::e($normalized) ?></p>
                <a href="<?= View::e($directUrl) ?>" target="_blank" rel="noopener noreferrer"><?= View::e($directUrl) ?></a>
            </article>
        <?php endif; ?>

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
            <p class="muted">No hay páginas en <code>indexed_pages</code>. Mostramos sugerencias manuales seguras.</p>
        <?php endif; ?>
        <ul>
            <?php foreach ($displaySuggestions as $page): ?>
                <li><a href="<?= View::e($page['url']) ?>" target="_blank" rel="noopener noreferrer"><?= View::e($page['title'] ?: $page['domain']) ?></a> <span class="muted">(<?= View::e($page['domain']) ?>)</span></li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
