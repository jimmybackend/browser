<?php

declare(strict_types=1);

namespace Browser\Models;

use Browser\Core\Database;
use PDO;

final class CrawlUrl
{
    public static function enqueue(int $jobId, string $url, string $urlHash, string $domain, int $depth, ?string $discoveredFromUrl): bool
    {
        $statement = Database::connection()->prepare(
            'INSERT IGNORE INTO crawl_urls (crawl_job_id, url, url_hash, domain, depth, status, discovered_from_url)
             VALUES (:crawl_job_id, :url, :url_hash, :domain, :depth, :status, :discovered_from_url)'
        );

        $statement->execute([
            'crawl_job_id' => $jobId,
            'url' => $url,
            'url_hash' => $urlHash,
            'domain' => $domain,
            'depth' => max(0, $depth),
            'status' => 'queued',
            'discovered_from_url' => $discoveredFromUrl,
        ]);

        return $statement->rowCount() > 0;
    }

    public static function nextQueued(int $jobId): ?array
    {
        $rows = self::queuedByJob($jobId, 1);

        return $rows[0] ?? null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function queuedByJob(int $jobId, int $limit = 100): array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM crawl_urls WHERE crawl_job_id = :crawl_job_id AND status = :status ORDER BY depth ASC, id ASC LIMIT :limit'
        );
        $statement->bindValue(':crawl_job_id', $jobId, PDO::PARAM_INT);
        $statement->bindValue(':status', 'queued', PDO::PARAM_STR);
        $statement->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    public static function markRunning(int $id): void
    {
        $statement = Database::connection()->prepare('UPDATE crawl_urls SET status = :status WHERE id = :id');
        $statement->execute(['status' => 'running', 'id' => $id]);
    }

    public static function markDone(int $id, string $status, ?int $httpStatus = null, ?string $error = null): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE crawl_urls SET status = :status, http_status = :http_status, error_message = :error_message WHERE id = :id'
        );
        $statement->bindValue(':status', $status);
        $statement->bindValue(':http_status', $httpStatus, $httpStatus === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':error_message', $error, $error === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();
    }

    public static function countByJob(int $jobId): int
    {
        $statement = Database::connection()->prepare('SELECT COUNT(*) FROM crawl_urls WHERE crawl_job_id = :crawl_job_id');
        $statement->execute(['crawl_job_id' => $jobId]);

        return (int) $statement->fetchColumn();
    }
}
