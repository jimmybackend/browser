CREATE TABLE IF NOT EXISTS crawl_urls (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    crawl_job_id BIGINT UNSIGNED NOT NULL,
    url TEXT NOT NULL,
    url_hash CHAR(64) NOT NULL,
    domain VARCHAR(190) NOT NULL,
    depth SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('queued', 'running', 'indexed', 'skipped', 'failed') NOT NULL DEFAULT 'queued',
    http_status SMALLINT UNSIGNED NULL,
    error_message TEXT NULL,
    discovered_from_url TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_crawl_url_job_hash (crawl_job_id, url_hash),
    INDEX idx_crawl_urls_job_status_depth (crawl_job_id, status, depth),
    FOREIGN KEY (crawl_job_id) REFERENCES crawl_jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
