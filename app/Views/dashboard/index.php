<?php

use Browser\Core\Csrf;
use Browser\Core\View;
?>
<div class="card">
    <p class="muted">Sesión activa</p>
    <h1>Dashboard</h1>
    <p>Bienvenido, <?= View::e($user['display_name'] ?? $user['username'] ?? 'usuario') ?>.</p>
    <ul>
        <li><strong>Nombre:</strong> <?= View::e($user['display_name'] ?? 'No definido') ?></li>
        <li><strong>Usuario:</strong> <?= View::e($user['username'] ?? '-') ?></li>
        <li><strong>Email:</strong> <?= View::e($user['email'] ?? '-') ?></li>
        <li><strong>Estado:</strong> <?= View::e($user['status'] ?? '-') ?></li>
    </ul>
    <form method="post" action="/logout" style="margin-top: 16px;">
        <?= Csrf::field() ?>
        <button class="button" type="submit">Cerrar sesión</button>
    </form>
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
    <a class="card" href="/dashboard#perfil">
        <h2>Perfil</h2>
        <p class="muted">Gestiona tus datos personales y preferencias.</p>
    </a>
</section>
