<?php

declare(strict_types=1);

namespace Browser\Middleware;

use Browser\Core\Auth;
use Browser\Core\Response;

final class AdminMiddleware
{
    public static function handle(): void
    {
        $user = Auth::user();

        // TODO: Validar roles desde user_roles cuando el módulo de permisos esté completo.
        if (!$user || ($user['username'] ?? '') !== 'admin') {
            Response::forbidden();
            exit;
        }
    }
}
