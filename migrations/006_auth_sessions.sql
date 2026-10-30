-- Phase 6: login sessions kept in the database.
-- Safe to run once on an existing database. Back up first.
-- Until you run this file the app keeps working with PHP sessions only.

CREATE TABLE IF NOT EXISTS auth_sessions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    remember TINYINT(1) NOT NULL DEFAULT 0,
    ip VARCHAR(45) NOT NULL DEFAULT '',
    user_agent VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    UNIQUE KEY uq_token (token_hash),
    INDEX idx_user (user_id),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
