-- Database schema for Microsoft Calendar Sync application

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    display_name VARCHAR(255),
    microsoft_user_id VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sync_configurations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    source_email VARCHAR(255) NOT NULL,
    target_email VARCHAR(255) NOT NULL,
    source_type ENUM('google', 'microsoft') DEFAULT 'microsoft',
    target_type ENUM('google', 'microsoft') DEFAULT 'microsoft',
    sync_direction ENUM('source_to_target', 'target_to_source', 'bidirectional') DEFAULT 'bidirectional',
    sync_frequency_minutes INT DEFAULT 15,
    is_active BOOLEAN DEFAULT TRUE,
    last_sync_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_active (user_id, is_active),
    INDEX idx_sync_frequency (sync_frequency_minutes)
);

CREATE TABLE IF NOT EXISTS calendar_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sync_config_id INT NOT NULL,
    microsoft_event_id VARCHAR(255) NOT NULL,
    subject VARCHAR(500),
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    is_all_day BOOLEAN DEFAULT FALSE,
    show_as ENUM('free', 'tentative', 'busy', 'oof', 'workingElsewhere') DEFAULT 'busy',
    source_email VARCHAR(255) NOT NULL,
    target_email VARCHAR(255) NOT NULL,
    sync_direction ENUM('source_to_target', 'target_to_source') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sync_config_id) REFERENCES sync_configurations(id) ON DELETE CASCADE,
    UNIQUE KEY unique_event_sync (microsoft_event_id, sync_config_id),
    INDEX idx_sync_config (sync_config_id),
    INDEX idx_time_range (start_time, end_time),
    INDEX idx_source_target (source_email, target_email)
);

CREATE TABLE IF NOT EXISTS sync_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sync_config_id INT NOT NULL,
    sync_type ENUM('full', 'incremental', 'manual') NOT NULL,
    status ENUM('success', 'error', 'partial') NOT NULL,
    events_processed INT DEFAULT 0,
    events_created INT DEFAULT 0,
    events_updated INT DEFAULT 0,
    events_deleted INT DEFAULT 0,
    error_message TEXT,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (sync_config_id) REFERENCES sync_configurations(id) ON DELETE CASCADE,
    INDEX idx_sync_config_status (sync_config_id, status),
    INDEX idx_started_at (started_at)
);

CREATE TABLE IF NOT EXISTS application_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT INTO application_settings (setting_key, setting_value, description) VALUES
('default_sync_frequency', '15', 'Default sync frequency in minutes'),
('max_sync_range_days', '30', 'Maximum days to sync in the future'),
('sync_buffer_minutes', '5', 'Buffer time in minutes for sync operations'),
('log_retention_days', '30', 'Number of days to keep sync logs')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
