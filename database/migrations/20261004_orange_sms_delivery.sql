ALTER TABLE orange_sms_logs ADD COLUMN IF NOT EXISTS delivery_status VARCHAR(32) NULL,
 ADD COLUMN IF NOT EXISTS delivery_received_at DATETIME NULL;
CREATE TABLE IF NOT EXISTS orange_sms_receipts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 resource_id VARCHAR(190) NOT NULL,
 delivery_status VARCHAR(32) NOT NULL,
 recipient_masked VARCHAR(32) NOT NULL,
 received_at DATETIME NOT NULL DEFAULT (UTC_TIMESTAMP()),
 UNIQUE KEY receipt_once(resource_id,delivery_status), INDEX(resource_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
