<?php

declare(strict_types=1);

namespace Browser\Models;

use Browser\Core\Database;
use PDO;

final class CrawlJob
{
    public static function create(string $seedUrl, int $maxDepth, int $maxPages): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO crawl_jobs (seed_url, status, max_depth, max_pages, pages_found, pages_indexed)
             VALUES (:seed_url, :status, :max_depth, :max_pages, 0, 0)'
        );
        $statement->execute([
            'seed_url' => $seedUrl,
            'status' => 'queued',
            'max_depth' => max(0, $maxDepth),
            'max_pages' => max(1, $maxPages),
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function queued(?int $limit = null): array
    {
        $sql = 'SELECT * FROM crawl_jobs WHERE status = :status ORDER BY id ASC';
        if ($limit !== null) {
            $sql .= ' LIMIT :limit';
        }
        $statement = Database::connection()->prepare($sql);
        $statement->bindValue(':status', 'queued');
        if ($limit !== null) {
            $statement->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        }
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function find(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM crawl_jobs WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public static function markRunning(int $id): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE crawl_jobs SET status = :status, started_at = NOW(), error_message = NULL WHERE id = :id'
        );
        $statement->execute(['status' => 'running', 'id' => $id]);
    }

    public static function markFinished(int $id, string $status, int $pagesFound, int $pagesIndexed, ?string $errorMessage = null): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE crawl_jobs
             SET status = :status, pages_found = :pages_found, pages_indexed = :pages_indexed,
                 error_message = :error_message, finished_at = NOW()
             WHERE id = :id'
        );
        $statement->execute([
            'status' => $status,
            'pages_found' => max(0, $pagesFound),
            'pages_indexed' => max(0, $pagesIndexed),
            'error_message' => $errorMessage,
            'id' => $id,
        ]);
    }

    public static function statusSummary(): array
    {
        $statement = Database::connection()->query(
            'SELECT status, COUNT(*) AS total, MAX(created_at) AS last_created_at FROM crawl_jobs GROUP BY status ORDER BY status'
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
