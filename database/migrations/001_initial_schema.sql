CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    username VARCHAR(60) NOT NULL UNIQUE,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(120) NULL,
    status ENUM('active', 'pending', 'suspended', 'deleted') NOT NULL DEFAULT 'pending',
    email_verified_at DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    session_token_hash CHAR(64) NOT NULL UNIQUE,
    ip_address VARBINARY(16) NULL,
    user_agent VARCHAR(500) NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS password_resets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roles (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_roles (
    user_id BIGINT UNSIGNED NOT NULL,
    role_id SMALLINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id),
    INDEX idx_user_roles_role_id (role_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_preferences (
    user_id BIGINT UNSIGNED PRIMARY KEY,
    search_history_enabled TINYINT(1) NOT NULL DEFAULT 0,
    email_notifications_enabled TINYINT(1) NOT NULL DEFAULT 1,
    theme ENUM('system', 'light', 'dark') NOT NULL DEFAULT 'system',
    language VARCHAR(10) NOT NULL DEFAULT 'es',
    timezone VARCHAR(60) NOT NULL DEFAULT 'America/Mexico_City',
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(120) NOT NULL,
    entity_type VARCHAR(80) NULL,
    entity_id VARCHAR(80) NULL,
    ip_address VARBINARY(16) NULL,
    user_agent VARCHAR(500) NULL,
    metadata JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_user_id (user_id),
    INDEX idx_audit_action (action),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mail_domains (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    domain_name VARCHAR(190) NOT NULL UNIQUE,
    status ENUM('active', 'pending', 'disabled') NOT NULL DEFAULT 'pending',
    dkim_public_key TEXT NULL,
    dkim_private_key_encrypted TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mailboxes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    domain_id BIGINT UNSIGNED NOT NULL,
    local_part VARCHAR(100) NOT NULL,
    full_address VARCHAR(190) NOT NULL UNIQUE,
    quota_mb INT UNSIGNED NOT NULL DEFAULT 1024,
    status ENUM('active', 'disabled', 'suspended') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (domain_id) REFERENCES mail_domains(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mailbox_folders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mailbox_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    system_name ENUM('inbox', 'sent', 'drafts', 'trash', 'spam', 'archive', 'custom') NOT NULL DEFAULT 'custom',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_mailbox_folder (mailbox_id, name),
    FOREIGN KEY (mailbox_id) REFERENCES mailboxes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS email_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mailbox_id BIGINT UNSIGNED NOT NULL,
    folder_id BIGINT UNSIGNED NOT NULL,
    message_uuid CHAR(36) NOT NULL UNIQUE,
    direction ENUM('inbound', 'outbound') NOT NULL,
    from_address VARCHAR(190) NOT NULL,
    to_addresses JSON NOT NULL,
    cc_addresses JSON NULL,
    bcc_addresses JSON NULL,
    subject VARCHAR(255) NULL,
    body_text MEDIUMTEXT NULL,
    body_html MEDIUMTEXT NULL,
    raw_headers MEDIUMTEXT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    is_starred TINYINT(1) NOT NULL DEFAULT 0,
    spam_score DECIMAL(6,3) NULL,
    received_at DATETIME NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_mailbox_folder (mailbox_id, folder_id),
    INDEX idx_email_created_at (created_at),
    FOREIGN KEY (mailbox_id) REFERENCES mailboxes(id) ON DELETE CASCADE,
    FOREIGN KEY (folder_id) REFERENCES mailbox_folders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS email_attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message_id BIGINT UNSIGNED NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    checksum_sha256 CHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES email_messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mail_aliases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mailbox_id BIGINT UNSIGNED NOT NULL,
    alias_address VARCHAR(190) NOT NULL UNIQUE,
    status ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mailbox_id) REFERENCES mailboxes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS search_queries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    query_hash CHAR(64) NOT NULL,
    query_text_encrypted TEXT NULL,
    results_count INT UNSIGNED NOT NULL DEFAULT 0,
    ip_address VARBINARY(16) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_search_user_id (user_id),
    INDEX idx_search_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS indexed_pages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    url_hash CHAR(64) NOT NULL UNIQUE,
    url TEXT NOT NULL,
    domain VARCHAR(190) NOT NULL,
    title VARCHAR(255) NULL,
    description TEXT NULL,
    content_text LONGTEXT NULL,
    language VARCHAR(10) NULL,
    status ENUM('pending', 'indexed', 'blocked', 'failed') NOT NULL DEFAULT 'pending',
    last_crawled_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    FULLTEXT KEY ft_page_content (title, description, content_text)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS crawl_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    seed_url TEXT NOT NULL,
    status ENUM('queued', 'running', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'queued',
    max_depth TINYINT UNSIGNED NOT NULL DEFAULT 1,
    pages_found INT UNSIGNED NOT NULL DEFAULT 0,
    pages_indexed INT UNSIGNED NOT NULL DEFAULT 0,
    error_message TEXT NULL,
    started_at DATETIME NULL,
    finished_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS marketing_clients (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(190) NOT NULL,
    contact_name VARCHAR(120) NULL,
    contact_email VARCHAR(190) NULL,
    contact_phone VARCHAR(60) NULL,
    website VARCHAR(255) NULL,
    status ENUM('active', 'prospect', 'paused', 'inactive') NOT NULL DEFAULT 'prospect',
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_marketing_clients_status (status),
    INDEX idx_marketing_clients_company (company_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
    FOREIGN KEY (client_id) REFERENCES marketing_clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS marketing_leads (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id BIGINT UNSIGNED NULL,
    campaign_id BIGINT UNSIGNED NULL,
    name VARCHAR(160) NOT NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(60) NULL,
    source VARCHAR(120) NULL,
    status ENUM('new', 'contacted', 'qualified', 'converted', 'lost') NOT NULL DEFAULT 'new',
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_marketing_leads_client (client_id),
    INDEX idx_marketing_leads_campaign (campaign_id),
    INDEX idx_marketing_leads_status (status),
    FOREIGN KEY (client_id) REFERENCES marketing_clients(id) ON DELETE SET NULL,
    FOREIGN KEY (campaign_id) REFERENCES marketing_campaigns(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS marketing_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id BIGINT UNSIGNED NULL,
    campaign_id BIGINT UNSIGNED NULL,
    lead_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(120) NOT NULL,
    event_data JSON NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_marketing_events_type (event_type),
    INDEX idx_marketing_events_client (client_id),
    INDEX idx_marketing_events_campaign (campaign_id),
    INDEX idx_marketing_events_lead (lead_id),
    FOREIGN KEY (client_id) REFERENCES marketing_clients(id) ON DELETE SET NULL,
    FOREIGN KEY (campaign_id) REFERENCES marketing_campaigns(id) ON DELETE SET NULL,
    FOREIGN KEY (lead_id) REFERENCES marketing_leads(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
