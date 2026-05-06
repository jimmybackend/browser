<?php

declare(strict_types=1);

namespace Browser\Core;

final class View
{
    public static function render(string $view, array $params = []): void
    {
        $viewPath = BASE_PATH . '/app/Views/' . $view . '.php';

        if (!file_exists($viewPath)) {
            Response::notFound();
            return;
        }

        extract($params, EXTR_SKIP);

        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        require BASE_PATH . '/app/Views/layout.php';
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}
