CREATE TABLE IF NOT EXISTS payment_lab_operations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 reference VARCHAR(80) NOT NULL UNIQUE,
 kind ENUM('payin','payout') NOT NULL,
 amount INT UNSIGNED NOT NULL,
 environment VARCHAR(20) NOT NULL,
 status ENUM('created','initiated','notification_unverified','processing','succeeded','failed','unknown') NOT NULL DEFAULT 'created',
 provider_session_id VARCHAR(160) NULL,
 recipient_channel VARCHAR(20) NULL,
 recipient_phone VARCHAR(30) NULL,
 provider_message VARCHAR(250) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
