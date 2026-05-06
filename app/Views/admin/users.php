<div class="card">
    <h1>Usuarios</h1>
    <p class="muted">Mostrando hasta 50 usuarios recientes.</p>
</div>

<div class="card" style="margin-top: 16px; overflow-x: auto;">
    <table style="width:100%; border-collapse: collapse;">
        <thead>
            <tr>
                <th>ID</th><th>Username</th><th>Email</th><th>Display name</th><th>Status</th><th>Creado</th><th>Último login</th><th>Roles</th><th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $row): ?>
            <tr>
                <td><?= \Browser\Core\View::e((string) ($row['id'] ?? '')) ?></td>
                <td><?= \Browser\Core\View::e((string) ($row['username'] ?? '')) ?></td>
                <td><?= \Browser\Core\View::e((string) ($row['email'] ?? '')) ?></td>
                <td><?= \Browser\Core\View::e((string) ($row['display_name'] ?? '')) ?></td>
                <td><?= \Browser\Core\View::e((string) ($row['status'] ?? '')) ?></td>
                <td><?= \Browser\Core\View::e((string) ($row['created_at'] ?? '')) ?></td>
                <td><?= \Browser\Core\View::e((string) ($row['last_login_at'] ?? '')) ?></td>
                <td><?= \Browser\Core\View::e((string) ($row['roles'] ?? '')) ?></td>
                <td>
                    <a href="/admin/users/show?id=<?= \Browser\Core\View::e((string) ($row['id'] ?? '')) ?>">Ver</a>
                    |
                    <a href="/admin/users/roles?id=<?= \Browser\Core\View::e((string) ($row['id'] ?? '')) ?>">Roles</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
