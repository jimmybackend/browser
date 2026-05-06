<?php

declare(strict_types=1);

namespace Browser\Services;

use Browser\Core\Auth;
use Browser\Core\Database;
use Browser\Core\TernarySignal;
use PDO;

final class SearchService
{
    private const MAX_QUERY_LENGTH = 160;
    private const RESULT_LIMIT = 20;
    private const SUGGESTED_LIMIT = 8;

    public function search(string $query, ?int $userId = null, ?string $ipAddress = null): array
    {
        $normalizedQuery = $this->normalizeQuery($query);

        if ($normalizedQuery === '') {
            return [];
        }

        $results = $this->runFulltextSearch($normalizedQuery);

        if ($results === []) {
            $results = $this->runLikeFallbackSearch($normalizedQuery);
        }

        $this->logQuery($normalizedQuery, count($results), $userId ?? Auth::id(), $ipAddress);

        return array_map(fn (array $result): array => $this->mapSignals($result), $results);
    }

    public function suggestedPages(int $limit = self::SUGGESTED_LIMIT): array
    {
        $statement = Database::connection()->prepare(
            'SELECT url, domain, title, description, last_crawled_at
             FROM indexed_pages
             WHERE status = :status
             ORDER BY COALESCE(last_crawled_at, updated_at, created_at) DESC
             LIMIT :limit'
        );
        $statement->bindValue(':status', 'indexed');
        $statement->bindValue(':limit', max(1, min($limit, self::RESULT_LIMIT)), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function normalizeQuery(string $query): string
    {
        $query = trim(preg_replace('/\s+/', ' ', $query) ?? '');

        if ($query === '') {
            return '';
        }

        return mb_substr($query, 0, self::MAX_QUERY_LENGTH);
    }

    private function runFulltextSearch(string $query): array
    {
        $statement = Database::connection()->prepare(
            'SELECT url, domain, title, description, last_crawled_at,
                    MATCH(title, description, content_text) AGAINST (:query IN NATURAL LANGUAGE MODE) AS score
             FROM indexed_pages
             WHERE status = :status
               AND MATCH(title, description, content_text) AGAINST (:query_against IN NATURAL LANGUAGE MODE)
             ORDER BY score DESC, COALESCE(last_crawled_at, updated_at, created_at) DESC
             LIMIT :limit'
        );
        $statement->bindValue(':query', $query);
        $statement->bindValue(':query_against', $query);
        $statement->bindValue(':status', 'indexed');
        $statement->bindValue(':limit', self::RESULT_LIMIT, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function runLikeFallbackSearch(string $query): array
    {
        $statement = Database::connection()->prepare(
            'SELECT url, domain, title, description, last_crawled_at, 0.0 AS score
             FROM indexed_pages
             WHERE status = :status
               AND (
                    title LIKE :q
                    OR description LIKE :q
                    OR domain LIKE :q
                    OR url LIKE :q
               )
             ORDER BY COALESCE(last_crawled_at, updated_at, created_at) DESC
             LIMIT :limit'
        );

        $likeTerm = '%' . $query . '%';
        $statement->bindValue(':status', 'indexed');
        $statement->bindValue(':q', $likeTerm);
        $statement->bindValue(':limit', self::RESULT_LIMIT, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function logQuery(string $query, int $resultsCount, ?int $userId, ?string $ipAddress): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO search_queries (user_id, query_hash, query_text_encrypted, results_count, ip_address)
             VALUES (:user_id, :query_hash, :query_text_encrypted, :results_count, :ip_address)'
        );

        $statement->bindValue(':user_id', $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':query_hash', hash('sha256', $query));
        $statement->bindValue(':query_text_encrypted', null, PDO::PARAM_NULL);
        $statement->bindValue(':results_count', max(0, $resultsCount), PDO::PARAM_INT);
        $statement->bindValue(':ip_address', $ipAddress !== null ? mb_substr($ipAddress, 0, 45) : null, $ipAddress === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->execute();
    }

    private function mapSignals(array $result): array
    {
        $relevanceSignal = TernarySignal::POSITIVE;
        $trustSignal = TernarySignal::NEUTRAL;
        $safetySignal = TernarySignal::POSITIVE;

        $result['search_relevance'] = $relevanceSignal;
        $result['trust_score'] = $trustSignal;
        $result['content_safety'] = $safetySignal;
        $result['relevance_label'] = TernarySignal::label($relevanceSignal);
        $result['trust_label'] = TernarySignal::label($trustSignal);
        $result['safety_label'] = TernarySignal::label($safetySignal);

        return $result;
    }
}
