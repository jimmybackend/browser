<div class="card">
    <h1>Roles de usuario #<?= \Browser\Core\View::e((string) ($user['id'] ?? '')) ?></h1>
    <p class="muted"><?= \Browser\Core\View::e((string) ($user['username'] ?? '')) ?> (<?= \Browser\Core\View::e((string) ($user['email'] ?? '')) ?>)</p>

    <?php if (!empty($error)): ?>
        <p style="color: #b42318;"><?= \Browser\Core\View::e((string) $error) ?></p>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <p style="color: #067647;"><?= \Browser\Core\View::e((string) $success) ?></p>
    <?php endif; ?>

    <h2>Roles actuales</h2>
    <ul>
        <?php foreach (($user['roles'] ?? []) as $assignedRole): ?>
            <li>
                <?= \Browser\Core\View::e((string) ($assignedRole['name'] ?? '')) ?>
                <form method="post" action="/admin/users/roles" style="display:inline; margin-left:8px;">
                    <input type="hidden" name="_csrf_token" value="<?= \Browser\Core\View::e((string) ($csrfToken ?? '')) ?>">
                    <input type="hidden" name="user_id" value="<?= \Browser\Core\View::e((string) ($user['id'] ?? '')) ?>">
                    <input type="hidden" name="role_id" value="<?= \Browser\Core\View::e((string) ($assignedRole['id'] ?? '')) ?>">
                    <input type="hidden" name="action" value="remove">
                    <button type="submit">Quitar</button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>

    <h2>Asignar rol</h2>
    <form method="post" action="/admin/users/roles">
        <input type="hidden" name="_csrf_token" value="<?= \Browser\Core\View::e((string) ($csrfToken ?? '')) ?>">
        <input type="hidden" name="user_id" value="<?= \Browser\Core\View::e((string) ($user['id'] ?? '')) ?>">
        <input type="hidden" name="action" value="assign">

        <label for="role_id">Rol</label>
        <select id="role_id" name="role_id" required>
            <?php foreach ($roles as $role): ?>
                <option value="<?= \Browser\Core\View::e((string) ($role['id'] ?? '')) ?>"><?= \Browser\Core\View::e((string) ($role['name'] ?? '')) ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Asignar</button>
    </form>
</div>
