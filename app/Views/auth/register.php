<?php

use Browser\Core\Csrf;
?>
<div class="card">
    <h1>Crear cuenta</h1>
    <form class="form" method="post" action="/register">
        <?= Csrf::field() ?>

        <label>
            Nombre visible
            <input class="input" type="text" name="display_name" maxlength="120">
        </label>

        <label>
            Usuario
            <input class="input" type="text" name="username" required maxlength="60">
        </label>

        <label>
            Correo
            <input class="input" type="email" name="email" required maxlength="190">
        </label>

        <label>
            Contraseña
            <input class="input" type="password" name="password" required minlength="12">
        </label>

        <button class="button" type="submit">Crear cuenta</button>
    </form>
</div>
