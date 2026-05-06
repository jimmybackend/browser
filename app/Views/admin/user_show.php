<div class="card">
    <h1>Detalle de usuario #<?= \Browser\Core\View::e((string) ($user['id'] ?? '')) ?></h1>
    <p><strong>Username:</strong> <?= \Browser\Core\View::e((string) ($user['username'] ?? '')) ?></p>
    <p><strong>Email:</strong> <?= \Browser\Core\View::e((string) ($user['email'] ?? '')) ?></p>
    <p><strong>Display name:</strong> <?= \Browser\Core\View::e((string) ($user['display_name'] ?? '')) ?></p>
    <p><strong>Status:</strong> <?= \Browser\Core\View::e((string) ($user['status'] ?? '')) ?></p>
    <p><strong>Creado:</strong> <?= \Browser\Core\View::e((string) ($user['created_at'] ?? '')) ?></p>
    <p><strong>Último login:</strong> <?= \Browser\Core\View::e((string) ($user['last_login_at'] ?? '')) ?></p>
    <p><strong>Roles:</strong>
        <?php foreach (($user['roles'] ?? []) as $role): ?>
            <span><?= \Browser\Core\View::e((string) ($role['name'] ?? '')) ?></span>
        <?php endforeach; ?>
    </p>
    <p><a href="/admin/users/roles?id=<?= \Browser\Core\View::e((string) ($user['id'] ?? '')) ?>">Administrar roles</a></p>
</div>
