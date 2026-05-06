<?php

declare(strict_types=1);

namespace Browser\Models;

use Browser\Core\Database;

final class IndexedPage
{
    public static function upsert(array $data): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO indexed_pages (url_hash, url, domain, title, description, content_text, language, status, last_crawled_at)
             VALUES (:url_hash, :url, :domain, :title, :description, :content_text, :language, :status, NOW())
             ON DUPLICATE KEY UPDATE
                url = VALUES(url),
                domain = VALUES(domain),
                title = VALUES(title),
                description = VALUES(description),
                content_text = VALUES(content_text),
                language = VALUES(language),
                status = VALUES(status),
                last_crawled_at = NOW()'
        );

        $statement->execute([
            'url_hash' => $data['url_hash'],
            'url' => $data['url'],
            'domain' => $data['domain'],
            'title' => $data['title'],
            'description' => $data['description'],
            'content_text' => $data['content_text'],
            'language' => $data['language'],
            'status' => 'indexed',
        ]);
    }
}
