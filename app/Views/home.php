<?php

use Browser\Core\View;

$homeData = $homeData ?? [];
$heroTitle = $homeData['heroTitle'] ?? 'Browser';
$heroSubtitle = $homeData['heroSubtitle'] ?? '';
$primaryCta = $homeData['primaryCta'] ?? ['label' => 'Crear cuenta', 'url' => '/register'];
$secondaryCta = $homeData['secondaryCta'] ?? ['label' => 'Probar buscador', 'url' => '/search'];
?>

<section class="hero-section card">
    <p class="eyebrow">Plataforma independiente</p>
    <h1><?= View::e($heroTitle) ?></h1>
    <p class="hero-subtitle"><?= View::e($heroSubtitle) ?></p>

    <div class="hero-actions">
        <a class="button" href="<?= View::e($primaryCta['url']) ?>"><?= View::e($primaryCta['label']) ?></a>
        <a class="button secondary" href="<?= View::e($secondaryCta['url']) ?>"><?= View::e($secondaryCta['label']) ?></a>
    </div>

    <form class="search-form" method="get" action="/" aria-label="Formulario de búsqueda Browser">
        <label for="home-search" class="sr-only">Buscar en Browser</label>
        <input id="home-search" class="input search-input" type="search" name="q" placeholder="Buscar en Browser..." autocomplete="off" required>
        <button class="button" type="submit">Buscar</button>
    </form>
</section>

<section class="grid feature-grid" aria-label="Servicios Browser">
    <article class="card"><h2>Búsqueda independiente</h2><p class="muted">Explora resultados en una plataforma propia preparada para crecer por módulos.</p></article>
    <article class="card"><h2>Correo interno</h2><p class="muted">Base para mensajería interna y comunicación segura entre usuarios y equipos.</p></article>
    <article class="card"><h2>Privacidad y seguridad</h2><p class="muted">Diseño pensado para sesiones seguras, tokens y buenas prácticas de protección de datos.</p></article>
    <article class="card"><h2>Clientes de marketing</h2><p class="muted">Gestión centralizada de clientes para seguimiento de servicios y oportunidades.</p></article>
    <article class="card"><h2>Campañas</h2><p class="muted">Estructura para organizar campañas por objetivos, estado y resultados.</p></article>
    <article class="card"><h2>Leads</h2><p class="muted">Registro y evolución de leads con enfoque comercial y trazabilidad del proceso.</p></article>
</section>

<section class="card tech-stack" aria-label="Base técnica Browser">
    <h2>Base técnica del MVP</h2>
    <ul>
        <li>PHP 8.3</li>
        <li>MySQL / MariaDB</li>
        <li>PDO</li>
        <li>Docker</li>
        <li>Arquitectura MVC</li>
        <li>Preparado para AWS</li>
    </ul>
</section>
