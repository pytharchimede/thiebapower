-- Apply once before deploying the application changes.
ALTER TABLE rentals ADD COLUMN checkout_token CHAR(32) NULL UNIQUE,
 ADD COLUMN checkout_payment_url VARCHAR(1000) NULL;
CREATE TABLE system_notifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 event_key VARCHAR(150) NOT NULL UNIQUE,
 type VARCHAR(60) NOT NULL,
 permission VARCHAR(60) NOT NULL,
 title VARCHAR(190) NOT NULL,
 message VARCHAR(500) NOT NULL,
 link VARCHAR(500) NOT NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 resolved_at DATETIME NULL,
 KEY idx_notification_active(active,created_at)
);
CREATE TABLE system_notification_reads (
 notification_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL,
 read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(notification_id,user_id),
 FOREIGN KEY(notification_id) REFERENCES system_notifications(id) ON DELETE CASCADE,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);
