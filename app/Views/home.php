<?php

use Browser\Core\View;

$query = $query ?? '';
$results = $results ?? [];
$suggestedPages = $suggestedPages ?? [];
$user = $user ?? null;
$isAdmin = $isAdmin ?? false;
?>

<section class="search-home card">
    <p class="eyebrow">Browser</p>
    <h1>Busca en Browser</h1>
    <?php if ($user): ?>
        <p class="muted">Hola, <?= View::e($user['display_name'] ?? $user['username'] ?? 'usuario') ?>.</p>
    <?php endif; ?>
    <form class="search-form-large" method="post" action="/search" aria-label="Formulario de búsqueda Browser">
        <?= Csrf::field() ?>
        <label for="home-search" class="sr-only">Buscar en Browser</label>
        <input id="home-search" class="input search-input-large" type="search" name="q" value="<?= View::e($query) ?>" placeholder="Buscar páginas, dominios o temas" autocomplete="off" required>
        <button class="button" type="submit">Buscar</button>
    </form>
</section>

<section class="card">
    <h2>Páginas sugeridas</h2>
    <?php if ($suggestedPages === []): ?>
        <p class="muted">Todavía no hay páginas indexadas.</p>
        <?php if ($isAdmin): ?>
            <p class="muted">Como administrador, puedes poblar <code>indexed_pages</code> para habilitar resultados reales en el buscador.</p>
        <?php endif; ?>
    <?php else: ?>
        <div class="grid" style="margin-top: 16px;">
            <?php foreach ($suggestedPages as $page): ?>
                <article class="card">
                    <h3 style="margin-top: 0;"><?= View::e($page['title'] ?: $page['domain']) ?></h3>
                    <p class="muted"><?= View::e($page['domain']) ?></p>
                    <p><?= View::e($page['description'] ?? '') ?></p>
                    <a href="<?= View::e($page['url']) ?>" target="_blank" rel="noopener noreferrer"><?= View::e($page['url']) ?></a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
