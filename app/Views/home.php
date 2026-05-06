<?php

use Browser\Core\Csrf;
?>
<section class="card">
    <p class="muted">MVP inicial</p>
    <h1>Browser</h1>
    <p>
        Plataforma web independiente para búsqueda, correo, privacidad y herramientas de marketing digital.
    </p>

    <form class="form" method="post" action="/search">
        <?= Csrf::field() ?>
        <input class="input" type="search" name="q" placeholder="Buscar en Browser..." autocomplete="off">
        <button class="button" type="submit">Buscar</button>
    </form>
</section>

<section class="grid" style="margin-top: 24px;">
    <div class="card">
        <h2>Privacidad</h2>
        <p class="muted">Diseño preparado para historial opcional, tokens seguros y auditoría.</p>
    </div>
    <div class="card">
        <h2>Correo</h2>
        <p class="muted">Base para buzones, dominios, carpetas, mensajes, adjuntos y alias.</p>
    </div>
    <div class="card">
        <h2>Marketing</h2>
        <p class="muted">Clientes, campañas, leads y eventos para seguimiento comercial.</p>
    </div>
</section>
