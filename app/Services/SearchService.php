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
    private const PRIVATE_IP_RANGES = [
        ['0.0.0.0', '0.255.255.255'],
        ['10.0.0.0', '10.255.255.255'],
        ['100.64.0.0', '100.127.255.255'],
        ['127.0.0.0', '127.255.255.255'],
        ['169.254.0.0', '169.254.255.255'],
        ['172.16.0.0', '172.31.255.255'],
        ['192.0.0.0', '192.0.0.255'],
        ['192.0.2.0', '192.0.2.255'],
        ['192.168.0.0', '192.168.255.255'],
        ['198.18.0.0', '198.19.255.255'],
        ['198.51.100.0', '198.51.100.255'],
        ['203.0.113.0', '203.0.113.255'],
        ['224.0.0.0', '255.255.255.255'],
    ];

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

    public function resolveNavigableUrl(string $query): ?string
    {
        $candidate = trim($query);
        if ($candidate === '') {
            return null;
        }

        if (preg_match('/^[a-z][a-z0-9+\-.]*:/i', $candidate) === 1
            && stripos($candidate, 'http://') !== 0
            && stripos($candidate, 'https://') !== 0) {
            return null;
        }

        if (stripos($candidate, 'http://') === 0 || stripos($candidate, 'https://') === 0) {
            $url = $candidate;
        } else {
            $url = 'https://' . $candidate;
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower(rtrim((string) $parts['host'], '.'));
        if (!$this->isSafeHost($host)) {
            return null;
        }

        return 'https://' . $host;
    }

    private function isSafeHost(string $host): bool
    {
        if ($host === 'localhost') {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return !$this->isReservedOrPrivateIpv4($host);
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return !$this->isReservedOrPrivateIpv6($host);
        }

        if (strpos($host, '..') !== false) {
            return false;
        }

        return preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $host) === 1;
    }

    private function isReservedOrPrivateIpv4(string $ip): bool
    {
        $ipLong = ip2long($ip);
        if ($ipLong === false) {
            return true;
        }

        foreach (self::PRIVATE_IP_RANGES as [$start, $end]) {
            $startLong = ip2long($start);
            $endLong = ip2long($end);
            if ($startLong === false || $endLong === false) {
                continue;
            }
            if ($ipLong >= $startLong && $ipLong <= $endLong) {
                return true;
            }
        }

        return false;
    }

    private function isReservedOrPrivateIpv6(string $ip): bool
    {
        if ($ip === '::1') {
            return true;
        }

        if (str_starts_with($ip, 'fc') || str_starts_with($ip, 'fd')) {
            return true;
        }

        if (str_starts_with($ip, 'fe80:')) {
            return true;
        }

        return true;
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
