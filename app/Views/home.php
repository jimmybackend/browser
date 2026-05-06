<?php

use Browser\Core\Csrf;
use Browser\Core\View;

$query = $query ?? '';
$user = $user ?? null;
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
        <input id="home-search" class="input search-input-large" type="search" name="q" value="<?= View::e($query) ?>" placeholder="Escribe una búsqueda o dominio" autocomplete="off" required>
        <button class="button" type="submit">Buscar</button>
    </form>
</section>
