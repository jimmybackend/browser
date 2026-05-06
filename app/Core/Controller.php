<?php

declare(strict_types=1);

namespace Browser\Core;

abstract class Controller
{
    protected function view(string $view, array $params = []): void
    {
        View::render($view, $params);
    }

    protected function requireAuth(): void
    {
        if (!Auth::check()) {
            Response::redirect('/login');
        }
    }
}
