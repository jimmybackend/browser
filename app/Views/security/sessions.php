<?php

use Browser\Core\Csrf;
use Browser\Core\View;

/** @var array<int, array<string, mixed>> $sessions */
$sessions = is_array($sessions ?? null) ? $sessions : [];
$currentSessionTokenHash = (string) ($currentSessionTokenHash ?? '');

$formatIpAddress = static function ($ipBinary): string {
    if (!is_string($ipBinary) || $ipBinary === '') {
        return '-';
    }

    $decoded = inet_ntop($ipBinary);

    return $decoded === false ? '-' : $decoded;
};
?>

<div class="card">
    <h1>Seguridad de sesiones</h1>
    <p class="muted">Administra tus sesiones activas sin exponer tokens de sesión.</p>

    <form method="post" action="/security/sessions/revoke-others" style="margin: 12px 0 18px 0;">
        <?= Csrf::field() ?>
        <button class="button secondary" type="submit">Cerrar otras sesiones</button>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>User agent</th>
                <th>IP</th>
                <th>Creada</th>
                <th>Expira</th>
                <th>Estado</th>
                <th>Acción</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($sessions as $session): ?>
                <?php
                $isRevoked = !empty($session['revoked_at']);
                $isCurrent = (string) ($session['session_fingerprint'] ?? '') === $currentSessionTokenHash;
                ?>
                <tr>
                    <td><?= View::e((string) ($session['user_agent'] ?? '-')) ?></td>
                    <td><?= View::e($formatIpAddress($session['ip_address'] ?? null)) ?></td>
                    <td><?= View::e((string) ($session['created_at'] ?? '-')) ?></td>
                    <td><?= View::e((string) ($session['expires_at'] ?? '-')) ?></td>
                    <td>
                        <?php if ($isRevoked): ?>
                            Revocada
                        <?php elseif ($isCurrent): ?>
                            Actual
                        <?php else: ?>
                            Activa
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!$isRevoked): ?>
                            <form method="post" action="/security/sessions/revoke">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="session_id" value="<?= (int) ($session['id'] ?? 0) ?>">
                                <button class="button secondary" type="submit">Revocar</button>
                            </form>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
