<?php

declare(strict_types=1);

namespace Browser\Core;

final class Response
{
    public static function redirect(string $to): never
    {
        header('Location: ' . $to, true, 302);
        exit;
    }

    public static function notFound(): void
    {
        http_response_code(404);
        echo '404 - Página no encontrada';
    }

    public static function forbidden(): void
    {
        http_response_code(403);
        echo '403 - Acceso denegado';
    }
}
