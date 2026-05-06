<?php

declare(strict_types=1);

namespace Browser\Middleware;

use Browser\Core\Csrf;
use Browser\Core\Response;
use Browser\Core\Session;

final class CsrfMiddleware
{
    public static function handle(?string $token): void
    {
        if (!Csrf::validate($token)) {
            Session::flash('error', 'Token CSRF inválido. Intenta de nuevo.');
            Response::redirect('/login');
        }
    }
}
