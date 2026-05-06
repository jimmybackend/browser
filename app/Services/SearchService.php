<?php

declare(strict_types=1);

namespace Browser\Services;

use Browser\Core\Auth;
use Browser\Core\Database;
use Browser\Core\Request;
use Browser\Core\TernarySignal;
use PDO;

final class SearchService
{
    public function search(string $query, ?Request $request = null): array
    {
        $cleanQuery = trim($query);
        if ($cleanQuery == '') {
            return [
                'directNavigation' => null,
                'results' => [],
                'resultsCount' => 0,
            ];
        }

        $directNavigation = $this->detectDirectNavigation($cleanQuery);
        $indexedResults = $this->searchIndexedPages($cleanQuery);

        $resultsCount = count($indexedResults) + ($directNavigation !== null ? 1 : 0);
        $this->logSearchQuery($cleanQuery, $resultsCount, $request);

        return [
            'directNavigation' => $directNavigation,
            'results' => $indexedResults,
            'resultsCount' => $resultsCount,
        ];
    }

    public function detectDirectNavigation(string $query): ?array
    {
        $candidate = trim($query);
        if ($candidate === '') {
            return null;
        }

        if (preg_match('/^[a-z][a-z0-9+\-.]*:/i', $candidate) === 1 && stripos($candidate, 'http://') !== 0 && stripos($candidate, 'https://') !== 0) {
            return null;
        }

        $rawUrl = $candidate;
        if (!str_contains($candidate, '://')) {
            $rawUrl = 'https://' . $candidate;
        }

        $normalizedUrl = filter_var($rawUrl, FILTER_VALIDATE_URL);
        if (!is_string($normalizedUrl)) {
            return null;
        }

        $parts = parse_url($normalizedUrl);
        if (!is_array($parts)) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if ($scheme !== 'http' && $scheme !== 'https') {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || !str_contains($host, '.')) {
            return null;
        }

        if ($this->isBlockedHost($host)) {
            return null;
        }

        $safeUrl = $scheme . '://' . $host;
        if (isset($parts['port']) && is_int($parts['port'])) {
            $safeUrl .= ':' . $parts['port'];
        }
        $path = (string) ($parts['path'] ?? '');
        if ($path !== '') {
            $safeUrl .= $path;
        }
        $queryString = (string) ($parts['query'] ?? '');
        if ($queryString !== '') {
            $safeUrl .= '?' . $queryString;
        }
        $fragment = (string) ($parts['fragment'] ?? '');
        if ($fragment !== '') {
            $safeUrl .= '#' . $fragment;
        }

        return [
            'type' => 'direct_navigation',
            'title' => 'Ir a ' . $host,
            'url' => $safeUrl,
            'domain' => $host,
            'description' => 'Abrir este sitio directamente.',
        ];
    }

    private function searchIndexedPages(string $query): array
    {
        $statement = Database::connection()->prepare(
            'SELECT title, url, description
             FROM indexed_pages
             WHERE status = :status
               AND MATCH(title, description, content_text) AGAINST (:query IN NATURAL LANGUAGE MODE)
             ORDER BY last_crawled_at DESC, id DESC
             LIMIT 20'
        );

        $statement->execute([
            'status' => 'indexed',
            'query' => $query,
        ]);

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($rows as $row) {
            $relevanceSignal = TernarySignal::POSITIVE;
            $trustSignal = TernarySignal::NEUTRAL;
            $safetySignal = TernarySignal::POSITIVE;

            $results[] = [
                'title' => (string) ($row['title'] ?? $row['url'] ?? 'Sin título'),
                'url' => (string) ($row['url'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                'relevance_signal' => $relevanceSignal,
                'trust_signal' => $trustSignal,
                'safety_signal' => $safetySignal,
                'relevance_label' => TernarySignal::label($relevanceSignal),
                'trust_label' => TernarySignal::label($trustSignal),
                'safety_label' => TernarySignal::label($safetySignal),
            ];
        }

        return $results;
    }

    private function logSearchQuery(string $query, int $resultsCount, ?Request $request): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO search_queries (user_id, query_hash, results_count, ip_address)
             VALUES (:user_id, :query_hash, :results_count, :ip_address)'
        );

        $ipAddress = null;
        if ($request !== null) {
            $ipAddress = @inet_pton($request->ip()) ?: null;
        }

        $statement->execute([
            'user_id' => Auth::id(),
            'query_hash' => hash('sha256', mb_strtolower($query)),
            'results_count' => $resultsCount,
            'ip_address' => $ipAddress,
        ]);
    }

    private function isBlockedHost(string $host): bool
    {
        if ($host === 'localhost') {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        if (in_array($host, ['127.0.0.1', '0.0.0.0', '::1'], true)) {
            return true;
        }

        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
