<?php

declare(strict_types=1);

namespace Browser\Services;

final class SearchService
{
    public function search(string $query): array
    {
        // Fase MVP: búsqueda placeholder.
        // Futuro: usar indexed_pages, FULLTEXT, crawler controlado y filtros de privacidad.
        if (trim($query) === '') {
            return [];
        }

        return [
            [
                'title' => 'Resultado inicial de Browser',
                'url' => 'https://example.com',
                'description' => 'Placeholder para que Codex implemente el buscador real sobre indexed_pages.',
            ],
        ];
    }
}
