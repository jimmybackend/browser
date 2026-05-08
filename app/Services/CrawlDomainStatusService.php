<?php

declare(strict_types=1);

namespace Browser\Services;

use PDO;

final class CrawlDomainStatusService
{
    public function __construct(private readonly ?PDO $pdo = null)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function summarize(int $limit = 20, ?string $domain = null, bool $withErrors = false): array
    {
        if ($this->pdo === null) {
            return [];
        }

        $safeLimit = max(1, min($limit, 200));
        $safeDomain = $this->normalizeDomain($domain);

        $domains = $this->fetchDomains($safeLimit, $safeDomain);
        if ($domains === []) {
            return [];
        }

        $domainNames = array_map(static fn (array $row): string => (string) $row['domain'], $domains);
        $indexedCounts = $this->fetchIndexedPagesCounts($domainNames);
        $errors = $withErrors ? $this->fetchRecentErrorsByDomain($domainNames) : [];

        $result = [];
        foreach ($domains as $row) {
            $domainName = (string) $row['domain'];
            $result[] = [
                'domain' => $domainName,
                'queued' => (int) ($row['queued'] ?? 0),
                'running' => (int) ($row['running'] ?? 0),
                'indexed' => (int) ($row['indexed'] ?? 0),
                'skipped' => (int) ($row['skipped'] ?? 0),
                'failed' => (int) ($row['failed'] ?? 0),
                'indexed_pages_count' => (int) ($indexedCounts[$domainName] ?? 0),
                'last_created_at' => $row['last_created_at'] ?? null,
                'last_updated_at' => $row['last_updated_at'] ?? null,
                'recent_errors' => $errors[$domainName] ?? [],
            ];
        }

        return $result;
    }

    private function normalizeDomain(?string $domain): ?string
    {
        if ($domain === null) {
            return null;
        }

        $trimmed = strtolower(trim($domain));

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchDomains(int $limit, ?string $domain): array
    {
        $sql = 'SELECT domain,
                    SUM(CASE WHEN status = :queuedStatus THEN 1 ELSE 0 END) AS queued,
                    SUM(CASE WHEN status = :runningStatus THEN 1 ELSE 0 END) AS running,
                    SUM(CASE WHEN status = :indexedStatus THEN 1 ELSE 0 END) AS indexed,
                    SUM(CASE WHEN status = :skippedStatus THEN 1 ELSE 0 END) AS skipped,
                    SUM(CASE WHEN status = :failedStatus THEN 1 ELSE 0 END) AS failed,
                    MAX(created_at) AS last_created_at,
                    MAX(updated_at) AS last_updated_at
                FROM crawl_urls';

        $params = [
            'queuedStatus' => 'queued',
            'runningStatus' => 'running',
            'indexedStatus' => 'indexed',
            'skippedStatus' => 'skipped',
            'failedStatus' => 'failed',
            'limit' => $limit,
        ];

        if ($domain !== null) {
            $sql .= ' WHERE domain = :domain';
            $params['domain'] = $domain;
        }

        $sql .= ' GROUP BY domain ORDER BY (queued + running + failed + indexed + skipped) DESC, last_updated_at DESC LIMIT :limit';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $name => $value) {
            if ($name === 'limit') {
                $stmt->bindValue(':' . $name, (int) $value, PDO::PARAM_INT);
                continue;
            }

            $stmt->bindValue(':' . $name, (string) $value, PDO::PARAM_STR);
        }

        $stmt->execute();

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }

    /** @param array<int, string> $domains @return array<string, int> */
    private function fetchIndexedPagesCounts(array $domains): array
    {
        if ($domains === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($domains), '?'));
        $sql = "SELECT domain, COUNT(*) AS total FROM indexed_pages WHERE domain IN ({$placeholders}) GROUP BY domain";
        $stmt = $this->pdo->prepare($sql);

        foreach ($domains as $index => $domain) {
            $stmt->bindValue($index + 1, $domain, PDO::PARAM_STR);
        }

        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['domain']] = (int) $row['total'];
        }

        return $counts;
    }

    /** @param array<int, string> $domains @return array<string, array<int, string>> */
    private function fetchRecentErrorsByDomain(array $domains): array
    {
        if ($domains === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($domains), '?'));
        $sql = "SELECT domain, http_status, error_message, updated_at
                FROM crawl_urls
                WHERE domain IN ({$placeholders}) AND (
                    status = 'failed' OR
                    (http_status IS NOT NULL AND http_status >= 400) OR
                    (error_message IS NOT NULL AND error_message <> '')
                )
                ORDER BY updated_at DESC, id DESC
                LIMIT 100";

        $stmt = $this->pdo->prepare($sql);
        foreach ($domains as $index => $domain) {
            $stmt->bindValue($index + 1, $domain, PDO::PARAM_STR);
        }

        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($rows as $row) {
            $domain = (string) ($row['domain'] ?? '');
            if ($domain === '') {
                continue;
            }

            if (!isset($grouped[$domain])) {
                $grouped[$domain] = [];
            }

            if (count($grouped[$domain]) >= 3) {
                continue;
            }

            $grouped[$domain][] = sprintf(
                'http=%s err=%s at=%s',
                (string) ($row['http_status'] ?? '-'),
                (string) ($row['error_message'] ?? '-'),
                (string) ($row['updated_at'] ?? '-')
            );
        }

        return $grouped;
    }
}
