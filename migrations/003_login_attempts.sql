-- Run once on an existing database. Adds the table used for login rate limiting.
CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    kind VARCHAR(10) NOT NULL DEFAULT 'login',
    ip VARCHAR(45) NOT NULL,
    email VARCHAR(100) NOT NULL DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_kind_ip_time (kind, ip, created_at),
    INDEX idx_kind_ip_email_time (kind, ip, email, created_at)
) ENGINE=InnoDB;
