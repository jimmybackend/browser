<?php

use Browser\Core\View;
?>
<div class="card">
    <p class="muted">Sesión activa</p>
    <h1>Dashboard</h1>
    <p>Bienvenido, <?= View::e($user['display_name'] ?? $user['username'] ?? 'usuario') ?>.</p>
</div>

<section class="grid" style="margin-top: 24px;">
    <a class="card" href="/mail">
        <h2>Correo</h2>
        <p class="muted">Bandeja placeholder para preparar el servicio de correo.</p>
    </a>
    <a class="card" href="/search">
        <h2>Buscador</h2>
        <p class="muted">Búsqueda inicial para evolucionar al índice propio.</p>
    </a>
    <a class="card" href="/marketing">
        <h2>Marketing</h2>
        <p class="muted">Clientes, campañas, leads y eventos.</p>
    </a>
</section>
