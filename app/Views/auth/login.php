<?php

use Browser\Core\Csrf;
?>
<div class="card">
    <h1>Iniciar sesión</h1>
    <form class="form" method="post" action="/login">
        <?= Csrf::field() ?>

        <label>
            Correo
            <input class="input" type="email" name="email" required maxlength="190">
        </label>

        <label>
            Contraseña
            <input class="input" type="password" name="password" required>
        </label>

        <button class="button" type="submit">Entrar</button>
    </form>
</div>
