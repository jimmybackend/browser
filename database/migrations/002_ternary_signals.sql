CREATE TABLE IF NOT EXISTS ternary_signal_definitions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    signal_key VARCHAR(120) NOT NULL UNIQUE,
    module VARCHAR(80) NOT NULL,
    positive_label VARCHAR(120) NOT NULL,
    neutral_label VARCHAR(120) NOT NULL,
    negative_label VARCHAR(120) NOT NULL,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ternary_signal_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    signal_key VARCHAR(120) NOT NULL,
    signal_value TINYINT SIGNED NOT NULL,
    entity_type VARCHAR(80) NULL,
    entity_id VARCHAR(80) NULL,
    user_id BIGINT UNSIGNED NULL,
    source ENUM('system', 'ai', 'user', 'admin') NOT NULL DEFAULT 'system',
    confidence DECIMAL(5,4) NULL,
    reason_code VARCHAR(120) NULL,
    human_note TEXT NULL,
    metadata JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_signal_value CHECK (signal_value IN (-1, 0, 1)),
    CONSTRAINT chk_confidence_value CHECK (confidence IS NULL OR (confidence >= 0 AND confidence <= 1)),
    CONSTRAINT fk_ternary_signal_events_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL,
    INDEX idx_ternary_signal_events_signal_key (signal_key),
    INDEX idx_ternary_signal_events_signal_value (signal_value),
    INDEX idx_ternary_signal_events_entity (entity_type, entity_id),
    INDEX idx_ternary_signal_events_user_id (user_id),
    INDEX idx_ternary_signal_events_created_at (created_at)
);
