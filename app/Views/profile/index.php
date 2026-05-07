<?php

use Browser\Core\Csrf;
use Browser\Core\View;

$preferences = is_array($preferences ?? null) ? $preferences : [];
?>
<div class="card">
    <p class="muted">Mi cuenta</p>
    <h1>Perfil</h1>
    <div class="grid" style="margin-top: 16px; gap: 12px;">
        <div><strong>Nombre visible:</strong> <?= View::e($user['display_name'] ?? '-') ?></div>
        <div><strong>Username:</strong> <?= View::e($user['username'] ?? '-') ?></div>
        <div><strong>Email:</strong> <?= View::e($user['email'] ?? '-') ?></div>
        <div><strong>Estado de cuenta:</strong> <?= View::e($user['status'] ?? '-') ?></div>
        <div><strong>Fecha de creación:</strong> <?= View::e($user['created_at'] ?? '-') ?></div>
        <div><strong>Último login:</strong> <?= View::e($user['last_login_at'] ?? 'Sin registros') ?></div>
    </div>
</div>

<div class="card" style="margin-top: 20px;">
    <h2>Seguridad</h2>
    <p class="muted">Puedes revisar y revocar tus sesiones activas.</p>
    <a class="button secondary" href="/security/sessions">Gestionar sesiones</a>
</div>

<div class="card" style="margin-top: 20px;">
    <h2>Preferencias</h2>
    <form method="post" action="/profile" class="stack" style="gap: 12px;">
        <?= Csrf::field() ?>

        <label for="display_name">Nombre visible</label>
        <input id="display_name" name="display_name" type="text" maxlength="120" required value="<?= View::e((string) ($user['display_name'] ?? '')) ?>">

        <label for="timezone">Zona horaria</label>
        <input id="timezone" name="timezone" type="text" maxlength="60" required value="<?= View::e((string) ($preferences['timezone'] ?? 'America/Mexico_City')) ?>">

        <label for="language">Idioma</label>
        <select id="language" name="language">
            <?php foreach (($languages ?? []) as $language): ?>
                <option value="<?= View::e($language) ?>" <?= (($preferences['language'] ?? 'es') === $language) ? 'selected' : '' ?>>
                    <?= View::e(strtoupper($language)) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="theme">Tema</label>
        <select id="theme" name="theme">
            <?php foreach (($themes ?? []) as $theme): ?>
                <option value="<?= View::e($theme) ?>" <?= (($preferences['theme'] ?? 'system') === $theme) ? 'selected' : '' ?>>
                    <?= View::e(ucfirst($theme)) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>
            <input type="checkbox" name="search_history_enabled" value="1" <?= ((int) ($preferences['search_history_enabled'] ?? 0) === 1) ? 'checked' : '' ?>>
            Guardar historial de búsquedas
        </label>

        <label>
            <input type="checkbox" name="email_notifications_enabled" value="1" <?= ((int) ($preferences['email_notifications_enabled'] ?? 1) === 1) ? 'checked' : '' ?>>
            Recibir notificaciones por email
        </label>

        <button class="button" type="submit">Guardar cambios</button>
        <p class="muted">Por ahora no se puede cambiar email, username ni contraseña.</p>
    </form>
</div>
