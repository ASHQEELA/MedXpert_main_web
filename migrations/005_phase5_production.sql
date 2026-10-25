-- Phase 5: audit log and consent records.
-- Safe to run once on an existing database. Back up first.

CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NULL,
    action VARCHAR(60) NOT NULL,
    target_type VARCHAR(40) NOT NULL DEFAULT '',
    target_id INT NULL,
    details VARCHAR(500) NOT NULL DEFAULT '',
    ip VARCHAR(45) NOT NULL DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at),
    INDEX idx_admin (admin_id),
    INDEX idx_action (action),
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

ALTER TABLE users ADD COLUMN consented_at DATETIME NULL;
ALTER TABLE users ADD COLUMN consent_version VARCHAR(20) NULL;

-- Accounts made before this release are asked to accept on their next login.
