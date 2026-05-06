<?php

return [
    'name' => $_ENV['APP_NAME'] ?? 'Browser',
    'env' => $_ENV['APP_ENV'] ?? 'local',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL),
    'url' => $_ENV['APP_URL'] ?? 'http://localhost:8080',
    'description' => $_ENV['APP_DESCRIPTION'] ?? 'Plataforma independiente de búsqueda, correo y marketing digital',
];
