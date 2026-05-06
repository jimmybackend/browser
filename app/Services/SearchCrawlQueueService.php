<?php

declare(strict_types=1);

namespace Browser\Services;

use Browser\Core\Database;
use Browser\Models\UserRole;
use Throwable;

final class SearchCrawlQueueService
{
    private const RECENT_JOB_HOURS = 24;
    private const GUEST_DAILY_LIMIT = 2;
    private const USER_DAILY_LIMIT = 20;
    private const ADMIN_DAILY_LIMIT = 100;
    private const DOMAIN_HOURLY_LIMIT_GUEST = 1;
    private const DOMAIN_HOURLY_LIMIT_USER = 3;
    private const DOMAIN_HOURLY_LIMIT_ADMIN = 10;

    public function __construct(private readonly SearchService $searchService = new SearchService())
    {
    }

    public function maybeQueueCrawlForDomain(string $query, ?int $userId, ?string $ip): void
    {
        if (trim($query) === '') {
            return;
        }

        if (!$this->isEnabledForActor($userId)) {
            return;
        }

        try {
            $seedUrl = $this->searchService->resolveNavigableUrl($query);
            if ($seedUrl === null) {
                return;
            }

            $domain = (string) (parse_url($seedUrl, PHP_URL_HOST) ?? '');
            if ($domain === '') {
                return;
            }

            if ($this->domainAlreadyIndexed($domain)) {
                return;
            }

            if ($this->hasRecentJob($domain)) {
                return;
            }

            if (!$this->withinRateLimits($domain, $userId, $ip)) {
                return;
            }

            $this->createQueuedJob($seedUrl, $userId, $ip);
        } catch (Throwable $exception) {
            error_log('[SearchCrawlQueueService] queue skipped: ' . $this->safeLogMessage($exception->getMessage()));
        }
    }


    private function createQueuedJob(string $seedUrl, ?int $userId, ?string $ip): void
    {
        $sourceTag = $userId !== null
            ? 'autoq:user:' . $userId
            : 'autoq:ip:' . hash('sha256', (string) $ip);

        $statement = Database::connection()->prepare(
            'INSERT INTO crawl_jobs (seed_url, status, max_depth, max_pages, pages_found, pages_indexed, error_message)
             VALUES (:seed_url, :status, :max_depth, :max_pages, 0, 0, :error_message)'
        );

        $statement->execute([
            'seed_url' => $seedUrl,
            'status' => 'queued',
            'max_depth' => $this->maxDepth(),
            'max_pages' => $this->maxPages(),
            'error_message' => $sourceTag,
        ]);
    }
    private function isEnabledForActor(?int $userId): bool
    {
        $enabled = filter_var($_ENV['CRAWL_AUTO_QUEUE_ENABLED'] ?? false, FILTER_VALIDATE_BOOL);
        if ($enabled !== true) {
            return false;
        }

        if ($userId === null) {
            return filter_var($_ENV['CRAWL_AUTO_QUEUE_FOR_GUESTS'] ?? false, FILTER_VALIDATE_BOOL) === true;
        }

        return true;
    }

    private function maxPages(): int
    {
        return max(1, (int) ($_ENV['CRAWL_AUTO_QUEUE_MAX_PAGES'] ?? 10));
    }

    private function maxDepth(): int
    {
        return max(0, (int) ($_ENV['CRAWL_AUTO_QUEUE_MAX_DEPTH'] ?? 1));
    }

    private function domainAlreadyIndexed(string $domain): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1 FROM indexed_pages WHERE domain = :domain AND status = :status LIMIT 1'
        );
        $statement->execute(['domain' => $domain, 'status' => 'indexed']);

        return (bool) $statement->fetchColumn();
    }

    private function hasRecentJob(string $domain): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT seed_url FROM crawl_jobs WHERE created_at >= (NOW() - INTERVAL :hours HOUR)'
        );
        $statement->execute([
            'hours' => self::RECENT_JOB_HOURS,
        ]);

        foreach ($statement->fetchAll(\PDO::FETCH_COLUMN) ?: [] as $seedUrl) {
            $jobHost = strtolower((string) parse_url((string) $seedUrl, PHP_URL_HOST));
            if ($jobHost === strtolower($domain)) {
                return true;
            }
        }

        return false;
    }

    private function withinRateLimits(string $domain, ?int $userId, ?string $ip): bool
    {
        $isAdmin = $userId !== null && UserRole::userHasRole($userId, 'admin');

        if ($this->countByDomainLastHour($domain) >= $this->domainHourlyLimit($userId, $isAdmin)) {
            return false;
        }

        if ($userId !== null) {
            return $this->countByUserLastDay($userId) < $this->dailyLimit($userId, $isAdmin);
        }

        if ($ip === null || $ip === '') {
            return false;
        }

        return $this->countByIpLastDay($ip) < $this->dailyLimit(null, false);
    }

    private function domainHourlyLimit(?int $userId, bool $isAdmin): int
    {
        if ($isAdmin) {
            return self::DOMAIN_HOURLY_LIMIT_ADMIN;
        }

        if ($userId !== null) {
            return self::DOMAIN_HOURLY_LIMIT_USER;
        }

        return self::DOMAIN_HOURLY_LIMIT_GUEST;
    }

    private function dailyLimit(?int $userId, bool $isAdmin): int
    {
        if ($isAdmin) {
            return self::ADMIN_DAILY_LIMIT;
        }

        if ($userId !== null) {
            return self::USER_DAILY_LIMIT;
        }

        return self::GUEST_DAILY_LIMIT;
    }

    private function countByUserLastDay(int $userId): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM crawl_jobs WHERE created_at >= (NOW() - INTERVAL 1 DAY) AND error_message = :tag'
        );
        $statement->execute(['tag' => 'autoq:user:' . $userId]);

        return (int) $statement->fetchColumn();
    }

    private function countByIpLastDay(string $ip): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM crawl_jobs WHERE created_at >= (NOW() - INTERVAL 1 DAY) AND error_message = :tag'
        );
        $statement->execute(['tag' => 'autoq:ip:' . hash('sha256', $ip)]);

        return (int) $statement->fetchColumn();
    }

    private function countByDomainLastHour(string $domain): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM crawl_jobs
             WHERE created_at >= (NOW() - INTERVAL 1 HOUR)
               AND seed_url LIKE :domain_like'
        );
        $statement->execute(['domain_like' => '%://' . $domain . '%']);

        return (int) $statement->fetchColumn();
    }

    private function safeLogMessage(string $message): string
    {
        return mb_substr(preg_replace('/[^\P{C}\n\t]/u', '', $message) ?? 'unknown error', 0, 200);
    }
}
