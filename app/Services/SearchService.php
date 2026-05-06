<?php

declare(strict_types=1);

namespace Browser\Services;

use Browser\Core\TernarySignal;

final class SearchService
{
    public function search(string $query): array
    {
        // Fase MVP: búsqueda placeholder.
        // Futuro: usar indexed_pages, FULLTEXT, crawler controlado y filtros de privacidad.
        if (trim($query) === '') {
            return [];
        }

        $relevanceSignal = TernarySignal::POSITIVE;
        $trustSignal = TernarySignal::NEUTRAL;
        $safetySignal = TernarySignal::POSITIVE;

        return [
            [
                'title' => 'Resultado inicial de Browser',
                'url' => 'https://example.com',
                'description' => 'Placeholder para que Codex implemente el buscador real sobre indexed_pages.',
                'relevance_signal' => $relevanceSignal,
                'trust_signal' => $trustSignal,
                'safety_signal' => $safetySignal,
                'relevance_label' => TernarySignal::label($relevanceSignal),
                'trust_label' => TernarySignal::label($trustSignal),
                'safety_label' => TernarySignal::label($safetySignal),
            ],
        ];
    }
}
