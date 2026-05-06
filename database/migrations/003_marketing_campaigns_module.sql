CREATE TABLE IF NOT EXISTS marketing_campaigns (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(190) NOT NULL,
    description TEXT NULL,
    channel ENUM('seo', 'sem', 'social', 'email', 'content', 'display', 'other') NOT NULL DEFAULT 'other',
    budget DECIMAL(12,2) NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    status ENUM('draft', 'active', 'paused', 'completed', 'cancelled') NOT NULL DEFAULT 'draft',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_marketing_campaigns_client (client_id),
    INDEX idx_marketing_campaigns_status (status),
    CONSTRAINT fk_marketing_campaigns_client
        FOREIGN KEY (client_id) REFERENCES marketing_clients(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
