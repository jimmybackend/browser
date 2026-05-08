<?php

declare(strict_types=1);

namespace Browser\Services;

use PDO;

final class CrawlOperationalReportService
{
    public function __construct(
        private readonly ?PDO $pdo = null,
        private readonly ?CrawlDomainStatusService $domainStatusService = null,
        private readonly ?CrawlDomainAdviceService $domainAdviceService = null,
        private readonly ?CrawlDomainPolicyService $domainPolicyService = null,
    ) {
    }

    /** @return array<string,mixed> */
    public function build(?string $domain = null, int $limit = 10): array
    {
        $safeLimit = max(1, min($limit, 200));
        $safeDomain = $this->normalizeDomain($domain);

        $jobs = $this->jobSummary($safeDomain);
        $urls = $this->urlSummary($safeDomain);
        $indexedTotal = $this->indexedPagesTotal($safeDomain);

        $domains = $this->domainStatusService?->summarize($safeLimit, $safeDomain, true) ?? [];
        $paused = $this->pausedDomains($safeDomain, $safeLimit);
        $advice = $this->domainAdviceService?->recommend(5, $safeLimit, $safeDomain) ?? [];
        $recentErrors = $this->recentErrors($safeDomain, $safeLimit);

        return [
            'filters' => ['domain' => $safeDomain, 'limit' => $safeLimit],
            'jobs' => $jobs,
            'urls' => $urls,
            'indexed_pages' => ['total' => $indexedTotal],
            'domains_top_queue' => array_map(static fn (array $row): array => [
                'domain' => (string) ($row['domain'] ?? ''),
                'queued' => (int) ($row['queued'] ?? 0),
                'running' => (int) ($row['running'] ?? 0),
                'failed' => (int) ($row['failed'] ?? 0),
            ], $domains),
            'paused_domains' => $paused,
            'domain_advice' => $advice,
            'recent_errors' => $recentErrors,
            'has_data' => $this->hasData($jobs, $urls, $indexedTotal, $domains, $paused, $advice, $recentErrors),
        ];
    }

    private function normalizeDomain(?string $domain): ?string
    {
        if ($domain === null) {
            return null;
        }

        $clean = strtolower(trim($domain));
        return $clean === '' ? null : $clean;
    }

    /** @return array<string,int> */
    private function jobSummary(?string $domain): array
    {
        if ($this->pdo === null) {
            return ['queued' => 0, 'running' => 0, 'completed' => 0, 'failed' => 0, 'cancelled' => 0];
        }

        $sql = 'SELECT status, COUNT(*) AS total FROM crawl_jobs';
        $params = [];
        if ($domain !== null) {
            $sql .= ' WHERE seed_url LIKE :seed_domain';
            $params['seed_domain'] = '%://' . $domain . '%';
        }
        $sql .= ' GROUP BY status';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $name => $value) {
            $stmt->bindValue(':' . $name, $value, PDO::PARAM_STR);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $summary = ['queued' => 0, 'running' => 0, 'completed' => 0, 'failed' => 0, 'cancelled' => 0];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (array_key_exists($status, $summary)) {
                $summary[$status] = (int) ($row['total'] ?? 0);
            }
        }

        return $summary;
    }

    /** @return array<string,int> */
    private function urlSummary(?string $domain): array
    {
        if ($this->pdo === null) {
            return ['queued' => 0, 'running' => 0, 'indexed' => 0, 'skipped' => 0, 'failed' => 0];
        }

        $sql = 'SELECT status, COUNT(*) AS total FROM crawl_urls';
        $params = [];
        if ($domain !== null) {
            $sql .= ' WHERE domain = :domain';
            $params['domain'] = $domain;
        }
        $sql .= ' GROUP BY status';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $name => $value) {
            $stmt->bindValue(':' . $name, $value, PDO::PARAM_STR);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $summary = ['queued' => 0, 'running' => 0, 'indexed' => 0, 'skipped' => 0, 'failed' => 0];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (array_key_exists($status, $summary)) {
                $summary[$status] = (int) ($row['total'] ?? 0);
            }
        }

        return $summary;
    }

    private function indexedPagesTotal(?string $domain): int
    {
        if ($this->pdo === null) {
            return 0;
        }

        $sql = 'SELECT COUNT(*) FROM indexed_pages';
        $params = [];
        if ($domain !== null) {
            $sql .= ' WHERE domain = :domain';
            $params['domain'] = $domain;
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $name => $value) {
            $stmt->bindValue(':' . $name, $value, PDO::PARAM_STR);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /** @return array<int,array<string,mixed>> */
    private function pausedDomains(?string $domain, int $limit): array
    {
        $all = $this->domainPolicyService?->all() ?? [];
        $rows = [];
        foreach ($all as $entry) {
            if (!is_array($entry) || !((bool) ($entry['paused'] ?? false))) {
                continue;
            }
            $entryDomain = strtolower((string) ($entry['domain'] ?? ''));
            if ($entryDomain === '') {
                continue;
            }
            if ($domain !== null && $entryDomain !== $domain) {
                continue;
            }

            $rows[] = [
                'domain' => $entryDomain,
                'reason' => (string) ($entry['reason'] ?? ''),
                'paused_at' => (string) ($entry['paused_at'] ?? ''),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => strcmp((string) $a['domain'], (string) $b['domain']));
        return array_slice($rows, 0, $limit);
    }

    /** @return array<int,array<string,mixed>> */
    private function recentErrors(?string $domain, int $limit): array
    {
        if ($this->pdo === null) {
            return [];
        }

        $sql = 'SELECT domain, url, http_status, error_message, updated_at
                FROM crawl_urls
                WHERE (status = :failedStatus OR (http_status IS NOT NULL AND http_status >= 400) OR (error_message IS NOT NULL AND error_message <> ""))';
        $params = ['failedStatus' => 'failed'];

        if ($domain !== null) {
            $sql .= ' AND domain = :domain';
            $params['domain'] = $domain;
        }

        $sql .= ' ORDER BY updated_at DESC, id DESC LIMIT :limit';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $name => $value) {
            $stmt->bindValue(':' . $name, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        /** @var array<int,array<string,mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows;
    }

    private function hasData(array $jobs, array $urls, int $indexedTotal, array $domains, array $paused, array $advice, array $errors): bool
    {
        return array_sum($jobs) > 0 || array_sum($urls) > 0 || $indexedTotal > 0 || $domains !== [] || $paused !== [] || $advice !== [] || $errors !== [];
    }
}
