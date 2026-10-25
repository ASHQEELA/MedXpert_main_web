-- Phase 4: slot calendars, prescriptions, notifications, account tokens, order tracking.
-- Safe to run once on an existing database. Back up first.

CREATE TABLE IF NOT EXISTS doctor_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    weekday TINYINT NOT NULL COMMENT '0 = Sunday ... 6 = Saturday',
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    slot_minutes SMALLINT NOT NULL DEFAULT 30,
    UNIQUE KEY uniq_doctor_day (doctor_id, weekday),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Give every existing doctor Monday to Friday, 09:00 to 17:00, 30 minute slots
INSERT IGNORE INTO doctor_schedules (doctor_id, weekday, start_time, end_time, slot_minutes)
SELECT d.id, w.weekday, '09:00:00', '17:00:00', 30
FROM doctors d
JOIN (SELECT 1 AS weekday UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) w;

ALTER TABLE medicine_orders ADD COLUMN prescription_image VARCHAR(255) NULL AFTER payment_method;

CREATE TABLE IF NOT EXISTS order_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    status VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order (order_id),
    FOREIGN KEY (order_id) REFERENCES medicine_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO order_status_history (order_id, status, created_at)
SELECT id, 'pending', created_at FROM medicine_orders;

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    body VARCHAR(500) NOT NULL DEFAULT '',
    link VARCHAR(200) NOT NULL DEFAULT '',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_read (user_id, is_read),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL;

CREATE TABLE IF NOT EXISTS user_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    kind ENUM('reset', 'verify') NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_hash (token_hash),
    INDEX idx_user_kind (user_id, kind),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
